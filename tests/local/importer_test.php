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
 * Tests for the bounds importer::parse() puts on an import file.
 *
 * A file is refused before any costly work when it is too large, too deeply
 * nested, malformed, or holds too many entries - while a full export of a real
 * site still goes through.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_rolesaudit\local\importer::parse
 * @covers      \local_rolesaudit\local\importer::preview
 */
final class importer_test extends \advanced_testcase {

    /**
     * A well-formed file around the given roles block.
     *
     * @param array $roles The "roles" block.
     * @return string The JSON file content.
     */
    private function file(array $roles): string {
        return json_encode([
            'meta' => ['format' => exporter::FORMAT, 'formatversion' => exporter::FORMAT_VERSION],
            'roles' => $roles,
        ]);
    }

    /**
     * Assert that parse() refuses a file with the given error string.
     *
     * @param string $json
     * @param string $errorcode
     */
    private function assert_refused(string $json, string $errorcode): void {
        try {
            importer::parse($json);
            $this->fail("parse() accepted a file it should have refused with {$errorcode}.");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * A full export of the test site, every role and every capability, is accepted.
     */
    public function test_parse_accepts_a_full_export(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $payload = exporter::build(array_keys(matrix::get_roles()), false);
        $json = json_encode($payload);

        $this->assertLessThan(importer::MAX_IMPORT_BYTES, strlen($json));
        $this->assertSame(array_keys($payload['roles']), array_keys(importer::parse($json)['roles']));
    }

    /**
     * A file over the size limit is refused before being decoded.
     */
    public function test_parse_refuses_an_oversized_file(): void {
        $this->assert_refused(str_repeat(' ', importer::MAX_IMPORT_BYTES + 1), 'error_toolarge');
    }

    /**
     * A file nested deeper than the format allows is refused.
     */
    public function test_parse_refuses_a_too_deep_file(): void {
        $nested = 1;
        for ($i = 0; $i < importer::MAX_IMPORT_DEPTH; $i++) {
            $nested = [$nested];
        }
        $this->assert_refused($this->file(['manager' => ['capabilities' => $nested]]), 'error_badfile');
    }

    /**
     * A role whose capabilities are not a list is refused.
     */
    public function test_parse_refuses_malformed_capabilities(): void {
        $this->assert_refused($this->file(['manager' => ['capabilities' => 'all']]), 'error_badfile');
        $this->assert_refused($this->file(['manager' => 'all']), 'error_badfile');
    }

    /**
     * A file holding more entries than allowed is refused, even below the size limit.
     */
    public function test_parse_refuses_too_many_entries(): void {
        $capabilities = array_fill_keys(range(1, importer::MAX_IMPORT_ENTRIES + 1), CAP_ALLOW);
        $json = $this->file(['manager' => ['capabilities' => $capabilities]]);

        $this->assertLessThan(importer::MAX_IMPORT_BYTES, strlen($json));
        $this->assert_refused($json, 'error_toomanyentries');
    }

    /**
     * Unknown names from the file are reported as they are: escaping them is the
     * job of the page that displays them, not of the importer.
     */
    public function test_preview_reports_unknown_names_unaltered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $role = '<img src=x onerror=alert(1)>';
        $capability = 'local_nope/<script>:x';

        $preview = importer::preview(importer::parse($this->file([
            $role => ['capabilities' => []],
            'manager' => ['capabilities' => [$capability => CAP_ALLOW]],
        ])));

        $this->assertSame([$role], $preview['skippedroles']);
        $this->assertSame(1, $preview['skippedcaps']['total']);
        $all = array_merge($preview['skippedcaps']['unknown'],
            ...array_column($preview['skippedcaps']['notinstalled'], 'caps'));
        $this->assertSame([$capability], $all);
    }
}
