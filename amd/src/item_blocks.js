// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define(['jquery', 'jqueryui', 'core/modal_save_cancel'], function($, JQueryUI, ModalSaveCancel) {
    var config = {};

    var updateOrderField = function() {
        var ids = [];
        $('.exaport-item-block-list [data-blockid]').each(function() {
            ids.push(parseInt($(this).attr('data-blockid'), 10));
        });
        $('#id_blockorder').val(JSON.stringify(ids));
    };

    var showChooser = function() {
        if (!config.hasitem) {
            window.alert(config.strings.savefirst);
            return;
        }

        var body = '<div class="list-group">' +
            '<a class="list-group-item list-group-item-action" href="' + config.addurls.text + '">' +
            config.strings.text + '</a>' +
            '<a class="list-group-item list-group-item-action" href="' + config.addurls.file + '">' +
            config.strings.file + '</a>' +
            '<a class="list-group-item list-group-item-action" href="' + config.addurls.link + '">' +
            config.strings.link + '</a>' +
            '<button type="button" class="btn btn-secondary mt-3 exaport-item-block-chooser-cancel">' +
            config.strings.cancel + '</button>' +
            '</div>';

        ModalSaveCancel.create({
            title: config.strings.choosertitle,
            body: body
        }).then(function(modal) {
            modal.getFooter().hide();
            modal.getRoot().on('click', '.exaport-item-block-chooser-cancel', function(e) {
                e.preventDefault();
                modal.hide();
            });
            modal.show();
            return modal;
        });
    };

    var initSortable = function() {
        if (!$('.exaport-item-block-list').length) {
            return;
        }

        $('.exaport-item-block-list').sortable({
            handle: '.exaport-item-block-handle',
            placeholder: 'block-placeholder',
            forcePlaceholderSize: true,
            update: updateOrderField
        });
        updateOrderField();
    };

    var moveBlock = function(button) {
        var item = button.closest('[data-blockid]');
        if (!item.length) {
            return;
        }

        if (button.data('direction') === 'up') {
            item.prev('[data-blockid]').before(item);
        } else {
            item.next('[data-blockid]').after(item);
        }

        updateOrderField();
    };

    return {
        init: function(initconfig) {
            config = initconfig || {};
            $('body').on('click', '.exaport-add-content', function(e) {
                e.preventDefault();
                showChooser();
            });
            $('body').on('click', '.exaport-item-block-move', function(e) {
                e.preventDefault();
                moveBlock($(this));
            });
            initSortable();
        }
    };
});
