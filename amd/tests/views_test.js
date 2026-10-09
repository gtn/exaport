/* global QUnit */
define(['jquery', 'block_exaport/views'], function($, Views) {
    QUnit.module('block_exaport/views structured content', {
        beforeEach: function() {
            this.originalConfig = window.M;
            this.originalExaport = window.block_exaport;
            this.originalIcons = window.block_exaport_update_fontawesome_icons;
            window.M = {
                cfg: {wwwroot: 'https://moodle.example.test'},
                util: {image_url: function(image, component) {
                    return 'https://moodle.example.test/theme/image.php?image=' + image + '&component=' + component;
                }}
            };
            window.block_exaport = {translate: function(key) { return key; }};
            window.block_exaport_update_fontawesome_icons = function() {};
            $('#qunit-fixture').html(
                '<form id="view_edit_form"><input name="blocks" type="hidden">' +
                '<input name="myresume" type="hidden" value=""></form>' +
                '<ul class="portfolioDesignBlocks"></ul>'
            );
        },
        afterEach: function() {
            window.M = this.originalConfig;
            window.block_exaport = this.originalExaport;
            window.block_exaport_update_fontawesome_icons = this.originalIcons;
            $('body').off('click', '[data-toggle="gtn-help-modal"]');
        }
    });

    var contents = {
        link: '<a href="https://example.test/link">Structured link</a>',
        text: '<p>Structured text <strong>formatting</strong></p>',
        file: '<a class="exaport-item-content-file" href="/pluginfile.php/file">document.pdf</a>'
    };
    contents.mixed = contents.link + contents.text + contents.file;

    Object.keys(contents).forEach(function(type) {
        [false, true].forEach(function(hasCompetences) {
            QUnit.test(type + ' renders with competences ' + hasCompetences, function(assert) {
                var contenthtml = '<section class="exaport-item-content-section">' + contents[type] + '</section>';
                var item = {
                    id: 42,
                    name: 'Artifact <title>',
                    type: 'note',
                    category: 'Category',
                    comments: 2,
                    filescount: 0,
                    link: '',
                    intro: '<p>Parent description</p>',
                    contenthtml: contenthtml,
                    competences: hasCompetences ? 'Competence title<br>' : ''
                };
                $('input[name=blocks]').val(JSON.stringify([
                    {id: 7, type: 'item', itemid: item.id, positionx: 1, positiony: 1, item: item}
                ]));

                // Use the public editor entry point, including its hidden-input serialization.
                Views.initialise(1);
                var block = $('.portfolioDesignBlocks > li');
                assert.strictEqual(block.find('.exaport-item-content').html(), contenthtml);
                assert.strictEqual(block.find('.exaport-item-intro').html(),
                    hasCompetences ? '' : item.intro, 'parent-description behavior is preserved');
                assert.strictEqual(block.find('img[alt="competences"]').length, hasCompetences ? 1 : 0,
                    'the competence tooltip remains independent');
                if (hasCompetences) {
                    assert.ok(block.find('a[onmouseover]').attr('onmouseover').includes('Competence title'),
                        'the tooltip retains its competence text');
                    assert.strictEqual(block.find('img[alt="competences"]').attr('src'),
                        window.M.util.image_url('t/grades', 'core'), 'Moodle resolves the competence icon');
                }
                assert.strictEqual(block.find('script').length, 0, 'rendering does not reload the tooltip library');
                assert.ok(block.find('.picture img').attr('src').endsWith('/item_thumb.php?item_id=42'),
                    'the existing thumbnail endpoint remains in use');
                assert.strictEqual(block.find('.header').text(), 'viewitem: Artifact <title>',
                    'metadata remains text');

                var saved = JSON.parse($('input[name=blocks]').val());
                assert.strictEqual(saved[0].item.contenthtml, contenthtml, 'serialization retains content');
                Views.initialise(1);
                assert.strictEqual($('.exaport-item-content-section').length, 1,
                    'refreshing from serialized blocks does not duplicate structured content');
                assert.strictEqual($('.exaport-item-content').html(), contenthtml);
            });
        });
    });

    QUnit.test('an artifact without structured content preserves its description', function(assert) {
        $('input[name=blocks]').val(JSON.stringify([{type: 'item', itemid: 42, item: {
            id: 42, name: 'Description only', type: 'note', category: '', comments: 0,
            filescount: 0, link: '', intro: '<p>Description only</p>', contenthtml: ''
        }}]));
        Views.initialise(1);
        assert.strictEqual($('.exaport-item-intro').html(), '<p>Description only</p>');
        assert.strictEqual($('.exaport-item-content').html(), '');
    });

    QUnit.test('an SVG competence icon renders at a small size', function(assert) {
        var done = assert.async();
        window.M.util.image_url = function() {
            return 'data:image/svg+xml,' + encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">' +
                '<rect width="512" height="512" /></svg>'
            );
        };
        $('input[name=blocks]').val(JSON.stringify([{type: 'item', itemid: 42, item: {
            id: 42, name: 'SVG icon', type: 'note', category: '', comments: 0,
            filescount: 0, link: '', intro: '', contenthtml: '', competences: 'Competence title<br>'
        }}]));
        Views.initialise(1);
        var icon = $('.portfolioDesignBlocks img[alt="competences"]')[0];
        icon.decode().then(function() {
            var bounds = icon.getBoundingClientRect();
            assert.strictEqual(bounds.width, 16, 'the loaded SVG is 16 pixels wide');
            assert.strictEqual(bounds.height, 16, 'the loaded SVG is 16 pixels high');
            done();
        }).catch(function(error) {
            assert.ok(false, 'SVG failed to load: ' + error.message);
            done();
        });
    });
});
