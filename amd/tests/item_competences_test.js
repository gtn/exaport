define(['block_exaport/item_competences'], function(Competences) {
    QUnit.module('block_exaport/item_competences', {
        beforeEach: function() {
            document.getElementById('qunit-fixture').innerHTML =
                '<form><input name="competenceids" type="hidden" value="4">' +
                '<div data-region="competence-picker">' +
                '<input type="checkbox" data-region="competence-checkbox" value="4">' +
                '<input type="checkbox" data-region="competence-checkbox" value="8" checked>' +
                '<input type="checkbox" data-region="competence-checkbox" value="12" checked>' +
                '</div></form>';
        }
    });

    QUnit.test('serializes the complete checked selection into the form field', function(assert) {
        var picker = document.querySelector('[data-region="competence-picker"]');
        Competences.synchronizeSelection(picker);
        assert.strictEqual(document.querySelector('[name="competenceids"]').value, '8,12');

        picker.querySelectorAll('input').forEach(function(checkbox) {
            checkbox.checked = false;
        });
        Competences.synchronizeSelection(picker);
        assert.strictEqual(document.querySelector('[name="competenceids"]').value, '', 'an empty selection is preserved');
    });

    QUnit.test('replaces only the selected competence summary', function(assert) {
        var done = assert.async();
        var section = document.createElement('div');
        section.innerHTML = '<p>Intro</p><div data-region="competence-summary">Old</div><button>Add</button>';
        document.getElementById('qunit-fixture').appendChild(section);

        Competences.replaceSummary(section, '<div data-region="competence-summary">New</div>').then(function() {
            assert.strictEqual(section.querySelector('p').textContent, 'Intro', 'intro remains unchanged');
            assert.strictEqual(section.querySelector('button').textContent, 'Add', 'button remains unchanged');
            assert.strictEqual(section.querySelector('[data-region="competence-summary"]').textContent, 'New');
            done();
        });
    });
});
