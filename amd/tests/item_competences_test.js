define(['jquery', 'block_exaport/item_competences', 'core_form/modalform'], function($, Competences, ModalForm) {
  QUnit.module('block_exaport/item_competences', {
    beforeEach: function () {
      document.getElementById('qunit-fixture').innerHTML =
        '<form><input name="competenceids" type="hidden" value="4">' +
        '<div data-region="competence-picker">' +
        '<input type="checkbox" data-region="competence-checkbox" value="4">' +
        '<input type="checkbox" data-region="competence-checkbox" value="8" checked>' +
        '<input type="checkbox" data-region="competence-checkbox" value="12" checked>' +
        '</div></form>';
    }
  });

  QUnit.test('serializes the complete checked selection into the form field', function (assert) {
    var picker = document.querySelector('[data-region="competence-picker"]');
    Competences.synchronizeSelection(picker);
    assert.strictEqual(document.querySelector('[name="competenceids"]').value, '8,12');

    picker.querySelectorAll('input').forEach(function (checkbox) {
      checkbox.checked = false;
    });
    Competences.synchronizeSelection(picker);
    assert.strictEqual(document.querySelector('[name="competenceids"]').value, '', 'an empty selection is preserved');
  });

  QUnit.test('replaces only the selected competence summary', function (assert) {
    var section = document.createElement('div');
    section.innerHTML = '<p>Intro</p><div data-region="competence-summary">Old</div><button>Add</button>';
    document.getElementById('qunit-fixture').appendChild(section);

    Competences.replaceSummary(section, '<div data-region="competence-summary">New</div>');

    assert.strictEqual(section.querySelector('p').textContent, 'Intro', 'intro remains unchanged');
    assert.strictEqual(section.querySelector('button').textContent, 'Add', 'button remains unchanged');
    assert.strictEqual(section.querySelector('[data-region="competence-summary"]').textContent, 'New');
  });

  QUnit.test('a closed modal cannot apply its late response over accepted competencies', function(assert) {
    var Modal = ModalForm.default || ModalForm;
    var originalShow = Modal.prototype.show;
    var originalListener = document.addEventListener;
    var roots = [];
    var modals = [];
    document.getElementById('qunit-fixture').innerHTML =
      '<form><input name="editorbaseline" value="signed-baseline">' +
      '<input name="competenceids" value="4"><section data-region="item-competences">' +
      '<div data-region="competence-summary">Accepted</div>' +
      '<button type="button" data-action="open-competence-picker">Choose</button></section></form>';
    var form = document.querySelector('#qunit-fixture form');
    var trigger = form.querySelector('button');

    // Keep the real launcher/submission handler, replacing only modal display and its server request.
    Modal.prototype.show = function() {
      var root = document.createElement('div');
      document.body.appendChild(root);
      this.modal = {getRoot: function() {
        return $(root);
      }};
      this.futureListeners.forEach(function(args) {
        root.addEventListener.apply(root, args);
      });
      roots.push(root);
      modals.push(this);
    };
    try {
      // Scope delegated listeners to this disposable form so other QUnit tests are unaffected.
      document.addEventListener = function(type, listener, options) {
        form.addEventListener(type, listener, options);
      };
      Competences.init({pending: true, itemId: 0, courseId: 1});
      document.addEventListener = originalListener;
      trigger.click();
      roots[0].remove(); // Core removes the modal when Escape closes it, even during Apply.
      trigger.click();

      var accepted = new CustomEvent(modals[1].events.FORM_SUBMITTED, {
        cancelable: true, detail: {competenceids: [8, 12],
          content: '<div data-region="competence-summary">Current accepted selection</div>'}
      });
      roots[1].dispatchEvent(accepted);
      assert.notOk(accepted.defaultPrevented, 'a live modal can still apply');
      assert.strictEqual(form.querySelector('[name="competenceids"]').value, '8,12');
      assert.strictEqual(form.dataset.formDirty, 'true');

      var delayed = new CustomEvent(modals[0].events.FORM_SUBMITTED, {
        cancelable: true, detail: {competenceids: [4],
          content: '<div data-region="competence-summary">Abandoned selection</div>'}
      });
      roots[0].dispatchEvent(delayed);
      assert.ok(delayed.defaultPrevented, 'core must not hide an already abandoned modal again');
      assert.strictEqual(form.querySelector('[name="competenceids"]').value, '8,12',
        'the delayed response cannot change the accepted selection');
      assert.strictEqual(form.querySelector('[data-region="competence-summary"]').textContent,
        'Current accepted selection', 'the preview retains the accepted selection');
    } finally {
      document.addEventListener = originalListener;
      Modal.prototype.show = originalShow;
      roots.forEach(function(root) {
        root.remove();
      });
    }
  });
});
