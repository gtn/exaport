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

require_once(__DIR__ . '/inc.php');

use block_exaport\globals as g;

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$show_subcategories = optional_param('show_subcategories', -1, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_TEXT);
$layout = optional_param('layout', '', PARAM_TEXT);
$folderlayout = optional_param('folderlayout', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_TEXT);

$wstoken = optional_param('wstoken', null, PARAM_RAW);

require_once($CFG->dirroot . '/webservice/lib.php');

$useBootstrapLayout = block_exaport_use_bootstrap_layout();

$authenticationinfo = null;
if ($wstoken) {
    $webservicelib = new webservice();
    $authenticationinfo = $webservicelib->authenticate_user($wstoken);
} else {
    block_exaport_require_login($courseid);
}


$context = context_system::instance();

// Fall back to stored user preferences when not provided via URL.
$layoutfromurl = $layout;
if (!$layout) {
    $layout = get_user_preferences('block_exaport_layout', 'folder');
}
$folderlayoutfromurl = $folderlayout;
if (!$folderlayout) {
    $folderlayout = get_user_preferences('block_exaport_folderlayout', 'tiles');
}
$sortfromurl = $sort;
if (!$sort) {
    $sort = get_user_preferences('block_exaport_sort', 'date.desc');
}
$showsubcategoriesfromurl = $show_subcategories;
if ($show_subcategories === -1) {
    $show_subcategories = (int)get_user_preferences('block_exaport_show_subcategories', 0);
}

if ($type != 'shared' && $type != 'sharedstudent') {
    $type = 'mine';
}

// Main layout mode switch: folder (legacy navigation) or flat (all items).
if (in_array($layout, ['tiles', 'details'])) {
    // Backward compatibility for old URL layout values.
    $folderlayout = $layout;
    $layout = 'folder';
}
if (!in_array($layout, ['folder', 'flat'])) {
    $layout = 'folder';
}
if ($folderlayout != 'details') {
    $folderlayout = 'tiles';
}
// Persist preferences on page load when explicitly provided via URL.
if ($layoutfromurl !== '') {
    set_user_preference('block_exaport_layout', $layout);
}
if ($folderlayoutfromurl !== '' || $layoutfromurl !== '') {
    set_user_preference('block_exaport_folderlayout', $folderlayout);
}
if ($showsubcategoriesfromurl !== -1) {
    set_user_preference('block_exaport_show_subcategories', (int)$show_subcategories);
}

// Check sorting.
$parsedsort = block_exaport_parse_item_sort($sort, false);
$sort = $parsedsort[0] . '.' . $parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
    $newsort = $sortkey . ".asc";
} else {
    $newsort = $sortkey . ".desc";
}
$sorticon = $parsedsort[1] . '.png';
$sqlsort = block_exaport_item_sort_to_sql($parsedsort, false);

if ($sortfromurl !== '') {
    set_user_preference('block_exaport_sort', $sort);
}

block_exaport_setup_default_categories();

if ($type == 'sharedstudent') {
    // Students for Teacher
    if (block_exaport_user_can_see_artifacts_of_students()) {
        $students = block_exaport_get_students_for_teacher();
    } else {
        throw new moodle_exception('not allowed');
    }

    $selecteduser = $userid && isset($students[$userid]) ? $students[$userid] : null;

    if (!$selecteduser) {
        throw new moodle_exception('wrong userid');
    } else {
        // Read all categories.
        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        $categories = $DB->get_records_sql("
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
                        ", array($selecteduser->id));

        foreach ($categories as $category) {
            $category->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . '&userid=' . $selecteduser->id .
                '&type=sharedstudent&categoryid=' . $category->id;
            $category->icon = block_exaport_get_category_icon($category);
        }

        // Build a tree according to parent.
        $categoriesbyparent = block_exaport_build_categories_by_parent($categories);

        // The main root category for student.
        $rootcategory = block_exaport_get_root_category($selecteduser->id);
        $rootcategory->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id .
            '&type=sharedstudent&userid=' . $selecteduser->id;
        $categories[0] = $rootcategory;

        if (isset($categories[$categoryid])) {
            $currentcategory = $categories[$categoryid];
        } else {
            $currentcategory = $rootcategory;
        }

        // What's the parent category?.
        if ($currentcategory->id && isset($categories[$currentcategory->pid])) {
            $parentcategory = $categories[$currentcategory->pid];
        } else {
            // Link to shared categories
            $parentcategory = (object)[
                'id' => 0,
                'url' => new moodle_url('shared_categories.php', ['courseid' => $COURSE->id, 'sort' => 'mystudents']),
                'name' => '',
            ];
        }

        $subcategories = !empty($categoriesbyparent[$currentcategory->id]) ? $categoriesbyparent[$currentcategory->id] : [];

        if ($layout == 'flat') {
            // Flat mode always lists the selected student's items globally.
            $currentcategory = $rootcategory;
            $parentcategory = null;
            $subcategories = [];
            $items = block_exaport_load_flat_items($selecteduser->id, $categories, $sqlsort);
        } else {
            // Common items.
            $items = $DB->get_records_sql("
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
            ", [$selecteduser->id, $currentcategory->id]);
        }
    }

} else if ($type == 'shared') {
    $rootcategory = (object)[
        'id' => 0,
        'pid' => 0,
        'name' => block_exaport_get_string('shareditems_category'),
        'item_cnt' => '',
        'url' => $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id . '&type=shared',
    ];

    $sharedusers = block_exaport\get_categories_shared_to_user($USER->id);
    $selecteduser = $userid && isset($sharedusers[$userid]) ? $sharedusers[$userid] : null;

    /*
    if (!$selectedUser) {
        $currentCategory = $rootCategory;
        $parentCategory = null;
        $subCategories = $sharedUsers;

        foreach ($subCategories as $category) {
            $userpicture = new user_picture($category);
            $userpicture->size = ($folderlayout == 'tiles' ? 100 : 32);
            $category->icon = $userpicture->get_url($PAGE);
        }

        $items = [];
    } else {
        $currentCategory = $selectedUser;
        $subCategories = $selectedUser->categories;
        $parentCategory = $rootCategory;
        $items = [];
    }
    */
    if (!$categoryid) {
        throw new moodle_exception('wrong category');
    } else if (!$selecteduser) {
        throw new moodle_exception('wrong userid');
    } else {
        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        $categories = $DB->get_records_sql("
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
        ", array($selecteduser->id));

        function category_allowed($selecteduser, $categories, $category) {
            while ($category) {
                if (isset($selecteduser->categories[$category->id])) {
                    return true;
                } else if ($category->pid && isset($categories[$category->pid])) {
                    $category = $categories[$category->pid];
                } else {
                    break;
                }
            }

            return false;
        }

        foreach ($categories as $category) {
            $category->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . '&type=shared&userid=' . $userid .
                '&categoryid=' . $category->id;
            $category->icon = block_exaport_get_category_icon($category);
        }
        // Build a tree according to parent.
        $categoriesbyparent = block_exaport_build_categories_by_parent($categories);

        if (!isset($categories[$categoryid])) {
            throw new moodle_exception('not allowed');
        }

        $currentcategory = $categories[$categoryid];
        $subcategories = !empty($categoriesbyparent[$currentcategory->id]) ? $categoriesbyparent[$currentcategory->id] : [];
        if (isset($categories[$currentcategory->pid]) &&
            category_allowed($selecteduser, $categories, $categories[$currentcategory->pid])
        ) {
            $parentcategory = $categories[$currentcategory->pid];
        } else {
            $parentcategory = (object)[
                'id' => 0,
                'url' => new moodle_url('shared_categories.php', ['courseid' => $COURSE->id]),
                'name' => '',
            ];
        }

        if (!category_allowed($selecteduser, $categories, $currentcategory)) {
            throw new moodle_exception('not allowed');
        }

        if ($layout == 'flat') {
            // Flat mode lists all shared items, limited to categories shared to the current user.
            $allowedcategories = [];
            foreach ($categories as $category) {
                if ((int)$category->id === 0) {
                    continue;
                }
                if (category_allowed($selecteduser, $categories, $category)) {
                    $allowedcategories[(int)$category->id] = $category;
                }
            }

            $currentcategory = $rootcategory;
            $parentcategory = null;
            $subcategories = [];
            $items = block_exaport_load_flat_items($selecteduser->id, $categories, $sqlsort, array_keys($allowedcategories));
        } else {
            $usercondition = ' i.userid = ' . intval($selecteduser->id) . ' ';
            if ($type == 'shared') {
                $usercondition = ' i.userid > 0 ';
            }

            $items = $DB->get_records_sql("
                SELECT DISTINCT i.*, COUNT(com.id) As comments
                FROM {block_exaportitem} i
                LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
                WHERE EXISTS (
                        SELECT 1
                        FROM {block_exaportitemcate} ic
                        WHERE ic.itemid = i.id
                          AND ic.cateid = ?
                    )
                    AND " . $usercondition . "
                    AND " . block_exaport_get_item_where() .
                " GROUP BY i.id, i.userid, i.type, i.name, i.url, i.intro,
                i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
                i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
                i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
                i.iseditable, i.example_url, i.parentid
                $sqlsort
            ", [$currentcategory->id]);
        }
    }

} else {
    // Read all categories.
    $categories = block_exaport_get_all_categories_for_user($USER->id);

    foreach ($categories as $category) {
        $category->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . '&categoryid=' . $category->id;
        $category->icon = block_exaport_get_category_icon($category);
    }

    // Build a tree according to parent.
    $categoriesbyparent = block_exaport_build_categories_by_parent($categories);

    // The main root category.
    $rootcategory = block_exaport_get_root_category();
    $categories[0] = $rootcategory;

    if (isset($categories[$categoryid])) {
        $currentcategory = $categories[$categoryid];
    } else {
        $currentcategory = $rootcategory;
    }

    // What's the parent category?.
    if ($currentcategory->id && isset($categories[$currentcategory->pid])) {
        $parentcategory = $categories[$currentcategory->pid];
    } else {
        $parentcategory = null;
    }

    $subcategories = !empty($categoriesbyparent[$currentcategory->id]) ? $categoriesbyparent[$currentcategory->id] : [];

    if ($layout == 'flat') {
        // Flat mode always lists the user's items globally and only applies optional category filters.
        $currentcategory = $rootcategory;
        $parentcategory = null;
        $subcategories = [];
        $items = block_exaport_load_flat_items($USER->id, $categories, $sqlsort);
    } else {
        // Folder mode keeps legacy category navigation behavior.
        $items = block_exaport_get_items_by_category_and_user($USER->id, $currentcategory->id, $sqlsort, true);
    }
}

// Build canonical URL with only navigation-defining params.
$pageparams = ['courseid' => $courseid];
if ($categoryid) {
    $pageparams['categoryid'] = $categoryid;
}
if ($type && $type != 'mine') {
    $pageparams['type'] = $type;
}
if ($userid) {
    $pageparams['userid'] = $userid;
}
$PAGE->set_url(new moodle_url('/blocks/exaport/view_items.php', $pageparams));
// $PAGE->set_context(context_system::instance());

block_exaport_add_iconpack();

block_exaport_print_header($type == 'shared' || $type == 'sharedstudent' ? 'shared_categories' : "myportfolio");

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) {
    $pref = "desp_";
} else {
    $pref = "";
}
$infobox = text_to_html(get_string($pref . "explaining", "block_exaport"));
$infobox .= '<a href="#more_artefacts_info" data-toggle="showmore">' . get_string('moreinfolink', 'block_exaport') . '</a>';
$infobox .= '<div id="more_artefacts_info" style="display: none;">' . get_string('explainingmoredata', 'block_exaport') . '</div>';
echo $OUTPUT->box($infobox, "center");

echo "</div>";

echo '<div class="excomdos_cont layout_' . block_exaport_used_layout() . ' excomdos_cont-type-' . $type . '">';
if ($type == 'mine' && $layout == 'folder') {
    echo '<div class="d-flex flex-wrap align-items-center" style="gap: 0.5rem;">';
    echo '<div>';
    echo get_string("categories", "block_exaport") . ": ";
    echo '<select onchange="document.location.href=\'' . $PAGE->url->out(false) . '&categoryid=\'+encodeURIComponent(this.value);">';
    echo '<option value="">';
    echo $rootcategory->name;
    if ($rootcategory->item_cnt) {
        echo ' (' . $rootcategory->item_cnt . ' ' . block_exaport_get_string($rootcategory->item_cnt == 1 ? 'item' : 'items') . ')';
    }
    echo '</option>';
    function block_exaport_print_category_select($categoriesbyparent, $currentcategoryid, $pid = 0, $level = 0) {
        if (!isset($categoriesbyparent[$pid])) {
            return;
        }

        foreach ($categoriesbyparent[$pid] as $category) {
            echo '<option value="' . $category->id . '"' . ($currentcategoryid == $category->id ? ' selected="selected"' : '') . '>';
            if ($level) {
                echo str_repeat('&nbsp;', 4 * $level) . ' &rarr;&nbsp; ';
            }
            echo $category->name;
            if ($category->item_cnt) {
                echo ' (' . $category->item_cnt . ' ' . block_exaport_get_string($category->item_cnt == 1 ? 'item' : 'items') . ')';
            }
            echo '</option>';
            block_exaport_print_category_select($categoriesbyparent, $currentcategoryid,
                $category->id, $level + 1);
        }
    }

    block_exaport_print_category_select($categoriesbyparent, $currentcategory->id);
    echo '</select>';
    echo '</div>';
    // Create button (pushed to right).
    echo '<div class="ms-auto">';
    block_exaport_print_create_button($courseid, $categoryid, $type);
    echo '</div>';
    echo '</div>';
} else if (($type == 'mine' || $type == 'shared' || $type == 'sharedstudent') && $layout == 'flat') {
    // Self-made filter bar: search input + category dropdown + sort dropdown in one row, chips below.
    $filtercategories = [];
    foreach ($categories as $category) {
        if ((int)$category->id === 0) {
            continue;
        }
        if ($type == 'shared' && !category_allowed($selecteduser, $categories, $category)) {
            continue;
        }
        $filtercategories[(int)$category->id] = block_exaport_category_full_path_name($category->id, $categories);
    }

    $flatsort = str_replace('.', '-', $sort);
    if (!in_array($flatsort, ['date-desc', 'date-asc', 'name-asc', 'name-desc'])) {
        $flatsort = 'date-desc';
    }

    echo '<div class="exaport-flat-filter mb-3">';
    // Row 1: search + category dropdown + sort dropdown + create button.
    echo '<div class="d-flex flex-wrap align-items-center" style="gap: 0.5rem;">';
    // Search input.
    echo '<div class="flex-grow-1" style="min-width: 150px; max-width: 300px;">';
    echo '<label class="sr-only" for="exaport-flat-search">' . get_string('search') . '</label>';
    echo '<input type="text" id="exaport-flat-search" class="form-control" placeholder="' . get_string('search') . '...">';
    echo '</div>';
    // Category filter dropdown (simple select; chip multiselect handled by JS).
    echo '<div style="min-width: 200px; max-width: 350px;">';
    echo '<label class="sr-only" for="exaport-flat-category-select">' . get_string('category', 'block_exaport') . '</label>';
    echo '<select id="exaport-flat-category-select" class="form-control custom-select">';
    echo '<option value="">' . get_string('category', 'block_exaport') . '</option>';
    foreach ($filtercategories as $catid => $catname) {
        echo '<option value="' . $catid . '">' . s($catname) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    // Sort dropdown.
    echo '<div style="min-width: 180px; max-width: 250px;">';
    echo '<label class="sr-only" for="exaport-flat-sort-select">' . get_string('sort') . '</label>';
    echo '<select id="exaport-flat-sort-select" class="form-control custom-select">';
    echo '<option value="date-desc"' . ($flatsort === 'date-desc' ? ' selected="selected"' : '') . '>' . get_string('date', 'block_exaport') . ' ↓</option>';
    echo '<option value="date-asc"' . ($flatsort === 'date-asc' ? ' selected="selected"' : '') . '>' . get_string('date', 'block_exaport') . ' ↑</option>';
    echo '<option value="name-asc"' . ($flatsort === 'name-asc' ? ' selected="selected"' : '') . '>' . get_string('name', 'block_exaport') . ' A-Z</option>';
    echo '<option value="name-desc"' . ($flatsort === 'name-desc' ? ' selected="selected"' : '') . '>' . get_string('name', 'block_exaport') . ' Z-A</option>';
    echo '</select>';
    echo '</div>';
    // Create button (pushed to right).
    echo '<div class="ms-auto">';
    block_exaport_print_create_button($courseid, $categoryid, $type);
    echo '</div>';
    echo '</div>';
    // Row 2: "show items from subcategories" checkbox + active filter chips.
    echo '<div class="mt-2 d-flex flex-wrap align-items-center" style="gap: 0.5rem;">';
    echo '<label style="font-weight:normal; margin:0;"><input type="checkbox" id="exaport-flat-subcategories-checkbox"' . ($show_subcategories ? ' checked="checked"' : '') . '> ';
    echo get_string('show_items_from_subcategories', 'block_exaport');
    echo '</label>';
    echo '</div>';
    echo '<div id="exaport-flat-filter-chips" class="mt-2 d-flex flex-wrap align-items-center" style="gap: 0.4rem;"></div>';
    echo '</div>';

    // Build category children map for JS (parent_id => [child_id, ...]).
    $categorychildrenmap = [];
    foreach ($categories as $cat) {
        if ((int)$cat->id === 0) {
            continue;
        }
        $pid = (int)$cat->pid;
        if (!isset($categorychildrenmap[$pid])) {
            $categorychildrenmap[$pid] = [];
        }
        $categorychildrenmap[$pid][] = (int)$cat->id;
    }

    // Load AMD module for filtering.
    $PAGE->requires->js_call_amd('block_exaport/flat_filter', 'init', [
        get_string('clearAllFilers', 'block_exaport'),
        get_string('searchcategory', 'block_exaport'),
        $categorychildrenmap
    ]);
}

echo '<div class="excomdos_additem ' . ($useBootstrapLayout ? 'd-flex justify-content-between align-items-center flex-wrap' : '') . '">';

// Left side: folder/flat display toggle (btn-group style).
echo '<div class="btn-group exaport-layout-toggle" role="group" aria-label="Layout">';
echo '<a href="' . $PAGE->url->out(true, ['layout' => 'folder', 'folderlayout' => $folderlayout]) . '" class="btn btn-sm ' . ($layout == 'folder' ? 'btn-primary' : 'btn-outline-secondary') . '">'
    . block_exaport_fontawesome_icon('folder-open', 'regular', 1)
    . ' ' . get_string('category', 'block_exaport') . '</a>';
echo '<a href="' . $PAGE->url->out(true, ['layout' => 'flat', 'folderlayout' => $folderlayout]) . '" class="btn btn-sm ' . ($layout == 'flat' ? 'btn-primary' : 'btn-outline-secondary') . '">'
    . block_exaport_fontawesome_icon('table-cells', 'solid', 1)
    . ' ' . get_string('all') . '</a>';
echo '</div>';

// Right side: tiles/details toggle (btn-group style) + printer-friendly button.
echo '<div class="d-flex align-items-center" style="gap: 0.5rem;">';
echo '<div class="btn-group exaport-view-toggle" role="group" aria-label="View">';
echo '<a href="' . $PAGE->url->out(true, ['folderlayout' => 'tiles']) . '" class="btn btn-sm exaport-view-toggle-action ' . ($folderlayout == 'tiles' ? 'btn-primary' : 'btn-outline-secondary') . '" data-folderlayout="tiles">'
    . block_exaport_fontawesome_icon('table-cells-large', 'solid', 1)
    . ' ' . block_exaport_get_string("tiles") . '</a>';
echo '<a href="' . $PAGE->url->out(true, ['folderlayout' => 'details']) . '" class="btn btn-sm exaport-view-toggle-action ' . ($folderlayout == 'details' ? 'btn-primary' : 'btn-outline-secondary') . '" data-folderlayout="details">'
    . block_exaport_fontawesome_icon('list', 'solid', 1)
    . ' ' . block_exaport_get_string("details") . '</a>';
echo '</div>';
if ($type == 'mine') {
    echo '<a target="_blank" href="' . $CFG->wwwroot . '/blocks/exaport/view_items_print.php?courseid=' . $courseid . '" class="btn btn-sm btn-outline-secondary">'
        . block_exaport_fontawesome_icon('print', 'solid', 1)
        . ' ' . get_string("printerfriendly", "group") . '</a>';
}
echo '</div>';

echo '</div>';

$PAGE->requires->js_call_amd('block_exaport/view_items_state', 'init', [$folderlayout, $layout]);

if ($layout == 'folder') {
    echo '<div class="excomdos_cat">';
    echo block_exaport_get_string('current_category') . ': ';

    $currentcategoryPathItemButtons = '';

/*echo '<b>';
if (($type == 'shared' || $type == 'sharedstudent') && $selecteduser) {
    echo $selecteduser->name.' / ';
}
echo $currentcategory->name;
echo '</b> ';*/

if ($type == 'mine' && $currentcategory->id > 0) {
    if (@$currentcategory->internshare && (count(exaport_get_category_shared_users($currentcategory->id)) > 0 ||
            count(exaport_get_category_shared_groups($currentcategory->id)) > 0 || $currentcategory->shareall == 1)
    ) {
        $currentcategoryPathItemButtons .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
        //        $currentcategoryPathItemButtons .= ' <img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
    }
    $currentcategoryPathItemButtons .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $currentcategory->id .
        '&action=edit&back=same">'
        . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
        //            .'<img src="pix/edit.png" alt="'.get_string("edit").'" />'
        . '</a>';
    $currentcategoryPathItemButtons .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $currentcategory->id .
        '&action=delete&back=same">'
        . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
        //            .'<img src="pix/del.png" alt="'.get_string("delete").'"/>'
        . '</a>';

    // Show path only for "my" category. Shared category will not show it, because we need to hide inner Path of the user's structure
    echo '<span class="excomdos_cat_path">' . block_exaport_category_path($currentcategory, $courseid, $currentcategoryPathItemButtons) . '</span>';
} else if ($type == 'shared' && $selecteduser && $categoryid) {
    echo block_exaport_fontawesome_icon('circle-user', 'solid', 1)
        //        .'<strong><img src="pix/user1.png" width="16" />&nbsp;'
        . $selecteduser->name . '&nbsp;/&nbsp;'
        . block_exaport_fontawesome_icon('folder', 'regular', 1, [], ['color' => '#7a7a7a'])
        //        .'<img src="pix/cat_path_item.png" width="16" />'
        . '&nbsp;' . $currentcategory->name . '</strong>';
    // When category selected, allow copy.
    /*
    $url = $PAGE->url->out(true, ['action'=>'copy']);
    echo '<button onclick="document.location.href=\'shared_categories.php?courseid='.$courseid.'&action=copy&categoryid='.$categoryid.'\'">'.block_exaport_get_string('copycategory').'</button>';
    */
} else if ($type == 'mine') {
    // mine, but ROOT
    echo '<span class="excomdos_cat_path">' . block_exaport_category_path(null, $courseid, $currentcategoryPathItemButtons) . '</span>';
}
    echo '</div>';
}

echo '<div class="exaport-view-section exaport-view-details' . ($folderlayout == 'details' ? ' is-active' : '') . '" data-exaport-view="details"' . ($folderlayout == 'details' ? '' : ' style="display:none;"') . '>';
// For flat mode, render the table manually so we can add data attributes for JS filtering.
// For folder mode, use html_table as before.
$useManualTable = ($layout == 'flat');

    $table = new html_table();
    $table->width = "100%";

    $table->head = array();
    $table->size = array();

    if ($layout == 'flat') {
        $table->head['type'] = get_string("type", "block_exaport");
    } else {
        $table->head['type'] = '<a href="' . $PAGE->url->out(true, ['sort' => ($sortkey == 'type' ? $newsort : 'type'), 'folderlayout' => 'details']) . '">' . get_string("type", "block_exaport") . '</a>';
    }
    $table->size['type'] = "10";

    if ($layout == 'flat') {
        $table->head['name'] = get_string("name", "block_exaport");
    } else {
        $table->head['name'] = '<a href="' . $PAGE->url->out(true, ['sort' => ($sortkey == 'name' ? $newsort : 'name'), 'folderlayout' => 'details']) . '">' . get_string("name", "block_exaport") . '</a>';
    }
    $table->size['name'] = "60";

    if ($layout == 'flat') {
        $table->head['date'] = get_string("date", "block_exaport");
    } else {
        $table->head['date'] = '<a href="' . $PAGE->url->out(true, ['sort' => ($sortkey == 'date' ? $newsort : 'date.desc'), 'folderlayout' => 'details']) . '">' . get_string("date", "block_exaport") . '</a>';
    }
    $table->size['date'] = "20";

    $table->head['icons'] = '';
    $table->size['icons'] = "10";

    // Add arrow to heading if available.
    if (isset($table->head[$sortkey])) {
        $table->head[$sortkey] = "<img src=\"pix/$sorticon\" alt='" . get_string("updownarrow", "block_exaport") . "' /> " .
            $table->head[$sortkey];
    }

    $table->data = array();
    $itemind = -1;

    if ($parentcategory) {
        // If isn't parent category, show link to go to parent category.
        $itemind++;
        $table->data[$itemind] = array();
        //        $table->data[$itemind]['type'] = '<img src="pix/folderup_32.png" alt="'.block_exaport_get_string('category').'">';
        $table->data[$itemind]['type'] = block_exaport_fontawesome_icon('folder-open', 'regular', 2, [], [], [], 'up', [], [], [], ['exaport-items-category-middle']);

        $table->data[$itemind]['name'] = '<a href="' . $parentcategory->url . '">' . $parentcategory->name . '</a>';
        $table->data[$itemind][] = null;
        $table->data[$itemind][] = null;
    }

    foreach ($subcategories as $category) {
        // Checking for shared items. If userid is null - show users, if userid > 0 - need to show items from user.
        $itemind++;
        $table->data[$itemind] = array();
        //        $table->data[$itemind]['type'] = '<img src="'.(@$category->icon ?: 'pix/folder_32_user.png').'" style="max-width:32px">';
        $table->data[$itemind]['type'] = block_exaport_fontawesome_icon('folder-open', 'regular', 2, [], [], [], '', [], [], [], ['exaport-items-category-middle']);

        $table->data[$itemind]['name'] = '<a href="' . $category->url . '">' . $category->name . '</a>';

        $table->data[$itemind][] = null;

        if ($type == 'mine' && $category->id > 0) {
            $table->data[$itemind]['icons'] = '<span class="excomdos_listicons">';
            if ((isset($category->internshare) && $category->internshare == 1) &&
                (count(exaport_get_category_shared_users($category->id)) > 0 ||
                    count(exaport_get_category_shared_groups($category->id)) > 0 ||
                    (isset($category->shareall) && $category->shareall == 1))
            ) {
                $table->data[$itemind]['icons'] .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
                //                $table->data[$itemind]['icons'] .= '<img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
            };
            if (@$category->structure_share) {
                $table->data[$itemind]['icons'] .= ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
            }

            $table->data[$itemind]['icons'] .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid .
                '&id=' . $category->id . '&action=edit">'
                . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                //                    .'<img src="pix/edit.png" alt="'.get_string("edit").'" />'
                . '</a>' .
                ' <a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $category->id .
                '&action=delete">'
                . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                //                    .'<img src="pix/del.png" alt="'.get_string("delete").'"/>'
                . '</a>' .
                '</span>';
        } else { // Category with shared items.
            $table->data[$itemind]['icons'] = '';
        }
    }

    // For flat mode, we'll collect item row data separately to render with data attributes.
    $flatItemRows = [];

    $itemscnt = count($items);
    foreach ($items as $item) {
        $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid . '&access=portfolio/id/' . $item->userid . '&itemid=' .
            $item->id;

        $itemind++;

        $rowdata = array();

        //        $imgtype = '<img src="pix/'.$item->type.'_32.png" alt="'.get_string($item->type, "block_exaport").'">';
        //        $imgtype = '<img src="pix/'.$item->type.'_icon.png" alt="'.get_string($item->type, "block_exaport").'" title="'.get_string($item->type, "block_exaport").'" width="32">';
        // Artefact type.
        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $imgtype = block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 2, [], [], [], '', [], [], [], ['exaport-items-type-icon']);

        $rowdata['type'] = $imgtype;

        $rowdata['name'] = "<a href=\"" . s($url) . "\">" . $item->name . "</a>";
        if ($item->intro) {
            $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
                'block_exaport', 'item_content', 'portfolio/id/' . $item->userid . '/itemid/' . $item->id);

            $shortintro = substr(trim(strip_tags($intro)), 0, 20);
            if (preg_match_all('#(?:<iframe[^>]*)(?:(?:/>)|(?:>.*?</iframe>))#i', $intro, $matches)) {
                $shortintro = $matches[0][0];
            }

            if (!$intro) {
                $tempvar = 1; // For code checker.
                // No intro.
            } else if ($shortintro == $intro) {
                // Very short one.
                $rowdata['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">" .
                    format_text($intro, FORMAT_HTML) . "</td></tr></table>";
            } else {
                // Display show/hide buttons.
                $rowdata['name'] .= '<div><div id="short-preview-' . $itemind . '"><div>' . $shortintro . '...</div>
                        <a href="javascript:long_preview_show(' . $itemind . ')">[' . get_string('more') . '...]</a>
                        </div>
                        <div id="long-preview-' . $itemind . '" style="display: none;"><div>' . $intro . '</div>
                        <a href="javascript:long_preview_hide(' . $itemind . ')">[' . strtolower(get_string('hide')) . '...]</a>
                        </div>';
            }
        }

        $rowdata['date'] = userdate($item->timemodified);

        $icons = '';

        // Link to export to my portfolio.
        if ($currentcategory->id == -1) {
            $rowdata['icons'] = '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid .
                '&id=' . $item->id . '&sesskey=' . sesskey() . '&action=copytoself' . '">' .
                '<img src="pix/import.png" title="' . get_string('make_it_yours', "block_exaport") . '"></a>';
            if ($useManualTable) {
                $flatItemRows[] = ['data' => $rowdata, 'item' => $item];
            } else {
                $table->data[$itemind] = $rowdata;
            }
            continue;
        };

        if (isset($item->comments) && $item->comments > 0) {
            $icons .= '<span class="excomdos_listcomments">
                            <a href="' . $url . '" >
                        ' . $item->comments . '<img src="pix/comments.png" alt="file">
                            </a>
                        </span>';
        }

        $icons .= block_exaport_get_item_comp_icon($item);

        // Copy files to course.
        if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course')) {
            $icons .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/copy_item_to_course.php?courseid=' . $courseid . '&itemid=' . $item->id .
                '&backtype=">' . get_string("copyitemtocourse", "block_exaport") . '</a>';
        }

        if ($type == 'mine') {
            $icons .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id . '&action=edit">'
                . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                //                    .'<img src="pix/edit.png" alt="'.get_string("edit").'" />'
                . '</a>';
            if ($allowedit = block_exaport_item_is_editable($item->id)) {
                $icons .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id .
                    '&action=delete&categoryid=' . $categoryid . '">'
                    . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                    //                        .'<img src="pix/del.png" alt="'.get_string("delete").'"/>'
                    . '</a>';
            } else {
                $icons .= '<img src="pix/deleteview.png" alt="' . get_string("delete") . '">';
            }
        }

        $icons = '<span class="excomdos_listicons">' . $icons . '</span>';

        $rowdata['icons'] = $icons;

        if ($useManualTable) {
            $flatItemRows[] = ['data' => $rowdata, 'item' => $item];
        } else {
            $table->data[$itemind] = $rowdata;
        }
    }

if ($useManualTable) {
        // Render table header and category rows using html_table, then manually render item rows with data attributes.
        echo html_writer::table($table);
        // Now render item rows as a separate table with data attributes on each row.
        echo '<table class="generaltable" width="100%"><tbody>';
        foreach ($flatItemRows as $flatRow) {
            $item = $flatRow['item'];
            $row = $flatRow['data'];
            $itemCatIds = [];
            if (!empty($item->flatcategories) && is_array($item->flatcategories)) {
                foreach ($item->flatcategories as $cat) {
                    $itemCatIds[] = (int)$cat->id;
                }
            }
            echo '<tr class="exaport-flat-item" data-item-name="' . s(strtolower($item->name)) . '" data-category-ids="' . s(implode(',', $itemCatIds)) . '" data-item-date="' . (int)$item->timemodified . '">';
            echo '<td style="width:10%">' . ($row['type'] ?? '') . '</td>';
            echo '<td style="width:60%">' . ($row['name'] ?? '') . '</td>';
            echo '<td style="width:20%">' . ($row['date'] ?? '') . '</td>';
            echo '<td style="width:10%">' . ($row['icons'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
} else {
    echo html_writer::table($table);
}
echo '</div>';

echo '<div class="exaport-view-section exaport-view-tiles' . ($folderlayout == 'tiles' ? ' is-active' : '') . '" data-exaport-view="tiles"' . ($folderlayout == 'tiles' ? '' : ' style="display:none;"') . '>';
echo '<div class="excomdos_tiletable ' . ($useBootstrapLayout ? 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5' : '') . '">';
echo '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>';

if ($layout == 'folder') {
    // Show a link to parent category only for folder mode navigation.
    if ($parentcategory) {
        $parentlinkcategory = $currentcategory;
        echo block_exaport_category_list_item($parentlinkcategory, $courseid, $type, $currentcategory, $parentcategory);
    }

    foreach ($subcategories as $category) {
        echo block_exaport_category_list_item($category, $courseid, $type, $currentcategory, null);
    }
}

foreach ($items as $item) {
    echo block_exaport_artefact_list_item($item, $courseid, $type, $categoryid, $currentcategory);
}

echo '</div>';
echo '</div>';

echo '<div style="clear: both;">&nbsp;</div>';
echo "</div>";
echo block_exaport_wrapperdivend();

echo $OUTPUT->footer();

function block_exaport_get_item_comp_icon($item) {
    global $DB;

    if (!block_exaport_check_competence_interaction()) {
        return;
    }

    $comps = block_exaport_get_active_comps_for_item($item);

    if (!$comps) {
        return;
    }

    // If item is assoziated with competences display them.
    $competences = "";
    foreach ($comps["descriptors"] as $comp) {
        $competences .= $comp->title . '<br>';
    }
    foreach ($comps["topics"] as $comp) {
        $competences .= $comp->title . '<br>';
    }
    $competences = str_replace("\r", "", $competences);
    $competences = str_replace("\n", "", $competences);
    $competences = str_replace("\"", "&quot;", $competences);
    $competences = str_replace("'", "&prime;", $competences);
    $competences = trim($competences);

    if (!$competences) {
        return;
    }

    return '<a class="artefact-button" onmouseover="Tip(\'' . $competences . '\')" onmouseout="UnTip()">'
        . block_exaport_fontawesome_icon('list', 'solid', 1)
        //        .'<img src="pix/comp.png" alt="'.'competences'.'" />'
        . '</a>';
}

/**
 * Prints the unified "Create" dropdown button (artefact + category).
 */
function block_exaport_print_create_button($courseid, $categoryid, $type) {
    global $CFG;
    $cattype = '';
    if ($type == 'shared') {
        $cattype = '&cattype=shared';
    }
    $createartefacturl = $CFG->wwwroot . '/blocks/exaport/item.php?action=add&courseid=' . $courseid . '&categoryid=' . $categoryid . $cattype . '&type=mixed';
    $createcategoryurl = $CFG->wwwroot . '/blocks/exaport/category.php?action=add&courseid=' . $courseid . '&pid=' . $categoryid;

    echo '<div style="position: relative; display: inline-block;">';
    echo '<button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">';
    echo block_exaport_fontawesome_icon('plus', 'solid', 1) . ' ' . get_string('create');
    echo '</button>';
    echo '<div class="dropdown-menu dropdown-menu-right dropdown-menu-end">';
    echo '<a class="dropdown-item" href="' . $createartefacturl . '">'
        . block_exaport_fontawesome_icon('clone', 'solid', 1) . ' '
        . get_string("add_mixed", "block_exaport") . '</a>';
    if ($type == 'mine') {
        echo '<a class="dropdown-item" href="' . $createcategoryurl . '">'
            . block_exaport_fontawesome_icon('folder', 'solid', 1) . ' '
            . get_string("category", "block_exaport") . '</a>';
    }
    echo '</div>';
    echo '</div>';
}

function block_exaport_get_item_project_icon($item) {
    global $DB, $OUTPUT;

    $hasprojectdata = @$item->project_description || @$item->project_process || @$item->project_result;

    if (!$hasprojectdata) {
        return '';
    }

    $projectinfo = [];
    if (@$item->project_description) {
        $projectinfo[] = '<strong>' . get_string('project_description', 'block_exaport') . ':</strong>';
        $content = $item->project_description;
        $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', context_user::instance($item->userid)->id,
            'block_exaport', 'item_content_project_description', 'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
        $projectinfo[] = $content;
    }
    if (@$item->project_process) {
        $projectinfo[] = '<strong>' . get_string('project_process', 'block_exaport') . ':</strong>';
        $content = $item->project_process;
        $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', context_user::instance($item->userid)->id,
            'block_exaport', 'item_content_project_process', 'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
        $projectinfo[] = $content;
    }
    if (@$item->project_result) {
        $projectinfo[] = '<strong>' . get_string('project_result', 'block_exaport') . ':</strong>';
        $content = $item->project_result;
        $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', context_user::instance($item->userid)->id,
            'block_exaport', 'item_content_project_result', 'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
        $projectinfo[] = $content;
    }

    $projectcontent = implode('<br>', $projectinfo);

    $projectcontent = str_replace("\r", "", $projectcontent);
    $projectcontent = str_replace("\n", "", $projectcontent);
    $projectcontent = str_replace("\"", "&quot;", $projectcontent);
    $projectcontent = str_replace("'", "&prime;", $projectcontent);
    $projectcontent = trim($projectcontent);

    if (!$projectcontent) {
        return '';
    }

    return '<a class="artefact-button" onmouseover="Tip(\'' . $projectcontent . '\')" onmouseout="UnTip()">'
        . block_exaport_fontawesome_icon('rectangle-list', 'regular', 1, [], [], [], '', [], [], [], [])
        //        .'<img src="pix/project.png" width="16" alt="'.get_string('item.project_information', 'block_exaport').'" />'
        . '</a>';
}

function block_exaport_render_item_category_badges($item) {
    if (empty($item->flatcategories) || !is_array($item->flatcategories)) {
        return '';
    }
    $badges = [];
    foreach ($item->flatcategories as $category) {
        $badges[] = html_writer::tag('span', format_string($category->name), ['class' => 'badge badge-secondary']) . ' ';
    }
    if (!$badges) {
        return '';
    }
    return html_writer::div(implode('', $badges), 'mt-2');
}

/**
 * Build a category tree keyed by parent id.
 *
 * @param array $categories
 * @return array
 */
function block_exaport_build_categories_by_parent(array $categories) {
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
 * Load all items for flat mode and attach flatcategories to each item.
 *
 * @param int $userid The user whose items to load.
 * @param array $categories All categories keyed by id (for path name resolution).
 * @param string $sqlsort SQL ORDER BY clause.
 * @param array|null $allowedcategoryids If null, no category filtering is applied. If an empty array is passed, no items are returned.
 *     Otherwise, only these category IDs are included and items with no matching categories are removed.
 * @return array The items array with ->flatcategories populated.
 */
function block_exaport_load_flat_items($userid, array $categories, $sqlsort, $allowedcategoryids = null) {
    global $DB;

    if ($allowedcategoryids !== null && !$allowedcategoryids) {
        return [];
    }

    $items = block_exaport_get_items_by_category_and_user($userid, null, $sqlsort);

    if (!$items) {
        return [];
    }

    $itemids = array_keys($items);
    [$iteminsql, $iteminparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_QM);
    $restrictcategoryuser = !empty($categories[0]->url) && strpos((string)$categories[0]->url, 'type=sharedstudent') !== false;

    $sql = "SELECT ic.id AS icid, ic.itemid, c.id, c.name, c.pid
            FROM {block_exaportitemcate} ic
            JOIN {block_exaportcate} c ON c.id = ic.cateid
            WHERE ic.itemid $iteminsql";
    $params = $iteminparams;

    if ($restrictcategoryuser) {
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
        $itemcategory->name = block_exaport_category_full_path_name($itemcategory->id, $categories);
        if (!isset($categoriesbyitem[$itemcategory->itemid])) {
            $categoriesbyitem[$itemcategory->itemid] = [];
        }
        $categoriesbyitem[$itemcategory->itemid][] = $itemcategory;
    }

    foreach ($items as $itemid => $item) {
        $item->flatcategories = $categoriesbyitem[$item->id] ?? [];
        if ($allowedcategoryids !== null && !$item->flatcategories) {
            unset($items[$itemid]);
        }
    }

    return $items;
}

/**
 * Build the full hierarchical path name for a category, e.g. "haustiere / hunde".
 *
 * @param int $categoryid The category id.
 * @param array $categories Associative array of all categories keyed by id (must have ->name and ->pid).
 * @return string The full path name with " / " separators.
 */
function block_exaport_category_full_path_name($categoryid, array $categories) {
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

function block_exaport_category_path($category, $courseid = 1, $currentcategoryPathItemButtons = '') {
    global $DB, $CFG;
    $pathItem = function($id, $title, $courseid, $selected = false, $currentcategoryPathItemButtons = '') use ($CFG) {
        return '<span class="cat_path_item ' . ($selected ? 'active' : '') . '">'
            . '<a href="' . $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . ($id ? '&categoryid=' . $id : '') . '">'
            . block_exaport_fontawesome_icon('folder', 'regular', 1, [], ['color' => '#7a7a7a']) . '&nbsp;'
            . $title
            . '</a>' . ($selected ? $currentcategoryPathItemButtons : '') . '</span>';
    };
    $path = [];
    if ($category !== null) {
        $currentId = $category->id;

        while ($currentId != NULL) {
            $item = $DB->get_record('block_exaportcate', array('id' => $currentId));
            if (!$item) {
                break;
            }
            array_unshift($path, $pathItem($item->id, $item->name, $courseid, (bool)($category->id == $item->id), $currentcategoryPathItemButtons));
            $currentId = $item->pid;
        }
    }
    // Add root.
    array_unshift($path, $pathItem('', 'Root', $courseid));

    $resultPath = implode('<span class="cat_path_delimeter">/</span>', $path);
    return $resultPath;
}

function block_exaport_category_template_tile($category, $courseid, $type, $currentcategory, $parentcategory = null) {
    global $CFG, $USER, $DB;
    $categoryContent = '';

    $categoryContent .= '<div class="excomdos_tile ';
    if ($parentcategory || ($parentcategory === null) && ($type == 'shared' || $type == 'sharedstudent')) {
        $categoryContent .= 'excomdos_tile_fixed';
    }
    $categoryContent .= ' excomdos_tile_category id-' . $category->id . '">
        <div class="excomdos_tilehead">
                <span class="excomdos_tileinfo">';
    if ($parentcategory) {
        $categoryContent .= block_exaport_get_string('category_up');
    } elseif ($currentcategory->id == -1) {
        $categoryContent .= block_exaport_get_string('user');
    } else {
        $categoryContent .= block_exaport_get_string('category');
    }
    $categoryContent .= '</span>';
    // edit buttons
    if (!$parentcategory) {
        $categoryContent .= '<span class="excomdos_tileedit">';

        if ($category->id == -1) {
            $tempvar = 1; // For code checker.
        } else if ($type == 'shared' || $type == 'sharedstudent') {
            $categoryContent .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
            //                        echo '<img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
        } else {
            // Type == mine.
            if (@$category->internshare && (count(exaport_get_category_shared_users($category->id)) > 0 ||
                    count(exaport_get_category_shared_groups($category->id)) > 0 ||
                    (isset($category->shareall) && $category->shareall == 1))) {
                $categoryContent .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
                //                            echo '<img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
            };
            if (@$category->structure_share) {
                $categoryContent .= ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
            };
            $categoryContent .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $category->id . '&action=edit' . '">'
                . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                //                            .'<img src="pix/edit.png" alt="file"></a>'
                . '<a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $category->id . '&action=delete' . '">'
                . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                //                            .'<img src="pix/del.png" alt="file">'
                . '</a>
        ';
        }
        $categoryContent .= '</span>';
    }
    $categoryContent .= '</div>';
    // category thumbnail
    if ($parentcategory) {
        $categoryThumbUrl = $parentcategory->url;
        $categoryName = $parentcategory->name;
        $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', [], [], [], 'up', [], [], [], ['exaport-items-category-big']);
    } else {
        $categoryThumbUrl = $category->url;
        $categoryName = $category->name;
        if ($category->icon) {
            if ($category->iconmerge) {
                // icon merge (also look JS - exaport.js - block_exaport_check_fontawesome_icon_merging()):
                $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', ['icon-for-merging'], [], ['data-categoryId' => $category->id], '', [], [], [], ['exaport-items-category-big']);
                $categoryIcon .= '<img id="mergeImageIntoCategory' . $category->id . '" src="' . $category->icon . '?tcacheremove=' . date('dmYhis') . '" style="display:none;">';
                $categoryIcon .= '<canvas id="mergedCanvas' . $category->id . '" class="category-merged-icon" width="115" height="115" style="display: none;"></canvas>';
            } else {
                // just picture instead of folder icon:
                $categoryIcon = '<img src="' . $category->icon . '">';
            }
        } else {
            $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', [], [], [], '', [], [], [], ['exaport-items-category-big']);
        }
    }
    $categoryContent .= '<div class="excomdos_tileimage">';
    $categoryContent .= '<a href="' . $categoryThumbUrl . '">';
    $categoryContent .= $categoryIcon;
    $categoryContent .= '</a>
        </div>
        <div class="exomdos_tiletitle">
            <a href="' . $categoryThumbUrl . '">' . $categoryName . '</a>
        </div>
    </div>';

    return $categoryContent;
}

function block_exaport_artefact_template_tile($item, $courseid, $type, $categoryid, $currentcategory) {
    global $CFG, $USER, $DB;
    $itemContent = '';
    $itemcatids = [];
    if (!empty($item->flatcategories) && is_array($item->flatcategories)) {
        foreach ($item->flatcategories as $cat) {
            $itemcatids[] = (int)$cat->id;
        }
    }

    $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid . '&access=portfolio/id/' . $item->userid . '&itemid=' . $item->id;
    $itemContent .= '
        <div class="excomdos_tile excomdos_tile_item exaport-flat-item id-' . $item->id . '" data-item-name="' . s(strtolower($item->name)) . '" data-category-ids="' . s(implode(',', $itemcatids)) . '" data-item-date="' . (int)$item->timemodified . '">
            <div class="excomdos_tilehead">
                    <span class="excomdos_tileinfo">';
    $iconTypeProps = block_exaport_item_icon_type_options($item->type);
    // Artefact type.
    $itemContent .= '<span class="excomdos_tileinfo_type">'
        . block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 1, ['artefact_icon'])
        . '<span class="type_title">'
        . get_string($item->type, "block_exaport")
        . '</span></span>';
    $itemContent .= '
        <br><span class="excomdos_tileinfo_time">' . userdate($item->timemodified) . '</span>
                </span>
            <span class="excomdos_tileedit">';

    if ($currentcategory->id == -1) {
        // Link to export to portfolio.
        $itemContent .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id . '&sesskey=' .
            sesskey() . '&action=copytoself' . '"><img src="pix/import.png" title="' .
            get_string('make_it_yours', "block_exaport") . '"></a>';
    } else {
        if ($item->comments > 0) {
            $itemContent .= ' <span class="excomdos_listcomments">' . $item->comments
                . block_exaport_fontawesome_icon('comment', 'regular', 1, [], [], [], '', [], [], [], [])
                //                                    .'<img src="pix/comments.png" alt="file">'
                . '</span>';
        }
        $itemContent .= block_exaport_get_item_project_icon($item);
        $itemContent .= block_exaport_get_item_comp_icon($item);

        if (in_array($type, ['mine', 'shared'])) {
            $cattype = '';
            if ($type == 'shared') {
                $cattype = '&cattype=shared';
            }
            if ($item->userid == $USER->id) { // only for self!
                $itemContent .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id .
                    '&action=edit' . $cattype . '">'
                    . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                    //                                    .'<img src="pix/edit.png" alt="file">'
                    . '</a>';
            }
            if (($type == 'mine' && $allowedit = block_exaport_item_is_editable($item->id)) // strange condition. If exacomp is not used - always allowed!
                || $item->userid == $USER->id) {
                if ($item->userid == $USER->id) {
                    $itemContent .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id .
                        '&action=delete&categoryid=' . $categoryid . $cattype . '" class="item_delete_icon">'
                        . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                        //                                        .'<img src="pix/del.png" alt="file">'
                        . '</a>';
                }
            } else if (!$allowedit = block_exaport_item_is_editable($item->id)) {
                $itemContent .= '<img src="pix/deleteview.png" alt="file">';
            }
            if ($item->userid != $USER->id) {
                $itemuser = $DB->get_record('user', ['id' => $item->userid]);
                // user icon
                $itemContent .= '<a class="" role="button" data-container="body"
                            ' ./*data-toggle="popover" data-placement="bottom" // popover does not work in Firefox
                            data-content="'.fullname($itemuser).'" tabindex="0" data-trigger="hover".*/
                    '
                            title="' . fullname($itemuser) . '">'
                    . block_exaport_fontawesome_icon('circle-user', 'solid', 1)
                    //                                        .'<img src="pix/personal.png">'
                    . '</a>';
                // echo '<img src="pix/personal.png" alt="'.fullname($itemuser).'" title="'.fullname($itemuser).'">';
            }
        }
    }
    $itemContent .= '
                </span>
        </div>
        <div class="excomdos_tileimage">
            <a href="' . $url . '"><img alt="' . $item->name . '" title="' . $item->name . '"
                                    src="' . $CFG->wwwroot . '/blocks/exaport/item_thumb.php?item_id=' . $item->id . '"/></a>
        </div>
        <div class="exomdos_tiletitle">
            <a href="' . $url . '">' . $item->name . '</a>
            ' . block_exaport_render_item_category_badges($item) . '
        </div>
    </div>';

    return $itemContent;
}

/**
 * Different templates of category list. Depends on exaport settings
 */
function block_exaport_category_list_item($category, $courseid, $type, $currentcategory, $parentcategory = null) {
    $template = block_exaport_used_layout();
    switch ($template) {
        case 'moodle_bootstrap':
            return block_exaport_category_template_bootstrap_card($category, $courseid, $type, $currentcategory, $parentcategory);
            break;
        case 'exaport_bootstrap': // may we do not need this at all?
            return '<div>TODO: !!!!!! ' . $template . ' category !!!!!!!</div>';
            break;
        case 'clean_old':
            return block_exaport_category_template_tile($category, $courseid, $type, $currentcategory, $parentcategory);
            break;
    }
    return 'something wrong!! (code: 1716992027125)';

}

/**
 * Different templates of artefact list. Depends on exaport settings
 */
function block_exaport_artefact_list_item($item, $courseid, $type, $categoryid, $currentcategory) {
    $template = block_exaport_used_layout();
    switch ($template) {
        case 'moodle_bootstrap':
            return block_exaport_artefact_template_bootstrap_card($item, $courseid, $type, $categoryid, $currentcategory);
            break;
        case 'exaport_bootstrap': // may we do not need this at all?
            return '<div>TODO: !!!!!! ' . $template . ' !!!!!!!</div>';
            break;
        case 'clean_old':
            return block_exaport_artefact_template_tile($item, $courseid, $type, $categoryid, $currentcategory);
            break;
    }
    return 'something wrong!! (code: 1716990501476)';

}

function block_exaport_category_template_bootstrap_card($category, $courseid, $type, $currentcategory, $parentcategory = null) {
    global $CFG;
    $categoryContent = '';

    $categoryContent .= '
    <div class="col mb-4">
				<div class="card h-100 excomdos_tile excomdos_tile_category id-' . $category->id . ' ">
					<div class="card-header excomdos_tilehead d-flex justify-content-between">
						<span class="excomdos_tileinfo">
							';
    if ($parentcategory) {
        $categoryContent .= block_exaport_get_string('category_up');
    } elseif ($currentcategory->id == -1) {
        $categoryContent .= block_exaport_get_string('user');
    } else {
        $categoryContent .= block_exaport_get_string('category');
    }
    $categoryContent .= '</span>';
    // edit buttons
    if (!$parentcategory) {
        if ($type == 'shared' || $type == 'sharedstudent') {
            $categoryContent .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
        } else {
            // Type == mine.
            if (@$category->internshare && (count(exaport_get_category_shared_users($category->id)) > 0 ||
                    count(exaport_get_category_shared_groups($category->id)) > 0 ||
                    (isset($category->shareall) && $category->shareall == 1))) {
                $categoryContent .= block_exaport_fontawesome_icon('handshake', 'regular', 1);
            };
            /*if (@$category->structure_share) {
                $categoryContent .= ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
            };*/
            $categoryContent .= '
						<span class="excomdos_tileedit">
							<a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $category->id . '&action=edit' . '">'
                . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                . '</a>
							<a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $courseid . '&id=' . $category->id . '&action=delete' . '">'
                . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                . '</a>
						</span>';
        }
    }
    if ($parentcategory) {
        $categoryThumbUrl = $parentcategory->url;
        $categoryName = $parentcategory->name;
        $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', [], [], [], 'up', [], [], [], ['exaport-items-category-big']);
    } else {
        $categoryThumbUrl = $category->url;
        $categoryName = $category->name;
        if ($category->icon) {
            if ($category->iconmerge) {
                // icon merge (also look JS - exaport.js - block_exaport_check_fontawesome_icon_merging()):
                $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', ['icon-for-merging'], [], ['data-categoryId' => $category->id], '', [], [], [], ['exaport-items-category-big']);
                $categoryIcon .= '<img id="mergeImageIntoCategory' . $category->id . '" src="' . $category->icon . '?tcacheremove=' . date('dmYhis') . '" style="display:none;">';
                $categoryIcon .= '<canvas id="mergedCanvas' . $category->id . '" class="category-merged-icon" width="115" height="115" style="display: none;"></canvas>';
            } else {
                // just picture instead of folder icon:
                $categoryIcon = '<img src="' . $category->icon . '">';
            }
        } else {
            $categoryIcon = block_exaport_fontawesome_icon('folder-open', 'regular', '6', [], [], [], '', [], [], [], ['exaport-items-category-big']);
        }
    }
    $categoryContent .= '
                    </div>
					<div class="card-body excomdos_tileimage d-flex justify-content-center align-items-center">
						<a href="' . $categoryThumbUrl . '">
						    ' . $categoryIcon . '
						</a>
					</div>
					<div class="card-extitle exomdos_tiletitle">
						<a href="' . $categoryThumbUrl . '">' . $categoryName . '</a>
					</div>
				</div>
			</div>
    ';

    return $categoryContent;
}

;

function block_exaport_artefact_template_bootstrap_card($item, $courseid, $type, $categoryid, $currentcategory) {
    global $CFG, $USER, $DB;

    $iconTypeProps = block_exaport_item_icon_type_options($item->type);
    $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid . '&access=portfolio/id/' . $item->userid . '&itemid=' . $item->id;

    // Build category IDs for client-side filtering.
    $itemCatIds = [];
    if (!empty($item->flatcategories) && is_array($item->flatcategories)) {
        foreach ($item->flatcategories as $cat) {
            $itemCatIds[] = (int)$cat->id;
        }
    }

    $itemContent = '
        <div class="col mb-4 exaport-flat-item" data-item-name="' . s(strtolower($item->name)) . '" data-category-ids="' . s(implode(',', $itemCatIds)) . '" data-item-date="' . (int)$item->timemodified . '">
				<div class="card h-100 excomdos_tile excomdos_tile_item id-13 ui-draggable ui-draggable-handle">
					<div class="card-header excomdos_tilehead d-flex justify-content-between flex-wrap">
						<div class="excomdos_tileinfo">
							<span class="excomdos_tileinfo_type">'
        . block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 1, ['artefact_icon'])
        . '<span class="type_title">' . get_string($item->type, "block_exaport") . '</span></span>
						</div>
						<div class="excomdos_tileedit">';

    if ($currentcategory->id == -1) {
        // Link to export to portfolio.
        $itemContent .= ' <a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id . '&sesskey=' .
            sesskey() . '&action=copytoself' . '"><img src="pix/import.png" title="' .
            get_string('make_it_yours', "block_exaport") . '"></a>';
    } else {
        if ($item->comments > 0) {
            $itemContent .= ' <span class="excomdos_listcomments">' . $item->comments
                . block_exaport_fontawesome_icon('comment', 'regular', 1, [], [], [], '', [], [], [], [])
                . '</span>';
        }
        $itemContent .= block_exaport_get_item_project_icon($item);
        $itemContent .= block_exaport_get_item_comp_icon($item);

        if (in_array($type, ['mine', 'shared'])) {
            $cattype = '';
            if ($type == 'shared') {
                $cattype = '&cattype=shared';
            }
            if ($item->userid == $USER->id) { // only for self!
                $itemContent .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id . '&action=edit' . $cattype . '">'
                    . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
                    . '</a>';
            }
            if (($type == 'mine' && $allowedit = block_exaport_item_is_editable($item->id)) // strange condition. If exacomp is not used - always allowed!
                || $item->userid == $USER->id) {
                if ($item->userid == $USER->id) {
                    $itemContent .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id . '&action=delete&categoryid=' . $categoryid . $cattype . '" class="item_delete_icon">'
                        . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
                        . '</a>';
                }
            } else if (!$allowedit = block_exaport_item_is_editable($item->id)) {
                $itemContent .= '<img src="pix/deleteview.png" alt="file">';
            }
            if ($item->userid != $USER->id) {
                $itemuser = $DB->get_record('user', ['id' => $item->userid]);
                // user icon
                $itemContent .= '<a class="" role="button" data-container="body"
                            title="' . fullname($itemuser) . '">'
                    . block_exaport_fontawesome_icon('circle-user', 'solid', 1)
                    . '</a>';
            }
        }
    }

    $itemContent .= '</div>
					</div>
					<div class="card-body excomdos_tileimage d-flex justify-content-center align-items-center">
					    <a href="' . $url . '">
					        <img height="75" alt="' . $item->name . '" title="' . $item->name . '" src="' . $CFG->wwwroot . '/blocks/exaport/item_thumb.php?item_id=' . $item->id . '"/>
                        </a>
					</div>
					<div class="card-extitle exomdos_tiletitle">
						<a href="' . $url . '">' . $item->name . '</a>
						' . block_exaport_render_item_category_badges($item) . '
					</div>
					<div class="card-footer excomdos_tileinfo_time mt-2">
                        ' . date('d.m.Y H:i', $item->timemodified) . '
					</div>
				</div>
			</div>
    ';

    return $itemContent;
}
