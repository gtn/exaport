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

            // Bind category dropdown: selecting an option adds it as a chip.
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    var val = categorySelect.value;
                    if (val && !selectedCategories[val]) {
                        selectedCategories[val] = categorySelect.options[categorySelect.selectedIndex].text;
                        renderChips();
                        filterItems();
                    }
                    // Reset dropdown to placeholder.
                    categorySelect.value = '';
                });
            }

            // Bind sort dropdown.
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    sortItems();
                });
            }
        }
    };
});
