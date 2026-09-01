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
 * Builds the unified "My shares" / "Shared with me" overviews across items, categories
 * and views. Uses the same share tables as the existing category/view/item sharing UI.
 */
class share_overview {

    /**
     * Build a type icon HTML string for the given entity type.
     *
     * Reuses block_exaport_fontawesome_icon() and block_exaport_item_icon_type_options()
     * with the same icon choices already used in view_items.php and the *_card.php classes.
     * Extracted here to be shared by my_shares_page and shared_with_me_page without duplication.
     *
     * @param string $entity_type 'item', 'category', or 'view'.
     * @param string $itemtype    Item sub-type (e.g. 'link', 'file', 'note'); ignored for non-items.
     * @return string HTML
     */
    public static function build_type_icon(string $entity_type, string $itemtype = ''): string {
        switch ($entity_type) {
            case 'item':
                $iconprops = block_exaport_item_icon_type_options($itemtype);
                return block_exaport_fontawesome_icon($iconprops['iconName'], $iconprops['iconStyle'], 1);
            case 'category':
                return block_exaport_fontawesome_icon('folder-open', 'regular', 1);
            case 'view':
                return block_exaport_fontawesome_icon('table-cells', 'solid', 1);
            default:
                return '';
        }
    }

    /**
     * Build a \block_exaport\share_info for any shareable entity type (item, category, view).
     *
     * Compatibility wrapper around the canonical share_info resolver.
     *
     * The $row must contain at minimum shareall and externaccess. For categories it must
     * also contain internshare.
     *
     * @param string    $entity_type 'item', 'category', or 'view'.
     * @param int       $id          Entity primary key.
     * @param \stdClass $row         Raw DB row (at minimum shareall, externaccess).
     * @return \block_exaport\share_info
     */
    public static function build_share_info(string $entity_type, int $id, \stdClass $row): \block_exaport\share_info {
        $row->id = $id;
        return \block_exaport\share_info::resolve($entity_type, $row);
    }

    /**
     * Entities (items, categories, views) owned by $userid that are shared in any way
     * (direct user share, group share, or shareall).
     *
     * @param int $userid
     * @return array list of stdClass rows: entity_type, id, title, type (items only),
     *               courseid, cnt_shared_users, cnt_shared_groups, shareall, externaccess,
     *               comment_cnt, internshare (categories only)
     */
    public static function get_my_shares(int $userid): array {
        global $DB;

        $rows = [];

        // Items — include the item type so we can show a per-type icon.
        $items = $DB->get_records_sql(
            "SELECT i.id, i.name AS title, i.type, i.courseid, i.shareall, i.externaccess,
                    COUNT(DISTINCT ishar.userid) AS cnt_shared_users,
                    COUNT(DISTINCT igshar.groupid) AS cnt_shared_groups,
                    COUNT(DISTINCT com.id) AS comment_cnt
               FROM {block_exaportitem} i
               LEFT JOIN {block_exaportitemshar} ishar ON ishar.itemid = i.id
                    AND (ishar.timestart IS NULL OR ishar.timestart = 0 OR ishar.timestart <= ?)
                    AND (ishar.timeend IS NULL OR ishar.timeend = 0 OR ishar.timeend >= ?)
               LEFT JOIN {block_exaportitemgroupshar} igshar ON igshar.itemid = i.id
               LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
              WHERE i.userid = ?
           GROUP BY i.id, i.name, i.type, i.courseid, i.shareall, i.externaccess
             HAVING COUNT(DISTINCT ishar.userid) > 0
                 OR COUNT(DISTINCT igshar.groupid) > 0
                 OR i.shareall > 0
                 OR i.externaccess = 1
           ORDER BY i.name",
            [time(), time(), $userid]
        );
        foreach ($items as $item) {
            $item->entity_type = 'item';
            $rows[] = $item;
        }

        // Categories — include internshare so category_helper::build_share_info() can use it.
        $categories = $DB->get_records_sql(
            "SELECT c.id, c.name AS title, c.courseid, c.shareall, c.externaccess, c.internshare,
                    COUNT(DISTINCT cshar.userid) AS cnt_shared_users,
                    COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups,
                    0 AS comment_cnt
               FROM {block_exaportcate} c
               LEFT JOIN {block_exaportcatshar} cshar ON cshar.catid = c.id
               LEFT JOIN {block_exaportcatgroupshar} cgshar ON cgshar.catid = c.id
              WHERE c.userid = ? AND c.internshare = 1
           GROUP BY c.id, c.name, c.courseid, c.shareall, c.externaccess, c.internshare
             HAVING COUNT(DISTINCT cshar.userid) > 0
                 OR COUNT(DISTINCT cgshar.groupid) > 0
                 OR c.shareall > 0
                 OR c.externaccess = 1
           ORDER BY c.name",
            [$userid]
        );
        foreach ($categories as $category) {
            $category->entity_type = 'category';
            $rows[] = $category;
        }

        // Views.
        $views = $DB->get_records_sql(
            "SELECT v.id, v.name AS title, 0 AS courseid, v.shareall, v.externaccess,
                    COUNT(DISTINCT vshar.userid) AS cnt_shared_users,
                    COUNT(DISTINCT vgshar.groupid) AS cnt_shared_groups,
                    0 AS comment_cnt
               FROM {block_exaportview} v
               LEFT JOIN {block_exaportviewshar} vshar ON vshar.viewid = v.id
               LEFT JOIN {block_exaportviewgroupshar} vgshar ON vgshar.viewid = v.id
              WHERE v.userid = ?
           GROUP BY v.id, v.name, v.shareall, v.externaccess
             HAVING COUNT(DISTINCT vshar.userid) > 0
                 OR COUNT(DISTINCT vgshar.groupid) > 0
                 OR v.shareall > 0
                 OR v.externaccess = 1
           ORDER BY v.name",
            [$userid]
        );
        foreach ($views as $view) {
            $view->entity_type = 'view';
            $rows[] = $view;
        }

        self::attach_share_info($rows);
        return $rows;
    }

    /**
     * Entities (items, categories, views) shared to $userid by others, either directly
     * or via a group/cohort the user belongs to.
     *
     * @param int $userid
     * @return array list of stdClass rows: entity_type, id, title, type (items only),
     *               owner_userid, courseid, shareinfo, comment_cnt
     */
    public static function get_shared_with_me(int $userid): array {
        global $DB;

        $rows = [];
        $usergroupids = array_keys(block_exaport_get_user_cohorts($userid));
        if ($usergroupids) {
            [$groupinsql, $groupparams] = $DB->get_in_or_equal($usergroupids, SQL_PARAMS_QM);
        } else {
            $groupinsql = '';
            $groupparams = [];
        }
        $shareallsql = block_exaport_shareall_enabled() ? '%s.shareall = 1 OR ' : '';

        $itemgroupsql = $groupinsql
            ? "EXISTS (SELECT 1 FROM {block_exaportitemgroupshar} gs
                        WHERE gs.itemid = i.id AND gs.groupid {$groupinsql})"
            : '1 = 0';
        $now = time();
        $items = $DB->get_records_sql(
            "SELECT i.id, i.name AS title, i.type, i.userid AS owner_userid, i.courseid,
                    i.shareall, i.externaccess, COUNT(DISTINCT com.id) AS comment_cnt
               FROM {block_exaportitem} i
               JOIN {user} u ON u.id = i.userid
          LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
              WHERE u.deleted = 0 AND i.userid != ? AND (" . sprintf($shareallsql, 'i') . "
                    EXISTS (SELECT 1 FROM {block_exaportitemshar} us
                             WHERE us.itemid = i.id AND us.userid = ?
                               AND (us.timestart IS NULL OR us.timestart = 0 OR us.timestart <= ?)
                               AND (us.timeend IS NULL OR us.timeend = 0 OR us.timeend >= ?))
                    OR {$itemgroupsql})
           GROUP BY i.id, i.name, i.type, i.userid, i.courseid, i.shareall, i.externaccess",
            array_merge([$userid, $userid, $now, $now], $groupparams)
        );
        foreach ($items as $item) {
            $item->entity_type = 'item';
            $rows[] = $item;
        }

        $categorygroupsql = $groupinsql
            ? "EXISTS (SELECT 1 FROM {block_exaportcatgroupshar} gs
                        WHERE gs.catid = c.id AND gs.groupid {$groupinsql})"
            : '1 = 0';
        $categories = $DB->get_records_sql(
            "SELECT c.id, c.name AS title, '' AS type, c.userid AS owner_userid, c.courseid,
                    c.shareall, c.externaccess, c.internshare, 0 AS comment_cnt
               FROM {block_exaportcate} c
               JOIN {user} u ON u.id = c.userid
              WHERE u.deleted = 0 AND c.userid != ? AND c.internshare = 1
                AND (" . sprintf($shareallsql, 'c') . "
                    EXISTS (SELECT 1 FROM {block_exaportcatshar} us
                             WHERE us.catid = c.id AND us.userid = ?)
                    OR {$categorygroupsql})",
            array_merge([$userid, $userid], $groupparams)
        );
        foreach ($categories as $category) {
            $category->entity_type = 'category';
            $rows[] = $category;
        }

        $viewgroupsql = $groupinsql
            ? "EXISTS (SELECT 1 FROM {block_exaportviewgroupshar} gs
                        WHERE gs.viewid = v.id AND gs.groupid {$groupinsql})"
            : '1 = 0';
        $views = $DB->get_records_sql(
            "SELECT v.id, v.name AS title, '' AS type, v.userid AS owner_userid,
                    0 AS courseid, v.shareall, v.externaccess, 0 AS comment_cnt
               FROM {block_exaportview} v
               JOIN {user} u ON u.id = v.userid
              WHERE u.deleted = 0 AND v.userid != ? AND (" . sprintf($shareallsql, 'v') . "
                    EXISTS (SELECT 1 FROM {block_exaportviewshar} us
                             WHERE us.viewid = v.id AND us.userid = ?)
                    OR {$viewgroupsql})",
            array_merge([$userid, $userid], $groupparams)
        );
        foreach ($views as $view) {
            $view->entity_type = 'view';
            $rows[] = $view;
        }

        self::attach_share_info($rows);
        return $rows;
    }

    /**
     * Attach canonical, batch-resolved share information to overview rows.
     *
     * @param array $rows Rows to decorate
     */
    private static function attach_share_info(array &$rows): void {
        foreach (['item', 'category', 'view'] as $entitytype) {
            $entities = array_filter($rows, function($row) use ($entitytype) {
                return $row->entity_type === $entitytype;
            });
            $resolved = \block_exaport\share_info::resolve_many($entitytype, $entities);
            foreach ($entities as $entity) {
                $entity->shareinfo = $resolved[(int)$entity->id];
            }
        }
    }
}
