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

define(['jquery'], function($) {
    
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
        var name = prompt(config.strings.categoryNameRequired);
        if (name) {
            submitForm('add_category', {
                'pid': pid,
                'name': name
            });
        }
    };
    
    /**
     * Rename a category
     * @param {int} id Category ID
     * @param {string} oldname Current category name
     */
    var renameCategory = function(id, oldname) {
        var name = prompt(config.strings.categoryNameRequired, oldname);
        if (name && name !== oldname) {
            submitForm('rename_category', {
                'id': id,
                'name': name
            });
        }
    };
    
    /**
     * Move a category to a new parent
     * @param {int} id Category ID
     */
    var moveCategory = function(id) {
        var message = config.strings.selectParent + "\n\n";
        message += config.strings.moveToRoot + ': 0' + "\n";
        
        // Add all nodes as options (passed in config)
        if (config.nodes) {
            config.nodes.forEach(function(node) {
                if (node.id !== id) { // Can't move to itself
                    message += node.name + ': ' + node.id + "\n";
                }
            });
        }
        
        message += "\n" + config.strings.enterParentId + ':';
        
        var newpid = prompt(message, '0');
        if (newpid !== null) {
            submitForm('move_category', {
                'id': id,
                'newpid': newpid || '0'
            });
        }
    };
    
    /**
     * Toggle share to teachers setting for a category
     * @param {int} id Category ID
     * @param {boolean} checked Whether the checkbox is checked
     */
    var toggleShareToTeachers = function(id, checked) {
        submitForm('toggle_share_to_teachers', {
            'id': id,
            'share_to_teachers': checked ? '1' : '0'
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
        
        $(document).on('change', '[data-action="toggle-share"]', function() {
            var id = $(this).data('id');
            var checked = $(this).is(':checked');
            toggleShareToTeachers(id, checked);
        });
    };
    
    return {
        init: init
    };
});
