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
});
