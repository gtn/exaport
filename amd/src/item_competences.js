import ModalForm from 'core_form/modalform';
import * as Templates from 'core/templates';
import Notification from 'core/notification';

const ITEM_SECTION_SELECTOR = '[data-region="item-competences"]';
const COMPETENCE_SUMMARY_SELECTOR = '[data-region="competence-summary"]';
const COMPETENCE_CHECKBOX_SELECTOR = '[data-region="competence-checkbox"]';
const COMPETENCE_HIDDEN_FIELD_SELECTOR = 'input[name="competenceids"]';

/**
 * Normalize a collection of checkbox values.
 *
 * @param {string[]} values Raw checkbox values.
 * @returns {number[]}
 */
const normalizeIds = values => [...new Set(values
    .map(value => Number.parseInt(value, 10))
    .filter(value => Number.isInteger(value) && value > 0))]
    .sort((left, right) => left - right);

/**
 * Collect the checked competence ids inside a picker root.
 *
 * @param {ParentNode} root Picker or form root.
 * @returns {number[]}
 */
export const collectSelectedCompetencyIds = root => normalizeIds(
    Array.from(root.querySelectorAll(`${COMPETENCE_CHECKBOX_SELECTOR}:checked`), checkbox => checkbox.value)
);

/**
 * Synchronize the custom tree selection into the registered form field.
 *
 * @param {ParentNode} root Picker or form root.
 * @returns {string}
 */
export const syncSelection = root => {
    const hiddenField = root.querySelector(COMPETENCE_HIDDEN_FIELD_SELECTOR);
    if (!hiddenField) {
        return '';
    }

    hiddenField.value = collectSelectedCompetencyIds(root).join(',');
    return hiddenField.value;
};

/**
 * Expand or collapse every tree branch in the picker.
 *
 * @param {ParentNode} root Picker root.
 * @param {boolean} expanded Whether branches should be expanded.
 */
export const setTreeExpanded = (root, expanded) => {
    root.querySelectorAll('details').forEach(details => {
        details.open = expanded;
    });
};

/**
 * Replace the authoritative server-rendered summary for one item.
 *
 * @param {number} itemId Item id.
 * @param {string} content Rendered summary HTML.
 * @returns {Promise<void>}
 */
export const replaceSummary = (itemId, content) => {
    const summary = document.querySelector(`${COMPETENCE_SUMMARY_SELECTOR}[data-itemid="${itemId}"]`);
    if (!summary) {
        return Promise.resolve();
    }

    return Templates.replaceNode(summary, content, '');
};

/**
 * Open the standard dynamic-form modal.
 *
 * @param {HTMLElement} trigger Action which opened the modal.
 * @param {object} config Page configuration.
 */
const open = (trigger, config) => {
    const modalForm = new ModalForm({
        formClass: 'block_exaport\\form\\item_competences',
        args: {
            courseid: config.courseId,
            itemid: config.itemId,
        },
        modalConfig: {title: config.title},
        saveButtonText: config.saveLabel,
        returnFocus: trigger,
    });
    let pickerHandlersBound = false;
    let summaryHandled = false;

    modalForm.addEventListener(modalForm.events.LOADED, () => {
        if (pickerHandlersBound) {
            return;
        }
        pickerHandlersBound = true;
        const modalRoot = modalForm.modal.getRoot()[0];
        syncSelection(modalRoot);

        modalRoot.addEventListener('change', event => {
            if (event.target.closest(COMPETENCE_CHECKBOX_SELECTOR)) {
                syncSelection(modalRoot);
            }
        });

        modalRoot.addEventListener('click', event => {
            if (event.target.closest('[data-action="expand-competences"]')) {
                event.preventDefault();
                setTreeExpanded(modalRoot, true);
            } else if (event.target.closest('[data-action="collapse-competences"]')) {
                event.preventDefault();
                setTreeExpanded(modalRoot, false);
            }
        });
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        if (summaryHandled) {
            return;
        }
        summaryHandled = true;
        replaceSummary(event.detail.itemid, event.detail.content).catch(Notification.exception);
    });
    modalForm.show().catch(Notification.exception);
};

/**
 * Register item-competence modal actions.
 *
 * @param {object} config Page configuration.
 */
export const init = config => {
    const section = document.querySelector(`${ITEM_SECTION_SELECTOR}[data-itemid="${config.itemId}"]`);
    if (!section) {
        return;
    }

    section.addEventListener('click', event => {
        const trigger = event.target.closest('[data-action="open-competence-picker"]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        open(trigger, config);
    });
};
