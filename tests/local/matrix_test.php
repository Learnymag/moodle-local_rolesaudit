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

/**
 * Tests for reading and writing role capability permissions at the system context.
 *
 * These lock down the behaviour that core's role_change_permission() got wrong
 * for this tool: at the system context it silently refused to replace an
 * existing CAP_PROHIBIT, and never stored CAP_PREVENT when no row existed yet.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_rolesaudit\local\matrix::write_permission
 * @covers      \local_rolesaudit\local\matrix::stored_permission
 */
final class matrix_test extends \advanced_testcase {

    /** @var string A real capability, unrelated to this tool's own access. */
    const CAPABILITY = 'moodle/course:view';

    /**
     * A pair with no stored row reads back as "not set".
     */
    public function test_stored_permission_defaults_to_inherit(): void {
        $this->resetAfterTest();
        $roleid = $this->getDataGenerator()->create_role();

        $this->assertSame(CAP_INHERIT, matrix::stored_permission($roleid, self::CAPABILITY));
    }

    /**
     * Every one of the four values survives a write/read round trip from "not set".
     *
     * CAP_PREVENT is the interesting one here: role_change_permission() dropped
     * it whenever no row existed yet.
     */
    public function test_write_permission_stores_every_value_from_not_set(): void {
        $this->resetAfterTest();
        $roleid = $this->getDataGenerator()->create_role();

        foreach (permissions::valid_values() as $value) {
            // Back to "not set" first, so the write under test is the only variable.
            matrix::write_permission($roleid, self::CAPABILITY, CAP_INHERIT);

            matrix::write_permission($roleid, self::CAPABILITY, $value);

            $this->assertSame($value, matrix::stored_permission($roleid, self::CAPABILITY),
                'Writing permission ' . $value . ' from "not set" did not persist.');
        }
    }

    /**
     * A stored CAP_PROHIBIT can be replaced by any other value.
     *
     * This is the reported bug: role_change_permission() returned early on an
     * existing prohibit, so nothing was written and the old value came back on
     * the next page load.
     */
    public function test_prohibit_can_be_replaced_by_any_value(): void {
        $this->resetAfterTest();
        $roleid = $this->getDataGenerator()->create_role();

        foreach (permissions::valid_values() as $value) {
            matrix::write_permission($roleid, self::CAPABILITY, CAP_PROHIBIT);
            $this->assertSame(CAP_PROHIBIT, matrix::stored_permission($roleid, self::CAPABILITY),
                'Test setup failed: prohibit was not stored.');

            matrix::write_permission($roleid, self::CAPABILITY, $value);

            $this->assertSame($value, matrix::stored_permission($roleid, self::CAPABILITY),
                'Leaving prohibit for permission ' . $value . ' did not persist.');
        }
    }

    /**
     * Writing "not set" removes the row rather than storing a zero.
     */
    public function test_inherit_removes_the_row(): void {
        global $DB;

        $this->resetAfterTest();
        $roleid = $this->getDataGenerator()->create_role();
        $conditions = [
            'contextid' => \context_system::instance()->id,
            'roleid' => $roleid,
            'capability' => self::CAPABILITY,
        ];

        matrix::write_permission($roleid, self::CAPABILITY, CAP_ALLOW);
        $this->assertTrue($DB->record_exists('role_capabilities', $conditions));

        matrix::write_permission($roleid, self::CAPABILITY, CAP_INHERIT);

        $this->assertFalse($DB->record_exists('role_capabilities', $conditions));
        $this->assertSame(CAP_INHERIT, matrix::stored_permission($roleid, self::CAPABILITY));
    }

    /**
     * Writes are visible to the matrix loader the compare screen renders from.
     */
    public function test_written_value_appears_in_the_matrix(): void {
        $this->resetAfterTest();
        $roleid = $this->getDataGenerator()->create_role();

        matrix::write_permission($roleid, self::CAPABILITY, CAP_PROHIBIT);
        matrix::write_permission($roleid, self::CAPABILITY, CAP_PREVENT);

        $loaded = matrix::get_matrix([$roleid]);

        $this->assertSame(CAP_PREVENT, matrix::get_permission($loaded, $roleid, self::CAPABILITY));
    }
}
