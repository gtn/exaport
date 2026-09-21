define([
    'jquery',
    'core/modal',
    'core/modal_events',
    'core/fragment',
    'core/templates'
], function($, Modal, ModalEvents, Fragment, Templates) {

    var modal;
    var activeType;
    var config;

    /** Remove editor instances which Moodle attached inside the modal. */
    var destroyWidgets = function() {
        if (!modal) {
            return;
        }
        modal.getRoot().find('textarea').each(function() {
            var id = this.id;
            if (id && window.tinyMCE && window.tinyMCE.get(id)) {
                window.tinyMCE.get(id).remove();
            }
            $(this).trigger('editorRemoved');
        });
    };

    var showError = function(message) {
        var alert = $('<div class="alert alert-danger exaport-item-content-error" role="alert"></div>');
        alert.text(message || config.error);
        modal.getBody().find('.exaport-item-content-error').remove();
        modal.getBody().prepend(alert);
    };

    var open = async function(type, url) {
        activeType = type;
        var body = Fragment.loadFragment('block_exaport', 'itemcontentform', config.contextId, {
            contenttype: type,
            courseid: config.courseId,
            itemid: config.itemId
        });
        if (!modal) {
            modal = await Modal.create({title: config.title, body: '', footer: '', large: true});
            modal.getRoot().on(ModalEvents.hidden, function() {
                destroyWidgets();
                modal.setBody('');
                activeType = null;
            });
            modal.getRoot().on('click', '[name="cancel"]', function(event) {
                event.preventDefault();
                modal.hide();
            });
            modal.getRoot().on('submit', 'form.mform', function(event) {
                event.preventDefault();
                var form = this;
                if (window.tinyMCE) {
                    window.tinyMCE.triggerSave();
                }
                var data = $(form).serializeArray();
                data.push({name: 'ajax', value: 1});
                modal.getBody().find('.exaport-item-content-error').remove();
                $.ajax({url: url, method: 'POST', data: data, dataType: 'json'}).done(function(response) {
                    if (response.success) {
                        var section = $('.exaport-item-content-section').first();
                        Templates.replaceNode(section[0], response.content, '').then(function() {
                            modal.hide();
                        });
                    } else if (response.form) {
                        destroyWidgets();
                        Templates.replaceNodeContents(modal.getBody()[0], response.form, response.javascript || '');
                    } else if (!response.cancelled) {
                        showError(response.error);
                    }
                }).fail(function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.error;
                    showError(message || config.error);
                });
            });
        }
        modal.setTitle(config.title);
        modal.setBody(body);
        modal.show();
    };

    return {
        init: function(options) {
            config = options;
            $(document).on('click', '.exaport-item-content-add', function(event) {
                event.preventDefault();
                open($(this).data('content-type'), $(this).data('content-url'));
            });
        },
        // Exported for focused JavaScript unit tests.
        _open: open,
        _getActiveType: function() { return activeType; }
    };
});
