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
 * Dynamic, fully client-side filtering and inline editing for the Roles Audit
 * comparison table, plus the role picker reused on the export page.
 *
 * @module     local_rolesaudit/compare
 * @copyright  2026 Septeo learn-ymag@septeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {add as addToast} from 'core/toast';
import {debounce} from 'core/utils';

const VALUES = [0, 1, -1, -1000];
const VALUE_CLASS = {
    0: 'rolesaudit-notset',
    1: 'rolesaudit-allow',
    '-1': 'rolesaudit-prevent',
    '-1000': 'rolesaudit-prohibit',
};

// Where the saved filter set lives. localStorage is already scoped per origin,
// so the key needs no site discriminator. Bump the suffix on payload changes.
const STORAGE_KEY = 'local_rolesaudit-filters-v1';

// CSV delimiter expected by spreadsheets in fr/European locales.
const CSV_DELIMITER = ';';

/**
 * Escape a string for safe insertion as HTML text/attribute.
 *
 * @param {String} value
 * @return {String}
 */
const esc = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

/**
 * Escape a string as HTML, wrapping every (case-insensitive) occurrence of the
 * query in <strong> to highlight what made the item match.
 *
 * @param {String} text
 * @param {String} query Already lower-cased search term.
 * @return {String}
 */
const highlight = (text, query) => {
    text = String(text);
    if (!query) {
        return esc(text);
    }
    const lower = text.toLowerCase();
    let out = '';
    let from = 0;
    let idx = lower.indexOf(query, from);
    while (idx !== -1) {
        out += esc(text.slice(from, idx))
            + '<strong>' + esc(text.slice(idx, idx + query.length)) + '</strong>';
        from = idx + query.length;
        idx = lower.indexOf(query, from);
    }
    return out + esc(text.slice(from));
};

// Maximum number of autocomplete suggestions shown at once.
const MAX_SUGGESTIONS = 10;

/**
 * Tag label for a capability: its name without the plugin type, i.e.
 * "mod/forum:addinstance" becomes "forum:addinstance".
 *
 * @param {Object} cap
 * @return {String}
 */
const capLabel = (cap) => {
    const slash = cap.name.indexOf('/');
    return slash === -1 ? cap.name : cap.name.slice(slash + 1);
};

// A spreadsheet evaluates a cell starting with one of these as a formula.
const CSV_FORMULA_TRIGGER = /^[=+\-@\t\r]/;

/**
 * Escape one CSV field: quote it only when it holds the delimiter, a quote or a
 * line break, doubling any embedded quote.
 *
 * A field that a spreadsheet would read as a formula (CSV injection, e.g. a role
 * renamed "=HYPERLINK(...)") is first prefixed with an apostrophe, so it opens as
 * plain text. This must happen before quoting: the spreadsheet strips the quotes
 * and would still see the formula.
 *
 * @param {*} value
 * @return {String}
 */
const csvCell = (value) => {
    let text = value === null || value === undefined ? '' : String(value);
    if (CSV_FORMULA_TRIGGER.test(text)) {
        text = "'" + text;
    }
    if (text.includes(CSV_DELIMITER) || text.includes('"') || /[\r\n]/.test(text)) {
        return '"' + text.replace(/"/g, '""') + '"';
    }
    return text;
};

/**
 * Trigger a client-side file download.
 *
 * @param {String} filename
 * @param {String} mime
 * @param {String} content
 */
const downloadFile = (filename, mime, content) => {
    const blob = new Blob([content], {type: mime});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.classList.add('d-none');
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

/**
 * Build one icon button.
 *
 * @param {String} action Value of the data-action attribute.
 * @param {String} icon Font Awesome classes, style included, e.g. "fa fa-check".
 * @param {String} label Accessible label and tooltip.
 * @param {String} cssClass Button class.
 * @return {HTMLElement}
 */
const makeIconButton = (action, icon, label, cssClass) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = cssClass;
    button.dataset.action = action;
    button.title = label;
    button.setAttribute('aria-label', label);
    const glyph = document.createElement('i');
    glyph.className = icon;
    glyph.setAttribute('aria-hidden', 'true');
    button.appendChild(glyph);
    return button;
};

/**
 * Read and parse the embedded JSON data payload.
 *
 * @param {HTMLElement} root
 * @return {Object|null}
 */
const readData = (root) => {
    const node = root.querySelector('#rolesaudit-data');
    if (!node) {
        return null;
    }
    return JSON.parse(node.textContent);
};

/**
 * Make a horizontal tag strip scrollable by click-and-drag (grab cursor).
 *
 * @param {HTMLElement} strip
 */
const enableDragScroll = (strip) => {
    let down = false;
    let moved = false;
    let startX = 0;
    let startScroll = 0;

    strip.addEventListener('mousedown', (e) => {
        down = true;
        moved = false;
        startX = e.pageX;
        startScroll = strip.scrollLeft;
        strip.classList.add('rolesaudit-dragging');
    });
    window.addEventListener('mousemove', (e) => {
        if (!down) {
            return;
        }
        const dx = e.pageX - startX;
        if (Math.abs(dx) > 3) {
            moved = true;
        }
        strip.scrollLeft = startScroll - dx;
    });
    window.addEventListener('mouseup', () => {
        if (!down) {
            return;
        }
        down = false;
        strip.classList.remove('rolesaudit-dragging');
        // Suppress the click that follows a real drag so it doesn't toggle a tag.
        strip.dataset.suppressClick = moved ? '1' : '';
    });
    // Also allow the mouse wheel to scroll the strip horizontally.
    strip.addEventListener('wheel', (e) => {
        if (e.deltaY !== 0) {
            strip.scrollLeft += e.deltaY;
            e.preventDefault();
        }
    }, {passive: false});
};

/**
 * Add the two scroll arrows of one strip, inside its wrapper and on either side
 * of it, then keep them and the wrapper's edge fades in sync with the scroll
 * position: an arrow is disabled and its fade hidden as soon as that side has
 * nothing more to reveal, so a strip that fits entirely shows neither.
 *
 * The arrows sit outside the scrolled area, so they never cover a tag and are
 * out of reach of the drag-scroll handler.
 *
 * @param {HTMLElement} strip
 * @param {Object} labels Needs scrollleft, scrollright and scrollhint.
 * @return {Function} Refresh callback, to be called whenever the strip's content
 *                    or width changes. Scrolling itself is handled here.
 */
const addScrollArrows = (strip, labels) => {
    const wrap = strip.closest('.rolesaudit-stripwrap');
    if (!wrap) {
        return () => {
            return;
        };
    }

    const makeArrow = (dir, icon, label) => {
        const button = makeIconButton('rolesaudit-scroll', icon, label || '', 'rolesaudit-scrollbtn');
        button.dataset.dir = String(dir);
        button.addEventListener('click', () => {
            const step = Math.max(80, strip.clientWidth * 0.8);
            strip.scrollBy({
                left: dir * step,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        });
        return button;
    };

    const left = makeArrow(-1, 'fa fa-chevron-left', labels.scrollleft);
    const right = makeArrow(1, 'fa fa-chevron-right', labels.scrollright);
    wrap.insertBefore(left, strip);
    wrap.appendChild(right);
    strip.title = labels.scrollhint || '';

    const refresh = () => {
        // A pixel of tolerance: fractional layout widths never reach the exact end.
        const max = strip.scrollWidth - strip.clientWidth;
        const canLeft = max > 1 && strip.scrollLeft > 1;
        const canRight = max > 1 && strip.scrollLeft < max - 1;
        left.disabled = !canLeft;
        right.disabled = !canRight;
        wrap.classList.toggle('rolesaudit-can-left', canLeft);
        wrap.classList.toggle('rolesaudit-can-right', canRight);
    };
    strip.addEventListener('scroll', refresh, {passive: true});
    refresh();

    return refresh;
};

/**
 * Scroll a strip so its first active tag sits at the left of the visible area.
 *
 * The strip's own left padding is kept clear, otherwise the tag would land right
 * against the edge, underneath the scroll-fade gradient drawn over it.
 *
 * @param {HTMLElement} strip
 */
const scrollActiveIntoView = (strip) => {
    const active = strip.querySelector('.rolesaudit-tag-active');
    if (!active) {
        return;
    }
    const padding = parseFloat(window.getComputedStyle(strip).paddingLeft) || 0;
    strip.scrollLeft += active.getBoundingClientRect().left
        - strip.getBoundingClientRect().left - padding;
};

/**
 * Build a single tag element.
 *
 * @param {String} label
 * @param {Boolean} active
 * @param {Object} dataset
 * @param {String} [title]
 * @param {String} [extraClass]
 * @return {HTMLElement}
 */
const makeTag = (label, active, dataset, title, extraClass) => {
    const tag = document.createElement('span');
    tag.className = 'rolesaudit-tag' + (active ? ' rolesaudit-tag-active' : '')
        + (extraClass ? ' ' + extraClass : '');
    tag.setAttribute('role', 'button');
    tag.setAttribute('tabindex', '0');
    tag.textContent = label;
    if (title) {
        tag.title = title;
    }
    Object.keys(dataset).forEach((key) => {
        tag.dataset[key] = dataset[key];
    });
    return tag;
};

/**
 * The main controller for the compare page.
 *
 * @param {HTMLElement} root
 * @param {Object} data
 */
const Controller = function(root, data) {
    this.root = root;
    this.data = data;
    this.editable = root.dataset.editable === '1';
    this.menuTemplate = root.querySelector('.rolesaudit-menu-template');

    // Search box and autocomplete state of every filter line that has one,
    // keyed by line and filled by addLineControls().
    this.suggest = {};

    // Filter state.
    this.activeRoles = new Set(data.activeroles.map(Number));
    this.activeTypes = new Set(data.activetypes);
    this.excludedPlugins = new Set();
    this.excludedCaps = new Set();
    this.activeValues = new Set(VALUES);
    this.onlyModified = true;
    this.search = '';
    // Per-line search terms, narrowing which tags a strip shows. The capability
    // line has its own query (this.search) because it also filters the table.
    this.lineSearch = {roles: '', types: '', plugins: ''};

    // Distinct type and plugin lists derived from the catalogue.
    this.types = [...new Set(data.capabilities.map((c) => c.type))].sort();

    this.strips = {};
    // Scroll-state refreshers of the strips, one per line, called back whenever a
    // re-render may have changed how far a strip can scroll.
    this.stripRefreshers = [];
    root.querySelectorAll('.rolesaudit-strip').forEach((strip) => {
        this.strips[strip.dataset.line] = strip;
        enableDragScroll(strip);
        this.addLineControls(strip);
        this.stripRefreshers.push(addScrollArrows(strip, data.labels));
    });
};

/**
 * Bring the arrows and edge fades of every strip back in line with its content.
 */
Controller.prototype.refreshStrips = function() {
    this.stripRefreshers.forEach((refresh) => refresh());
};

/**
 * Add the buttons of one filter line: the magnifier that reveals the line's
 * search box (when it has one), then the select-all / select-none pair.
 *
 * The pair is inserted as a sibling right before the strip wrapper, so it sits
 * flush against the strip without being part of it - and stays clear of the
 * scroll-fade overlay drawn on the wrapper. The magnifier goes right before the
 * search box it controls, so the box grows away from it towards the strip.
 *
 * @param {HTMLElement} strip
 */
Controller.prototype.addLineControls = function(strip) {
    const wrap = strip.closest('.rolesaudit-stripwrap');
    if (!wrap || !wrap.parentNode) {
        return;
    }
    const line = strip.dataset.line;

    // Counter of what the line has selected, appended to its label so the figure
    // reads as part of it. Filled by updateCount() on every render of the line.
    const label = wrap.parentNode.querySelector('.rolesaudit-filterlabel');
    if (label) {
        const count = document.createElement('span');
        count.className = 'rolesaudit-filtercount';
        count.dataset.line = line;
        count.title = this.data.labels.filtercount || '';
        label.appendChild(count);
    }

    const searchWrap = wrap.parentNode.querySelector('.rolesaudit-searchwrap[data-line="' + line + '"]');
    if (searchWrap) {
        const input = searchWrap.querySelector('input');
        this.suggest[line] = {
            wrap: searchWrap,
            input: input,
            list: searchWrap.querySelector('.rolesaudit-suggestions'),
            items: [],
            active: -1,
        };
        const toggle = makeIconButton('rolesaudit-searchtoggle', 'fa fa-magnifying-glass',
            this.data.labels.searchtoggle || '', 'rolesaudit-circlebtn');
        toggle.dataset.line = line;
        toggle.setAttribute('aria-expanded', 'false');
        if (input) {
            // Collapsed: keep it out of the tab order until it is revealed.
            input.tabIndex = -1;
            if (input.id) {
                toggle.setAttribute('aria-controls', input.id);
            }
        }
        searchWrap.parentNode.insertBefore(toggle, searchWrap);
    }

    const group = document.createElement('div');
    group.className = 'rolesaudit-stripbtns';
    [
        // Checked / unchecked boxes rather than a tick and a cross: testers read
        // the cross as "remove the filter line" instead of "untick everything".
        ['rolesaudit-selectall', 'fa-regular fa-square-check', this.data.labels.selectall],
        ['rolesaudit-selectnone', 'fa-regular fa-square', this.data.labels.selectnone],
    ].forEach(([action, icon, label]) => {
        const button = makeIconButton(action, icon, label || '', 'rolesaudit-circlebtn');
        button.dataset.line = line;
        group.appendChild(button);
    });
    wrap.parentNode.insertBefore(group, wrap);
};

/**
 * Whether a tag's searchable text matches its line's current search term.
 *
 * @param {String} line
 * @param {String} text
 * @return {Boolean}
 */
Controller.prototype.lineMatches = function(line, text) {
    const query = this.lineSearch[line];
    return !query || String(text).toLowerCase().includes(query);
};

/**
 * Set the search term of one line and re-render what it affects.
 *
 * @param {String} line
 * @param {String} query Already lower-cased, trimmed search term.
 */
Controller.prototype.setLineQuery = function(line, query) {
    if (line === 'capabilities') {
        this.search = query;
    } else {
        this.lineSearch[line] = query;
    }
    this.closeSuggestions(line);
    this.renderForLine(line);
};

/**
 * Show or hide the search box of one line.
 *
 * Hiding also clears the term, so a collapsed box can never keep a strip
 * silently narrowed.
 *
 * @param {String} line
 * @param {Boolean} [show] Defaults to toggling the current state.
 * @param {Boolean} [focus] Whether to focus the box when revealing it.
 */
Controller.prototype.toggleLineSearch = function(line, show, focus) {
    const state = this.suggest[line];
    if (!state) {
        return;
    }
    const collapsed = state.wrap.classList.contains('rolesaudit-search-collapsed');
    show = show === undefined ? collapsed : show;
    if (show === !collapsed) {
        return;
    }
    state.wrap.classList.toggle('rolesaudit-search-collapsed', !show);
    const toggle = this.root.querySelector(
        '[data-action="rolesaudit-searchtoggle"][data-line="' + line + '"]');
    if (toggle) {
        toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
    }
    if (!show) {
        this.closeSuggestions(line);
    }
    const input = state.input;
    if (!input) {
        return;
    }
    input.tabIndex = show ? 0 : -1;
    if (show) {
        if (focus !== false) {
            input.focus();
        }
    } else if (input.value !== '') {
        input.value = '';
        this.setLineQuery(line, '');
    }
};

Controller.prototype.value = function(roleid, capname) {
    const row = this.data.matrix[roleid];
    if (row && Object.prototype.hasOwnProperty.call(row, capname)) {
        return Number(row[capname]);
    }
    return 0;
};

Controller.prototype.isModified = function(role, capname) {
    const defaults = this.data.defaults[role.archetype] || {};
    const def = Object.prototype.hasOwnProperty.call(defaults, capname) ? Number(defaults[capname]) : 0;
    return this.value(role.id, capname) !== def;
};

Controller.prototype.pluginKey = (cap) => cap.type + '/' + cap.plugin;

Controller.prototype.pluginSelected = function(cap) {
    return this.activeTypes.has(cap.type) && !this.excludedPlugins.has(this.pluginKey(cap));
};

/**
 * Whether a capability matches the given search term across its action, name
 * and definition (description).
 *
 * @param {Object} cap
 * @param {String} query Already lower-cased search term.
 * @return {Boolean}
 */
Controller.prototype.matchesSearch = function(cap, query) {
    return (cap.action + ' ' + cap.name + ' ' + cap.def).toLowerCase().includes(query);
};

/**
 * Whether a capability passes every active filter (for the table rows).
 *
 * @param {Object} cap
 * @return {Boolean}
 */
Controller.prototype.capIncluded = function(cap) {
    if (!this.pluginSelected(cap) || this.excludedCaps.has(cap.name)) {
        return false;
    }
    if (this.search && !this.matchesSearch(cap, this.search)) {
        return false;
    }
    const roles = this.data.roles.filter((r) => this.activeRoles.has(r.id));
    // Value filter: at least one active-role cell must be in the active values.
    if (this.activeValues.size < VALUES.length) {
        const anyValue = roles.some((r) => this.activeValues.has(this.value(r.id, cap.name)));
        if (!anyValue) {
            return false;
        }
    }
    // Modified filter.
    if (this.onlyModified) {
        const anyModified = roles.some((r) => this.isModified(r, cap.name));
        if (!anyModified) {
            return false;
        }
    }
    return true;
};

/**
 * Show how much of a line is selected, as "(selected/total)" after its label.
 *
 * Both figures cover the whole line, not the tags a line search leaves on
 * screen: the number answers "what is in the table", which a search must not
 * change. Callers therefore count from the filter state, never from the strip.
 *
 * @param {String} line
 * @param {Number} selected
 * @param {Number} total
 */
Controller.prototype.updateCount = function(line, selected, total) {
    const count = this.root.querySelector('.rolesaudit-filtercount[data-line="' + line + '"]');
    if (count) {
        count.textContent = '(' + selected + '/' + total + ')';
    }
};

Controller.prototype.renderRoleTags = function() {
    const strip = this.strips.roles;
    strip.innerHTML = '';
    this.data.roles.forEach((role) => {
        if (!this.lineMatches('roles', role.localname + ' ' + role.shortname)) {
            return;
        }
        strip.appendChild(makeTag(role.localname, this.activeRoles.has(role.id),
            {line: 'roles', value: role.id}, role.shortname));
    });
    this.updateCount('roles', this.data.roles.filter((role) => this.activeRoles.has(role.id)).length,
        this.data.roles.length);
};

Controller.prototype.renderTypeTags = function() {
    const strip = this.strips.types;
    strip.innerHTML = '';
    this.types.forEach((type) => {
        if (!this.lineMatches('types', type)) {
            return;
        }
        strip.appendChild(makeTag(type, this.activeTypes.has(type), {line: 'types', value: type}));
    });
    this.updateCount('types', this.types.filter((type) => this.activeTypes.has(type)).length,
        this.types.length);
};

/**
 * The distinct plugins of the selected plugin types, as "type/plugin" keys
 * sorted alphabetically - which groups them by type and keeps same-named plugins
 * of different types apart. Each key comes with one capability of that plugin,
 * for the label fallback on unusual capability names.
 *
 * @return {Array} List of {key, cap}.
 */
Controller.prototype.pluginEntries = function() {
    const seen = new Map();
    this.data.capabilities.forEach((cap) => {
        if (!this.activeTypes.has(cap.type)) {
            return;
        }
        const key = this.pluginKey(cap);
        if (!seen.has(key)) {
            seen.set(key, cap);
        }
    });
    return [...seen.keys()].sort((a, b) => a.localeCompare(b))
        .map((key) => ({key: key, cap: seen.get(key)}));
};

Controller.prototype.renderPluginTags = function() {
    const strip = this.strips.plugins;
    strip.innerHTML = '';
    const entries = this.pluginEntries();
    entries.forEach(({key, cap}) => {
        if (!this.lineMatches('plugins', key)) {
            return;
        }
        strip.appendChild(makeTag(cap.plugin ? key : cap.type, !this.excludedPlugins.has(key),
            {line: 'plugins', value: key}, key));
    });
    // Counted over the current entries, not over excludedPlugins: that set keeps
    // the plugins of deselected types, which are no longer part of this line.
    this.updateCount('plugins', entries.filter(({key}) => !this.excludedPlugins.has(key)).length,
        entries.length);
};

Controller.prototype.renderCapTags = function() {
    const strip = this.strips.capabilities;
    strip.innerHTML = '';
    // The capabilities of the selected plugins, i.e. the whole line - what the
    // counter is about, and what the search then narrows for display only.
    const pool = this.data.capabilities.filter((cap) => this.pluginSelected(cap));
    // Tags are labelled "plugin:action" and sorted alphabetically on that label.
    const caps = pool.filter((cap) => !this.search || this.matchesSearch(cap, this.search));
    caps.sort((a, b) => capLabel(a).localeCompare(capLabel(b)));
    caps.forEach((cap) => {
        strip.appendChild(makeTag(capLabel(cap), !this.excludedCaps.has(cap.name),
            {line: 'capabilities', value: cap.name}, cap.def + ' (' + cap.name + ')'));
    });
    this.updateCount('capabilities', pool.filter((cap) => !this.excludedCaps.has(cap.name)).length,
        pool.length);
};

Controller.prototype.renderValueTags = function() {
    const strip = this.strips.values;
    strip.innerHTML = '';
    VALUES.forEach((value) => {
        strip.appendChild(makeTag(this.data.labels[value], this.activeValues.has(value),
            {line: 'values', value: value}, '', VALUE_CLASS[value]));
    });
    this.updateCount('values', VALUES.filter((value) => this.activeValues.has(value)).length,
        VALUES.length);
};

/**
 * The roles and capabilities the current filters leave visible, i.e. exactly
 * the columns and rows of the rendered table. Shared with the CSV export so the
 * file can never drift from what is on screen.
 *
 * @return {Object} {roles: Array, caps: Array}
 */
Controller.prototype.visibleRows = function() {
    return {
        roles: this.data.roles.filter((r) => this.activeRoles.has(r.id)),
        caps: this.data.capabilities.filter((c) => this.capIncluded(c)),
    };
};

Controller.prototype.renderTable = function() {
    const {roles, caps} = this.visibleRows();
    const container = this.root.querySelector('#rolesaudit-table');

    if (!roles.length || !caps.length) {
        container.innerHTML = '<div class="alert alert-info" role="alert">'
            + esc(this.data.labels.empty || '') + '</div>';
        return;
    }

    let html = '<table class="rolesaudit-table"><thead><tr><th class="rolesaudit-capcol"></th>';
    roles.forEach((role) => {
        html += '<th class="rolesaudit-rolecol">' + esc(role.localname) + '</th>';
    });
    html += '</tr></thead><tbody>';

    caps.forEach((cap) => {
        html += '<tr><th scope="row" class="rolesaudit-capcell" title="' + esc(cap.def) + '">'
            + '<span class="rolesaudit-caplabel">' + esc(cap.action) + '</span>'
            + '<span class="rolesaudit-capname">' + esc(cap.name) + '</span></th>';
        roles.forEach((role) => {
            const permission = this.value(role.id, cap.name);
            const modified = this.isModified(role, cap.name);
            const editattrs = this.editable
                ? ' role="button" tabindex="0" title="' + esc(this.data.labels.edithint || '') + '"'
                : '';
            html += '<td class="rolesaudit-cell ' + VALUE_CLASS[permission]
                + (modified ? ' rolesaudit-modified' : '') + '"'
                + ' data-roleid="' + role.id + '" data-capability="' + esc(cap.name) + '"'
                + ' data-permission="' + permission + '"' + editattrs + '>'
                + '<span class="rolesaudit-value">' + esc(this.data.labels[permission]) + '</span>'
                + (modified ? '<span class="rolesaudit-badge">*</span>' : '')
                + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
};

Controller.prototype.renderDownstream = function() {
    this.renderPluginTags();
    this.renderCapTags();
    this.renderTable();
};

Controller.prototype.renderAll = function() {
    this.renderRoleTags();
    this.renderTypeTags();
    this.renderPluginTags();
    this.renderCapTags();
    this.renderValueTags();
    this.renderTable();
    this.refreshStrips();
};

/**
 * Toggle a value in a set and return the new active state.
 *
 * @param {Set} set
 * @param {*} value
 * @return {Boolean}
 */
const toggleInSet = (set, value) => {
    if (set.has(value)) {
        set.delete(value);
        return false;
    }
    set.add(value);
    return true;
};

/**
 * The autocomplete entries of one line for the given query, capped at
 * MAX_SUGGESTIONS.
 *
 * Suggestions are gated exactly like the line's own tag strip: only the
 * preceding filters restrict them (plugin types for plugins, types and plugins
 * for capabilities). The value filter and the "only modified" checkbox refine
 * the table afterwards and deliberately do not narrow the suggestions.
 *
 * @param {String} line
 * @param {String} query Already lower-cased, trimmed search term.
 * @return {Array} List of {value, primary, secondary}.
 */
Controller.prototype.suggestionItems = function(line, query) {
    const items = [];
    if (line === 'capabilities') {
        const caps = this.data.capabilities;
        for (let i = 0; i < caps.length && items.length < MAX_SUGGESTIONS; i++) {
            const cap = caps[i];
            if (this.pluginSelected(cap) && this.matchesSearch(cap, query)) {
                items.push({value: cap.name, primary: cap.def, secondary: cap.name});
            }
        }
    } else if (line === 'roles') {
        const roles = this.data.roles;
        for (let i = 0; i < roles.length && items.length < MAX_SUGGESTIONS; i++) {
            const role = roles[i];
            if ((role.localname + ' ' + role.shortname).toLowerCase().includes(query)) {
                items.push({value: role.localname, primary: role.localname, secondary: role.shortname});
            }
        }
    } else if (line === 'types') {
        for (let i = 0; i < this.types.length && items.length < MAX_SUGGESTIONS; i++) {
            const type = this.types[i];
            if (type.toLowerCase().includes(query)) {
                items.push({value: type, primary: type, secondary: ''});
            }
        }
    } else if (line === 'plugins') {
        const entries = this.pluginEntries();
        for (let i = 0; i < entries.length && items.length < MAX_SUGGESTIONS; i++) {
            const key = entries[i].key;
            if (key.toLowerCase().includes(query)) {
                items.push({value: key, primary: key, secondary: ''});
            }
        }
    }
    return items;
};

/**
 * Rebuild the autocomplete suggestion list of one line for the current query.
 *
 * @param {String} line
 * @param {String} query Already lower-cased, trimmed search term.
 */
Controller.prototype.renderSuggestions = function(line, query) {
    const state = this.suggest[line];
    if (!state || !state.list) {
        return;
    }
    if (!query) {
        this.closeSuggestions(line);
        return;
    }
    const items = this.suggestionItems(line, query);
    if (!items.length) {
        this.closeSuggestions(line);
        return;
    }
    state.items = items;
    state.active = -1;
    state.list.innerHTML = items.map((item, i) =>
        '<li class="rolesaudit-suggestion" role="option" id="' + state.list.id + '-' + i + '"'
        + ' data-index="' + i + '" data-value="' + esc(item.value) + '">'
        + '<span class="rolesaudit-suggestion-def">' + highlight(item.primary, query) + '</span>'
        + (item.secondary
            ? '<span class="rolesaudit-suggestion-name">' + highlight(item.secondary, query) + '</span>'
            : '')
        + '</li>').join('');
    state.list.hidden = false;
    if (state.input) {
        state.input.setAttribute('aria-expanded', 'true');
        state.input.removeAttribute('aria-activedescendant');
    }
};

/**
 * Hide and clear the suggestion list of one line.
 *
 * @param {String} line
 */
Controller.prototype.closeSuggestions = function(line) {
    const state = this.suggest[line];
    if (!state || !state.list) {
        return;
    }
    state.list.hidden = true;
    state.list.innerHTML = '';
    state.items = [];
    state.active = -1;
    if (state.input) {
        state.input.setAttribute('aria-expanded', 'false');
        state.input.removeAttribute('aria-activedescendant');
    }
};

/**
 * Highlight the suggestion at the given index (wraps around the ends).
 *
 * @param {String} line
 * @param {Number} index
 */
Controller.prototype.setActiveSuggestion = function(line, index) {
    const state = this.suggest[line];
    if (!state || !state.list) {
        return;
    }
    const items = state.list.querySelectorAll('.rolesaudit-suggestion');
    if (!items.length) {
        return;
    }
    const count = items.length;
    index = ((index % count) + count) % count;
    items.forEach((item, i) => item.classList.toggle('rolesaudit-suggestion-active', i === index));
    state.active = index;
    const active = items[index];
    active.scrollIntoView({block: 'nearest'});
    if (state.input) {
        state.input.setAttribute('aria-activedescendant', active.id);
    }
};

/**
 * Target a single entry: fill the line's search box with its exact value so the
 * line narrows down to it, then close the list. On the capability line that also
 * narrows the table down to that one row.
 *
 * @param {String} line
 * @param {String} value
 */
Controller.prototype.selectSuggestion = function(line, value) {
    const state = this.suggest[line];
    if (state && state.input) {
        state.input.value = value;
    }
    this.setLineQuery(line, value.toLowerCase());
};

/**
 * Re-render everything a change on the given filter line affects.
 *
 * @param {String} line
 */
Controller.prototype.renderForLine = function(line) {
    if (line === 'roles') {
        this.renderRoleTags();
        this.renderTable();
    } else if (line === 'types') {
        this.renderTypeTags();
        this.renderDownstream();
    } else if (line === 'plugins') {
        this.renderPluginTags();
        this.renderCapTags();
        this.renderTable();
    } else if (line === 'capabilities') {
        this.renderCapTags();
        this.renderTable();
    } else if (line === 'values') {
        this.renderValueTags();
        this.renderTable();
    }
    this.refreshStrips();
};

Controller.prototype.onTagClick = function(tag) {
    const line = tag.dataset.line;
    const raw = tag.dataset.value;

    if (line === 'roles') {
        toggleInSet(this.activeRoles, Number(raw));
    } else if (line === 'types') {
        toggleInSet(this.activeTypes, raw);
    } else if (line === 'plugins') {
        toggleInSet(this.excludedPlugins, raw);
    } else if (line === 'capabilities') {
        toggleInSet(this.excludedCaps, raw);
    } else if (line === 'values') {
        toggleInSet(this.activeValues, Number(raw));
    }
    this.renderForLine(line);
};

/**
 * Select or deselect every tag currently rendered in one filter line.
 *
 * The values are read from the strip itself rather than from the catalogue, so
 * the buttons only ever affect what the user can see: on the capabilities line
 * an active search, and on the plugins line the selected plugin types, both
 * narrow the strip and therefore the reach of these buttons.
 *
 * Note that the plugins and capabilities lines track exclusions, so selecting
 * all means removing from their set, and vice versa.
 *
 * @param {String} line
 * @param {Boolean} active Desired state of the tags.
 */
Controller.prototype.setAllInLine = function(line, active) {
    const strip = this.strips[line];
    if (!strip) {
        return;
    }
    const values = [...strip.querySelectorAll('.rolesaudit-tag')].map((tag) => tag.dataset.value);
    const apply = (set, value) => {
        if (active) {
            set.add(value);
        } else {
            set.delete(value);
        }
    };
    values.forEach((raw) => {
        if (line === 'roles') {
            apply(this.activeRoles, Number(raw));
        } else if (line === 'types') {
            apply(this.activeTypes, raw);
        } else if (line === 'plugins') {
            // Inverted: an excluded plugin is an inactive tag.
            if (active) {
                this.excludedPlugins.delete(raw);
            } else {
                this.excludedPlugins.add(raw);
            }
        } else if (line === 'capabilities') {
            if (active) {
                this.excludedCaps.delete(raw);
            } else {
                this.excludedCaps.add(raw);
            }
        } else if (line === 'values') {
            apply(this.activeValues, Number(raw));
        }
    });
    this.renderForLine(line);
};

/**
 * Persist a permission change and patch the edited cell in place.
 *
 * @param {HTMLElement} cell
 * @param {Number} permission
 * @param {Function} cleanup
 */
Controller.prototype.save = function(cell, permission, cleanup) {
    const pending = new Pending('local_rolesaudit/save');
    const roleid = parseInt(cell.dataset.roleid, 10);
    const capability = cell.dataset.capability;
    const self = this;
    Ajax.call([{
        methodname: 'local_rolesaudit_set_permission',
        args: {roleid: roleid, capability: capability, permission: permission},
    }])[0].then((response) => {
        // Keep the in-memory matrix in sync.
        if (!self.data.matrix[roleid]) {
            self.data.matrix[roleid] = {};
        }
        self.data.matrix[roleid][capability] = response.permission;

        cell.dataset.permission = response.permission;
        Object.values(VALUE_CLASS).forEach((cls) => cell.classList.remove(cls));
        cell.classList.add(response.cssclass);
        const value = cell.querySelector('.rolesaudit-value');
        if (value) {
            value.textContent = response.label;
        }
        cell.classList.toggle('rolesaudit-modified', response.ismodified);
        let badge = cell.querySelector('.rolesaudit-badge');
        if (response.ismodified && !badge) {
            badge = document.createElement('span');
            badge.className = 'rolesaudit-badge';
            badge.textContent = '*';
            cell.appendChild(badge);
        } else if (!response.ismodified && badge) {
            badge.remove();
        }
        cell.classList.add('rolesaudit-saved');
        setTimeout(() => cell.classList.remove('rolesaudit-saved'), 800);
        cleanup();
        pending.resolve();
        return response;
    }).catch((error) => {
        cleanup();
        pending.resolve();
        Notification.exception(error);
    });
};

Controller.prototype.openEditor = function(cell) {
    if (!this.editable || !this.menuTemplate || cell.querySelector('select')) {
        return;
    }
    const current = cell.dataset.permission;
    const select = this.menuTemplate.cloneNode(true);
    select.classList.remove('rolesaudit-menu-template', 'd-none');
    select.classList.add('rolesaudit-menu');
    select.removeAttribute('aria-hidden');
    select.setAttribute('tabindex', '0');
    select.value = current;

    const value = cell.querySelector('.rolesaudit-value');
    if (value) {
        value.classList.add('d-none');
    }
    cell.appendChild(select);
    select.focus();

    let done = false;
    const cleanup = () => {
        if (done) {
            return;
        }
        done = true;
        select.remove();
        if (value) {
            value.classList.remove('d-none');
        }
    };
    const self = this;
    select.addEventListener('change', () => {
        const newValue = parseInt(select.value, 10);
        if (newValue === parseInt(current, 10)) {
            cleanup();
            return;
        }
        self.save(cell, newValue, cleanup);
    });
    select.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cleanup();
            cell.focus();
        }
    });
    select.addEventListener('blur', () => setTimeout(cleanup, 150));
};

/**
 * Read the saved filter set, or null when there is none (or it is unusable).
 *
 * @return {Object|null}
 */
Controller.prototype.readStored = function() {
    let raw;
    try {
        raw = window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        // Storage disabled by the browser: behave as if nothing was saved.
        return null;
    }
    if (!raw) {
        return null;
    }
    try {
        const stored = JSON.parse(raw);
        return stored && stored.v === 1 ? stored : null;
    } catch (e) {
        return null;
    }
};

/**
 * Enable the load button only when there is something to load.
 */
Controller.prototype.refreshLoadButton = function() {
    const button = this.root.querySelector('[data-action="rolesaudit-loadfilters"]');
    if (button) {
        button.disabled = this.readStored() === null;
    }
};

/**
 * Store the whole filter state in this browser, overwriting the previous set.
 */
Controller.prototype.saveFilters = function() {
    const payload = {
        v: 1,
        roles: [...this.activeRoles],
        types: [...this.activeTypes],
        excludedplugins: [...this.excludedPlugins],
        excludedcaps: [...this.excludedCaps],
        values: [...this.activeValues],
        onlymodified: this.onlyModified,
        search: this.search,
        linesearch: Object.assign({}, this.lineSearch),
    };
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (error) {
        Notification.exception(error);
        return;
    }
    this.refreshLoadButton();
    addToast(this.data.labels.filterssaved || '');
};

/**
 * Restore the filter state saved by saveFilters().
 */
Controller.prototype.loadFilters = function() {
    const stored = this.readStored();
    if (!stored) {
        this.refreshLoadButton();
        addToast(this.data.labels.filtersnosaved || '', {type: 'warning'});
        return;
    }
    const list = (value) => (Array.isArray(value) ? value : []);
    this.activeRoles = new Set(list(stored.roles).map(Number));
    this.activeTypes = new Set(list(stored.types).map(String));
    this.excludedPlugins = new Set(list(stored.excludedplugins).map(String));
    this.excludedCaps = new Set(list(stored.excludedcaps).map(String));
    this.activeValues = new Set(list(stored.values).map(Number));
    this.onlyModified = stored.onlymodified !== false;
    this.search = typeof stored.search === 'string' ? stored.search : '';

    const modifiedBox = this.root.querySelector('[data-action="rolesaudit-onlymodified"]');
    if (modifiedBox) {
        modifiedBox.checked = this.onlyModified;
    }

    // Per-line search terms, with each box revealed only where one is set.
    const searches = stored.linesearch && typeof stored.linesearch === 'object' ? stored.linesearch : {};
    Object.keys(this.lineSearch).forEach((line) => {
        this.lineSearch[line] = typeof searches[line] === 'string' ? searches[line] : '';
    });
    Object.keys(this.suggest).forEach((line) => {
        const query = line === 'capabilities' ? this.search : this.lineSearch[line];
        if (this.suggest[line].input) {
            this.suggest[line].input.value = query;
        }
        this.closeSuggestions(line);
        this.toggleLineSearch(line, Boolean(query), false);
    });

    this.renderAll();
    Object.values(this.strips).forEach(scrollActiveIntoView);
    addToast(this.data.labels.filtersloaded || '');
};

/**
 * Download the filtered table as a semicolon-delimited CSV: the capability name
 * and description, then two columns per displayed role (permission, and whether
 * it differs from the archetype default).
 */
Controller.prototype.exportCsv = function() {
    const {roles, caps} = this.visibleRows();
    if (!roles.length || !caps.length) {
        addToast(this.data.labels.empty || '', {type: 'warning'});
        return;
    }
    const labels = this.data.labels;
    const modifiedHeader = (role) => String(labels.csvmodified || '{$a}').replace('{$a}', role.localname);

    const header = [labels.csvcapability, labels.csvdescription];
    roles.forEach((role) => header.push(role.localname, modifiedHeader(role)));

    const lines = [header.map(csvCell).join(CSV_DELIMITER)];
    caps.forEach((cap) => {
        const row = [cap.name, cap.def];
        roles.forEach((role) => {
            row.push(labels[this.value(role.id, cap.name)],
                this.isModified(role, cap.name) ? labels.yes : labels.no);
        });
        lines.push(row.map(csvCell).join(CSV_DELIMITER));
    });

    // The BOM makes spreadsheets open the file as UTF-8 rather than as the local codepage.
    downloadFile(this.data.csvfilename || 'rolesaudit.csv', 'text/csv;charset=utf-8',
        '\uFEFF' + lines.join('\r\n') + '\r\n');
};

Controller.prototype.wire = function() {
    // Tag clicks (delegated on the filters container), guarding drag "clicks".
    // The per-line select-all / select-none buttons ride on the same listener;
    // being real buttons, they need no keyboard handling of their own.
    const filters = this.root.querySelector('.rolesaudit-filters');
    const handleActivate = (e) => {
        const button = e.target.closest('[data-action^="rolesaudit-select"]');
        if (button) {
            this.setAllInLine(button.dataset.line, button.dataset.action === 'rolesaudit-selectall');
            return;
        }
        const searchToggle = e.target.closest('[data-action="rolesaudit-searchtoggle"]');
        if (searchToggle) {
            this.toggleLineSearch(searchToggle.dataset.line);
            return;
        }
        const tag = e.target.closest('.rolesaudit-tag');
        if (!tag) {
            return;
        }
        const strip = tag.closest('.rolesaudit-strip');
        if (strip && strip.dataset.suppressClick === '1') {
            strip.dataset.suppressClick = '';
            return;
        }
        this.onTagClick(tag);
    };
    filters.addEventListener('click', handleActivate);
    filters.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            if (e.target.classList && e.target.classList.contains('rolesaudit-tag')) {
                e.preventDefault();
                handleActivate(e);
            }
        }
    });

    // Search box and autocomplete list of every line that has one.
    Object.keys(this.suggest).forEach((line) => {
        const state = this.suggest[line];
        if (state.input) {
            const run = debounce(() => {
                const query = state.input.value.toLowerCase().trim();
                this.setLineQuery(line, query);
                this.renderSuggestions(line, query);
            }, 200);
            // Keys that drive the suggestion list rather than the query itself.
            const navKeys = ['ArrowDown', 'ArrowUp', 'Enter', 'Escape'];
            state.input.addEventListener('keyup', (e) => {
                if (!navKeys.includes(e.key)) {
                    run(e);
                }
            });
            state.input.addEventListener('keydown', (e) => {
                if (!state.list || state.list.hidden) {
                    // With no suggestions showing, Escape collapses the search box.
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        this.toggleLineSearch(line, false);
                    }
                    return;
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.setActiveSuggestion(line, state.active + 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.setActiveSuggestion(line, state.active - 1);
                } else if (e.key === 'Enter') {
                    if (state.active >= 0 && state.items[state.active]) {
                        e.preventDefault();
                        this.selectSuggestion(line, state.items[state.active].value);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.closeSuggestions(line);
                }
            });
            // Close after the browser processes any pending click on a suggestion.
            state.input.addEventListener('blur', () => setTimeout(() => this.closeSuggestions(line), 150));
        }
        if (state.list) {
            // Keep focus on the input so the blur-close doesn't race the click.
            state.list.addEventListener('mousedown', (e) => e.preventDefault());
            state.list.addEventListener('click', (e) => {
                const item = e.target.closest('.rolesaudit-suggestion');
                if (item) {
                    this.selectSuggestion(line, item.dataset.value);
                }
            });
        }
    });

    // A narrower window makes a strip scrollable (or a wider one stops it being
    // so), which its arrows and edge fades have to reflect.
    window.addEventListener('resize', debounce(() => this.refreshStrips(), 150));

    // Only-modified checkbox.
    const modifiedBox = this.root.querySelector('[data-action="rolesaudit-onlymodified"]');
    if (modifiedBox) {
        modifiedBox.addEventListener('change', () => {
            this.onlyModified = modifiedBox.checked;
            this.renderTable();
        });
    }

    // Toolbar next to the page heading: filter set persistence and CSV export.
    const toolbar = {
        'rolesaudit-savefilters': () => this.saveFilters(),
        'rolesaudit-loadfilters': () => this.loadFilters(),
        'rolesaudit-exportcsv': () => this.exportCsv(),
    };
    Object.keys(toolbar).forEach((action) => {
        const button = this.root.querySelector('[data-action="' + action + '"]');
        if (button) {
            button.addEventListener('click', toolbar[action]);
        }
    });
    this.refreshLoadButton();

    // Inline editing (delegated on the table container).
    const table = this.root.querySelector('#rolesaudit-table');
    table.addEventListener('click', (e) => {
        const cell = e.target.closest('.rolesaudit-cell');
        if (cell) {
            this.openEditor(cell);
        }
    });
    table.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ')) {
            const cell = e.target.closest('.rolesaudit-cell');
            if (cell && cell === document.activeElement) {
                e.preventDefault();
                this.openEditor(cell);
            }
        }
    });
};

/**
 * Initialise the compare page.
 */
export const init = () => {
    const root = document.querySelector('.rolesaudit-compare');
    if (!root) {
        return;
    }
    const data = readData(root);
    if (!data) {
        return;
    }
    const controller = new Controller(root, data);
    controller.renderAll();
    controller.wire();
    // Bring the first active tag of each strip into view (e.g. the "moodle" type).
    Object.values(controller.strips).forEach(scrollActiveIntoView);
};

/**
 * Initialise the role-picker tags on the export page.
 *
 * Tags mirror the compare page's first line; the selected role ids are kept in
 * sync with a hidden container of inputs submitted with the export form.
 */
export const initExport = () => {
    const root = document.querySelector('.rolesaudit-exportroles');
    if (!root) {
        return;
    }
    const data = JSON.parse(root.querySelector('#rolesaudit-exportdata').textContent);
    const strip = root.querySelector('.rolesaudit-strip');
    const hidden = root.querySelector('.rolesaudit-hiddenroles');
    const active = new Set(data.activeroles.map(Number));

    const syncHidden = () => {
        hidden.innerHTML = '';
        active.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'roles[]';
            input.value = id;
            hidden.appendChild(input);
        });
    };
    enableDragScroll(strip);
    const refreshArrows = addScrollArrows(strip, data.labels || {});

    const render = () => {
        strip.innerHTML = '';
        data.roles.forEach((role) => {
            strip.appendChild(makeTag(role.localname, active.has(role.id),
                {value: role.id}, role.shortname));
        });
        refreshArrows();
    };

    strip.addEventListener('click', (e) => {
        const tag = e.target.closest('.rolesaudit-tag');
        if (!tag || strip.dataset.suppressClick === '1') {
            strip.dataset.suppressClick = '';
            return;
        }
        toggleInSet(active, Number(tag.dataset.value));
        render();
        syncHidden();
    });

    render();
    syncHidden();
};

/**
 * Initialise the per-row selection on the import preview page.
 *
 * Every change row carries a checkbox, ticked by default; this adds the select
 * all / deselect all buttons, keeps the counter in sync and blocks an empty
 * submission. Without JS the checkboxes still post as changes[].
 */
export const initImport = () => {
    const form = document.querySelector('.rolesaudit-importform');
    if (!form) {
        return;
    }
    const boxes = Array.from(form.querySelectorAll('.rolesaudit-importcheck'));
    const counter = form.querySelector('.rolesaudit-selcount');
    const submit = form.querySelector('button[type="submit"]');

    const refresh = () => {
        const selected = boxes.filter((box) => box.checked).length;
        counter.textContent = selected;
        submit.disabled = (selected === 0);
    };

    form.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action^="rolesaudit-import-select"]');
        if (!button) {
            return;
        }
        const on = button.dataset.action === 'rolesaudit-import-selectall';
        boxes.forEach((box) => {
            box.checked = on;
        });
        refresh();
    });

    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('rolesaudit-importcheck')) {
            refresh();
        }
    });

    // Collapse the checkboxes into one field: past max_input_vars PHP drops the
    // extra ones silently, which would apply an arbitrary subset without warning.
    form.addEventListener('submit', () => {
        const packed = document.createElement('input');
        packed.type = 'hidden';
        packed.name = 'changeskeys';
        packed.value = boxes.filter((box) => box.checked).map((box) => box.value).join('\n');
        form.appendChild(packed);
        boxes.forEach((box) => box.removeAttribute('name'));
    });

    refresh();
};
