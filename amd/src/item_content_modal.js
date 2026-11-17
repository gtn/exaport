import ModalForm from 'core_form/modalform';
import * as Templates from 'core/templates';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';
import * as FormChangeChecker from 'core_form/changechecker';

/**
 * Read the main form's working copy, never the values from an earlier modal opening.
 *
 * @param {HTMLFormElement} form Main item form
 * @returns {Object} Accepted pending blocks and explicit removals
 */
export const readPendingState = form => {
    const state = JSON.parse(form.querySelector('[name="contentblocks"]').value);
    if (!state || !Array.isArray(state.blocks) || !Array.isArray(state.removed)) {
        throw new Error('Invalid pending content state');
    }
    return state;
};

/**
 * Stable editor keys also identify blocks which do not have a database ID yet.
 *
 * @param {HTMLElement} section Content section
 * @param {string} key Editor key
 * @returns {HTMLElement|undefined} Current preview row
 */
const findRow = (section, key) => Array.from(section.querySelectorAll('.exaport-item-content-row'))
    .find(row => row.dataset.blockKey === key);

/**
 * Apply a validated modal result to the form and return the control to focus after it closes.
 *
 * @param {HTMLFormElement} form Main item form
 * @param {HTMLElement} section Content section
 * @param {Object} result Validated modal response
 * @returns {HTMLElement|undefined} Focus destination after the modal closes
 */
export const applyPendingResult = (form, section, result) => {
    const state = readPendingState(form);
    const key = result.removed ? result.key : result.block.key;
    const index = state.blocks.findIndex(block => block.key === key);
    const previous = index >= 0 ? state.blocks[index] : null;
    const row = findRow(section, key);
    const rows = section.querySelector('.exaport-item-content-rows');

    if (result.removed) {
        // An omitted block may have been added by another client. Only record explicit removals.
        if (previous && Number(previous.id) > 0 && !state.removed.includes(Number(previous.id))) {
            state.removed.push(Number(previous.id));
        }
        state.blocks = state.blocks.filter(block => block.key !== key);
        if (row) {
            Templates.replaceNode(row, '', '');
        }
    } else {
        if (index >= 0) {
            state.blocks[index] = result.block;
        } else {
            state.blocks.push(result.block);
        }
        state.removed = state.removed.filter(id => Number(id) !== Number(result.block.id));
        if (row) {
            Templates.replaceNode(row, result.content, '');
        } else {
            Templates.appendNodeContents(rows, result.content, '');
        }
    }

    form.querySelector('[name="contentblocks"]').value = JSON.stringify(state);
    rows.hidden = state.blocks.length === 0;
    // Hidden inputs do not emit user input events when assigned programmatically.
    FormChangeChecker.markFormAsDirty(form);
    return result.removed ? section.querySelector('.exaport-item-content-add-button') :
        findRow(section, key)?.querySelector('.exaport-item-content-edit');
};

/** Create a client-only identity; authorization always uses the separately validated database ID. */
const newKey = () => `new-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

/**
 * Open Moodle's standard dynamic-form modal.
 *
 * @param {HTMLElement} trigger The action which opened the modal.
 * @param {object} config Page configuration.
 */
const open = (trigger, config) => {
    const form = trigger.closest('form');
    const section = trigger.closest('.exaport-item-content-section');
    const args = {
        contenttype: trigger.dataset.contentType,
        courseid: config.courseId,
        itemid: config.itemId,
        blockid: trigger.dataset.blockId || 0,
        operation: trigger.dataset.operation || 'save',
    };
    if (config.pending) {
        args.pending = 1;
        const key = trigger.dataset.blockKey;
        const block = key ? readPendingState(form).blocks.find(candidate => candidate.key === key) : {
            key: newKey(), id: 0, type: args.contenttype, title: '', modified: true,
        };
        if (!block) {
            return;
        }
        args.pendingblock = JSON.stringify(block);
        args.blockid = block.id;
    }
    const modalForm = new ModalForm({
        formClass: 'block_exaport\\form\\item_content',
        args,
        modalConfig: {title: trigger.dataset.operation === 'delete' ? config.deleteTitle : config.title},
        saveButtonText: trigger.dataset.operation === 'delete' ? config.deleteLabel : config.saveLabel,
        returnFocus: trigger,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
        // A slow Apply can finish after Escape has closed and removed its modal.
        if (config.pending && !event.currentTarget.isConnected) {
            event.preventDefault();
            return;
        }
        if (section) {
            try {
                if (config.pending) {
                    const focus = applyPendingResult(form, section, event.detail);
                    // The old edit/delete trigger may have been replaced; focus its current counterpart.
                    modalForm.modal.getRoot().one(ModalEvents.hidden, () => focus?.focus());
                } else {
                    Templates.replaceNode(section, event.detail.content, '');
                }
            } catch (error) {
                if (config.pending) {
                    // Keep the working modal available if its preview cannot be applied to the parent form.
                    event.preventDefault();
                    modalForm.enableButtons();
                }
                Notification.exception(error);
            }
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
        const trigger = event.target.closest(
            '.exaport-item-content-add, .exaport-item-content-edit, .exaport-item-content-delete'
        );
        if (!trigger) {
            return;
        }
        event.preventDefault();
        try {
            open(trigger, config);
        } catch (error) {
            Notification.exception(error);
        }
    });
};
