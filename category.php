<?php

require_once dirname(__FILE__) . '/inc.php';
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

block_exaport_setup_default_categories();

$url = '/blocks/exaport/category.php?courseid='.$courseid;
$PAGE->set_url($url);

// Get userlist for sharing category
if (optional_param('action', '', PARAM_ALPHA) == 'userlist') {
    require_once dirname(__FILE__).'/lib/sharelib.php';
	echo json_encode(exaport_get_shareable_courses_with_users(''));
	exit;
}
// Get grouplist for sharing category
if (optional_param('action', '', PARAM_ALPHA) == 'grouplist') {
    require_once dirname(__FILE__).'/lib/sharelib.php';
	echo json_encode(exaport_get_shareable_courses_with_groups(''));
	exit;
}

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
		global $DB;
		global $USER;

		$id = optional_param('id', 0, PARAM_INT);
		$category = $DB->get_record_sql('
			SELECT c.id, c.name, c.pid, c.internshare, c.shareall
			FROM {block_exaportcate} c
			WHERE c.userid = ? AND id = ?
			', array($USER->id, $id));
		if (!$category) $category = new stdClass;
		
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
        
		if (has_capability('block/exaport:shareintern', context_system::instance())) {
			$mform->addElement('checkbox', 'internshare', get_string('share', 'block_exaport'));
			$mform->setType('internshare', PARAM_INT);
			//$mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div>');
			$mform->addElement('html', '<div id="internaccess-settings" class="fitem""><div class="fitemtitle"></div><div class="felement">');
			
			$mform->addElement('html', '<div style="padding: 4px 0;"><table width=100%>');
			// share to all
			if (block_exaport_shareall_enabled()) {
				$mform->addElement('html', '<tr><td>');
				$mform->addElement('html', '<input type="radio" name="shareall" value="1"'.($category->shareall==1 ? ' checked="checked"' : '').'/>');
				$mform->addElement('html', '</td><td>'.get_string('internalaccessall', 'block_exaport').'</td></tr>');
				$mform->setType('shareall', PARAM_INT);
				$mform->addElement('html', '</td></tr>');
			}

			// share to users
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="shareall" value="0"'.(!$category->shareall ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessusers', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcatshar', array("catid" => $category->id), null, 'userid, userid AS tmp');
				$mform->addElement('html', '<script> var sharedusersarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'sharedusersarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}
			$mform->addElement('html', '<tr id="internaccess-users"><td></td><td><div id="sharing-userlist">userlist</div></td></tr>');
			//$mform->addElement('html', '</div>');

			// share to groups
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="shareall" value="2"'.($category->shareall==2 ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessgroups', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcatgroupshar', array("catid" => $category->id), null, 'groupid, groupid AS tmp');
				$mform->addElement('html', '<script> var sharedgroupsarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'sharedgroupsarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}/**/
			$mform->addElement('html', '<tr id="internaccess-groups"><td></td><td><div id="sharing-grouplist">grouplist</div></td></tr>');
			//$mform->addElement('html', '</div>');
			
			
			$mform->addElement('html', '</table></div>');
			$mform->addElement('html', '</div></div>');
		};

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
	$newEntry->shareall = optional_param('shareall', 0, PARAM_INT);
	if (optional_param('internshare', 0, PARAM_INT) > 0) {
		$newEntry->internshare = optional_param('internshare', 0, PARAM_INT);
	} else {
		$newEntry->internshare = 0;
	}

	if ($newEntry->id) {
		$DB->update_record("block_exaportcate", $newEntry);
	} else {
		$newEntry->id = $DB->insert_record("block_exaportcate", $newEntry);
	}
	// Share to users.
	if (!empty($_POST["shareusers"])){
		$shareusers = $_POST["shareusers"];
		if (function_exists("clean_param_array")) 
			$shareusers=clean_param_array($shareusers,PARAM_SEQUENCE,false);
	} else {
		$shareusers = "";
	}	
	// delete all shared users
	$DB->delete_records("block_exaportcatshar", array('catid' => $newEntry->id));
	// add new shared users
	if ($newEntry->internshare && !$newEntry->shareall && is_array($shareusers)) {
		foreach ($shareusers as $shareuser) {
			$shareuser = clean_param($shareuser, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->userid = $shareuser;
			$DB->insert_record("block_exaportcatshar", $shareItem);
		};
	};
	// Share to groups.
	if (!empty($_POST["sharegroups"])){
		$sharegroups = $_POST["sharegroups"];
		if (function_exists("clean_param_array")) 
			$sharegroups=clean_param_array($sharegroups,PARAM_SEQUENCE,false);
	} else {
		$sharegroups = "";
	}	
	// delete all shared users
	$DB->delete_records("block_exaportcatgroupshar", array('catid' => $newEntry->id));
	// add new shared groups
	if ($newEntry->internshare && $newEntry->shareall==2 && is_array($sharegroups)) {
		foreach ($sharegroups as $sharegroup) {
			$sharegroup = clean_param($sharegroup, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->groupid = $sharegroup;
			$DB->insert_record("block_exaportcatgroupshar", $shareItem);
		};
	};
	redirect('view_items.php?courseid='.$courseid.'&categoryid='.
		($newEntry->back=='same' ? $newEntry->id : $newEntry->pid));
} else {
	block_exaport_print_header("bookmarks");
	
	$category = null;
	if ($id = optional_param('id', 0, PARAM_INT)) {
		$category = $DB->get_record_sql('
			SELECT c.id, c.name, c.pid, c.internshare, c.shareall
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
  
$PAGE->requires->js('/blocks/exaport/javascript/category.js', true);

// Translations
$translations = array(
	'name', 'role', 'nousersfound',
    'internalaccessgroups', 'grouptitle', 'membersnumber', 'nogroupsfound', 
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
	ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
//]]>
</script>
<?php /**/

	echo $OUTPUT->footer();
}
