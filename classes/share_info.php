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
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolved sharing detail for a shareable entity (item, category, or view).
 */
class share_info {
    /** @var string[] Fullnames of internally shared users. */
    public array $users = [];
    /** @var string[] Group/cohort names it is internally shared with. */
    public array $groups = [];
    /** @var bool Shared with everyone (internal shareall). */
    public bool $all = false;
    /** @var bool Shared externally via a public URL. */
    public bool $external = false;

    /**
     * Whether the entity is shared in any way.
     *
     * @return bool
     */
    public function is_shared(): bool {
        return (bool)($this->users || $this->groups || $this->all || $this->external);
    }

    /**
     * Resolve sharing details for multiple entities of one type.
     *
     * @param string $entitytype item, category, or view
     * @param array $entities Entity records containing at least id, shareall, and externaccess
     * @return share_info[] Share details keyed by entity id
     */
    public static function resolve_many(string $entitytype, array $entities): array {
        global $DB;

        $tables = [
            'item' => ['block_exaportitemshar', 'block_exaportitemgroupshar', 'itemid'],
            'category' => ['block_exaportcatshar', 'block_exaportcatgroupshar', 'catid'],
            'view' => ['block_exaportviewshar', 'block_exaportviewgroupshar', 'viewid'],
        ];
        if (!isset($tables[$entitytype])) {
            throw new \coding_exception('Unsupported share entity type: ' . $entitytype);
        }

        $resolved = [];
        foreach ($entities as $entity) {
            $share = new self();
            $internal = $entitytype !== 'category' || !empty($entity->internshare);
            $share->all = $internal && (int)($entity->shareall ?? 0) === 1
                && block_exaport_shareall_enabled();
            $share->external = !empty($entity->externaccess) && block_exaport_externaccess_enabled();
            $resolved[(int)$entity->id] = $share;
        }
        if (!$resolved) {
            return [];
        }

        [$usertable, $grouptable, $idcolumn] = $tables[$entitytype];
        $detailids = [];
        foreach ($entities as $entity) {
            $id = (int)$entity->id;
            $internal = $entitytype !== 'category' || !empty($entity->internshare);
            if ($internal && !$resolved[$id]->all) {
                $detailids[] = $id;
            }
        }
        if (!$detailids) {
            return $resolved;
        }

        $now = time();
        foreach (array_chunk(array_unique($detailids), 1000) as $idchunk) {
            [$insql, $params] = $DB->get_in_or_equal($idchunk, SQL_PARAMS_QM);
            $timecondition = $entitytype === 'item'
                ? ' AND (s.timestart IS NULL OR s.timestart = 0 OR s.timestart <= ?)
                    AND (s.timeend IS NULL OR s.timeend = 0 OR s.timeend >= ?)'
                : '';
            $userparams = $entitytype === 'item' ? array_merge($params, [$now, $now]) : $params;
            $users = $DB->get_records_sql(
                "SELECT s.id, s.{$idcolumn} AS entityid, " . $DB->sql_fullname() . " AS name
                   FROM {{$usertable}} s
                   JOIN {user} u ON u.id = s.userid
                  WHERE s.{$idcolumn} {$insql} AND u.deleted = 0 {$timecondition}
               ORDER BY name",
                $userparams
            );
            foreach ($users as $user) {
                $resolved[(int)$user->entityid]->users[] = $user->name;
            }

            $groups = $DB->get_records_sql(
                "SELECT s.id, s.{$idcolumn} AS entityid, c.name
                   FROM {{$grouptable}} s
                   JOIN {cohort} c ON c.id = s.groupid
                  WHERE s.{$idcolumn} {$insql}
               ORDER BY c.name",
                $params
            );
            foreach ($groups as $group) {
                $resolved[(int)$group->entityid]->groups[] = $group->name;
            }
        }

        return $resolved;
    }

    /**
     * Resolve sharing details for one entity.
     *
     * @param string $entitytype item, category, or view
     * @param \stdClass $entity Entity record
     * @return share_info
     */
    public static function resolve(string $entitytype, \stdClass $entity): share_info {
        return self::resolve_many($entitytype, [$entity])[(int)$entity->id];
    }
}
