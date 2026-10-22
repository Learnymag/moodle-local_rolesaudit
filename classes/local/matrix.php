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
use core_collator;

/**
 * Data access layer for the Roles Audit tool.
 *
 * Everything here works at the SYSTEM context only: it reads and interprets the
 * role *definitions* stored in the core {role_capabilities} table, which is the
 * portable part of a role that can be transferred between Moodle instances.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix {
    /**
     * Shortnames pre-selected when the admin has never saved the setting.
     *
     * Doubles as the default value of the local_rolesaudit/defaultroles setting
     * in settings.php, so the list lives in exactly one place.
     *
     * @var string[]
     */
    const FACTORY_DEFAULT_ROLES = ['manager', 'coursecreator', 'editingteacher', 'teacher', 'student'];

    /** @var array Cache of archetype default maps, keyed by archetype. */
    protected static $archetypedefaults = [];

    /**
     * All roles with their context-fixed display names.
     *
     * @return array Role records keyed by id, each with a ->localname property.
     */
    public static function get_roles(): array {
        $context = context_system::instance();
        return role_fix_names(get_all_roles($context), $context);
    }

    /**
     * All capabilities defined on the site (raw {capabilities} rows).
     *
     * @return array Capability records keyed by capability name.
     */
    public static function get_capabilities(): array {
        return get_all_capabilities();
    }

    /**
     * Distinct components that own at least one capability, sorted.
     *
     * @return array [component => component] suitable for a select menu.
     */
    public static function get_components(): array {
        $components = [];
        foreach (self::get_capabilities() as $cap) {
            $components[$cap['component']] = $cap['component'];
        }
        ksort($components);
        return $components;
    }

    /**
     * The stored permission matrix for the given roles at the system context.
     *
     * Only rows that actually exist in {role_capabilities} are returned; any
     * (role, capability) pair with no row defaults to CAP_INHERIT and is simply
     * absent from the map. Loading every stored row for the selected roles in a
     * single query avoids huge IN() clauses on the capability list.
     *
     * @param int[] $roleids
     * @return array [roleid => [capability => permission]]
     */
    public static function get_matrix(array $roleids): array {
        global $DB;

        $matrix = [];
        foreach ($roleids as $roleid) {
            $matrix[(int) $roleid] = [];
        }
        if (empty($roleids)) {
            return $matrix;
        }

        $systemcontext = context_system::instance();
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $params = $roleparams + ['ctx' => $systemcontext->id];

        $sql = "SELECT id, roleid, capability, permission
                  FROM {role_capabilities}
                 WHERE contextid = :ctx
                   AND roleid $rolesql";
        $rows = $DB->get_records_sql($sql, $params);

        foreach ($rows as $row) {
            $matrix[(int) $row->roleid][$row->capability] = (int) $row->permission;
        }
        return $matrix;
    }

    /**
     * Effective stored permission for a single (role, capability) pair.
     *
     * @param array $matrix Result of {@see get_matrix()}.
     * @param int $roleid
     * @param string $capability
     * @return int One of the CAP_* constants (CAP_INHERIT when not stored).
     */
    public static function get_permission(array $matrix, int $roleid, string $capability): int {
        return $matrix[$roleid][$capability] ?? CAP_INHERIT;
    }

    /**
     * The permission value stored for a single (role, capability) pair.
     *
     * A pair with no row in {role_capabilities} is not "unknown" but CAP_INHERIT
     * ("Not set"), which is how the whole tool presents a missing row.
     *
     * @param int $roleid
     * @param string $capability
     * @return int One of the CAP_* constants.
     */
    public static function stored_permission(int $roleid, string $capability): int {
        global $DB;

        // The (roleid, contextid, capability) index is unique, so there is at
        // most one row and get_field() can never be ambiguous here.
        $stored = $DB->get_field('role_capabilities', 'permission', [
            'contextid' => context_system::instance()->id,
            'roleid' => $roleid,
            'capability' => $capability,
        ]);

        // Cast: get_field() hands back a string on some drivers, and callers
        // compare the result against the CAP_* integers with ===.
        return $stored === false ? CAP_INHERIT : (int) $stored;
    }

    /**
     * Store a permission value for one (role, capability) pair at the system context.
     *
     * Deliberately NOT role_change_permission(): that function exists to keep the
     * number of overrides in a context hierarchy to a minimum, and at the system
     * context (path "/1", no parent) its short-circuits become silent no-ops - an
     * existing CAP_PROHIBIT is never replaced by CAP_ALLOW or CAP_PREVENT, and
     * CAP_PREVENT is never stored when no row exists yet.
     *
     * assign_capability() is what core's own "Define role" page uses (see
     * admin/roles/classes/capability_table_with_risks::save_changes()): it stores
     * the value verbatim, delegates CAP_INHERIT to unassign_capability(), fires
     * the core capability_assigned / capability_unassigned events and purges the
     * role caches - everything role_change_permission() did on top.
     *
     * @param int $roleid
     * @param string $capability Must exist in {capabilities}; callers validate it.
     * @param int $permission One of the CAP_* constants.
     */
    public static function write_permission(int $roleid, string $capability, int $permission): void {
        assign_capability($capability, $permission, $roleid, context_system::instance()->id, true);
    }

    /**
     * The archetype default capability map, cached per archetype for the request.
     *
     * @param string $archetype Role archetype (may be empty for custom roles).
     * @return array [capability => permission]
     */
    public static function get_archetype_defaults(string $archetype): array {
        if (!array_key_exists($archetype, self::$archetypedefaults)) {
            self::$archetypedefaults[$archetype] = get_default_capabilities($archetype);
        }
        return self::$archetypedefaults[$archetype];
    }

    /**
     * Whether a stored permission differs from the role archetype's default.
     *
     * A custom role with no archetype has an empty default map, so any value
     * other than CAP_INHERIT counts as modified.
     *
     * @param string $archetype Role archetype.
     * @param string $capability Capability name.
     * @param int $permission Current stored permission value.
     * @return bool
     */
    public static function is_modified(string $archetype, string $capability, int $permission): bool {
        $defaults = self::get_archetype_defaults($archetype);
        $default = $defaults[$capability] ?? CAP_INHERIT;
        return (int) $default !== $permission;
    }

    /**
     * The archetype default permission value for a capability.
     *
     * @param string $archetype Role archetype.
     * @param string $capability Capability name.
     * @return int
     */
    public static function default_permission(string $archetype, string $capability): int {
        $defaults = self::get_archetype_defaults($archetype);
        return (int) ($defaults[$capability] ?? CAP_INHERIT);
    }

    /**
     * The stored permission matrix for ALL roles at the system context.
     *
     * Loaded in a single query with no role filter; only rows that actually
     * exist in {role_capabilities} are returned (missing pairs = CAP_INHERIT).
     *
     * @return array [roleid => [capability => permission]]
     */
    public static function get_all_matrix(): array {
        global $DB;

        $systemcontext = context_system::instance();
        $sql = "SELECT id, roleid, capability, permission
                  FROM {role_capabilities}
                 WHERE contextid = :ctx";
        $rows = $DB->get_records_sql($sql, ['ctx' => $systemcontext->id]);

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[(int) $row->roleid][$row->capability] = (int) $row->permission;
        }
        return $matrix;
    }

    /**
     * Split a capability name into its type / plugin / action parts.
     *
     * A capability name has the form "type/plugin:action" (one slash, one
     * colon), e.g. "mod/quiz:attempt" → type=mod, plugin=quiz, action=attempt,
     * or "moodle/course:view" → type=moodle, plugin=course, action=view.
     * Unusual names fall back to type only, with the whole name as the action.
     *
     * @param string $name Capability name.
     * @return array [type => string, plugin => string, action => string].
     */
    public static function parse_capability(string $name): array {
        $slash = strpos($name, '/');
        $colon = strpos($name, ':');
        if ($slash === false || $colon === false || $colon < $slash) {
            // Fallback for any unusual capability name.
            return [
                'type' => $slash !== false ? substr($name, 0, $slash) : $name,
                'plugin' => '',
                'action' => $name,
            ];
        }
        return [
            'type' => substr($name, 0, $slash),
            'plugin' => substr($name, $slash + 1, $colon - $slash - 1),
            'action' => substr($name, $colon + 1),
        ];
    }

    /**
     * Best-effort frankenstyle component that owns a capability, derived from
     * its name, e.g. "mod/quiz:attempt" → "mod_quiz", "moodle/course:view" →
     * "core".
     *
     * Used on import to attribute a capability that does not exist on the
     * current site to a plugin: its {capabilities} row - and thus its real
     * component - is unavailable there, so the component is inferred from the
     * name instead.
     *
     * @param string $name Capability name.
     * @return string Frankenstyle component, or '' when it cannot be derived.
     */
    public static function capability_component(string $name): string {
        $parts = self::parse_capability($name);
        if ($parts['type'] === 'moodle' || $parts['type'] === '') {
            return 'core';
        }
        if ($parts['plugin'] === '') {
            return '';
        }
        return $parts['type'] . '_' . $parts['plugin'];
    }

    /**
     * Full catalogue of capabilities, parsed for the dynamic filter tags.
     *
     * Sorted by capability name once here, so the table rows, the autocomplete
     * suggestions and the capability tag strip - which all iterate this list -
     * share the same alphabetical order (type, then plugin, then action).
     *
     * @return array List of [name, type, plugin, action, def].
     */
    public static function get_capability_catalog(): array {
        $catalog = [];
        foreach (self::get_capabilities() as $name => $info) {
            $parts = self::parse_capability($name);
            $catalog[] = [
                'name' => $name,
                'type' => $parts['type'],
                'plugin' => $parts['plugin'],
                'action' => $parts['action'],
                'def' => get_capability_string($name),
            ];
        }
        usort($catalog, static fn($a, $b) => strcmp($a['name'], $b['name']));
        return $catalog;
    }

    /**
     * Archetype default maps for the given archetypes.
     *
     * @param string[] $archetypes
     * @return array [archetype => [capability => permission]]
     */
    public static function get_archetype_default_maps(array $archetypes): array {
        $maps = [];
        foreach (array_unique($archetypes) as $archetype) {
            $maps[(string) $archetype] = self::get_archetype_defaults((string) $archetype);
        }
        return $maps;
    }

    /**
     * The role shortnames that are pre-selected (active) when the page loads.
     *
     * Matching on shortname rather than archetype keeps custom roles built on a
     * standard archetype out of the initial selection.
     *
     * @return string[]
     */
    public static function default_role_shortnames(): array {
        $raw = get_config('local_rolesaudit', 'defaultroles');
        if ($raw === false) {
            // Setting never saved: fall back to the factory list.
            return self::FACTORY_DEFAULT_ROLES;
        }
        // An emptied setting is a deliberate "select nothing" and stays empty.
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
    }

    /**
     * Ids of the roles that should be active by default.
     *
     * @param array $roles Role records (with ->shortname).
     * @return int[]
     */
    public static function default_active_role_ids(array $roles): array {
        $wanted = self::default_role_shortnames();
        $ids = [];
        foreach ($roles as $role) {
            if (in_array($role->shortname, $wanted, true)) {
                $ids[] = (int) $role->id;
            }
        }
        return $ids;
    }

    /**
     * Roles ordered for display: the ones pre-selected by the settings first,
     * then all the others, each group sorted alphabetically on its display name.
     *
     * Grouping on the *setting* rather than on the current selection keeps the tag
     * strip stable while the user toggles roles on and off.
     *
     * @param array $roles Role records keyed by id (with ->shortname and ->localname).
     * @return array The same records with the same keys, reordered.
     */
    public static function sort_roles_default_first(array $roles): array {
        $wanted = self::default_role_shortnames();

        $defaults = [];
        $others = [];
        foreach ($roles as $id => $role) {
            if (in_array($role->shortname, $wanted, true)) {
                $defaults[$id] = $role;
            } else {
                $others[$id] = $role;
            }
        }

        core_collator::asort_objects_by_property($defaults, 'localname', core_collator::SORT_NATURAL);
        core_collator::asort_objects_by_property($others, 'localname', core_collator::SORT_NATURAL);

        // Keys are distinct role ids, so the union simply concatenates the two groups.
        return $defaults + $others;
    }
}
