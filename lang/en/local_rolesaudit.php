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
 * Plugin strings are defined here.
 *
 * @package     local_rolesaudit
 * @category    string
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['backtocompare'] = 'Back to Compare & edit';
$string['clicktoedit'] = 'Click to change this permission';
$string['compare_intro'] = 'Compare the capability definitions of several roles side by side and change a permission straight from the table. The filters below narrow the display down to the roles and capabilities you care about.';
$string['csv_capability'] = 'Capability';
$string['csv_description'] = 'Description';
$string['csv_modified'] = '{$a} changed';
$string['error_badfile'] = 'The uploaded file could not be read as a valid Roles Audit export.';
$string['error_badformat'] = 'The uploaded file is not a Roles Audit export file.';
$string['error_badversion'] = 'The uploaded file was produced by a newer version of the plugin and cannot be imported.';
$string['error_escalation_critical'] = 'This change was blocked because only a site administrator can grant this capability.';
$string['error_escalation_notheld'] = 'This change was blocked because you cannot grant a capability you do not hold yourself.';
$string['error_lockout'] = 'This change was blocked because it would remove your own access to role management.';
$string['error_sessionexpired'] = 'The import session has expired, or another file has been uploaded since. Please upload the file again.';
$string['error_toolarge'] = 'The uploaded file is too large to be imported (maximum {$a}).';
$string['error_toomanyentries'] = 'The uploaded file holds too many role/capability entries to be imported (maximum {$a}).';
$string['event_permission_changed'] = 'Role permission changed';
$string['event_roles_exported'] = 'Role definitions exported';
$string['event_roles_imported'] = 'Role definitions imported';
$string['export_download'] = 'Download export file';
$string['export_intro'] = 'Export the capability definitions of the selected roles to a JSON file that can be imported on another Moodle site. Roles are matched by their short name and capabilities by their name.';
$string['export_onlymodified'] = 'Only export capabilities changed from the default';
$string['field_role'] = 'Role';
$string['filter_capabilities'] = 'Capabilities';
$string['filter_count'] = 'Items selected on this line, out of everything the line offers. A search in the line does not change it.';
$string['filter_onlymodified'] = 'Only capabilities changed from their initial configuration';
$string['filter_plugins'] = 'Plugins';
$string['filter_roles'] = 'Roles';
$string['filter_scrollhint'] = 'Drag, use the mouse wheel or the arrows at both ends to scroll this line';
$string['filter_scrollleft'] = 'Scroll this line to the left';
$string['filter_scrollright'] = 'Scroll this line to the right';
$string['filter_search'] = 'Search a capability…';
$string['filter_searchplugins'] = 'Search a plugin…';
$string['filter_searchroles'] = 'Search a role…';
$string['filter_searchtoggle'] = 'Show or hide the search box of this line';
$string['filter_searchtypes'] = 'Search a plugin type…';
$string['filter_types'] = 'Plugin types';
$string['filter_values'] = 'Values';
$string['filters_heading'] = 'Filters';
$string['filters_hint'] = 'Each line is a list of tags, switched on or off with a click. Scroll a line with the arrows at its ends, the mouse wheel, or by dragging it.';
$string['filters_loaded'] = 'Filter set loaded.';
$string['filters_nosaved'] = 'No filter set has been saved in this browser.';
$string['filters_saved'] = 'Filter set saved in this browser.';
$string['import_applied'] = '{$a} change(s) applied successfully.';
$string['import_apply'] = 'Apply the selected changes';
$string['import_blocked_critical'] = 'Refused: only a site administrator can grant this capability.';
$string['import_blocked_notheld'] = 'Refused: you cannot grant a capability you do not hold yourself.';
$string['import_current'] = 'Current value';
$string['import_failed'] = '{$a} change(s) could not be written to the database and were left unchanged.';
$string['import_file'] = 'Import file (.json)';
$string['import_incoming'] = 'New value';
$string['import_intro'] = 'Upload a JSON file previously produced by the Export tab. You will see a preview of the changes before anything is applied.';
$string['import_nochanges'] = 'The file contains no change to apply.';
$string['import_noselection'] = 'No change was selected, so nothing was applied.';
$string['import_preview'] = 'Import preview';
$string['import_result'] = 'Import result';
$string['import_selectedcount'] = '{$a->selected} of {$a->total} change(s) selected';
$string['import_selectrow'] = 'Apply {$a->capability} for role {$a->role}';
$string['import_skippedcaps_notinstalled'] = 'Capabilities skipped — owning plugin not installed on this site';
$string['import_skippedcaps_older'] = 'Capabilities skipped — plugin installed but at an older version (capability not yet deployed here)';
$string['import_skippedcaps_plugin'] = '{$a->name} ({$a->component}) — not installed';
$string['import_skippedcaps_pluginolder'] = '{$a->name} ({$a->component}) — installed version {$a->localversion}, source site had {$a->fileversion}';
$string['import_skippedcaps_unknown'] = 'Capabilities skipped — unknown on this site (renamed, removed or a typo)';
$string['import_skippedescalation'] = '{$a} change(s) were skipped because they would have granted a capability you are not entitled to grant.';
$string['import_skippedlockout'] = '{$a} change(s) were skipped because they would have removed your own access to role management.';
$string['import_skippedroles'] = 'Roles skipped (not found on this site)';
$string['import_source'] = 'File exported from {$a->site} (Moodle {$a->release}) on {$a->date}.';
$string['import_startover'] = 'Start over with another file';
$string['import_summary'] = '{$a->changes} change(s) to apply, {$a->unchanged} already up to date, {$a->skippedroles} unknown role(s) skipped, {$a->skippedcaps} unknown capability(ies) skipped.';
$string['import_th_apply'] = 'Apply?';
$string['import_upload'] = 'Upload and preview';
$string['legend_clicktoedit'] = 'Click a cell to change its permission';
$string['legend_modified'] = 'Value differs from the role\'s initial configuration';
$string['nav_compare'] = 'Compare & edit';
$string['nav_export'] = 'Export';
$string['nav_import'] = 'Import';
$string['nodifferences'] = 'No capability matches the current filters.';
$string['pluginname'] = 'Roles Audit';
$string['privacy:metadata'] = 'The Roles Audit plugin does not store any personal data. It only reads and edits role definitions (the core role capabilities), which are not personal data.';
$string['rolesaudit:manage'] = 'Compare, edit, export and import role capability definitions';
$string['search_suggestions'] = 'Capability suggestions';
$string['setting_defaultroles'] = 'Default selected roles';
$string['setting_defaultroles_desc'] = 'Roles ticked when the Compare & edit and Export tabs load. If none is selected, the table opens empty.';
$string['settings_heading'] = 'Settings';
$string['th_capability'] = 'Capability';
$string['toolbar_exportcsv'] = 'Export the table to CSV';
$string['toolbar_loadfilters'] = 'Load the saved filter set';
$string['toolbar_savefilters'] = 'Save the current filter set';
