<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2006 exabis internet solutions <info@exabis.at>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once dirname(__FILE__).'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);

$type = optional_param('type', 'all', PARAM_ALPHA);
$type = block_exaport_check_item_type($type, true);

// Needed for Translations
$type_plural = block_exaport_get_plural_item_type($type);

block_exaport_require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);

$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	error("That's an invalid course id");
}

$url = '/blocks/exaport/view_items_print.php';
$PAGE->set_url($url);
$PAGE->set_pagelayout('print');

$PAGE->requires->css('/blocks/exaport/css/view_items_print.css');

echo $OUTPUT->header();

block_exaport_setup_default_categories();

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) $pref="desp_";
else $pref="";
echo $OUTPUT->box( text_to_html(get_string($pref."explaining","block_exaport")) , "center");
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
	$sort = $userpreferences->itemsort;
}

// check sorting
$parsedsort = block_exaport_parse_item_sort($sort, true);
$sort = $parsedsort[0].'.'.$parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
	$newsort = $sortkey.".asc";
} else {
	$newsort = $sortkey.".desc";
}
$sorticon = $parsedsort[1].'.png';


block_exaport_set_user_preferences(array('itemsort'=>$sort));



$sql_sort = block_exaport_item_sort_to_sql($parsedsort, true);

$condition = array($USER->id);


$items = $DB->get_records_sql("
	SELECT i.*, ic.name AS cname, ic.id AS catid, COUNT(com.id) As comments
	FROM {block_exaportitem} i
	LEFT JOIN {block_exaportcate} ic on i.categoryid = ic.id
	LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
	WHERE i.userid = ?
		AND ".block_exaport_get_item_where()."
	GROUP BY i.id, i.name, i.intro, i.timemodified, i.userid, i.type, i.categoryid, i.url, i.attachment, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
	i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, i.iseditable
	$sql_sort
", $condition);

$table = new html_table();
$table->width = "100%";

$table->head = array();
$table->size = array();

$table->head['category'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=".
		($sortkey == 'category' ? $newsort : 'category' ) ."'>" . get_string("category", "block_exaport") . "</a>";
$table->size['category'] = "14";

$table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=".
		($sortkey == 'type' ? $newsort : 'type') ."'>" . get_string("type", "block_exaport") . "</a>";
$table->size['type'] = "14";

$table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=".
		($sortkey == 'name' ? $newsort : 'name') ."'>" . get_string("name", "block_exaport") . "</a>";
$table->size['name'] = "30";

$table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items_print.php?courseid=$courseid&amp;type=$type&amp;sort=".
		($sortkey == 'date' ? $newsort : 'date.desc') ."'>" . get_string("date", "block_exaport") . "</a>";
$table->size['date'] = "20";

$table->head[] = get_string("comments","block_exaport");
$table->size[] = "8";

// add arrow to heading if available
if (isset($table->head[$sortkey]))
	$table->head[$sortkey] .= "<img src=\"pix/$sorticon\" alt='".get_string("updownarrow", "block_exaport")."' />";


$table->data = Array();
$lastcat = "";

$item_i = -1;
$itemscnt = count($items);
foreach ($items as $item) {
	$item_i++;

	$table->data[$item_i] = array();

	// set category
	$category = format_string($item->cname);

	if (($sortkey == "category") && ($lastcat == $category)) {
		$category = "";
	} else {
		$lastcat = $category;
	}
	$table->data[$item_i]['category'] = $category;

	$table->data[$item_i]['type'] = get_string($item->type, "block_exaport");
	
	$table->data[$item_i]['name'] = $item->name;
	if ($item->intro) {
		$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);

		if (!$intro) {
			// no intro
		} else {
			// show whole intro for printing
			$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
		}
	}

	$table->data[$item_i]['date'] = userdate($item->timemodified);
	$table->data[$item_i]['comments'] = $item->comments;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
