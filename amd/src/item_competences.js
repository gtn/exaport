// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Moodle dynamic-form modal used to edit an item's competence selection.
 *
 * @module block_exaport/item_competences
 */

import ModalForm from 'core_form/modalform';
import * as Templates from 'core/templates';
import Notification from 'core/notification';

/** Synchronize custom tree checkboxes with the registered dynamic-form value. */
export const synchronizeSelection = picker => {
    const field = picker.closest('form').querySelector('[name="competenceids"]');
    field.value = Array.from(picker.querySelectorAll('[data-region="competence-checkbox"]:checked'))
        .map(checkbox => checkbox.value).join(',');
};

/** Replace the server-rendered selected-competences summary. */
export const replaceSummary = (section, content) => Templates.replaceNode(
    section.querySelector('[data-region="competence-summary"]'), content, '');

/** Register competency tree interactions and the Moodle dynamic-form launcher. */
export const init = config => {
    document.addEventListener('click', event => {
        const treeAction = event.target.closest('[data-action="expand-competences"], [data-action="collapse-competences"]');
        if (treeAction) {
            const picker = treeAction.closest('[data-region="competence-picker"]');
            picker.querySelectorAll('details').forEach(details => {
                details.open = treeAction.dataset.action === 'expand-competences';
            });
            return;
        }

        const trigger = event.target.closest('[data-action="open-competence-picker"]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        const section = trigger.closest('[data-region="item-competences"]');
        const modalForm = new ModalForm({
            formClass: 'block_exaport\\form\\item_competences',
            args: {courseid: config.courseId, itemid: config.itemId},
            modalConfig: {title: config.title, large: true},
            saveButtonText: config.saveLabel,
            returnFocus: trigger,
        });
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, submitted => {
            replaceSummary(section, submitted.detail.content).catch(Notification.exception);
        });
        modalForm.show();
    });

    document.addEventListener('change', event => {
        if (event.target.matches('[data-region="competence-checkbox"]')) {
            synchronizeSelection(event.target.closest('[data-region="competence-picker"]'));
        }
    });
};
