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

});
