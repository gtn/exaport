// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Moodle modal used to edit an item's competence selection.
 *
 * @module block_exaport/item_competences
 */
define(['jquery', 'core/modal_save_cancel', 'core/modal_events', 'core/notification'], function(
    $, ModalSaveCancel, ModalEvents, Notification
) {

    /**
     * Read selected competence ids from a picker.
     *
     * @param {jQuery} $root Picker root.
     * @return {Array}
     */
    const selectedIds = function($root) {
        return $root.find('[data-region="competence-checkbox"]:checked').map(function() {
            return this.value;
        }).get();
    };

    /**
     * Update the read-only tree shown in the item form.
     *
     * @param {jQuery} $section Competence section.
     * @param {jQuery} $picker Picker containing the persisted selection.
     */
    const renderSummary = function($section, $picker) {
        const $tree = $picker.find('.exaport-competence-tree').first().clone();

        $tree.find('.exaport-competence-node').each(function() {
            const $node = $(this);
            const $checkbox = $node.children('.custom-control').find('[data-region="competence-checkbox"]');
            if ($checkbox.length && !$checkbox.is(':checked')) {
                $node.remove();
            }
        });
        $tree.find('details').each(function() {
            const $details = $(this);
            if (!$details.find('[data-region="competence-checkbox"]:checked').length) {
                $details.closest('.exaport-competence-node').remove();
            }
        });
        $tree.find('.custom-control').each(function() {
            const label = $(this).find('label').text();
            $(this).replaceWith($('<span class="text-success"></span>').text('✓ ' + label));
        });
        $tree.find('details').prop('open', true);
        $tree.addClass('exaport-competence-summary mb-3');
        $section.find('[data-region="competence-summary"]').empty().append($tree);
    };

    /**
     * Initialize the competence section and picker modal.
     *
     * @param {Object} config Endpoint, item and translated string configuration.
     */
    const init = function(config) {
        const $section = $('[data-region="item-competences"]');
        const $source = $section.find('[data-region="competence-picker-source"]');
        const pickerHtml = $source.html();
        let persistedIds = selectedIds($(pickerHtml));

        $section.on('click', '[data-action="open-competence-picker"]', function() {
            const $picker = $(pickerHtml);
            $picker.find('[data-region="competence-checkbox"]').each(function() {
                this.checked = persistedIds.indexOf(this.value) !== -1;
            });

            ModalSaveCancel.create({
                title: config.title,
                body: $picker,
                large: true,
                removeOnClose: true,
            }).then(function(modal) {
                modal.setSaveButtonText(config.saveLabel);

                modal.getRoot().on('click', '[data-action="expand-competences"]', function() {
                    $picker.find('details').prop('open', true);
                });
                modal.getRoot().on('click', '[data-action="collapse-competences"]', function() {
                    $picker.find('details').prop('open', false);
                });
                modal.getRoot().on(ModalEvents.save, function(event) {
                    event.preventDefault();
                    const $savebutton = modal.getRoot().find('[data-action="save"]');
                    $savebutton.prop('disabled', true);
                    $picker.find('[data-region="competence-save-error"]').text('');

                    $.ajax({
                        url: config.saveUrl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            courseid: config.courseId,
                            itemid: config.itemId,
                            competenceids: selectedIds($picker),
                            sesskey: config.sesskey,
                        },
                    }).done(function() {
                        persistedIds = selectedIds($picker);
                        renderSummary($section, $picker);
                        modal.hide();
                    }).fail(function() {
                        $picker.find('[data-region="competence-save-error"]').text(config.saveFailed);
                    }).always(function() {
                        $savebutton.prop('disabled', false);
                    });
                });

                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    };

    return {init: init};
});
