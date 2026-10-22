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

use context_system;

/**
 * Safety guards applied to every permission write made through the tool.
 *
 * Two independent concerns:
 *  - lockout: a user must not remove their own access to the tool
 *    ({@see would_lock_out()});
 *  - escalation: a user must not grant more than they hold, and only a site
 *    administrator may grant a security-critical capability
 *    ({@see escalation_reason()}).
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guard {
    /** @var string Escalation reason: the capability is security-critical and the user is not a site admin. */
    const REASON_CRITICAL = 'critical';

    /** @var string Escalation reason: the user does not hold the capability they are granting. */
    const REASON_NOTHELD = 'notheld';

    /**
     * Capabilities that only a site administrator may grant through the tool.
     *
     * Deliberately distinct from {@see protected_capabilities()}: that list
     * guards against removing access, this one against handing it out. Each
     * entry either controls the whole site, lets its holder act as someone
     * else, or lets its holder hand out further rights - holding one is not
     * enough to be trusted with granting it to a whole role.
     *
     * @return string[]
     */
    public static function critical_capabilities(): array {
        return [
            // Site configuration.
            'moodle/site:config',
            // Acting as, creating or rewriting other accounts.
            'moodle/user:loginas',
            'moodle/user:create',
            'moodle/user:update',
            'moodle/user:delete',
            'moodle/site:uploadusers',
            // Handing out further rights.
            'moodle/role:manage',
            'moodle/role:assign',
            'moodle/role:override',
            'moodle/role:safeoverride',
            'local/rolesaudit:manage',
            // Programmatic access, which bypasses the interface altogether.
            'moodle/webservice:createtoken',
            'webservice/rest:use',
            'webservice/soap:use',
        ];
    }

    /**
     * Capabilities that grant access to this tool (and to role management).
     *
     * Downgrading any of these on a role the current user relies on could lock
     * them out of the very page they are using.
     *
     * @return string[]
     */
    public static function protected_capabilities(): array {
        return ['moodle/role:manage', 'local/rolesaudit:manage'];
    }

    /**
     * Whether applying a change would risk locking the current user out.
     *
     * Returns true when the target capability is one that grants access to the
     * tool, the current user holds the target role in the system context, and
     * the incoming value is anything other than "Allow". Site administrators
     * bypass capability checks entirely, so they are never at risk and always
     * return false.
     *
     * @param int $roleid Role being edited.
     * @param string $capability Capability being changed.
     * @param int $permission Incoming permission value.
     * @return bool
     */
    public static function would_lock_out(int $roleid, string $capability, int $permission): bool {
        global $USER;

        if ($permission === CAP_ALLOW) {
            return false;
        }
        if (!in_array($capability, self::protected_capabilities(), true)) {
            return false;
        }
        if (is_siteadmin()) {
            return false;
        }

        $systemcontext = context_system::instance();
        $userroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($userroles as $ra) {
            if ((int) $ra->roleid === $roleid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Why a change would let the current user escalate privileges, if it would.
     *
     * Only elevating changes are checked: setting "Allow", or leaving "Prohibit"
     * (lifting a system-level prohibit re-enables the overrides of every child
     * context). Such a change is refused when the capability is critical and the
     * user is not a site administrator, or when the user does not hold the
     * capability themselves at the system context - nobody can hand out more
     * than they have. Site administrators pass has_capability() for everything,
     * so only the critical rule can ever stop them, and it exempts them.
     *
     * @param string $capability Capability being changed.
     * @param int $current Permission currently stored for the role.
     * @param int $incoming Permission about to be stored.
     * @return string|null One of the REASON_* constants, or null when allowed.
     */
    public static function escalation_reason(string $capability, int $current, int $incoming): ?string {
        $elevating = $incoming === CAP_ALLOW || ($current === CAP_PROHIBIT && $incoming !== CAP_PROHIBIT);
        if (!$elevating || is_siteadmin()) {
            return null;
        }
        if (in_array($capability, self::critical_capabilities(), true)) {
            return self::REASON_CRITICAL;
        }
        if (!has_capability($capability, context_system::instance())) {
            return self::REASON_NOTHELD;
        }
        return null;
    }
}
