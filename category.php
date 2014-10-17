<?php

require_once dirname(__FILE__) . '/inc.php';
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

block_exaport_setup_default_categories();

$url = '/blocks/exaport/category.php?courseid='.$courseid;
$PAGE->set_url($url);
if (optional_param('action', '', PARAM_ALPHA) == 'addstdcat') {
	block_exaport_import_categories('lang_categories');
	redirect('view_items.php?courseid='.$courseid);
}
if (optional_param('action', '', PARAM_ALPHA) == 'movetocategory') {
	confirm_sesskey();

	$category = $DB->get_record("block_exaportcate", array(
		'id' => required_param('id', PARAM_INT),
		'userid' => $USER->id
	));
	if (!$category) {
		die(block_exaport_get_string('category_not_found'));
	}

	if (!$targetCategory = block_exaport_get_category(required_param('categoryid', PARAM_INT))) {
		die('target category not found');
	}
	
	$DB->update_record('block_exaportcate', (object)array(
		'id' => $category->id,
		'pid' => $targetCategory->id
	));

	echo 'ok';
	exit;
}

if (optional_param('action', '', PARAM_ALPHA) == 'delete') {
	$id = required_param('id', PARAM_INT);
	
	$category = $DB->get_record("block_exaportcate", array(
		'id' => $id,
		'userid' => $USER->id
	));
	if (!$category) die(block_exaport_get_string('category_not_found'));
	
	if (optional_param('confirm', 0, PARAM_INT)) {
		confirm_sesskey();
		
		function block_exaport_recursive_delete_category($id) {
			global $DB;

			// delete subcategories
			if ($entries = $DB->get_records('block_exaportcate', array("pid" => $id))) {
				foreach ($entries as $entry) {
					block_exaport_recursive_delete_category($entry->id);
				}
			}
			$DB->delete_records('block_exaportcate', array('pid'=>$id));

			// delete itemsharing
			if ($entries = $DB->get_records('block_exaportitem', array("categoryid" => $id))) {
				foreach ($entries as $entry) {
					$DB->delete_records('block_exaportitemshar', array('itemid'=>$entry->id));
				}
			}
			
			// delete items
			$DB->delete_records('block_exaportitem', array('categoryid'=>$id));
		}
		block_exaport_recursive_delete_category($category->id);
		
		if (!$DB->delete_records('block_exaportcate', array('id'=>$category->id)))
		{
			$message = "Could not delete your record";
		}
		else
		{
			
			block_exaport_add_to_log($courseid, "bookmark", "delete category", "", $category->id);
			
			redirect('view_items.php?courseid='.$courseid.'&categoryid='.$category->pid);
		}
	}

	$optionsyes = array('action'=>'delete', 'courseid' => $courseid, 'confirm'=>1, 'sesskey'=>sesskey(), 'id'=>$id);
	$optionsno = array(
		'courseid'=>$courseid, 
		'categoryid' => optional_param('back', '', PARAM_TEXT)=='same' ? $category->id : $category->pid
	);
	
	$strbookmarks = get_string("mybookmarks", "block_exaport");
	$strcat = get_string("categories", "block_exaport");

	block_exaport_print_header("bookmarks");
	
	echo '<br />';
	echo $OUTPUT->confirm(get_string("deletecategoryconfirm", "block_exaport", $category), new moodle_url('category.php', $optionsyes), new moodle_url('view_items.php', $optionsno));
	echo block_exaport_wrapperdivend();
	$OUTPUT->footer();

	exit;
}


require_once("$CFG->libdir/formslib.php");
 
class simplehtml_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'pid');
        $mform->setType('pid', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'back');
        $mform->setType('back', PARAM_TEXT);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', block_exaport_get_string('titlenotemtpy'), 'required', null, 'client');

        $this->add_action_buttons();
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

//Instantiate simplehtml_form 
$mform = new simplehtml_form();

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
	redirect('view_items.php?courseid='.$courseid.'&categoryid='.
		(optional_param('back', '', PARAM_TEXT)=='same' ? optional_param('id', 0, PARAM_INT) : optional_param('pid', 0, PARAM_INT)));
} else if ($newEntry = $mform->get_data()) {
	$newEntry->userid = $USER->id;
	
	if ($newEntry->id) {
		$DB->update_record("block_exaportcate", $newEntry);
	} else {
		$newEntry->id = $DB->insert_record("block_exaportcate", $newEntry);
	}

	redirect('view_items.php?courseid='.$courseid.'&categoryid='.
		($newEntry->back=='same' ? $newEntry->id : $newEntry->pid));
} else {
	block_exaport_print_header("bookmarks");
	
	$category = null;
	if ($id = optional_param('id', 0, PARAM_INT)) {
		$category = $DB->get_record_sql('
			SELECT c.id, c.name, c.pid
			FROM {block_exaportcate} c
			WHERE c.userid = ? AND id = ?
		', array($USER->id, $id));
	}
	if (!$category) $category = new stdClass;
	
	$category->courseid = $courseid;
	$category->back = optional_param('back', '', PARAM_TEXT);
	if (empty($category->pid)) $category->pid = optional_param('pid', 0, PARAM_INT);

	$mform->set_data($category);
	$mform->display();
  echo block_exaport_wrapperdivend();
	echo $OUTPUT->footer();
}
