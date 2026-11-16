define(['jquery', 'block_exaport/item_content_modal'], function($, Content) {
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

    QUnit.module('block_exaport/item_content_modal pending state', {
        beforeEach: function() {
            this.fixture = document.getElementById('qunit-fixture');
            this.fixture.innerHTML = '<form><input type="hidden" name="contentblocks">' +
                '<section class="exaport-item-content-section">' +
                '<div class="exaport-item-content-rows" hidden></div>' +
                '<button type="button" class="exaport-item-content-add-button">Add</button>' +
                '</section></form>';
            this.form = this.fixture.querySelector('form');
            this.section = this.fixture.querySelector('section');
            this.form.querySelector('[name="contentblocks"]').value = JSON.stringify({blocks: [], removed: []});
            this.row = function(key, title) {
                return '<div class="exaport-item-content-row" data-block-key="' + key + '">' +
                    '<span>' + title + '</span><a class="exaport-item-content-edit" href="#">Edit</a></div>';
            };
        }
    });

    QUnit.test('applying an unsaved block twice replaces the same pending entry and preview', function(assert) {
        var first = {key: 'new-first', id: 0, type: 'text', title: 'First', modified: true};
        Content.applyPendingResult(this.form, this.section, {block: first, content: this.row(first.key, first.title)});
        var second = {key: 'new-second', id: 0, type: 'text', title: 'Other', modified: true};
        Content.applyPendingResult(this.form, this.section, {block: second, content: this.row(second.key, second.title)});
        var edited = {key: 'new-first', id: 0, type: 'text', title: 'Edited', modified: true};
        var focus = Content.applyPendingResult(this.form, this.section,
            {block: edited, content: this.row(edited.key, edited.title)});

        var state = Content.readPendingState(this.form);
        assert.strictEqual(state.blocks.length, 2, 'reopening an unsaved block does not append a duplicate');
        assert.strictEqual(state.blocks[0].title, 'Edited', 'latest accepted values replace the original values');
        assert.strictEqual(state.blocks[1].title, 'Other', 'another block is retained');
        assert.strictEqual(this.section.querySelectorAll('.exaport-item-content-row').length, 2);
        assert.strictEqual(focus.closest('[data-block-key]').dataset.blockKey, edited.key);
        assert.strictEqual(this.form.dataset.formDirty, 'true', 'the main form warns before abandoning pending changes');
        assert.strictEqual(this.section.querySelector('.exaport-item-content-rows').hidden, false);
    });

    QUnit.test('only explicitly removed saved blocks produce deletion IDs', function(assert) {
        var saved = {key: 'block-42', id: 42, type: 'text', title: 'Saved', modified: false};
        var temporary = {key: 'new-first', id: 0, type: 'text', title: 'New', modified: true};
        Content.applyPendingResult(this.form, this.section, {block: saved, content: this.row(saved.key, saved.title)});
        Content.applyPendingResult(this.form, this.section,
            {block: temporary, content: this.row(temporary.key, temporary.title)});
        Content.applyPendingResult(this.form, this.section, {key: temporary.key, removed: true});
        assert.deepEqual(Content.readPendingState(this.form).removed, [], 'a temporary key is never a database deletion');
        assert.strictEqual(Content.readPendingState(this.form).blocks[0].id, 42, 'the saved block remains');

        var focus = Content.applyPendingResult(this.form, this.section, {key: saved.key, removed: true});
        assert.deepEqual(Content.readPendingState(this.form).removed, [42], 'the deletion is explicit');
        assert.strictEqual(this.section.querySelector('.exaport-item-content-rows').hidden, true);
        assert.strictEqual(focus, this.section.querySelector('.exaport-item-content-add-button'));
    });

});
