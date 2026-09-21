define(['jquery'], function($) {
    QUnit.module('block_exaport/item_content_modal', {
        beforeEach: function() {
            this.fixture = $('#qunit-fixture');
            this.fixture.html(
                '<section class="exaport-item-content-section">' +
                '<a class="exaport-item-content-add" data-content-type="text" data-content-url="/text">Text</a>' +
                '<a class="exaport-item-content-add" data-content-type="link" data-content-url="/link">Link</a>' +
                '<a class="exaport-item-content-add" data-content-type="file" data-content-url="/file">File</a>' +
                '</section>'
            );
        }
    });

    QUnit.test('all add actions expose modal routing data without losing fallback URLs', function(assert) {
        var actions = this.fixture.find('.exaport-item-content-add');
        assert.deepEqual(actions.map(function() { return $(this).data('content-type'); }).get(),
            ['text', 'link', 'file'], 'text, link and file actions are available');
        actions.each(function() {
            assert.ok($(this).data('content-url'), 'the asynchronous endpoint is exposed');
        });
    });

    QUnit.test('validation and server errors can remain in the modal', function(assert) {
        var body = $('<div><form class="mform"><input required></form></div>');
        body.prepend('<div class="alert alert-danger exaport-item-content-error">Error</div>');
        assert.strictEqual(body.find('form.mform').length, 1, 'the form remains available');
        assert.strictEqual(body.find('[role="alert"]').length, 1, 'the error is announced');
    });

    QUnit.test('success replaces the visible content section', function(assert) {
        this.fixture.find('.exaport-item-content-section').replaceWith(
            '<section class="exaport-item-content-section"><div class="exaport-item-content-row">New</div></section>'
        );
        assert.strictEqual(this.fixture.find('.exaport-item-content-row').text(), 'New');
    });

    QUnit.test('cancellation leaves the visible content list unchanged', function(assert) {
        var before = this.fixture.find('.exaport-item-content-section').html();
        $('<button name="cancel">Cancel</button>').trigger('click').remove();
        assert.strictEqual(this.fixture.find('.exaport-item-content-section').html(), before);
    });
});
