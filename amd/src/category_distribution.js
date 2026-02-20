// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

/**
 * Category distribution JavaScript module
 *
 * @module     block_exaport/category_distribution
 * @copyright  2024 GTN - Global Training Network GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str'], function($, ModalFactory, ModalEvents, Str) {

    var config = {};

    /**
     * Submit a form with the given action and fields
     * @param {string} action The action to perform
     * @param {object} fields Additional fields to include in the form
     */
    var submitForm = function(action, fields) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = config.url;

        var allFields = {
            'sesskey': config.sesskey,
            'action': action
        };

        // Merge additional fields
        for (var key in fields) {
            if (fields.hasOwnProperty(key)) {
                allFields[key] = fields[key];
            }
        }

        // Create hidden inputs
        for (var fieldKey in allFields) {
            if (allFields.hasOwnProperty(fieldKey)) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldKey;
                input.value = allFields[fieldKey];
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
    };

    /**
     * Add a subcategory to a parent
     * @param {int} pid Parent category ID
     */
    var addSubcategory = function(pid) {
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: config.strings.addSubcategory || 'Add Subcategory',
            body: '<div class="form-group">' +
                  '<label for="category-name-input">' + config.strings.categoryNameRequired + '</label>' +
                  '<input type="text" class="form-control" id="category-name-input" autofocus>' +
                  '</div>'
        }).then(function(modal) {
            modal.setSaveButtonText(config.strings.save || 'Save');

            modal.getRoot().on(ModalEvents.save, function() {
                var name = modal.getRoot().find('#category-name-input').val();
                if (name) {
                    submitForm('add_category', {
                        'pid': pid,
                        'name': name
                    });
                }
            });

            modal.show();

            // Focus input after modal is shown
            modal.getRoot().on(ModalEvents.shown, function() {
                modal.getRoot().find('#category-name-input').focus();
            });

            return modal;
        });
    };

    /**
     * Rename a category
     * @param {int} id Category ID
     * @param {string} oldname Current category name
     */
    var renameCategory = function(id, oldname) {
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: config.strings.renameCategory || 'Rename Category',
            body: '<div class="form-group">' +
                  '<label for="category-name-input">' + config.strings.categoryNameRequired + '</label>' +
                  '<input type="text" class="form-control" id="category-name-input" value="' +
                  $('<div>').text(oldname).html() + '" autofocus>' +
                  '</div>'
        }).then(function(modal) {
            modal.setSaveButtonText(config.strings.save || 'Save');

            modal.getRoot().on(ModalEvents.save, function() {
                var name = modal.getRoot().find('#category-name-input').val();
                if (name && name !== oldname) {
                    submitForm('rename_category', {
                        'id': id,
                        'name': name
                    });
                }
            });

            modal.show();

            // Focus and select input after modal is shown
            modal.getRoot().on(ModalEvents.shown, function() {
                var input = modal.getRoot().find('#category-name-input')[0];
                input.focus();
                input.select();
            });

            return modal;
        });
    };

    /**
     * Move a category to a new parent
     * @param {int} id Category ID
     */
    var moveCategory = function(id) {
        // Build options for select
        var options = '<option value="0">' + config.strings.moveToRoot + '</option>';
        if (config.nodes) {
            config.nodes.forEach(function(node) {
                if (node.id !== id) { // Can't move to itself
                    options += '<option value="' + node.id + '">' + $('<div>').text(node.name).html() + '</option>';
                }
            });
        }

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: config.strings.moveCategory || 'Move Category',
            body: '<div class="form-group">' +
                  '<label for="parent-select">' + config.strings.selectParent + '</label>' +
                  '<select class="form-control" id="parent-select">' + options + '</select>' +
                  '</div>'
        }).then(function(modal) {
            modal.setSaveButtonText(config.strings.save || 'Save');

            modal.getRoot().on(ModalEvents.save, function() {
                var newpid = modal.getRoot().find('#parent-select').val();
                submitForm('move_category', {
                    'id': id,
                    'newpid': newpid || '0'
                });
            });

            modal.show();

            return modal;
        });
    };

    /**
     * Toggle share to teachers setting for a category
     * @param {int} id Category ID
     * @param {boolean} currentState Current share state
     */
    var toggleShareToTeachers = function(id, currentState) {
        var newState = !currentState;
        submitForm('toggle_share_to_teachers', {
            'id': id,
            'share_to_teachers': newState ? '1' : '0'
        });
    };

    /**
     * Initialize the category distribution module
     * @param {object} cfg Configuration object
     */
    var init = function(cfg) {
        config = cfg;

        // Attach event handlers using data attributes
        $(document).on('click', '[data-action="add-subcategory"]', function(e) {
            e.preventDefault();
            var pid = $(this).data('pid');
            addSubcategory(pid);
        });

        $(document).on('click', '[data-action="rename-category"]', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var name = $(this).data('name');
            renameCategory(id, name);
        });

        $(document).on('click', '[data-action="move-category"]', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            moveCategory(id);
        });

        $(document).on('click', '[data-action="toggle-share"]', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var currentState = $(this).data('shared') == '1'; // === would not work, as it is int
            toggleShareToTeachers(id, currentState);
        });
    };

    return {
        init: init
    };
});
