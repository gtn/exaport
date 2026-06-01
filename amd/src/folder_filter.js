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
define([], function() {

    return {
        /**
         * Initialise the folder filter module.
         */
        init: function() {
            var searchInput = document.getElementById('exaport-folder-search');
            var sortSelect = document.getElementById('exaport-folder-sort-select');

            // Bind text search: filter tiles in real time.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
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
                });
            }

            // Bind sort dropdown: reload page with the new sort URL parameter.
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    // Convert select value (e.g. "date-desc") to URL param (e.g. "date.desc").
                    var sortVal = sortSelect.value.replace('-', '.');
                    var url = new URL(window.location.href);
                    url.searchParams.set('sort', sortVal);
                    window.location.href = url.toString();
                });
            }
        }
    };
});
