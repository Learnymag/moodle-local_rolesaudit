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
 * Helper mapping the four capability permission values to labels and CSS classes.
 *
 * The permission integers are the core accesslib constants:
 * CAP_INHERIT (0), CAP_ALLOW (1), CAP_PREVENT (-1), CAP_PROHIBIT (-1000).
 * Because this tool always works at the system context, CAP_INHERIT is
 * presented as "Not set" (rather than "Inherit").
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permissions {

    /**
     * The permission values a user is allowed to pick, in display order.
     *
     * @return int[]
     */
    public static function valid_values(): array {
        return [CAP_INHERIT, CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT];
    }

    /**
     * Whether the given integer is one of the four accepted permission values.
     *
     * @param int $permission
     * @return bool
     */
    public static function is_valid(int $permission): bool {
        return in_array($permission, self::valid_values(), true);
    }

    /**
     * Localised label for a permission value (from the core "role" component).
     *
     * @param int $permission
     * @return string
     */
    public static function label(int $permission): string {
        $labels = [
            CAP_INHERIT => 'notset',
            CAP_ALLOW => 'allow',
            CAP_PREVENT => 'prevent',
            CAP_PROHIBIT => 'prohibit',
        ];
        $key = $labels[$permission] ?? 'notset';
        return get_string($key, 'core_role');
    }

    /**
     * CSS class used to colour a cell for a permission value.
     *
     * @param int $permission
     * @return string
     */
    public static function cssclass(int $permission): string {
        $classes = [
            CAP_INHERIT => 'notset',
            CAP_ALLOW => 'allow',
            CAP_PREVENT => 'prevent',
            CAP_PROHIBIT => 'prohibit',
        ];
        return 'rolesaudit-' . ($classes[$permission] ?? 'notset');
    }

    /**
     * The permission value → label map, for building select menus.
     *
     * @return array [int => string]
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::valid_values() as $value) {
            $menu[$value] = self::label($value);
        }
        return $menu;
    }
}
