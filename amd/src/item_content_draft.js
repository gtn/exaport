/**
 * Deferred text content block drafts for the Exaport item form.
 *
 * @module     block_exaport/item_content_draft
 * @copyright  2026 GTN - Global Training Network GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal', 'core/modal_events', 'core/fragment'], function($, Modal, ModalEvents, Fragment) {
    var config = {};
    var modalpromise = null;
    var activeform = null;
    var draftcounter = 0;

    var getForm = function(trigger) {
        return $(trigger).closest('form').first();
    };

    var getDrafts = function(form) {
        var value = form.find('input[name="pendingcontentblocks"]').val() || '';
        if (!value) {
            return [];
        }

        try {
            var drafts = JSON.parse(value);
            return Array.isArray(drafts) ? drafts : [];
        } catch (error) {
            return [];
        }
    };

    var setDrafts = function(form, drafts) {
        form.find('input[name="pendingcontentblocks"]').val(JSON.stringify(drafts));
    };

    var plainText = function(content) {
        var container = document.createElement('div');
        container.innerHTML = content || '';
        return (container.textContent || container.innerText || '').replace(/\s+/g, ' ').trim();
    };

    var renderDrafts = function(form) {
        var rows = form.find('[data-exaport-content-rows]').first();
        if (!rows.length) {
            return;
        }

        rows.find('[data-exaport-pending-id]').remove();
        var drafts = getDrafts(form);
        var icon = form.find('[data-exaport-text-icon]').first().html() || '';

        drafts.forEach(function(draft) {
            var row = $('<div>', {
                'class': 'exaport-item-content-row d-flex align-items-start py-3 border-bottom',
                'data-exaport-pending-id': draft.tempid,
            });
            var iconcontainer = $('<div>', {
                'class': 'exaport-item-content-icon flex-shrink-0 me-3',
            }).html(icon);
            var contentcontainer = $('<div>', {
                'class': 'flex-grow-1 text-break',
            });
            var metadata = $('<div>', {
                'class': 'd-flex flex-wrap align-items-baseline gap-2',
            });
            $('<span>', {
                'class': 'exaport-item-content-type text-muted small',
                text: config.textlabel,
            }).appendTo(metadata);
            if (draft.title) {
                $('<span>', {
                    'class': 'exaport-item-content-title fw-semibold',
                    text: draft.title,
                }).appendTo(metadata);
            }
            metadata.appendTo(contentcontainer);

            var preview = plainText(draft.content);
            if (preview) {
                $('<div>', {
                    'class': 'exaport-item-content-preview mt-1',
                    text: preview,
                }).appendTo(contentcontainer);
            }

            row.append(iconcontainer, contentcontainer).appendTo(rows);
        });

        if (drafts.length || rows.children().length) {
            rows.removeClass('d-none');
        } else {
            rows.addClass('d-none');
        }
    };

    var readEditorValue = function(form) {
        var textarea = form.find('[name="content_editor[text]"]').first();
        if (!textarea.length) {
            textarea = form.find('textarea[name="content_editor"]').first();
        }

        var formatinput = form.find('[name="content_editor[format]"]').first();
        var contentformat = parseInt(formatinput.val(), 10);
        if (isNaN(contentformat)) {
            contentformat = 1;
        }

        return {
            content: textarea.val() || '',
            contentformat: contentformat,
        };
    };

    var addDraft = function(form, modal) {
        var editor = readEditorValue(modal.getRoot());
        var drafts = getDrafts(form);
        draftcounter++;
        drafts.push({
            tempid: 'pending-' + Date.now() + '-' + draftcounter,
            type: 'text',
            title: modal.getRoot().find('[name="title"]').val() || '',
            content: editor.content,
            contentformat: editor.contentformat,
        });
        setDrafts(form, drafts);
        renderDrafts(form);
        modal.hide();
    };

    var getModal = function() {
        if (!modalpromise) {
            modalpromise = Modal.create({
                title: config.title,
                body: '',
                footer: '<button type="button" class="btn btn-secondary" data-exaport-modal-cancel>' +
                    config.cancellabel + '</button>' +
                    '<button type="button" class="btn btn-primary" data-exaport-modal-save>' +
                    config.savelabel + '</button>',
            });
        }
        return modalpromise;
    };

    var openModal = function(form) {
        activeform = form;
        var fragment = Fragment.loadFragment(
            'block_exaport',
            'item_content_text',
            config.contextid,
            {
                courseid: config.courseid,
                itemid: config.itemid,
            }
        );

        getModal().then(function(modal) {
            if (!modal.getRoot().data('exaport-content-events')) {
                modal.getRoot().data('exaport-content-events', true);
                modal.getRoot().on('click', '[data-exaport-modal-save]', function() {
                    var modalform = modal.getRoot().find('form').first();
                    if (modalform.length) {
                        modalform.trigger('submit');
                    } else {
                        addDraft(activeform, modal);
                    }
                });
                modal.getRoot().on('click', '[data-exaport-modal-cancel]', function() {
                    modal.hide();
                });
                modal.getRoot().on('submit', 'form', function(event) {
                    event.preventDefault();
                    addDraft(activeform, modal);
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.setBody('');
                    activeform = null;
                });
            }
            modal.setBody(fragment);
            modal.show();
        });
    };

    return {
        init: function(options) {
            config = options || {};
            $(document).on('click', '[data-exaport-content-add="text"]', function(event) {
                event.preventDefault();
                openModal(getForm(this));
            });

            $('input[name="pendingcontentblocks"]').each(function() {
                renderDrafts($(this).closest('form'));
            });
        },
    };
});
