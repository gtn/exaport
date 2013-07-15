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
$print = optional_param('print', false, PARAM_BOOL);
$type = block_exaport_check_item_type($type, true);

// Needed for Translations
$type_plural = block_exaport_get_plural_item_type($type);


$strbookmarks = get_string("mybookmarks", "block_exaport");
$strheadline = get_string("bookmarks".$type_plural, "block_exaport");

block_exaport_require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);

$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	error("That's an invalid course id");
}
$url = '/blocks/exaport/view_items.php';
$PAGE->set_url($url);
if(!$print)block_exaport_print_header("bookmarks".$type_plural);
else {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>Alle Eintr&auml;ge</title>

<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<meta content="moodle, Alle Eintr&auml;ge" name="keywords" />
<link
	href="<?php echo $CFG->wwwroot;?>/theme/styles.php/standard/1341490079/all"
	type="text/css" rel="stylesheet" />
<link
	href="<?php echo $CFG->wwwroot;?>/theme/yui_combo.php?3.5.1/build/cssreset/reset.css&3.5.1/build/cssfonts/fonts.css&3.5.1/build/cssgrids/grids.css&3.5.1/build/cssbase/base.css"
	type="text/css" rel="stylesheet" />
<link href="printversion.css" type="text/css" rel="stylesheet" />
<?php 
echo '<link href="'.$CFG->wwwroot.'blocks/exaport/styles.css" type="text/css" rel="stylesheet" />';
echo '</head><body>';
}

block_exaport_setup_default_categories();

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) $pref="desp_";
else $pref="";
echo $OUTPUT->box( text_to_html(get_string($pref."explaining".$type,"block_exaport")) , "center");
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
	$sort = $userpreferences->itemsort;
}

// check sorting
$parsedsort = block_exaport_parse_item_sort($sort);
$sort = $parsedsort[0].'.'.$parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
	$newsort = $sortkey.".asc";
} else {
	$newsort = $sortkey.".desc";
}
$sorticon = $parsedsort[1].'.gif';


block_exaport_set_user_preferences(array('itemsort'=>$sort));

if (!$print) {
	echo "<div class='block_eportfolio_center'>";

	echo "<form action=\"{$CFG->wwwroot}/blocks/exaport/item.php?backtype=$type\" method=\"post\">
	<fieldset>
	<input type=\"hidden\" name=\"action\" value=\"add\"/>
	<input type=\"hidden\" name=\"courseid\" value=\"$courseid\"/>
	<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" />";
	if ($type != 'all')
	{
		echo '<input type="hidden" name="type" value="'.$type.'" />';
		echo "<input type=\"submit\" value=\"" . get_string("new".$type, "block_exaport"). "\"/>";
	}
	else
	{
		echo '<select name="type">';
		echo '<option value="link">'.get_string("link", "block_exaport")."</option>";
		echo '<option value="file">'.get_string("file", "block_exaport")."</option>";
		echo '<option value="note">'.get_string("note", "block_exaport")."</option>";
		echo '</select>';
		echo "<input type=\"submit\" value=\"" . get_string("new", "block_exaport"). "\"/>";
	}
	echo "</fieldset>
	</form>";
	echo "</div>";
}


$sql_sort = block_exaport_item_sort_to_sql($parsedsort);

$condition = array($USER->id);

if ($type == 'all')
	$sql_type_where = '';
else{
	//$sql_type_where = " AND i.type='".$type."'";
	$sql_type_where = " AND i.type=?";
	$condition = array($USER->id, $type);
}

if(strcmp("sqlsrv", $CFG->dbtype)==0){
	$query = "SELECT i.id, i.userid, i.type, i.categoryid, i.name, i.url, cast(INTRO as text) intro,
	i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess, i.externcomment,
	i.sortorder, i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid,
	cast(BEISPIEL as text)beispiel_angabe,
	i.cname, i.cname_parent, i.catid, i.coursename,comments FROM(
	SELECT i.id, i.userid, i.type, i.categoryid, i.name, i.url, cast(i.intro AS varchar) INTRO,
	i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
	i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, cast(i.beispiel_angabe AS varchar) BEISPIEL,
	ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, c.fullname AS coursename, COUNT( com.id ) AS comments
	FROM {block_exaportitem} i
	JOIN {block_exaportcate} ic ON i.categoryid = ic.id
	LEFT JOIN {block_exaportcate} ic2 ON ic.pid=ic2.id
	LEFT JOIN {course} c ON i.courseid = c.id
	LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id
	WHERE i.userid=? $sql_type_where  AND (i.isoez=0 OR (i.isoez=1 AND (i.intro<>'' OR i.url<>'' OR i.attachment<>''))) GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, cast(i.intro AS varchar),
	i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,
	i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, CAST(i.beispiel_angabe AS varchar), ic.name,
	ic2.name,c.fullname )i $sql_sort ";
}
else{
	$query = "select i.*, ic.name AS cname, ic2.name AS cname_parent, ic.id AS catid, c.fullname As coursename, COUNT(com.id) As comments".
			" from {block_exaportitem} i".
			" join {block_exaportcate} ic on i.categoryid = ic.id".
			" left join {block_exaportcate} ic2 on ic.pid = ic2.id".
			" left join {course} c on i.courseid = c.id".
			" left join {block_exaportitemcomm} com on com.itemid = i.id".
			" where i.userid = ? $sql_type_where AND (i.isoez=0 OR (i.isoez=1 AND (i.intro<>'' OR i.url<>'' OR i.attachment<>''))) group by i.id, i.name, i.intro, i.timemodified, cname, cname_parent, coursename,".
			"i.userid, i.type, i.categoryid, i.url, i.attachment, i.courseid, i.shareall, i.externaccess, i.externcomment, i.sortorder,". 
			"i.isoez, i.fileurl, i.beispiel_url, i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, i.iseditable, ic.id $sql_sort";
}
$items = $DB->get_records_sql($query, $condition);

if ($items) {

	$table = new html_table();
	$table->width = "100%";

	$table->head = array();
	$table->size = array();

	$table->head['category'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;type=$type&amp;sort=".
			($sortkey == 'category' ? $newsort : 'category' ) ."'>" . get_string("category", "block_exaport") . "</a>";
	$table->size['category'] = "14";

	if ($type == 'all') {
		$table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;type=$type&amp;sort=".
				($sortkey == 'type' ? $newsort : 'type') ."'>" . get_string("type", "block_exaport") . "</a>";
		$table->size['type'] = "14";
	}

	$table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;type=$type&amp;sort=".
			($sortkey == 'name' ? $newsort : 'name') ."'>" . get_string("name", "block_exaport") . "</a>";
	$table->size['name'] = "30";

	$table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&amp;type=$type&amp;sort=".
			($sortkey == 'date' ? $newsort : 'date.desc') ."'>" . get_string("date", "block_exaport") . "</a>";
	$table->size['date'] = "20";

	$table->head[] = get_string("course","block_exaport");
	$table->size[] = "14";

	$table->head[] = get_string("comments","block_exaport");
	$table->size[] = "8";

	$table->head[] = '';
	$table->size[] = "10";

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
		if(is_null($item->cname_parent)) {
			$category = format_string($item->cname);
		}
		else {
			$catid= $item->catid;
			$catname = $item->cname;
			$category = format_string($item->cname);
			//recursives abfragen des kategorienpfades, zu langsam!

			/*do{
				$conditions = array("userid" => $USER->id, "id" => $catid);
				$cats=$DB->get_records_select("block_exaportcate", "userid = ? AND id = ?",$conditions, "name ASC");
				foreach($cats as $cat){
					if($category == "")
						$category =format_string($cat->name);
					else
						$category =format_string($cat->name)." &rArr; ".$category;
					$catid = $cat->pid;
			}
			
			}while ($cat->pid != 0);*/
		}
		if (($sortkey == "category") && ($lastcat == $category)) {
			$category = "";
		} else {
			$lastcat = $category;
		}
		$table->data[$item_i]['category'] = $category;

		if ($type == 'all') {
			$table->data[$item_i]['type'] = get_string($item->type, "block_exaport");
		}

		$table->data[$item_i]['name'] = "<a href=\"".s("{$CFG->wwwroot}/blocks/exaport/shared_item.php?courseid=$courseid&access=portfolio/id/".$USER->id."&itemid=$item->id&backtype=".$type."&att=".$item->attachment)."\">" . $item->name . "</a>";
		if ($item->intro) {
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);

			$shortIntro = substr(trim(strip_tags($intro)), 0, 20);
			if(preg_match_all('#(?:<iframe[^>]*)(?:(?:/>)|(?:>.*?</iframe>))#i', $intro, $matches)) {
				$shortIntro = $matches[0][0];
			}

			if (!$intro) {
				// no intro
			} elseif ($print) {
				// show whole intro for printing
				$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
			} elseif ($shortIntro == $intro) {
				// very short one
				$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
			} else {
				// display show/hide buttons
				$table->data[$item_i]['name'] .=
				'<div><div id="short-preview-'.$item_i.'"><div>'.$shortIntro.'...</div>
				<a href="javascript:long_preview_show('.$item_i.')">['.get_string('more').'...]</a>
				</div>
				<div id="long-preview-'.$item_i.'" style="display: none;"><div>'.$intro.'</div>
				<a href="javascript:long_preview_hide('.$item_i.')">['.strtolower(get_string('hide')).'...]</a>
				</div>';
			}
		}

		$table->data[$item_i]['date'] = userdate($item->timemodified);
		$table->data[$item_i]['course'] = $item->coursename;
		$table->data[$item_i]['comments'] = $item->comments;

		$icons = '';
		
		$array = block_exaport_get_competences($item, 0);
		
		//if item is assoziated with competences display them
		//begin
		if(count($array)>0){
			$competences = "";
			foreach($array as $element){
		
				$conditions = array("id" => $element->descid);
				$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING); 

				if($competencesdb != null){
					$competences .= $competencesdb->title.'<br>';
				}
			}
			$competences = str_replace("\r", "", $competences);
			$competences = str_replace("\n", "", $competences);
			
			$icons .= '<script type="text/javascript" src="lib/wz_tooltip.js"></script><a onmouseover="Tip(\''.$competences.'\')" onmouseout="UnTip()"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/application_view_title.png" class="iconsmall" alt="'.'competences'.'" /></a>';
		}
		//end
		
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=edit&amp;backtype='.$type.'"><img src="'.$CFG->wwwroot.'/pix/t/edit.gif" class="iconsmall" alt="'.get_string("edit").'" /></a> ';

		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=delete&amp;confirm=1&amp;backtype='.$type.'"><img src="'.$CFG->wwwroot.'/pix/t/delete.gif" class="iconsmall" alt="' . get_string("delete"). '"/></a> ';

		/*
		 if ($parsedsort[0] == 'sortorder') {
		if ($item_i > 0) {
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=movetop&backtype='.$type.'" title="'.get_string("movetop", "block_exaport").'"><img src="pix/movetop.gif" class="iconsmall" alt="'.get_string("movetop", "block_exaport").'"/></a> ';
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=moveup&backtype='.$type.'" title="'.get_string("moveup").'"><img src="'.$CFG->wwwroot.'/pix/t/up.gif" class="iconsmall" alt="'.get_string("moveup").'"/></a> ';
		} else {
		$icons .= '<img src="'.$CFG->wwwroot.'/pix/spacer.gif" class="iconsmall" alt="" /> ';
		$icons .= '<img src="'.$CFG->wwwroot.'/pix/spacer.gif" class="iconsmall" alt="" /> ';
		}

		if ($item_i+1 < $itemscnt) {
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=movedown&backtype='.$type.'" title="'.get_string("movedown").'"><img src="'.$CFG->wwwroot.'/pix/t/down.gif" class="iconsmall" alt="'.get_string("movedown").'"/></a> ';
		$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&amp;id='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=movebottom&backtype='.$type.'" title="'.get_string("movebottom", "block_exaport").'"><img src="pix/movebottom.gif" class="iconsmall" alt="'.get_string("movebottom", "block_exaport").'"/></a> ';
		}
		else {
		$icons .= '<img src="'.$CFG->wwwroot.'/pix/spacer.gif" class="iconsmall" alt="" /> ';
		$icons .= '<img src="'.$CFG->wwwroot.'/pix/spacer.gif" class="iconsmall" alt="" /> ';
		}
		}
		*/

		if (block_exaport_feature_enabled('share_item')) {
			if (has_capability('block/exaport:shareintern', $context)) {
				if( ($item->shareall == 1) ||
						($item->externaccess == 1) ||
						(($item->shareall == 0) && (count_records('block_exaportitemshar', 'itemid', $item->id, 'original', $USER->id) > 0))) {
					$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/share_item.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("strunshare", "block_exaport").'</a> ';
				}
				else {
					$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/share_item.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("strshare", "block_exaport").'</a> ';
				}
			}
		}

		// copy files to course
		if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course'))
			$icons .= '<a href="'.$CFG->wwwroot.'/blocks/exaport/copy_item_to_course.php?courseid='.$courseid.'&amp;itemid='.$item->id.'&backtype='.$type.'">'.get_string("copyitemtocourse", "block_exaport").'</a> ';

		$table->data[$item_i]['icons'] = $icons;
	}

	/*
	 if ($parsedsort[0] != 'sortorder')
		echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&amp;&type='.$type.'&amp;sort=sortorder">'.get_string("userdefinedsort", "block_exaport").'</a>';
	*/
	$output = html_writer::table($table);
	echo $output;
} else {
	echo block_exaport_get_string("nobookmarks".$type,"block_exaport");
}

if(!$print) {
	echo "<div class='block_eportfolio_center'>";
	echo "<a target='_blank' href='".$CFG->wwwroot.$url."?courseid=".$courseid."&print=true'>".get_string('printerfriendly', 'group')."</a>";
	echo "</div>";
}

?>
<script type="text/javascript">
//<![CDATA[
function long_preview_show(i) {
document.getElementById("short-preview-" + i).style.display = "none";
document.getElementById("long-preview-" + i).style.display = "block";
}
function long_preview_hide(i) {
document.getElementById("short-preview-" + i).style.display = "block";
document.getElementById("long-preview-" + i).style.display = "none";
}
//]]>
</script>
<?php

if(!$print) echo $OUTPUT->footer($course);
else echo '</body></html>';
