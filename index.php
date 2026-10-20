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
 * Compare & edit page for the Roles Audit tool.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rolesaudit\output\compare_page;

require_login();

$context = context_system::instance();
admin_externalpage_setup('local_rolesaudit_compare');
require_capability('local/rolesaudit:manage', $context);

$PAGE->set_url(new moodle_url('/local/rolesaudit/index.php'));
$PAGE->set_title(get_string('nav_compare', 'local_rolesaudit'));
$PAGE->set_heading(get_string('pluginname', 'local_rolesaudit'));

$editable = has_capability('moodle/role:manage', $context);
$PAGE->requires->js_call_amd('local_rolesaudit/compare', 'init');

/** @var \local_rolesaudit\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_rolesaudit');

echo $OUTPUT->header();
echo $renderer->tabs('compare');
// The heading is rendered by the compare template, on the same row as its toolbar.
echo $renderer->render_compare_page(new compare_page($editable));
echo $OUTPUT->footer();
