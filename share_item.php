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
require_once dirname(__FILE__).'/lib/sharelib.php';

global $DB;
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$save_this = optional_param('save_this', '', PARAM_ALPHA);
$itemid = optional_param('itemid', 0, PARAM_INT);
$shareusers = optional_param('shareusers', '', PARAM_RAW); // array of integer
$shareall = optional_param('shareall', 0, PARAM_INT);
$externaccess = optional_param('externaccess', 0, PARAM_INT);
$externcomment = optional_param('externcomment', 0, PARAM_INT);

$backtype = optional_param('backtype', 'all', PARAM_ALPHA);
$backtype = block_exaport_check_item_type($backtype, true);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:shareintern', $context);

$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	print_error("invalidcourseid", "block_exaport");
}

if ($action == 'userlist') {
	echo json_encode(exaport_get_shareable_courses_with_users('sharing'));
	exit;
}

// get the bookmark if it is mine.
$bookmark = $DB->get_record_sql("select b.*, bc.name AS cname, bc2.name AS cname_parent, c.fullname As coursename".
							 " from {block_exaportitem} b join {block_exaportcate} bc on b.categoryid = bc.id".
							 " left join {block_exaportcate} bc2 on bc.pid = bc2.id".
							 " left join {course} c on b.courseid = c.id".
							 " where b.userid = ? and b.id=?", array($USER->id, $itemid));

if(!$bookmark) {
	print_error("bookmarknotfound","block_exaport", 'view.php?courseid=' . $courseid);	 
}

if ($save_this == "ok") {
	if (!confirm_sesskey()) {
		print_error("badsessionkey","block_exaport");
	}

	// set bookmark options
	$bookmark_update = new stdClass();
	$bookmark_update->id = $itemid;
	$bookmark_update->shareall = $shareall;
	if (has_capability('block/exaport:shareextern', $context)) {
		$bookmark_update->externaccess = $externaccess;
		$bookmark_update->externcomment = $externcomment;
	} else {
		$bookmark_update->externaccess = 0;
		$bookmark_update->externcomment = 0;
	}
	update_record("block_exaportitem", $bookmark_update);
   

	// delete all shared users
	delete_records("block_exaportitemshar", "courseid", $courseid, "itemid", $itemid);

	// add new shared users
	if (is_array($shareusers)){
		foreach ($shareusers as $share_item) {
			$share_item = clean_param($share_item, PARAM_INT);
			
			$bookmark_shared = new stdClass();
			$bookmark_shared->itemid = $itemid;	 
			$bookmark_shared->courseid = $courseid;
			$bookmark_shared->userid = $share_item;
			$bookmark_shared->original = $USER->id;
			insert_record("block_exaportitemshar", $bookmark_shared);
		}
	}


	redirect("view_items.php?courseid=$courseid&type=".$backtype);
}

$conditions = array("itemid" => $itemid);
$sharedUsers = $DB->get_records('block_exaportitemshar', $conditions, null, 'userid');
if (!$sharedUsers) {
	$sharedUsers = array();
} else {
	$sharedUsers = array_flip(array_keys($sharedUsers));
}


$url = '/blocks/exabis_competences/share_item.php';
$PAGE->set_url($url);
block_exaport_print_header("bookmarks".block_exaport_get_plural_item_type($backtype), "share");

$translations = array(
	'name', 'role', 'nousersfound',
);

$translations = array_flip($translations);
foreach ($translations as $key => &$value) {
	$value = block_exaport_get_string($key);
}
unset($value);

?>
<script type="text/javascript">
//<![CDATA[
	var sharedUsers = <?php echo json_encode($sharedUsers); ?>;
	ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
//]]>
</script>
<?php

$extern_link = exaport_get_extern_access($USER->id);

$table = new stdClass();
$table->head  = array (get_string("category","block_exaport"), get_string("name", "block_exaport"), get_string("date","block_exaport"), get_string("course","block_exaport"));
$table->align = array("LEFT", "LEFT", "CENTER", "CENTER");
$table->size = array("20%", "34%", "26%","20%");
$table->width = "85%";
$table->data[] = array(format_string($bookmark->cname), format_string($bookmark->name), "<div class='block_eportfolio_timemodified'>" . userdate($bookmark->timemodified) . "</div>", $bookmark->coursename);
print_table($table);

print_simple_box( text_to_html(get_string("explainingshare".$bookmark->type, "block_exaport")) , "center");

exaport_print_js();

echo "<form action=\"".$_SERVER['PHP_SELF']."\" method=\"post\" id=\"listing\">";
echo '<fieldset><legend>'.get_string("accessoptions", "block_exaport").'</legend>';

echo '<label>' . get_string("internalaccess", "block_exaport") . '</label>';

if($bookmark->shareall == 0) {
	echo '<p><label><input name="shareall" type="radio" value="1" />'.get_string("shareallexceptthose","block_exaport").'</label><br />';
	echo '<label><input name="shareall" type="radio" value="0" checked="checked" />'.get_string("sharenoneexceptthose","block_exaport").'</label></p>';
}
else {
	echo '<p><label><input name="shareall" type="radio" value="1" checked="checked" />'.get_string("shareallexceptthose","block_exaport").'</label><br />';
	echo '<label><input name="shareall" type="radio" value="0" />'.get_string("sharenoneexceptthose","block_exaport").'</label></p>';
}


if (has_capability('block/exaport:shareextern', $context)) {
	echo '<label>' . get_string("externalaccess", "block_exaport") . '</label>';
	
	if($bookmark->externaccess == 0) {
		echo '<p><label><input type="checkbox" name="externaccess" value="1" />'.get_string("externaccess", "block_exaport").' (<a  onclick="this.target=\'extlink\'; return openpopup(\'/blocks/exaport/'.$extern_link.'\',\'extlink\',\'resizable=1,scrollbars=1,directories=1,location=1,menubar=1,toolbar=1,status=1,width=620,height=450\');" href="'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'">'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'</a>)</label><br />';
	}
	else {
		echo '<p><label><input type="checkbox" name="externaccess" checked="checked" value="1" />'.get_string("externaccess", "block_exaport").' (<a  onclick="this.target=\'extlink\'; return openpopup(\'/blocks/exaport/'.$extern_link.'\',\'extlink\',\'resizable=1,scrollbars=1,directories=1,location=1,menubar=1,toolbar=1,status=1,width=620,height=450\');" href="'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'">'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'</a>)</label><br />';
	}
	if($bookmark->externcomment == 0) {
		echo '<label><input type="checkbox" name="externcomment" value="1" />'.get_string("externcomment", "block_exaport").'</label></p>';
	}
	else {
		echo '<label><input type="checkbox" name="externcomment" checked="checked" value="1" />'.get_string("externcomment", "block_exaport").'</label></p>';
	}
}
else {
	echo '<input type="hidden" name="externaccess" value="0" /><input type="hidden" name="externcomment" value="0" />';
}

echo '</fieldset>';

echo '<div id="sharing-userlist">userlist</div>';

echo "<fieldset>";
echo "<input type=\"hidden\" name=\"courseid\" value=\"$courseid\" />";
echo "<input type=\"hidden\" name=\"itemid\" value=\"$itemid\" />";
echo "<input type=\"hidden\" name=\"backtype\" value=\"$backtype\" />";

echo "<input type=\"submit\" onclick=\"document.getElementById('listing').elements['save_this'].value = 'ok'\" value=\"".get_string("savechanges")."\" />\n<br /><br />";
echo "<input type=\"hidden\" name=\"save_this\" value=\"notok\" />";
echo "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" />";
echo "<input type=\"hidden\" name=\"selectall\" value=\"\" />";
echo "</fieldset>";
echo "</form>";

print_footer($course);
