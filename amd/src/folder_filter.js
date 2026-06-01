/**
 * AMD module for search and sort controls in the folder layout view.
 *
 * - Text search input (#exaport-folder-search): hides/shows category and item tiles
 *   in real time. The pinned "back to parent" tile (data-pinned="true") is always kept
 *   visible regardless of the search term.
 * - Sort dropdown (#exaport-folder-sort-select): reloads the page with the chosen sort
 *   URL parameter so items are re-sorted server-side (same as the column-heading links).
 *
 * @module     block_exaport/folder_filter
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /**
     * Save a user preference in the background.
     *
     * @param {string} name
     * @param {string|number} value
     */
    function savePreference(name, value) {
        Ajax.call([{
            methodname: 'core_user_set_user_preferences',
            args: {
                preferences: [{
                    name: 'block_exaport_' + name,
                    value: String(value)
                }]
            }
        }])[0];
    }

    /**
     * Try to restore folder filter state from sessionStorage (saved before a page reload).
     * Clears the stored state after restoration.
     *
     * @param {HTMLElement} searchInput
     * @param {HTMLElement} sortSelect
     * @return {boolean} Whether state was restored.
     */
    function restoreFilterStateFromSession(searchInput, sortSelect) {
        var saved = sessionStorage.getItem('exaport_folder_filters');
        if (!saved) {
            return false;
        }
        sessionStorage.removeItem('exaport_folder_filters');

        var state;
        try {
            state = JSON.parse(saved);
        } catch (e) {
            return false;
        }

        var restored = false;

        // Restore search text.
        if (state.search && searchInput) {
            searchInput.value = state.search;
            restored = true;
        }

        // Restore sort selection.
        if (state.sort && sortSelect) {
            sortSelect.value = state.sort;
            restored = true;
        }

        return restored;
    }

    /**
     * Apply the search filter to visible items and categories.
     *
     * @param {HTMLElement} searchInput
     */
    function applySearchFilter(searchInput) {
        if (!searchInput) {
            return;
        }
        var searchText = searchInput.value.toLowerCase();

        // Filter item tiles (.exaport-flat-item have data-item-name).
        document.querySelectorAll('.exaport-flat-item[data-item-name]').forEach(function(tile) {
            var name = tile.getAttribute('data-item-name') || '';
            tile.style.display = (!searchText || name.indexOf(searchText) !== -1) ? '' : 'none';
        });

        // Filter category tiles — but never hide the pinned "back" tile.
        document.querySelectorAll('.exaport-folder-category[data-item-name]').forEach(function(tile) {
            if (tile.getAttribute('data-pinned') === 'true') {
                // Pinned tile is always visible.
                tile.style.display = '';
                return;
            }
            var name = tile.getAttribute('data-item-name') || '';
            tile.style.display = (!searchText || name.indexOf(searchText) !== -1) ? '' : 'none';
        });
    }

    return {
        /**
         * Initialise the folder filter module.
         */
        init: function() {
            var searchInput = document.getElementById('exaport-folder-search');
            var sortSelect = document.getElementById('exaport-folder-sort-select');

            // Try to restore filter state from sessionStorage (after a reload).
            var restoredFromSession = restoreFilterStateFromSession(searchInput, sortSelect);

            // Bind text search: filter tiles in real time.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applySearchFilter(searchInput);
                });
            }

            // If state was restored, apply the search filter immediately.
            if (restoredFromSession) {
                applySearchFilter(searchInput);
            }

            // Bind sort dropdown: reload page with the new sort URL parameter and save preference.
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    // Save with hyphen format as-is (e.g. "date-asc") — matches PARAM_ALPHANUMEXT.
                    savePreference('sort', sortSelect.value);
                    // URL param also uses hyphen format; PHP parse_sort handles both.
                    var url = new URL(window.location.href);
                    url.searchParams.set('sort', sortSelect.value);
                    window.location.href = url.toString();
                });
            }
        }
    };
});
