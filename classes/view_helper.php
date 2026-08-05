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
 * Helper for loading and decorating view records for display on view_items.php.
 *
 * Mirrors category_helper patterns so views can be integrated into the same
 * card grid and flat-filter AMD module as items.
 */
class view_helper {

    /**
     * Build a safe ORDER BY clause for the views query (table alias v).
     *
     * @param string $sortkey  Sort column key (e.g. 'name', 'date').
     * @param string $sortdir  Sort direction, 'asc' or 'desc'.
     * @return string SQL ORDER BY fragment including the leading " ORDER BY ".
     */
    private static function view_sort_sql(string $sortkey, string $sortdir): string {
        $dir = (strtolower($sortdir) === 'desc') ? 'DESC' : 'ASC';

        if ($sortkey === 'name') {
            $column = 'v.name';
        } else {
            // 'date' and any unknown key → timemodified.
            $column = 'v.timemodified';
        }

        return ' ORDER BY ' . $column . ' ' . $dir;
    }

    /**
     * Load all views for a user in flat mode and attach flatcategories.
     *
     * Mirrors category_helper::load_flat_items().
     *
     * @param int    $userid     The user whose views to load.
     * @param array  $categories All categories keyed by id (for path name resolution).
     * @param string $sortkey    Sort column key (e.g. 'name', 'date').
     * @param string $sortdir    Sort direction, 'asc' or 'desc'.
     * @param array|null $allowedcategoryids Optional list of allowed category ids for filtering.
     * @return array View records keyed by id, each with ->flatcategories and ->entrytype.
     */
    public static function load_flat_views(int $userid, array $categories, string $sortkey, string $sortdir,
                                           ?array $allowedcategoryids = null): array {
        global $DB;

        $views = $DB->get_records_sql(
            "SELECT v.* FROM {block_exaportview} v WHERE v.userid = ? " . self::view_sort_sql($sortkey, $sortdir),
            [$userid]
        );

        if (!$views) {
            return [];
        }

        self::attach_categories($views, $categories);
        if ($allowedcategoryids !== null) {
            $allowedcategoryids = array_map('intval', $allowedcategoryids);
            foreach ($views as $id => $view) {
                $matchingcategories = array_filter($view->flatcategories, function($category) use ($allowedcategoryids) {
                    return in_array((int)$category->id, $allowedcategoryids);
                });
                if (!$matchingcategories) {
                    unset($views[$id]);
                    continue;
                }
                $view->flatcategories = array_values($matchingcategories);
            }
        }
        self::attach_share_info($views);

        return $views;
    }

    /**
     * Load views for a specific category (folder mode).
     *
     * Returns only views that are assigned to the given category via block_exaportviewcate,
     * or all uncategorised views when $categoryid is 0 (root).
     *
     * @param int    $userid     The user whose views to load.
     * @param int    $categoryid The active category id (0 = root = uncategorised views only).
     * @param array  $categories All categories keyed by id (for path name resolution).
     * @param string $sortkey    Sort column key (e.g. 'name', 'date').
     * @param string $sortdir    Sort direction, 'asc' or 'desc'.
     * @return array View records with ->flatcategories and ->entrytype.
     */
    public static function load_owner_category_views(int $userid, int $categoryid, array $categories, string $sortkey, string $sortdir): array {
        global $DB;

        $ordersql = self::view_sort_sql($sortkey, $sortdir);

        if ($categoryid > 0) {
            // Views explicitly assigned to this category.
            $views = $DB->get_records_sql(
                "SELECT v.*
                   FROM {block_exaportview} v
                   JOIN {block_exaportviewcate} vc ON vc.viewid = v.id AND vc.cateid = ?
                  WHERE v.userid = ? " . $ordersql,
                [$categoryid, $userid]
            );
        } else {
            // Root/uncategorised: views that have no category assignment at all.
            $views = $DB->get_records_sql(
                "SELECT v.*
                   FROM {block_exaportview} v
                  WHERE v.userid = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM {block_exaportviewcate} vc WHERE vc.viewid = v.id
                    ) " . $ordersql,
                [$userid]
            );
        }

        if (!$views) {
            return [];
        }

        self::attach_categories($views, $categories);
        self::attach_share_info($views);

        return $views;
    }

    /**
     * Attach flatcategories (array of category objects) to each view.
     *
     * Uses the same shape as items so the existing badge/filter code works unchanged.
     *
     * @param array $views      Associative array of view objects keyed by id (modified in place).
     * @param array $categories All categories keyed by id.
     */
    private static function attach_categories(array &$views, array $categories): void {
        global $DB;

        if (!$views) {
            return;
        }

        $viewids = array_keys($views);
        [$insql, $inparams] = $DB->get_in_or_equal($viewids, SQL_PARAMS_QM);

        $viewcaterows = $DB->get_records_sql(
            "SELECT vc.id AS vcid, vc.viewid, c.id, c.name, c.pid
               FROM {block_exaportviewcate} vc
               JOIN {block_exaportcate} c ON c.id = vc.cateid
              WHERE vc.viewid $insql
              ORDER BY c.name ASC",
            $inparams
        );

        $catesbyview = [];
        foreach ($viewcaterows as $row) {
            $row->name = category_helper::full_path_name($row->id, $categories);
            if (!isset($catesbyview[$row->viewid])) {
                $catesbyview[$row->viewid] = [];
            }
            $catesbyview[$row->viewid][] = $row;
        }

        foreach ($views as $viewid => $view) {
            $view->flatcategories = $catesbyview[$viewid] ?? [];
            $view->entrytype = 'view';
        }
    }

    /**
     * Attach share info to each view as a \block_exaport\share_info object.
     *
     * Decorates each view with:
     *   ->shareinfo  \block_exaport\share_info object with resolved sharing detail.
     *
     * @param array $views Associative array of view objects keyed by id (modified in place).
     */
    private static function attach_share_info(array &$views): void {
        global $DB;

        foreach ($views as $view) {
            $share = new \block_exaport\share_info();

            // shareall == 1 means "shared with all users" (short-circuit: skip detail).
            // Groups (cohorts) and users are stored separately and can both be set at the
            // same time; populate them independently, mirroring category_helper.
            // Note: block_exaportviewgroupshar.groupid stores cohort ids ({cohort}.id),
            // not course-group ids ({groups}.id), despite the misleading column name.
            if ($view->shareall == 1 && block_exaport_shareall_enabled()) {
                $share->all = true;
            } else {
                $groups = $DB->get_records_sql(
                    "SELECT c.name
                       FROM {cohort} c
                       JOIN {block_exaportviewgroupshar} vshar ON c.id = vshar.groupid AND vshar.viewid = ?
                      ORDER BY c.name",
                    [$view->id]
                );
                foreach ($groups as $g) {
                    $share->groups[] = $g->name;
                }

                $users = $DB->get_records_sql(
                    "SELECT " . $DB->sql_fullname() . " AS name
                       FROM {user} u
                       JOIN {block_exaportviewshar} vshar ON u.id = vshar.userid AND vshar.viewid = ?
                      WHERE u.deleted = 0
                      ORDER BY name",
                    [$view->id]
                );
                foreach ($users as $u) {
                    $share->users[] = $u->name;
                }
            }

            $share->external = (bool)(block_exaport_externaccess_enabled() && $view->externaccess);

            $view->shareinfo = $share;
        }
    }
}
