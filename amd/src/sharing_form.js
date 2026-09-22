// This file is part of Exabis Eportfolio (extension for Moodle).

define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {

const selectors = {
    root: '[data-region="exaport-sharing"]',
    master: '[name="shareenabled"]',
    settings: '[data-region="sharing-settings"]',
    external: '[name="externaccess"]',
    externalSettings: '[data-region="external-settings"]',
    internal: '[name="internaccess"], [name="internshare"]',
    internalSettings: '[data-region="internal-settings"]',
    mode: '[name="shareall"]',
    users: '[data-region="users-settings"]',
    groups: '[data-region="groups-settings"]',
    email: '[name="sharedemails"]',
    emailSettings: '[data-region="email-settings"]',
    statusOn: '[data-region="status-enabled"]',
    statusOff: '[data-region="status-disabled"]',
    summary: '[data-region="sharing-summary"]',
};

const initialiseRoot = (root, loaderType) => {
  return Str.get_strings([
        {key: 'sharing_disable_confirm', component: 'block_exaport'},
        {key: 'share_summary_all', component: 'block_exaport'},
        {key: 'share_summary_users', component: 'block_exaport'},
        {key: 'share_summary_groups', component: 'block_exaport'},
        {key: 'share_summary_external', component: 'block_exaport'},
        {key: 'share_summary_emails', component: 'block_exaport'},
        {key: 'share_summary_none', component: 'block_exaport'},
        {key: 'sharing_disable_title', component: 'block_exaport'},
        {key: 'disable'},
        {key: 'cancel'},
    ]).then(strings => {
    const $root = $(root);
    const $master = $root.find(selectors.master);
    let usersLoaded = false;
    let groupsLoaded = false;

    const update = () => {
        const enabled = !$master.length || $master.is(':checked');
        const internalEnabled = enabled && (!$root.find(selectors.internal).length || $root.find(selectors.internal).is(':checked'));
        const mode = String($root.find(`${selectors.mode}:checked`).val() ?? '0');
        const segments = [];

        $master.attr('aria-expanded', enabled ? 'true' : 'false');
        $root.toggleClass('is-disabled', !enabled);
        $root.find(selectors.settings).attr('aria-disabled', enabled ? 'false' : 'true');
        $root.find(`${selectors.settings} :input`).not($master).prop('disabled', !enabled);
        $root.find(selectors.statusOn).toggle(enabled);
        $root.find(selectors.statusOff).toggle(!enabled);

        const externalEnabled = enabled && $root.find(selectors.external).is(':checked');
        $root.find(selectors.externalSettings).toggle(externalEnabled);
        if (externalEnabled) {
            segments.push(strings[4]);
        }

        $root.find(selectors.internalSettings).toggle(internalEnabled);
        $root.find(selectors.users).toggle(internalEnabled && mode === '0');
        $root.find(selectors.groups).toggle(internalEnabled && mode === '2');
        if (internalEnabled) {
            segments.push(mode === '1' ? strings[1] : (mode === '2' ? strings[3] : strings[2]));
            if (mode === '0' && !usersLoaded) {
                usersLoaded = true;
                window.ExabisEportfolio.load_userlist(loaderType);
            } else if (mode === '2' && !groupsLoaded) {
                groupsLoaded = true;
                window.ExabisEportfolio.load_grouplist(loaderType);
            }
        }

        const emailEnabled = enabled && $root.find(selectors.email).is(':checked');
        $root.find(selectors.emailSettings).toggle(emailEnabled);
        if (emailEnabled) {
            segments.push(strings[5]);
        }
        $root.find(selectors.summary).text(enabled && segments.length ? segments.join(' · ') : strings[6]);
    };

    $root.on('change', 'input[type="checkbox"], input[type="radio"]', event => {
        if ($(event.target).is(selectors.master) && !event.target.checked &&
                root.dataset.configured === '1') {
            event.target.checked = true;
            update();
            Notification.confirm(
                strings[7],
                strings[0],
                strings[8],
                strings[9],
                () => {
                    event.target.checked = false;
                    update();
                }
            );
            return;
        }
        update();
    });
    update();
  });
};

return {
  init: function(loaderType) {
    loaderType = loaderType || '';
    document.querySelectorAll(selectors.root).forEach(root => initialiseRoot(root, loaderType));
  }
};
});
