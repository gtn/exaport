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
$type = optional_param('type', 'content', PARAM_ALPHA);
if ($action=="add")
	$type="title";

//if (function_exists("clean_param_array")) $shareusers=clean_param_array($_POST["shareusers"],PARAM_SEQUENCE,true);
//else 
if (!empty($_POST["shareusers"])){
	$shareusers=$_POST["shareusers"];
	if (function_exists("clean_param_array")) $shareusers=clean_param_array($shareusers,PARAM_SEQUENCE,false);
}else{$shareusers="";}


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
/*	$view = new stdClass();
	$view->id = -1;
	// generate view hash
	do {
		$hash = substr(md5(microtime()), 3, 8);
    } while ($DB->record_exists("block_exaportview", array("hash"=>$hash)));
	$view->hash = $hash;/**/
}

$returnurl_to_list = $CFG->wwwroot.'/blocks/exaport/views_list.php?courseid='.$courseid;
$returnurl = $CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$courseid.'&id='.$id.'&sesskey='.sesskey().'&action=edit';

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
		redirect($returnurl_to_list);
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

$query = "select i.id, i.name, i.type, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, COUNT(com.id) As comments, files.mimetype as mimetype".
	 " from {block_exaportitem} i".
	 " join {block_exaportcate} ic on i.categoryid = ic.id".
	 " left join {block_exaportcate} ic2 on ic.pid = ic2.id".
	 " left join {block_exaportitemcomm} com on com.itemid = i.id".
	 " left join {files} files on (files.itemid = i.id and files.userid = i.userid)".
	 " where files.filesize>0 AND i.userid=?".
	 " GROUP BY i.id, i.name, i.type, i.type, i.url, ic.id, ic.name, ic2.name, files.mimetype".
	 " ORDER BY i.name";
	 //echo $query;
$portfolioItems = $DB->get_records_sql($query, array($USER->id));
if (!$portfolioItems) {
	$portfolioItems = array();
}

foreach ($portfolioItems as &$item) {
	if (null == $item->cname_parent) {
		$item->category = format_string($item->cname);
	} else {
		//$item->category = format_string($item->cname_parent) . " &rArr; " . format_string($item->cname);
		$catid= $item->catid;
			$catname = $item->cname;
			$item->category = "";
			do{
				$conditions = array("userid" => $USER->id, "id" => $catid);
				$cats=$DB->get_records_select("block_exaportcate", "userid = ? AND id = ?",$conditions, "name ASC");
				foreach($cats as $cat){
					if($item->category == "")
						$item->category =format_string($cat->name);
					else
						$item->category =format_string($cat->name)." &rArr; ".$item->category;
					$catid = $cat->pid;
			}
			
			}while ($cat->pid != 0);}
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
		$mform->updateAttributes(array('class'=>'', 'id'=>'view_edit_form'));

		$mform->addElement('hidden', 'items');
		$mform->addElement('hidden', 'action');
		$mform->addElement('hidden', 'courseid');
		$mform->addElement('hidden', 'viewid');
		if (optional_param('type', 'content', PARAM_ALPHA)<>'title' and optional_param("action", "", PARAM_ALPHA)<>'add')
			$mform->addElement('hidden', 'name');

		switch ($this->_customdata['type']) {
			case "title":	$mform->addElement('text', 'name', get_string("title", "block_exaport"), 'maxlength="255" size="60"');
							$mform->setType('name', PARAM_TEXT);
							$mform->addRule('name', get_string("titlenotemtpy", "block_exaport"), 'required', null, 'client');							
//							$mform->addElement('textarea', 'description', get_string("title", "block_exaport"), 'cols="60" rows="5"');
//							$mform->setType('description', PARAM_TEXT);

							$mform->addElement('editor', 'description_editor', get_string('viewdescription', 'block_exaport'), array('rows'=> '20', 'cols'=>'5'), array('maxfiles' => EDITOR_UNLIMITED_FILES));
							$mform->setType('description', PARAM_RAW);
							
							if (block_exaport_course_has_desp()) {
								$langcode=get_string("langcode","block_desp");
								$sql = "SELECT lang.id,lang.".$langcode." as name FROM {block_desp_lang} lang WHERE id IN(SELECT langid FROM {block_desp_check_lang} WHERE userid=?) OR id IN (SELECT langid FROM {block_desp_lanhistories} WHERE userid=?) ORDER BY lang.".$langcode;
								$languages = $DB->get_records_sql_menu($sql, array($USER->id, $USER->id));
								$languages[0]='';
								asort($languages);
								$mform->addElement('select', 'langid', get_string("desp_language", "block_exaport"), $languages);
								$mform->setType('langid', PARAM_INT);
							}
						break;									
			case "layout":
							$radioarray=array();
							for ($i=1; $i<=10; $i++)
								$radioarray[] = $mform->createElement('radio', 'layout', '', '', $i);
							$mform->addGroup($radioarray, 'radioar', '', array(' '), false);		
						break;
			case "content" :
							$mform->addElement('hidden', 'blocks');
							$mform->setType('blocks', PARAM_RAW);
						break;
			case "share" :
							$mform->addElement('checkbox', 'externaccess');
							$mform->setType('externaccess', PARAM_INT);
							
							$mform->addElement('checkbox', 'internaccess');
							$mform->setType('internaccess', PARAM_INT);
							
							$mform->addElement('checkbox', 'externcomment');
							$mform->setType('externcomment', PARAM_INT);
							
							$mform->addElement('text', 'shareall');
							$mform->setType('shareall', PARAM_INT);							
						break;
			default: break;
		};		
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

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>$context);

$editform = new block_exaport_view_edit_form($_SERVER['REQUEST_URI'], array('view' => $view, 'course' => $COURSE->id, 'action'=> $action, 'type'=>$type));

if ($editform->is_cancelled()) {
	redirect($returnurl_to_list);
} else if ($editform->no_submit_button_pressed()) {
	die("nosubmitbutton");
	//no_submit_button_actions($editform, $sitecontext);
} else if ($formView = $editform->get_data()) {

	if ($type=='title' or $action=='add') {
		if (!$view) {$view = new stdClass(); $view->id = -1;};			
		$formView = file_postupdate_standard_editor($formView, 'description', $textfieldoptions, $context, 'block_exaport', 'view', $view->id);
	}

	$dbView = $formView;
	$dbView->timemodified = time();
	if (!$view || !isset($view->hash)) {
		// generate view hash
        do {
			$hash = substr(md5(microtime()), 3, 8);
        } while ($DB->record_exists("block_exaportview", array("hash"=>$hash)));
		$dbView->hash = $hash;
	}

	if ($type=='share') {
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
	}/**/

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

// processing for blocks and shares	
	switch ($type) {
		case 'content':
			// delete all blocks
			$DB->delete_records('block_exaportviewblock', array('viewid'=>$dbView->id));
			// add blocks
			$blocks = json_decode($formView->blocks);
			if(!$blocks)
				print_error("noentry","block_exaport");
			foreach ($blocks as $block) {
				$block->viewid = $dbView->id;
				$DB->insert_record('block_exaportviewblock', $block);
			};
			
			if (optional_param('ajax', 0, PARAM_INT)) {
				$ret = new stdClass;
				$ret->ok = true;
				$ret->blocks = json_encode(get_view_blocks($dbView));
				
				echo json_encode($ret);
				exit;
			}
			
			break;
		case 'share':
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
				};
				// message users, if they have shared
				//$notifyusers = optional_param('notifyusers', '', PARAM_RAW);
				$notifyusers = $_POST['notifyusers'];
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
			};		
			break;
		default: break;
	};

/*	if ($action=="add")
		redirect($returnurl_to_list);
	else /**/
	$returnurl = $CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$courseid.'&id='.$dbView->id.'&sesskey='.sesskey().'&action=edit';
	redirect($returnurl);
}
// gui setup
$postView = ($view ? $view : new stdClass());
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

function get_view_blocks($view) {
	global $DB;
	
	$query = "select b.*".
		 " from {block_exaportviewblock} b".
		 " where b.viewid = ? ORDER BY b.positionx, b.positiony";

	return $DB->get_records_sql($query, array($view->id));
}

if ($view) {
	$postView->blocks = json_encode(get_view_blocks($view));
}

require_once $CFG->libdir.'/editor/tinymce/lib.php';
$tinymce = new tinymce_texteditor();

$PAGE->requires->js('/blocks/exaport/javascript/jquery.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/jquery.ui.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/jquery.json.js', true);
$PAGE->requires->js('/lib/editor/tinymce/tiny_mce/'.$tinymce->version.'/tiny_mce.js', true);
//$PAGE->requires->js('/lib/editor/tinymce/plugins/moodlemedia/tinymce/editor_plugin.js', true);
//$PAGE->requires->js('/lib/editor/tinymce/plugins/moodlenolink/tinymce/editor_plugin.js', true);
//$PAGE->requires->js('/lib/editor/tinymce/plugins/dragmath/tinymce/editor_plugin.js', true);
$PAGE->requires->js('/lib/editor/tinymce/module.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/views_mod.js', true);
$PAGE->requires->css('/blocks/exaport/css/views_mod.css');
$PAGE->requires->css('/blocks/exaport/css/blocks.css');

block_exaport_print_header('views');

$editform->set_data($postView);
if ($type<>'title') {// for delete php notes 
	$form = $editform->toArray();
	echo $form['javascript'];
	echo '<form'.$form['attributes'].'><div id="view-mod">';
	echo $form['html_hidden_fields'];
};
	

// Translations
$translations = array(
	'name', 'role', 'nousersfound',
	'view_specialitem_headline', 'view_specialitem_headline_defaulttext', 'view_specialitem_text', 'view_specialitem_text_defaulttext',
	'viewitem', 'comments', 'category', 'type',
	'delete', 'viewand',
	'file', 'note', 'link',
	'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs', 'notify',
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

echo "<!--[if IE]> <style> #link_thumbnail{ zoom: 0.2; } </style> <![endif]--> ";
switch ($type) {
	case 'content' :
		?>
		<script type="text/javascript">
		//<![CDATA[
			var portfolioItems = <?php echo json_encode($portfolioItems); ?>;
			jQueryExaport(exaportViewEdit.initContentEdit);
		//]]>
		</script>
		<?php
	
		// view data form
echo '<div id="blocktype-list">'.get_string('createpage', 'block_exaport');
echo '<ul>
    <li class="portfolioElement" title="'.get_string('personalinformation', 'block_exaport').'" block-type="personal_information">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/personal_info.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('personalinformation', 'block_exaport').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('personalinformation', 'block_exaport').'</div>
        </div>
    </li>
    <li class="portfolioElement" title="'.get_string('headertext', 'block_exaport').'" block-type="headline">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/header_text.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('headertext', 'block_exaport').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('headertext', 'block_exaport').'</div>
        </div>
    </li>
    <li class="portfolioElement" title="'.get_string('view_specialitem_text', 'block_exaport').'" block-type="text">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/text.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('view_specialitem_text', 'block_exaport').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('view_specialitem_text', 'block_exaport').'</div>
        </div>
    </li>
    <li class="portfolioElement" title="'.get_string('items', 'block_exaport').'" block-type="item">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/lists.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('items', 'block_exaport').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('selectitems','block_exaport').'</div>
        </div>
    </li>	
</ul>';
echo '</div>';

$cols_layout = array (
	"1" => 1, 	"2" => 2,	"3" => 2,	"4" => 2,	"5" => 3,	"6" => 3,	"7" => 3,	"8" => 4,	"9" => 4,	"10" => 5
);

// default layout
if (!isset($view->layout)) 
	$view->layout = 2;

echo '<div class="view-middle">';

	echo '<div id="view-preview">';
		echo '<div class="view-group-header"><div>'.get_string('viewdesign', 'block_exaport').'</div></div>';
		echo '<div>';
			echo '<table class="table_layout layout'.$view->layout.'"><tr>';
			for ($i=1; $i<=$cols_layout[$view->layout]; $i++) {
				echo '<td class="td'.$i.'">';
				echo '<ul class="portfolioDesignBlocks">';
				echo '</ul>';
				echo '</td>';
			};
//			echo '<ul class="portfolioDesignBlocks portfolioDesignBlocks-left">';
			echo '</tr></table>';
		echo '</div>';
	echo '</div>';
	echo '<div class="clear"><span>&nbsp;</span></div>';
echo '</div>';

//include dirname(__FILE__).'/blocks_tmpl.php';

break;
// --------------------
	case 'title' : 
			echo '<div class="mform">';
			echo '<fieldset class="clearfix"><legend class="ftoggler">'.get_string('viewinformation', 'block_exaport').'</legend>';
//				echo '<div class="fitem required"><div class="fitemtitle"><label>'.get_string('viewtitle', 'block_exaport').'<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/pix/req.gif" /> </label></div><div class="felement ftext">'.$form['elements_by_name']['name']['html'].'</div></div>';
//				echo '<div class="fitem"><div class="fitemtitle"><label>'.get_string('viewdescription', 'block_exaport').'</label></div><div class="felement ftext">'.$form['elements_by_name']['description']['html'].'</div></div>';

			$data = new stdClass();
			$data->courseid = $courseid;
			if (isset($view) and $view->id>0) {
				$data->description = $view->description;
				$data->descriptionformat = FORMAT_HTML;
			};
			$data->cataction = 'save';
			$data->edit = 1;
			if (isset($view))
				$data = file_prepare_standard_editor($data, 'description', $textfieldoptions, $context, 'block_exaport', 'veiw', $view->id);
			$editform ->set_data($data);
		
			$editform->display();				
			echo '</fieldset>';
			echo '</div>';
		break;
// --------------------		
	case 'layout' :
		if (!isset($view->layout)) 
		$view->layout = 2;
			echo '
			<p>'.get_string('chooselayout','block_exaport').'</p>
			<div class="select_layout">
            <hr class="cb" />
				<div class="fl columnoption"><strong>'.get_string("viewlayoutgroup1", "block_exaport").'</strong></div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="1" type="radio" '.($view->layout==1?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-100.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout1", "block_exaport").'</div>
				</div>
			<hr class="cb" />
				<div class="fl columnoption"><strong>'.get_string("viewlayoutgroup2", "block_exaport").'</strong></div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="2" type="radio" '.($view->layout==2?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-50-50.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout2", "block_exaport").'</div>
				</div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="3" type="radio" '.($view->layout==3?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-67-33.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout3", "block_exaport").'</div>
				</div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="4" type="radio" '.($view->layout==4?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-33-67.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout4", "block_exaport").'</div>
				</div>
			<hr class="cb" />
				<div class="fl columnoption"><strong>'.get_string("viewlayoutgroup3", "block_exaport").'</strong></div>
				<div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="5" type="radio" '.($view->layout==5?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-33-33-33.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout5", "block_exaport").'</div>
				</div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="6" type="radio" '.($view->layout==6?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-25-50-25.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout6", "block_exaport").'</div>
				</div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="7" type="radio" '.($view->layout==7?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-15-70-15.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout7", "block_exaport").'</div>
				</div>
            <hr class="cb" />
				<div class="fl columnoption"><strong>'.get_string("viewlayoutgroup4", "block_exaport").'</strong></div>
					<div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="8" type="radio" '.($view->layout==8?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-25-25-25-25.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout8", "block_exaport").'</div>
				</div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="9" type="radio" '.($view->layout==9?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-20-30-30-20.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout9", "block_exaport").'</div>
				</div>
            <hr class="cb" />
				<div class="fl columnoption"><strong>'.get_string("viewlayoutgroup5", "block_exaport").'</strong></div>
				<div class="fl layoutoptions">
					<div class="radiobutton"><input class="radio" name="layout" value="10" type="radio" '.($view->layout==10?'checked="checked"':'').' /></div>
					<div class="layoutimg"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/vl-20-20-20-20-20.png" alt="" /></div>
					<div class="layoutdescription">'.get_string("viewlayout10", "block_exaport").'</div>
				</div>
			</div>';	
		break;
// --------------------		
	case 'share' :
		echo '<div class="view-sharing view-group">';
			echo '<div class="view-group-header"><div>'.get_string('view_sharing', 'block_exaport').': <span id="view-share-text"></span></div></div>';
			echo '<div class="">';
				echo '<div style="padding: 18px 22px"><table class="table_share">';
			
					echo '<tr><td style="padding-right: 10px; width: 10px">';
					echo $form['elements_by_name']['externaccess']['html'];
					echo '</td><td>'.get_string("externalaccess", "block_exaport").'</td></tr>';
					
					if ($view) {
						$url = block_exaport_get_external_view_url($view);
						// only when editing a view, the external link will work!
						echo '<tr id="externaccess-settings"><td></td><td>';
							echo '<div style="padding: 4px;"><a href="'.$url.'">'.$url.'</a></div>';
							echo '<div style="padding: 4px 0;"><table>';
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
						echo '<div style="padding: 4px 0;"><table>';
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
		break;
	default: break;
}

if ($type!='title') {
	echo '<div style="padding-top: 20px; text-align: center; clear: both;">';
	echo $form['elements_by_name']['submitbutton']['html'];
	echo '</div>';
	echo '</div></form>';
};

echo '<div id="block_form" class="block" style="position: absolute; top: 10px; left: 30%; width: 510px;">
        <div class="block-controls">                
            <a class="delete" title="'.get_string('closewindow').'" onclick="exaportViewEdit.cancelAddEdit();" href="#"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/remove-block.png" alt="" /></a>
        </div>
        <div class="block-header">
            <h4>'.get_string('cofigureblock','block_exaport').'</h4>
        </div>
        <div class="block-content">
			<div id="container"></div>
		</div>
	</div>
	<script type="text/javascript"> // for valid html and move block to body parent
		jQueryExaport("#block_form").appendTo("#page-blocks-exabis_competences-views_mod");
	</script>
	';
echo $OUTPUT->footer();	
?>