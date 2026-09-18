// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define([], function() {

    /**
     * Initialize the item competence popup.
     *
     * @param {Object} config Endpoint and item configuration.
     */
    const init = function(config) {
        const $ = window.jQueryExaport;
        const $form = $('#treeform');
        const $descriptors = $form.find(':checkbox');
        const $submit = $form.find('[name="savecompetencesbutton"]');
        let persistedIds = selectedIds();

        function selectedIds() {
            return $descriptors.filter(':checked').map(function() {
                return this.value;
            }).get();
        }

        function restorePersistedSelection() {
            $descriptors.each(function() {
                this.checked = persistedIds.indexOf(this.value) !== -1;
            });
        }

        function renderSelection() {
            const $tree = $('#comptree').clone().attr('id', 'comptree-selected');
            $tree.find('li').each(function() {
                if (!$(this).find(':checked').length) {
                    $(this).remove();
                }
            });
            $tree.find(':checkbox').remove();
            $('#comptitles').empty().append($tree);
            window.ddtreemenu.createTree('comptree-selected', false);
            window.ddtreemenu.flatten('comptree-selected', 'expand');
        }

        $descriptors.on('click', function(event) {
            event.stopPropagation();
        });

        $form.on('submit', function(event) {
            event.preventDefault();
            $submit.prop('disabled', true);
            $('#competences-popup-status').text('');

            $.ajax({
                url: config.saveUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    courseid: config.courseId,
                    itemid: config.itemId,
                    competenceids: selectedIds(),
                    sesskey: config.sesskey,
                },
            }).done(function() {
                persistedIds = selectedIds();
                renderSelection();
                $.colorbox.close();
            }).fail(function() {
                $('#competences-popup-status').text(config.saveFailed);
            }).always(function() {
                $submit.prop('disabled', false);
            });
        });

        $('.competences').colorbox({
            width: '75%',
            height: '75%',
            inline: true,
            href: '#inline_comp_tree',
            onOpen: function() {
                restorePersistedSelection();
                $('#competences-popup-status').text('');
            },
            onClosed: restorePersistedSelection,
        });

        window.ddtreemenu.createTree('comptree', true);
        renderSelection();
    };

    return {init: init};
});
