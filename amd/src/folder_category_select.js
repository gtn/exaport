/**
 * AMD module enhancing the folder-mode category <select> into the same searchable
 * dropdown widget used by the flat-mode category filter, but as a single-select
 * navigator: picking a category immediately navigates to it (full page reload),
 * instead of adding a chip. The picked category then shows up via the normal
 * server-rendered breadcrumb (.excomdos_cat).
 *
 * @module     block_exaport/folder_category_select
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['block_exaport/category_select'], function(CategorySelect) {
    return {
        /**
         * @param {string} searchCategoryString Translated "Search Category..." placeholder.
         * @param {string} baseUrl Current page URL (without a categoryid param) to navigate to,
         *                          with "&categoryid=<id>" appended on pick.
         */
        init: function(searchCategoryString, baseUrl) {
            var select = document.getElementById('exaport-category-select-folder');
            if (!select) {
                return;
            }
            CategorySelect.build({
                select: select,
                        inputId: 'exaport-category-search-folder',
                        placeholder: searchCategoryString,
                        onPick: function(id) {
                    window.location.href = baseUrl + '&categoryid=' + encodeURIComponent(id);
                }
            });
        }
    };
});

