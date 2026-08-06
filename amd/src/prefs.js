/**
 * Shared preference and filter-state helpers for view_items AMD modules.
 *
 * @module     block_exaport/prefs
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    var STORAGE_KEY = 'exaport_filters';

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
     * Store filter state in sessionStorage.
     *
     * @param {Object} state
     */
    function saveFilterStateToSession(state) {
        if (state && Object.keys(state).length > 0) {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        }
    }

    /**
     * Restore filter state from sessionStorage and remove it.
     *
     * @return {?Object}
     */
    function restoreFilterStateFromSession() {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (!saved) {
            return null;
        }
        sessionStorage.removeItem(STORAGE_KEY);

        try {
            return JSON.parse(saved);
        } catch (e) {
            return null;
        }
    }

    return {
        savePreference: savePreference,
        saveFilterStateToSession: saveFilterStateToSession,
        restoreFilterStateFromSession: restoreFilterStateFromSession
    };
});
