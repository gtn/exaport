<?php  // $Id: information_edit_form.php,v 1.2 2008/09/21 12:57:49 danielpr Exp $

require_once $CFG->libdir.'/formslib.php';

class block_exaport_personal_information_form extends moodleform {

	function definition() {

		global $CFG;
		$mform    =& $this->_form;

//		$mform->addElement('editor', 'description', get_string('message', 'forum'), array('cols'=>50, 'rows'=>30));
//		$mform->setType('description', PARAM_RAW);
//		$mform->addRule('description', get_string('required'), 'required', null, 'client');

                $mform->addElement('editor', 'description', get_string('steckbrief', 'block_exaport'), null,
                    array('maxfiles' => EDITOR_UNLIMITED_FILES));
                
		$mform->addElement('hidden', 'cataction');
		$mform->setType('cataction', PARAM_ALPHA);
		
		$mform->addElement('hidden', 'descid');
		$mform->setType('descid', PARAM_INT);
		
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);
		
		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL);
		
		 $this->add_action_buttons();
	}

}
