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
			$update->id         = $personalInfo->id;
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
    	$field = new xmldb_field('description', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('view_items_layout', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportcate*/
		$table = new xmldb_table('block_exaportcate');
		
		$field = new xmldb_field('description', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('parent_titles', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('name_short', XMLDB_TYPE_CHAR, '500');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportitem*/
		$table = new xmldb_table('block_exaportitem');
		
		$field = new xmldb_field('intro', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('beispiel_angabe', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
    	/*block_exaportitemcomm*/
		$table = new xmldb_table('block_exaportitemcomm');
		
		$field = new xmldb_field('entry', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportview*/
		$table = new xmldb_table('block_exaportview');
		
		$field = new xmldb_field('name', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('description', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		/*block_exaportviewblock*/
		$table = new xmldb_table('block_exaportviewblock');
		
		$field = new xmldb_field('type', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('text', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('block_title', XMLDB_TYPE_CHAR, '1000');
		$dbman->change_field_type($table, $field);
		
		$field = new xmldb_field('contentmedia', XMLDB_TYPE_CHAR, '1333');
		$dbman->change_field_type($table, $field);
		
		// exaport savepoint reached
    	upgrade_block_savepoint(true, 2014081100, 'exaport');
    }
    
	return $result;
}
