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

namespace local_rolesaudit\output;

use local_rolesaudit\local\exporter;
use local_rolesaudit\local\matrix;
use local_rolesaudit\local\permissions;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable shell for the dynamic Compare & edit page.
 *
 * All the data (roles, full capability catalogue, the permission matrix for
 * every role and the archetype defaults) is serialised to JSON and embedded in
 * the page; the client-side module does the filtering and rendering.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compare_page implements renderable, templatable {
    /** @var bool Whether the current user may edit permissions. */
    protected $editable;

    /**
     * Constructor.
     *
     * @param bool $editable
     */
    public function __construct(bool $editable) {
        $this->editable = $editable;
    }

    /**
     * Build the data payload and template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Default-first ordering: the roles the settings pre-select lead the list,
        // which drives the tag strip, the table columns and the CSV export alike.
        $roles = matrix::sort_roles_default_first(matrix::get_roles());

        $roledata = [];
        foreach ($roles as $role) {
            $roledata[] = [
                'id' => (int) $role->id,
                'shortname' => $role->shortname,
                'localname' => $role->localname,
                'archetype' => (string) $role->archetype,
            ];
        }

        $archetypes = array_map(static fn($r) => (string) $r->archetype, $roles);

        // Permission value labels, plus a couple of UI strings the module needs.
        $labels = permissions::menu();
        $labels['empty'] = get_string('nodifferences', 'local_rolesaudit');
        $labels['edithint'] = get_string('clicktoedit', 'local_rolesaudit');
        // Labels for the buttons the module adds to every filter line.
        $labels['selectall'] = get_string('selectall');
        $labels['selectnone'] = get_string('deselectall');
        $labels['searchtoggle'] = get_string('filter_searchtoggle', 'local_rolesaudit');
        // Tooltip of the "(selected/total)" counter the module appends to every line label.
        $labels['filtercount'] = get_string('filter_count', 'local_rolesaudit');
        // Labels for the scroll arrows the module adds on either side of every strip.
        $labels['scrollleft'] = get_string('filter_scrollleft', 'local_rolesaudit');
        $labels['scrollright'] = get_string('filter_scrollright', 'local_rolesaudit');
        $labels['scrollhint'] = get_string('filter_scrollhint', 'local_rolesaudit');
        // Feedback for the save/load filter set buttons.
        $labels['filterssaved'] = get_string('filters_saved', 'local_rolesaudit');
        $labels['filtersloaded'] = get_string('filters_loaded', 'local_rolesaudit');
        $labels['filtersnosaved'] = get_string('filters_nosaved', 'local_rolesaudit');
        // CSV export: headers, the per-role "modified" column pattern and the yes/no values.
        $labels['csvcapability'] = get_string('csv_capability', 'local_rolesaudit');
        $labels['csvdescription'] = get_string('csv_description', 'local_rolesaudit');
        $labels['csvmodified'] = get_string('csv_modified', 'local_rolesaudit');
        $labels['yes'] = get_string('yes');
        $labels['no'] = get_string('no');

        $data = [
            'roles' => $roledata,
            'capabilities' => matrix::get_capability_catalog(),
            'matrix' => (object) matrix::get_all_matrix(),
            'defaults' => (object) matrix::get_archetype_default_maps($archetypes),
            'activeroles' => matrix::default_active_role_ids($roles),
            'activetypes' => ['moodle'],
            'labels' => (object) $labels,
            'editable' => $this->editable,
            'sesskey' => sesskey(),
            // Filename the client-side CSV export downloads to, same convention as the JSON export.
            'csvfilename' => exporter::filename('csv'),
        ];

        $json = json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        // Choices for the inline edit menu.
        $menu = [];
        foreach (permissions::menu() as $value => $label) {
            $menu[] = ['value' => $value, 'label' => $label];
        }

        return [
            'datajson' => $json,
            'editable' => $this->editable,
            'permissionmenu' => $menu,
        ];
    }
}
