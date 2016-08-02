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

require_once $CFG->libdir.'/formslib.php';

global $attachedFileNames, $attachedFileDatas, $attachedFileMimeTypes;
$attachedFileNames = array();
$attachedFileDatas = array();
$attachedFileMimeTypes  = array();

class block_exaport_resume_editor_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB, $COURSE;
		$mform	=& $this->_form;
		
		$param = $this->_customdata['field'];		
		$withfiles = $this->_customdata['withfiles'];
		if (!$withfiles)
			$withfiles = false;
		
		$mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');		
		
		$mform->addElement('editor', $param.'_editor', get_string('resume_'.$param, 'block_exaport'), null,
							array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));

		if ($withfiles) {
			$mform->addElement('filemanager', 'attachments', get_string('resume_files', 'block_exaport'), null, array('subdirs' => false, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'maxfiles' => 5));
		}
				
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'action');
		$mform->setType('action', PARAM_TEXT);
		
		$mform->addElement('hidden', 'type');
		$mform->setType('type', PARAM_TEXT);
		
		//		$mform->addElement('hidden', 'resume_id');
//		$mform->setType('resume_id', PARAM_INT);

		$this->add_action_buttons();
	}

}

class block_exaport_resume_multifields_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB;
		$mform  =& $this->_form;
			
		$attributes_text = array('size' => '50');
		$attributes_textarea = array('cols' => '47');
		
		$inputs = $this->_customdata['inputs'];

		// Form's header.
		$mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');		
		
		if (isset($inputs) && is_array($inputs) && count($inputs) > 0) {
			foreach ($inputs as $fieldname => $fieldtype) { 
				list ($type, $required) = explode(':', $fieldtype.":");
				switch ($type) {
					case 'text' : 
							$mform->addElement('text', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'), $attributes_text);
							$mform->setType($fieldname, PARAM_RAW);
							break;
					case 'textarea' : 
							$mform->addElement('textarea', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'), $attributes_textarea);
							$mform->setType($fieldname, PARAM_RAW);
							break;
					case 'filearea' : 
							$mform->addElement('filemanager', 'attachments', get_string('resume_'.$fieldname, 'block_exaport'), null, array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
							//$mform->addRule('attachments', null, 'required', null, 'client');
							//$mform->addElement('filemanager', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'));
							//$mform->setType($fieldname, PARAM_RAW);
							break;
				};
				// Required field.
				if ($required == 'required')
						$mform->addRule($fieldname, null, 'required');
			}			
		};		

		$mform->addElement('hidden', 'id');
		$mform->setType('id', PARAM_INT);
		
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'resume_id');
		$mform->setType('resume_id', PARAM_INT);
		
		$mform->addElement('hidden', 'action');
		$mform->setType('action', PARAM_TEXT);
		
		$mform->addElement('hidden', 'type');
		$mform->setType('type', PARAM_TEXT);

		$this->add_action_buttons();
	}

}

class block_exaport_resume_checkboxlist_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB;
		$mform  =& $this->_form;
		$records = $this->_customdata['records'];
		// Form's header.
		$mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');		
		
		if (isset($records) && is_array($records) && count($records) > 0) {
			foreach ($records as $record) { 
				$mform->addElement('checkbox', 'check['.$record['id'].']', $record['title'],  $record['description']);
			}			
		};		
			
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'resume_id');
		$mform->setType('resume_id', PARAM_INT);
		
		$mform->addElement('hidden', 'action');
		$mform->setType('action', PARAM_TEXT);
		
		$mform->addElement('hidden', 'type');
		$mform->setType('type', PARAM_TEXT);
		
		$this->add_action_buttons();
	}

}

function block_exaport_resume_checkboxeslist_form($resume, $edit, $data) {
	global $DB, $CFG, $USER, $OUTPUT;
	
	$show_information = false;
	
	$records = array();	
	switch ($edit) {
		case 'badges':
			$badges = block_exaport_get_all_user_badges();
			foreach ($badges as $badge) {
				//print_r($badge);
				$badge_image = block_exaport_get_user_badge_image($badge);
				$records[$badge->id]['id'] = $badge->id;			
				$records[$badge->id]['title'] = $badge_image.$badge->name;			
				$dateformat = get_string('strftimedate', 'langconfig');
				$records[$badge->id]['description'] = userdate($badge->dateissued, $dateformat).': '.$badge->description;			
			};
			$default_values = $DB->get_records('block_exaportresume_'.$edit, array('resumeid' => $resume->id), null, 'badgeid');
			break;
	}
	
	$formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$edit, "block_exaport");
	$workform = new block_exaport_resume_checkboxlist_form($_SERVER['REQUEST_URI'].'#'.$edit, array('formheader' => $formheader, 'records'=>$records));
	$data->check = $default_values;
	$data->resume_id = $resume->id;
	$workform->set_data($data);
	if ($workform->is_cancelled()) {
		$show_information = true;
	} else if ($fromform = $workform->get_data()) {
		$DB->delete_records('block_exaportresume_'.$edit, array('resumeid' => $resume->id)); 
		// Save records
		$sorting = 0; 
		if (isset($fromform->check)) {
			$new_records = array_keys($fromform->check);
		} else {
			$new_records = array();
		}
		foreach	($new_records as $id) {
			switch ($edit) {
				case 'badges':
					$dataobject = new stdClass();
					$dataobject->resumeid = $resume->id;
					$dataobject->badgeid = $id;
					$dataobject->sorting = $sorting + 10;
					$DB->insert_record('block_exaportresume_'.$edit, $dataobject);
					$sorting = $sorting + 10;
				break;
			};
		};
		$show_information = true;		
	} else {
		echo block_exaport_resume_header();
		$workform->display();
	};
	return $show_information;
}

function block_exaport_resume_prepare_block_mm_data($resume, $id, $type_block, $display_inputs, $data) {
	global $DB, $CFG, $USER, $OUTPUT;

	$show_information = false;
	$formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$type_block, "block_exaport");
	$workform = new block_exaport_resume_multifields_form($_SERVER['REQUEST_URI'].'#'.$type_block, array('formheader' => $formheader, 'inputs'=>$display_inputs));
	$data->resume_id = $resume->id;
	$workform->set_data($data);
	
	if ($workform->is_cancelled()) {
		$show_information = true;
	} else if ($fromform = $workform->get_data()) { 
		// Save record.
		$fromform->user_id = $USER->id;
		$item_id = block_exaport_set_resume_mm($type_block, $fromform);
		// save uploaded file in 'resume_education' filearea
		$context = context_user::instance($USER->id);
		// Checking userquota.
		$upload_filesizes = block_exaport_get_filesize_by_draftid($fromform->attachments);
		if (block_exaport_file_userquotecheck($upload_filesizes) && block_exaport_get_maxfilesize_by_draftid_check($fromform->attachments)) {
			file_save_draft_area_files($fromform->attachments, $context->id, 'block_exaport', 'resume_'.$type_block, $item_id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
		};
		echo "<div class='block_eportfolio_center'>".$OUTPUT->box(get_string('resume_'.$type_block."saved", "block_exaport"), 'center')."</div>";
		$show_information = true;
	} else {
		if ($id > 0) { // Edit existing record.		
			// files
			$draftitemid = file_get_submitted_draft_itemid('attachments');
			$context = context_user::instance($USER->id);
			file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'resume_'.$type_block, $id,
									array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));				 					
			// all data to form.
			$data = $DB->get_record("block_exaportresume_".$type_block, array('id' => $id, 'resume_id' => $resume->id));
			$data->attachments = $draftitemid;   
			$workform->set_data($data);
		} 			
		echo block_exaport_resume_header();		
		$workform->display();
	};	
	
	return $show_information;
}

function block_exaport_get_resume_params_record($userid = null) {
	global $DB;

	if (is_null($userid)) {
		global $USER;
		$userid = $USER->id;
	}
	$conditions = array("user_id" => $userid);
	return $DB->get_record('block_exaportresume', $conditions);
}

function block_exaport_get_resume_params($userid = null) {
	global $DB;
	if ($userid === null) {
		global $USER;
		$userid = $USER->id;
	}
	
	$resumeparams = block_exaport_get_resume_params_record($userid);
	return $resumeparams;
}

function block_exaport_set_resume_params($userid, $params = null) {
	global $DB;

	if (is_null($params) && (is_array($userid) || is_object($userid))) {
		global $USER;
		$params = $userid;
		$userid = $USER->id;
	}

	$newresumeparams = new stdClass();

	if (is_object($params)) {
		$newresumeparams = $params;
	} elseif (is_array($params)) {
		$newresumeparams = (object) $params;
	} 

	if ($oldresumeparams = block_exaport_get_resume_params_record($userid)) {
		$newresumeparams->id = $oldresumeparams->id;
		$DB->update_record('block_exaportresume', $newresumeparams);
	} else {
		$newresumeparams->user_id = $userid;
		$DB->insert_record("block_exaportresume", $newresumeparams);
	}
}

function block_exaport_set_resume_mm($table, $fromform) {
	global $DB;	
	if ($fromform->id < 1) {
		$fromform->sorting = block_exaport_get_max_sorting($table, $fromform->resume_id) + 10; // Step of sorting
		$id = $DB->insert_record('block_exaportresume_'.$table, $fromform);
	} else if ($fromform->id > 0) {
		$DB->update_record('block_exaportresume_'.$table, $fromform);
		$id = $fromform->id;
	}
	return $id;
}

function block_exaport_resume_get_mm_records($table, $conditions) {
	global $DB;	
	//$records = $DB->get_records('block_exaportresume_'.$table, $conditions);	
	foreach ($conditions as $field => $value) {
		$where_arr[] = $field.' = ? ';
		$params[] = $value;
	}
	$where = implode(' AND ', $where_arr);
	$records = $DB->get_records_sql('SELECT * FROM {block_exaportresume_'.$table.'} WHERE '.$where.' ORDER BY sorting', $params);
	return $records;
}

function block_exaport_resume_templating_mm_records($courseid, $type, $headertitle, $records, $filescolumn=1, $updowncolumn=1, $editcolumn=1) {
	global $CFG, $DB, $OUTPUT, $USER;
	if (count($records) < 1) {
		return '';
	};
	$table = new html_table();
	$table->width = "100%";
	$table->head = array();
	$table->size = array();
	$table->head['title'] = get_string('resume_'.$headertitle, 'block_exaport');
	if ($filescolumn) {
		$table->head['files'] = get_string('resume_files', 'block_exaport');
	};
	if ($updowncolumn) {
		$table->head['down'] = '';
		$table->head['up'] = '';
	};
	if ($editcolumn) {
		$table->head['icons'] = ''; 
	};
	
	if ($filescolumn) {
		$table->size['files'] = '40px';
	};
	if ($updowncolumn) {
		$table->size['down'] = '16px'; 
		$table->size['up'] = '16px'; 	
	};
	if ($editcolumn) {
		$table->size['icons'] = '40px'; 
	};
		
	$table->data = array();
	$item_i = -1;
	$id_prev = 0;
	$id_next = 0;
	$keys = array_keys($records);
//		print_r($records);
	
	foreach ($records as $key => $record) {
		$item_i++;
		// Title/description block
		switch ($type) {
			case 'edu': 
					$position = $record->qualname;
					if ($position) {
						$position .= ' ('.$record->qualtype.')';
					} else {
						$position .= $record->qualtype;
					};
					if ($position) {
						$position .= ' at ';
					}
					$table->data[$item_i]['title'] = '<strong>';
					if ($record->qualdescription) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $position.$record->institution.'</strong>';
					if ($record->qualdescription) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$table->data[$item_i]['title'] .= '<div>'.$record->startdate.(isset($record->enddate) && $record->enddate<>'' ? ' - '.$record->enddate : '').'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$record->qualdescription.'</div>';
				break;
			case 'employ': 
					$table->data[$item_i]['title'] = '<strong>';
					if ($record->positiondescription) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $record->jobtitle.': '.$record->employer.'</strong>';
					if ($record->positiondescription) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$table->data[$item_i]['title'] .= '<div>'.$record->startdate.(isset($record->enddate) && $record->enddate<>'' ? ' - '.$record->enddate : '').'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$record->positiondescription.'</div>';
				break;
			case 'certif': 
					$table->data[$item_i]['title'] = '<strong>';
					if ($record->description) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $record->title.'</strong>';
					if ($record->description) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$table->data[$item_i]['title'] .= '<div>'.$record->date.'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$record->description.'</div>';
				break;
			case 'public': 
					$table->data[$item_i]['title'] = '<strong>';
					if ($record->contributiondetails) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $record->title.' ('.$record->contribution.')</strong>';
					if ($record->contributiondetails) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$table->data[$item_i]['title'] .= '<div>'.$record->date.'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$record->contributiondetails;
					if ($record->url) {
						$table->data[$item_i]['title'] .= '<br><a href="'.$record->url.'">'.$record->url.'</a>';
					};					
					$table->data[$item_i]['title'] .= '</div>';
				break;
			case 'mbrship': 
					$table->data[$item_i]['title'] = '<strong>';
					if ($record->description) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $record->title.'</strong>';
					if ($record->description) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$table->data[$item_i]['title'] .= '<div>'.$record->startdate.(isset($record->enddate) && $record->enddate<>'' ? ' - '.$record->enddate : '').'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$record->description.'</div>';
				break;
			case 'badges': 
					$badge = $DB->get_record_sql('SELECT b.*, bi.dateissued, bi.uniquehash  FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid='.$USER->id.' WHERE b.id=? ',
									array('id'=>$record->badgeid));
					$table->data[$item_i]['title'] = '<strong>';
					if ($badge->description) {
						$table->data[$item_i]['title'] .= '<a href="#" class="expandable-head">';
					};
					$table->data[$item_i]['title'] .= $badge->name.'</strong>';
					if ($badge->description) {
						$table->data[$item_i]['title'] .= '</a>';
					};
					$dateformat = get_string('strftimedate', 'langconfig');
					$badge_image = block_exaport_get_user_badge_image($badge);
					$table->data[$item_i]['title'] .= '<div>'.userdate($badge->dateissued, $dateformat).'</div>';
					$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$badge->description.$badge_image.'</div>';
				break;
			default: break;
		}
		// Count of files
		if ($filescolumn) {
			$fs = get_file_storage();
			$context = context_user::instance($USER->id);
			$files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type, $record->id, 'filename', false);
			$count_files = count($files);
			if ($count_files > 0) {
				$table->data[$item_i]['files'] = '<a href="#" class="expandable-head">'.$count_files.'</a>';
				$table->data[$item_i]['files'] .= '<div class="expandable-text hidden">'.block_exaport_resume_list_files($type, $files).'</div>';
			} else {
				$table->data[$item_i]['files'] = '0'; 
			};
		};
		// Links to up/down
		if ($updowncolumn) {
			if ($item_i < count($records)-1) {
				$id_next = $keys[$item_i+1];
			};
			$linkto_up = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=sortchange&type='.$type.'&id1='.$record->id.'&id2='.$id_next.'&sesskey='.sesskey().'"><img src="pix/down_16.png" alt="'.get_string("down").'" /></a>';
			$linkto_down = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=sortchange&type='.$type.'&id1='.$record->id.'&id2='.$id_prev.'&sesskey='.sesskey().'"><img src="pix/up_16.png" alt="'.get_string("up").'" /></a>';		
			$table->data[$item_i]['up'] = '&nbsp';
			$table->data[$item_i]['down'] = '&nbsp';
			if ($item_i < count($records)-1) {
				$table->data[$item_i]['up'] = $linkto_up;
			};
			if ($item_i > 0) {
				$table->data[$item_i]['down'] = $linkto_down;
			};
			$id_prev = $record->id;
		};
		// Links to edit / delete
		if ($editcolumn) {
			$table->data[$item_i]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=edit&type='.$type.'&id='.$record->id.'&sesskey='.sesskey().'"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
							' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=delete&type='.$type.'&id='.$record->id.'"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>'; 
		};
	};
	return html_writer::table($table);
}

// goals and skills
function block_exaport_resume_templating_list_goals_skills($courseid, $resume, $type, $tabletitle) {
	global $CFG, $DB, $OUTPUT, $USER;
	$elements = array ('personal', 'academic', 'careers');
	$table = new html_table();
	$table->width = "100%";
	$table->head = array();
	$table->size = array();
	$table->head['title'] = get_string('resume_'.$type, 'block_exaport');
	$table->head['files'] = get_string('resume_files', 'block_exaport');
	$table->head['icons'] = ''; 
	$table->size['files'] = '40px';
	$table->size['icons'] = '40px';
	
	$item_i = 0;
	// Competencies
	if (block_exaport_check_competence_interaction()) {
		$dbman = $DB->get_manager();
		if (!$dbman->table_exists('block_exacompdescriptors')){
			$table->data[$item_i]['title'] = get_string('resume_'.$type.'comp', 'block_exaport').' / <span style="color:red;">Error: Please install latest version of Exabis Competence Grid</span>';
			$table->data[$item_i]['files'] = '';
			$table->data[$item_i]['icons'] = '';
		} else {
			$comptitles = '';
			$competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => $type));
			foreach ($competences as $competence) {
				$competencesdb = $DB->get_record('block_exacompdescriptors', array('id' => $competence->compid), $fields='*', $strictness=IGNORE_MISSING); 
				if($competencesdb != null){
					$comptitles .= $competencesdb->title.'<br>';
				};
			};		
			if ($comptitles <> '') {
				$table->data[$item_i]['title'] = '<a name="'.$type.'comp"></a><a href="#" class="expandable-head">'.get_string('resume_'.$type.'comp', 'block_exaport').'</a>';
			} else {
				$table->data[$item_i]['title'] = '<a name="'.$type.'comp"></a>'.get_string('resume_'.$type.'comp', 'block_exaport');
			}
			$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$comptitles.'</div>';
			$table->data[$item_i]['files'] = '';
			// Links to edit / delete
			if (file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php')) {
				$table->data[$item_i]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=edit&type='.$type.'comp&id='.$resume->id.'&sesskey='.sesskey().'"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
			} else {
				$table->data[$item_i]['icons'] = '';
			}
		};

	};
	
	foreach ($elements as $element) {
		$item_i++;
		// Title and Description
		$description = '';
		$description = $resume->{$type.$element};
		$description = file_rewrite_pluginfile_urls($description, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_'.$type.$element, $resume->id);
		$description = trim($description);
		if (preg_replace('/\<br(\s*)?\/?\>/i', "", $description)=='') // if text is only <br> (html-editor can return this)
			$description = '';
		$table->data[$item_i]['title'] = '';
		$fs = get_file_storage();
		$context = context_user::instance($USER->id);
		$files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type.$element, $resume->id, 'filename', false);
		// Count of files
		$count_files = count($files);
		if ($count_files > 0) {
			$table->data[$item_i]['files'] = '<a href="#" class="expandable-head">'.$count_files.'</a>';
			$table->data[$item_i]['files'] .= '<div class="expandable-text hidden">'.block_exaport_resume_list_files($type.$element, $files).'</div>';
		} else {
			$table->data[$item_i]['files'] = '0'; 
		};
		if ($description <> '') {
			$table->data[$item_i]['title'] = '<a name="'.$type.$element.'"></a><a href="#" class="expandable-head">'.get_string('resume_'.$type.$element, 'block_exaport').'</a>';
			$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$description.'</div>';
		} else {
			$table->data[$item_i]['title'] = '<a name="'.$type.$element.'"></a>'.get_string('resume_'.$type.$element, 'block_exaport');
		};
		// Links to edit / delete
		$table->data[$item_i]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=edit&type='.$type.$element.'&id='.$resume->id.'&sesskey='.sesskey().'"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
	};

	$table_content = html_writer::table($table);
	return $table_content;
}

function block_exaport_resume_list_files($filearea, $files) {
	global $CFG;
	//print_r($files);
	$listfiles = '<ul class="resume_listfiles">';
	foreach ($files as $file) {
		$filename = $file->get_filename();
//		$url = moodle_url::make_pluginfile_url($file->get_contextid(), 'block_exaport', 'resume_'.$filearea, $file->get_itemid(), $file->get_filepath(), $filename, true);
//		$url = moodle_url::make_file_url('/pluginfile.php', array($file->get_contextid(), 'block_exaport', 'resume_'.$filearea,
//			$file->get_itemid(), $file->get_filepath(), $filename));
		$url = $CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/block_exaport/resume_'.$filearea.'/'.$file->get_itemid().'/'.$filename;
		$listfiles .= '<li>'.html_writer::link($url, $filename).'</li>';
	};
	$listfiles .= '<ul>';
	
	return $listfiles;
}

function block_exaport_resume_mm_delete($table, $conditions) {
	global $DB, $USER;
	$DB->delete_records('block_exaportresume_'.$table, $conditions); 
	$fs = get_file_storage();
	$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_'.$table, $conditions['id']);
	foreach ($files as $file) {
		$file->delete();
	};
}

function block_exaport_get_max_sorting($table, $resume_id) {
	global $DB;

	return $DB->get_field_sql('SELECT MAX(sorting) FROM {block_exaportresume_'.$table.'} WHERE resume_id=?', array($resume_id)); 
}

function block_exaport_resume_competences_form($resume, $id, $type_block) {
	global $DB;

	$type = substr($type_block, 0, -4); // skillscomp -> skills / goalscomp -> goals
	$save = optional_param('submitbutton', '', PARAM_RAW);
	$cancel = optional_param('cancel', '', PARAM_RAW);
	$resume->descriptors = array();
	if ($cancel) {
		return true;
	}

	if ($save) {
		$interaction = block_exaport_check_competence_interaction();
		if ($interaction) {
			$DB->delete_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => $type));
			$compids = optional_param_array('desc', array(), PARAM_INT);
			if (count($compids)>0) {
				foreach($compids as $compid) {
					$DB->insert_record('block_exaportcompresume_mm', array("resumeid" => $resume->id, "compid" => $compid, "comptype" => $type));
				}
			}
		}
		return true;
	}
	$content = block_exaport_resume_header();
	$resume->descriptors = array_keys($DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => $type), null, 'compid'));
	$content .= '<div class="block_eportfolio_center">'.get_string('edit', "block_exaport").': '.get_string('resume_'.$type_block, "block_exaport").'</div>';
	$content .= block_exaport_build_comp_tree($type_block, $resume);
	echo $content;
	return false;
}

function block_exaport_get_user_badge_image($badge) {
	global $USER;
	$src = '/pluginfile.php/'.context_user::instance($badge->usercreated)->id.'/badges/userbadge/'.$badge->id.'/'.$badge->uniquehash;
	$img = '<img src="'.$src.'" style="float: left; margin: 0px 10px;">';
	return $img;
}

// get XML for europass
function europassXML($resumeid = 0) {
	global $USER, $DB;
	global $attachedFileNames, $attachedFileDatas, $attachedFileMimeTypes;
	$xml = '';
	$resume = $DB->get_record('block_exaportresume', array("id" => $resumeid, 'user_id' => $USER->id));
	
	$dom = new DOMDocument('1.0', 'utf-8');
	$root = $dom->createElement('SkillsPassport');
	$root->setAttribute('xmlns','http://europass.cedefop.europa.eu/Europass');
	$root->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
	$root->setAttribute('xsi:schemaLocation','http://europass.cedefop.europa.eu/Europass http://europass.cedefop.europa.eu/xml/v3.2.0/EuropassSchema.xsd');
	$root->setAttribute('locale','en');
	// DocumentInfo
	$DocumentInfo = $dom->createElement('DocumentInfo');
		$DocumentType = $dom->createElement('DocumentType');
			$text = $dom->createTextNode('ECV');
			$DocumentType->appendChild($text);
			$DocumentInfo->appendChild($DocumentType);
		$Bundle = $dom->createElement('Bundle');
			$DocumentInfo->appendChild($Bundle);
		$CreationDate = $dom->createElement('CreationDate');
			$text = $dom->createTextNode(date("Y-m-d").'T'.date("H:i:s.000").'Z');
			$CreationDate->appendChild($text);
			$DocumentInfo->appendChild($CreationDate);
		$LastUpdateDate = $dom->createElement('LastUpdateDate');
			$text = $dom->createTextNode(date("Y-m-d").'T'.date("H:i:s.000").'Z');
			$LastUpdateDate->appendChild($text);
			$DocumentInfo->appendChild($LastUpdateDate);		
		$XSDVersion = $dom->createElement('XSDVersion');
			$text = $dom->createTextNode('V3.2');
			$XSDVersion->appendChild($text);
			$DocumentInfo->appendChild($XSDVersion);
		$Generator = $dom->createElement('Generator');
			$text = $dom->createTextNode('Moodle exaport resume');
			$Generator->appendChild($text);
			$DocumentInfo->appendChild($Generator);
		$Comment = $dom->createElement('Comment');
			$text = $dom->createTextNode('Europass CV from Moodle exaport');
			$Comment->appendChild($text);
			$DocumentInfo->appendChild($Comment);
	$root->appendChild($DocumentInfo);
		
	// LearnerInfo
	$LearnerInfo = $dom->createElement('LearnerInfo');
		$Identification = $dom->createElement('Identification');
			$PersonName = $dom->createElement('PersonName');
				$Title = $dom->createElement('Title');
					$text = $dom->createTextNode('');
					$Title->appendChild($text);
					$PersonName->appendChild($Title);
				$FirstName = $dom->createElement('FirstName');
					$text = $dom->createTextNode($USER->firstname);
					$FirstName->appendChild($text);
					$PersonName->appendChild($FirstName);
				$Surname = $dom->createElement('Surname');
					$text = $dom->createTextNode($USER->lastname);
					$Surname->appendChild($text);
					$PersonName->appendChild($Surname);
			$Identification->appendChild($PersonName);
			
			$ContactInfo = $dom->createElement('ContactInfo');
				// address info
				$Address = $dom->createElement('Address');
					$Contact = $dom->createElement('Contact');
						$AddressLine = $dom->createElement('AddressLine');
							$text = $dom->createTextNode($USER->address);
							$AddressLine->appendChild($text);
							$Contact->appendChild($AddressLine);
						$PostalCode = $dom->createElement('PostalCode');
							$text = $dom->createTextNode('');
							$PostalCode->appendChild($text);
							$Contact->appendChild($PostalCode);
						$Municipality = $dom->createElement('Municipality');
							$text = $dom->createTextNode($USER->city);
							$Municipality->appendChild($text);
							$Contact->appendChild($Municipality);
						$Country = $dom->createElement('Country');
							$Code = $dom->createElement('Code');
								$text = $dom->createTextNode($USER->country);
								$Code->appendChild($text);
								$Country->appendChild($Code);
							$Label = $dom->createElement('Label');
								$text = $dom->createTextNode('');
								$Label->appendChild($text);
								$Country->appendChild($Label);
							$Contact->appendChild($Country);
					$Address->appendChild($Contact);
				$ContactInfo->appendChild($Address);
				// email
				$Email = $dom->createElement('Email');
					$Contact = $dom->createElement('Contact');
						$text = $dom->createTextNode($USER->email);
						$Contact->appendChild($text);
						$Email->appendChild($Contact);
				$ContactInfo->appendChild($Email);
				// phones
				$TelephoneList = $dom->createElement('TelephoneList');
					$phones = array('1' => 'home', '2' => 'mobile');
					foreach ($phones as $index => $label) {
						$Telephone = $dom->createElement('Telephone');
							$Contact = $dom->createElement('Contact');
								$text = $dom->createTextNode($USER->{'phone'.$index});
								$Contact->appendChild($text);
								$Telephone->appendChild($Contact);
							$Use = $dom->createElement('Use');
								$Code = $dom->createElement('Code');
									$text = $dom->createTextNode($label);
									$Code->appendChild($text);
								$Use->appendChild($Code);
							$Telephone->appendChild($Use);
						$TelephoneList->appendChild($Telephone);
					};
				$ContactInfo->appendChild($TelephoneList);
				// www
				$WebsiteList = $dom->createElement('WebsiteList');
					$Website = $dom->createElement('Website');
						$Contact = $dom->createElement('Contact');
							$text = $dom->createTextNode($USER->url);
							$Contact->appendChild($text);
							$Website->appendChild($Contact);
						$Use = $dom->createElement('Use');
							$Code = $dom->createElement('Code');
								$text = $dom->createTextNode('personal');
								$Code->appendChild($text);
							$Use->appendChild($Code);
						$Website->appendChild($Use);
					$WebsiteList->appendChild($Website);
				$ContactInfo->appendChild($WebsiteList);
				// messengers
				$InstantMessagingList = $dom->createElement('InstantMessagingList');
					$messangers = array('skype', 'icq', 'aim', 'msn', 'yahoo');					
					foreach ($messangers as $messanger) {
						$InstantMessaging = $dom->createElement('InstantMessaging');
							$Contact = $dom->createElement('Contact');
								$text = $dom->createTextNode($USER->{$messanger});
								$Contact->appendChild($text);
								$InstantMessaging->appendChild($Contact);
							$Use = $dom->createElement('Use');
								$Code = $dom->createElement('Code');
									$text = $dom->createTextNode($messanger);
									$Code->appendChild($text);
								$Use->appendChild($Code);
							$InstantMessaging->appendChild($Use);
						$InstantMessagingList->appendChild($InstantMessaging);
					};
				$ContactInfo->appendChild($InstantMessagingList);
			$Identification->appendChild($ContactInfo);
			
			// PHOTO
			$fs = get_file_storage();
			$file = $fs->get_file(context_user::instance($USER->id)->id, 'user', 'icon', 0, '/', 'f3.png');			 
			if ($file) {
				$Photo = $dom->createElement('Photo');
					$MimeType = $dom->createElement('MimeType');
						$text = $dom->createTextNode($file->get_mimetype());
						$MimeType->appendChild($text);
					$Photo->appendChild($MimeType);
					$Data = $dom->createElement('Data');
						$userpicturefilecontent = base64_encode($file->get_content());					
						//$userpicturefilecontent .= print_r($file, true);
						$text = $dom->createTextNode($userpicturefilecontent);
						$Data->appendChild($text);
					$Photo->appendChild($Data);
				$Identification->appendChild($Photo);
			};
			
		$LearnerInfo->appendChild($Identification);	
		// Headline - insert of the cover of exaport resume
		$Headline = $dom->createElement('Headline');
			$Type = $dom->createElement('Type');
				$Code = $dom->createElement('Code');
					$text = $dom->createTextNode('personal_statement');
					$Code->appendChild($text);
				$Type->appendChild($Code);	
				$Label = $dom->createElement('Label');
					$text = $dom->createTextNode('PERSONAL STATEMENT');
					$Label->appendChild($text);
				$Type->appendChild($Label);	
			$Headline->appendChild($Type);	
			$Description = $dom->createElement('Description');
				$Label = $dom->createElement('Label');
					$text = $dom->createTextNode(cleanForExternalXML($resume->cover));
					$Label->appendChild($text);
				$Description->appendChild($Label);	
			$Headline->appendChild($Description);	
		$LearnerInfo->appendChild($Headline);	
		
		// WorkExperienceList / Employment history
		$resume->employments = $DB->get_records('block_exaportresume_employ', array("resume_id" => $resume->id), 'sorting');
		$WorkExperienceList = europassXMLEmployersEducations($dom, 'WorkExperience', $resume->employments);
		$LearnerInfo->appendChild($WorkExperienceList);	/**/
		
		// EducationList / Education history
		$resume->educations = $DB->get_records('block_exaportresume_edu', array("resume_id" => $resume->id), 'sorting');
		$WorkEducationsList = europassXMLEmployersEducations($dom, 'Education', $resume->educations);
		$LearnerInfo->appendChild($WorkEducationsList);	/**/
		
		// Skills. Carrer skills to Job-related skills.
		$Skills = $dom->createElement('Skills');
			$Other = $dom->createElement('Other');
				$Description = $dom->createElement('Description');
					$skillscontent = $resume->skillscareers.$resume->skillsacademic.$resume->skillspersonal;			
					$competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'skills'));
					foreach ($competences as $competence) {
						$competencesdb = $DB->get_record('block_exacompdescriptors', array('id' => $competence->compid), $fields='*', $strictness=IGNORE_MISSING); 
						if($competencesdb != null){
							$skillscontent .= $competencesdb->title.'<br>';
						};
					};					
					$text = $dom->createTextNode(cleanForExternalXML($skillscontent));
					$Description->appendChild($text);
					$Other->appendChild($Description);
				// Skill's files
				$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_skillscareers', $resume->id, 'filename', false);
				$files = $files + $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_skillspersonal', $resume->id, 'filename', false);
				$files = $files + $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_skillsacademic', $resume->id, 'filename', false);
				if (count($files) > 0) {
					$fileArray = europassXMLGetAttachedFileContents($files);
					$Documentation = europassXMLDocumentationList($dom, $fileArray);
					$Other->appendChild($Documentation);	
				}; 
				
			$Skills->appendChild($Other);	
		$LearnerInfo->appendChild($Skills);	
		
		// AchievementList.
		$AchievementList = $dom->createElement('AchievementList');
		// Sertifications, awards.
		list($sertificationsString, $elementIDs) = listForResumeElements($resume->id, 'block_exaportresume_certif');
		$Sertifications = europassXMLAchievement($dom, 'certif', $elementIDs, get_string('resume_certif', 'block_exaport'), cleanForExternalXML($sertificationsString));
		$AchievementList->appendChild($Sertifications);	
		
		// Books, publications.
		list($publicationsString, $elementIDs) = listForResumeElements($resume->id, 'block_exaportresume_public');
		$Publications = europassXMLAchievement($dom, 'public', $elementIDs, get_string('resume_public', 'block_exaport'), cleanForExternalXML($publicationsString));
		$AchievementList->appendChild($Publications);	
		
		// Memberships	.	
		list($mbrshipString, $elementIDs) = listForResumeElements($resume->id, 'block_exaportresume_mbrship');
		$Memberships = europassXMLAchievement($dom, 'membership', $elementIDs, get_string('resume_mbrship', 'block_exaport'), cleanForExternalXML($mbrshipString));
		$AchievementList->appendChild($Memberships);	
		
		// Goals.
		//$goalsString = listForResumeElements($resume->id, 'block_exaportresume_mbrship');
		$goalsString = $resume->goalspersonal.'<br>'.$resume->goalsacademic.'<br>'.$resume->goalscareers;
		$competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'goals'));
		foreach ($competences as $competence) {
			$competencesdb = $DB->get_record('block_exacompdescriptors', array('id' => $competence->compid), $fields='*', $strictness=IGNORE_MISSING); 
			if($competencesdb != null){
				$goalsString .= $competencesdb->title.'<br>';
			};
		};	
		$Goals = europassXMLAchievement($dom, 'goals', array($resume->id), get_string('resume_mygoals', 'block_exaport'), cleanForExternalXML($goalsString));
		$AchievementList->appendChild($Goals);	

		// Interests.	
		$Interests = europassXMLAchievement($dom, 'intersts', array($resume->id), get_string('resume_interests', 'block_exaport'), cleanForExternalXML($resume->interests));
		$AchievementList->appendChild($Interests);	
		
		$LearnerInfo->appendChild($AchievementList);

		// All attached files IDs
		$fileArray = array_keys($attachedFileNames);
		$Documentation = europassXMLDocumentationList($dom, $fileArray);
		if ($Documentation) {
			$LearnerInfo->appendChild($Documentation);	
		};

	$root->appendChild($LearnerInfo);
	
	// Attachment files
	if (count($attachedFileNames)>0) {
		$AttachmentList = $dom->createElement('AttachmentList');
		foreach ($attachedFileNames as $ID => $filename) {
			$Attachment = $dom->createElement('Attachment');
			$Attachment->setAttribute('id', $ID);
				// name
				$Name = $dom->createElement('Name');
					$text = $dom->createTextNode($filename);
					$Name->appendChild($text);
					$Attachment->appendChild($Name);
				// mimetype
				$MimeType = $dom->createElement('MimeType');
					$text = $dom->createTextNode($attachedFileMimeTypes[$ID]);
					$MimeType->appendChild($text);
					$Attachment->appendChild($MimeType);	
				// data
				$Data = $dom->createElement('Data');
					$text = $dom->createTextNode($attachedFileDatas[$ID]);
					$Data->appendChild($text);
					$Attachment->appendChild($Data);						
				// description
				$Description = $dom->createElement('Description');
					$text = $dom->createTextNode($filename);
					$Description->appendChild($text);
					$Attachment->appendChild($Description);
			$AttachmentList->appendChild($Attachment);
		};
		$root->appendChild($AttachmentList);
	}
	
	// Cover. Insert the Cover letter from the exaport to the main content of the europass cover
	$CoverLetter = $dom->createElement('CoverLetter');
		$Letter = $dom->createElement('Letter');
			$Body = $dom->createElement('Body');
				$MainBody = $dom->createElement('MainBody');
					$text = $dom->createTextNode(cleanForExternalXML($resume->cover));
					$MainBody->appendChild($text);	
				$Body->appendChild($MainBody);	
			$Letter->appendChild($Body);	
		$CoverLetter->appendChild($Letter);	
	$root->appendChild($CoverLetter);	
	
	$dom->appendChild($root);
	$dom->formatOutput = true;
	$xml .= $dom->saveXML();
	
	// save to file for development
 	// $filename = 'd:/incom/testXML.xml';
	// $strXML = $xml;
	// file_put_contents($filename, $strXML); 
	
	return $xml;
}

// Clean text for XML. Images, links, e.t.c
function cleanForExternalXML($text = '') {
	$result = $text;
	// img
	$result = preg_replace("/<img[^>]+\>/i", "", $result); 	
	return $result;
}

function getDateParamsFromString($datestring) {
	$date_arr = date_parse($datestring);
	if ($date_arr['year'])
		$year = $date_arr['year'];
	else if (preg_match('/(19|20|21)\d{2}/', $datestring, $maches)) 
		$year = $maches[0];
	else 
		$year = '';
	if ($date_arr['month'])
		$month = $date_arr['month'];
	else 
		$month = '';
	if ($date_arr['day'])
		$day = $date_arr['day'];
	else 
		$day = '';
	$dateparams['year'] = $year;
	if ($month <> '') {
		$month = str_pad($month, 2, '0', STR_PAD_LEFT);
		$month = str_pad($month, 4, "-", STR_PAD_LEFT);
		$dateparams['month'] = $month;
	};
	if ($day <> '') {
		$day = str_pad($day, 2, '0', STR_PAD_LEFT);
		$day = str_pad($day, 5, "-", STR_PAD_LEFT);
		$dateparams['day'] = $day;
	}
	return $dateparams;
}

// xml for educations and employers
function europassXMLEmployersEducations($dom, $type, $data) {
	global $USER;
	switch ($type) {
		case 'WorkExperience':
				$orgname = 'employer';
				$orgaddress = 'employeraddress';
				$activities = 'positiondescription';
				break;
		case 'Education':
				$orgname = 'institution';
				$orgaddress = 'institutionaddress';
				$activities = 'qualdescription';
				break;		
	}

	$ExperienceList = $dom->createElement($type.'List');
		foreach ($data as $id => $dataitem) {
			// print_r($employment); echo '<br><br><br>';
			$ExperienceItem = $dom->createElement($type);
				// Period
				$Period = $dom->createElement('Period');
					$From = $dom->createElement('From');
					$date_arr = getDateParamsFromString($dataitem->startdate);
					foreach ($date_arr as $param => $value)
						$From->setAttribute($param, $value);
					$Period->appendChild($From);	
					$textcurrent = $dom->createTextNode(cleanForExternalXML('true'));
					if ($dataitem->enddate <> '') {
						$To = $dom->createElement('To');
						$date_arr = getDateParamsFromString($dataitem->enddate);
						foreach ($date_arr as $param => $value)
							$To->setAttribute($param, $value);
						$Period->appendChild($To);	
						$textcurrent = $dom->createTextNode(cleanForExternalXML('false'));
					};
					$Current = $dom->createElement('Current');
						$Current->appendChild($textcurrent);
						$Period->appendChild($Current);	
				$ExperienceItem->appendChild($Period);					
				// Position
				if ($type == 'WorkExperience') {
					$Position = $dom->createElement('Position');
						$Label = $dom->createElement('Label');
							$text = $dom->createTextNode($dataitem->jobtitle);
							$Label->appendChild($text);
							$Position->appendChild($Label);	
						$Position->appendChild($Label);	
					$ExperienceItem->appendChild($Position);	
				} else if ($type == 'Education') {
					$Title = $dom->createElement('Title');
						$text = $dom->createTextNode($dataitem->qualname);
						$Title->appendChild($text);
					$ExperienceItem->appendChild($Title);	
				};
				// Activities
				if ($dataitem->{$activities} <> '') {
					$Activities = $dom->createElement('Activities');
							$text = $dom->createTextNode($dataitem->{$activities});
							$Activities->appendChild($text);				
					$ExperienceItem->appendChild($Activities);				
				};
				// Employer.
				if ($type == 'WorkExperience') {
					$OrganisationXML = $dom->createElement('Employer');
				} else if ($type == 'Education') {
					$OrganisationXML = $dom->createElement('Organisation');
				}
					// Organisation name.
					$Name = $dom->createElement('Name');
						$text = $dom->createTextNode($dataitem->{$orgname});
						$Name->appendChild($text);
						$OrganisationXML->appendChild($Name);	
					// Employer contacts.
					if ($dataitem->{$orgaddress} <> '') {
						$ContactInfo = $dom->createElement('ContactInfo');
							// address info.
							$Address = $dom->createElement('Address');
								$Contact = $dom->createElement('Contact');
									$AddressLine = $dom->createElement('AddressLine');
										$text = $dom->createTextNode($dataitem->{$orgaddress});
										$AddressLine->appendChild($text);
										$Contact->appendChild($AddressLine);
								$Address->appendChild($Contact);
								$ContactInfo->appendChild($Address);						
						$OrganisationXML->appendChild($ContactInfo);	
					};
				$ExperienceItem->appendChild($OrganisationXML);	
				// attached files
				switch ($type) {
					case 'WorkExperience':
						$filearea = 'resume_employ';
						break;
					case 'Education':
						$filearea = 'resume_edu';
						break;		
				};
				$fs = get_file_storage();
				$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', $filearea, $dataitem->id, 'filename', false);
				if (count($files) > 0) {
					$fileArray = europassXMLGetAttachedFileContents($files);
					$Documentation = europassXMLDocumentationList($dom, $fileArray);
					$ExperienceItem->appendChild($Documentation);	
				}; 
			$ExperienceList->appendChild($ExperienceItem);	
		};
	return $ExperienceList;
}

// Single Achievement for achievementlist
function europassXMLAchievement($dom, $type, $ids = array(), $title, $content) {
	global $USER;
	$files = array();
	$fs = get_file_storage();
	$Achievement = $dom->createElement('Achievement');
		$Title = $dom->createElement('Title');
			$Label = $dom->createElement('Label');
				$text = $dom->createTextNode($title);
				$Label->appendChild($text);
				$Title->appendChild($Label);
			$Achievement->appendChild($Title);
		$Description = $dom->createElement('Description');
			$text = $dom->createTextNode($content);
			$Description->appendChild($text);
			$Achievement->appendChild($Description);
		// Achievement's files
		switch ($type) {
			case 'certif':
				$filearea = 'resume_certif';
				break;
			case 'public':
				$filearea = 'resume_publication';
				break;
			case 'membership':
				$filearea = 'resume_membership';
				break;
			case 'goals':
				foreach ($ids as $id) {
					$files = $files + $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_goalspersonal', $id, 'filename', false);
					$files = $files + $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_goalsacademic', $id, 'filename', false);
				};
				$filearea = 'resume_goalscareers';
				break;
			default:
				$filearea = 'none';
		};
		foreach ($ids as $id) {
			$files = $files + $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', $filearea, $id, 'filename', false);
		};
		if (count($files) > 0) {
			$fileArray = europassXMLGetAttachedFileContents($files);
			$Documentation = europassXMLDocumentationList($dom, $fileArray);
			$Achievement->appendChild($Documentation);	
		}; 
	return $Achievement;
}

// Get string from resume block.
function listForResumeElements($resumeid, $tablename) {
	global $DB, $USER;
	$itemsIDs = array();
	$items = $DB->get_records($tablename, array("resume_id" => $resumeid));
	$itemsstring = '<ul>';
	foreach ($items as $ind => $item) {
		$itemsstring .= '<li>';
		$itemsIDs[] = $ind;
		switch ($tablename) {
			case 'block_exaportresume_certif': 
					$itemsstring .= $item->title;
					$itemsstring .= ' ('.$item->date.')';				
					$itemsstring .= ($item->description ? ". ":"").$item->description;
					break;
			case 'block_exaportresume_public':
					$itemsstring .= $item->title;
					$itemsstring .= ' ('.$item->date.'). ';				
					$itemsstring .= $item->contribution;
					$itemsstring .= ($item->contributiondetails ? ": ":"").$item->contributiondetails;
					break;
			case 'block_exaportresume_mbrship':
					$itemsstring .= $item->title;
					$itemsstring .= ' ('.$item->startdate.($item->enddate ? "-".$item->enddate : "").')';				
					$itemsstring .= ($item->description ? ". ":"").$item->description;
					break;
			default: $itemsstring .= '';
		};

		$itemsstring .= '</li>';
	}
	$itemsstring .= '</ul>';
	return array($itemsstring, $itemsIDs);
}

// Fill global arrays with:
//					fileid => filecontent
// 					fileid => mimetype
// 					fileid => filename
function europassXMLGetAttachedFileContents($files) {
	global $attachedFileNames, $attachedFileDatas, $attachedFileMimeTypes;
	$chars = '123456789';
	$numChars = strlen($chars);
	foreach ($files as $file) {
		$fmimetype = $file->get_mimetype();
		if (($fmimetype=='application/pdf' || $fmimetype=='image/jpeg' || $fmimetype=='image/png') && $file->get_filesize() <= 2560000 ) {
			// random ID
			$ID = 'ATT_';
			for ($i = 0; $i < 13; $i++) {
				$ID .= substr($chars, rand(1, $numChars) - 1, 1);
			};
			$attachedFileMimeTypes[$ID] = $fmimetype;
			$attachedFileDatas[$ID] = base64_encode($file->get_content());					
			$attachedFileNames[$ID] = $file->get_filename();			
			$arrayIDs[] = $ID;
		};
	};
	return $arrayIDs;
}

// get XML for documentations (attached to item)
function europassXMLDocumentationList($dom, $fileArray) {
	if (count($fileArray)>0) {
		$Documentation = $dom->createElement('Documentation');
		foreach ($fileArray as $fileID) {
			$ReferenceTo = $dom->createElement('ReferenceTo');		
			$ReferenceTo->setAttribute('idref', $fileID);
			$Documentation->appendChild($ReferenceTo);
		};
		return $Documentation;
	};
	return null;
};
