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

namespace local_rolesaudit\event;

use core\event\base;

/**
 * Event fired when role definitions are imported and applied through the tool.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles_imported extends base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_roles_imported', 'local_rolesaudit');
    }

    /**
     * Returns a human readable description of the event.
     *
     * @return string
     */
    public function get_description() {
        $applied = $this->other['applied'] ?? 0;
        return "The user with id '{$this->userid}' imported role definitions and applied '{$applied}' change(s).";
    }

    /**
     * Returns the URL related to this event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/rolesaudit/import.php');
    }
}
