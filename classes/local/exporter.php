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
 * Builds the export payload of role capability definitions.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /** The file format identifier, checked on import. */
    const FORMAT = 'local_rolesaudit';

    /** The file format version. Bumped to 2 when the "plugins" block was added. */
    const FORMAT_VERSION = 2;

    /**
     * Build the export data structure for the given roles.
     *
     * @param int[] $roleids Roles to export.
     * @param bool $onlymodified When true, only include capabilities whose value
     *                           differs from the role's archetype default.
     * @return array The payload, ready to be json-encoded.
     */
    public static function build(array $roleids, bool $onlymodified): array {
        global $CFG, $USER, $DB;

        $roles = matrix::get_roles();
        $matrix = matrix::get_matrix($roleids);

        $exportroles = [];
        $exportedcaps = [];
        foreach ($roleids as $roleid) {
            $roleid = (int) $roleid;
            if (!isset($roles[$roleid])) {
                continue;
            }
            $role = $roles[$roleid];
            $capabilities = [];
            foreach (($matrix[$roleid] ?? []) as $capability => $permission) {
                if ($onlymodified && !matrix::is_modified((string) $role->archetype, $capability, $permission)) {
                    continue;
                }
                $capabilities[$capability] = $permission;
                $exportedcaps[$capability] = true;
            }
            ksort($capabilities);

            $exportroles[$role->shortname] = [
                'archetype' => $role->archetype,
                'name' => $role->localname,
                'capabilities' => $capabilities,
            ];
        }

        return [
            'meta' => [
                'format' => self::FORMAT,
                'formatversion' => self::FORMAT_VERSION,
                'sourcesite' => $CFG->wwwroot,
                'sitename' => format_string($DB->get_field('course', 'fullname', ['id' => SITEID])),
                'moodleversion' => $CFG->version,
                'moodlerelease' => $CFG->release,
                'pluginversion' => get_config('local_rolesaudit', 'version'),
                'timestamp' => time(),
                'exportedby' => fullname($USER),
                'onlymodified' => $onlymodified,
                'context' => 'system',
            ],
            'plugins' => self::build_plugins(array_keys($exportedcaps)),
            'roles' => $exportroles,
        ];
    }

    /**
     * Build the inventory of plugins that own the exported capabilities.
     *
     * Keyed by the same normalised component that {@see matrix::capability_component()}
     * derives on import, so the import side can look each capability's owner up
     * directly. Records the installed version (and a display name) so the import
     * site can tell "plugin not installed" from "plugin present but older".
     *
     * @param string[] $capnames Names of the exported capabilities.
     * @return array [component => [version => int|null, name => string]]
     */
    private static function build_plugins(array $capnames): array {
        global $CFG;

        $plugins = [];
        foreach ($capnames as $capname) {
            $component = matrix::capability_component($capname);
            if ($component === '' || isset($plugins[$component])) {
                continue;
            }
            if ($component === 'core') {
                $plugins[$component] = [
                    'version' => (int) $CFG->version,
                    'name' => 'core',
                ];
                continue;
            }
            $version = get_config($component, 'version');
            $name = $component;
            try {
                $name = \core_plugin_manager::instance()->plugin_name($component);
            } catch (\Throwable $e) {
                // Best-effort display name only; fall back to the component.
                $name = $component;
            }
            $plugins[$component] = [
                'version' => $version === false ? null : (int) $version,
                'name' => $name,
            ];
        }
        ksort($plugins);
        return $plugins;
    }

    /**
     * Build a sensible download filename for an export.
     *
     * @param string $extension File extension, without the dot (json, csv...).
     * @return string
     */
    public static function filename(string $extension = 'json'): string {
        global $CFG;
        $host = preg_replace('/[^a-z0-9]+/i', '-', preg_replace('#^https?://#', '', $CFG->wwwroot));
        $host = trim($host, '-');
        return 'rolesaudit-' . $host . '-' . userdate(time(), '%Y%m%d-%H%M') . '.' . $extension;
    }
}
