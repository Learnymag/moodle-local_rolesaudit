# Changelog

All notable changes to `local_rolesaudit` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions before 1.3.10 were reconstructed from the Git history.

## [1.3.10] - 2026-10-15

### Changed
- The tool is now reserved to site administrators, consistently. Its pages were
  already only registered for `moodle/site:config`, but `local/rolesaudit:manage`
  was granted to the *Manager* archetype, which opened the `set_permission` web
  service to managers without the pages. The capability no longer has any
  archetype.
- The colour legend now relies on CSS classes instead of inline styles.
- The stylesheet only falls back on Bootstrap variables, without theme-specific
  custom properties.

### Removed
- On upgrade, `local/rolesaudit:manage` is withdrawn from every role with the
  *Manager* archetype that holds it as *Allow* at the system context. A grant
  made on purpose on such a role cannot be told apart from the install default
  and is withdrawn too; re-grant it if needed.
- The empty `lib.php`, the unused hidden legend and two unused language strings.

### Fixed
- The three admin external pages are registered with a URL string, as
  `admin_externalpage` expects, instead of a `moodle_url` object.
- The `compare` template now carries an example context.
- The README developer note recommends `grunt amd --files=…` instead of `--root=…`.

## [1.3.9] - 2026-10-05

### Security
- Import files are bounded in size, JSON depth and number of role/capability
  entries, checked on the server side.
- A previewed import expires after 30 minutes, and only one pending import is
  kept per session.
- Role and capability names read from the import file are escaped in the preview.
- CSV export neutralises cells a spreadsheet would read as a formula.

## [1.3.8] - 2026-09-29

### Security
- Privilege escalation guard on both write paths (inline edit and import): only
  a site administrator may grant a security-critical capability, and nobody may
  grant a capability they do not hold themselves.
- Importing requires `moodle/role:manage` in addition to the tool capability.

## [1.3.7] - 2026-08-25

### Added
- Each filter line shows how many of its items are selected.

### Removed
- The plugin description on the settings page (documented in the README).

## [1.3.6] - 2026-08-19

### Added
- Scroll arrows on the filter lines.
- Legend and explanations above the comparison table.

## [1.3.5] - 2026-08-18

### Added
- On import, choose which of the capabilities found in the file are applied.

## [1.3.4] - 2026-08-17

### Fixed
- Compatibility with Moodle 5.2.

## [1.3.3] - 2026-08-17

### Fixed
- Icons.
- Values not refreshed after an edit.

## [1.3.1] - 2026-07-29

### Changed
- Role order in the filters.

## [1.3.0] - 2026-07-29

### Added
- Setting to choose the roles selected by default.

## [1.2.2] - 2026-07-29

### Added
- Search and select all / deselect all on every filter line.
- CSV export of the table.
- Save and load a filter set.

## [1.0.0] - 2026-07-22

### Added
- Initial release: compare, edit, export and import role definitions at the
  system context.
- Autocomplete on the capability search.
- Missing capabilities and plugins reported during import.
