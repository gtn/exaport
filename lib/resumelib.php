<?php

require_once $CFG->libdir.'/formslib.php';

class block_exaport_resume_editor_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB;
		$mform    =& $this->_form;
		
		$param = $this->_customdata['field'];		
		$withfiles = $this->_customdata['withfiles'];
		if (!$withfiles)
			$withfiles = false;
		
		$mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');		
		
		$mform->addElement('editor', $param.'_editor', get_string('resume_'.$param, 'block_exaport'), null,
							array('maxfiles' => EDITOR_UNLIMITED_FILES));
							
		if ($withfiles) {
			$mform->addElement('filemanager', 'attachments', get_string('resume_files', 'block_exaport'), null, array('subdirs' => false, 'maxfiles' => 5));
		}
				
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL);
		
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
							$mform->addElement('filemanager', 'attachments', get_string('resume_'.$fieldname, 'block_exaport'), null, array('subdirs' => false, 'maxfiles' => 5));
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
		
		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL); /**/

		$this->add_action_buttons();
	}

}

function block_exaport_resume_prepare_block_mm_data($resume, $id, $type_block, $display_inputs, $data) {
	global $DB, $USER, $OUTPUT;

	$show_information = false;
	$formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$type_block, "block_exaport");
	$workform = new block_exaport_resume_multifields_form($_SERVER['REQUEST_URI'], array('formheader' => $formheader, 'inputs'=>$display_inputs));
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
		file_save_draft_area_files($fromform->attachments, $context->id, 'block_exaport', 'resume_'.$type_block, $item_id, null);
		
		echo "<div class='block_eportfolio_center'>".$OUTPUT->box(get_string('resume_'.$type_block."saved", "block_exaport"), 'center')."</div>";
		$show_information = true;
	} else {
		if ($id > 0) { // Edit existing record.		
			// files
			$draftitemid = file_get_submitted_draft_itemid('attachments');
			$context = context_user::instance($USER->id);
			file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'resume_'.$type_block, $id,
									array('subdirs' => false, 'maxfiles' => 5));                 					
			// all data to form.
			$data = $DB->get_record("block_exaportresume_".$type_block, array('id' => $id, 'user_id' => $USER->id));
			$data->attachments = $draftitemid;   
			$workform->set_data($data);
		} 					
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
    if (is_null($userid)) {
        global $USER;
        $userid = $USER->id;
    }
	
	$resumeparams = new stdClass();
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

function block_exaport_resume_templating_mm_records($courseid, $type, $headertitle, $records) {
	global $CFG, $DB, $OUTPUT, $USER;
	if (count($records) < 1) {
		return '';
	};
	$table = new html_table();
	$table->width = "100%";
	$table->head = array();
	$table->size = array();
	$table->head['title'] = get_string('resume_'.$headertitle, 'block_exaport');
	$table->head['files'] = get_string('resume_files', 'block_exaport');
	$table->head['down'] = '';
	$table->head['up'] = '';
	$table->head['icons'] = ''; 
	
	$table->size['files'] = '40px';
	$table->size['down'] = '16px'; 
	$table->size['up'] = '16px'; 	
	$table->size['icons'] = '40px'; 
		
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
					$table->data[$item_i]['title'] .= $record->employer.'</strong>';
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
			default: break;
		}
		// Count of files
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
		// Links to up/down
		if ($item_i < count($records)-1) {
			$id_next = $keys[$item_i+1];
		};
		$linkto_up = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&sortchange='.$type.'&id1='.$record->id.'&id2='.$id_next.'&sesskey='.sesskey().'"><img src="pix/down_16.png" alt="'.get_string("down").'" /></a>';
		$linkto_down = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&sortchange='.$type.'&id1='.$record->id.'&id2='.$id_prev.'&sesskey='.sesskey().'"><img src="pix/up_16.png" alt="'.get_string("up").'" /></a>';		
		$table->data[$item_i]['up'] = '&nbsp';
		$table->data[$item_i]['down'] = '&nbsp';
		if ($item_i < count($records)-1) {
			$table->data[$item_i]['up'] = $linkto_up;
		};
		if ($item_i > 0) {
			$table->data[$item_i]['down'] = $linkto_down;
		};
		$id_prev = $record->id;
		// Links to edit / delete
		$table->data[$item_i]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&edit='.$type.'&id='.$record->id.'&sesskey='.sesskey().'"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
							' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&delete='.$type.'&id='.$record->id.'&sesskey='.sesskey().'"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>'; 
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
	
	$item_i = -1;
	foreach ($elements as $element) {
		$item_i++;
		// Title and Description
		$table->data[$item_i]['title'] = '<a href="#" class="expandable-head">'.get_string('resume_'.$type.$element, 'block_exaport').'</a>';
		$description = $resume->{$type.$element};
		$description = file_rewrite_pluginfile_urls($description, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_'.$type.$element, $resume->id);
		$table->data[$item_i]['title'] .= '<div class="expandable-text hidden">'.$description.'</div>';
		// Count of files
		$fs = get_file_storage();
		$context = context_user::instance($USER->id);
		$files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type.$element, $resume->id, 'filename', false);
		$count_files = count($files);
		if ($count_files > 0) {
			$table->data[$item_i]['files'] = '<a href="#" class="expandable-head">'.$count_files.'</a>';
			$table->data[$item_i]['files'] .= '<div class="expandable-text hidden">'.block_exaport_resume_list_files($type.$element, $files).'</div>';
		} else {
			$table->data[$item_i]['files'] = '0'; 
		};
		// Links to edit / delete
		$table->data[$item_i]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&edit='.$type.$element.'&id='.$resume->id.'&sesskey='.sesskey().'"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
	};

	
	return html_writer::table($table);
}

function block_exaport_resume_list_files($filearea, $files) {
	global $CFG;
	//print_r($files);
	$listfiles = '<ul class="resume_listfiles">';
	foreach ($files as $file) {
		$filename = $file->get_filename();
//		$url = moodle_url::make_pluginfile_url($file->get_contextid(), 'block_exaport', 'resume_'.$filearea, $file->get_itemid(), $file->get_filepath(), $filename, true);
//		$url = moodle_url::make_file_url('/pluginfile.php', array($file->get_contextid(), 'block_exaport', 'resume_'.$filearea,
//            $file->get_itemid(), $file->get_filepath(), $filename));
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
	global $DB, $USER;
	$conditions = array($USER->id, $resume_id);
	$rec = $DB->get_record_sql('SELECT MAX(sorting) as max_sorting FROM {block_exaportresume_'.$table.'} WHERE user_id=? AND resume_id=?', $conditions); 
	if (isset($rec->max_sorting)) {
		return $rec->max_sorting;
	} else {
		return 0;
	}
}