/**
 * AMD module for dynamic filtering of items in the flat layout view.
 *
 * Provides real-time text search, multi-select category chip filtering,
 * sorting, and a "remove all filters" button.
 *
 * @module     block_exaport/flat_filter
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var selectedCategories = {}; // {id: name}
    var searchInput;
    var categorySelect;
    var sortSelect;
    var chipsContainer;
    var clearAllLabel = 'Clear all filters';

    /**
     * Render chips and "remove all" button into the chips container.
     */
    function renderChips() {
        if (!chipsContainer) {
            return;
        }
        chipsContainer.innerHTML = '';
        var ids = Object.keys(selectedCategories);
        if (ids.length === 0) {
            return;
        }
        ids.forEach(function(id) {
            var chip = document.createElement('span');
            chip.className = 'badge badge-primary d-inline-flex align-items-center mr-1 mb-1';
            chip.style.cssText = 'font-size: 0.85rem; padding: 0.35em 0.6em; cursor: pointer; gap: 0.3em;';
            chip.textContent = selectedCategories[id] + ' ';
            var closeBtn = document.createElement('span');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'margin-left: 0.3em; font-size: 1.1em; line-height: 1; cursor: pointer;';
            closeBtn.setAttribute('aria-label', 'Remove');
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                delete selectedCategories[id];
                renderChips();
                filterItems();
            });
            chip.appendChild(closeBtn);
            chipsContainer.appendChild(chip);
        });

        // "Remove all" button.
        var removeAll = document.createElement('span');
        removeAll.className = 'badge badge-danger d-inline-flex align-items-center mr-1 mb-1';
        removeAll.style.cssText = 'font-size: 0.85rem; padding: 0.35em 0.6em; cursor: pointer; gap: 0.3em;';
        removeAll.textContent = clearAllLabel + ' ';
        var closeAll = document.createElement('span');
        closeAll.innerHTML = '&times;';
        closeAll.style.cssText = 'margin-left: 0.3em; font-size: 1.1em; line-height: 1;';
        removeAll.appendChild(closeAll);
        removeAll.addEventListener('click', function() {
            selectedCategories = {};
            renderChips();
            filterItems();
        });
        chipsContainer.appendChild(removeAll);
    }

    /**
     * Filter and sort items based on current state.
     */
    function filterItems() {
        var searchText = (searchInput ? searchInput.value : '').toLowerCase();
        var selectedCatIds = Object.keys(selectedCategories).map(Number);

        var items = document.querySelectorAll('.exaport-flat-item');
        items.forEach(function(item) {
            var name = item.getAttribute('data-item-name') || '';
            var catIdsStr = item.getAttribute('data-category-ids') || '';
            var catIds = catIdsStr ? catIdsStr.split(',').map(Number) : [];

            var matchesSearch = !searchText || name.indexOf(searchText) !== -1;
            var matchesCategory = selectedCatIds.length === 0 || selectedCatIds.some(function(catId) {
                return catIds.indexOf(catId) !== -1;
            });

            item.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
        });

        sortItems();
    }

    /**
     * Sort visible items based on the sort dropdown.
     */
    function sortItems() {
        if (!sortSelect) {
            return;
        }
        var sortVal = sortSelect.value; // e.g. "date-desc", "name-asc"
        var parts = sortVal.split('-');
        var field = parts[0]; // "date" or "name"
        var dir = parts[1]; // "asc" or "desc"

        var allItems = document.querySelectorAll('.exaport-flat-item');
        if (allItems.length === 0) {
            return;
        }
        var parent = allItems[0].parentElement;
        var items = Array.from(allItems);

        items.sort(function(a, b) {
            var valA, valB;
            if (field === 'date') {
                valA = parseInt(a.getAttribute('data-item-date') || '0', 10);
                valB = parseInt(b.getAttribute('data-item-date') || '0', 10);
            } else {
                valA = a.getAttribute('data-item-name') || '';
                valB = b.getAttribute('data-item-name') || '';
            }
            var cmp;
            if (typeof valA === 'number') {
                cmp = valA - valB;
            } else {
                cmp = valA.localeCompare(valB);
            }
            return dir === 'asc' ? cmp : -cmp;
        });

        items.forEach(function(item) {
            parent.appendChild(item);
        });
    }

    /**
     * Build a custom searchable dropdown to replace the native select element.
     * The widget uses the same form-control styling and fits in the same space.
     */
    function buildSearchableDropdown() {
        if (!categorySelect) {
            return;
        }
        var wrapper = categorySelect.parentElement;
        var options = [];
        for (var i = 1; i < categorySelect.options.length; i++) { // Skip placeholder at index 0.
            options.push({id: categorySelect.options[i].value, name: categorySelect.options[i].text});
        }
        var placeholder = categorySelect.options[0] ? categorySelect.options[0].text : 'Category';

        // Hide the native select.
        categorySelect.style.display = 'none';

        // Create the container.
        var container = document.createElement('div');
        container.className = 'exaport-searchable-select';
        container.style.cssText = 'position: relative; width: 100%;';

        // Create search input (styled like the select) with a dropdown arrow.
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.placeholder = placeholder;
        input.setAttribute('autocomplete', 'off');
        input.style.cssText = 'padding-right: 2em;';

        // Dropdown arrow indicator.
        var arrow = document.createElement('span');
        arrow.innerHTML = '&#9662;';
        arrow.style.cssText = 'position: absolute; right: 0.75em; top: 50%; transform: translateY(-50%);'
            + ' pointer-events: none; font-size: 0.9em; color: #555;';

        // Create dropdown list.
        var dropdown = document.createElement('div');
        dropdown.className = 'exaport-searchable-select-dropdown';
        dropdown.style.cssText = 'display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;'
            + ' max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ced4da;'
            + ' border-top: none; border-radius: 0 0 0.25rem 0.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';

        /**
         * Render dropdown options filtered by search text.
         * @param {string} filter Text to filter options by.
         */
        function renderOptions(filter) {
            dropdown.innerHTML = '';
            var lowerFilter = (filter || '').toLowerCase();
            var hasResults = false;
            options.forEach(function(opt) {
                // Hide already-selected categories entirely.
                if (selectedCategories[opt.id]) {
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
                    e.preventDefault(); // Prevent input blur.
                    selectedCategories[opt.id] = opt.name;
                    renderChips();
                    filterItems();
                    // Re-render options to remove the just-selected item, keep dropdown open.
                    renderOptions(input.value);
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

        // Show dropdown on focus.
        input.addEventListener('focus', function() {
            renderOptions(input.value);
            dropdown.style.display = 'block';
        });

        // Filter on input.
        input.addEventListener('input', function() {
            renderOptions(input.value);
            dropdown.style.display = 'block';
        });

        // Hide dropdown on blur.
        input.addEventListener('blur', function() {
            dropdown.style.display = 'none';
            input.value = '';
        });

        container.appendChild(input);
        container.appendChild(arrow);
        container.appendChild(dropdown);
        wrapper.appendChild(container);
    }

    return {
        /**
         * Initialise the flat filter module.
         *
         * @param {string} clearAllString The translated "clear all filters" label.
         */
        init: function(clearAllString) {
            clearAllLabel = clearAllString || clearAllLabel;
            searchInput = document.getElementById('exaport-flat-search');
            categorySelect = document.getElementById('exaport-flat-category-select');
            sortSelect = document.getElementById('exaport-flat-sort-select');
            chipsContainer = document.getElementById('exaport-flat-filter-chips');

            // Bind text search input event.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterItems();
                });
            }

            // Replace native category select with a custom searchable dropdown.
            buildSearchableDropdown();

            // Bind sort dropdown.
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    sortItems();
                });
            }
        }
    };
});
