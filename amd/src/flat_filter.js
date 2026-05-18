/**
 * AMD module for dynamic filtering of items in the flat layout view.
 *
 * Provides real-time text search and multi-select category filtering.
 * The category selector is rendered by Moodle's autocomplete form element
 * (same as item.php) so no manual enhancement is needed here.
 *
 * @module     block_exaport/flat_filter
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /**
     * Filter items based on current search text and selected categories.
     *
     * @param {HTMLElement} searchInput The text search input element.
     * @param {HTMLElement} categorySelect The hidden multi-select element for categories.
     */
    function filterItems(searchInput, categorySelect) {
        var searchText = (searchInput ? searchInput.value : '').toLowerCase();
        var selectedCats = [];
        if (categorySelect) {
            var options = categorySelect.selectedOptions || categorySelect.querySelectorAll('option:checked');
            for (var i = 0; i < options.length; i++) {
                var val = parseInt(options[i].value, 10);
                if (val > 0) {
                    selectedCats.push(val);
                }
            }
        }

        var items = document.querySelectorAll('.exaport-flat-item');
        items.forEach(function(item) {
            var name = item.getAttribute('data-item-name') || '';
            var catIdsStr = item.getAttribute('data-category-ids') || '';
            var catIds = catIdsStr ? catIdsStr.split(',').map(Number) : [];

            var matchesSearch = !searchText || name.indexOf(searchText) !== -1;
            var matchesCategory = selectedCats.length === 0 || selectedCats.some(function(catId) {
                return catIds.indexOf(catId) !== -1;
            });

            item.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
        });
    }

    return {
        /**
         * Initialise the flat filter module.
         *
         * @param {string} selectId The ID of the category multi-select element.
         */
        init: function(selectId) {
            var searchInput = document.getElementById('exaport-flat-search');
            var categorySelect = document.getElementById(selectId);

            // Bind text search input event.
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterItems(searchInput, categorySelect);
                });
            }

            // Bind category select change event.
            // Moodle's autocomplete form element updates the hidden select on change.
            if (categorySelect) {
                $(categorySelect).on('change', function() {
                    filterItems(searchInput, categorySelect);
                });
            }
        }
    };
});
