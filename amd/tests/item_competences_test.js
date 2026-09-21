define(['jquery', 'block_exaport/item_competences'], function($, Competences) {
    QUnit.module('block_exaport/item_competences', {
        beforeEach: function() {
            this.fixture = $('#qunit-fixture');
            this.fixture.html(
                '<div data-region="item-competences" data-itemid="42">' +
                    '<div data-region="competence-summary" data-itemid="42"><span>Old summary</span></div>' +
                    '<form>' +
                        '<input type="hidden" name="competencyids" value="" />' +
                        '<details><summary>One</summary></details>' +
                        '<details open><summary>Two</summary></details>' +
                        '<input type="checkbox" data-region="competence-checkbox" value="9" checked />' +
                        '<input type="checkbox" data-region="competence-checkbox" value="3" checked />' +
                        '<input type="checkbox" data-region="competence-checkbox" value="9" checked />' +
                        '<input type="checkbox" data-region="competence-checkbox" value="-1" checked />' +
                    '</form>' +
                '</div>'
            );
        }
    });

    QUnit.test('selection serialization normalizes duplicate and invalid checkbox values', function(assert) {
        assert.deepEqual(
            Competences.collectSelectedCompetencyIds(this.fixture[0]),
            [3, 9],
            'selected ids are unique, positive and sorted'
        );
        assert.strictEqual(
            Competences.syncSelection(this.fixture[0]),
            '3,9',
            'the hidden dynamic-form field is kept in sync'
        );
    });

    QUnit.test('empty selection serializes to an empty string', function(assert) {
        this.fixture.find('[data-region="competence-checkbox"]').prop('checked', false);

        assert.deepEqual(Competences.collectSelectedCompetencyIds(this.fixture[0]), [], 'no ids are selected');
        assert.strictEqual(Competences.syncSelection(this.fixture[0]), '', 'empty selections clear the field');
    });

    QUnit.test('expand and collapse toggle all tree branches', function(assert) {
        Competences.setTreeExpanded(this.fixture[0], false);
        assert.strictEqual(this.fixture.find('details[open]').length, 0, 'all branches collapse');

        Competences.setTreeExpanded(this.fixture[0], true);
        assert.strictEqual(this.fixture.find('details[open]').length, 2, 'all branches expand');
    });

    QUnit.test('server-rendered summaries replace the current summary node', function(assert) {
        var done = assert.async();

        Competences.replaceSummary(42,
            '<div data-region="competence-summary" data-itemid="42"><span>Updated summary</span></div>')
            .then(function() {
                assert.strictEqual(
                    $.trim($('#qunit-fixture [data-region="competence-summary"]').text()),
                    'Updated summary',
                    'the rendered summary is installed'
                );
                done();
            });
    });
});
