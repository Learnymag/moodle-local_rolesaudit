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

namespace local_rolesaudit\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_rolesaudit\local\guard;
use local_rolesaudit\local\matrix;
use local_rolesaudit\local\permissions;
use moodle_exception;

/**
 * External function to change a role capability permission at the system context.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_permission extends external_api {

    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid' => new external_value(PARAM_INT, 'The role id to change'),
            'capability' => new external_value(PARAM_CAPABILITY, 'The capability name to change'),
            'permission' => new external_value(PARAM_INT,
                'The new permission value: 0 (not set), 1 (allow), -1 (prevent) or -1000 (prohibit)'),
        ]);
    }

    /**
     * Change the stored permission of a capability for a role.
     *
     * @param int $roleid
     * @param string $capability
     * @param int $permission
     * @return array
     */
    public static function execute(int $roleid, string $capability, int $permission): array {
        global $DB;

        [
            'roleid' => $roleid,
            'capability' => $capability,
            'permission' => $permission,
        ] = self::validate_parameters(self::execute_parameters(), [
            'roleid' => $roleid,
            'capability' => $capability,
            'permission' => $permission,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/rolesaudit:manage', $context);
        require_capability('moodle/role:manage', $context);

        // Validate the incoming values against real records / accepted constants.
        if (!permissions::is_valid($permission)) {
            throw new invalid_parameter_exception('Unknown permission value: ' . $permission);
        }
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        if (get_capability_info($capability) === null) {
            throw new invalid_parameter_exception('Unknown capability: ' . $capability);
        }

        // Never let a manager lock themselves out of role management.
        if (guard::would_lock_out($roleid, $capability, $permission)) {
            throw new moodle_exception('error_lockout', 'local_rolesaudit');
        }

        // Nor grant more than they are entitled to.
        $before = matrix::stored_permission($roleid, $capability);
        $reason = guard::escalation_reason($capability, $before, $permission);
        if ($reason !== null) {
            throw new moodle_exception('error_escalation_' . $reason, 'local_rolesaudit');
        }

        // Canonical writer: stores the value verbatim (and deletes the row for
        // CAP_INHERIT) and invalidates the access caches for us. See
        // matrix::write_permission() for why role_change_permission() is avoided.
        if ($before !== $permission) {
            matrix::write_permission($roleid, $capability, $permission);
        }

        // Always report what the database actually holds, never what was asked
        // for: the UI patches the edited cell straight from this response.
        $stored = matrix::stored_permission($roleid, $capability);
        $ismodified = matrix::is_modified((string) $role->archetype, $capability, $stored);

        // Log the change for traceability, but only when something really changed.
        if ($stored !== $before) {
            \local_rolesaudit\event\permission_changed::create([
                'context' => $context,
                'objectid' => $roleid,
                'other' => [
                    'capability' => $capability,
                    'oldpermission' => $before,
                    'permission' => $stored,
                ],
            ])->trigger();
        }

        return [
            'permission' => $stored,
            'label' => permissions::label($stored),
            'cssclass' => permissions::cssclass($stored),
            'ismodified' => $ismodified,
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'permission' => new external_value(PARAM_INT, 'The stored permission value'),
            'label' => new external_value(PARAM_TEXT, 'The localised label for the new value'),
            'cssclass' => new external_value(PARAM_TEXT, 'The CSS class for the new value'),
            'ismodified' => new external_value(PARAM_BOOL, 'Whether the value differs from the archetype default'),
        ]);
    }
}
