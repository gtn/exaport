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
$userid = optional_param('userid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_TEXT);
$layout = optional_param('layout', '', PARAM_TEXT);
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

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
    $sort = $userpreferences->itemsort;
}

if ($type != 'shared' && $type != 'sharedstudent') {
    $type = 'mine';
}

// What's the display layout: tiles / details?
if (!$layout && isset($userpreferences->view_items_layout)) {
    $layout = $userpreferences->view_items_layout;
}
if ($layout != 'details') {
    $layout = 'tiles';
} // Default = tiles.

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
                                , COUNT(i.id) AS item_cnt
                            FROM {block_exaportcate} c
                            LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND " . block_exaport_get_item_where() . "
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
        $categoriesbyparent = array();
        foreach ($categories as $category) {
            if (!isset($categoriesbyparent[$category->pid])) {
                $categoriesbyparent[$category->pid] = array();
            }
            $categoriesbyparent[$category->pid][] = $category;
        }

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

        // Common items.
        $items = $DB->get_records_sql("
            SELECT DISTINCT i.*, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE i.userid = ?
                AND i.categoryid=?
                AND " . block_exaport_get_item_where() .
            " GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro,
            i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
            i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
            i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
            i.iseditable, i.example_url, i.parentid
            $sqlsort
        ", [$selecteduser->id, $currentcategory->id]);
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
            $userpicture->size = ($layout == 'tiles' ? 100 : 32);
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
                , COUNT(i.id) AS item_cnt
            FROM {block_exaportcate} c
            LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND " . block_exaport_get_item_where() . "
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

        // Build a tree according to parent.
        $categoriesbyparent = [];
        foreach ($categories as $category) {
            $category->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . '&type=shared&userid=' . $userid .
                '&categoryid=' . $category->id;
            $category->icon = block_exaport_get_category_icon($category);

            if (!isset($categoriesbyparent[$category->pid])) {
                $categoriesbyparent[$category->pid] = array();
            }
            $categoriesbyparent[$category->pid][] = $category;
        }

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

        $usercondition = ' i.userid = ' . intval($selecteduser->id) . ' ';
        if ($type == 'shared') {
            $usercondition = ' i.userid > 0 ';
        }

        $items = $DB->get_records_sql("
            SELECT DISTINCT i.*, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE i.categoryid = ?
                AND " . $usercondition . "
                AND " . block_exaport_get_item_where() .
            " GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro,
            i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
            i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
            i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
            i.iseditable, i.example_url, i.parentid
            $sqlsort
        ", [$currentcategory->id]);
    }

} else {
    // Read all categories.
    $categories = block_exaport_get_all_categories_for_user($USER->id);

    foreach ($categories as $category) {
        $category->url = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . '&categoryid=' . $category->id;
        $category->icon = block_exaport_get_category_icon($category);
    }

    // Build a tree according to parent.
    $categoriesbyparent = array();
    foreach ($categories as $category) {
        if (!isset($categoriesbyparent[$category->pid])) {
            $categoriesbyparent[$category->pid] = array();
        }
        $categoriesbyparent[$category->pid][] = $category;
    }

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

    // Common items.
    $items = block_exaport_get_items_by_category_and_user($USER->id, $currentcategory->id, $sqlsort, true);
}

$PAGE->set_url($currentcategory->url);
$PAGE->set_context(context_system::instance());

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

// Save user preferences.
block_exaport_set_user_preferences(array('itemsort' => $sort, 'view_items_layout' => $layout));

echo '<div class="excomdos_cont layout_' . block_exaport_used_layout() . ' excomdos_cont-type-' . $type . '">';
if ($type == 'mine') {
    echo get_string("categories", "block_exaport") . ": ";
    echo '<select onchange="document.location.href=\'' . $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid .
        '&categoryid=\'+this.value;">';
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
}

echo '<div class="excomdos_additem ' . ($useBootstrapLayout ? 'd-flex justify-content-between align-items-center flex-column flex-sm-row' : '') . '">';
if (in_array($type, ['mine', 'shared'])) {
    $cattype = '';
    if ($type == 'shared') {
        $cattype = '&cattype=shared';
    }
    echo '<div class="excomdos_additem_content">';
    if ($type == 'mine') {
        echo '<span><a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?action=add&courseid=' . $courseid . '&pid=' . $categoryid . '">'
            . block_exaport_fontawesome_icon('folder', 'solid', 2, [], ['color' => '#7a7a7a'], [], 'add') . '<br />'
            . get_string("category", "block_exaport") . "</a></span>";
    }
    // Add "Mixed" artefact
    echo '<span><a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?action=add&courseid=' . $courseid . '&categoryid=' . $categoryid . $cattype
        . '&type=mixed">'
        . block_exaport_fontawesome_icon('clone', 'solid', 2, [], [], ['data-fa-transform' => 'flip-h flip-v'],
            'add', [], [], ['data-fa-transform' => 'shrink-7 down-4 right-8'])
        . '<br />' . get_string("add_mixed", "block_exaport") . "</a></span>";
    // Next types are disabled after adding 'mixed' type. Real artefact type will be changed after filling fields.
    // These types are hidden only in this view. All other functions are working with types as before.
    /*
    // Add "Link" artefact.
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=link">'.
            '<img src="pix/link_new_32.png" /><br />'.get_string("link", "block_exaport")."</a></span>";
    // Add "File" artefact.
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=file">'.
            '<img src="pix/file_new_32.png" /><br />'.get_string("file", "block_exaport")."</a></span>";
    // Add "Note" artefact.
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=note">'.
            '<img src="pix/note_new_32.png" /><br />'.get_string("note", "block_exaport")."</a></span>";
    */
    // Anzeigen wenn kategorien vorhanden zum importieren aus sprachfile.
    if ($type == 'mine') {
        $categories = trim(get_string("lang_categories", "block_exaport"));
        if ($categories) {
            echo '<span><a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?action=addstdcat&courseid=' . $courseid . '">' .
                '<img src="pix/folder_new_32.png" /><br />' . get_string("addstdcat", "block_exaport") . "</a></span>";
        }
    }
    echo '</div>';
}

echo '<div class="excomdos_changeview ' . ($useBootstrapLayout ? 'my-4 my-sm-0 align-self-end align-self-sm-center' : '') . '"><p>';
//echo '<span>'.block_exaport_get_string('change_layout').':</span>';
if ($layout == 'tiles') {
    echo '<span><a href="' . $PAGE->url->out(true, ['layout' => 'details']) . '">'
        . block_exaport_fontawesome_icon('list', 'solid', '2')
        //        .'<img src="pix/view_list.png" alt="Tile View" />'
        . '<br />' . block_exaport_get_string("details") . "</a></span>";
} else {
    echo '<span><a href="' . $PAGE->url->out(true, ['layout' => 'tiles']) . '">'
        . block_exaport_fontawesome_icon('table-cells-large', 'solid', '2')
        //            .'<img src="pix/view_tile.png" alt="Tile View" />'
        . '<br />' . block_exaport_get_string("tiles") . "</a></span>";
}

if ($type == 'mine') {
    echo '<span><a target="_blank" href="' . $CFG->wwwroot . '/blocks/exaport/view_items_print.php?courseid=' . $courseid . '">'
        . block_exaport_fontawesome_icon('print', 'solid', '2')
        //            .'<img src="pix/view_print.png" alt="Tile View" />'
        . '<br />' . get_string("printerfriendly", "group") . "</a></span>";
}
echo '</p></div></div>';

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

if ($layout == 'details') {
    $table = new html_table();
    $table->width = "100%";

    $table->head = array();
    $table->size = array();

    $table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=" .
        ($sortkey == 'type' ? $newsort : 'type') . "'>" . get_string("type", "block_exaport") . "</a>";
    $table->size['type'] = "10";

    $table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=" .
        ($sortkey == 'name' ? $newsort : 'name') . "'>" . get_string("name", "block_exaport") . "</a>";
    $table->size['name'] = "60";

    $table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=" .
        ($sortkey == 'date' ? $newsort : 'date.desc') . "'>" . get_string("date", "block_exaport") . "</a>";
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

    $itemscnt = count($items);
    foreach ($items as $item) {
        $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid . '&access=portfolio/id/' . $item->userid . '&itemid=' .
            $item->id;

        $itemind++;

        $table->data[$itemind] = array();

        //        $imgtype = '<img src="pix/'.$item->type.'_32.png" alt="'.get_string($item->type, "block_exaport").'">';
        //        $imgtype = '<img src="pix/'.$item->type.'_icon.png" alt="'.get_string($item->type, "block_exaport").'" title="'.get_string($item->type, "block_exaport").'" width="32">';
        // Artefact type.
        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $imgtype = block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 2, [], [], [], '', [], [], [], ['exaport-items-type-icon']);

        $table->data[$itemind]['type'] = $imgtype;

        $table->data[$itemind]['name'] = "<a href=\"" . s($url) . "\">" . $item->name . "</a>";
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
                $table->data[$itemind]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">" .
                    format_text($intro, FORMAT_HTML) . "</td></tr></table>";
            } else {
                // Display show/hide buttons.
                $table->data[$itemind]['name'] .= '<div><div id="short-preview-' . $itemind . '"><div>' . $shortintro . '...</div>
                        <a href="javascript:long_preview_show(' . $itemind . ')">[' . get_string('more') . '...]</a>
                        </div>
                        <div id="long-preview-' . $itemind . '" style="display: none;"><div>' . $intro . '</div>
                        <a href="javascript:long_preview_hide(' . $itemind . ')">[' . strtolower(get_string('hide')) . '...]</a>
                        </div>';
            }
        }

        $table->data[$itemind]['date'] = userdate($item->timemodified);

        $icons = '';

        // Link to export to my portfolio.
        if ($currentcategory->id == -1) {
            $table->data[$itemind]['icons'] = '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid .
                '&id=' . $item->id . '&sesskey=' . sesskey() . '&action=copytoself' . '">' .
                '<img src="pix/import.png" title="' . get_string('make_it_yours', "block_exaport") . '"></a>';
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

        $table->data[$itemind]['icons'] = $icons;
    }

    echo html_writer::table($table);
} else {
    echo '<div class="excomdos_tiletable ' . ($useBootstrapLayout ? 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5' : '') . '">';
    echo '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>';

    // show a link to parent category
    if ($parentcategory) {
        echo block_exaport_category_list_item($category, $courseid, $type, $currentcategory, $parentcategory);
    }

    foreach ($subcategories as $category) {
        echo block_exaport_category_list_item($category, $courseid, $type, $currentcategory, null);
    }

    foreach ($items as $item) {
        echo block_exaport_artefact_list_item($item, $courseid, $type, $categoryid, $currentcategory);
    }

    echo '</div>';
}

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

    $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid . '&access=portfolio/id/' . $item->userid . '&itemid=' . $item->id;
    $itemContent .= '
        <div class="excomdos_tile excomdos_tile_item id-' . $item->id . '">
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

    $itemContent = '
        <div class="col mb-4">
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
					</div>
					<div class="card-footer excomdos_tileinfo_time mt-2">
                        ' . date('d.m.Y H:i', $item->timemodified) . '
					</div>
				</div>
			</div>
    ';

    return $itemContent;
}
