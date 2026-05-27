/**
 * State handling for view_items.php (tiles/details toggle and preference persistence).
 *
 * @module     block_exaport/view_items_state
 * @copyright  2026 gtn gmbh
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
     * Save current flat filter state to sessionStorage before a page reload.
     * This allows the flat_filter module to restore the state after the reload.
     */
    function saveFilterStateToSession() {
        var state = {};
        var searchInput = document.getElementById('exaport-flat-search');
        if (searchInput && searchInput.value) {
            state.search = searchInput.value;
        }
        var sortSelect = document.getElementById('exaport-flat-sort-select');
        if (sortSelect) {
            state.sort = sortSelect.value;
        }
        // Get active category chips from the flat_filter module's DOM.
        var chipsContainer = document.getElementById('exaport-flat-filter-chips');
        if (chipsContainer) {
            var chips = chipsContainer.querySelectorAll('.badge.bg-secondary');
            var categories = {};
            chips.forEach(function(chip) {
                // Each chip has a close button then text node with category name.
                // We need to extract the category id — stored in the chip's close handler.
                // Instead, read from the native select to map name -> id.
                var name = chip.textContent.replace('×', '').trim();
                if (name) {
                    // Find matching option in category select.
                    var catSelect = document.getElementById('exaport-flat-category-select');
                    if (catSelect) {
                        for (var i = 0; i < catSelect.options.length; i++) {
                            if (catSelect.options[i].text === name) {
                                categories[catSelect.options[i].value] = name;
                                break;
                            }
                        }
                    }
                }
            });
            if (Object.keys(categories).length > 0) {
                state.categories = categories;
            }
        }
        if (Object.keys(state).length > 0) {
            sessionStorage.setItem('exaport_flat_filters', JSON.stringify(state));
        }
    }

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

    function bindViewToggle() {
        document.querySelectorAll('.exaport-view-toggle-action').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var folderlayout = link.getAttribute('data-folderlayout');
                if (folderlayout !== 'tiles' && folderlayout !== 'details') {
                    return;
                }
                e.preventDefault();
                setActiveView(folderlayout);
                savePreference('folderlayout', folderlayout);

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

    function bindFlatPreferencePersistence(layout) {
        if (layout !== 'flat') {
            return;
        }

        var sortFlatItems = function(sortvalue) {
            var parts = sortvalue.split('-');
            var field = parts[0];
            var dir = parts[1] || 'desc';
            document.querySelectorAll('.exaport-view-section[data-exaport-view]').forEach(function(section) {
                var items = Array.prototype.slice.call(section.querySelectorAll('.exaport-flat-item'));
                if (!items.length) {
                    return;
                }
                var parent = items[0].parentElement;
                items.sort(function(a, b) {
                    var valA;
                    var valB;
                    if (field === 'name') {
                        valA = a.getAttribute('data-item-name') || '';
                        valB = b.getAttribute('data-item-name') || '';
                        return dir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                    valA = parseInt(a.getAttribute('data-item-date') || '0', 10);
                    valB = parseInt(b.getAttribute('data-item-date') || '0', 10);
                    return dir === 'asc' ? valA - valB : valB - valA;
                });
                items.forEach(function(item) {
                    parent.appendChild(item);
                });
            });
        };

        var sortSelect = document.getElementById('exaport-flat-sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                sortFlatItems(sortSelect.value);
                savePreference('sort', sortSelect.value.replace('-', '.'));
            });
            sortFlatItems(sortSelect.value);
        }

        var subcategoriesCheckbox = document.getElementById('exaport-flat-subcategories-checkbox');
        if (subcategoriesCheckbox) {
            subcategoriesCheckbox.addEventListener('change', function() {
                savePreference('show_subcategories', subcategoriesCheckbox.checked ? 1 : 0);
            });
        }
    }

    return {
        init: function(folderlayout, layout) {
            setActiveView(folderlayout === 'details' ? 'details' : 'tiles');
            bindViewToggle();
            bindFlatPreferencePersistence(layout);

            var otherUsersCheckbox = document.getElementById('exaport-show-otherusers-checkbox');
            if (otherUsersCheckbox) {
                otherUsersCheckbox.addEventListener('change', function() {
                    savePreference('show_otherusers', otherUsersCheckbox.checked ? 1 : 0);
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
