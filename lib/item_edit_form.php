<?php

// $Id: item_edit_form.php,v 1.2 2008/09/21 12:57:49 danielpr Exp $

require_once $CFG->libdir . '/formslib.php';
//require_once $CFG->libdir . '/filelib.php';

class block_exaport_comment_edit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $DB;
        $mform = & $this->_form;

        $this->_form->_attributes['action'] = $_SERVER['REQUEST_URI'];
        $mform->addElement('header', 'comment', get_string("addcomment", "block_exaport"));

        $mform->addElement('editor', 'entry', get_string("comment", "block_exaport"),null, array('rows' => 10));
        $mform->setType('entry', PARAM_TEXT);
        $mform->addRule('entry', get_string("commentshouldnotbeempty", "block_exaport"), 'required', null, 'client');
        //$mform->setHelpButton('entry', array('writing', 'richtext'), false, 'editorhelpbutton');

        $this->add_action_buttons(false, get_string('add'));

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', 'add');

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->setDefault('itemid', 0);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setDefault('userid', 0);
    }

}

class block_exaport_item_edit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $DB;

        $type = $this->_customdata['type'];

        $mform = & $this->_form;

        $mform->addElement('header', 'general', get_string($type, "block_exaport"));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);
        // wird f�r das formular beim moodle import ben�tigt
        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', '');

        $mform->addElement('hidden', 'compids');
        $mform->setType('compids', PARAM_TEXT);
        $mform->setDefault('compids','');

        $mform->addElement('text', 'name', get_string("title", "block_exaport"), 'maxlength="255" size="60"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string("titlenotemtpy", "block_exaport"), 'required', null, 'client');

        $mform->addElement('select', 'categoryid', get_string("category", "block_exaport"), array());
        $mform->addRule('categoryid', get_string("categorynotempty", "block_exaport"), 'required', null, 'client');
        $mform->setDefault('categoryid', 0);
        $this->category_select_setup();

        if ($type == 'link') {
            $mform->addElement('text', 'url', get_string("url", "block_exaport"), 'maxlength="255" size="60" value="http://"');
            $mform->setType('url', PARAM_TEXT);
            $mform->addRule('url', get_string("urlnotempty", "block_exaport"), 'required', null, 'client');
        } elseif ($type == 'file') {
            if ($this->_customdata['action'] == 'add') {
				$mform->addElement('filemanager', 'file', get_string('file', 'block_exaport'), null, array('subdirs' => false, 'maxfiles' => 1));
			} else {
                // filename for assignment import
                $mform->addElement('hidden', 'filename');
                $mform->setType('filename', PARAM_TEXT);
                $mform->setDefault('filename', '');
            }
        }

	
        if (block_exaport_course_has_desp()) {
        	$langcode=get_string("langcode","block_desp");
        	
        	$sql = "SELECT lang.id,lang.".$langcode." as name FROM {block_desp_lang} lang WHERE id IN(SELECT langid FROM {block_desp_check_lang} WHERE userid=?) OR id IN (SELECT langid FROM {block_desp_lanhistories} WHERE userid=?) ORDER BY lang.".$langcode;
        	$languages = $DB->get_records_sql_menu($sql, array($USER->id, $USER->id));
        	
        	$languages[0]='';
        
        	asort($languages);
        	$mform->addElement('select', 'langid', get_string("desp_language", "block_exaport"), $languages);
        	$mform->setType('langid', PARAM_INT);
        }
        
        $mform->addElement('editor', 'intro_editor', get_string('intro', 'block_exaport'), null, $this->_customdata['textfieldoptions']);
        $mform->setType('intro_editor', PARAM_RAW);
        if ($type == 'note')
            $mform->addRule('intro_editor', get_string("intronotempty", "block_exaport"), 'required', null, 'client');

        $this->add_action_buttons();
    }

    function category_select_setup() {
        global $CFG, $USER, $DB;
        $mform = & $this->_form;
        $categorysselect = & $mform->getElement('categoryid');
        $categorysselect->removeOptions();

        $conditions = array("userid" => $USER->id, "pid" => 0);
        $outercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
        $categories = array();
        if ($outercategories) {
            foreach ($outercategories as $curcategory) {
                $categories[$curcategory->id] = format_string($curcategory->name);

                $conditions = array("userid" => $USER->id, "pid" => $curcategory->id);
                $inner_categories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
                if ($inner_categories) {
                    foreach ($inner_categories as $inner_curcategory) {
                        $categories[$inner_curcategory->id] = format_string($curcategory->name) . '&rArr; ' . format_string($inner_curcategory->name);
                    }
                }
            }
        } else {
            $categories[0] = get_string("nocategories", "block_exaport");
        }
        $categorysselect->loadArray($categories);
    }

}
