/* global QUnit */
define(['jquery', 'block_exaport/views', 'core_filters/events'], function($, Views, FilterEvents) {
    QUnit.module('block_exaport/views structured content', {
        beforeEach: function() {
            this.originalConfig = window.M;
            this.originalExaport = window.block_exaport;
            this.originalIcons = window.block_exaport_update_fontawesome_icons;
            window.M = {cfg: {wwwroot: 'https://moodle.example.test'}};
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
    var compbadge = '<span class="eportoflio-comment me-2">' +
        '<i class="icon icon-comment fa fa-lightbulb" aria-label="competences" data-bs-toggle="tooltip" ' +
        'data-bs-html="true" data-bs-title="&lt;ul&gt;&lt;li&gt;Competence title&lt;/li&gt;&lt;/ul&gt;"></i>' +
        '<span class="eportfolio-comment-count">1</span></span>';

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
                    competences: hasCompetences ? 'Competence title<br>' : '',
                    compbadge: hasCompetences ? compbadge : ''
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
                assert.strictEqual(block.find('.exaport-item-compbadge').html(), item.compbadge,
                    'the server-rendered card badge is reused unchanged');
                assert.strictEqual(block.find('.fa-lightbulb').length, hasCompetences ? 1 : 0,
                    'the competence tooltip remains independent');
                if (hasCompetences) {
                    assert.ok(block.find('[data-bs-toggle="tooltip"]').attr('data-bs-title').includes('Competence title'),
                        'the tooltip retains its competence text');
                    assert.strictEqual(block.find('.eportfolio-comment-count').text(), '1', 'the card count is retained');
                }
                assert.strictEqual(block.find('script').length, 0, 'rendering does not reload the tooltip library');
                assert.ok(block.find('.picture img').attr('src').endsWith('/item_thumb.php?item_id=42'),
                    'the existing thumbnail endpoint remains in use');
                assert.strictEqual(block.find('.header').text(), 'viewitem: Artifact <title>',
                    'metadata remains text');

                var saved = JSON.parse($('input[name=blocks]').val());
                assert.strictEqual(saved[0].item.contenthtml, contenthtml, 'serialization retains content');
                assert.strictEqual(saved[0].item.compbadge, item.compbadge, 'serialization retains the badge');
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

    QUnit.test('tooltips are initialized after FontAwesome has replaced the icon', function(assert) {
        var done = assert.async();
        var completeConversion;
        window.block_exaport_update_fontawesome_icons = function(block) {
            return new Promise(function(resolve) {
                completeConversion = function() {
                    block.find('.fa-lightbulb').replaceWith(
                        '<svg class="icon fa-lightbulb" data-bs-toggle="tooltip"></svg>'
                    );
                    resolve();
                };
            });
        };
        $('input[name=blocks]').val(JSON.stringify([{type: 'item', itemid: 42, item: {
            id: 42, name: 'Shared badge', type: 'note', category: '', comments: 0,
            filescount: 0, link: '', intro: '', contenthtml: '', competences: 'Competence title<br>', compbadge: compbadge
        }}]));
        Views.initialise(1);
        var block = $('.portfolioDesignBlocks > li')[0];
        var notified = false;
        var onUpdated = function(event) {
            if (!event.detail.nodes.includes(block)) {
                return;
            }
            notified = true;
            document.removeEventListener(FilterEvents.eventTypes.filterContentUpdated, onUpdated);
            assert.strictEqual($(block).find('svg.fa-lightbulb').length, 1,
                'the notification targets the converted icon');
            done();
        };
        document.addEventListener(FilterEvents.eventTypes.filterContentUpdated, onUpdated);
        assert.notOk(notified, 'tooltips wait for conversion');
        completeConversion();
    });
});
