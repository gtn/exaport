<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Persists the common internal-sharing state of portfolio entities.
 *
 * Keeping this operation independent of request parameters makes the semantics shared by the
 * three edit forms explicit and, importantly, gives callers one ownership boundary.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_service {

    /** Direct-user sharing mode. */
    public const MODE_USERS = 0;
    /** Everybody sharing mode. */
    public const MODE_EVERYONE = 1;
    /** Cohort sharing mode. */
    public const MODE_GROUPS = 2;

    /**
     * Save all internal shares for an entity.
     *
     * @return bool true when the entity belonged to the current user and was saved
     */
    public static function save_internal_shares(string $entitytype, int $entityid, bool $enabled,
            int $mode, array $userids = [], array $groupids = [], array $notifyuserids = [],
            bool $forcenotify = false): bool {
        global $DB, $USER;

        require_once(__DIR__ . '/../lib/sharelib.php');
        $config = block_exaport_get_sharing_entity_config($entitytype);
        $entity = $DB->get_record($config->entitytable, ['id' => $entityid, 'userid' => $USER->id]);
        if (!$entity) {
            return false;
        }

        if (!$enabled || !in_array($mode, [self::MODE_USERS, self::MODE_EVERYONE, self::MODE_GROUPS], true)) {
            $enabled = false;
            $mode = self::MODE_USERS;
        }

        $update = (object)['id' => $entityid, 'shareall' => $enabled ? $mode : 0];
        if ($config->internaccessfield !== null) {
            $update->{$config->internaccessfield} = $enabled ? 1 : 0;
        }
        $DB->update_record($config->entitytable, $update);

        $selectedusers = $enabled && $mode === self::MODE_USERS ? $userids : [];
        $extra = $entitytype === 'item'
            ? ['original' => 0, 'courseid' => (int)$entity->courseid]
            : [];
        block_exaport_sharing_save_direct_user_shares($config, $entityid, $selectedusers,
            $notifyuserids, $extra, $forcenotify);

        $DB->delete_records($config->groupsharetable, [$config->idfield => $entityid]);
        if ($enabled && $mode === self::MODE_GROUPS) {
            $allowedgroups = block_exaport_get_user_cohorts($USER->id);
            foreach (array_unique(array_map('intval', $groupids)) as $groupid) {
                if ($groupid > 0 && isset($allowedgroups[$groupid])) {
                    $DB->insert_record($config->groupsharetable, (object)[
                        $config->idfield => $entityid,
                        'groupid' => $groupid,
                    ]);
                }
            }
        }

        return true;
    }
}
