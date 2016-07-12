<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

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
				$update->id		 = $file->id;
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
			
			$context = context_user::instance($GLOBALS['test_for_userid']);
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
			$update->id		 = $personalInfo->id;
			$update->description = $description;
			$DB->update_record('block_exaportuser', $update);
		}
	}
	if ($oldversion < 2013020101) {

		$table = new xmldb_table('block_exaportview');
		$field_wert = new xmldb_field('layout');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 

		$table = new xmldb_table('block_exaportviewblock');
		$field_wert = new xmldb_field('block_title');
		$field_wert->set_attributes(XMLDB_TYPE_TEXT, 'big', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('firstname');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('lastname');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('email');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('picture');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '250', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
	/**/
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

	if ($oldversion < 2013041101) {

		$table = new xmldb_table('block_exaportview');
	
		$field_wert = new xmldb_field('langid');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		
		$table = new xmldb_table('block_exaportviewblock');
		$field_wert = new xmldb_field('block_title');
		$field_wert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('firstname');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		   
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('lastname');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('email');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('picture');
		$field_wert->set_attributes(XMLDB_TYPE_CHAR, '250', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		
		$table = new xmldb_table('block_exaportview');
		$field_wert = new xmldb_field('layout');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 2,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}
				
	}
	
	if ($oldversion < 2013041201) {
		$table = new xmldb_table('block_exaportview');
		$field_wert = new xmldb_field('layout');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 2,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		}else{
			$dbman->change_field_default($table, $field_wert);
		}
	}

	if ($oldversion < 2013060101) {
		$table = new xmldb_table('block_exaportviewblock');
		$field_wert = new xmldb_field('contentmedia');
		$field_wert->set_attributes(XMLDB_TYPE_TEXT, 'big', null, null, null, null, null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('width');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
		$field_wert = new xmldb_field('height');
		$field_wert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0,null); //  Moodle 2.x deprecated
		if (!$dbman->field_exists($table, $field_wert)) {
			$dbman->add_field($table, $field_wert);
		} 
	}

	if ($oldversion < 2013071700) {

		// Define field view_items_layout to be added to block_exaportuser
		$table = new xmldb_table('block_exaportuser');
		$field = new xmldb_field('view_items_layout', XMLDB_TYPE_TEXT, null, null, null, null, null, 'import_oez_tstamp');

		// Conditionally launch add field view_items_layout
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}

		// eportfolio savepoint reached
	   
	}
	
	if ($oldversion < 2013102205) {

		// Define field view_items_layout to be added to block_exaportuser
		$table = new xmldb_table('block_exaportitem');
		$field = new xmldb_field('example_url');
				$field->set_attributes(XMLDB_TYPE_CHAR, '255',null, null, null, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}

		// eportfolio savepoint reached
		upgrade_block_savepoint(true, 2013102205, 'exaport');
	}

	if ($oldversion < 2013111800) {
	
		// Define field name_short to be added to block_exaportcate
		$table = new xmldb_table('block_exaportcate');
		$field = new xmldb_field('name_short', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourcemod');
	
		// Conditionally launch add field name_short
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
	
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2013111800, 'exaport');
	}
	 if ($oldversion < 2014031700) {
	
		// Define field name_short to be added to block_exaportcate
		$table = new xmldb_table('block_exaportitem');
		$field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', 0, null, null, null, 'example_url');
		// Conditionally launch add field name_short
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
	
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2014031700, 'exaport');
	}
	if($oldversion < 2014081100){
		//to be compatible with oracle change text fields to varchar
		/* block_exaportuser */
		$table = new xmldb_table('block_exaportuser');
		
		$field = new xmldb_field('view_items_layout', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportcate*/
		$table = new xmldb_table('block_exaportcate');
		
		$field = new xmldb_field('parent_titles', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('name_short', XMLDB_TYPE_CHAR, '500');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportview*/
		$table = new xmldb_table('block_exaportview');
		
		$field = new xmldb_field('name', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportviewblock*/
		$table = new xmldb_table('block_exaportviewblock');
		
		$field = new xmldb_field('type', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('block_title', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2014081100, 'exaport');
	}
	if($oldversion < 2014092600){
		$table = new xmldb_table('block_exaportuser');
		$field = new xmldb_field('description', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		$table = new xmldb_table('block_exaportcate');
		$field = new xmldb_field('description', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		$table = new xmldb_table('block_exaportitem');
		
		$field = new xmldb_field('intro', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('beispiel_angabe', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		$table = new xmldb_table('block_exaportitemcomm');
		$field = new xmldb_field('entry', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		$table = new xmldb_table('block_exaportview');
		$field = new xmldb_field('description', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		
		/*block_exaportviewblock*/
		$table = new xmldb_table('block_exaportviewblock');
		$field = new xmldb_field('text', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		$field = new xmldb_field('contentmedia', XMLDB_TYPE_TEXT, null);
		$dbman->change_field_type($table, $field);
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2014092600, 'exaport');
	}
	
	if($oldversion < 2015012600) {
		// Define field autofill_artefacts to be added to block_exaporview
		$table = new xmldb_table('block_exaportview');
		$field = new xmldb_field('autofill_artefacts', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
	
		// Conditionally launch add field autofill_artefacts
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
	}
	
	if($oldversion < 2015030201) {
		// Add group sharing
		$table = new xmldb_table('block_exaportviewgroupshar');
		if (!$dbman->table_exists($table)) {
			// fields
			$table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
			$table->add_field('viewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
			// Create table			
			$dbman->create_table($table);
		}
	}

	if($oldversion < 2015031901) {
		// Add sharing for artefacts
		$table = new xmldb_table('block_exaportcate');
		$field = new xmldb_field('shareall', '3', null, null, null, null, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		$field = new xmldb_field('internshare', '3', null, null, null, null, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		
		$table = new xmldb_table('block_exaportcatshar');
		if (!$dbman->table_exists($table)) {
			// fields
			$table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
			$table->add_field('catid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
			// Create table			
			$dbman->create_table($table);
		}
		$table = new xmldb_table('block_exaportcatgroupshar');
		if (!$dbman->table_exists($table)) {
			// fields
			$table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
			$table->add_field('catid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
			$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
			// Create table			
			$dbman->create_table($table);
		}
	}
	
	if($oldversion < 2015040801) {
		// Add resume functionality.

		// Define table block_exaportresume to be created.
		$table = new xmldb_table('block_exaportresume');
		// Adding fields to table block_exaportresume.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('cover', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('interests', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('goalspersonal', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('goalsacademic', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('goalscareers', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('skillspersonal', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('skillsacademic', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('skillscareers', XMLDB_TYPE_TEXT, null, null, null, null, null);
		// Adding keys to table block_exaportresume.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}
		
		// Define table block_exaportresume_certif to be created.
		$table = new xmldb_table('block_exaportresume_certif');
		// Adding fields to table block_exaportresume_certif.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resume_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('date', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
		$table->add_field('title', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportresume_certif.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_certif.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}		
		
		// Define table block_exaportresume_edu to be created.
		$table = new xmldb_table('block_exaportresume_edu');
		// Adding fields to table block_exaportresume_edu.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resume_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('startdate', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
		$table->add_field('enddate', XMLDB_TYPE_CHAR, '250', null, null, null, null);
		$table->add_field('institution', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('institutionaddress', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('qualtype', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('qualname', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('qualdescription', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportresume_edu.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_edu.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}		
		
		// Define table block_exaportresume_employ to be created.
		$table = new xmldb_table('block_exaportresume_employ');
		// Adding fields to table block_exaportresume_employ.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resume_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('startdate', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
		$table->add_field('enddate', XMLDB_TYPE_CHAR, '250', null, null, null, null);
		$table->add_field('employer', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('employeraddress', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('jobtitle', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('positiondescription', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportresume_employ.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_employ.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}

		// Define table block_exaportresume_mbrship to be created.
		$table = new xmldb_table('block_exaportresume_mbrship');
		// Adding fields to table block_exaportresume_mbrship.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resume_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('startdate', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
		$table->add_field('enddate', XMLDB_TYPE_CHAR, '250', null, null, null, null);
		$table->add_field('title', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportresume_mbrship.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_mbrship.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}

		 // Define table block_exaportresume_public to be created.
		$table = new xmldb_table('block_exaportresume_public');
		// Adding fields to table block_exaportresume_public.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('user_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resume_id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('date', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
		$table->add_field('title', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('contribution', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
		$table->add_field('contributiondetails', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportresume_public.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_public.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}

		// Exaport savepoint reached.
		upgrade_block_savepoint(true, 2015040801, 'exaport');
	}


	if ($oldversion < 2015051901) {

		// Define table block_exaportresume_badges to be created.
		$table = new xmldb_table('block_exaportresume_badges');
		// Adding fields to table block_exaportresume_badges.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('resumeid', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, null);
		$table->add_field('badgeid', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, null);
		$table->add_field('sorting', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, '10');
		// Adding keys to table block_exaportresume_badges.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportresume_badges.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}
		// Exaport savepoint reached.
		upgrade_block_savepoint(true, 2015051901, 'exaport');
	}

	if ($oldversion < 2015052001) {
	 // Define table block_exaportcompresume_mm to be created.
		$table = new xmldb_table('block_exaportcompresume_mm');
		// Adding fields to table block_exaportcompresume_mm.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('compid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null);
		$table->add_field('resumeid', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, null);
		$table->add_field('comptype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
		// Adding keys to table block_exaportcompresume_mm.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportcompresume_mm.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}
		// Exaport savepoint reached.
		upgrade_block_savepoint(true, 2015052001, 'exaport');	
   }

   if ($oldversion < 2015060901) {

		// Define field shareall to be added to block_exaportcate.
		$table = new xmldb_table('block_exaportcate');
		$field = new xmldb_field('structure_shareall', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'internshare');
		// Conditionally launch add field shareall.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		$field = new xmldb_field('structure_share', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'structure_shareall');
		// Conditionally launch add field shareall.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}

	 // Define table block_exaportcat_structshar to be created.
		$table = new xmldb_table('block_exaportcat_structshar');
		// Adding fields to table block_exaportcat_structshar.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('catid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null);
		$table->add_field('userid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null);
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportcat_structshar.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		};
	// Define table block_exaportcat_strgrshar to be created.
		$table = new xmldb_table('block_exaportcat_strgrshar');
		// Adding fields to table block_exaportcat_strgrshar.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('catid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null);
		$table->add_field('groupid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null);
	$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportcat_strgrshar.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}

		// Exaport savepoint reached.
		upgrade_block_savepoint(true, 2015060901, 'exaport');
	}

	if ($oldversion < 2015110900) {
		// drop unused user_id fields
		$field = new xmldb_field('user_id');
		
		$table = new xmldb_table('block_exaportresume_certif');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$table = new xmldb_table('block_exaportresume_edu');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$table = new xmldb_table('block_exaportresume_employ');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$table = new xmldb_table('block_exaportresume_mbrship');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$table = new xmldb_table('block_exaportresume_public');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
	}

	if ($oldversion < 2016031800) {
		// views sharing by email	           
		$table = new xmldb_table('block_exaportview');
		$field = new xmldb_field('sharedemails', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
	
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2016031800, 'exaport');
	}

	if ($oldversion < 2016040500) {
		// views sharing by email - remastering
		$table = new xmldb_table('block_exaportviewemailshar');
		// Adding fields to table block_exaportcat_structshar.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('viewid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
		$table->add_field('email', XMLDB_TYPE_CHAR, '150', null, XMLDB_NOTNULL, null, null);
		$table->add_field('hash', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		// Conditionally launch create table for block_exaportviewemailshar.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		};        
		// move old emails to the new table
		foreach ($views = $DB->get_records('block_exaportview') as $view) {
			$update = new stdClass();
			$update->id	= $view->id;
			if ($view->sharedemails != '') {
				$sharedEmails = explode(';', $view->sharedemails);
				foreach($sharedEmails as $newEmail) {
					// old secure phrase. For keep old links.
					$hash = md5($newEmail.$view->id.'=='.$view->id);
					$insertData = array('viewid' => $view->id, 'email' => $newEmail, 'hash' => $hash);
					$DB->insert_record('block_exaportviewemailshar', $insertData);						
					$update->sharedemails = 1;
					$DB->update_record('block_exaportview', $update);						
				};
			} else {
				$update->sharedemails = 0;
				$DB->update_record('block_exaportview', $update);										
			};
		};
		
		// Changing type of field sharedemails on table block_exaportview to int
		$table = new xmldb_table('block_exaportview');
		$field = new xmldb_field('sharedemails', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', null);
		// Launch change of type for field sharedemails
		if (!$dbman->field_exists($table, $field)) {
			$dbman->change_field_type($table, $field);
		};	
		// exaport savepoint reached
		upgrade_block_savepoint(true, 2016040500, 'exaport');
	}
	
	if ($oldversion < 2016062700) {
		// Change tags itemtype from 'exaport_item' to 'block_exaportitem'
		// for more compatibility with Moodle v3.1
		$sql = "UPDATE {tag_instance} SET itemtype='block_exaportitem' WHERE itemtype='exaport_item'";
		$DB->execute($sql);
	}

	// TODO: delete structure fields / tables
	
   	return $result;
}
