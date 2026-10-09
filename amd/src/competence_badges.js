// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Initialize shared competence badges after FontAwesome icon conversion.
 *
 * @module block_exaport/competence_badges
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core_filters/events'], function($, FilterEvents) {
    const initialized = new WeakSet();

    return {
        /**
         * Convert icons and notify Moodle once for each final tooltip trigger.
         *
         * @param {HTMLElement|jQuery} element Container holding the badges.
         * @returns {Promise} Resolves after conversion and tooltip notification.
         */
        initialise: function(element) {
            const root = $(element);
            if (!root.length) {
                return Promise.resolve();
            }
            return Promise.resolve(window.block_exaport_update_fontawesome_icons(root)).then(function() {
                const badges = root.find('[data-region="item-competence-badge"]').toArray().filter(function(badge) {
                    const trigger = badge.querySelector('[data-bs-toggle="tooltip"]');
                    if (!trigger || initialized.has(trigger)) {
                        return false;
                    }
                    // Overlapping page and item conversions can replace the same icon twice.
                    initialized.add(trigger);
                    return true;
                });
                if (badges.length) {
                    FilterEvents.notifyFilterContentUpdated(badges);
                }
            });
        }
    };
});
