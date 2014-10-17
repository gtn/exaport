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
require_once dirname(__FILE__) . '/lib/sharelib.php';

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$q = trim(optional_param('q', '', PARAM_TEXT));

block_exaport_require_login($courseid);

$context = context_system::instance();
$url = '/blocks/exabis_competences/views_mod_share_user_search.php';
$PAGE->set_url($url);

$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	error("That's an invalid course id");
}

$conditions = array("id" => $id, "userid" => $USER->id);
if (!$view = $DB->get_record('block_exaportview', $conditions)) {
	print_error("wrongviewid", "block_exaport");
}

$shareusers = optional_param_array('shareusers', null, PARAM_INT);

if ($shareusers) {
	// update shared users
	$sharedUsers = exaport_get_view_shared_users($id);

	foreach ($shareusers as $userid => $share) {
		if ($share && !isset($sharedUsers[$userid])) {
			// add
			$shareItem = new stdClass();
			$shareItem->viewid = $view->id;
			$shareItem->userid = $userid;
			$DB->insert_record("block_exaportviewshar", $shareItem);
		} elseif (!$share && isset($sharedUsers[$userid])) {
			// delete
			$DB->delete_records("block_exaportviewshar", array('viewid' => $view->id, 'userid' => $userid));
		} else {
			// do nothing, everything is fine
		}
	}

	$DB->update_record("block_exaportview", array(
		'id' => $view->id,
		'internaccess' => true,
		'shareall' => false
	));
}
				
block_exaport_print_header('views', 'share');
			 
echo '<a href="views_mod.php?courseid='.$courseid.'&id='.$id.'&sesskey='.sesskey().'&type=share&action=edit">'.get_string('back').'</a><br /><br />';
?>
	<form method="get" action="views_mod_share_user_search.php">
		<input type="hidden" name="courseid" value="<?php p($courseid) ?>" />
		<input type="hidden" name="id" value="<?php p($id) ?>" />
		<input name="q" type="text" value="<?php p($q) ?>" />
		<input value="<?php echo get_string('search'); ?>" type="submit" />
	</form>
<?php

if ($q) {
	$users = get_users_listing('firstname', 'ASC', 0, 10, $q, '', '', '', array(), $context);
	
	if ($users) {
		echo '<form method="post" action="'.$_SERVER['REQUEST_URI'].'" style="padding-top: 10px;">';
		echo "<table width=\"70%\">";
		echo "<tr><th align=\"center\">".get_string('strshare', 'block_exaport')."</th>";
		echo "<th align=\"left\">".get_string('name')."</th></tr>";

		$sharedUsers = exaport_get_view_shared_users($id);
		foreach ($users as $user) {

			$shared_to = isset($sharedUsers[$user->id]);
			
			echo '<tr><td align=\"center\" width="50">';
			echo '<input class="shareusers" type="hidden" name="shareusers['.$user->id.']" value="" />';
			echo '<input class="shareusers" type="checkbox" name="shareusers['.$user->id.']" value="'.$user->id.'"' .
				($shared_to ? ' checked="checked"' : '') .
					' />';
			echo "</td><td align=\"center\">" . fullname($user) . "</td></tr>";
		}
		echo "</table>";
		echo '<a href="views_mod.php?courseid='.$courseid.'&id='.$id.'&sesskey='.sesskey().'&type=share&action=edit">'.get_string('back').'</a>&nbsp;&nbsp;&nbsp;';
		echo '<input value="'.get_string('savechanges').'" type="submit" />';
		echo '</form>';
	} else {
		echo get_string('nousersfound');
	}
} else {
}

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
