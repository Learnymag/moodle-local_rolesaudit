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

namespace local_rolesaudit\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_rolesaudit\local\importer;
use moodleform;

/**
 * Import upload form.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // The size limit here only informs the user up front: the client supplies it
        // to the upload request, so the real check is importer::parse().
        $mform->addElement('filepicker', 'importfile',
            get_string('import_file', 'local_rolesaudit'), null,
            ['accepted_types' => ['.json'], 'maxfiles' => 1, 'maxbytes' => importer::MAX_IMPORT_BYTES]);
        $mform->addRule('importfile', get_string('required'), 'required', null, 'client');

        $mform->addElement('submit', 'submitbutton',
            get_string('import_upload', 'local_rolesaudit'));
    }
}
