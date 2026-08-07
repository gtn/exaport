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
     * Entities (items, categories, views) owned by $userid that are shared in any way
     * (direct user share, group share, or shareall).
     *
     * @param int $userid
     * @return array list of stdClass rows: entity_type, id, title, courseid, cnt_shared_users,
     *               cnt_shared_groups, shareall, externaccess, comment_cnt
     */
    public static function get_my_shares(int $userid): array {
        global $DB;

        $rows = [];

        // Items.
        $items = $DB->get_records_sql(
            "SELECT i.id, i.name AS title, i.courseid, i.shareall, i.externaccess,
                    COUNT(DISTINCT ishar.userid) AS cnt_shared_users,
                    COUNT(DISTINCT igshar.groupid) AS cnt_shared_groups,
                    COUNT(DISTINCT com.id) AS comment_cnt
               FROM {block_exaportitem} i
               LEFT JOIN {block_exaportitemshar} ishar ON ishar.itemid = i.id
               LEFT JOIN {block_exaportitemgroupshar} igshar ON igshar.itemid = i.id
               LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
              WHERE i.userid = ?
           GROUP BY i.id, i.name, i.courseid, i.shareall, i.externaccess
             HAVING COUNT(DISTINCT ishar.userid) > 0
                 OR COUNT(DISTINCT igshar.groupid) > 0
                 OR i.shareall > 0
                 OR i.externaccess = 1
           ORDER BY i.name",
            [$userid]
        );
        foreach ($items as $item) {
            $item->entity_type = 'item';
            $rows[] = $item;
        }

        // Categories.
        $categories = $DB->get_records_sql(
            "SELECT c.id, c.name AS title, c.courseid, c.shareall, c.externaccess,
                    COUNT(DISTINCT cshar.userid) AS cnt_shared_users,
                    COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups,
                    0 AS comment_cnt
               FROM {block_exaportcate} c
               LEFT JOIN {block_exaportcatshar} cshar ON cshar.catid = c.id
               LEFT JOIN {block_exaportcatgroupshar} cgshar ON cgshar.catid = c.id
              WHERE c.userid = ? AND c.internshare = 1
           GROUP BY c.id, c.name, c.courseid, c.shareall, c.externaccess
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

        return $rows;
    }

    /**
     * Entities (items, categories, views) shared to $userid by others, either directly
     * or via a group/cohort the user belongs to.
     *
     * @param int $userid
     * @return array list of stdClass rows: entity_type, id, title, owner_userid, courseid, share_mode
     */
    public static function get_shared_with_me(int $userid): array {
        global $DB;

        $rows = [];
        $usergroupids = array_keys(block_exaport_get_user_cohorts($userid));

        // Items shared directly.
        $items = $DB->get_records_sql(
            "SELECT i.id, i.name AS title, i.userid AS owner_userid, i.courseid, 'user' AS share_mode
               FROM {block_exaportitemshar} ishar
               JOIN {block_exaportitem} i ON i.id = ishar.itemid
               JOIN {user} u ON u.id = i.userid
              WHERE ishar.userid = ? AND u.deleted = 0
                AND (ishar.timestart IS NULL OR ishar.timestart = 0 OR ishar.timestart <= ?)
                AND (ishar.timeend IS NULL OR ishar.timeend = 0 OR ishar.timeend >= ?)",
            [$userid, time(), time()]
        );
        foreach ($items as $item) {
            $item->entity_type = 'item';
            $rows[$item->entity_type . '_' . $item->id] = $item;
        }

        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $groupitems = $DB->get_records_sql(
                "SELECT i.id, i.name AS title, i.userid AS owner_userid, i.courseid, 'group' AS share_mode
                   FROM {block_exaportitemgroupshar} igshar
                   JOIN {block_exaportitem} i ON i.id = igshar.itemid
                   JOIN {user} u ON u.id = i.userid
                  WHERE igshar.groupid $insql AND u.deleted = 0",
                $inparams
            );
            foreach ($groupitems as $item) {
                $item->entity_type = 'item';
                $key = $item->entity_type . '_' . $item->id;
                if (!isset($rows[$key])) {
                    $rows[$key] = $item;
                }
            }
        }

        // Categories shared directly.
        $categories = $DB->get_records_sql(
            "SELECT c.id, c.name AS title, c.userid AS owner_userid, c.courseid, 'user' AS share_mode
               FROM {block_exaportcatshar} cshar
               JOIN {block_exaportcate} c ON c.id = cshar.catid
               JOIN {user} u ON u.id = c.userid
              WHERE cshar.userid = ? AND u.deleted = 0 AND c.internshare = 1",
            [$userid]
        );
        foreach ($categories as $category) {
            $category->entity_type = 'category';
            $rows[$category->entity_type . '_' . $category->id] = $category;
        }

        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $groupcategories = $DB->get_records_sql(
                "SELECT c.id, c.name AS title, c.userid AS owner_userid, c.courseid, 'group' AS share_mode
                   FROM {block_exaportcatgroupshar} cgshar
                   JOIN {block_exaportcate} c ON c.id = cgshar.catid
                   JOIN {user} u ON u.id = c.userid
                  WHERE cgshar.groupid $insql AND u.deleted = 0 AND c.internshare = 1",
                $inparams
            );
            foreach ($groupcategories as $category) {
                $category->entity_type = 'category';
                $key = $category->entity_type . '_' . $category->id;
                if (!isset($rows[$key])) {
                    $rows[$key] = $category;
                }
            }
        }

        // Views shared directly.
        $views = $DB->get_records_sql(
            "SELECT v.id, v.name AS title, v.userid AS owner_userid, 0 AS courseid, 'user' AS share_mode
               FROM {block_exaportviewshar} vshar
               JOIN {block_exaportview} v ON v.id = vshar.viewid
               JOIN {user} u ON u.id = v.userid
              WHERE vshar.userid = ? AND u.deleted = 0",
            [$userid]
        );
        foreach ($views as $view) {
            $view->entity_type = 'view';
            $rows[$view->entity_type . '_' . $view->id] = $view;
        }

        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $groupviews = $DB->get_records_sql(
                "SELECT v.id, v.name AS title, v.userid AS owner_userid, 0 AS courseid, 'group' AS share_mode
                   FROM {block_exaportviewgroupshar} vgshar
                   JOIN {block_exaportview} v ON v.id = vgshar.viewid
                   JOIN {user} u ON u.id = v.userid
                  WHERE vgshar.groupid $insql AND u.deleted = 0",
                $inparams
            );
            foreach ($groupviews as $view) {
                $view->entity_type = 'view';
                $key = $view->entity_type . '_' . $view->id;
                if (!isset($rows[$key])) {
                    $rows[$key] = $view;
                }
            }
        }

        return array_values($rows);
    }
}
