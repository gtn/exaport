/**
 * State handling for view_items.php (tiles/details toggle and preference persistence).
 *
 * @module     block_exaport/view_items_state
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['block_exaport/prefs'], function(Prefs) {

    /**
     * Save current filter state to sessionStorage before a page reload.
     * This allows the flat_filter module to restore the state after the reload.
     */
    function saveFilterStateToSession() {
        var state = {};

        var searchInput = document.getElementById('exaport-search');
        if (searchInput && searchInput.value) {
            state.search = searchInput.value;
        }
        var sortSelect = document.getElementById('exaport-sort-select');
        if (sortSelect) {
            state.sort = sortSelect.value;
        }
        var chipsContainer = document.getElementById('exaport-filter-chips');
        if (chipsContainer) {
            var chips = chipsContainer.querySelectorAll('.badge.bg-secondary[data-cat-id]');
            var categories = {};
            chips.forEach(function(chip) {
                var catId = chip.getAttribute('data-cat-id');
                var name = chip.textContent.replace('×', '').trim();
                if (catId && name) {
                    categories[catId] = name;
                }
            });
            if (Object.keys(categories).length > 0) {
                state.categories = categories;
            }
        }
        Prefs.saveFilterStateToSession(state);
    }

    /**
     * Show/hide the details and tiles view sections and update toggle button styles.
     *
     * @param {string} folderlayout 'tiles' or 'details'.
     */
    function setActiveView(folderlayout) {
        var details = document.querySelector('.exaport-view-section[data-exaport-view="details"]');
        var tiles = document.querySelector('.exaport-view-section[data-exaport-view="tiles"]');
        if (!details || !tiles) {
            return;
        }

        var showdetails = folderlayout === 'details';
        details.style.display = showdetails ? '' : 'none';
        tiles.style.display = showdetails ? 'none' : '';
        details.classList.toggle('is-active', showdetails);
        tiles.classList.toggle('is-active', !showdetails);

        document.querySelectorAll('.exaport-view-toggle-action').forEach(function(button) {
            var active = button.getAttribute('data-folderlayout') === folderlayout;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-secondary', !active);
        });
    }

    /**
     * Bind click handlers for the tiles/details view toggle buttons.
     */
    function bindViewToggle() {
        document.querySelectorAll('.exaport-view-toggle-action').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var folderlayout = link.getAttribute('data-folderlayout');
                if (folderlayout !== 'tiles' && folderlayout !== 'details') {
                    return;
                }
                e.preventDefault();
                setActiveView(folderlayout);
                Prefs.savePreference('folderlayout', folderlayout);

                // Keep the URL in sync with the JS-driven toggle so that any subsequent
                // full-page navigation (e.g. switching folder ↔ flat) carries the correct
                // folderlayout value.  We use replaceState (not pushState) because this is
                // a display preference, not a new navigation step – the same pattern Moodle
                // core uses in admin/amd/src/plugins_overview.js (window.history.replaceState).
                var url = new URL(window.location.href);
                url.searchParams.set('folderlayout', folderlayout);
                history.replaceState(null, '', url);

                // Also patch the server-rendered href attributes on the folder/flat toggle
                // links so they carry the now-current folderlayout when the user clicks them.
                document.querySelectorAll('.exaport-layout-toggle a').forEach(function(link) {
                    var linkUrl = new URL(link.href, window.location.href);
                    linkUrl.searchParams.set('folderlayout', folderlayout);
                    link.href = linkUrl.toString();
                });
            });
        });
    }

    return {
        /**
         * Initialise the view-items state module.
         *
         * @param {string} folderlayout Initial layout: 'tiles' or 'details'.
         */
        init: function(folderlayout) {
            setActiveView(folderlayout === 'details' ? 'details' : 'tiles');
            bindViewToggle();

            var otherUsersCheckbox = document.getElementById('exaport-show-otherusers-checkbox');
            if (otherUsersCheckbox) {
                otherUsersCheckbox.addEventListener('change', function() {
                    Prefs.savePreference('show_otherusers', otherUsersCheckbox.checked ? 1 : 0);
                    // Save filter state before reload so it can be restored.
                    saveFilterStateToSession();
                    // Reload because this affects server-side item loading.
                    var url = new URL(window.location.href);
                    url.searchParams.set('show_otherusers', otherUsersCheckbox.checked ? 1 : 0);
                    window.location.href = url.toString();
                });
            }
        }
    };
});
