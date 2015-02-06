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
require_once dirname(__FILE__).'/blockmediafunc.php';

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

$context = context_system::instance();
require_capability('block/exaport:use', $context);


if (!$COURSE) {
   print_error("invalidcourseid","block_exaport");
}

if ($id) {
	$conditions = array("id" => $id, "userid" => $USER->id);
	if (!$view = $DB->get_record('block_exaportview', $conditions)) {
		print_error("wrongviewid", "block_exaport");
	}
} else {
	//$view  = null;
	$view = new stdClass();
	$view->id = null;
	/*
	// generate view hash
	do {
		$hash = substr(md5(microtime()), 3, 8);
    } while ($DB->record_exists("block_exaportview", array("hash"=>$hash)));
	$view->hash = $hash;/**/
}

if ($view && $action == 'userlist') {
	echo json_encode(exaport_get_shareable_courses_with_users_for_view($view->id));
	exit;
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
		
		block_exaport_add_to_log(SITEID, 'blog', 'delete', 'views_mod.php?courseid='.$courseid.'&id='.$view->id.'&action=delete&confirm=1', $view->name);

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
		echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer();
		die;
	}
}



if ($view) {
	$hasSharedUsers = !!$DB->count_records('block_exaportviewshar', array("viewid" => $view->id));
} else {
	$hasSharedUsers = false;
}


require_once $CFG->libdir.'/formslib.php';

class block_exaport_view_edit_form extends moodleform {

	function definition() {
		global $CFG, $USER, $DB;
		$mform =& $this->_form;
		$mform->updateAttributes(array('class'=>'', 'id'=>'view_edit_form'));

		$mform->setType('items', PARAM_RAW);
		$mform->setType('draft_itemid', PARAM_TEXT);
		$mform->setType('action', PARAM_TEXT);
		$mform->setType('courseid', PARAM_INT);
		$mform->setType('viewid', PARAM_INT);
		$mform->setType('name', PARAM_TEXT);
        $mform->setType('autofill_artefacts', PARAM_TEXT);
		$mform->addElement('hidden', 'items');
		$mform->addElement('hidden', 'draft_itemid');
		$mform->addElement('hidden', 'action');
		$mform->addElement('hidden', 'courseid');
		$mform->addElement('hidden', 'viewid');
        $mform->addElement('hidden', 'autofill_artefacts');
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
                            
                            if ($this->_customdata['view']) {
                                // Auto generate view with the artefacts checkbos.
                                $artefacts = block_exaport_get_portfolio_items(1);
                                if (count($artefacts) > 0) {
                                    if ($this->_customdata['view']->id > 0) {
                                        foreach ($artefacts as $artefact) {
                                            $allartefacts[] = $artefact->id;
                                        };
                                        $filledartefacts = explode(',', $this->_customdata['view']->autofill_artefacts);
                                        sort($filledartefacts);
                                        sort($allartefacts);
                                        $diff = array_diff($allartefacts, $filledartefacts);
                                        if (count($diff)>0) {
                                            $mform->addElement('checkbox', 'autofill_add', '', get_string('autofillview_addartefacts', 'block_exaport'));
                                        };
                                    } else {
                                        $mform->addElement('checkbox', 'autofill', '', get_string('autofillview', 'block_exaport'));
                                    };
                                };
                                // Share to cheacher checkbox.
                                $allteachers = block_exaport_get_course_teachers();
                                // If view is editing.
                                if ($this->_customdata['view']->id > 0) {
                                    $allsharedusers = block_exaport_get_shared_users($this->_customdata['view']->id);
                                    $diff = array_diff($allteachers, $allsharedusers);
                                    // If there is teacher which does not share.
                                    if ((count($allteachers) > 0) && (count($diff) > 0)) {
                                        $mform->addElement('checkbox', 'sharetoteacher', '', get_string('sharetoteacher_add', 'block_exaport'));
                                    };
                                } else { // If view is adding.
                                        $mform->addElement('checkbox', 'sharetoteacher', '', get_string('sharetoteacher', 'block_exaport'));
                                };
                            };

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
							
							if (block_exaport_shareall_enabled()) {
								$mform->addElement('text', 'shareall');
								$mform->setType('shareall', PARAM_INT);							
							}
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

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id)->id);

$editform = new block_exaport_view_edit_form($_SERVER['REQUEST_URI'], array('view' => $view, 'course' => $COURSE->id, 'action'=> $action, 'type'=>$type));

if ($editform->is_cancelled()) {
	redirect($returnurl_to_list);
} else if ($editform->no_submit_button_pressed()) {
	die("nosubmitbutton");
	//no_submit_button_actions($editform, $sitecontext);
} else if ($formView = $editform->get_data()) {
	if ($type=='title' or $action=='add') {
		//if (!$view) {$view = new stdClass(); $view->id = -1;};			
		$formView = file_postupdate_standard_editor($formView, 'description', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'view', $view->id);
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
		if (!block_exaport_shareall_enabled() || !$dbView->internaccess || empty($dbView->shareall)) {
			$dbView->shareall = 0;
		}
		if (empty($dbView->externcomment)) {
			$dbView->externcomment = 0;
		}
	}/**/

	switch ($action) {
		case 'add':

			$dbView->userid = $USER->id;
			if (empty($dbView->layout)  || $dbView->layout==0)  $dbView->layout=2;
			if ($dbView->id = $DB->insert_record('block_exaportview', $dbView)) {
                // Auto fill with the artefacts.
                if (isset($dbView->autofill) and $dbView->autofill == 1) {
                    $filledartefacts = fill_view_with_artefacts($dbView->id);
                    $dbView->autofill_artefacts = $filledartefacts;
                    $DB->update_record('block_exaportview', $dbView);
                }
                // Auto Share to the teachers.
                if (isset($dbView->sharetoteacher) and $dbView->sharetoteacher == 1) {
                    share_view_to_teachers($dbView->id);
                };
				block_exaport_add_to_log(SITEID, 'bookmark', 'add', 'views_mod.php?courseid='.$courseid.'&id='.$dbView->id.'&action=add', $dbView->name);
			} else {
				print_error('addposterror', 'block_exaport', $returnurl);
			}
		break;

		case 'edit':	
			if (!$view) {
				print_error("viewnotfound", "block_exaport");	                
			}

			$dbView->id = $view->id;
			if (empty($dbView->layout) || $dbView->layout==0) {
				if (empty($view->layout) || $view->layout==0)  
					$dbView->layout=2;
				else 
					$dbView->layout=$view->layout;
			};
            // Add new artefacts if selected.
            if (isset($dbView->autofill_add) and $dbView->autofill_add == 1) {
                    $filledartefacts = fill_view_with_artefacts($dbView->id, $dbView->autofill_artefacts);
                    $dbView->autofill_artefacts = $filledartefacts;
            };
            // Auto Share to the teachers.
            if (isset($dbView->sharetoteacher) and $dbView->sharetoteacher == 1) {
                share_view_to_teachers($dbView->id);
            };
			if ($DB->update_record('block_exaportview', $dbView)) {
				block_exaport_add_to_log(SITEID, 'bookmark', 'update', 'item.php?courseid='.$courseid.'&id='.$dbView->id.'&action=edit', $dbView->name);
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
			
			$blocks = file_save_draft_area_files(required_param('draft_itemid', PARAM_INT), context_user::instance($USER->id)->id, 'block_exaport', 'view_content', $view->id, 
								array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id)), 
								$formView->blocks);
								
			$blocks = json_decode($blocks);
			
		
			if(!$blocks)
				print_error("noentry","block_exaport");
			
			foreach ($blocks as $block) {
				$block->viewid = $dbView->id;

				// media process
				if ($block->type=='media') {
					if (!empty($block->contentmedia)) {
						if (empty($block->width)) $block->width = 360; else $block->width = (int) $block->width;
						if (empty($block->height)) $block->height = 240; else $block->height = (int) $block->height;
						$block->contentmedia = process_media_url($block->contentmedia, $block->width, $block->height);
					}
					
					if (!empty($block->create_as_note)) {
						$newItem = new stdClass;
						$newItem->name = $block->block_title;
						$newItem->type = 'note';
						$newItem->categoryid = 0;
						$newItem->userid = $USER->id;
						$newItem->intro = $block->contentmedia;
						$newItem->timemodified = time();

						$block->itemid = $DB->insert_record('block_exaportitem', $newItem);
						$block->type = 'item';
						$block->block_title = '';
						$block->contentmedia = '';
						$block->width = 0;
						$block->height = 0;/**/
					}
				}
				
				$block->id = $DB->insert_record('block_exaportviewblock', $block);
			}
			
			if (optional_param('ajax', 0, PARAM_INT)) {
				$ret = new stdClass;
				$ret->ok = true;
				file_prepare_draft_area($view->draft_itemid,context_user::instance($USER->id)->id, 'block_exaport', 'view_content', $view->id, array('subdirs'=>true), null);
				$ret->blocks = json_encode(block_exaport_get_view_blocks($view));
				
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
				if (isset($_POST['notifyusers'])) {
					$notifyusers = $_POST['notifyusers'];
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
							$notificationdata->subject          = get_string('i_shared', 'block_exaport');
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
			
			if (optional_param('share_to_other_users_submit', '', PARAM_RAW)) {
				// search button pressed -> redirect to search form
				redirect(new moodle_url('/blocks/exaport/views_mod_share_user_search.php',
					array('courseid' => $courseid, 'id' => $dbView->id, 'q' => optional_param('share_to_other_users_q', '', PARAM_RAW))));
				exit;
			}
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
$postView->draft_itemid = null;

file_prepare_draft_area($postView->draft_itemid,context_user::instance($USER->id)->id, 'block_exaport', 'view_content', $view->id, array('subdirs'=>true), null);

// we need to copy additional files from the personal information to the views editor, just in case if the personal information is added
copy_personal_information_draft_files($postView->draft_itemid, context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', $USER->id, array('subdirs'=>true), null);
function copy_personal_information_draft_files($targetDraftitemid, $contextid, $component, $filearea, $itemid, array $options=null, $text=null) {
	global $USER;
	
	// copy from filelib.php
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();

	$file_record = array('contextid'=>$usercontext->id, 'component'=>'user', 'filearea'=>'draft', 'itemid'=>$targetDraftitemid);
	if (!is_null($itemid) and $files = $fs->get_area_files($contextid, $component, $filearea, $itemid)) {
		foreach ($files as $file) {
			if ($file->is_directory() and $file->get_filepath() === '/') {
				// we need a way to mark the age of each draft area,
				// by not copying the root dir we force it to be created automatically with current timestamp
				continue;
			}
			if (!$options['subdirs'] and ($file->is_directory() or $file->get_filepath() !== '/')) {
				continue;
			}

			if ($tmp = $fs->get_file($file_record['contextid'], $file_record['component'], $file_record['filearea'],
					$file_record['itemid'], $file->get_filepath(), $file->get_filename())) {
				continue;
			}

			$draftfile = $fs->create_file_from_storedfile($file_record, $file);
			// XXX: This is a hack for file manager (MDL-28666)
			// File manager needs to know the original file information before copying
			// to draft area, so we append these information in mdl_files.source field
			// {@link file_storage::search_references()}
			// {@link file_storage::search_references_count()}
			$sourcefield = $file->get_source();
			$newsourcefield = new stdClass;
			$newsourcefield->source = $sourcefield;
			$original = new stdClass;
			$original->contextid = $contextid;
			$original->component = $component;
			$original->filearea  = $filearea;
			$original->itemid    = $itemid;
			$original->filename  = $file->get_filename();
			$original->filepath  = $file->get_filepath();
			$newsourcefield->original = file_storage::pack_reference($original);
			$draftfile->set_source(serialize($newsourcefield));
			// End of file manager hack
		}
	}
}

$postView->viewid = $view->id;

switch ($action) {
	case 'add':
		$postView->internaccess = 0;
		$postView->shareall = 0;

		$strAction = get_string('new');
		break;
	case 'edit':
		if (!isset($postView->internaccess) && ($postView->shareall || $hasSharedUsers)) {
			$postView->internaccess = 1;
		}
		$strAction = get_string('edit');
		break;
	default :
		print_error("unknownaction", "block_exaport");
}

/**
 * Autofill the view with all existing artefacts
 * @param integer $viewid
 * @param string $existingartefacts 
 * @return string Artefacts
 */
function fill_view_with_artefacts($viewid, $existingartefacts='') {
	global $DB, $USER;
    
    $artefacts = block_exaport_get_portfolio_items(1);
    if ($existingartefacts<>'') {
        $existingartefactsarray = explode(',', $existingartefacts); 
        $filledartefacts = $existingartefacts;
    } else {
        $existingartefactsarray = array();
        $filledartefacts = '';
    }
    if (count($artefacts)>0) {
        $y = 1;
        foreach ($artefacts as $artefact) {
            if (!in_array($artefact->id, $existingartefactsarray)) {
                $block = new stdClass();
                $block->itemid = $artefact->id;
                $block->viewid = $viewid;
                $block->type = 'item';
                $block->positionx = 1;
                $block->positiony = $y;
                $block->id = $DB->insert_record('block_exaportviewblock', $block);
                $y++;
                $filledartefacts .= ','.$artefact->id;
            }
        }
        if ($existingartefacts == '') {        
            $filledartefacts = substr($filledartefacts, 1);
        };
    }; /**/
    return $filledartefacts;
}

/**
 * Autoshare the view to teachers
 * @param integer $viewid
 * @return nothing
 */
function share_view_to_teachers($viewid) {
    global $DB, $USER;
    if ($viewid > 0) {
        $allteachers = block_exaport_get_course_teachers();
        $allsharedusers = block_exaport_get_shared_users($viewid);
        $diff = array_diff($allteachers, $allsharedusers);
        $view = $DB->get_record_sql('SELECT * FROM {block_exaportview} WHERE id = ?', array('id'=>$viewid));
        if (!$view->shareall) {
            $view->shareall = 0;
        };
        if (!$view->externaccess) {
            $view->externaccess = 0;
        };
        if (!$view->externcomment) {
            $view->externcomment = 0;
        };
        $DB->update_record('block_exaportview', $view);        
        // Add all teachers to shared users (if it is not there yet).
        if ((count($allteachers) > 0) && (count($diff) > 0)) {
            foreach ($diff as $userid) {
                // If course has a teacher.
                if ($userid > 0) {
                    $shareItem = new stdClass();
                    $shareItem->viewid = $view->id;
                    $shareItem->userid = $userid;
                    $DB->insert_record("block_exaportviewshar", $shareItem);
                };
            };
        };
    };
}

function block_exaport_get_view_blocks($view) {
	global $DB, $USER;
	
	$portfolioItems = block_exaport_get_portfolio_items();
	$badges = block_exaport_get_all_user_badges();
	
	$query = "select b.*".
		 " from {block_exaportviewblock} b".
		 " where b.viewid = ? ORDER BY b.positionx, b.positiony";

	$allBlocks = $DB->get_records_sql($query, array($view->id));	
	$blocks = array();
	
	foreach ($allBlocks as $block) {
		if ($block->type == 'item') {
			if (!isset($portfolioItems[$block->itemid])) {
				// item not found
				continue;
			}
			$block->item = $portfolioItems[$block->itemid];
		} elseif ($block->type == 'badge') {
			// find bage by id
			$badge = null;
			foreach ($badges as $tmp) {
				if ($tmp->id == $block->itemid) {
					$badge = $tmp;
					break;
				}
			}
			if (!$badge) {
				// badge not found
				continue;
			}
			
			$context = context_course::instance($badge->courseid);
			$badge->imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);

			$block->badge = $badge;
		} else {
			$block->print_text = file_rewrite_pluginfile_urls($block->text, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $view->draft_itemid);
			$block->itemid = null;
		}
		
		// clean html texts for output
		if (isset($block->print_text) && $block->print_text) {
			$block->print_text = clean_text($block->print_text, FORMAT_HTML);
		}
		if (isset($block->intro) && $block->intro) {
			$block->intro = clean_text($block->intro, FORMAT_HTML);
		}
		
		$blocks[$block->id] = $block;
	}

	return $blocks;
}

function block_exaport_get_portfolio_items($epopwhere = 0) {
	global $DB, $USER;
    if ($epopwhere == 1) {
        $addwhere = " AND ".block_exaport_get_item_where();
    } else {
        $addwhere = "";
    };
	$query = "select i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments".
		 " from {block_exaportitem} i".
		 " left join {block_exaportcate} ic on i.categoryid = ic.id".
		 " left join {block_exaportcate} ic2 on ic.pid = ic2.id".
		 " left join {block_exaportitemcomm} com on com.itemid = i.id".
		 " where i.userid=? ".$addwhere.
		 " GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid".
		 " ORDER BY i.name";
		 //echo $query."<br><br>";
	$portfolioItems = $DB->get_records_sql($query, array($USER->id));
	if (!$portfolioItems) {
		$portfolioItems = array();
	}

	foreach ($portfolioItems as &$item) {
		if (null == $item->cname) {
			$item->category = format_string(block_exaport_get_root_category()->name);
			$item->catid = 0;
		} elseif (null == $item->cname_parent) {
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
				
				}while ($cat->pid != 0);
		}
		
		if ($item->intro) {
			$item->intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);
			$item->intro = clean_text($item->intro, FORMAT_HTML);
		}
		
		//get competences of the item
		$item->userid = $USER->id;
		
		$comp = block_exaport_check_competence_interaction();
		if($comp){
			$array = block_exaport_get_competences($item, 0);
		
			if(count($array)>0){
				$competences = "";
				foreach($array as $element){
					$conditions = array("id" => $element->compid);
					$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING); 

					if($competencesdb != null){
						$competences .= $competencesdb->title.'<br>';
					}
				}
				$competences = str_replace("\r", "", $competences);
				$competences = str_replace("\n", "", $competences);
				$competences = str_replace("\"", "&quot;", $competences);
				$competences = str_replace("'", "&prime;", $competences);		
					
				$item->competences = $competences;
			}
		}
		
		unset($item->userid);
		
		unset($item->cname);
		unset($item->cname_parent);
	}
	
	return $portfolioItems;
}

/**
 * Function gets teachers array of course
 * @return array
 */
function block_exaport_get_course_teachers() {
    global $DB, $USER;
    $courseid = optional_param('courseid', 0, PARAM_INT);
    // Role id='3' - teachers. '4'- assistents.
    $query = "SELECT u.id as userid, c.id, c.shortname, u.username
        FROM {course} c
        LEFT OUTER JOIN {context} cx ON c.id = cx.instanceid
        LEFT OUTER JOIN {role_assignments} ra ON cx.id = ra.contextid AND ra.roleid = '3'
        LEFT OUTER JOIN {user} u ON ra.userid = u.id
        WHERE cx.contextlevel = '50' AND u.id>0 AND c.id = ".$courseid;
    $courseteachers = $DB->get_records_sql($query); 
    $teacherarray = array();
    foreach($courseteachers as $teacher) {
        if ($teacher->userid <> $USER->id) { // Except himself.
            $teacherarray[] = $teacher->userid;
        };
    };
    sort($teacherarray);
    return $teacherarray;
}

/**
 * Function gets all shared users
 * @param $viewid
 * @return array
 */
function block_exaport_get_shared_users($viewid) {
    global $DB, $USER;
    $sharedusers = array ();
    if ($viewid > 0) {
        $query = "SELECT userid FROM {block_exaportviewshar} s WHERE s.viewid=".$viewid;
        $users = $DB->get_records_sql($query); 
        foreach($users as $user) {
            $sharedusers[] = $user->userid;
        };
    };
    sort($sharedusers);
    return $sharedusers;
};

if ($view) {
	$postView->blocks = json_encode(block_exaport_get_view_blocks($view));
}

require_once $CFG->libdir.'/editor/tinymce/lib.php';
$tinymce = new tinymce_texteditor();

$PAGE->requires->css('/blocks/exaport/css/blocks.css');

block_exaport_print_header('views', $type);

$editform->set_data($postView);
if ($type<>'title') {// for delete php notes 
	$form = $editform->toArray();
	echo $form['javascript'];
	echo '<form'.$form['attributes'].'><div id="exaport-view-mod">';
	echo $form['html_hidden_fields'];
};
	

// Translations
$translations = array(
	'name', 'role', 'nousersfound',
	'view_specialitem_headline', 'view_specialitem_headline_defaulttext', 'view_specialitem_text', 'view_specialitem_media', 'view_specialitem_badge', 'view_specialitem_text_defaulttext',
	'viewitem', 'comments', 'category','link', 'type','personalinformation',
	'delete', 'viewand',
	'file', 'note', 'link',
	'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs', 'notify',
	'checkall',
);


$translations = array_flip($translations);
foreach ($translations as $key => &$value) {
	$value = block_exaport_get_string($key);
}
unset($value);

?>
<script type="text/javascript">
//<![CDATA[
	var portfolioItems = <?php echo json_encode(block_exaport_get_portfolio_items()); ?>;
	ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
//]]>
</script>
<?php

$rev = theme_get_revision();
echo "<!--[if IE]> <style> #link_thumbnail{ zoom: 0.2; } </style> <![endif]--> ";
switch ($type) {
	case 'content' :
		?>
		<script type="text/javascript">
		//<![CDATA[
			jQueryExaport(exaportViewEdit.initContentEdit);
			M.yui.add_module({"editor_tinymce":{"name":"editor_tinymce","fullpath":"<?php echo $CFG->wwwroot;?>/lib/javascript.php/<?php echo $rev;?>/lib/editor/tinymce/module.js","requires":[]}});
		//]]>
		</script>
		<script type="text/javascript" src="<?php echo $CFG->wwwroot;?>/lib/editor/tinymce/tiny_mce/<?php echo $tinymce->version;?>/tiny_mce.js"></script>			
		<?php
		// view data form
echo '<div id="blocktype-list">'.get_string('createpage', 'block_exaport');
// Preview button.
echo '<div style="float: right;">
            <a target="_blank" href="'.s($CFG->wwwroot.'/blocks/exaport/shared_view.php?courseid='.$courseid.'&access=id/'.$USER->id.'-'.$view->id).'">
                    <img alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/preview.png" />
            </a></div>';
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
    <li class="portfolioElement" title="'.get_string('media', 'block_exaport').'" block-type="media">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('media', 'block_exaport').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('selectitems','block_exaport').'</div>
        </div>
    </li>';

if (block_exaport_badges_enabled()) {
    echo '<li class="portfolioElement" title="'.get_string('mybadges', 'badges').'" block-type="badge">
        <div class="blocktype" style="position: relative;">
            <img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/badges.png" />
            <h4 class="blocktype-title js-hidden">'.get_string('mybadges', 'badges').'</h4>
            <div class="blocktype-description js-hidden">'.get_string('selectitems','block_exaport').'</div>
        </div>
    </li>';
}
	
echo '</ul>';
echo '</div>';

$cols_layout = array (
	"1" => 1, 	"2" => 2,	"3" => 2,	"4" => 2,	"5" => 3,	"6" => 3,	"7" => 3,	"8" => 4,	"9" => 4,	"10" => 5
);

// default layout
if (!isset($view->layout) || $view->layout==0) 
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
			$data->description="";
			if (isset($view) and $view->id>0) {
				$data->description = $view->description;
				$data->descriptionformat = FORMAT_HTML;
			};
			if ($data->description) {
				$draftid_editor = file_get_submitted_draft_itemid('description');
				$currenttext = file_prepare_draft_area($draftid_editor, context_user::instance($USER->id)->id, "block_exaport", "view", $view->id, array('subdirs'=>true), $data->description);	
				$data->description = file_rewrite_pluginfile_urls($data->description, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $draftid_editor, array('subdirs'=>true));								
				$data->description_editor = array('text'=>$data->description, 'format'=>$data->descriptionformat, 'itemid'=>$draftid_editor);
			};
			$data->cataction = 'save';
			$data->edit = 1;
//			if (isset($view))
				//$data = file_prepare_standard_editor($data, 'description', $textfieldoptions, $context, 'block_exaport', 'veiw', $view->id);
			$editform ->set_data($data);
		
			$editform->display();				
			echo '</fieldset>';
			echo '</div>';
		break;
// --------------------		
	case 'layout' :
		if (!isset($view->layout) || $view->layout==0) 
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
			
				if (has_capability('block/exaport:shareextern', context_system::instance())) {

					echo '<tr><td style="padding-right: 10px; width: 10px">';
					echo $form['elements_by_name']['externaccess']['html'];
					echo '</td><td>'.get_string("externalaccess", "block_exaport").'</td></tr>';
					
					if ($view) {
						$url = block_exaport_get_external_view_url($view);
						// only when editing a view, the external link will work!
						echo '<tr id="externaccess-settings"><td></td><td>';
							echo '<div style="padding: 4px;"><a href="'.$url.'">'.$url.'</a></div>';
							if (block_exaport_external_comments_enabled()) {
								echo '<div style="padding: 4px 0;"><table>';
								echo '<tr><td style="padding-right: 10px; width: 10px">';
								echo '<input type="checkbox" name="externcomment" value="1"'.($postView->externcomment?' checked="checked"':'').' />';
								echo '</td><td>'.get_string("externcomment", "block_exaport").'</td></tr>';
								echo '</table></div>';
							}
							/*
							echo '<table>';
							echo '<tr><td>'.$form['elements_by_name']['externcomment']['html'];
							echo '</td><td>'.get_string("externalaccess", "block_exaport").'</td></tr>';
							echo '</table>';
							*/
						echo '</td></tr>';
					}
				
					echo '<tr><td style="height: 10px"></td></tr>';
				}
		
				if (has_capability('block/exaport:shareintern', context_system::instance())) {
					echo '<tr><td style="padding-right: 10px">';
					echo $form['elements_by_name']['internaccess']['html'];
					echo '</td><td>'.get_string("internalaccess", "block_exaport").'</td></tr>';
					echo '<tr id="internaccess-settings"><td></td><td>';
						echo '<div style="padding: 4px 0;"><table>';
							if (block_exaport_shareall_enabled()) {
								echo '<tr><td style="padding-right: 10px; width: 10px">';
								echo '<input type="radio" name="shareall" value="1"'.($postView->shareall?' checked="checked"':'').' />';
								echo '</td><td>'.get_string("internalaccessall", "block_exaport").'</td></tr>';
							}
							echo '<tr><td style="padding-right: 10px">';
							echo '<input type="radio" name="shareall" value="0"'.(!$postView->shareall?' checked="checked"':'').'/>';
							echo '</td><td>'.get_string("internalaccessusers", "block_exaport").'</td></tr>';
							echo '<tr id="internaccess-users"><td></td><td>';
							if (block_exaport_shareall_enabled()) {
								// show user search form
								echo get_string("share_to_other_users", "block_exaport").':';
								echo '<div style="padding-bottom: 20px;">';
								echo '<input name="share_to_other_users_q" type="text" /> ';
								echo '<input name="share_to_other_users_submit" type="submit" value="'.get_string('search').'" />';
								echo '</div>';
							}
							echo '<div id="sharing-userlist">userlist</div>';
							echo '</td></tr>';
						echo '</table></div>';
					echo '</td></tr>';
				}
		
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

echo '<div id="exaport-block_form" class="block">
        <div class="block-controls">                
            <a class="delete" title="'.get_string('closewindow').'" onclick="exaportViewEdit.cancelAddEdit();" href="#"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/remove-block.png" alt="" /></a>
        </div>
        <div class="block-header">
            <h4 id="block_form_title">'.get_string('cofigureblock','block_exaport').'</h4>
        </div>
        <div class="block-content">
			<div id="exaport-container"></div>
		</div>
	</div>
	<script type="text/javascript"> // for valid html and move block to body parent
		jQueryExaport("#exaport-block_form").appendTo("#page-blocks-exabis_competences-views_mod");
	</script>
	';
	echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();	
