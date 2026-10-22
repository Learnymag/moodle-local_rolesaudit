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
 * Plugin administration pages are defined here.
 *
 * The Roles Audit tool lives under Site administration > Users > Permissions
 * as a small category holding its settings page plus its three pages:
 * Compare/Edit, Export and Import.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_rolesaudit\local\matrix;

// The whole tool, not only its settings, is reserved to site administrators:
// none of its pages is registered for anyone without moodle/site:config.
if ($hassiteconfig) {
    // Group the pages under their own category in the "Permissions" node.
    $category = new admin_category(
        'local_rolesaudit',
        get_string('pluginname', 'local_rolesaudit')
    );
    $ADMIN->add('roles', $category);

    $capability = 'local/rolesaudit:manage';

    $settingspage = new admin_settingpage(
        'local_rolesaudit_settings',
        get_string('settings_heading', 'local_rolesaudit'),
        'moodle/site:config'
    );

    // Only build the settings when the full tree is needed, i.e. when the page is
    // actually being rendered.
    if ($ADMIN->fulltree) {
        // What the plugin is for is documented in the README, not repeated here:
        // this page is for its settings.

        // The role list needs the core tables, so never before they exist.
        if (!during_initial_install()) {
            $choices = [];
            foreach (matrix::get_roles() as $role) {
                $choices[$role->shortname] = $role->localname . ' (' . $role->shortname . ')';
            }

            $settingspage->add(new admin_setting_configmultiselect(
                'local_rolesaudit/defaultroles',
                get_string('setting_defaultroles', 'local_rolesaudit'),
                get_string('setting_defaultroles_desc', 'local_rolesaudit'),
                matrix::FACTORY_DEFAULT_ROLES,
                $choices
            ));
        }
    }

    $ADMIN->add('local_rolesaudit', $settingspage);

    $ADMIN->add('local_rolesaudit', new admin_externalpage(
        'local_rolesaudit_compare',
        get_string('nav_compare', 'local_rolesaudit'),
        $CFG->wwwroot . '/local/rolesaudit/index.php',
        $capability
    ));

    $ADMIN->add('local_rolesaudit', new admin_externalpage(
        'local_rolesaudit_export',
        get_string('nav_export', 'local_rolesaudit'),
        $CFG->wwwroot . '/local/rolesaudit/export.php',
        $capability
    ));

    $ADMIN->add('local_rolesaudit', new admin_externalpage(
        'local_rolesaudit_import',
        get_string('nav_import', 'local_rolesaudit'),
        $CFG->wwwroot . '/local/rolesaudit/import.php',
        $capability
    ));
}
