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

use block_exaport\globals as g;

defined('MOODLE_INTERNAL') || die();

class category_helper {

    /**
     * Build a category tree keyed by parent id.
     *
     * @param array $categories
     * @return array
     */
    public static function build_by_parent(array $categories): array {
        $categoriesbyparent = [];
        foreach ($categories as $category) {
            if (!isset($categoriesbyparent[$category->pid])) {
                $categoriesbyparent[$category->pid] = [];
            }
            $categoriesbyparent[$category->pid][] = $category;
        }

        return $categoriesbyparent;
    }

    /**
     * Build the full hierarchical path name for a category, e.g. "haustiere / hunde".
     *
     * @param int $categoryid The category id.
     * @param array $categories Associative array of all categories keyed by id (must have ->name and ->pid).
     * @return string The full path name with " / " separators.
     */
    public static function full_path_name(int $categoryid, array $categories): string {
        $parts = [];
        $id = $categoryid;
        $visited = [];
        while ($id && isset($categories[$id])) {
            if (isset($visited[$id])) {
                break; // Prevent infinite loop on circular references.
            }
            $visited[$id] = true;
            $parts[] = $categories[$id]->name;
            $id = $categories[$id]->pid ?? 0;
        }
        $parts = array_reverse($parts);
        return implode(' / ', $parts);
    }

    /**
     * Load all items for flat mode and attach flatcategories to each item.
     *
     * @param int $userid The user whose items to load.
     * @param array $categories All categories keyed by id (for path name resolution).
     * @param string $sqlsort SQL ORDER BY clause.
     * @param array|null $allowedcategoryids Category filter behavior:
     *     - null: load all categories for the viewed user in flat mode; this is all categories for your own items, or only that
     *       other user's own categories when viewing someone else's items.
     *     - empty array: return no items.
     *     - non-empty array: only include these category IDs and remove items with no matching categories.
     * @return array The items array with ->flatcategories populated.
     */
    public static function load_flat_items(int $userid, array $categories, string $sqlsort,
                                           ?array $allowedcategoryids = null): array {
        global $DB, $USER;

        if ($allowedcategoryids !== null && empty($allowedcategoryids)) {
            // Keep the shared flat-mode behavior while avoiding an empty IN() SQL clause.
            return [];
        }
        if ($allowedcategoryids !== null) {
            $items = block_exaport_get_items_by_category_and_user(0, $allowedcategoryids, $sqlsort, true);
        } else {
            // this gets ALL the items of that user... e.g. unshared ones as well. As a teacher, this loads all the students items
            // but they get filtered a few lines later with the unset()
            $items = block_exaport_get_items_by_category_and_user($userid, null, $sqlsort);
        }

        if (!$items) {
            return [];
        }

        $itemids = array_keys($items);
        [$iteminsql, $iteminparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_QM);

        // Belt-and-suspenders: restrict to the viewed user's own categories,
        // even though items are already scoped by userid.
        $is_viewing_other_user = $allowedcategoryids === null && (int)$userid !== (int)$USER->id;

        $sql = "SELECT ic.id AS icid, ic.itemid, c.id, c.name, c.pid
                FROM {block_exaportitemcate} ic
                JOIN {block_exaportcate} c ON c.id = ic.cateid
                WHERE ic.itemid $iteminsql";
        $params = $iteminparams;

        // Belt-and-suspenders: restrict to the viewed user's own categories,
        // even though items are already scoped by userid.
        if ($is_viewing_other_user) {
            $sql .= " AND c.userid = ?";
            $params[] = $userid;
        }

        if ($allowedcategoryids !== null) {
            [$catinsql, $catinparams] = $DB->get_in_or_equal($allowedcategoryids, SQL_PARAMS_QM);
            $sql .= " AND c.id $catinsql";
            $params = array_merge($params, $catinparams);
        }

        $sql .= " ORDER BY c.name ASC";
        $itemcategories = $DB->get_records_sql($sql, $params);

        $categoriesbyitem = [];
        foreach ($itemcategories as $itemcategory) {
            $itemcategory->name = self::full_path_name($itemcategory->id, $categories);
            if (!isset($categoriesbyitem[$itemcategory->itemid])) {
                $categoriesbyitem[$itemcategory->itemid] = [];
            }
            $categoriesbyitem[$itemcategory->itemid][] = $itemcategory;
        }

        foreach ($items as $itemid => $item) {
            $item->flatcategories = $categoriesbyitem[$item->id] ?? [];
            if ($allowedcategoryids !== null && !$item->flatcategories) {
                unset($items[$itemid]); // this is crucial! This unsets all items that should not be displayed
            }
        }

        return $items;
    }

    /**
     * Load all categories (with item counts) for one owner.
     *
     * @param int $userid
     * @return array
     */
    public static function load_owner_categories(int $userid): array {
        global $DB;

        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        return $DB->get_records_sql("
            SELECT
                {$categorycolumns}
                , COUNT(DISTINCT i.id) AS item_cnt
            FROM {block_exaportcate} c
            LEFT JOIN {block_exaportitemcate} ic ON ic.cateid = c.id
            LEFT JOIN {block_exaportitem} i ON (
                i.id = ic.itemid
            ) AND " . block_exaport_get_item_where() . "
            WHERE c.userid = ?
            GROUP BY
                {$categorycolumns}
            ORDER BY c.name ASC
        ", [$userid]);
    }

    /**
     * Load items for one owner and one category.
     *
     * @param int $userid
     * @param int $categoryid
     * @param string $sqlsort
     * @return array
     */
    public static function load_owner_category_items(int $userid, int $categoryid, string $sqlsort): array {
        global $DB;

        return $DB->get_records_sql("
            SELECT DISTINCT i.*, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE i.userid = ?
                AND EXISTS (
                    SELECT 1
                    FROM {block_exaportitemcate} ic
                    WHERE ic.itemid = i.id
                      AND ic.cateid = ?
                )
                AND " . block_exaport_get_item_where() .
            " GROUP BY i.id, i.userid, i.type, i.name, i.url, i.intro,
            i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
            i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
            i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
            i.iseditable, i.example_url, i.parentid
            $sqlsort
        ", [$userid, $categoryid]);
    }
}
