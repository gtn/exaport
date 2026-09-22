define(['block_exaport/sharing_form', 'core/notification', 'core/str'],
        function(SharingForm, Notification, Str) {

    QUnit.module('block_exaport/sharing_form', {
        beforeEach: function() {
            document.getElementById('qunit-fixture').innerHTML =
                '<section data-region="exaport-sharing" data-configured="1">' +
                '<input name="shareenabled" type="checkbox" checked>' +
                '<div data-region="sharing-status">' +
                '<span data-region="status-enabled"></span>' +
                '<span data-region="status-disabled"></span>' +
                '<span data-region="sharing-summary"></span>' +
                '</div>' +
                '<div data-region="sharing-settings"></div>' +
                '</section>';

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
