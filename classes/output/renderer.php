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
 * Collection of methods to handle rendering visual aspects of pages.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rolesaudit\output;

use plugin_renderer_base;
use tabobject;
use moodle_url;

/**
 * Plugin renderer.
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the shared tab bar for the three tool pages.
     *
     * @param string $selected One of 'compare', 'export', 'import'.
     * @return string
     */
    public function tabs(string $selected): string {
        $tabs = [
            new tabobject(
                'compare',
                new moodle_url('/local/rolesaudit/index.php'),
                get_string('nav_compare', 'local_rolesaudit')
            ),
            new tabobject(
                'export',
                new moodle_url('/local/rolesaudit/export.php'),
                get_string('nav_export', 'local_rolesaudit')
            ),
            new tabobject(
                'import',
                new moodle_url('/local/rolesaudit/import.php'),
                get_string('nav_import', 'local_rolesaudit')
            ),
        ];
        return $this->tabtree($tabs, $selected);
    }

    /**
     * Render the dynamic Compare & edit page shell.
     *
     * @param compare_page $page
     * @return string
     */
    public function render_compare_page(compare_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_rolesaudit/compare', $data);
    }
}
