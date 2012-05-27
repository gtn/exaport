<?php

require_once $CFG->libdir . '/formslib.php';

class block_exaport_import_scorm_form extends moodleform {

    function definition() {
        global $CFG, $USER, $DB;

        $mform = & $this->_form;


        $mform->addElement('header', 'general', get_string("file", "block_exaport"));
        $mform->addElement('filepicker', 'attachment', get_string('file', 'block_exaport'), null, null);
        $this->add_action_buttons();
    }

}

?>
