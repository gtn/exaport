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

require_once(__DIR__.'/inc.php');

use block_exaport\globals as g;

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_TEXT);
$layout = optional_param('layout', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_TEXT);

$wstoken = optional_param('wstoken', null, PARAM_RAW);

require_once($CFG->dirroot.'/webservice/lib.php');

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
$sort = $parsedsort[0].'.'.$parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
    $newsort = $sortkey.".asc";
} else {
    $newsort = $sortkey.".desc";
}
$sorticon = $parsedsort[1].'.png';
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
                            LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND ".block_exaport_get_item_where()."
                            WHERE c.userid = ?
                            GROUP BY
                                {$categorycolumns}
                            ORDER BY c.name ASC
                        ", array($selecteduser->id));

        foreach ($categories as $category) {
            $category->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&userid='.$selecteduser->id.
                                            '&type=sharedstudent&categoryid='.$category->id;
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
        $rootcategory->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$COURSE->id.
                                                    '&type=sharedstudent&userid='.$selecteduser->id;
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
            $parentcategory = (object) [
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
                AND ".block_exaport_get_item_where().
                " GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro,
            i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
            i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
            i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
            i.iseditable, i.example_url, i.parentid
            $sqlsort
        ", [$selecteduser->id, $currentcategory->id]);
    }

} else if ($type == 'shared') {
    $rootcategory = (object) [
            'id' => 0,
            'pid' => 0,
            'name' => block_exaport_get_string('shareditems_category'),
            'item_cnt' => '',
            'url' => $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$COURSE->id.'&type=shared',
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
    } elseif (!$selecteduser) {
        throw new moodle_exception('wrong userid');
    } else {
        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        $categories = $DB->get_records_sql("
            SELECT
                {$categorycolumns}
                , COUNT(i.id) AS item_cnt
            FROM {block_exaportcate} c
            LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND ".block_exaport_get_item_where()."
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
            $category->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&type=shared&userid='.$userid.
                    '&categoryid='.$category->id;
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
            $parentcategory = (object) [
                    'id' => 0,
                    'url' => new moodle_url('shared_categories.php', ['courseid' => $COURSE->id]),
                    'name' => '',
            ];
        }

        if (!category_allowed($selecteduser, $categories, $currentcategory)) {
            throw new moodle_exception('not allowed');
        }

        $usercondition = ' i.userid = '.intval($selecteduser->id). ' ';
        if ($type == 'shared') {
            $usercondition = ' i.userid > 0 ';
        }

        $items = $DB->get_records_sql("
            SELECT DISTINCT i.*, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE i.categoryid = ?
                AND ".$usercondition."
                AND ".block_exaport_get_item_where().
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
    $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
    $categories = $DB->get_records_sql("
        SELECT
            {$categorycolumns}
            , COUNT(i.id) AS item_cnt
        FROM {block_exaportcate} c
        LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND ".block_exaport_get_item_where()."
        WHERE c.userid = ?
        GROUP BY
            {$categorycolumns}
        ORDER BY c.name ASC
    ", array($USER->id));

    foreach ($categories as $category) {
        $category->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id;
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
    // SZ 14.10.2020 - shows not only own items. here can be items from other users if the folder was shared
    $items = $DB->get_records_sql("
            SELECT DISTINCT i.*, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE "/*i.userid = ? AND*/." i.categoryid = ? ".($currentcategory->id > 0 ? "" : " AND i.userid = ? " )."
                AND ".block_exaport_get_item_where().
            " GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro,
            i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess,
            i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url,
            i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
            i.iseditable, i.example_url, i.parentid
            $sqlsort
        ", [$currentcategory->id, $USER->id]);
}

$PAGE->set_url($currentcategory->url);

block_exaport_print_header($type == 'shared' || $type == 'sharedstudent' ? 'shared_categories' : "myportfolio");

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) {
    $pref = "desp_";
} else {
    $pref = "";
}
echo $OUTPUT->box(text_to_html(get_string($pref."explaining", "block_exaport")), "center");
echo "</div>";

// Save user preferences.
block_exaport_set_user_preferences(array('itemsort' => $sort, 'view_items_layout' => $layout));

echo '<div class="excomdos_cont excomdos_cont-type-'.$type.'">';
if ($type == 'mine') {
    echo get_string("categories", "block_exaport").": ";
    echo '<select onchange="document.location.href=\''.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.
            '&categoryid=\'+this.value;">';
    echo '<option value="">';
    echo $rootcategory->name;
    if ($rootcategory->item_cnt) {
        echo ' ('.$rootcategory->item_cnt.' '.block_exaport_get_string($rootcategory->item_cnt == 1 ? 'item' : 'items').')';
    }
    echo '</option>';
    function block_exaport_print_category_select($categoriesbyparent, $currentcategoryid, $pid = 0, $level = 0) {
        if (!isset($categoriesbyparent[$pid])) {
            return;
        }

        foreach ($categoriesbyparent[$pid] as $category) {
            echo '<option value="'.$category->id.'"'.($currentcategoryid == $category->id ? ' selected="selected"' : '').'>';
            if ($level) {
                echo str_repeat('&nbsp;', 4 * $level).' &rarr;&nbsp; ';
            }
            echo $category->name;
            if ($category->item_cnt) {
                echo ' ('.$category->item_cnt.' '.block_exaport_get_string($category->item_cnt == 1 ? 'item' : 'items').')';
            }
            echo '</option>';
            block_exaport_print_category_select($categoriesbyparent, $currentcategoryid,
                    $category->id, $level + 1);
        }
    }

    block_exaport_print_category_select($categoriesbyparent, $currentcategory->id);
    echo '</select>';
}

echo '<div class="excomdos_additem">';
if (in_array($type, ['mine', 'shared'])) {
    $cattype = '';
    if ($type == 'shared') {
        $cattype = '&cattype=shared';
    }
    echo '<div class="excomdos_additem_content">';
    if ($type == 'mine') {
        echo '<span><a href="' . $CFG->wwwroot . '/blocks/exaport/category.php?action=add&courseid=' . $courseid . '&pid=' . $categoryid . '">' .
            '<img src="pix/folder_new_32.png" /><br />' . get_string("category", "block_exaport") . "</a></span>";
    }
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=link">'.
            '<img src="pix/link_new_32.png" /><br />'.get_string("link", "block_exaport")."</a></span>";
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=file">'.
            '<img src="pix/file_new_32.png" /><br />'.get_string("file", "block_exaport")."</a></span>";
    echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.$cattype.
            '&type=note">'.
            '<img src="pix/note_new_32.png" /><br />'.get_string("note", "block_exaport")."</a></span>";
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

echo '<div class="excomdos_changeview"><p>';
echo '<span>'.block_exaport_get_string('change_layout').':</span>';
if ($layout == 'tiles') {
    echo '<span><a href="'.$PAGE->url->out(true, ['layout' => 'details']).'">'.
            '<img src="pix/view_list.png" alt="Tile View" /><br />'.block_exaport_get_string("details")."</a></span>";
} else {
    echo '<span><a href="'.$PAGE->url->out(true, ['layout' => 'tiles']).'">'.
            '<img src="pix/view_tile.png" alt="Tile View" /><br />'.block_exaport_get_string("tiles")."</a></span>";
}

if ($type == 'mine') {
    echo '<span><a target="_blank" href="'.$CFG->wwwroot.'/blocks/exaport/view_items_print.php?courseid='.$courseid.'">'.
            '<img src="pix/view_print.png" alt="Tile View" /><br />'.get_string("printerfriendly", "group")."</a></span>";
}
echo '</p></div></div>';

echo '<div class="excomdos_cat">';
echo block_exaport_get_string('current_category').': ';

echo '<b>';
if (($type == 'shared' || $type == 'sharedstudent') && $selecteduser) {
    echo $selecteduser->name.' / ';
}
echo $currentcategory->name;
echo '</b> ';

if ($type == 'mine' && $currentcategory->id > 0) {
    if (@$currentcategory->internshare && (count(exaport_get_category_shared_users($currentcategory->id)) > 0 ||
                    count(exaport_get_category_shared_groups($currentcategory->id)) > 0 || $currentcategory->shareall == 1)
    ) {
        echo ' <img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
    }
    echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentcategory->id.
            '&action=edit&back=same"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
    echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentcategory->id.
            '&action=delete&back=same"><img src="pix/del.png" alt="'.get_string("delete").'"/></a>';
} else if ($type == 'shared' && $selecteduser && $categoryid) {
    $tempvar = 1; // For code checker.
    // When category selected, allow copy.
    /*
    $url = $PAGE->url->out(true, ['action'=>'copy']);
    echo '<button onclick="document.location.href=\'shared_categories.php?courseid='.$courseid.'&action=copy&categoryid='.$categoryid.'\'">'.block_exaport_get_string('copycategory').'</button>';
    */
}
echo '</div>';

if ($layout == 'details') {
    $table = new html_table();
    $table->width = "100%";

    $table->head = array();
    $table->size = array();

    $table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
            ($sortkey == 'type' ? $newsort : 'type')."'>".get_string("type", "block_exaport")."</a>";
    $table->size['type'] = "10";

    $table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
            ($sortkey == 'name' ? $newsort : 'name')."'>".get_string("name", "block_exaport")."</a>";
    $table->size['name'] = "60";

    $table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
            ($sortkey == 'date' ? $newsort : 'date.desc')."'>".get_string("date", "block_exaport")."</a>";
    $table->size['date'] = "20";

    $table->head['icons'] = '';
    $table->size['icons'] = "10";

    // Add arrow to heading if available.
    if (isset($table->head[$sortkey])) {
        $table->head[$sortkey] = "<img src=\"pix/$sorticon\" alt='".get_string("updownarrow", "block_exaport")."' /> ".
                                 $table->head[$sortkey];
    }

    $table->data = Array();
    $itemind = -1;

    if ($parentcategory) {
        // If isn't parent category, show link to go to parent category.
        $itemind++;
        $table->data[$itemind] = array();
        $table->data[$itemind]['type'] = '<img src="pix/folderup_32.png" alt="'.block_exaport_get_string('category').'">';

        $table->data[$itemind]['name'] = '<a href="'.$parentcategory->url.'">'.$parentcategory->name.'</a>';
        $table->data[$itemind][] = null;
        $table->data[$itemind][] = null;
    }

    foreach ($subcategories as $category) {
        // Checking for shared items. If userid is null - show users, if userid > 0 - need to show items from user.
        $itemind++;
        $table->data[$itemind] = array();
        $table->data[$itemind]['type'] = '<img src="'.(@$category->icon ?: 'pix/folder_32_user.png').'" style="max-width:32px">';

        $table->data[$itemind]['name'] = '<a href="'.$category->url.'">'.$category->name.'</a>';

        $table->data[$itemind][] = null;

        if ($type == 'mine' && $category->id > 0) {
            $table->data[$itemind]['icons'] = '<span class="excomdos_listicons">';
            if ((isset($category->internshare) && $category->internshare == 1) &&
                    (count(exaport_get_category_shared_users($category->id)) > 0 ||
                            count(exaport_get_category_shared_groups($category->id)) > 0 ||
                            (isset($category->shareall) && $category->shareall == 1))
            ) {
                $table->data[$itemind]['icons'] .= '<img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
            };
            if (@$category->structure_share) {
                $table->data[$itemind]['icons'] .= ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
            }

            $table->data[$itemind]['icons'] .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.
                    '&id='.$category->id.'&action=edit"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
                    ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.
                    '&action=delete"><img src="pix/del.png" alt="'.get_string("delete").'"/></a>'.
                    '</span>';
        } else { // Category with shared items.
            $table->data[$itemind]['icons'] = '';
        }
    }

    $itemscnt = count($items);
    foreach ($items as $item) {
        $url = $CFG->wwwroot.'/blocks/exaport/shared_item.php?courseid='.$courseid.'&access=portfolio/id/'.$item->userid.'&itemid='.
                $item->id;

        $itemind++;

        $table->data[$itemind] = array();

        $imgtype = '<img src="pix/'.$item->type.'_32.png" alt="'.get_string($item->type, "block_exaport").'">';
        $table->data[$itemind]['type'] = $imgtype;

        $table->data[$itemind]['name'] = "<a href=\"".s($url)."\">".$item->name."</a>";
        if ($item->intro) {
            $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
                    'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);

            $shortintro = substr(trim(strip_tags($intro)), 0, 20);
            if (preg_match_all('#(?:<iframe[^>]*)(?:(?:/>)|(?:>.*?</iframe>))#i', $intro, $matches)) {
                $shortintro = $matches[0][0];
            }

            if (!$intro) {
                $tempvar = 1; // For code checker.
                // No intro.
            } else if ($shortintro == $intro) {
                // Very short one.
                $table->data[$itemind]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".
                                                    format_text($intro, FORMAT_HTML)."</td></tr></table>";
            } else {
                // Display show/hide buttons.
                $table->data[$itemind]['name'] .= '<div><div id="short-preview-'.$itemind.'"><div>'.$shortintro.'...</div>
                        <a href="javascript:long_preview_show('.$itemind.')">['.get_string('more').'...]</a>
                        </div>
                        <div id="long-preview-'.$itemind.'" style="display: none;"><div>'.$intro.'</div>
                        <a href="javascript:long_preview_hide('.$itemind.')">['.strtolower(get_string('hide')).'...]</a>
                        </div>';
            }
        }

        $table->data[$itemind]['date'] = userdate($item->timemodified);

        $icons = '';

        // Link to export to my portfolio.
        if ($currentcategory->id == -1) {
            $table->data[$itemind]['icons'] = '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.
                    '&id='.$item->id.'&sesskey='.sesskey().'&action=copytoself'.'">'.
                    '<img src="pix/import.png" title="'.get_string('make_it_yours', "block_exaport").'"></a>';
            continue;
        };

        if (isset($item->comments) && $item->comments > 0) {
            $icons .= '<span class="excomdos_listcomments">
                            <a href="'.$url.'" > 
                        '.$item->comments.'<img src="pix/comments.png" alt="file">
                            </a>
                        </span>';
        }

        $icons .= block_exaport_get_item_comp_icon($item);

        // Copy files to course.
        if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course')) {
            $icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/copy_item_to_course.php?courseid='.$courseid.'&itemid='.$item->id.
                    '&backtype=">'.get_string("copyitemtocourse", "block_exaport").'</a>';
        }

        if ($type == 'mine') {
            $icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.
                    '&action=edit"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
            if ($allowedit = block_exaport_item_is_editable($item->id)) {
                $icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.
                        '&action=delete&categoryid='.$categoryid.'"><img src="pix/del.png" alt="'.get_string("delete").'"/></a>';
            } else {
                $icons .= '<img src="pix/deleteview.png" alt="'.get_string("delete").'">';
            }
        }

        $icons = '<span class="excomdos_listicons">'.$icons.'</span>';

        $table->data[$itemind]['icons'] = $icons;
    }

    echo html_writer::table($table);
} else {
    echo '<div class="excomdos_tiletable">';
    echo '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>';

    if ($parentcategory) {
        ?>
        <div class="excomdos_tile excomdos_tile_fixed excomdos_tile_category id-<?php echo $parentcategory->id; ?>">
            <div class="excomdos_tilehead">
                <span class="excomdos_tileinfo">
                    <?php echo block_exaport_get_string('category_up'); ?>
                    <br>
                </span>
            </div>
            <div class="excomdos_tileimage">
                <a href="<?php echo $parentcategory->url; ?>"><img src="pix/folderup_tile.png"></a>
            </div>
            <div class="exomdos_tiletitle">
                <a href="<?php echo $parentcategory->url; ?>"><?php echo $parentcategory->name; ?></a>
            </div>
        </div>
        <?php
    }

    foreach ($subcategories as $category) {
        ?>
        <div class="excomdos_tile <?php
        if ($type == 'shared' || $type == 'sharedstudent') {
            echo 'excomdos_tile_fixed';
        }
        ?> excomdos_tile_category id-<?php echo $category->id; ?>">
            <div class="excomdos_tilehead">
                <span class="excomdos_tileinfo">
                    <?php
                    if ($currentcategory->id == -1) {
                        echo block_exaport_get_string('user');
                    } else {
                        echo block_exaport_get_string('category');
                    }
                    ?>
                </span>
                <span class="excomdos_tileedit">
                    <?php
                    if ($category->id == -1) {
                        $tempvar = 1; // For code checker.
                    } else if ($type == 'shared' || $type == 'sharedstudent') {
                        ?>
                        <img src="pix/noteitshared.gif" alt="file" title="shared to other users">
                        <?php
                    } else {
                        // Type == mine.
                        if (@$category->internshare && (count(exaport_get_category_shared_users($category->id)) > 0 ||
                                        count(exaport_get_category_shared_groups($category->id)) > 0 ||
                                        (isset($category->shareall) && $category->shareall == 1))) {
                            ?>
                            <img src="pix/noteitshared.gif" alt="file" title="shared to other users">
                        <?php
                        };
                        if (@$category->structure_share) {
                            echo ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
                        };
                        ?>
                        <a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.
                                '&action=edit'; ?>"><img src="pix/edit.png" alt="file"></a>
                        <a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.
                                '&action=delete'; ?>"><img src="pix/del.png" alt="file"></a>
                        <?php
                    }
                    ?>
                </span>
            </div>
            <div class="excomdos_tileimage">
                <a href="<?php echo $category->url; ?>">
                    <?php
                    $imgurl = @$category->icon ?: 'pix/folder_tile.png';
                    echo '<img src="'.$imgurl.'">';
                    ?>
                </a>
            </div>
            <div class="exomdos_tiletitle">
                <a href="<?php echo $category->url; ?>"><?php echo $category->name; ?></a>
            </div>
        </div>
        <?php
    }

    foreach ($items as $item) {
        $url = $CFG->wwwroot.'/blocks/exaport/shared_item.php?courseid='.$courseid.'&access=portfolio/id/'.$item->userid.'&itemid='.
                $item->id;
        ?>
        <div class="excomdos_tile excomdos_tile_item id-<?php echo $item->id; ?>">
            <div class="excomdos_tilehead">
                <span class="excomdos_tileinfo">
                    <?php echo get_string($item->type, "block_exaport"); ?>
                    <br><span class="excomdos_tileinfo_time"><?php echo userdate($item->timemodified); ?></span>
                </span>
                <span class="excomdos_tileedit">
                    <?php
                    if ($currentcategory->id == -1) {
                        // Link to export to portfolio.
                        echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.
                                sesskey().'&action=copytoself'.'"><img src="pix/import.png" title="'.
                                get_string('make_it_yours', "block_exaport").'"></a>';
                    } else {
                        if ($item->comments > 0) {
                            echo '<span class="excomdos_listcomments">'.$item->comments.
                                    '<img src="pix/comments.png" alt="file"></span>';
                        }
                        echo block_exaport_get_item_comp_icon($item);

                        if (in_array($type, ['mine', 'shared'])) {
                            $cattype = '';
                            if ($type == 'shared') {
                                $cattype = '&cattype=shared';
                            }
                            if ($item->userid == $USER->id) { // only for self!
                                echo '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id .
                                    '&action=edit'.$cattype.'"><img src="pix/edit.png" alt="file"></a>';
                            }
                            if (($type == 'mine' && $allowedit = block_exaport_item_is_editable($item->id)) // strange condition. If exacomp is not used - always allowed!
                                    || $item->userid == $USER->id) {
                                if ($item->userid == $USER->id) {
                                    echo '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $item->id .
                                        '&action=delete&categoryid=' . $categoryid . $cattype . '"><img src="pix/del.png" alt="file"></a>';
                                }
                            } else if (!$allowedit = block_exaport_item_is_editable($item->id)) {
                                echo '<img src="pix/deleteview.png" alt="file">';
                            }
                            if ($item->userid != $USER->id) {
                                $itemuser = $DB->get_record('user', ['id' => $item->userid]);
                                // user icon
                                echo '<a class="" role="button" data-container="body" 
                                            './*data-toggle="popover" data-placement="bottom" // popover does not work in Firefox
                                            data-content="'.fullname($itemuser).'" tabindex="0" data-trigger="hover".*/'
                                            title="'.fullname($itemuser).'">
                                        <img src="pix/personal.png">
                                      </a>';
//                                echo '<img src="pix/personal.png" alt="'.fullname($itemuser).'" title="'.fullname($itemuser).'">';
                            }
                        }
                    }
                    ?>
                </span>
            </div>
            <div class="excomdos_tileimage">
                <a href="<?php echo $url; ?>"><img alt="<?php echo $item->name ?>" title="<?php echo $item->name ?>"
                                                   src="<?php echo $CFG->wwwroot.'/blocks/exaport/item_thumb.php?item_id='.
                                                           $item->id; ?>"/></a>
            </div>
            <div class="exomdos_tiletitle">
                <a href="<?php echo $url; ?>"><?php echo $item->name; ?></a>
            </div>
        </div>
        <?php
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

    $compids = block_exaport_get_active_compids_for_item($item);

    if (!$compids) {
        return;
    }

    // If item is assoziated with competences display them.
    $competences = "";
    foreach ($compids as $compid) {

        $conditions = array("id" => $compid);
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, $conditions, $fields = '*', $strictness = IGNORE_MISSING);

        if ($competencesdb != null) {
            $competences .= $competencesdb->title.'<br>';
        }
    }
    $competences = str_replace("\r", "", $competences);
    $competences = str_replace("\n", "", $competences);
    $competences = str_replace("\"", "&quot;", $competences);
    $competences = str_replace("'", "&prime;", $competences);

    return '<a onmouseover="Tip(\''.$competences.'\')" onmouseout="UnTip()"><img src="pix/comp.png" alt="'.'competences'.'" /></a>';
}
