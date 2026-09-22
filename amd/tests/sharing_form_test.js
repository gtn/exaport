define(['block_exaport/sharing_form', 'core/notification', 'core/str'],
        function(SharingForm, Notification, Str) {

    QUnit.module('block_exaport/sharing_form', {
        beforeEach: function() {
            document.getElementById('qunit-fixture').innerHTML =
                '<section data-region="exaport-sharing" data-configured="1">' +
                '<input name="shareenabled" type="checkbox" checked>' +
                '<div data-region="status-enabled"></div>' +
                '<div data-region="status-disabled"></div>' +
                '<div data-region="sharing-summary"></div>' +
                '<div data-region="sharing-settings"></div>' +
                '</section>';

            this.originalGetStrings = Str.get_strings;
            this.originalConfirm = Notification.confirm;
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
            window.ExabisEportfolio = this.originalExabisEportfolio;
        }
    });

    QUnit.test('confirms each attempted transition from enabled to disabled', function(assert) {
        const confirmations = [];
        Notification.confirm = function(title, message, confirmLabel, cancelLabel, onConfirm) {
            confirmations.push(onConfirm);
        };

        SharingForm.init('views_mod');

        return Promise.resolve().then(function() {
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
});
