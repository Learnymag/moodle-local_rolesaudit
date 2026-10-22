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

use moodle_exception;

/**
 * Parses an import file and computes / applies the resulting changes.
 *
 * Roles are matched across instances by shortname, capabilities by name.
 * Anything that cannot be matched on the current site is skipped and reported;
 * roles are never created.
 *
 * @package     local_rolesaudit
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /**
     * Upper bound for an import file. A full export of a large site weighs a few
     * MB; the check runs on the content read server-side, since the filepicker's
     * own limit is supplied by the client and can be bypassed.
     */
    const MAX_IMPORT_BYTES = 10 * 1024 * 1024;

    /** Upper bound for role x capability entries, far above any real site. */
    const MAX_IMPORT_ENTRIES = 500000;

    /**
     * Maximum nesting depth accepted by json_decode(). The format itself nests
     * four levels (root > roles > role > capabilities).
     */
    const MAX_IMPORT_DEPTH = 8;

    /** How long (seconds) a previewed import stays available for confirmation. */
    const PENDING_TTL = 1800;

    /**
     * Decode and validate a raw import file.
     *
     * Size, depth and entry count are bounded before any costly work, so an
     * oversized or malformed file is refused rather than processed in full.
     *
     * @param string $json Raw file content.
     * @return array The decoded payload.
     * @throws moodle_exception When the file is not a valid tool export or exceeds the limits.
     */
    public static function parse(string $json): array {
        if (strlen($json) > self::MAX_IMPORT_BYTES) {
            throw new moodle_exception('error_toolarge', 'local_rolesaudit', '', display_size(self::MAX_IMPORT_BYTES));
        }
        $payload = json_decode($json, true, self::MAX_IMPORT_DEPTH);
        if (!is_array($payload) || !isset($payload['meta']['format'])) {
            throw new moodle_exception('error_badfile', 'local_rolesaudit');
        }
        if ($payload['meta']['format'] !== exporter::FORMAT) {
            throw new moodle_exception('error_badformat', 'local_rolesaudit');
        }
        if (($payload['meta']['formatversion'] ?? 0) > exporter::FORMAT_VERSION) {
            throw new moodle_exception('error_badversion', 'local_rolesaudit');
        }
        if (!isset($payload['roles']) || !is_array($payload['roles'])) {
            throw new moodle_exception('error_badfile', 'local_rolesaudit');
        }
        $entries = 0;
        foreach ($payload['roles'] as $roledata) {
            if (!is_array($roledata) || !is_array($roledata['capabilities'] ?? [])) {
                throw new moodle_exception('error_badfile', 'local_rolesaudit');
            }
            $entries += count($roledata['capabilities'] ?? []);
        }
        if ($entries > self::MAX_IMPORT_ENTRIES) {
            throw new moodle_exception('error_toomanyentries', 'local_rolesaudit', '', self::MAX_IMPORT_ENTRIES);
        }
        return $payload;
    }

    /**
     * Compute the diff between the file and the current site (no writes).
     *
     * @param array $payload Parsed payload from {@see parse()}.
     * @return array {
     *     changes: array of change rows,
     *     unchanged: int,
     *     skippedroles: string[] (shortnames),
     *     skippedcaps: array grouped by cause (see {@see classify_skipped()})
     * }
     */
    public static function preview(array $payload): array {
        $roles = matrix::get_roles();
        $byshortname = [];
        foreach ($roles as $role) {
            $byshortname[$role->shortname] = $role;
        }
        $allcaps = matrix::get_capabilities();

        // Load the current matrix only for the roles we can match.
        $matchedroleids = [];
        foreach ($payload['roles'] as $shortname => $unused) {
            if (isset($byshortname[$shortname])) {
                $matchedroleids[] = (int) $byshortname[$shortname]->id;
            }
        }
        $currentmatrix = matrix::get_matrix($matchedroleids);

        $changes = [];
        $unchanged = 0;
        $skippedroles = [];
        $skippedcaps = [];

        foreach ($payload['roles'] as $shortname => $roledata) {
            if (!isset($byshortname[$shortname])) {
                $skippedroles[] = $shortname;
                continue;
            }
            $role = $byshortname[$shortname];
            $capabilities = $roledata['capabilities'] ?? [];
            foreach ($capabilities as $capname => $incoming) {
                $incoming = (int) $incoming;
                if (!permissions::is_valid($incoming)) {
                    continue;
                }
                if (!isset($allcaps[$capname])) {
                    $skippedcaps[$capname] = true;
                    continue;
                }
                $current = matrix::get_permission($currentmatrix, (int) $role->id, $capname);
                if ($current === $incoming) {
                    $unchanged++;
                    continue;
                }
                $changes[] = [
                    'roleid' => (int) $role->id,
                    'roleshortname' => $shortname,
                    'rolename' => $role->localname,
                    'capability' => $capname,
                    'caplabel' => get_capability_string($capname),
                    'current' => $current,
                    'currentlabel' => permissions::label($current),
                    'incoming' => $incoming,
                    'incominglabel' => permissions::label($incoming),
                    // Shown in advance so the user is not surprised; apply() checks again.
                    'blocked' => guard::escalation_reason($capname, $current, $incoming),
                ];
            }
        }

        return [
            'changes' => $changes,
            'unchanged' => $unchanged,
            'skippedroles' => $skippedroles,
            'skippedcaps' => self::classify_skipped(array_keys($skippedcaps), $payload['plugins'] ?? []),
        ];
    }

    /**
     * Attribute skipped capabilities to a cause so the preview can explain them.
     *
     * A capability in the file that is not defined on the current site falls
     * into one of three buckets, resolved from the capability's owning component
     * (see {@see matrix::capability_component()}), the file's "plugins" inventory
     * and the component's local install state / version:
     *  - notinstalled: the owning plugin is not installed here at all;
     *  - older: the plugin is installed but at a version earlier than the source
     *    site, so the capability was likely introduced by a later update;
     *  - unknown: the plugin is installed and up to date (or the file predates
     *    the plugins inventory), so the capability was renamed, removed or is a
     *    typo.
     *
     * @param string[] $capnames Skipped capability names (deduplicated).
     * @param array $fileplugins The "plugins" block from the import file (may be empty for v1 files).
     * @return array {
     *     notinstalled: [component => [name, caps[]]],
     *     older: [component => [name, localversion, fileversion, caps[]]],
     *     unknown: string[] (capability names),
     *     total: int
     * }
     */
    private static function classify_skipped(array $capnames, array $fileplugins): array {
        global $CFG;

        $result = [
            'notinstalled' => [],
            'older' => [],
            'unknown' => [],
            'total' => count($capnames),
        ];
        sort($capnames);

        foreach ($capnames as $capname) {
            $component = matrix::capability_component($capname);
            if ($component === '') {
                $result['unknown'][] = $capname;
                continue;
            }

            $fileinfo = $fileplugins[$component] ?? null;
            $filename = $fileinfo['name'] ?? $component;
            $fileversion = isset($fileinfo['version']) ? (int) $fileinfo['version'] : null;

            // Install state / version of the owning component on this site.
            if ($component === 'core') {
                $localversion = (int) $CFG->version;
            } else {
                $localversion = get_config($component, 'version');
            }

            if ($localversion === false) {
                // The owning plugin is not installed on this site.
                if (!isset($result['notinstalled'][$component])) {
                    $result['notinstalled'][$component] = ['name' => $filename, 'caps' => []];
                }
                $result['notinstalled'][$component]['caps'][] = $capname;
                continue;
            }

            if ($fileversion !== null && (int) $localversion < $fileversion) {
                // Installed, but older than the source site: capability likely newer.
                if (!isset($result['older'][$component])) {
                    $result['older'][$component] = [
                        'name' => $filename,
                        'localversion' => (int) $localversion,
                        'fileversion' => $fileversion,
                        'caps' => [],
                    ];
                }
                $result['older'][$component]['caps'][] = $capname;
                continue;
            }

            // Installed and up to date (or no version info in the file).
            $result['unknown'][] = $capname;
        }

        return $result;
    }

    /**
     * Apply a set of changes to the current site.
     *
     * Changes that would lock the current user out of role management, or let
     * them grant more than they are entitled to (see {@see guard}), are skipped
     * and returned separately. The whole batch runs in a transaction. The
     * applied count is read back from the database after the commit rather than
     * assumed, so it can never over-report.
     *
     * The capability checks are repeated here rather than left to the calling
     * page, so that no caller can ever write permissions without them - the same
     * pair the set_permission web service requires.
     *
     * @param array $changes Change rows from {@see preview()}.
     * @return array [applied => int, skippedlockout => array, skippedescalation => array, failed => array]
     */
    public static function apply(array $changes): array {
        global $DB;

        $context = \context_system::instance();
        require_capability('local/rolesaudit:manage', $context);
        require_capability('moodle/role:manage', $context);

        $applied = 0;
        $skippedlockout = [];
        $skippedescalation = [];
        $failed = [];
        $written = [];
        $touchedroles = [];

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($changes as $change) {
                $roleid = (int) $change['roleid'];
                $capability = $change['capability'];
                $incoming = (int) $change['incoming'];

                if (guard::would_lock_out($roleid, $capability, $incoming)) {
                    $skippedlockout[] = $change;
                    continue;
                }
                // The current value comes from the preview recomputed by the caller,
                // i.e. from the database, never from the imported file.
                if (guard::escalation_reason($capability, (int) $change['current'], $incoming) !== null) {
                    $skippedescalation[] = $change;
                    continue;
                }
                matrix::write_permission($roleid, $capability, $incoming);
                $touchedroles[$roleid] = $roleid;
                $written[] = $change;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // A failed write undoes the whole batch: an import is all or nothing.
            $transaction->rollback($e);
        }

        // Count what really landed rather than what was asked for: a single
        // query for the touched roles, read back after the commit.
        $after = matrix::get_matrix(array_keys($touchedroles));
        foreach ($written as $change) {
            $stored = matrix::get_permission($after, (int) $change['roleid'], $change['capability']);
            if ($stored === (int) $change['incoming']) {
                $applied++;
            } else {
                $failed[] = $change;
            }
        }

        // The per-write cache purges happened inside the transaction, where a
        // concurrent request could repopulate the role definitions from rows it
        // could not see yet; purge again now that they are committed.
        if (!empty($touchedroles)) {
            accesslib_clear_role_cache(array_values($touchedroles));
        }

        return [
            'applied' => $applied,
            'skippedlockout' => $skippedlockout,
            'skippedescalation' => $skippedescalation,
            'failed' => $failed,
        ];
    }
}
