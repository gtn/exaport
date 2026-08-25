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
     * For categories the method delegates to category_helper::build_share_info() to avoid
     * duplication. For items and views it queries the respective share tables using the same
     * patterns as exaport_get_view_shared_users() / exaport_get_category_shared_users() in
     * lib/sharelib.php.
     *
     * The $row must contain at minimum: shareall, externaccess.
     * For categories it must also contain id, internshare (passed through to category_helper).
     *
     * @param string    $entity_type 'item', 'category', or 'view'.
     * @param int       $id          Entity primary key.
     * @param \stdClass $row         Raw DB row (at minimum shareall, externaccess).
     * @return \block_exaport\share_info
     */
    public static function build_share_info(string $entity_type, int $id, \stdClass $row): \block_exaport\share_info {
        global $DB;

        if ($entity_type === 'category') {
            // Delegate to the existing helper which already handles cohort resolution.
            return \block_exaport\category_helper::build_share_info($row);
        }

        $share = new \block_exaport\share_info();
        $share->external = !empty($row->externaccess);

        if (!empty($row->shareall)) {
            $share->all = true;
            return $share;
        }

        // Table names depend on entity type.
        if ($entity_type === 'item') {
            $usertable  = 'block_exaportitemshar';
            $grouptable = 'block_exaportitemgroupshar';
            $idcol      = 'itemid';
        } else {
            // view
            $usertable  = 'block_exaportviewshar';
            $grouptable = 'block_exaportviewgroupshar';
            $idcol      = 'viewid';
        }

        // Resolve shared user full-names (same pattern as exaport_get_view_shared_users()).
        $userids = $DB->get_fieldset_select($usertable, 'userid', "$idcol = ?", [$id]);
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_sql(
                "SELECT " . $DB->sql_fullname() . " AS name FROM {user} u"
                . " WHERE u.id $insql AND u.deleted = 0 ORDER BY name",
                $inparams
            );
            foreach ($users as $u) {
                $share->users[] = $u->name;
            }
        }

        // Resolve shared cohort names (same pattern as category_helper::build_share_info()).
        // Note: groupid stores cohort ids ({cohort}.id), mirroring the category share tables.
        $groupids = $DB->get_fieldset_select($grouptable, 'groupid', "$idcol = ?", [$id]);
        if ($groupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($groupids);
            $groups = $DB->get_records_sql(
                "SELECT c.name FROM {cohort} c WHERE c.id $insql ORDER BY c.name",
                $inparams
            );
            foreach ($groups as $g) {
                $share->groups[] = $g->name;
            }
        }

        return $share;
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
               LEFT JOIN {block_exaportitemgroupshar} igshar ON igshar.itemid = i.id
               LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
              WHERE i.userid = ?
           GROUP BY i.id, i.name, i.type, i.courseid, i.shareall, i.externaccess
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

        return $rows;
    }

    /**
     * Entities (items, categories, views) shared to $userid by others, either directly
     * or via a group/cohort the user belongs to.
     *
     * @param int $userid
     * @return array list of stdClass rows: entity_type, id, title, type (items only),
     *               owner_userid, courseid, share_mode, comment_cnt
     */
    public static function get_shared_with_me(int $userid): array {
        global $DB;

        $rows = [];
        $usergroupids = array_keys(block_exaport_get_user_cohorts($userid));

        // Items shared directly — include type for the type icon and comment count.
        $items = $DB->get_records_sql(
            "SELECT i.id, i.name AS title, i.type, i.userid AS owner_userid, i.courseid,
                    'user' AS share_mode,
                    COUNT(DISTINCT com.id) AS comment_cnt
               FROM {block_exaportitemshar} ishar
               JOIN {block_exaportitem} i ON i.id = ishar.itemid
               JOIN {user} u ON u.id = i.userid
               LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
              WHERE ishar.userid = ? AND u.deleted = 0
                AND (ishar.timestart IS NULL OR ishar.timestart = 0 OR ishar.timestart <= ?)
                AND (ishar.timeend IS NULL OR ishar.timeend = 0 OR ishar.timeend >= ?)
           GROUP BY i.id, i.name, i.type, i.userid, i.courseid",
            [$userid, time(), time()]
        );
        foreach ($items as $item) {
            $item->entity_type = 'item';
            $rows[$item->entity_type . '_' . $item->id] = $item;
        }

        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $groupitems = $DB->get_records_sql(
                "SELECT i.id, i.name AS title, i.type, i.userid AS owner_userid, i.courseid,
                        'group' AS share_mode,
                        COUNT(DISTINCT com.id) AS comment_cnt
                   FROM {block_exaportitemgroupshar} igshar
                   JOIN {block_exaportitem} i ON i.id = igshar.itemid
                   JOIN {user} u ON u.id = i.userid
                   LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
                  WHERE igshar.groupid $insql AND u.deleted = 0
               GROUP BY i.id, i.name, i.type, i.userid, i.courseid",
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
            "SELECT c.id, c.name AS title, '' AS type, c.userid AS owner_userid, c.courseid,
                    'user' AS share_mode, 0 AS comment_cnt
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
                "SELECT c.id, c.name AS title, '' AS type, c.userid AS owner_userid, c.courseid,
                        'group' AS share_mode, 0 AS comment_cnt
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
            "SELECT v.id, v.name AS title, '' AS type, v.userid AS owner_userid,
                    0 AS courseid, 'user' AS share_mode, 0 AS comment_cnt
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
                "SELECT v.id, v.name AS title, '' AS type, v.userid AS owner_userid,
                        0 AS courseid, 'group' AS share_mode, 0 AS comment_cnt
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
