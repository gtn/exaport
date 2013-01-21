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

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$confirm = optional_param("confirm", "", PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);
$shareusers = optional_param('shareusers', '', PARAM_RAW); // array of integer

if (!confirm_sesskey()) {
	print_error("badsessionkey","block_exaport");    	
}

$url = '/blocks/exabis_competences/views_mod.php';
$PAGE->set_url($url);

require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('block/exaport:use', $context);


if (!$COURSE) {
   print_error("invalidcourseid","block_exaport");
}
//echo json_encode(exaport_get_shareable_courses_with_users('sharing'));
if ($action == 'userlist') {
	echo json_encode(exaport_get_shareable_courses_with_users('sharing'));
	exit;
}


if ($id) {
	$conditions = array("id" => $id, "userid" => $USER->id);
	if (!$view = $DB->get_record('block_exaportview', $conditions)) {
		print_error("wrongviewid", "block_exaport");
	}
} else {
	$view  = null;
}

$returnurl = $CFG->wwwroot.'/blocks/exaport/views_list.php?courseid='.$courseid;

// delete item
if ($action == 'delete') {
	if (!$view) {
		print_error("viewnotfound", "block_exaport");        
	}
	if (data_submitted() && $confirm && confirm_sesskey()) {
		$conditions = array("viewid" => $view->id);
		$DB->delete_records('block_exaportviewblock', $conditions);
		$conditions = array("id" => $view->id);
		$status = $DB->delete_records('block_exaportview', $conditions);
		
		add_to_log(SITEID, 'blog', 'delete', 'views_mod.php?courseid='.$courseid.'&id='.$view->id.'&action=delete&confirm=1', $view->name);

		if (!$status) {
			print_error('deleteposterror', 'block_exaport', $returnurl);
		}
		redirect($returnurl);
	} else {
		$optionsyes = array('id'=>$id, 'action'=>'delete', 'confirm'=>1, 'sesskey'=>sesskey(), 'courseid'=>$courseid);
		$optionsno = array('courseid'=>$courseid);

		block_exaport_print_header('views');
		echo '<br />';
		//notice_yesno(get_string("deletecheck", null, $view->name), 'views_mod.php', 'views_list.php', $optionsyes, $optionsno, 'post', 'get');
		echo $OUTPUT->confirm(get_string("deletecheck",null,$view->name), new moodle_url('views_mod.php', $optionsyes), new moodle_url('views_list.php', $optionsno));
                echo $OUTPUT->footer();
		die;
	}
}


$query = "select i.id, i.name, i.type, ic.name AS cname, ic2.name AS cname_parent, COUNT(com.id) As comments".
	 " from {block_exaportitem} i".
	 " join {block_exaportcate} ic on i.categoryid = ic.id".
	 " left join {block_exaportcate} ic2 on ic.pid = ic2.id".
	 " left join {block_exaportitemcomm} com on com.itemid = i.id".
	 " where i.userid=?".
	 " GROUP BY i.id, i.name, i.type, ic.name, ic2.name".
	 " ORDER BY i.name";
$portfolioItems = $DB->get_records_sql($query, array($USER->id));
if (!$portfolioItems) {
	$portfolioItems = array();
}

foreach ($portfolioItems as &$item) {
	if (null == $item->cname_parent) {
		$item->category = format_string($item->cname);
	} else {
		$item->category = format_string($item->cname_parent) . " &rArr; " . format_string($item->cname);
	}
	unset($item->cname);
	unset($item->cname_parent);
}
unset($item);


if ($view) {
	$conditions = array("viewid" => $view->id);
	$sharedUsers = $DB->get_records('block_exaportviewshar', $conditions, null, 'userid');
	if (!$sharedUsers) {
		$sharedUsers = array();
	} else {
		$sharedUsers = array_flip(array_keys($sharedUsers));
	}
} else {
	$sharedUsers = array();
}


require_once $CFG->libdir.'/formslib.php';

class block_exaport_view_edit_form extends moodleform {

	function definition() {
		global $CFG, $USER, $DB;
		$mform =& $this->_form;

		$mform->updateAttributes(array('class'=>''));
		
		$mform->addElement('hidden', 'items');
    $mform->addElement('hidden', 'action');
		$mform->addElement('hidden', 'courseid');
		$mform->addElement('hidden', 'viewid');

		$mform->addElement('text', 'name', get_string("title", "block_exaport"), 'maxlength="255" size="60"');
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', get_string("titlenotemtpy", "block_exaport"), 'required', null, 'client');

		$mform->addElement('textarea', 'description', get_string("title", "block_exaport"), 'cols="60" rows="5"');
		$mform->setType('description', PARAM_TEXT);

		if (block_exaport_course_has_desp()) {
			$langcode=get_string("langcode","block_desp");
			$sql = "SELECT lang.id,lang.".$langcode." as name FROM {block_desp_lang} lang WHERE id IN(SELECT langid FROM {block_desp_check_lang} WHERE userid=?) OR id IN (SELECT langid FROM {block_desp_lanhistories} WHERE userid=?) ORDER BY lang.".$langcode;
			$languages = $DB->get_records_sql_menu($sql, array($USER->id, $USER->id));

			$languages[0]='';
			asort($languages);
			$mform->addElement('select', 'langid', get_string("desp_language", "block_exaport"), $languages);
			$mform->setType('langid', PARAM_INT);
		}

		$mform->addElement('hidden', 'blocks');
		$mform->setType('blocks', PARAM_RAW);

		$mform->addElement('checkbox', 'externaccess');
		$mform->setType('externaccess', PARAM_INT);

		$mform->addElement('checkbox', 'internaccess');
		$mform->setType('internaccess', PARAM_INT);

		$mform->addElement('checkbox', 'externcomment');
		$mform->setType('externcomment', PARAM_INT);

		$mform->addElement('text', 'shareall');
		$mform->setType('shareall', PARAM_INT);

		if ($this->_customdata['view'])
			$this->add_action_buttons(false, get_string('savechanges'));
		else
			$this->add_action_buttons(false, get_string('add'));
	}

	function toArray() {
        //finalize the form definition if not yet done
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        $form = $this->_form->toArray();

		$form['html_hidden_fields'] = '';
		$form['elements_by_name'] = array();

		foreach ($form['elements'] as $element) {
			if ($element['type'] == 'hidden')
				$form['html_hidden_fields'] .= $element['html'];
			$form['elements_by_name'][$element['name']] = $element;
		}
	
		return $form;
    }
}


$editform = new block_exaport_view_edit_form($_SERVER['REQUEST_URI'], array('view' => $view, 'course' => $COURSE->id, 'action'=> $action));

if ($editform->is_cancelled()) {
	redirect($returnurl);
} else if ($editform->no_submit_button_pressed()) {
	die("nosubmitbutton");
	//no_submit_button_actions($editform, $sitecontext);
} else if ($formView = $editform->get_data()) {

	$dbView = $formView;
	$dbView->timemodified = time();

	if (!$view || !$view->hash) {
		// generate view hash
        do {
			$hash = substr(md5(microtime()), 3, 8);
        } while ($DB->record_exists("block_exaportview", array("hash"=>$hash)));
		$dbView->hash = $hash;
	}

	if (empty($dbView->externaccess)) {
		$dbView->externaccess = 0;
	}
	if (empty($dbView->internaccess)) {
		$dbView->internaccess = 0;
	}
	if (!$dbView->internaccess || empty($dbView->shareall)) {
		$dbView->shareall = 0;
	}
	if (empty($dbView->externcomment)) {
		$dbView->externcomment = 0;
	}

	switch ($action) {
		case 'add':

			$dbView->userid = $USER->id;

			if ($dbView->id = $DB->insert_record('block_exaportview', $dbView)) {
				add_to_log(SITEID, 'bookmark', 'add', 'views_mod.php?courseid='.$courseid.'&id='.$dbView->id.'&action=add', $dbView->name);
			} else {
				print_error('addposterror', 'block_exaport', $returnurl);
			}
		break;

		case 'edit':
			
			if (!$view) {
				print_error("viewnotfound", "block_exaport");	                
			}

			$dbView->id = $view->id;

			if ($DB->update_record('block_exaportview', $dbView)) {
				add_to_log(SITEID, 'bookmark', 'update', 'item.php?courseid='.$courseid.'&id='.$dbView->id.'&action=edit', $dbView->name);
			} else {
				print_error('updateposterror', 'block_exaport', $returnurl);
			}

		break;
		
		default:
			print_error("unknownaction", "block_exaport");
			exit;
	}

	// delete all blocks
	$DB->delete_records('block_exaportviewblock', array('viewid'=>$dbView->id));

	// add blocks
	$blocks = json_decode(stripslashes($formView->blocks));
	if(!$blocks)
		print_error("noentry","block_exaport");
	foreach ($blocks as $block) {
		$block->viewid = $dbView->id;
		$DB->insert_record('block_exaportviewblock', $block);
	}

	// delete all shared users
	$DB->delete_records("block_exaportviewshar", array('viewid'=>$dbView->id));

	// add new shared users
	if ($dbView->internaccess && !$dbView->shareall && is_array($shareusers)) {
		foreach ($shareusers as $shareuser) {
			$shareuser = clean_param($shareuser, PARAM_INT);
			
			$shareItem = new stdClass();
			$shareItem->viewid = $dbView->id;
			$shareItem->userid = $shareuser;
			$DB->insert_record("block_exaportviewshar", $shareItem);
		}

		// message users, if they have shared
		$notifyusers = optional_param('notifyusers', '', PARAM_RAW);
		if ($notifyusers) {
			foreach ($notifyusers as $notifyuser) {
				// only notify if he also is shared
				if (isset($shareusers[$notifyuser])) {
					// notify
					$notificationdata = new stdClass();
					$notificationdata->component        = 'block_exaport';
					$notificationdata->name             = 'sharing';
					$notificationdata->userfrom         = $USER;
					$notificationdata->userto           = $DB->get_record('user', array('id' => $notifyuser));
					// TODO: subject + message text
					$notificationdata->subject          = 'I shared an eportfolio view with you';
					$notificationdata->fullmessage      = $CFG->wwwroot.'/blocks/exaport/shared_view.php?courseid=1&access=id/'.$USER->id.'-'.$dbView->id;
					$notificationdata->fullmessageformat = FORMAT_PLAIN;
					$notificationdata->fullmessagehtml  = '';
					$notificationdata->smallmessage     = '';
					$notificationdata->notification     = 1;

					$mailresult = message_send($notificationdata);
				}
			}
		}
	}

	redirect($returnurl);
}

// gui setup
$postView = $view;
$postView->action       = $action;
$postView->courseid     = $courseid;

switch ($action) {
	case 'add':
		$postView->internaccess = 0;
		$postView->shareall = 1;

		$strAction = get_string('new');
		break;
	case 'edit':
		if (!isset($postView->internaccess) && ($postView->shareall || $sharedUsers)) {
			$postView->internaccess = 1;
		}

		$strAction = get_string('edit');
		break;
	default :
		print_error("unknownaction", "block_exaport");	                	            
}



if ($view) {
	$query = "select b.*".
		 " from {block_exaportviewblock} b".
		 " where b.viewid = ? ORDER BY b.positionx, b.positiony";

	$blocks = $DB->get_records_sql($query, array($view->id));
	$postView->blocks = json_encode($blocks);
}

$PAGE->requires->js('/blocks/exaport/javascript/jquery.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/jquery.ui.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/jquery.json.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/views_mod.js', true);
$PAGE->requires->css('/blocks/exaport/css/views_mod.css');

block_exaport_print_header('views');


$editform->set_data($postView);
$form = $editform->toArray();

// Translations
$translations = array(
	'name', 'role', 'nousersfound',
	'view_specialitem_headline', 'view_specialitem_headline_defaulttext', 'view_specialitem_text', 'view_specialitem_text_defaulttext',
	'viewitem', 'comments', 'category', 'type',
	'delete', 'viewand',
	'file', 'note', 'link',
	'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 
);


$translations = array_flip($translations);
foreach ($translations as $key => &$value) {
	$value = block_exaport_get_string($key);
}
unset($value);

?>
<script type="text/javascript">
//<![CDATA[
	var portfolioItems = <?php echo json_encode($portfolioItems); ?>;
	var sharedUsers = <?php echo json_encode($sharedUsers); ?>;
	ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
//]]>
</script>
<?php

echo $form['javascript'];
echo '<form'.$form['attributes'].'><div id="view-mod">';
echo $form['html_hidden_fields'];

// view data form
echo '<div class="view-data view-group'.(!$view?' view-group-open':'').'">';
	echo '<div class="view-group-header"><div>';
	echo get_string('view', 'block_exaport').': <span id="view-name">'.(!empty($postView->name)?$postView->name:'new').'</span> <span class="change">('.get_string('change', 'block_exaport').')</span>';
	echo '</div></div>';
	echo '<div class="view-group-body">';
		echo '<div class="mform">';
		echo '<fieldset class="clearfix"><legend class="ftoggler">'.get_string('viewinformation', 'block_exaport').'</legend>';
			echo '<div class="fitem required"><div class="fitemtitle"><label>'.get_string('viewtitle', 'block_exaport').'<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/pix/req.gif" /> </label></div><div class="felement ftext">'.$form['elements_by_name']['name']['html'].'</div></div>';
			echo '<div class="fitem"><div class="fitemtitle"><label>'.get_string('viewdescription', 'block_exaport').'</label></div><div class="felement ftext">'.$form['elements_by_name']['description']['html'].'</div></div>';
			if (block_exaport_course_has_desp()) {
				echo '<div class="fitem"><div class="fitemtitle"><label>'.get_string('desp_language', 'block_exaport').'</label></div><div class="felement ftext">'.$form['elements_by_name']['langid']['html'].'</div></div>';
			}
		echo '</fieldset>';
		echo '</div>';
	echo '</div>';
echo '</div>';

echo '<div class="view-middle">';
	echo '<div id="view-options">';
		echo '<div id="portfolioItems" class="view-group view-group-open">';
			echo '<div class="view-group-header"><div>'.get_string('viewitems', 'block_exaport').'</div></div>';
			echo '<div class="view-group-body">';
			echo '<ul class="portfolioOptions">';
				if (!$portfolioItems) {
					echo '<div style="padding: 5px;">'.get_string('nobookmarksall', 'block_exaport').'</div>';
				} else {
					foreach ($portfolioItems as $item) {
						echo '<li class="item" itemid="'.$item->id.'">'.$item->name.'</li>';
					}
				}
			echo '</ul>';
			echo '</div>';
		echo '</div>';

		echo '<div id="portfolioExtras" class="view-group view-group-open">';
			echo '<div class="view-group-header"><div>'.get_string('view_specialitems', 'block_exaport').'</div></div>';
			echo '<div class="view-group-body">';
			echo '<ul class="portfolioOptions">';
			echo '<li block-type="personal_information">'.get_string("explainpersonal", "block_exaport").'</li>';
			echo '<li block-type="headline">'.get_string('view_specialitem_headline', 'block_exaport').'</li>';
			echo '<li block-type="text">'.get_string('view_specialitem_text', 'block_exaport').'</li>';
			echo '</ul>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	echo '<div id="view-preview">';
		echo '<div class="view-group-header"><div>'.get_string('viewdesign', 'block_exaport').'</div></div>';
		echo '<div>';
			echo '<table cellspacing="0" cellpadding="0" width="100%"><tr><td style="width: 50%" valign="top">';
			echo '<ul class="portfolioDesignBlocks">';
			echo '</ul>';
			echo '</td><td style="width: 50%" valign="top">';
			echo '<ul class="portfolioDesignBlocks portfolioDesignBlocks-left">';
			echo '</ul>';
			echo '</td></tr></table>';
		echo '</div>';
	echo '</div>';
	echo '<div class="clear"><span>&nbsp;</span></div>';
echo '</div>';

echo '<div class="view-sharing view-group">';
	echo '<div class="view-group-header"><div>'.get_string('view_sharing', 'block_exaport').': <span id="view-share-text"></span> <span class="change">('.get_string('change', 'block_exaport').')</span></div></div>';
	echo '<div class="view-group-body">';
		echo '<div style="padding: 18px 22px"><table width="100%">';
			
			echo '<tr><td style="padding-right: 10px; width: 10px">';
			echo $form['elements_by_name']['externaccess']['html'];
			echo '</td><td>'.get_string("externalaccess", "block_exaport").'</td></tr>';
			
			if ($view) {
				$url = block_exaport_get_external_view_url($view);
				// only when editing a view, the external link will work!
				echo '<tr id="externaccess-settings"><td></td><td>';
					echo '<div style="padding: 4px;"><a href="'.$url.'" target="_blank">'.$url.'</a></div>';
					echo '<div style="padding: 4px 0;"><table width="100%">';
						echo '<tr><td style="padding-right: 10px; width: 10px">';
						echo '<input type="checkbox" name="externcomment" value="1"'.($postView->externcomment?' checked="checked"':'').' />';
						echo '</td><td>'.get_string("externcomment", "block_exaport").'</td></tr>';
					echo '</table></div>';
					/*
					echo '<table>';
					echo '<tr><td>'.$form['elements_by_name']['externcomment']['html'];
					echo '</td><td>'.get_string("externalaccess", "block_exaport").'</td></tr>';
					echo '</table>';
					*/
				echo '</td></tr>';
			}
		
			echo '<tr><td style="height: 10px"></td></tr>';

			echo '<tr><td style="padding-right: 10px">';
			echo $form['elements_by_name']['internaccess']['html'];
			echo '</td><td>'.get_string("internalaccess", "block_exaport").'</td></tr>';
			echo '<tr id="internaccess-settings"><td></td><td>';
				echo '<div style="padding: 4px 0;"><table width="100%">';
					echo '<tr><td style="padding-right: 10px; width: 10px">';
					echo '<input type="radio" name="shareall" value="1"'.($postView->shareall?' checked="checked"':'').' />';
					echo '</td><td>'.get_string("internalaccessall", "block_exaport").'</td></tr>';
					echo '<tr><td style="padding-right: 10px">';
					echo '<input type="radio" name="shareall" value="0"'.(!$postView->shareall?' checked="checked"':'').'/>';
					echo '</td><td>'.get_string("internalaccessusers", "block_exaport").'</td></tr>';
					echo '<tr id="internaccess-users"><td></td><td id="sharing-userlist">userlist</td></tr>';
				echo '</table></div>';
			echo '</td></tr>';

		echo '</table></div>';
	echo '</div>';
echo '</div>';

echo '<div style="padding-top: 20px; text-align: center;">';
echo $form['elements_by_name']['submitbutton']['html'];
echo '</div>';

echo '</div></form>';

//echo "<pre>";
// print_r($form);

echo $OUTPUT->footer();

