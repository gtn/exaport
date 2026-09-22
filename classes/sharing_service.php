<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Coordinates internal sharing persistence across supported entity types.
 */
final class sharing_service {
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
        $record = $DB->get_record($config->entitytable, ['id' => $entityid, 'userid' => $USER->id], '*', MUST_EXIST);
        $record->shareall = $enabled ? $mode : 0;
        if ($config->internaccessfield !== null) {
            $record->{$config->internaccessfield} = $enabled ? 1 : 0;
        }
        $DB->update_record($config->entitytable, $record);

        block_exaport_sharing_save_direct_user_shares($config, $entityid, $userids, $notifyuserids,
            $extrafields, $alwaysnotify);

        $DB->delete_records($config->groupsharetable, [$config->idfield => $entityid]);
        if ($enabled && $mode === 2) {
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
        $transaction->allow_commit();
    }
}
