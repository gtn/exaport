<?php
function xmldb_block_exaport_upgrade($oldversion) {
	global $DB,$CFG;
	$dbman = $DB->get_manager();
	$result=true;


	/// Add a new column newcol to the mdl_question_myqtype


	if ($oldversion < 2012051801) {
			
		$table = new xmldb_table('block_exaportuser');
		$field_wert = new xmldb_field('user_hash_long');
		$field_wert2 = new xmldb_field('oezinstall');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '30', null, null, null, null, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		$field_wert2->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
		// Conditionally launch add temporary fields
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
		if (!$dbman->field_exists($table, $field_wert2)) {
			$dbman->add_field($table, $field_wert2);
		}
		////
		$table = new xmldb_table('block_exaportcate');
		$field_wert = new xmldb_field('description');
		$field_wert2 = new xmldb_field('isoez');
		$field_wert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		$field_wert2->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
		// Conditionally launch add temporary fields
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
		if (!$dbman->field_exists($table, $field_wert2)) {
			$dbman->add_field($table, $field_wert2);
		}

		$table = new xmldb_table('block_exaportitem');
		$field_wert = new xmldb_field('isoez');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
		$field2_wert = new xmldb_field('fileurl');
		$field2_wert->set_attributes(XMLDB_TYPE_CHAR, '255',null, null, null, null, null);
		$field3_wert = new xmldb_field('beispiel_url');
		$field3_wert->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
		$field4_wert = new xmldb_field('exampid');
		$field4_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);

		// Conditionally launch add temporary fields
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
		if (!$dbman->field_exists($table, $field2_wert)) {
			$dbman->add_field($table, $field2_wert);
		}
		if (!$dbman->field_exists($table, $field3_wert)) {
			$dbman->add_field($table, $field3_wert);
		}
		if (!$dbman->field_exists($table, $field4_wert)) {
			$dbman->add_field($table, $field4_wert);
		}

	}

	if ($oldversion < 2012051801) {
		// update wrong filearea storage

		// update files
		$fs = get_file_storage();

		foreach ($files = $DB->get_records('block_exaportitem', array('type'=>'file')) as $file) {
			if ($file->attachment && preg_match('!^[0-9]+$!', $file->attachment)) {
				// numeral attachment = filestorage id

				$sql = "UPDATE {files} SET component='block_exaport', filearea='item_file', itemid='".$file->id."' WHERE component='user' AND filearea='draft' AND itemid='".$file->attachment."'";
				$DB->execute($sql);

				$update = new stdClass();
				$update->id         = $file->id;
				$update->attachment = '';
				$DB->update_record('block_exaportitem', $update);
			}
		}
	}

	if ($oldversion < 2012072401) {

		$table = new xmldb_table('block_exaportitem');
		$field_wert = new xmldb_field('langid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} else {
			$dbman->change_field_notnull($table, $field_wert);
			$dbman->change_field_default($table, $field_wert);
		}
		$table = new xmldb_table('block_exaportcate');
		$field_wert = new xmldb_field('subjid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} else {
			$dbman->change_field_notnull($table, $field_wert);
			$dbman->change_field_default($table, $field_wert);
		}

		$field_wert2 = new xmldb_field('topicid');
		$field_wert2->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert2)) {
			$dbman->add_field($table, $field_wert2);
		} else {
			$dbman->change_field_notnull($table, $field_wert2);
			$dbman->change_field_default($table, $field_wert2);
		}
			
		$table = new xmldb_table('block_exaportitem');
		$field_wert = new xmldb_field('beispiel_angabe');
		if (!$dbman->field_exists($table, $field_wert)) {
			$field_wert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null); // [XMLDB_ENUM, null,] Moodle 2.x deprec
			$dbman->add_field($table, $field_wert);
		}
	}

	if ($oldversion < 2012101601) {

		$table = new xmldb_table('block_exaportitem');
		$field_wert = new xmldb_field('source');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('sourceid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		
		$field_wert = new xmldb_field('iseditable');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		
		
		$table = new xmldb_table('block_exaportcate');
		$field_wert = new xmldb_field('source');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
		$field_wert = new xmldb_field('sourceid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
	
	}
	
	if ($oldversion < 2012120301) {
		// update wrong filearea storage in personal information

		// update files
		$fs = get_file_storage();

		function block_exaport_wrong_personal_information_upgrade_2012120301($matches) {
			// http://test665.ethinkeducation.com/pluginfile.php/5743/block_exaport/personal_information_self/7-eleven-brand.svg.png
			// http://test665.ethinkeducation.com/draftfile.php/66/user/draft/596724312/Rachel.jpg
			
			$context = get_context_instance(CONTEXT_USER, $GLOBALS['test_for_userid']);
			if ($context->id != $matches['contextid']) return;
			
			$fs = get_file_storage();
			$file = $fs->get_area_files($matches['contextid'], 'user', 'draft', $matches['draftid'], null, false);
			$file = reset($file);
			if (!$file) return;
			
			$fs->create_file_from_storedfile(array(
				'contextid' => $matches['contextid'],
				'component' => 'block_exaport',
				'filearea' => 'personal_information',
				'itemid' => $GLOBALS['test_for_userid'],
				'filename' => $file->get_filename()
			), $file);
			
			return '@@PLUGINFILE@@/';
		}
		
		foreach ($DB->get_records_select('block_exaportuser', "description LIKE '%draftfile%'") as $personalInfo) {
		
			$GLOBALS['test_for_userid'] = $personalInfo->user_id;

			$description = preg_replace_callback("!".preg_quote($CFG->wwwroot)."/draftfile.php/(?<contextid>[0-9]+)/user/draft/(?<draftid>[0-9]+)/!", "block_exaport_wrong_personal_information_upgrade_2012120301", $personalInfo->description);
			
			$update = new stdClass();
			$update->id         = $personalInfo->id;
			$update->description = $description;
			$DB->update_record('block_exaportuser', $update);
		}
	}
	if ($oldversion < 2013031400) {
			$table = new xmldb_table('block_exaportuser');
			$field = new xmldb_field('import_oez_tstamp', XMLDB_TYPE_INTEGER, 20, XMLDB_UNSIGNED, null, null, 0, null);
			if (!$dbman->field_exists($table, $field)) {
				$dbman->add_field($table, $field);
			} 
			$table = new xmldb_table('block_exaportcate');
			$field = new xmldb_field('parent_ids', XMLDB_TYPE_CHAR, '30', null, null, null, null, null);
			if (!$dbman->field_exists($table, $field)) {
				$dbman->add_field($table, $field);
			} 
			$field = new xmldb_field('parent_titles', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
			if (!$dbman->field_exists($table, $field)) {
				$dbman->add_field($table, $field);
			}
			$field = new xmldb_field('stid', XMLDB_TYPE_INTEGER, 20, XMLDB_UNSIGNED, null, null, 0, null);
			if (!$dbman->field_exists($table, $field)) {
				$dbman->add_field($table, $field);
			}
			$field = new xmldb_field('sourcemod', XMLDB_TYPE_INTEGER, 1, XMLDB_UNSIGNED, null, null, 0, null);
			if (!$dbman->field_exists($table, $field)) {
				$dbman->add_field($table, $field);
			}
	}

	return $result;
}

?>