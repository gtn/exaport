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

	if ($oldversion < 2012072400) {
		// update wrong filearea storage

		// update files

		$table = new xmldb_table('block_exaportitem');
		$field_wert = new xmldb_field('langid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} else {
			$dbman->change_field_default($table, $field_wert);
		}
		$table = new xmldb_table('block_exaportcate');
		$field_wert = new xmldb_field('subjid');
		$field_wert2 = new xmldb_field('topicid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, true, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} else {
			$dbman->change_field_notnull($table, $field_wert);
			$dbman->change_field_default($table, $field_wert);
		}

		$field_wert2->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, true, null, 0, null); // [XMLDB_ENUM, null,] Moodle 2.x deprecated
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


	return $result;
}

?>