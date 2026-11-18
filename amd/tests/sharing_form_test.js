define(['block_exaport/sharing_form', 'core/notification', 'core/str'],
        function(SharingForm, Notification, Str) {

    QUnit.module('block_exaport/sharing_form', {
        beforeEach: function() {
            document.getElementById('qunit-fixture').innerHTML =
                '<form><section data-region="exaport-sharing" data-configured="1">' +
                '<input type="hidden" name="exaport_grouplist_ready" value="0">' +
                '<input name="shareenabled" type="checkbox" checked>' +
                '<div data-region="sharing-status">' +
                '<span data-region="status-enabled"></span>' +
                '<span data-region="status-disabled"></span>' +
                '<span data-region="sharing-summary"></span>' +
                '</div>' +
                '<div data-region="sharing-settings"></div>' +
                '</section><button type="submit" name="cancel">Cancel</button></form>';

            this.originalGetStrings = Str.get_strings;
            this.originalConfirm = Notification.confirm;
            this.originalException = Notification.exception;
            this.originalExabisEportfolio = window.ExabisEportfolio;
            Str.get_strings = () => Promise.resolve([
                'Disable sharing?',
                'All users',
                'Selected users',
                'Selected groups',
                'External link',
                'Email recipients',
                'Not shared',
                'Disable sharing',
                'Disable',
                'Cancel',
                'Wait for the group list and retry',
            ]);
            window.ExabisEportfolio = {
                load_userlist: function() {},
                load_grouplist: function() {},
            };
        },

        afterEach: function() {
            Str.get_strings = this.originalGetStrings;
            Notification.confirm = this.originalConfirm;
            Notification.exception = this.originalException;
            window.ExabisEportfolio = this.originalExabisEportfolio;
        }
    });

    QUnit.test('only an active unknown group selection blocks Save and Cancel remains available', function(assert) {
        const root = document.querySelector('[data-region="exaport-sharing"]');
        root.dataset.configured = '0';
        root.querySelector('[data-region="sharing-settings"]').innerHTML =
            '<input type="checkbox" name="internshare" checked>' +
            '<input type="radio" name="shareall" value="0">' +
            '<input type="radio" name="shareall" value="1">' +
            '<input type="radio" name="shareall" value="2" checked>' +
            '<div data-region="groups-settings">' +
            '<div data-region="submitted-group-selection"><input type="hidden" name="sharegroups[22]" value="22"></div>' +
            '</div>';
        const form = root.closest('form');
        // Moodle's document listener marks even prevented submissions as submitted.
        const markSubmitted = event => {
            if (event.target === form) {
                form.dataset.formSubmitted = 'true';
            }
        };
        document.addEventListener('submit', markSubmitted);
        const submit = submitter => {
            form.dataset.formSubmitted = 'false';
            form.dataset.formDirty = 'true';
            const event = new SubmitEvent('submit', {bubbles: true, cancelable: true, submitter});
            form.dispatchEvent(event);
            assert.strictEqual(form.dataset.formSubmitted, event.defaultPrevented ? 'false' : 'true',
                'only an allowed submission reaches Moodle\'s submitted marker');
            if (event.defaultPrevented) {
                assert.strictEqual(form.dataset.formDirty, 'true', 'blocked Save keeps the unsaved work dirty');
            }
            return event.defaultPrevented;
        };

        return SharingForm.init('cat_mod').then(function() {
            assert.true(submit(), 'unknown active groups cannot silently submit an empty audience');
            assert.strictEqual(root.querySelectorAll('.exaport-grouplist-incomplete').length, 1);
            assert.false(submit(form.querySelector('[name="cancel"]')), 'Cancel bypasses loading completeness');
            root.querySelector('[name="exaport_grouplist_ready"]').value = '1';
            assert.false(submit(), 'a known submitted selection can Save while its roster loads');

            root.querySelector('[name="exaport_grouplist_ready"]').value = '0';
            root.querySelector('[name="shareall"][value="1"]').checked = true;
            assert.false(submit(), 'everyone mode does not need a loaded group selection');
            root.querySelector('[name="shareall"][value="0"]').checked = true;
            assert.false(submit(), 'user mode does not need a loaded group selection');
            root.querySelector('[name="shareall"][value="2"]').checked = true;
            root.querySelector('[name="internshare"]').checked = false;
            assert.false(submit(), 'disabled internal sharing does not wait for groups');
            root.querySelector('[name="internshare"]').checked = true;
            const master = root.querySelector('[name="shareenabled"]');
            master.checked = false;
            master.dispatchEvent(new Event('change', {bubbles: true}));
            assert.false(submit(), 'sharing off can still Save');
            assert.false(new FormData(form).has('sharegroups[22]'), 'sharing off disables the fallback with the settings');
        }).finally(function() {
            document.removeEventListener('submit', markSubmitted);
        });
    });

    QUnit.test('confirms each attempted transition from enabled to disabled', function(assert) {
        const confirmations = [];
        Notification.confirm = function(title, message, confirmLabel, cancelLabel, onConfirm) {
            confirmations.push(onConfirm);
        };

        return SharingForm.init('views_mod').then(function() {
            const master = document.querySelector('[name="shareenabled"]');

            master.checked = false;
            master.dispatchEvent(new Event('change', {bubbles: true}));
            assert.strictEqual(confirmations.length, 1, 'the first disable attempt requests confirmation');

            confirmations.shift()();
            assert.false(master.checked, 'confirming applies the first disable transition');

            master.checked = true;
            master.dispatchEvent(new Event('change', {bubbles: true}));
            master.checked = false;
            master.dispatchEvent(new Event('change', {bubbles: true}));

            assert.strictEqual(confirmations.length, 1,
                'disabling again after re-enabling requests a new confirmation');
            assert.true(master.checked, 'the second disable waits for its own confirmation');
        });
    });

    QUnit.test('reports string loading failures and leaves a usable fallback form', function(assert) {
        const failure = new Error('String loading failed');
        let reportedFailure = null;
        Str.get_strings = () => Promise.reject(failure);
        Notification.exception = function(error) {
            reportedFailure = error;
        };

        const initialisation = SharingForm.init('views_mod');
        assert.ok(initialisation instanceof Promise, 'init returns the aggregate initialisation promise');

        return initialisation.then(function() {
            const root = document.querySelector('[data-region="exaport-sharing"]');
            const settings = root.querySelector('[data-region="sharing-settings"]');

            assert.strictEqual(reportedFailure, failure, 'the initialisation failure is reported');
            assert.true(root.classList.contains('is-unavailable'), 'the fallback state is identifiable');
            assert.strictEqual(root.dataset.initializationFailed, '1', 'the failed state is exposed to the DOM');
            assert.notStrictEqual(settings.style.display, 'none', 'the server-rendered settings remain visible');
            assert.strictEqual(settings.getAttribute('aria-disabled'), 'false',
                'the fallback does not describe the settings as disabled');
            assert.strictEqual(root.querySelector('[data-region="sharing-status"]').style.display, 'none',
                'the JavaScript-only status is hidden when it cannot be initialized');
        });
    });
});
