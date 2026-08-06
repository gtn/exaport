/**
 * Reusable searchable "select" dropdown widget.
 *
 * Progressively enhances a native <select> (kept hidden, used purely as the option
 * data source) with a search input + dropdown list, matching Moodle's
 * core/form-autocomplete visual style (search box with a caret, filtered list below).
 *
 * Shared by:
 * - block_exaport/flat_filter: multi-select category filter, chips rendered in an
 *   external container, dropdown stays open after each pick.
 * - block_exaport/folder_category_select: single-select category navigator,
 *   picking an option navigates to that category (page reload).
 *
 * @module     block_exaport/category_select
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Progressively enhance a native select into a searchable dropdown.
     *
     * @param {Object} config
     * @param {HTMLSelectElement} config.select The native select (hidden, used as option source).
     * @param {string} [config.inputId] Id to give the generated search input.
     * @param {string} [config.placeholder] Search input placeholder text.
     * @param {Function} [config.isSelected] function(id):boolean - options for which this returns
     *        true are hidden from the dropdown (used for multi-select so picked items disappear).
     *        Defaults to always false (nothing hidden).
     * @param {Function} config.onPick function(id, name) - called when the user picks an option.
     * @param {boolean} [config.keepOpenOnPick] If true, the dropdown stays open after a pick
     *        (multi-select). If false (default), the dropdown closes after a pick (single-select).
     *        In both cases the search input is always cleared after a pick / on blur.
     * @param {boolean} [config.skipFirstOption] Skip the select's first <option> (e.g. a non-selectable
     *        placeholder label such as "Category"). Default false.
     * @return {Object} {refresh: function(filterText)} - call to re-render the (open) option list,
     *        e.g. after external state changes such as a pick updating isSelected()'s data source.
     */
    var build = function(config) {
        var select = config.select;
        if (!select) {
            return {refresh: function() {}};
        }
        var wrapper = select.parentElement;
        var placeholder = config.placeholder || '';
        var isSelected = config.isSelected || function() {
            return false;
        };
        var onPick = config.onPick || function() {};
        var keepOpenOnPick = !!config.keepOpenOnPick;
        var skipFirstOption = !!config.skipFirstOption;

        var options = [];
        for (var i = (skipFirstOption ? 1 : 0); i < select.options.length; i++) {
            options.push({id: select.options[i].value, name: select.options[i].text});
        }

        // Hide the native select; it stays in the DOM purely as the option data source
        // and as a no-JS fallback (its onchange/onsubmit behaviour, if any, still works).
        select.style.display = 'none';

        var container = document.createElement('div');
        container.className = 'exaport-searchable-select';
        container.style.cssText = 'position: relative; width: 100%;';

        var input = document.createElement('input');
        input.type = 'text';
        input.id = config.inputId || 'exaport-category-search';
        input.className = 'form-control';
        input.placeholder = placeholder;
        input.setAttribute('autocomplete', 'off');
        input.style.cssText = 'padding-right: 2em;';

        // Dropdown arrow indicator — replicates Moodle's core/form-autocomplete pattern:
        // a <span class="form-autocomplete-downarrow"> wrapping an <i class="icon fa fa-caret-down">.
        var arrow = document.createElement('span');
        arrow.className = 'form-autocomplete-downarrow';
        arrow.style.cssText = 'position: absolute; right: 0.5em; top: 50%; transform: translateY(-50%);'
            + ' pointer-events: none; line-height: 1;';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.innerHTML = '<i class="icon fa fa-caret-down fa-fw" aria-hidden="true"></i>';

        // Dropdown list — uses Bootstrap utility classes for theme-consistent styling.
        var dropdown = document.createElement('div');
        dropdown.className = 'exaport-searchable-select-dropdown bg-white border rounded-bottom shadow-sm';
        dropdown.style.cssText = 'display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;'
            + ' max-height: 200px; overflow-y: auto; border-top: none;';

        var highlightedIndex = -1;

        /**
         * Update visual highlight on dropdown items and scroll it into view.
         */
        function updateHighlight() {
            var items = dropdown.querySelectorAll('.exaport-searchable-select-item');
            items.forEach(function(el, idx) {
                el.style.backgroundColor = (idx === highlightedIndex) ? '#f0f0f0' : '';
            });
            if (items[highlightedIndex]) {
                items[highlightedIndex].scrollIntoView({block: 'nearest'});
            }
        }

        /**
         * Render dropdown options filtered by search text.
         *
         * @param {string} filter Text to filter options by.
         */
        function renderOptions(filter) {
            dropdown.innerHTML = '';
            var lowerFilter = (filter || '').toLowerCase();
            var hasResults = false;
            options.forEach(function(opt) {
                if (isSelected(opt.id)) {
                    return;
                }
                if (lowerFilter && opt.name.toLowerCase().indexOf(lowerFilter) === -1) {
                    return;
                }
                hasResults = true;
                var item = document.createElement('div');
                item.className = 'exaport-searchable-select-item';
                item.style.cssText = 'padding: 0.4em 0.75em; cursor: pointer; font-size: 0.9rem;';
                item.textContent = opt.name;
                item.setAttribute('data-id', opt.id);

                item.addEventListener('mousedown', function(e) {
                    // Use mousedown (not click) so we can preventDefault() to stop
                    // the input from losing focus — this keeps the dropdown open when needed.
                    e.preventDefault();
                    onPick(opt.id, opt.name);
                    // Always clear the search text after a pick (single- and multi-select alike),
                    // ready for the next search rather than showing the picked option's text.
                    input.value = '';
                    if (keepOpenOnPick) {
                        // Re-render to remove the just-selected item (multi-select).
                        renderOptions(input.value);
                    } else {
                        dropdown.style.display = 'none';
                    }
                });
                item.addEventListener('mouseenter', function() {
                    item.style.backgroundColor = '#f0f0f0';
                });
                item.addEventListener('mouseleave', function() {
                    item.style.backgroundColor = '';
                });
                dropdown.appendChild(item);
            });
            if (!hasResults) {
                var noResult = document.createElement('div');
                noResult.style.cssText = 'padding: 0.4em 0.75em; color: #999; font-size: 0.9rem;';
                noResult.textContent = '—';
                dropdown.appendChild(noResult);
            }
        }

        input.addEventListener('focus', function() {
            highlightedIndex = -1;
            renderOptions(input.value);
            dropdown.style.display = 'block';
        });

        input.addEventListener('input', function() {
            highlightedIndex = -1;
            renderOptions(input.value);
            dropdown.style.display = 'block';
        });

        input.addEventListener('keydown', function(e) {
            var items = dropdown.querySelectorAll('.exaport-searchable-select-item');
            if (!items.length) {
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % items.length;
                updateHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + items.length) % items.length;
                updateHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    items[highlightedIndex].dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
                }
            } else if (e.key === 'Escape') {
                input.blur();
            }
        });

        input.addEventListener('blur', function() {
            dropdown.style.display = 'none';
            highlightedIndex = -1;
            input.value = ''; // Always empty when not focused, whatever was picked/typed.
        });

        container.appendChild(input);
        container.appendChild(arrow);
        container.appendChild(dropdown);
        wrapper.appendChild(container);

        return {
            refresh: function(filter) {
                renderOptions(typeof filter === 'string' ? filter : input.value);
            }
        };
    };

    return {build: build};
});

