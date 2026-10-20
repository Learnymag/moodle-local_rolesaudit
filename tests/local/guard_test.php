<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_rolesaudit\local;

use local_rolesaudit\external\set_permission;

/**
 * Tests for the privilege escalation guard, on both write paths of the tool.
 *
 * The scenario the security review asked for: a user holding the tool's own
 * capability, but not site configuration, tries to grant moodle/site:config to
 * their own role through an import - and must be refused.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_rolesaudit\local\guard::escalation_reason
 * @covers      \local_rolesaudit\local\importer::preview
 * @covers      \local_rolesaudit\local\importer::apply
 * @covers      \local_rolesaudit\external\set_permission::execute
 */
final class guard_test extends \advanced_testcase {

    /** @var string A capability the test user never holds and that is not critical. */
    const NOTHELD = 'moodle/course:create';

    /**
     * Create a user holding a fresh role at the system context, and log in as them.
     *
     * @param string[] $capabilities Capabilities allowed on that role.
     * @return \stdClass The role record (with id and shortname).
     */
    private function login_with_role(array $capabilities): \stdClass {
        global $DB;

        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        return $DB->get_record('role', ['id' => $roleid], 'id, shortname', MUST_EXIST);
    }

    /**
     * The shortname of a role.
     *
     * @param int $roleid
     * @return string
     */
    private function shortname(int $roleid): string {
        global $DB;
        return $DB->get_field('role', 'shortname', ['id' => $roleid], MUST_EXIST);
    }

    /**
     * An import payload changing one capability on one role.
     *
     * @param string $shortname Target role shortname.
     * @param string $capability
     * @param int $permission
     * @return array
     */
    private function payload(string $shortname, string $capability, int $permission): array {
        return ['roles' => [$shortname => ['capabilities' => [$capability => $permission]]]];
    }

    /**
     * Without moodle/role:manage, apply() refuses outright and writes nothing.
     */
    public function test_apply_requires_role_manage(): void {
        $this->resetAfterTest();
        $role = $this->login_with_role(['local/rolesaudit:manage']);
        $preview = importer::preview($this->payload($role->shortname, 'moodle/site:config', CAP_ALLOW));

        try {
            importer::apply($preview['changes']);
            $this->fail('apply() wrote permissions without moodle/role:manage.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('moodle/role:manage', $e->getMessage());
        }
        $this->assertSame(CAP_INHERIT, matrix::stored_permission((int) $role->id, 'moodle/site:config'));
    }

    /**
     * With role management but no site configuration, granting site:config to
     * one's own role is refused as critical, shown as such in the preview, and
     * never written.
     */
    public function test_import_refuses_granting_site_config_to_own_role(): void {
        $this->resetAfterTest();
        $role = $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage']);
        $preview = importer::preview($this->payload($role->shortname, 'moodle/site:config', CAP_ALLOW));

        $this->assertCount(1, $preview['changes']);
        $this->assertSame(guard::REASON_CRITICAL, $preview['changes'][0]['blocked']);

        $result = importer::apply($preview['changes']);

        $this->assertSame(0, $result['applied']);
        $this->assertCount(1, $result['skippedescalation']);
        $this->assertSame(CAP_INHERIT, matrix::stored_permission((int) $role->id, 'moodle/site:config'));
    }

    /**
     * A non-critical capability the user does not hold cannot be granted.
     */
    public function test_import_refuses_granting_a_capability_not_held(): void {
        $this->resetAfterTest();
        $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage']);
        $target = $this->getDataGenerator()->create_role();
        $shortname = $this->shortname($target);
        $preview = importer::preview($this->payload($shortname, self::NOTHELD, CAP_ALLOW));

        $this->assertSame(guard::REASON_NOTHELD, $preview['changes'][0]['blocked']);
        $result = importer::apply($preview['changes']);

        $this->assertSame(0, $result['applied']);
        $this->assertCount(1, $result['skippedescalation']);
        $this->assertSame(CAP_INHERIT, matrix::stored_permission($target, self::NOTHELD));
    }

    /**
     * A capability the user holds can be granted to another role.
     */
    public function test_import_allows_granting_a_capability_held(): void {
        $this->resetAfterTest();
        $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage', self::NOTHELD]);
        $target = $this->getDataGenerator()->create_role();
        $shortname = $this->shortname($target);
        $preview = importer::preview($this->payload($shortname, self::NOTHELD, CAP_ALLOW));

        $this->assertNull($preview['changes'][0]['blocked']);
        $result = importer::apply($preview['changes']);

        $this->assertSame(1, $result['applied']);
        $this->assertSame(CAP_ALLOW, matrix::stored_permission($target, self::NOTHELD));
    }

    /**
     * Lifting a prohibit is an elevation too: it re-enables child-context overrides.
     */
    public function test_import_refuses_lifting_a_prohibit_not_held(): void {
        $this->resetAfterTest();
        $target = $this->getDataGenerator()->create_role();
        matrix::write_permission($target, self::NOTHELD, CAP_PROHIBIT);
        $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage']);
        $shortname = $this->shortname($target);
        $preview = importer::preview($this->payload($shortname, self::NOTHELD, CAP_INHERIT));

        $result = importer::apply($preview['changes']);

        $this->assertCount(1, $result['skippedescalation']);
        $this->assertSame(CAP_PROHIBIT, matrix::stored_permission($target, self::NOTHELD));
    }

    /**
     * Restricting is never an escalation, even on a critical capability.
     */
    public function test_restricting_is_never_an_escalation(): void {
        $this->resetAfterTest();
        $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage']);

        $this->assertNull(guard::escalation_reason('moodle/site:config', CAP_ALLOW, CAP_PROHIBIT));
        $this->assertNull(guard::escalation_reason('moodle/site:config', CAP_INHERIT, CAP_PREVENT));
        $this->assertNull(guard::escalation_reason(self::NOTHELD, CAP_ALLOW, CAP_INHERIT));
    }

    /**
     * A site administrator may grant a critical capability.
     */
    public function test_admin_can_grant_a_critical_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $target = $this->getDataGenerator()->create_role();
        $shortname = $this->shortname($target);
        $preview = importer::preview($this->payload($shortname, 'moodle/site:config', CAP_ALLOW));

        $this->assertNull($preview['changes'][0]['blocked']);
        $result = importer::apply($preview['changes']);

        $this->assertSame(1, $result['applied']);
        $this->assertSame(CAP_ALLOW, matrix::stored_permission($target, 'moodle/site:config'));
    }

    /**
     * The inline editor's web service enforces the same guard.
     */
    public function test_set_permission_refuses_granting_site_config(): void {
        $this->resetAfterTest();
        $role = $this->login_with_role(['local/rolesaudit:manage', 'moodle/role:manage']);

        try {
            set_permission::execute((int) $role->id, 'moodle/site:config', CAP_ALLOW);
            $this->fail('set_permission granted a critical capability to a non-admin.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_escalation_critical', $e->errorcode);
        }
        $this->assertSame(CAP_INHERIT, matrix::stored_permission((int) $role->id, 'moodle/site:config'));
    }
}
