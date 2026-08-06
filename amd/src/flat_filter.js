/**
 * AMD module for dynamic filtering of items in the flat layout view.
 *
 * Provides real-time text search, multi-select category chip filtering,
 * sorting, and a "remove all filters" button.
 * Supports restoring filter state from sessionStorage after page reloads
 * (e.g. when toggling "show items from other users").
 *
 * @module     block_exaport/flat_filter
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['block_exaport/prefs', 'block_exaport/category_select'], function(Prefs, CategorySelect) {

    // Module-level state: we keep references to DOM elements and selection state here
    // so all functions can access them without re-querying the DOM.
    var selectedCategories = {}; // Map of {categoryId: categoryName} for currently active filters.
    var searchInput;        // Text search input element.
    var categorySelect;     // The native <select> (hidden, used as data source for the custom dropdown).
    var sortSelect;         // Sort-by dropdown.
    var chipsContainer;     // Container where selected category chips are rendered below the filter bar.
    var subcategoriesCheckbox; // "Show items from subcategories" checkbox.
    var categoryChildrenMap = {}; // Map of {parentId: [childId, ...]} for expanding filters to subcategories.
    var clearAllLabel = 'Clear all filters'; // Translatable via init() parameter.
    var searchCategoryLabel = 'Search Category...'; // Translatable via init() parameter.
    var entryTypeSelect;    // Entry-type filter dropdown (All / Only items / Only views).

    /**
     * Render category chips and a "remove all" button into the chips container.
     * Each chip represents one active category filter and can be removed individually.
     * This is the visual counterpart to the selectedCategories state object.
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
            // badge-primary for BS4 (Moodle 3.x), bg-primary for BS5 (Moodle 4.x).
            chip.className = 'badge bg-secondary text-dark m-1';
            chip.style.cssText = 'font-size: 100%';
            chip.setAttribute('data-cat-id', id);
            var closeBtn = document.createElement('span');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'margin-right: 0.3em; font-size: 1.1em; line-height: 1; cursor: pointer;';
            closeBtn.setAttribute('aria-label', 'Remove');
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                delete selectedCategories[id];
                renderChips();
                filterItems();
            });
            chip.appendChild(closeBtn);
            chip.appendChild(document.createTextNode(selectedCategories[id]));
            chipsContainer.appendChild(chip);
        });

        // "Remove all" button — badge-danger for BS4, bg-danger for BS5.
        var removeAll = document.createElement('span');
        removeAll.className = 'badge bg-primary text-light m-1';
        removeAll.style.cssText = 'font-size: 0.85rem; padding: 0.35em 0.6em; cursor: pointer; gap: 0.3em;';
        removeAll.textContent = clearAllLabel;
        removeAll.addEventListener('click', function() {
            selectedCategories = {};
            renderChips();
            filterItems();
        });
        chipsContainer.appendChild(removeAll);
    }

    /**
     * Given a category ID, collect all descendant category IDs recursively
     * using the categoryChildrenMap.
     *
     * @param {number} catId The parent category ID.
     * @return {number[]} Array of all descendant category IDs.
     */
    function getDescendantCatIds(catId) {
        var descendants = [];
        var stack = [catId];
        var visited = {};
        while (stack.length > 0) {
            var current = stack.pop();
            if (visited[current]) {
                continue;
            }
            visited[current] = true;
            var children = categoryChildrenMap[current];
            if (children) {
                for (var i = 0; i < children.length; i++) {
                    descendants.push(children[i]);
                    stack.push(children[i]);
                }
            }
        }
        return descendants;
    }

    /**
     * Filter and sort visible items based on current search text and selected categories.
     * Items are shown/hidden via display style — no DOM removal, so state is preserved.
     */
    function filterItems() {
        var searchText = (searchInput ? searchInput.value : '').toLowerCase();
        var selectedCatIds = Object.keys(selectedCategories).map(Number);
        var selectedEntryType = entryTypeSelect ? entryTypeSelect.value : 'all';

        // If "show subcategories" is checked, expand each selected category to include descendants.
        var includeSubcats = subcategoriesCheckbox && subcategoriesCheckbox.checked;
        var matchCatIds = selectedCatIds.slice();
        if (includeSubcats && selectedCatIds.length > 0) {
            selectedCatIds.forEach(function(catId) {
                var descendants = getDescendantCatIds(catId);
                for (var i = 0; i < descendants.length; i++) {
                    if (matchCatIds.indexOf(descendants[i]) === -1) {
                        matchCatIds.push(descendants[i]);
                    }
                }
            });
        }

        var items = document.querySelectorAll('.exaport-flat-item');
        items.forEach(function(item) {
            var name = item.getAttribute('data-item-name') || '';
            var catIdsStr = item.getAttribute('data-category-ids') || '';
            var catIds = catIdsStr ? catIdsStr.split(',').map(Number) : [];
            var entryType = item.getAttribute('data-entry-type') || 'item';

            var matchesSearch = !searchText || name.indexOf(searchText) !== -1;
            var matchesCategory = matchCatIds.length === 0 || matchCatIds.some(function(catId) {
                return catIds.indexOf(catId) !== -1;
            });
            var matchesEntryType = !selectedEntryType || selectedEntryType === 'all' || entryType === selectedEntryType;

            item.style.display = (matchesSearch && matchesCategory && matchesEntryType) ? '' : 'none';
        });

        document.querySelectorAll('.exaport-folder-category[data-item-name]').forEach(function(tile) {
            var name = tile.getAttribute('data-item-name') || '';
            var pinned = tile.getAttribute('data-pinned') === 'true';
            tile.style.display = (pinned || !searchText || name.indexOf(searchText) !== -1) ? '' : 'none';
        });

        sortItems();
    }

    /**
     * Sort visible items by reordering DOM elements within their parent container.
     * Reads the currently selected sort field/direction from the sort dropdown.
     */
    function sortItems() {
        if (!sortSelect) {
            return;
        }
        var sortVal = sortSelect.value; // e.g. "date-desc", "name-asc"
        var parts = sortVal.split('-');
        var field = parts[0];
        var dir = parts[1] || 'desc';

        // Sort within each view section independently to avoid moving items across sections.
        document.querySelectorAll('.exaport-view-section[data-exaport-view]').forEach(function(section) {
            var items = Array.from(section.querySelectorAll('.exaport-flat-item'));
            if (items.length === 0) {
                return;
            }
            var parent = items[0].parentElement;

            items.sort(function(a, b) {
                var valA, valB;
                if (field === 'date') {
                    valA = parseInt(a.getAttribute('data-item-date') || '0', 10);
                    valB = parseInt(b.getAttribute('data-item-date') || '0', 10);
                } else if (field === 'type') {
                    valA = a.getAttribute('data-item-type') || '';
                    valB = b.getAttribute('data-item-type') || '';
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
        });
    }

    /**
     * Update table sort heading arrows and aria-sort attributes from current select value.
     */
    function updateSortHeadings() {
        var field = '';
        var dir = '';
        if (sortSelect && sortSelect.value) {
            var parts = sortSelect.value.split('-');
            field = parts[0];
            dir = parts[1] || 'desc';
        }

        document.querySelectorAll('.exaport-sort-heading').forEach(function(heading) {
            var headingField = heading.getAttribute('data-sort-field');
            var arrow = heading.querySelector('.exaport-sort-arrow');
            var active = headingField && headingField === field;
            heading.setAttribute('aria-sort', active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
            if (arrow) {
                arrow.textContent = active ? (dir === 'asc' ? '↑' : '↓') : '';
            }
        });
    }

    /**
     * Bind click handlers for details-table sort headings.
     */
    function bindSortHeadings() {
        document.querySelectorAll('.exaport-sort-heading').forEach(function(heading) {
            heading.addEventListener('click', function(e) {
                e.preventDefault();
                if (!sortSelect) {
                    return;
                }
                var field = heading.getAttribute('data-sort-field');
                if (!field) {
                    return;
                }

                var current = sortSelect.value.split('-');
                var currentField = current[0];
                var currentDir = current[1] || 'desc';
                var nextDir;
                if (field === currentField) {
                    nextDir = currentDir === 'asc' ? 'desc' : 'asc';
                } else {
                    nextDir = field === 'date' ? 'desc' : 'asc';
                }

                sortSelect.value = field + '-' + nextDir;
                if (!sortSelect.value) {
                    return;
                }
                sortSelect.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });
    }

    /**
     * Replace the native category <select> with a searchable multi-select dropdown
     * (chips rendered externally, dropdown stays open after each pick).
     * Delegates the actual widget building to the shared block_exaport/category_select module.
     */
    function buildSearchableDropdown() {
        if (!categorySelect) {
            return;
        }
        var widget = CategorySelect.build({
            select: categorySelect,
            inputId: 'exaport-category-search',
            placeholder: searchCategoryLabel,
            keepOpenOnPick: true,
            skipFirstOption: true,
            isSelected: function(id) {
                return !!selectedCategories[id];
            },
            onPick: function(id, name) {
                selectedCategories[id] = name;
                renderChips();
                filterItems();
                widget.refresh();
            }
        });
    }

    /**
     * Try to restore filter state from sessionStorage (saved before a page reload).
     * Clears the stored state after restoration so it doesn't persist across
     * manual navigations.
     */
    function restoreFilterStateFromSession() {
        var state = Prefs.restoreFilterStateFromSession();
        if (!state) {
            return false;
        }

        var restored = false;

        // Restore search text.
        if (state.search && searchInput) {
            searchInput.value = state.search;
            restored = true;
        }

        // Restore sort.
        if (state.sort && sortSelect) {
            sortSelect.value = state.sort;
            restored = true;
        }

        // Restore selected categories.
        if (state.categories && typeof state.categories === 'object') {
            // Validate that the categories still exist in the select options.
            if (categorySelect) {
                var validOptions = {};
                for (var i = 0; i < categorySelect.options.length; i++) {
                    validOptions[categorySelect.options[i].value] = categorySelect.options[i].text;
                }
                Object.keys(state.categories).forEach(function(catId) {
                    if (validOptions[catId]) {
                        selectedCategories[catId] = validOptions[catId];
                        restored = true;
                    }
                });
            }
        }

        return restored;
    }

    return {
        /**
         * Initialise the flat filter module.
         *
         * @param {string} clearAllString The translated "clear all filters" label.
         * @param {string} searchCategoryString The translated "Search Category..." placeholder.
         * @param {Object} childrenMap Map of parent category ID to array of child category IDs.
         * @param {number} preSelectedCategoryId Optional pre-selected category ID to add as chip on load.
         */
        init: function(clearAllString, searchCategoryString, childrenMap, preSelectedCategoryId) {
            clearAllLabel = clearAllString || clearAllLabel;
            searchCategoryLabel = searchCategoryString || searchCategoryLabel;
            categoryChildrenMap = childrenMap || {};
            searchInput = document.getElementById('exaport-search');
            categorySelect = document.getElementById('exaport-category-select');
            sortSelect = document.getElementById('exaport-sort-select');
            chipsContainer = document.getElementById('exaport-filter-chips');
            subcategoriesCheckbox = document.getElementById('exaport-subcategories-checkbox');
            entryTypeSelect = document.getElementById('exaport-entrytype-select');

            // Try to restore filter state from sessionStorage (after a reload).
            var restoredFromSession = restoreFilterStateFromSession();

            // Bind text search input event.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterItems();
                });
            }

            // Bind subcategories checkbox.
            if (subcategoriesCheckbox) {
                subcategoriesCheckbox.addEventListener('change', function() {
                    Prefs.savePreference('show_subcategories', subcategoriesCheckbox.checked ? 1 : 0);
                    filterItems();
                });
            }

            // Replace native category select with a custom searchable dropdown.
            buildSearchableDropdown();

            // Bind sort dropdown.
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    sortItems();
                    updateSortHeadings();
                    Prefs.savePreference('sort', sortSelect.value);
                    var url = new URL(window.location.href);
                    url.searchParams.set('sort', sortSelect.value);
                    history.replaceState(null, '', url);
                });
            }

            // Bind entry-type filter dropdown.
            if (entryTypeSelect) {
                entryTypeSelect.addEventListener('change', function() {
                    Prefs.savePreference('entrytype', entryTypeSelect.value);
                    filterItems();
                });
            }
            bindSortHeadings();

            // If state was restored from session, render chips and apply filters.
            if (restoredFromSession) {
                renderChips();
                filterItems();
            } else if (preSelectedCategoryId && preSelectedCategoryId > 0 && categorySelect) {
                // Pre-select category if provided (e.g. when navigating from folder view).
                var catName = '';
                for (var i = 0; i < categorySelect.options.length; i++) {
                    if (Number(categorySelect.options[i].value) === Number(preSelectedCategoryId)) {
                        catName = categorySelect.options[i].text;
                        break;
                    }
                }
                if (catName) {
                    selectedCategories[preSelectedCategoryId] = catName;
                    if (subcategoriesCheckbox) {
                        subcategoriesCheckbox.checked = true;
                    }
                    renderChips();
                    filterItems();
                }
            } else if (entryTypeSelect && entryTypeSelect.value && entryTypeSelect.value !== 'all') {
                // Apply persisted entry-type filter on initial page load (no session restore needed).
                filterItems();
            }
            updateSortHeadings();
        }
    };
});
