<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$confirm = optional_param("confirm", "", PARAM_BOOL);
$backtype = optional_param('backtype', 'all', PARAM_ALPHA);
$compids = optional_param('compids', '', PARAM_TEXT);
$backtype = block_exaport_check_item_type($backtype, true);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$descriptorselection = optional_param('descriptorselection',true,PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);

$url = '/blocks/exaport/item.php';
$PAGE->set_url($url, ['courseid' => $courseid, 'id' => $id, 'action'=>$action]);

$conditions = array("id" => $courseid);

if (!$course = $DB->get_record("course", $conditions)) {
	print_error("invalidcourseid", "block_exaport");
}

if ($CFG->branch < 31)
	include($CFG->dirroot.'/tag/lib.php');

$allowEdit = block_exaport_item_is_editable($id);
$allowResubmission = block_exaport_item_is_resubmitable($id);

if ($action == 'copytoself') {
	require_sesskey();
	if (!$owner_id = block_exaport_can_user_access_shared_item($USER->id, $id)) {
		die(block_exaport_get_string('bookmarknotfound'));
		
	}
	
	$conditions = array("id" => $id, "userid" => $owner_id);	
	$source_item = $DB->get_record('block_exaportitem', $conditions);
	
	$copy = $source_item;
	
	unset($copy->id);
	$copy->userid = $USER->id;
	$copy->categoryid = 0;
	$copy->timemodified = time();
	$copy->shareall = 0;
	$copy->externaccess = 0;
	$copy->externcomment = 0;
	$copy->shareall = 0;
	
	$newitem_id = $DB->insert_record('block_exaportitem', $copy);
	if ($copy->type=='file') {
		$fs = get_file_storage();
		$fileinfo = array(
			'component' => 'block_exaport',
			'filearea' => 'item_file',	 
			'itemid' => $id); 
		$ownerusercontext = context_user::instance($owner_id);
		$usercontext = context_user::instance($USER->id);
		$oldfiles = $fs->get_area_files($ownerusercontext->id, 'block_exaport', 'item_file', $id);
		foreach ($oldfiles as $f) {
			$newfile_params = array(
				'contextid'	=> $usercontext->id,
				'itemid'	=> $newitem_id,
				'userid' 	=> $USER->id
				);
			$filecopy = $fs->create_file_from_storedfile($newfile_params, $f->get_id());
		};
	};
	
	$returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid='.$courseid."&categoryid=-1&userid=".$owner_id;
	redirect($returnurl);
} 

if ($id) {
	$conditions = array("id" => $id, "userid" => $USER->id);
	if (!$existing = $DB->get_record('block_exaportitem', $conditions)) {
		print_error("wrong" . $type . "id", "block_exaport");
	}
} else {
	$existing = false;
}


// read + check type
if ($existing)
	$type = $existing->type;
else {
	$type = optional_param('type', 'all', PARAM_ALPHA);
	$type = block_exaport_check_item_type($type, false);
	if (!$type) {
		print_error("badtype", "block_exaport");
	}
}

//get competences from item if editing
$exacomp_active = block_exaport_check_competence_interaction();
if ($existing && $exacomp_active) {
	// for the tree:
	$existing->compids_array = array_keys(block_exaport_get_active_comps_for_item($existing));
	// for form:
	$existing->compids = join(',', $existing->compids_array);
}
$returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&categoryid=" . $categoryid;

// delete item
if ($action == 'delete' && $allowEdit) {
	if (!$existing) {
		print_error("bookmarknotfound", "block_exaport");
	}
	if (data_submitted() && $confirm && confirm_sesskey()) {
		require_sesskey();
		block_exaport_do_delete($existing, $returnurl, $courseid);
		redirect($returnurl);
	} else {
		$optionsyes = array('id' => $id, 'action' => 'delete', 'confirm' => 1, 'backtype' => $backtype, 'categoryid' => $categoryid, 'sesskey' => sesskey(), 'courseid' => $courseid);
		$optionsno = array('userid' => $existing->userid, 'courseid' => $courseid, 'type' => $backtype, 'categoryid' => $categoryid);

		block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), $action);
		// ev. noch eintrag anzeigen!!!
		//blog_print_entry($existing);
		echo '<br />';

		echo $OUTPUT->confirm(get_string("delete" . $type . "confirm", "block_exaport"), new moodle_url('item.php', $optionsyes), new moodle_url('view_items.php', $optionsno));
		echo block_exaport_wrapperdivend();
		echo $OUTPUT->footer();
		die;
	}
}

if ($action == 'movetocategory' && $allowEdit) {
	require_sesskey();

	if (!$existing) {
		die(block_exaport_get_string('bookmarknotfound'));
	}
	
	if (!$targetCategory = block_exaport_get_category(required_param('categoryid', PARAM_INT))) {
		die('target category not found');
	}

	$DB->update_record('block_exaportitem', (object)array(
		'id' => $existing->id,
		'categoryid' => $targetCategory->id
	));

	echo 'ok';
	exit;
}

require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id));

$useTextarea = false;
if ($existing && $existing->intro && preg_match('!<iframe!i', $existing->intro))
	$useTextarea = true;

$editform = new block_exaport_item_edit_form($_SERVER['REQUEST_URI'] . '&type=' . $type, Array('current' => $existing, 'useTextarea'=>$useTextarea, 'textfieldoptions' => $textfieldoptions, 'course' => $course, 'type' => $type, 'action' => $action, 'allowedit' => $allowEdit, 'allowresubmission' => $allowResubmission));

if ($editform->is_cancelled()) {
	redirect($returnurl);
} else if ($editform->no_submit_button_pressed()) {
	die("nosubmitbutton");
	//no_submit_button_actions($editform, $sitecontext);
} else if (($fromform = $editform->get_data()) && $allowEdit) {
	require_sesskey();

	switch ($action) {
		case 'add':
			$fromform->type = $type;
			$fromform->compids = $compids;

			block_exaport_do_add($fromform, $editform, $returnurl, $courseid, $textfieldoptions, $useTextarea);
			break;

		case 'edit':
			$fromform->type = $type;
			if (!$existing) {
				print_error("bookmarknotfound", "block_exaport");
			}

			block_exaport_do_edit($fromform, $editform, $returnurl, $courseid, $textfieldoptions, $useTextarea);
			break;

		default:
			print_error("unknownaction", "block_exaport");
	}

	redirect($returnurl);
}

$strAction = "";
$extra_content = '';
// gui setup
$post = new stdClass();
$post->introformat = FORMAT_HTML;
$post->allowedit = $allowEdit;

switch ($action) {
	case 'add':
		$post->action = $action;
		$post->courseid = $courseid;
		$post->categoryid = $categoryid;

		$strAction = get_string('new');

		break;
	case 'edit':
		if (!$existing) {
			print_error("bookmarknotfound", "block_exaport");
		}
		$post->id = $existing->id;
		$post->name = $existing->name;
		$post->intro = $existing->intro;
		$post->categoryid = $existing->categoryid;
		$post->userid = $existing->userid;
		$post->action = $action;
		$post->courseid = $courseid;
		$post->type = $existing->type;
		$post->compids = isset($existing->compids) ? $existing->compids : '';
		$post->langid = $existing->langid;
		if (!empty($CFG->usetags)) {
			if ($CFG->branch < 31)
				$post->tags = tag_get_tags_array('block_exaportitem', $id);
			else 
				$post->tags = core_tag_tag::get_item_tags_array('block_exaport', 'block_exaportitem', $post->id);		
		}

		if (!$useTextarea)
			$post = file_prepare_standard_editor($post, 'intro', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'item_content', $post->id);

		$strAction = get_string('edit');
		$post->url = $existing->url;
		if ($type == 'link') {
			
		} elseif ($type == 'file') {
			if ($file = block_exaport_get_item_file($post)) {
				$ffurl = "{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=portfolio/id/" . $post->userid . "&itemid=" . $post->id;

				$extra_content = "<div class='block_eportfolio_center'>\n";
				if ($file->is_valid_image()) {
					$extra_content .= "<div class=\"item-detail-image\"><img src=\"$ffurl\" alt=\"" . format_string($post->name) . "\" /></div>";
				} else {
					$icon = $OUTPUT->pix_icon(file_file_icon($file), '');
					$extra_content .= "<p>".$icon.' '.$OUTPUT->action_link($ffurl, format_string($post->name), new popup_action ('click', $ffurl)) . "</p>";
				}
				$extra_content .= "</div>";
				
				// Filemanager for editing file
				$draftitemid = file_get_submitted_draft_itemid('file');
				$context = context_user::instance($USER->id);
				file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'item_file', $post->id,
										array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));				 
				$post->file = $draftitemid;   
			}
				
			if (!$extra_content && !$post->url) {
				$extra_content = 'File not found';
			}
		}
		
		// Filemanager for editing icon picture 
		$draftitemid = file_get_submitted_draft_itemid('iconfile');
		$context = context_user::instance($USER->id);
		file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'item_iconfile', $post->id,
								array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));				 
		$post->iconfile = $draftitemid;   

		break;
	default :
		print_error("unknownaction", "block_exaport");
}

$exacomp_active = block_exaport_check_competence_interaction() && $descriptorselection;

if ($exacomp_active) {
	$PAGE->requires->jquery();
	
	$PAGE->requires->js('/blocks/exaport/javascript/simpletreemenu.js', true);
	$PAGE->requires->css('/blocks/exaport/javascript/simpletree.css');
	
	$PAGE->requires->js('/blocks/exaport/javascript/jquery.colorbox.js', true);
	$PAGE->requires->css('/blocks/exaport/css/colorbox.css');
}

block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), $action);

if ($exacomp_active) {
	echo '<fieldset id="general" style="border: 1px solid #ddd; margin: 10px;">';
	echo '<legend class="ftoggler"><b>' . get_string("competences", "block_exaport") . '</b></legend>';
	if(file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php'))
		echo "<p style='margin-left: 5px;'><a class='competences' href='#'>" . get_string("selectcomps", "block_exaport") . "</a>";
	else{
		echo "<p style='margin-left: 5px;'".get_string("competences_old_version", "block_exaport");
	}
	echo "<div style='margin-left: 5px;' id='comptitles'></div></p>";
	echo '</fieldset>';
	?>
<div style="display: none">
	<div id='inline_comp_tree' style='padding: 10px; background: #fff;'>
		<h4>
			<?php echo get_string("opencomps", "block_exaport") ?>
		</h4>

		<a href="javascript:ddtreemenu.flatten('comptree', 'expand')"><?php echo get_string("expandcomps", "block_exaport") ?>
		</a> | <a href="javascript:ddtreemenu.flatten('comptree', 'contact')"><?php echo get_string("contactcomps", "block_exaport") ?>
		</a>

		<?php echo block_exaport_build_comp_tree('item', $existing, $allowEdit); ?>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	jQueryExaport(function($){

		$('#treeform :checkbox').click(function(e){
			// prevent item open/close
			e.stopPropagation();
		});
		$(".competences").colorbox({width:"75%", height:"75%", inline:true, href:"#inline_comp_tree", onClosed: function(){
			// save ids to input field
			var compids = '';
			$descriptors.filter(':checked').each(function() { compids += this.value+','; });
			$compids.val(compids);

			build_competence_output();
		}});
		ddtreemenu.createTree("comptree", true);

		var $compids = $('input[name=compids]');
		var $descriptors = $('#treeform :checkbox');

		function build_competence_output() {
			var $tree = $('#comptree').clone();
			// remove original id, conflicts with real tree
			$tree.attr('id', 'comptree-selected');

			// delete all not checked
			$tree.find('li').each(function(){
				if (!$(this).find(':checked').length) {
					$(this).remove();
				}
			});

			// delete checkboxes
			$tree.find(':checkbox').remove();

			$("#comptitles").empty().append($tree);
			ddtreemenu.createTree("comptree-selected", false);

			// open all
			ddtreemenu.flatten('comptree-selected', 'expand');
		}

		build_competence_output();
	});
//]]>
</script>
<?php
}

$editform->set_data($post);
echo $OUTPUT->box($extra_content);
$editform->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

/**
 * Update item in the database
 */
function block_exaport_do_edit($post, $blogeditform, $returnurl, $courseid, $textfieldoptions, $useTextarea) {
	global $CFG, $USER, $DB;

	$post->timemodified = time();
	if (!$useTextarea) {
		$post->introformat = FORMAT_HTML;
		$post = file_postupdate_standard_editor($post, 'intro', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'item_content', $post->id);
	}
	
	if(!empty($post->url)){
		if ($post->url=='http://') $post->url="";
		else if (strpos($post->url,'http://') === false && strpos($post->url,'https://') === false) $post->url = "http://".$post->url;
	}
	
	$context = context_user::instance($USER->id);
	// Updating file.
	if ($post->type == 'file') {
		// checking userquoata
		$upload_filesizes = block_exaport_get_filesize_by_draftid($post->file);
		if (block_exaport_file_userquotecheck($upload_filesizes, $post->id) && block_exaport_get_maxfilesize_by_draftid_check($post->file)) {
			file_save_draft_area_files($post->file, $context->id, 'block_exaport', 'item_file', $post->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
		};
	}

	// icon for item
	// checking userquoata
	$upload_filesizes = block_exaport_get_filesize_by_draftid($post->iconfile);
	if (block_exaport_file_userquotecheck($upload_filesizes, $post->id) && block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)) {
		file_save_draft_area_files($post->iconfile, $context->id, 'block_exaport', 'item_iconfile', $post->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
	};
	
	if ($DB->update_record('block_exaportitem', $post)) {
		block_exaport_add_to_log(SITEID, 'bookmark', 'update', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=edit', $post->name);
	} else {
		print_error('updateposterror', 'block_exaport', $returnurl);
	}
	$interaction = block_exaport_check_competence_interaction();
	if ($interaction) {
		$DB->delete_records('block_exacompcompactiv_mm', array("activityid" => $post->id, "eportfolioitem" => 1));
		$DB->delete_records('block_exacompcompuser_mm', array("activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
		$comps = $post->compids;
		if ($comps) {
			$comps = explode(",", $comps);
			$course = $DB->get_record('course', array("id" => $courseid));

			foreach ($comps as $comp) {
				if ($comp != 0)
					$DB->insert_record('block_exacompcompactiv_mm', array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "activitytitle" => $post->name, "coursetitle" => $course->shortname));
				$DB->insert_record('block_exacompcompuser_mm', array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id, "userid" => $USER->id, "role" => 0));
			}
		}
	}
	
	// Tags.
	if ($CFG->branch < 31)
		// Moodle before v3.1
		tag_set('block_exaportitem', $post->id, $post->tags, 'block_exaport', context_user::instance($USER->id)->id);
	else 
		// Moodle v3.1
		core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $post->id, context_user::instance($USER->id), $post->tags);

}

/**
 * Write a new item into database
 */
function block_exaport_do_add($post, $blogeditform, $returnurl, $courseid, $textfieldoptions, $useTextarea) {
	global $CFG, $USER, $DB;

	$post->userid = $USER->id;
	$post->timemodified = time();
	$post->courseid = $courseid;
	if (!$useTextarea)
		$post->intro = '';
	
	if(!empty($post->url)){
		if ($post->url=='http://') $post->url="";
		else if (strpos($post->url,'http://') === false && strpos($post->url,'https://') === false) $post->url = "http://".$post->url;
	}
	// Insert the new blog entry.
	if ($post->id = $DB->insert_record('block_exaportitem', $post)) {
		if (!$useTextarea) {
			$post->introformat = FORMAT_HTML;
			$post = file_postupdate_standard_editor($post, 'intro', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'item_content', $post->id);
			$DB->update_record('block_exaportitem', $post);
		}

		$context = context_user::instance($USER->id);
		if ($post->type == 'file') {
			// save uploaded file in user filearea
			// checking userquoata
			$upload_filesizes = block_exaport_get_filesize_by_draftid($post->file);
			if (block_exaport_file_userquotecheck($upload_filesizes, $post->id) && block_exaport_get_maxfilesize_by_draftid_check($post->file)) {
				file_save_draft_area_files($post->file, $context->id, 'block_exaport', 'item_file', $post->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
			}
		}
		
		// icon picture
		if ($post->iconfile) {
			// checking userquoata
			$upload_filesizes = block_exaport_get_filesize_by_draftid($post->iconfile);
			if (block_exaport_file_userquotecheck($upload_filesizes, $post->id) && block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)) {
				file_save_draft_area_files($post->iconfile, $context->id, 'block_exaport', 'item_iconfile', $post->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
			}
		}
		
		$comps = $post->compids;
		if ($comps) {
			$comps = explode(",", $comps);
			$course = $DB->get_record('course', array("id" => $courseid));

			foreach ($comps as $comp) {
				if ($comp != 0)
					$DB->insert_record('block_exacompcompactiv_mm', array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "activitytitle" => $post->name, "coursetitle" => $course->shortname));
				$DB->insert_record('block_exacompcompuser_mm', array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id, "userid" => $USER->id, "role" => 0));
			}
		}
		block_exaport_add_to_log(SITEID, 'bookmark', 'add', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=add', $post->name);
			
		// Tags.
		if ($CFG->branch < 31) 
			// Moodle before v3.1
			tag_set('block_exaportitem', $post->id, $post->tags, 'block_exaport', context_user::instance($USER->id)->id);
		else 			
			// Moodle v3.1
			core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $post->id, context_user::instance($USER->id), $post->tags);
	} else {
		print_error('addposterror', 'block_exaport', $returnurl);
	}
}

/**
 * Delete item from database
 */
function block_exaport_do_delete($post, $returnurl = "", $courseid = 0) {

	global $DB, $USER;

	// try to delete the item file
	block_exaport_file_remove($post);

	$conditions = array("id" => $post->id);
	$status = $DB->delete_records('block_exaportitem', $conditions);

	$interaction = block_exaport_check_competence_interaction();
	if ($interaction) {
		$DB->delete_records('block_exacompcompactiv_mm', array("activityid" => $post->id, "eportfolioitem" => 1));
		$DB->delete_records('block_exacompcompuser_mm', array("activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
		$DB->delete_records('block_exacompitemexample', array('itemid' => $post->id));
	}
	
	
	block_exaport_add_to_log(SITEID, 'blog', 'delete', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=delete&confirm=1', $post->name);

	if (!$status) {
		print_error('deleteposterror', 'block_exaport', $returnurl);
	}
}
