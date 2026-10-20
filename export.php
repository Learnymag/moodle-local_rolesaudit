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
 * Export page for the Roles Audit tool.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rolesaudit\local\exporter;
use local_rolesaudit\local\matrix;

require_login();

$context = context_system::instance();
admin_externalpage_setup('local_rolesaudit_export');
require_capability('local/rolesaudit:manage', $context);

$PAGE->set_url(new moodle_url('/local/rolesaudit/export.php'));
$PAGE->set_title(get_string('nav_export', 'local_rolesaudit'));
$PAGE->set_heading(get_string('pluginname', 'local_rolesaudit'));

// Handle the download (POST + sesskey) before any output.
if (data_submitted()) {
    require_sesskey();
    $roleids = optional_param_array('roles', [], PARAM_INT);
    $onlymodified = optional_param('onlymodified', 0, PARAM_BOOL);

    if (!empty($roleids)) {
        $payload = exporter::build($roleids, (bool) $onlymodified);

        \local_rolesaudit\event\roles_exported::create([
            'context' => $context,
            'other' => ['rolecount' => count($payload['roles'])],
        ])->trigger();

        $json = json_encode($payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        \core\session\manager::write_close();
        send_file($json, exporter::filename(), 0, 0, true, true, 'application/json');
        die();
    }
}

// Build the role-picker data (shared shape with the compare page's first line).
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
$exportjson = json_encode([
    'roles' => $roledata,
    'activeroles' => matrix::default_active_role_ids($roles),
    // Labels of the scroll arrows the module adds on either side of the strip.
    'labels' => [
        'scrollleft' => get_string('filter_scrollleft', 'local_rolesaudit'),
        'scrollright' => get_string('filter_scrollright', 'local_rolesaudit'),
        'scrollhint' => get_string('filter_scrollhint', 'local_rolesaudit'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$PAGE->requires->js_call_amd('local_rolesaudit/compare', 'initExport');

/** @var \local_rolesaudit\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_rolesaudit');

echo $OUTPUT->header();
echo $renderer->tabs('export');
echo $OUTPUT->heading(get_string('nav_export', 'local_rolesaudit'), 3);
echo html_writer::div(get_string('export_intro', 'local_rolesaudit'), 'rolesaudit-intro');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/rolesaudit/export.php'))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Role tags line.
echo html_writer::start_div('rolesaudit-exportroles rolesaudit-filterline');
echo '<script type="application/json" id="rolesaudit-exportdata">' . $exportjson . '</script>';
echo html_writer::tag('span', get_string('filter_roles', 'local_rolesaudit'),
    ['class' => 'rolesaudit-filterlabel']);
// The wrapper hosts the scroll arrows and the edge fades, as on the compare page.
echo html_writer::start_div('rolesaudit-stripwrap');
echo html_writer::div('', 'rolesaudit-strip', ['data-line' => 'roles']);
echo html_writer::end_div();
echo html_writer::div('', 'rolesaudit-hiddenroles');
echo html_writer::end_div();

// Only-modified checkbox.
echo html_writer::start_div('rolesaudit-filterline');
echo html_writer::tag('label',
    html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'onlymodified', 'value' => 1])
    . ' ' . get_string('export_onlymodified', 'local_rolesaudit'),
    ['class' => 'rolesaudit-onlymodified']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('export_download', 'local_rolesaudit'),
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
