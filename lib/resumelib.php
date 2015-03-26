<?php

require_once $CFG->libdir.'/formslib.php';

class block_exaport_resume_editor_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB;
		$mform    =& $this->_form;
		
		$param = $this->_customdata['field'];
		
		$mform->addElement('editor', $param.'_editor', get_string('resume_'.$param, 'block_exaport'), null,
							array('maxfiles' => EDITOR_UNLIMITED_FILES));
				
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL);

		$this->add_action_buttons();
	}

}

class block_exaport_resume_multifields_form extends moodleform {

	function definition() {

		global $CFG, $USER, $DB;
		$mform  =& $this->_form;
		
		$attributes = array('size' => '20');
		
		$inputs = $this->_customdata['inputs'];
		
		if (isset($inputs) && is_array($inputs) && count($inputs) > 0) {
			foreach ($inputs as $fieldname => $fieldtype) { 
				list ($type, $required) = explode(':', $fieldtype);
				switch ($type) {
					case 'text' : 
							$mform->addElement('text', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'), $attributes);
							$mform->setType($fieldname, PARAM_RAW);
							break;
					case 'textarea' : 
							$mform->addElement('textarea', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'));
							$mform->setType($fieldname, PARAM_RAW);
							break;
				};
				// Required field.
				if ($required == 'required')
						$mform->addRule($fieldname, null, 'required');
			}			
		};		

		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);

		$mform->addElement('hidden', 'resume_id');
		$mform->setType('resume_id', PARAM_INT);
		
		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL); /**/

		$this->add_action_buttons();
	}

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
