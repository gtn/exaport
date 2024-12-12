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

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);

$type = optional_param('type', 'all', PARAM_ALPHA);
$type = block_exaport_check_item_type($type, true);

// Needed for Translations.
$typeplural = block_exaport_get_plural_item_type($type);

block_exaport_require_login($courseid);

$context = context_system::instance();

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

$url = '/blocks/exaport/view_items_print.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
$PAGE->set_pagelayout('print');

$PAGE->requires->css('/blocks/exaport/css/view_items_print.css');

echo $OUTPUT->header();
echo block_exaport_wrapperdivstart();
block_exaport_setup_default_categories();

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) {
    $pref = "desp_";
} else {
    $pref = "";
}
echo $OUTPUT->box(text_to_html(get_string($pref . "explaining", "block_exaport")), "center");
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
    $sort = $userpreferences->itemsort;
}

// Check sorting.
$parsedsort = block_exaport_parse_item_sort($sort, true);
$sort = $parsedsort[0] . '.' . $parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
    $newsort = $sortkey . ".asc";
} else {
    $newsort = $sortkey . ".desc";
}
$sorticon = $parsedsort[1] . '.png';

block_exaport_set_user_preferences(array('itemsort' => $sort));

$sqlsort = block_exaport_item_sort_to_sql($parsedsort, true);

$condition = array($USER->id);

$items = $DB->get_records_sql("
    SELECT i.id, i.name, i.intro, i.timemodified, i.userid, i.type, i.categoryid, i.url,
    i.attachment, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
    i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid,
    i.iseditable, ic.name AS cname, ic.id AS catid, COUNT(com.id) As comments
    FROM {block_exaportitem} i
    LEFT JOIN {block_exaportcate} ic on i.categoryid = ic.id
    LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
    WHERE i.userid = ? AND " . block_exaport_get_item_where() .
    " GROUP BY i.id, i.name, i.intro, i.timemodified, i.userid, i.type, i.categoryid, i.url, i.attachment,
    i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
    i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, i.iseditable, ic.name, ic.id
    $sqlsort
", $condition);

$table = new html_table();
$table->width = "100%";

$table->head = array();
$table->size = array();

$table->head['category'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;" .
    "sort=" . ($sortkey == 'category' ? $newsort : 'category') . "'>" . get_string("category", "block_exaport") . "</a>";
$table->size['category'] = "14";

$table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=" .
    ($sortkey == 'type' ? $newsort : 'type') . "'>" . get_string("type", "block_exaport") . "</a>";
$table->size['type'] = "14";

$table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=" .
    ($sortkey == 'name' ? $newsort : 'name') . "'>" . get_string("name", "block_exaport") . "</a>";
$table->size['name'] = "30";

$table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=" .
    ($sortkey == 'date' ? $newsort : 'date.desc') . "'>" . get_string("date", "block_exaport") . "</a>";
$table->size['date'] = "20";

$table->head[] = get_string("comments", "block_exaport");
$table->size[] = "8";

// Add arrow to heading if available.
if (isset($table->head[$sortkey])) {
    $table->head[$sortkey] .= "<img src=\"pix/$sorticon\" alt='" . get_string("updownarrow", "block_exaport") . "' />";
}

$table->data = array();
$lastcat = "";

$itemi = -1;
$itemscnt = count($items);
foreach ($items as $item) {
    $itemi++;

    $table->data[$itemi] = array();

    // Set category.
    $category = format_string($item->cname);

    if (($sortkey == "category") && ($lastcat == $category)) {
        $category = "";
    } else {
        $lastcat = $category;
    }
    $table->data[$itemi]['category'] = $category;

    $table->data[$itemi]['type'] = get_string($item->type, "block_exaport");

    $table->data[$itemi]['name'] = $item->name;
    if ($item->intro) {
        $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
            'block_exaport', 'item_content', 'portfolio/id/' . $item->userid . '/itemid/' . $item->id);

        if (!$intro) {
            $tempvar = 1; // For code checker.
            // No intro.
        } else {
            // Show whole intro for printing.
            $table->data[$itemi]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">" .
                format_text($intro, FORMAT_HTML) . "</td></tr></table>";
        }
    }

    $table->data[$itemi]['date'] = userdate($item->timemodified);
    $table->data[$itemi]['comments'] = $item->comments;
}

echo html_writer::table($table);
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
