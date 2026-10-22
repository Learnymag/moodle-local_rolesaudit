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
 * Import page for the Roles Audit tool.
 *
 * Three steps: upload a file, preview the diff, then confirm and apply.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rolesaudit\form\import_form;
use local_rolesaudit\local\importer;
use local_rolesaudit\local\permissions;

require_login();

$context = context_system::instance();
admin_externalpage_setup('local_rolesaudit_import');
require_capability('local/rolesaudit:manage', $context);
// Importing writes role definitions: require core role management too, as the
// set_permission web service does. Checked for every step, since a preview the
// user could never apply is of no use.
require_capability('moodle/role:manage', $context);

$PAGE->set_url(new moodle_url('/local/rolesaudit/import.php'));
$PAGE->set_title(get_string('nav_import', 'local_rolesaudit'));
$PAGE->set_heading(get_string('pluginname', 'local_rolesaudit'));

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$token = optional_param('token', '', PARAM_ALPHANUM);

/** @var \local_rolesaudit\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_rolesaudit');

$mform = new import_form();

// Step 3: apply the previously previewed changes.
if ($confirm && $token && confirm_sesskey()) {
    $stash = $SESSION->local_rolesaudit_import ?? [];
    if (empty($stash[$token]['payload']) || $stash[$token]['time'] < time() - importer::PENDING_TTL) {
        throw new moodle_exception('error_sessionexpired', 'local_rolesaudit');
    }
    $payload = $stash[$token]['payload'];
    $preview = importer::preview($payload);

    // Which rows the user ticked. The JS collapses the checkboxes into a single
    // field before submitting: a large diff would otherwise be silently truncated
    // by PHP's max_input_vars and a wrong subset applied without any warning.
    // Without JS the individual changes[] checkboxes are posted instead.
    $packed = optional_param('changeskeys', null, PARAM_RAW);
    if ($packed !== null) {
        $keys = array_filter(explode("\n", str_replace("\r", '', $packed)), 'strlen');
    } else {
        $keys = optional_param_array('changes', [], PARAM_RAW);
    }
    // Keys are only ever compared against the freshly computed rows, never reused.
    $selected = array_flip($keys);
    $changes = array_values(array_filter($preview['changes'], static function ($change) use ($selected) {
        return isset($selected[$change['roleid'] . '|' . $change['capability']]);
    }));

    if (empty($changes)) {
        // Nothing to do: leave the stash alone so the token is not burnt for nothing.
        echo $OUTPUT->header();
        echo $renderer->tabs('import');
        echo $OUTPUT->heading(get_string('import_result', 'local_rolesaudit'), 3);
        echo $OUTPUT->notification(get_string('import_noselection', 'local_rolesaudit'), 'warning');
        echo html_writer::link(
            new moodle_url('/local/rolesaudit/import.php'),
            get_string('import_startover', 'local_rolesaudit'),
            ['class' => 'btn btn-secondary']
        );
        echo $OUTPUT->footer();
        die();
    }

    unset($SESSION->local_rolesaudit_import[$token]);

    $result = importer::apply($changes);

    \local_rolesaudit\event\roles_imported::create([
        'context' => $context,
        'other' => ['applied' => $result['applied']],
    ])->trigger();

    echo $OUTPUT->header();
    echo $renderer->tabs('import');
    echo $OUTPUT->heading(get_string('import_result', 'local_rolesaudit'), 3);
    echo $OUTPUT->notification(
        get_string('import_applied', 'local_rolesaudit', $result['applied']),
        'success'
    );
    if (!empty($result['skippedlockout'])) {
        echo $OUTPUT->notification(
            get_string('import_skippedlockout', 'local_rolesaudit', count($result['skippedlockout'])),
            'warning'
        );
    }
    if (!empty($result['skippedescalation'])) {
        echo $OUTPUT->notification(
            get_string('import_skippedescalation', 'local_rolesaudit', count($result['skippedescalation'])),
            'warning'
        );
    }
    if (!empty($result['failed'])) {
        echo $OUTPUT->notification(
            get_string('import_failed', 'local_rolesaudit', count($result['failed'])),
            'error'
        );
    }
    echo html_writer::link(
        new moodle_url('/local/rolesaudit/index.php'),
        get_string('backtocompare', 'local_rolesaudit'),
        ['class' => 'btn btn-secondary']
    );
    echo $OUTPUT->footer();
    die();
}

// Step 2: a file has been uploaded - parse it and show the preview.
if ($data = $mform->get_data()) {
    $json = $mform->get_file_content('importfile');
    if ($json === false) {
        throw new moodle_exception('error_badfile', 'local_rolesaudit');
    }
    $payload = importer::parse($json);
    $preview = importer::preview($payload);

    // Stash the parsed payload for the confirm step, keyed by a random token.
    // Only one pending import per session: a new upload replaces any abandoned
    // preview, so the session cannot grow with every file previewed.
    $token = random_string(20);
    $SESSION->local_rolesaudit_import = [$token => ['payload' => $payload, 'time' => time()]];

    // Rows the guard will refuse are shown but can never be ticked or counted.
    $applicable = count(array_filter($preview['changes'], static function ($change) {
        return $change['blocked'] === null;
    }));
    if ($applicable) {
        $PAGE->requires->js_call_amd('local_rolesaudit/compare', 'initImport');
    }

    echo $OUTPUT->header();
    echo $renderer->tabs('import');
    echo $OUTPUT->heading(get_string('import_preview', 'local_rolesaudit'), 3);

    // Source metadata.
    $meta = $payload['meta'];
    echo html_writer::div(
        get_string('import_source', 'local_rolesaudit', (object) [
            'site' => s($meta['sourcesite'] ?? ''),
            'release' => s($meta['moodlerelease'] ?? ''),
            'date' => is_numeric($meta['timestamp'] ?? null) ? userdate((int) $meta['timestamp']) : '',
        ]),
        'rolesaudit-intro'
    );

    // Summary counts.
    $summary = get_string('import_summary', 'local_rolesaudit', (object) [
        'changes' => count($preview['changes']),
        'unchanged' => $preview['unchanged'],
        'skippedroles' => count($preview['skippedroles']),
        'skippedcaps' => $preview['skippedcaps']['total'],
    ]);
    echo $OUTPUT->notification($summary, 'info');

    // Changes table, wrapped in the confirm form: one checkbox per row, all ticked
    // except the rows the guard refuses.
    if (!empty($preview['changes'])) {
        $total = $applicable;

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/rolesaudit/import.php'))->out(false),
            'class' => 'rolesaudit-importform',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);

        // Select all / deselect all + live counter. The count is rendered as its own
        // span so the JS only has to rewrite a number, never rebuild the sentence.
        // Pointless when every row is refused, and the JS is then not loaded.
        if ($applicable) {
            echo html_writer::start_div('rolesaudit-importtools');
            foreach (
                [
                'rolesaudit-import-selectall' => get_string('selectall'),
                'rolesaudit-import-selectnone' => get_string('deselectall'),
                ] as $action => $label
            ) {
                echo html_writer::tag('button', $label, [
                    'type' => 'button',
                    'class' => 'btn btn-sm btn-outline-secondary',
                    'data-action' => $action,
                ]);
            }
            echo html_writer::tag('span', get_string('import_selectedcount', 'local_rolesaudit', (object) [
                'selected' => html_writer::tag('span', $total, ['class' => 'rolesaudit-selcount']),
                'total' => $total,
            ]), ['class' => 'rolesaudit-importcount']);
            echo html_writer::end_div();
        }

        $table = new html_table();
        $table->head = [
            get_string('import_th_apply', 'local_rolesaudit'),
            get_string('field_role', 'local_rolesaudit'),
            get_string('th_capability', 'local_rolesaudit'),
            get_string('import_current', 'local_rolesaudit'),
            get_string('import_incoming', 'local_rolesaudit'),
        ];
        $table->attributes['class'] = 'table table-sm table-bordered rolesaudit-difftable';
        foreach ($preview['changes'] as $change) {
            $arialabel = get_string('import_selectrow', 'local_rolesaudit', (object) [
                'capability' => $change['capability'],
                'role' => $change['rolename'],
            ]);
            if ($change['blocked'] === null) {
                // The row key must survive the recompute done by the confirm step, so it
                // identifies the row rather than its position in the array.
                $checkbox = html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'name' => 'changes[]',
                    'value' => $change['roleid'] . '|' . $change['capability'],
                    'checked' => 'checked',
                    'class' => 'rolesaudit-importcheck',
                    'aria-label' => $arialabel,
                ]);
                $reason = '';
            } else {
                // No name and no rolesaudit-importcheck class: the row is never posted,
                // and the import JS (which selects on that class) leaves it alone.
                $checkbox = html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'disabled' => 'disabled',
                    'aria-label' => $arialabel,
                ]);
                $reason = html_writer::tag(
                    'span',
                    get_string('import_blocked_' . $change['blocked'], 'local_rolesaudit'),
                    ['class' => 'd-block small text-danger']
                );
            }
            $selectcell = new html_table_cell($checkbox);
            $selectcell->attributes['class'] = 'rolesaudit-selectcell';
            $capcell = $change['caplabel'] . html_writer::tag(
                'span',
                $change['capability'],
                ['class' => 'rolesaudit-capname d-block']
            ) . $reason;
            // Colour the current/new value cells like the compare table.
            $currentcell = new html_table_cell($change['currentlabel']);
            $currentcell->attributes['class'] = permissions::cssclass($change['current']);
            $incomingcell = new html_table_cell($change['incominglabel']);
            $incomingcell->attributes['class'] = permissions::cssclass($change['incoming']);
            $table->data[] = [$selectcell, $change['rolename'], $capcell, $currentcell, $incomingcell];
        }
        echo html_writer::table($table);

        if ($applicable) {
            echo html_writer::tag(
                'button',
                get_string('import_apply', 'local_rolesaudit'),
                ['type' => 'submit', 'class' => 'btn btn-primary']
            );
        }
        echo html_writer::end_tag('form');
    } else {
        echo $OUTPUT->notification(get_string('import_nochanges', 'local_rolesaudit'), 'info');
    }

    // Skipped roles / capabilities. These names come straight from the uploaded
    // file and html_writer::alist() does not escape its items: escape them here.
    if (!empty($preview['skippedroles'])) {
        echo $OUTPUT->heading(get_string('import_skippedroles', 'local_rolesaudit'), 4);
        echo html_writer::alist(array_map('s', $preview['skippedroles']));
    }

    $skipped = $preview['skippedcaps'];

    // Capabilities whose owning plugin is not installed on this site.
    if (!empty($skipped['notinstalled'])) {
        echo $OUTPUT->heading(get_string('import_skippedcaps_notinstalled', 'local_rolesaudit'), 4);
        foreach ($skipped['notinstalled'] as $component => $info) {
            echo html_writer::tag('p', get_string('import_skippedcaps_plugin', 'local_rolesaudit', (object) [
                'name' => s($info['name']),
                'component' => s($component),
            ]), ['class' => 'fw-bold mb-1']);
            echo html_writer::alist(array_map('s', $info['caps']));
        }
    }

    // Capabilities whose plugin is installed but older than the source site.
    if (!empty($skipped['older'])) {
        echo $OUTPUT->heading(get_string('import_skippedcaps_older', 'local_rolesaudit'), 4);
        foreach ($skipped['older'] as $component => $info) {
            echo html_writer::tag('p', get_string('import_skippedcaps_pluginolder', 'local_rolesaudit', (object) [
                'name' => s($info['name']),
                'component' => s($component),
                'localversion' => $info['localversion'],
                'fileversion' => $info['fileversion'],
            ]), ['class' => 'fw-bold mb-1']);
            echo html_writer::alist(array_map('s', $info['caps']));
        }
    }

    // Capabilities that are genuinely unknown here (renamed, removed or typo).
    if (!empty($skipped['unknown'])) {
        echo $OUTPUT->heading(get_string('import_skippedcaps_unknown', 'local_rolesaudit'), 4);
        echo html_writer::alist(array_map('s', $skipped['unknown']));
    }

    echo html_writer::link(
        new moodle_url('/local/rolesaudit/import.php'),
        get_string('import_startover', 'local_rolesaudit'),
        ['class' => 'btn btn-link']
    );
    echo $OUTPUT->footer();
    die();
}

// Step 1: show the upload form.
echo $OUTPUT->header();
echo $renderer->tabs('import');
echo $OUTPUT->heading(get_string('nav_import', 'local_rolesaudit'), 3);
echo html_writer::div(get_string('import_intro', 'local_rolesaudit'), 'rolesaudit-intro');
$mform->display();
echo $OUTPUT->footer();
