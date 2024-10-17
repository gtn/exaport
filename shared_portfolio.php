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

$access = optional_param('access', 0, PARAM_TEXT);

require_login(0, true);

if (!$user = block_exaport_get_user_from_access($access)) {
    print_error("nouserforid", "block_exaport");
}

$userpreferences = block_exaport_get_user_preferences($user->id);

if ($user->access->request == 'intern') {
    block_exaport_print_header("shared_views");
} else {
    print_header(get_string("externaccess", "block_exaport"),
        get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
    echo block_exaport_wrapperdivstart();
}

$parsedsort = block_exaport_parse_item_sort($userpreferences->itemsort, false);
$orderby = block_exaport_item_sort_to_sql($parsedsort, false);

$conditions = array();
if ($user->access->request == 'extern') {
    $extratable = "";
    $extrawhere = "i.externaccess=1";
} else {
    $extratable = "LEFT JOIN {block_exaportitemshar} ishar ON i.id=ishar.itemid AND ishar.userid=?";
    $extrawhere = " ((i.shareall=1 AND ishar.userid IS NULL)";
    $extrawhere .= "  OR (i.shareall=0 AND ishar.userid IS NOT NULL))";
    $conditions[] = $USER->Id;
}

$conditions[] = $user->id;
$items = $DB->get_records_sql(
    "SELECT i.id, i.type, i.url, i.name, i.intro, i.attachment, i.timemodified, ic.name AS cname, ic2.name AS cname_parent
    FROM {block_exaportitem} i
    JOIN {block_exaportcate} ic ON i.categoryid = ic.id
    $extratable
    LEFT JOIN {block_exaportcate} ic2 on ic.pid = ic2.id
    WHERE i.userid=? AND $extrawhere
    $orderby", $conditions);

if (!$items) {
    print_error("nobookmarksall", "block_exaport");
}

echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

echo '<tr class="header"><td class="picture left">';
print_user_picture($user->id, 0, $user->picture);
echo '</td>';

echo '<td class="topic starter"><div class="author">';
$by = fullname($user, $user->id);
print_string('byname', 'moodle', $by);
echo '</div></td></tr>';

echo "<br />";

echo "<div class='block_eportfolio_center'>\n";
$table = new stdClass();
$table->head = array(get_string("name", "block_exaport"), get_string("date", "block_exaport"));
$table->align = array("CENTER", "LEFT", "CENTER", "CENTER");
$table->size = array("20%", "37%", "28%", "15%");
$table->width = "85%";

if ($items) {
    $lastcat = "";
    $firstrow = true;

    foreach ($items as $item) {

        if (!is_null($item->cname_parent)) {
            $item->cname = $item->cname_parent . ' &rArr; ' . $item->cname;
        }

        if ($parsedsort[0] == 'category') {
            if ($lastcat != $item->cname) {
                if ($firstrow) {
                    $firstrow = false;
                } else {
                    print_table($table);
                }
                print_heading(format_string($item->cname));
                $lastcat = $item->cname;
                unset($table->data);
            }
        }

        $name = "";
        $name .= "<a href=\"shared_item.php?access=portfolio/" . $access . "&itemid=" . $item->id . '">' . format_string($item->name) . "</a>";

        if ($item->intro) {
            $name .= "<br /><table width=\"98%\"><tr><td class='block_eportfolio_externalview'>" . format_text($item->intro) .
                "</td></tr></table>";
        }

        $date = userdate($item->timemodified);

        $table->data[] = array($name, $date);
    }
    print_table($table);
} else {
    echo "<div class='block_eportfolio_center'>" . get_string("nobookmarksexternal", "block_exaport") . "</div>";
}

echo "<br />";

echo "</div>\n";
echo block_exaport_wrapperdivend();
print_footer();
