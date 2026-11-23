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
    var clearCategoryLabel = 'Clear category filter'; // Translatable via init() parameter.
    var searchCategoryLabel = 'Search Category...'; // Translatable via init() parameter.
    var entryTypeSelect;    // Entry-type filter dropdown (All / Only items / Only views).
    var shareFilters;       // Filter bar on My shares or Shared with me.
    var shareClearFilters; // Clear search and type on the outgoing overview.
    var shareSenderGrouping; // Incoming shares only: group matching rows by sender.
    var selectedShareOwners = {}; // Map of selected incoming sender IDs to their display names.
    var selectedShareCourses = {}; // Map of selected incoming course IDs to their display names.
    var incomingSharesTable; // Incoming headers own their sort state without a sort dropdown.
    var incomingSort = 'name-asc';
    var sortAttributes = {
        name: 'data-item-name',
        type: 'data-item-type',
        sender: 'data-owner-name',
        course: 'data-course-name',
        date: 'data-item-date',
        sharedwith: 'data-shared-with',
        comments: 'data-comment-count'
    };

    /**
     * Return the active header sort for incoming shares, or the legacy dropdown sort.
     *
     * @return {string}
     */
    function currentSort() {
        if (incomingSharesTable) {
            return incomingSort;
        }
        return sortSelect ? sortSelect.value : '';
    }

    /**
     * Keep the active sort in the URL without changing any filter or display preferences.
     */
    function saveSort() {
        var url = new URL(window.location.href);
        url.searchParams.set('sort', currentSort());
        history.replaceState(null, '', url);
    }

    /**
     * Restore incoming sorting only for fields actually offered by the table headers.
     */
    function initIncomingSort() {
        incomingSharesTable = document.querySelector('[data-region="incoming-shares-table"]');
        incomingSort = 'name-asc';
        if (!incomingSharesTable) {
            return;
        }
        var requested = new URL(window.location.href).searchParams.get('sort');
        if (requested === null) {
            return;
        }
        var parts = requested.split('-');
        var headings = incomingSharesTable.querySelectorAll('.exaport-sort-heading[data-sort-field]');
        var validField = Array.from(headings).some(function(heading) {
            return heading.getAttribute('data-sort-field') === parts[0];
        });
        if (validField && parts.length === 2 && (parts[1] === 'asc' || parts[1] === 'desc')) {
            incomingSort = requested;
        } else {
            saveSort();
        }
    }

    /**
     * Keep share filters in the current URL, separate from Portfolio preferences.
     */
    function saveShareFilters() {
        var url = new URL(window.location.href);
        if (searchInput.value) {
            url.searchParams.set('search', searchInput.value);
        } else {
            url.searchParams.delete('search');
        }
        if (entryTypeSelect.value !== 'all') {
            url.searchParams.set('entrytype', entryTypeSelect.value);
        } else {
            url.searchParams.delete('entrytype');
        }
        if (incomingSharesTable) {
            url.searchParams.delete('userid');
            Object.keys(selectedShareOwners).forEach(function(id) {
                url.searchParams.append('userid', id);
            });
        }
        history.replaceState(null, '', url);
    }

    /**
     * Restore the overview's own filters and wire up its clear button.
     */
    function initShareFilters() {
        var params = new URL(window.location.href).searchParams;
        searchInput.value = params.get('search') || '';
        entryTypeSelect.value = params.get('entrytype') || 'all';
        if (!entryTypeSelect.value) {
            entryTypeSelect.value = 'all';
        }
        shareClearFilters = shareFilters.querySelector('[data-action="clear-filters"]');
        if (incomingSharesTable) {
            selectedShareOwners = initSharePicker({
                select: shareFilters.querySelector('[data-share-filter="userid"]'),
                ids: params.getAll('userid'),
                chips: shareFilters.querySelector('[data-region="sender-filter-chips"]'),
                clear: shareFilters.querySelector('[data-action="clear-senders"]'),
                inputId: 'exaport-share-sender-search',
                attribute: 'data-sender-id'
            });
            selectedShareCourses = initSharePicker({
                select: shareFilters.querySelector('[data-share-filter="course"]'),
                ids: [],
                chips: shareFilters.querySelector('[data-region="course-filter-chips"]'),
                clear: shareFilters.querySelector('[data-action="clear-courses"]'),
                inputId: 'exaport-share-course-search',
                attribute: 'data-course-id'
            });
            saveShareFilters();
        }
        if (shareClearFilters) {
            shareClearFilters.addEventListener('click', function() {
                searchInput.value = '';
                entryTypeSelect.value = 'all';
                searchInput.focus();
                saveShareFilters();
                filterItems();
            });
        }
    }

    /**
     * Render removable chips for an existing selection, preserving its identifying attributes.
     *
     * @param {HTMLElement} container Chip container, if present.
     * @param {Object} selection Map of selected IDs to display names.
     * @param {string} attribute Attribute identifying each chip's selection ID.
     * @param {Function} onRemove Remove the selection by ID and refresh the relevant filter.
     * @param {string} removeLabel Accessible removal label.
     */
    function renderSelectionChips(container, selection, attribute, onRemove, removeLabel) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        Object.keys(selection).forEach(function(id) {
            var chip = document.createElement('span');
            chip.className = 'badge bg-secondary text-dark m-1';
            chip.style.cssText = 'font-size: 100%';
            chip.setAttribute(attribute, id);
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'border-0 bg-transparent text-reset p-0';
            closeBtn.textContent = '\u00d7';
            closeBtn.style.cssText = 'margin-right: 0.3em; font-size: 1.1em; line-height: 1; cursor: pointer;';
            closeBtn.setAttribute('aria-label', removeLabel + ': ' + selection[id]);
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                onRemove(id);
            });
            chip.appendChild(closeBtn);
            chip.appendChild(document.createTextNode(selection[id]));
            container.appendChild(chip);
        });
    }

    /**
     * Render category chips and their existing "remove all" action.
     */
    function renderChips() {
        renderSelectionChips(chipsContainer, selectedCategories, 'data-cat-id', function(id) {
            delete selectedCategories[id];
            renderChips();
            filterItems();
        }, 'Remove');
        if (!chipsContainer || Object.keys(selectedCategories).length === 0) {
            return;
        }

        var removeAll = document.createElement('button');
        removeAll.type = 'button';
        removeAll.className = 'badge bg-primary text-light border-0 m-1 exaport-clear-filters-chip';
        removeAll.textContent = clearCategoryLabel;
        removeAll.addEventListener('click', function() {
            selectedCategories = {};
            renderChips();
            filterItems();
        });
        chipsContainer.appendChild(removeAll);
    }

    /**
     * Enhance an incoming sender or course select with searchable options and independent chips.
     *
     * @param {Object} config Select, restored IDs, chips, clear control, inputId and attribute.
     * @return {Object} Selection map shared with filtering and URL state; changed in place.
     */
    function initSharePicker(config) {
        var selection = {};
        if (!config.select) {
            return selection;
        }
        var knownOptions = Object.create(null);
        Array.from(config.select.options).forEach(function(option) {
            if (option.value && !option.disabled) {
                knownOptions[option.value] = option.text;
            }
        });
        config.ids.forEach(function(id) {
            if (Object.prototype.hasOwnProperty.call(knownOptions, id)) {
                selection[id] = knownOptions[id];
            }
        });
        var widget;

        /** Render this picker's selected values and its clear action. */
        function renderPickerChips() {
            if (config.clear) {
                config.clear.hidden = Object.keys(selection).length === 0;
            }
            var removeLabel = config.chips ? config.chips.getAttribute('data-remove-label') : '';
            renderSelectionChips(config.chips, selection, config.attribute, function(id) {
                var restoreFocus = config.chips.contains(document.activeElement);
                delete selection[id];
                applySelection();
                widget.refresh();
                if (restoreFocus) {
                    document.getElementById(config.inputId).focus();
                }
            }, removeLabel || 'Remove');
        }

        /** Apply a pick, removal or clear without changing the other picker's selection. */
        function applySelection() {
            renderPickerChips();
            saveShareFilters();
            filterItems();
        }

        widget = CategorySelect.build({
            select: config.select,
            inputId: config.inputId,
            placeholder: config.select.options[0].text,
            keepOpenOnPick: true,
            skipFirstOption: true,
            isSelected: function(id) {
                return !Object.prototype.hasOwnProperty.call(knownOptions, id) ||
                    Object.prototype.hasOwnProperty.call(selection, id);
            },
            onPick: function(id, name) {
                selection[id] = name;
                applySelection();
            }
        });
        Array.from(config.select.labels).forEach(function(label) {
            label.htmlFor = config.inputId;
        });
        if (config.clear) {
            config.clear.addEventListener('click', function() {
                Object.keys(selection).forEach(function(id) {
                    delete selection[id];
                });
                applySelection();
                widget.refresh();
                document.getElementById(config.inputId).focus();
            });
        }
        renderPickerChips();
        return selection;
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
        var selectedOwnerIds = Object.keys(selectedShareOwners);
        var selectedCourseIds = Object.keys(selectedShareCourses);
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
        var hasMatches = false;
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
            var matchesOwner = selectedOwnerIds.length === 0 ||
                selectedOwnerIds.indexOf(item.getAttribute('data-owner-id')) !== -1;
            var matchesCourse = selectedCourseIds.length === 0 ||
                (item.getAttribute('data-filter-course-ids') || '').split(',').some(function(id) {
                    return selectedCourseIds.indexOf(id) !== -1;
                });

            var matches = matchesSearch && matchesCategory && matchesEntryType && matchesOwner && matchesCourse;
            item.style.display = matches ? '' : 'none';
            hasMatches = hasMatches || matches;
        });

        document.querySelectorAll('.exaport-folder-category[data-item-name]').forEach(function(tile) {
            var name = tile.getAttribute('data-item-name') || '';
            var pinned = tile.getAttribute('data-pinned') === 'true';
            tile.style.display = (pinned || !searchText || name.indexOf(searchText) !== -1) ? '' : 'none';
        });

        if (shareFilters) {
            if (shareClearFilters) {
                shareClearFilters.hidden = !searchText && selectedEntryType === 'all';
            }
            shareFilters.querySelector('[data-region="no-matching-shares"]').hidden = hasMatches;
            var shareTable = document.querySelector('.exaport-share-overview-table');
            if (shareTable) {
                shareTable.hidden = !hasMatches;
            }
        }

        sortItems();
    }

    /**
     * Compare rows by numeric values or the displayed text associated with a column.
     *
     * @param {HTMLElement} a First row or card.
     * @param {HTMLElement} b Second row or card.
     * @param {string} field Sort field.
     * @param {string} dir Sort direction.
     * @return {number}
     */
    function compareItems(a, b, field, dir) {
        var attribute = sortAttributes[field] || sortAttributes.name;
        var valA = a.getAttribute(attribute) || '';
        var valB = b.getAttribute(attribute) || '';
        if (field === 'date' || field === 'comments') {
            valA = parseInt(valA, 10) || 0;
            valB = parseInt(valB, 10) || 0;
        }
        var cmp = typeof valA === 'number' ? valA - valB : valA.localeCompare(valB);
        return dir === 'asc' ? cmp : -cmp;
    }

    /**
     * Keep incoming ties independent of earlier sorts and the database's initial row order.
     *
     * @param {HTMLElement} a First incoming row.
     * @param {HTMLElement} b Second incoming row.
     * @param {string} field Primary sort field.
     * @param {string} dir Primary sort direction.
     * @return {number}
     */
    function compareIncomingItems(a, b, field, dir) {
        var cmp = compareItems(a, b, field, dir) || compareItems(a, b, 'name', 'asc');
        if (cmp) {
            return cmp;
        }
        var typeA = a.getAttribute('data-entry-type') || '';
        var typeB = b.getAttribute('data-entry-type') || '';
        if (typeA !== typeB) {
            return typeA < typeB ? -1 : 1;
        }
        return parseInt(a.getAttribute('data-entry-id') || '0', 10) -
            parseInt(b.getAttribute('data-entry-id') || '0', 10);
    }

    /**
     * Rebuild incoming sections using the real rows, so filtering and sorting never duplicate entries.
     *
     * @param {string} field Sort field.
     * @param {string} dir Sort direction.
     */
    function sortIncomingShares(field, dir) {
        var table = incomingSharesTable;
        var body = table.querySelector('tbody');
        if (!body) {
            return;
        }
        body.querySelectorAll('[data-share-group-header]').forEach(function(heading) {
            heading.remove();
        });
        var rows = Array.from(body.querySelectorAll('.exaport-flat-item'));
        rows.sort(function(a, b) {
            return compareIncomingItems(a, b, field, dir);
        });
        var visible = rows.filter(function(row) {
            return row.style.display !== 'none';
        });
        var columns = rows.length ? rows[0].cells.length : 1;
        var fragment = document.createDocumentFragment();

        var appendHeading = function(name, count) {
            var heading = document.createElement('tr');
            heading.setAttribute('data-share-group-header', 'sender');
            var cell = document.createElement('th');
            cell.colSpan = columns;
            cell.textContent = name + ' (' + count + ')';
            heading.appendChild(cell);
            fragment.appendChild(heading);
        };
        var appendRows = function(sectionRows) {
            if (!shareSenderGrouping || !shareSenderGrouping.checked) {
                sectionRows.forEach(function(row) {
                    fragment.appendChild(row);
                });
                return;
            }
            var owners = Object.create(null);
            sectionRows.forEach(function(row) {
                var id = row.getAttribute('data-owner-id') || '';
                if (!owners[id]) {
                    owners[id] = {id: id, name: row.getAttribute('data-owner-name') || '', rows: []};
                }
                owners[id].rows.push(row);
            });
            Object.keys(owners).map(function(id) {
                return owners[id];
            }).sort(function(a, b) {
                var cmp = a.name.localeCompare(b.name) || a.id.localeCompare(b.id);
                return field === 'sender' && dir === 'desc' ? -cmp : cmp;
            }).forEach(function(owner) {
                appendHeading(owner.name, owner.rows.length);
                owner.rows.forEach(function(row) {
                    fragment.appendChild(row);
                });
            });
        };

        appendRows(visible);
        rows.forEach(function(row) {
            if (row.style.display === 'none') {
                fragment.appendChild(row);
            }
        });
        body.appendChild(fragment);
    }

    /**
     * Sort visible items by reordering DOM elements within their parent container.
     * Reads the current incoming header state or the legacy sort dropdown.
     */
    function sortItems() {
        var sortVal = currentSort(); // e.g. "date-desc", "name-asc"
        if (!sortVal) {
            return;
        }
        var parts = sortVal.split('-');
        var field = parts[0];
        var dir = parts[1] || 'desc';

        if (incomingSharesTable) {
            sortIncomingShares(field, dir);
            return;
        }

        // Sort within each view section independently to avoid moving items across sections.
        document.querySelectorAll('.exaport-view-section[data-exaport-view]').forEach(function(section) {
            var items = Array.from(section.querySelectorAll('.exaport-flat-item'));
            if (items.length === 0) {
                return;
            }
            var parent = items[0].parentElement;

            items.sort(function(a, b) {
                return compareItems(a, b, field, dir);
            });

            items.forEach(function(item) {
                parent.appendChild(item);
            });
        });
    }

    /**
     * Update table sort heading arrows and aria-sort attributes from the current sort state.
     */
    function updateSortHeadings() {
        var field = '';
        var dir = '';
        var sort = currentSort();
        if (sort) {
            var parts = sort.split('-');
            field = parts[0];
            dir = parts[1] || 'desc';
        }

        document.querySelectorAll('.exaport-sort-heading').forEach(function(heading) {
            var headingField = heading.getAttribute('data-sort-field');
            var arrow = heading.querySelector('.exaport-sort-arrow');
            var active = headingField && headingField === field;
            var column = heading.closest('th');
            if (column) {
                if (active) {
                    column.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
                } else {
                    column.removeAttribute('aria-sort');
                }
            }
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
                if (!incomingSharesTable && !sortSelect) {
                    return;
                }
                var field = heading.getAttribute('data-sort-field');
                if (!field) {
                    return;
                }
                if (incomingSharesTable && !incomingSharesTable.contains(heading)) {
                    return;
                }

                var current = currentSort().split('-');
                var currentField = current[0];
                var currentDir = current[1] || 'desc';
                var nextDir;
                if (field === currentField) {
                    nextDir = currentDir === 'asc' ? 'desc' : 'asc';
                } else {
                    nextDir = field === 'date' || field === 'comments' ? 'desc' : 'asc';
                }

                if (incomingSharesTable) {
                    incomingSort = field + '-' + nextDir;
                    sortItems();
                    updateSortHeadings();
                    saveSort();
                    return;
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
        CategorySelect.build({
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
         * @param {string} clearCategoryString The translated category clear label.
         * @param {string} searchCategoryString The translated "Search Category..." placeholder.
         * @param {Object} childrenMap Map of parent category ID to array of child category IDs.
         * @param {number} preSelectedCategoryId Optional pre-selected category ID to add as chip on load.
         */
        init: function(clearCategoryString, searchCategoryString, childrenMap, preSelectedCategoryId) {
            clearCategoryLabel = clearCategoryString || clearCategoryLabel;
            searchCategoryLabel = searchCategoryString || searchCategoryLabel;
            categoryChildrenMap = childrenMap || {};
            searchInput = document.getElementById('exaport-search');
            categorySelect = document.getElementById('exaport-category-select');
            sortSelect = document.getElementById('exaport-sort-select');
            chipsContainer = document.getElementById('exaport-filter-chips');
            subcategoriesCheckbox = document.getElementById('exaport-subcategories-checkbox');
            entryTypeSelect = document.getElementById('exaport-entrytype-select');
            shareFilters = document.querySelector('[data-region="exaport-share-filters"]');
            initIncomingSort();
            shareSenderGrouping = document.getElementById('exaport-group-by-sender');
            if (shareSenderGrouping) {
                shareSenderGrouping.addEventListener('change', function() {
                    Prefs.savePreference('group_by_sender', shareSenderGrouping.checked ? 1 : 0);
                    sortItems();
                });
            }

            var restoredFromSession = false;
            if (shareFilters) {
                initShareFilters();
            } else {
                restoredFromSession = restoreFilterStateFromSession();
            }

            // Bind text search input event.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    if (shareFilters) {
                        saveShareFilters();
                    }
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
                    if (!shareFilters) {
                        Prefs.savePreference('sort', sortSelect.value);
                    }
                    saveSort();
                });
            }

            // Bind entry-type filter dropdown.
            if (entryTypeSelect) {
                entryTypeSelect.addEventListener('change', function() {
                    if (shareFilters) {
                        saveShareFilters();
                    } else {
                        Prefs.savePreference('entrytype', entryTypeSelect.value);
                    }
                    filterItems();
                });
            }
            bindSortHeadings();

            // If state was restored from session, render chips and apply filters.
            if (shareFilters) {
                // The overview queries return each type separately, so sort the combined list on load.
                filterItems();
            } else if (restoredFromSession) {
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
