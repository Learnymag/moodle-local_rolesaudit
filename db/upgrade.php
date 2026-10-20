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

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * This plugin stores no data of its own (it edits the core {role_capabilities}
 * table through the core API), so its steps only ever adjust role definitions.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_rolesaudit_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026101500) {
        // The tool is now reserved to site administrators: local/rolesaudit:manage
        // no longer has the manager archetype. Withdraw the grant that the
        // install gave to manager-archetype roles at the system context, which
        // opened the set_permission web service to managers without the pages.
        // No capability check here: $USER is empty when upgrading from the CLI.
        $capability = 'local/rolesaudit:manage';
        $systemcontext = context_system::instance();
        $roles = $DB->get_records('role', ['archetype' => 'manager'], 'id', 'id');
        foreach ($roles as $role) {
            $permission = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $role->id,
                'contextid' => $systemcontext->id,
                'capability' => $capability,
            ]);
            if ($permission !== false && (int) $permission === CAP_ALLOW) {
                unassign_capability($capability, $role->id, $systemcontext->id);
            }
        }

        upgrade_plugin_savepoint(true, 2026101500, 'local', 'rolesaudit');
    }

    return true;
}
