<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Coordinates internal sharing persistence across supported entity types.
 */
final class sharing_service {
    /**
     * Permanently remove one audience without changing other sharing channels or policy flags.
     *
     * @param string $audience everyone or groups.
     */
    public static function remove_all_shares(string $audience): void {
        global $DB;

        require_capability('moodle/site:config', \context_system::instance());
        if (!in_array($audience, ['everyone', 'groups'], true)) {
            throw new \coding_exception('Unsupported sharing audience');
        }
        $transaction = $DB->start_delegated_transaction();
        foreach (['item', 'category', 'view'] as $type) {
            $config = block_exaport_get_sharing_entity_config($type);
            if ($audience === 'groups') {
                // Remove the relationships too, so re-enabling the policy cannot revive them.
                $DB->delete_records($config->groupsharetable);
            }
            // The same column also stores groups (2) and individual users (0).
            $DB->set_field($config->entitytable, 'shareall', 0, ['shareall' => $audience === 'groups' ? 2 : 1]);
        }
        $transaction->allow_commit();
    }

    /**
     * Apply the view sharing master switch and feature flags to submitted channel state.
     *
     * The master switch is authoritative: when it is off, subordinate values are ignored even
     * if a forged request submits them as enabled. This method does not persist the view.
     *
     * @param \stdClass $state Submitted view sharing state.
     * @param bool $shareenabled Master sharing state.
     * @return \stdClass Normalized state safe for persistence.
     */
    public static function normalize_view_channels(\stdClass $state, bool $shareenabled): \stdClass {
        if (!$shareenabled) {
            $state->externaccess = 0;
            $state->internaccess = 0;
            $state->shareall = 0;
            $state->externcomment = 0;
            $state->sharedemails = 0;
            return $state;
        }

        $state->externaccess = block_exaport_externaccess_enabled() && !empty($state->externaccess) ? 1 : 0;
        $state->internaccess = !empty($state->internaccess) ? 1 : 0;
        $mode = (int)($state->shareall ?? 0);
        $state->shareall = $state->internaccess && in_array($mode, [0, 1, 2], true) ? $mode : 0;
        // Everyone and groups have separate feature flags. Keep disabled group mode for the
        // persistence service to preserve an existing audience without accepting new groups.
        if ($state->shareall === 1 && !block_exaport_shareall_enabled()) {
            $state->shareall = 0;
        }
        $state->externcomment = !empty($state->externcomment) ? 1 : 0;
        $state->sharedemails = block_exaport_shareemails_enabled() && !empty($state->sharedemails) ? 1 : 0;
        return $state;
    }

    /**
     * Save a view's normalized sharing settings and recipients together.
     *
     * Turning off sharing or the email channel revokes the grants. When the site hides the
     * email channel, or the email flag is absent from the settings, the saved email
     * configuration is preserved while the master switch is on.
     *
     * @param \stdClass $view Owned view with normalized sharing flags.
     * @param bool $shareenabled Master sharing state.
     * @param string|null $recipientlist Submitted addresses, or null if the field was omitted.
     * @param int[] $userids Submitted direct recipients.
     * @param int[] $notifyuserids Submitted notification recipients.
     * @param int[] $groupids Submitted cohort recipients.
     * @return array<string, string> Invitation hashes keyed by newly added email address.
     */
    public static function save_view_sharing_settings(\stdClass $view, bool $shareenabled, ?string $recipientlist,
            array $userids = [], array $notifyuserids = [], array $groupids = []): array {
        global $DB, $USER;

        $view = clone $view;
        unset($view->userid);
        $emailavailable = block_exaport_shareemails_enabled();
        $emails = null;
        if (!$shareenabled || ($emailavailable && isset($view->sharedemails) && !$view->sharedemails)) {
            $view->sharedemails = 0;
            $emails = [];
        } else if (!$emailavailable || !isset($view->sharedemails)) {
            unset($view->sharedemails);
        } else if ($recipientlist !== null) {
            $parts = preg_split('/[;,\s]+/', $recipientlist, -1, PREG_SPLIT_NO_EMPTY);
            $emails = array_unique(array_filter(array_map('trim', $parts), static fn(string $email): bool =>
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
        }

        $lock = \core\lock\lock_config::get_lock_factory('block_exaport')->get_lock('viewemailshares:' . $view->id, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $savedview = $DB->get_record('block_exaportview', ['id' => $view->id, 'userid' => $USER->id],
                    'id, sharedemails', MUST_EXIST);
                $conditions = ['viewid' => $view->id];
                // Versions before permanent revocation could leave recipient grants on disabled views.
                $enablingemail = !empty($view->sharedemails) && $savedview->sharedemails != 1;
                $DB->update_record('block_exaportview', $view);
                self::save_internal_shares('view', (int)$view->id, $shareenabled && !empty($view->internaccess),
                    (int)($view->shareall ?? 0), $userids, $notifyuserids, $groupids);
                $invitations = [];
                if ($enablingemail || $emails === []) {
                    $DB->delete_records('block_exaportviewemailshar', $conditions);
                }
                if ($emails) {
                    $saved = $enablingemail ? [] :
                        $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'id, email');
                    $removed = array_keys(array_diff($saved, $emails));
                    foreach (array_chunk($removed, 1000) as $ids) {
                        $DB->delete_records_list('block_exaportviewemailshar', 'id', $ids);
                    }
                    foreach (array_diff($emails, $saved) as $email) {
                        $hash = block_exaport_securephrase_viewemail($view, $email);
                        $DB->insert_record('block_exaportviewemailshar', (object)[
                            'viewid' => $view->id,
                            'email' => $email,
                            'hash' => $hash,
                        ]);
                        $invitations[$email] = $hash;
                    }
                }
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
            $transaction->allow_commit();
            return $invitations;
        } finally {
            $lock->release();
        }
    }

    /**
     * Save the audience for an entity owned by the current user.
     *
     * Disabling sharing deliberately removes recipient assignments. This matches the historic
     * behaviour and makes the master switch fail closed even when forged subordinate values are posted.
     *
     * @param string $entitytype view, category or item.
     * @param int $entityid Entity id.
     * @param bool $enabled Whether internal sharing is enabled.
     * @param int $mode 0 users, 1 everyone, 2 groups.
     * @param int[] $userids Submitted direct recipients.
     * @param int[] $notifyuserids Submitted notification recipients.
     * @param int[] $groupids Submitted cohort ids.
     * @param array $extrafields Extra fields inserted into direct-share rows.
     * @param bool $alwaysnotify Force notification for selected recipients.
     */
    public static function save_internal_shares(string $entitytype, int $entityid, bool $enabled, int $mode,
            array $userids = [], array $notifyuserids = [], array $groupids = [], array $extrafields = [],
            bool $alwaysnotify = false): void {
        global $DB, $USER;

        $config = block_exaport_get_sharing_entity_config($entitytype);
        if (block_exaport_sharing_owned_entity_id($config, $entityid) !== $entityid) {
            throw new \moodle_exception($config->notfoundstring, 'block_exaport');
        }

        $mode = in_array($mode, [0, 1, 2], true) ? $mode : 0;
        $preservegroups = false;
        if ($enabled && $mode === 2 && !block_exaport_sharegroups_enabled()) {
            // A policy toggle must not erase saved audiences or let a stale/forged form replace
            // them. Only an explicit switch to another audience or sharing-off removes them.
            $preservegroups = $DB->record_exists($config->groupsharetable, [$config->idfield => $entityid]);
            if (!$preservegroups) {
                $enabled = false;
            }
        }
        if (!$enabled) {
            $mode = 0;
            $userids = [];
            $notifyuserids = [];
            $groupids = [];
        } else if ($mode !== 0) {
            $userids = [];
            $notifyuserids = [];
        }
        if ($mode !== 2) {
            $groupids = [];
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $record = $DB->get_record($config->entitytable, ['id' => $entityid, 'userid' => $USER->id], 'id', MUST_EXIST);
            $record->shareall = $enabled ? $mode : 0;
            if ($config->internaccessfield !== null) {
                $record->{$config->internaccessfield} = $enabled ? 1 : 0;
            }
            $DB->update_record($config->entitytable, $record);

            block_exaport_sharing_save_direct_user_shares($config, $entityid, $userids, $notifyuserids,
                $extrafields, $alwaysnotify);

            if (!$preservegroups) {
                $DB->delete_records($config->groupsharetable, [$config->idfield => $entityid]);
            }
            if ($enabled && $mode === 2 && block_exaport_sharegroups_enabled()) {
                $allowedgroups = block_exaport_get_user_cohorts();
                foreach (array_unique(array_map('intval', $groupids)) as $groupid) {
                    if (!isset($allowedgroups[$groupid])) {
                        continue;
                    }
                    $DB->insert_record($config->groupsharetable, (object)[
                        $config->idfield => $entityid,
                        'groupid' => $groupid,
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }
        $transaction->allow_commit();
    }
}
