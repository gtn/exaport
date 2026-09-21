import ModalForm from 'core_form/modalform';
import * as Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Open Moodle's standard dynamic-form modal.
 *
 * @param {HTMLElement} trigger The action which opened the modal.
 * @param {object} config Page configuration.
 */
const open = (trigger, config) => {
    const modalForm = new ModalForm({
        formClass: 'block_exaport\\form\\item_content',
        args: {
            contenttype: trigger.dataset.contentType,
            courseid: config.courseId,
            itemid: config.itemId,
        },
        modalConfig: {title: config.title},
        saveButtonText: config.saveLabel,
        returnFocus: trigger,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        const section = document.querySelector('.exaport-item-content-section');
        if (section) {
            Templates.replaceNode(section, event.detail.content, '').catch(Notification.exception);
        }
    });
    modalForm.show();
};

/**
 * Register item-content actions.
 *
 * @param {object} config Page configuration.
 */
export const init = config => {
    document.addEventListener('click', event => {
        const trigger = event.target.closest('.exaport-item-content-add');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        open(trigger, config);
    });
};
