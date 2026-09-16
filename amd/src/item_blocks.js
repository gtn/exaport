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
            '<a class="list-group-item list-group-item-action" href="' + config.chooserurl + '&blockaction=add&blocktype=text">' +
            config.strings.text + '</a>' +
            '<a class="list-group-item list-group-item-action" href="' + config.chooserurl + '&blockaction=add&blocktype=file">' +
            config.strings.file + '</a>' +
            '<a class="list-group-item list-group-item-action" href="' + config.chooserurl + '&blockaction=add&blocktype=link">' +
            config.strings.link + '</a>' +
            '</div>';

        ModalSaveCancel.create({
            title: config.strings.choosertitle,
            body: body
        }).then(function(modal) {
            modal.getFooter().hide();
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

    return {
        init: function(initconfig) {
            config = initconfig || {};
            $('body').on('click', '.exaport-add-content', function(e) {
                e.preventDefault();
                showChooser();
            });
            initSortable();
        }
    };
});
