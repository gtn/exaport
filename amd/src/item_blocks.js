// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define([
    'jquery',
    'jqueryui',
    'core/fragment',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/notification'
], function($, JQueryUI, Fragment, ModalSaveCancel, ModalEvents, Notification) {
    var config = {};
    var chooserModal = null;

    var getBlockList = function() {
        return $('.exaport-item-block-list');
    };

    var getCurrentOrder = function() {
        var ids = [];
        getBlockList().find('[data-blockid]').each(function() {
            ids.push(parseInt($(this).attr('data-blockid'), 10));
        });
        return ids;
    };

    var updateOrderField = function() {
        $('#id_blockorder').val(JSON.stringify(getCurrentOrder()));
    };

    var applyOrder = function(order) {
        var list = getBlockList();
        $.each(order, function(index, blockid) {
            var row = list.find('[data-blockid="' + blockid + '"]');
            if (row.length) {
                list.append(row);
            }
        });
        updateOrderField();
    };

    var showMessage = function(message) {
        window.alert(message);
    };

    var getRequestErrorMessage = function(exception, fallbackMessage) {
        if (exception && exception.responseJSON && exception.responseJSON.message) {
            return exception.responseJSON.message;
        }
        if (exception && exception.message) {
            return exception.message;
        }
        if (exception && exception.responseText && exception.responseText.charAt(0) === '{') {
            try {
                return JSON.parse(exception.responseText).message || fallbackMessage;
            } catch (error) {
                return fallbackMessage;
            }
        }
        return fallbackMessage;
    };

    var clearFieldErrors = function(form) {
        form.find('.exaport-field-error').remove();
        form.find('[aria-invalid="true"]').removeAttr('aria-invalid');
    };

    var getFieldContainer = function(form, fieldname) {
        var selector = '[name="' + fieldname + '"], [name="' + fieldname + '[text]"], #id_' + fieldname;
        var field = form.find(selector).first();
        if (!field.length && fieldname === 'content_editor') {
            field = form.find('[name="content_editor[text]"], #id_content_editor').first();
        }
        if (!field.length) {
            return $();
        }
        return field.closest('.fitem, .form-group, [data-fieldtype]');
    };

    var showFieldErrors = function(form, fieldErrors) {
        $.each(fieldErrors || {}, function(fieldname, message) {
            var container = getFieldContainer(form, fieldname);
            if (!container.length) {
                return;
            }
            container.find('.exaport-field-error').remove();
            $('<div class="text-danger exaport-field-error" role="alert"></div>').text(message).appendTo(container);
            container.find(':input').first().attr('aria-invalid', 'true');
        });
    };

    var updateListEmptyState = function() {
        var list = getBlockList();
        var hasBlocks = list.find('[data-blockid]').length > 0;
        list.toggleClass('d-none', !hasBlocks);
        $('.exaport-item-block-empty').toggleClass('d-none', hasBlocks);
        updateOrderField();
    };

    var upsertRow = function(blockid, rowhtml) {
        var list = getBlockList();
        var existing = list.find('[data-blockid="' + blockid + '"]');
        if (existing.length) {
            existing.replaceWith(rowhtml);
        } else {
            list.append(rowhtml);
        }
        updateListEmptyState();
    };

    var request = function(data) {
        return $.ajax({
            url: config.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: data
        });
    };

    var saveOrder = function(previousOrder) {
        var currentOrder = getCurrentOrder();
        if (!currentOrder.length) {
            updateOrderField();
            return;
        }

        request({
            sesskey: M.cfg.sesskey,
            ajax: 1,
            ajaxaction: 'saveblockorder',
            blockorder: JSON.stringify(currentOrder)
        }).fail(function(exception) {
            if (previousOrder && previousOrder.length) {
                applyOrder(previousOrder);
            }
            showMessage(getRequestErrorMessage(exception, config.strings.cancel));
        });
    };

    var ensureFormModal = function() {
        return ModalSaveCancel.create({
            title: '',
            body: ''
        }).then(function(modal) {
            modal.getFooter().hide();
            modal.getRoot().on(ModalEvents.hidden, function() {
                clearFieldErrors(modal.getBody());
                if (typeof modal.destroy === 'function') {
                    modal.destroy();
                }
            });
            modal.getRoot().on('click', 'input[name=cancel], button[name=cancel]', function(e) {
                e.preventDefault();
                modal.hide();
            });
            modal.getRoot().on('submit', 'form', function(e) {
                var form = $(e.target);
                e.preventDefault();
                clearFieldErrors(form);
                request(form.serializeArray().concat([
                    {name: 'sesskey', value: M.cfg.sesskey},
                    {name: 'ajax', value: 1},
                    {name: 'ajaxaction', value: 'saveblock'}
                ])).done(function(response) {
                    if (!response || !response.success) {
                        showFieldErrors(form, response ? response.fielderrors : {});
                        if (!response || !response.fielderrors || !Object.keys(response.fielderrors).length) {
                            showMessage(response && response.message ? response.message : config.strings.cancel);
                        }
                        return;
                    }
                    upsertRow(response.blockid, response.rowhtml);
                    modal.hide();
                }).fail(function(exception) {
                    showMessage(getRequestErrorMessage(exception, config.strings.cancel));
                });
            });
            return modal;
        });
    };

    var getModalTitle = function(blockaction, blocktype) {
        var prefix = blockaction === 'edit' ? config.strings.edit : config.strings.add;
        return prefix + ': ' + config.strings[blocktype];
    };

    var openBlockForm = function(blockaction, blocktype, blockid) {
        if (!config.hasitem) {
            window.alert(config.strings.savefirst);
            return;
        }

        ensureFormModal().then(function(modal) {
            modal.setTitle(getModalTitle(blockaction, blocktype));
            modal.setBody(Fragment.loadFragment('block_exaport', 'itemblock_form', config.fragmentcontextid, {
                itemid: config.itemid,
                courseid: config.courseid,
                categoryid: config.categoryid,
                cattype: config.cattype,
                blockaction: blockaction,
                blocktype: blocktype,
                blockid: blockid || 0
            }));
            modal.show();
        }).fail(Notification.exception);
    };

    var ensureChooserModal = function() {
        if (chooserModal) {
            return $.Deferred().resolve(chooserModal).promise();
        }

        var body = '' +
            '<div class="list-group exaport-item-block-chooser">' +
            '<button type="button" class="list-group-item list-group-item-action" data-blocktype="text">' +
            '<i class="fa fa-align-left" aria-hidden="true"></i> ' + config.strings.text + '</button>' +
            '<button type="button" class="list-group-item list-group-item-action" data-blocktype="link">' +
            '<i class="fa fa-link" aria-hidden="true"></i> ' + config.strings.link + '</button>' +
            '<button type="button" class="list-group-item list-group-item-action" data-blocktype="file">' +
            '<i class="fa fa-paperclip" aria-hidden="true"></i> ' + config.strings.file + '</button>' +
            '</div>';

        return ModalSaveCancel.create({
            title: config.strings.choosertitle,
            body: body
        }).then(function(modal) {
            chooserModal = modal;
            modal.getFooter().hide();
            modal.getRoot().on('click', '[data-blocktype]', function(e) {
                e.preventDefault();
                modal.hide();
                openBlockForm('add', $(this).data('blocktype'), 0);
            });
            return modal;
        });
    };

    var showChooser = function() {
        if (!config.hasitem) {
            window.alert(config.strings.savefirst);
            return;
        }
        ensureChooserModal().then(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    };

    var deleteBlock = function(blockid) {
        if (!window.confirm(config.strings.deleteconfirm)) {
            return;
        }

        request({
            sesskey: M.cfg.sesskey,
            ajax: 1,
            ajaxaction: 'deleteblock',
            blockid: blockid
        }).done(function(response) {
            if (!response || !response.success) {
                showMessage(response && response.message ? response.message : config.strings.deleteconfirm);
                return;
            }
            getBlockList().find('[data-blockid="' + blockid + '"]').remove();
            updateListEmptyState();
        }).fail(function(exception) {
            showMessage(getRequestErrorMessage(exception, config.strings.deleteconfirm));
        });
    };

    var initSortable = function() {
        var list = getBlockList();
        if (!list.length) {
            return;
        }

        if (list.hasClass('ui-sortable')) {
            list.sortable('destroy');
        }

        list.sortable({
            handle: '.exaport-item-block-handle',
            placeholder: 'block-placeholder',
            forcePlaceholderSize: true,
            start: function() {
                list.data('previous-order', getCurrentOrder());
            },
            update: function() {
                var previousOrder = list.data('previous-order') || [];
                updateOrderField();
                saveOrder(previousOrder);
            }
        });
        updateOrderField();
    };

    var moveBlock = function(button) {
        var item = button.closest('[data-blockid]');
        var previousOrder = getCurrentOrder();
        if (!item.length) {
            return;
        }

        if (button.data('direction') === 'up') {
            item.prev('[data-blockid]').before(item);
        } else {
            item.next('[data-blockid]').after(item);
        }

        updateOrderField();
        saveOrder(previousOrder);
    };

    return {
        init: function(initconfig) {
            config = initconfig || {};
            $('body').off('.exaportItemBlocks');
            $('body').on('click.exaportItemBlocks', '.exaport-add-content', function(e) {
                e.preventDefault();
                showChooser();
            });
            $('body').on('click.exaportItemBlocks', '.exaport-item-block-move', function(e) {
                e.preventDefault();
                moveBlock($(this));
            });
            $('body').on('click.exaportItemBlocks', '.exaport-item-block-edit', function(e) {
                e.preventDefault();
                openBlockForm('edit', $(this).data('blocktype'), $(this).data('blockid'));
            });
            $('body').on('click.exaportItemBlocks', '.exaport-item-block-delete', function(e) {
                e.preventDefault();
                deleteBlock($(this).data('blockid'));
            });
            updateListEmptyState();
            initSortable();
        }
    };
});
