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
     * Load all views for a user in flat mode and attach flatcategories.
     *
     * Mirrors category_helper::load_flat_items().
     *
     * @param int    $userid   The user whose views to load.
     * @param array  $categories All categories keyed by id (for path name resolution).
     * @param string $sqlsort  SQL ORDER BY clause fragment for the query.
     * @return array View records keyed by id, each with ->flatcategories and ->entrytype.
     */
    public static function load_flat_views(int $userid, array $categories, string $sqlsort): array {
        global $DB;

        $views = $DB->get_records_sql(
            "SELECT v.* FROM {block_exaportview} v WHERE v.userid = ? " . $sqlsort,
            [$userid]
        );

        if (!$views) {
            return [];
        }

        self::attach_categories($views, $categories);
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
     * @param string $sqlsort    SQL ORDER BY clause fragment.
     * @return array View records with ->flatcategories and ->entrytype.
     */
    public static function load_owner_category_views(int $userid, int $categoryid, array $categories, string $sqlsort): array {
        global $DB;

        if ($categoryid > 0) {
            // Views explicitly assigned to this category.
            $views = $DB->get_records_sql(
                "SELECT v.*
                   FROM {block_exaportview} v
                   JOIN {block_exaportviewcate} vc ON vc.viewid = v.id AND vc.cateid = ?
                  WHERE v.userid = ? " . $sqlsort,
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
                    ) " . $sqlsort,
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
     * Attach share info to each view so the tooltip helper can use it.
     *
     * Decorates each view with:
     *   ->share_users  array of user fullnames (internal user sharing)
     *   ->share_groups array of group names (internal group sharing)
     *   ->share_all    bool (shareall == 1 and shareall is enabled)
     *   ->share_groups_only bool (shareall == 2 = groups only)
     *   ->share_external string|null (external URL if externaccess enabled)
     *
     * @param array $views Associative array of view objects keyed by id (modified in place).
     */
    private static function attach_share_info(array &$views): void {
        global $DB;

        foreach ($views as $view) {
            $view->share_users = [];
            $view->share_groups = [];
            $view->share_all = false;
            $view->share_groups_only = false;
            $view->share_external = null;

            if ($view->shareall == 1 && block_exaport_shareall_enabled()) {
                $view->share_all = true;
            } else if ($view->shareall == 2 && block_exaport_shareall_enabled()) {
                $view->share_groups_only = true;
                $groups = $DB->get_records_sql(
                    "SELECT g.name
                       FROM {groups} g
                       JOIN {block_exaportviewgroupshar} vshar ON g.id = vshar.groupid AND vshar.viewid = ?
                      ORDER BY g.name",
                    [$view->id]
                );
                foreach ($groups as $g) {
                    $view->share_groups[] = $g->name;
                }
            } else {
                $users = $DB->get_records_sql(
                    "SELECT " . $DB->sql_fullname() . " AS name
                       FROM {user} u
                       JOIN {block_exaportviewshar} vshar ON u.id = vshar.userid AND vshar.viewid = ?
                      WHERE u.deleted = 0
                      ORDER BY name",
                    [$view->id]
                );
                foreach ($users as $u) {
                    $view->share_users[] = $u->name;
                }
            }

            if (block_exaport_externaccess_enabled() && $view->externaccess) {
                $view->share_external = block_exaport_get_external_view_url($view);
            }
        }
    }
}
