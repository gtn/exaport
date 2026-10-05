define(['jquery'], function($) {
    QUnit.module('block_exaport/item_content_modal', {
        beforeEach: function() {
            this.fixture = $('#qunit-fixture');
            this.fixture.html(
                '<section class="exaport-item-content-section">' +
                '<a class="exaport-item-content-add" data-content-type="text" data-content-url="/text">Text</a>' +
                '<a class="exaport-item-content-add" data-content-type="link" data-content-url="/link">Link</a>' +
                '<a class="exaport-item-content-add" data-content-type="file" data-content-url="/file">File</a>' +
                '<a class="exaport-item-content-edit" data-content-type="link" data-block-id="42" ' +
                'data-operation="save" href="/edit">Edit</a>' +
                '<a class="exaport-item-content-delete" data-content-type="link" data-block-id="42" ' +
                'data-operation="delete" href="/delete">Delete</a>' +
                '</section>'
            );
        }
    });

    QUnit.test('edit and delete actions identify the existing block and retain fallbacks', function(assert) {
        var edit = this.fixture.find('.exaport-item-content-edit');
        var remove = this.fixture.find('.exaport-item-content-delete');
        assert.strictEqual(edit.data('block-id'), 42, 'edit targets the selected block');
        assert.strictEqual(edit.data('operation'), 'save', 'edit uses the save operation');
        assert.strictEqual(remove.data('block-id'), 42, 'delete targets the same selected block');
        assert.strictEqual(remove.data('operation'), 'delete', 'delete is explicit');
        assert.ok(edit.attr('href') && remove.attr('href'), 'both actions retain standalone fallback URLs');
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
