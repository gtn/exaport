<?php

require_once $CFG->libdir.'/formslib.php';
require_once $CFG->libdir . '/filelib.php';

class block_exaport_scorm_upload_form extends moodleform {

	function definition() {
		global $CFG, $USER, $COURSE;
		$mform	=& $this->_form;

		$mform->addElement('header', 'general', "Import");

		$mform->addElement('filepicker', 'attachment', get_string('file'), null, array('accepted_types' => '*'));
		$this->add_action_buttons();

		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);
		$mform->setDefault('courseid', 0);
	}

}
