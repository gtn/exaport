<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

defined('MOODLE_INTERNAL') || die();

function xmldb_block_exaport_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    $result = true;

    // Add a new column newcol to the mdl_question_myqtype.
    if ($oldversion < 2012051801) {

        $table = new xmldb_table('block_exaportuser');
        $fieldwert = new xmldb_field('user_hash_long');
        $fieldwert2 = new xmldb_field('oezinstall');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '30', null, null, null, null, null);
        $fieldwert2->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
        // Conditionally launch add temporary fields.
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        if (!$dbman->field_exists($table, $fieldwert2)) {
            $dbman->add_field($table, $fieldwert2);
        }

        $table = new xmldb_table('block_exaportcate');
        $fieldwert = new xmldb_field('description');
        $fieldwert2 = new xmldb_field('isoez');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
        $fieldwert2->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
        // Conditionally launch add temporary fields.
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        if (!$dbman->field_exists($table, $fieldwert2)) {
            $dbman->add_field($table, $fieldwert2);
        }

        $table = new xmldb_table('block_exaportitem');
        $fieldwert = new xmldb_field('isoez');
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
        $field2wert = new xmldb_field('fileurl');
        $field2wert->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        $field3wert = new xmldb_field('beispiel_url');
        $field3wert->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        $field4wert = new xmldb_field('exampid');
        $field4wert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);

        // Conditionally launch add temporary fields.
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        if (!$dbman->field_exists($table, $field2wert)) {
            $dbman->add_field($table, $field2wert);
        }
        if (!$dbman->field_exists($table, $field3wert)) {
            $dbman->add_field($table, $field3wert);
        }
        if (!$dbman->field_exists($table, $field4wert)) {
            $dbman->add_field($table, $field4wert);
        }

    }

    if ($oldversion < 2012051801) {
        // Update wrong filearea storage.

        // Update files.
        $fs = get_file_storage();

        foreach ($files = $DB->get_records('block_exaportitem', array('type' => 'file')) as $file) {
            if ($file->attachment && preg_match('!^[0-9]+$!', $file->attachment)) {
                // Numeral attachment = filestorage id.

                $sql = "UPDATE {files} SET component='block_exaport', filearea='item_file', itemid='" . $file->id . "' " .
                    " WHERE component='user' AND filearea='draft' AND itemid='" . $file->attachment . "'";
                $DB->execute($sql);

                $update = new stdClass();
                $update->id = $file->id;
                $update->attachment = '';
                $DB->update_record('block_exaportitem', $update);
            }
        }
    }

    if ($oldversion < 2012072401) {

        $table = new xmldb_table('block_exaportitem');
        $fieldwert = new xmldb_field('langid');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        } else {
            $dbman->change_field_notnull($table, $fieldwert);
            $dbman->change_field_default($table, $fieldwert);
        }
        $table = new xmldb_table('block_exaportcate');
        $fieldwert = new xmldb_field('subjid');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        } else {
            $dbman->change_field_notnull($table, $fieldwert);
            $dbman->change_field_default($table, $fieldwert);
        }

        $fieldwert2 = new xmldb_field('topicid');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert2->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert2)) {
            $dbman->add_field($table, $fieldwert2);
        } else {
            $dbman->change_field_notnull($table, $fieldwert2);
            $dbman->change_field_default($table, $fieldwert2);
        }

        $table = new xmldb_table('block_exaportitem');
        $fieldwert = new xmldb_field('beispiel_angabe');
        if (!$dbman->field_exists($table, $fieldwert)) {
            // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
            $fieldwert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
            $dbman->add_field($table, $fieldwert);
        }
    }

    if ($oldversion < 2012101601) {

        $table = new xmldb_table('block_exaportitem');
        $fieldwert = new xmldb_field('source');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('sourceid');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

        $fieldwert = new xmldb_field('iseditable');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

        $table = new xmldb_table('block_exaportcate');
        $fieldwert = new xmldb_field('source');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('sourceid');
        // This is Moodle 2.x deprecated: [XMLDB_ENUM, null,].
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

    }

    if ($oldversion < 2012120301) {
        // Update wrong filearea storage in personal information.

        // Update files.
        $fs = get_file_storage();

        function block_exaport_wrong_personal_information_upgrade_2012120301($matches) {
            // Http://test665.ethinkeducation.com/pluginfile.php/5743/block_exaport/personal_information_self/7-eleven-brand.svg.png.
            // Http://test665.ethinkeducation.com/draftfile.php/66/user/draft/596724312/Rachel.jpg.

            $context = context_user::instance($GLOBALS['test_for_userid']);
            if ($context->id != $matches['contextid']) {
                return;
            }

            $fs = get_file_storage();
            $file = $fs->get_area_files($matches['contextid'], 'user', 'draft', $matches['draftid'], null, false);
            $file = reset($file);
            if (!$file) {
                return;
            }

            $fs->create_file_from_storedfile(array(
                'contextid' => $matches['contextid'],
                'component' => 'block_exaport',
                'filearea' => 'personal_information',
                'itemid' => $GLOBALS['test_for_userid'],
                'filename' => $file->get_filename(),
            ), $file);

            return '@@PLUGINFILE@@/';
        }

        foreach ($DB->get_records_select('block_exaportuser', "description LIKE '%draftfile%'") as $personalinfo) {

            $GLOBALS['test_for_userid'] = $personalinfo->user_id;

            $description = preg_replace_callback(
                "!" . preg_quote($CFG->wwwroot) . "/draftfile.php/(?<contextid>[0-9]+)/user/draft/(?<draftid>[0-9]+)/!",
                "block_exaport_wrong_personal_information_upgrade_2012120301",
                $personalinfo->description);

            $update = new stdClass();
            $update->id = $personalinfo->id;
            $update->description = $description;
            $DB->update_record('block_exaportuser', $update);
        }
    }
    if ($oldversion < 2013020101) {

        $table = new xmldb_table('block_exaportview');
        $fieldwert = new xmldb_field('layout');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

        $table = new xmldb_table('block_exaportviewblock');
        $fieldwert = new xmldb_field('block_title');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_TEXT, 'big', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('firstname');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('lastname');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('email');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('picture');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '250', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
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

    if ($oldversion < 2013041101) {

        $table = new xmldb_table('block_exaportview');

        $fieldwert = new xmldb_field('langid');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

        $table = new xmldb_table('block_exaportviewblock');
        $fieldwert = new xmldb_field('block_title');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('firstname');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);

        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('lastname');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('email');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '150', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('picture');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_CHAR, '250', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

        $table = new xmldb_table('block_exaportview');
        $fieldwert = new xmldb_field('layout');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 2, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }

    }

    if ($oldversion < 2013041201) {
        $table = new xmldb_table('block_exaportview');
        $fieldwert = new xmldb_field('layout');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 2, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        } else {
            $dbman->change_field_default($table, $fieldwert);
        }
    }

    if ($oldversion < 2013060101) {
        $table = new xmldb_table('block_exaportviewblock');
        $fieldwert = new xmldb_field('contentmedia');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_TEXT, 'big', null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('width');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
        $fieldwert = new xmldb_field('height');
        // Moodle 2.x deprecated.
        $fieldwert->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, null);
        if (!$dbman->field_exists($table, $fieldwert)) {
            $dbman->add_field($table, $fieldwert);
        }
    }

    if ($oldversion < 2013071700) {

        // Define field view_items_layout to be added to block_exaportuser.
        $table = new xmldb_table('block_exaportuser');
        $field = new xmldb_field('view_items_layout', XMLDB_TYPE_TEXT, null, null, null, null, null, 'import_oez_tstamp');

        // Conditionally launch add field view_items_layout.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Eportfolio savepoint reached.

    }

    if ($oldversion < 2013102205) {

        // Define field view_items_layout to be added to block_exaportuser.
        $table = new xmldb_table('block_exaportitem');
        $field = new xmldb_field('example_url');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Eportfolio savepoint reached.
        upgrade_block_savepoint(true, 2013102205, 'exaport');
    }

    if ($oldversion < 2013111800) {

        // Define field name_short to be added to block_exaportcate.
        $table = new xmldb_table('block_exaportcate');
        $field = new xmldb_field('name_short', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourcemod');

        // Conditionally launch add field name_short.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2013111800, 'exaport');
    }
    if ($oldversion < 2014031700) {

        // Define field name_short to be added to block_exaportcate.
        $table = new xmldb_table('block_exaportitem');
        $field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', 0, null, null, null, 'example_url');
        // Conditionally launch add field name_short.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2014031700, 'exaport');
    }
    if ($oldversion < 2014081100) {
        // To be compatible with oracle change text fields to varchar
        // block_exaportuser.
        $table = new xmldb_table('block_exaportuser');

        $field = new xmldb_field('view_items_layout', XMLDB_TYPE_CHAR, '1000');
        $dbman->change_field_type($table, $field);

        // Block_exaportcate.
        $table = new xmldb_table('block_exaportcate');

        $field = new xmldb_field('parent_titles', XMLDB_TYPE_CHAR, '1333');
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('name_short', XMLDB_TYPE_CHAR, '500');
        $dbman->change_field_type($table, $field);

        // Block_exaportview.
        $table = new xmldb_table('block_exaportview');

        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '1000');
        $dbman->change_field_type($table, $field);

        // Block_exaportviewblock.
        $table = new xmldb_table('block_exaportviewblock');

        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '1000');
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('block_title', XMLDB_TYPE_CHAR, '1000');
        $dbman->change_field_type($table, $field);

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2014081100, 'exaport');
    }
    if ($oldversion < 2014092600) {
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

        // Block_exaportviewblock.
        $table = new xmldb_table('block_exaportviewblock');
        $field = new xmldb_field('text', XMLDB_TYPE_TEXT, null);
        $dbman->change_field_type($table, $field);
        $field = new xmldb_field('contentmedia', XMLDB_TYPE_TEXT, null);
        $dbman->change_field_type($table, $field);
        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2014092600, 'exaport');
    }

    if ($oldversion < 2015012600) {
        // Define field autofill_artefacts to be added to block_exaporview.
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('autofill_artefacts', XMLDB_TYPE_TEXT, null, null, null, null, null, null);

        // Conditionally launch add field autofill_artefacts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2015030201) {
        // Add group sharing.
        $table = new xmldb_table('block_exaportviewgroupshar');
        if (!$dbman->table_exists($table)) {
            // Fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
            $table->add_field('viewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
            // Create table.
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2015031901) {
        // Add sharing for artefacts.
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
            // Fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
            $table->add_field('catid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
            // Create table.
            $dbman->create_table($table);
        }
        $table = new xmldb_table('block_exaportcatgroupshar');
        if (!$dbman->table_exists($table)) {
            // Fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
            $table->add_field('catid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
            // Create table.
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2015040801) {
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
        // Drop unused user_id fields.
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
        // Views sharing by email.
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('sharedemails', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2016031800, 'exaport');
    }

    if ($oldversion < 2016040500) {
        // Views sharing by email - remastering.
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
        // Move old emails to the new table.
        foreach ($views = $DB->get_records('block_exaportview') as $view) {
            $update = new stdClass();
            $update->id = $view->id;
            if ($view->sharedemails != '') {
                $sharedemails = explode(';', $view->sharedemails);
                foreach ($sharedemails as $newemail) {
                    // Old secure phrase. For keep old links.
                    $hash = md5($newemail . $view->id . '==' . $view->id);
                    $insertdata = array('viewid' => $view->id, 'email' => $newemail, 'hash' => $hash);
                    $DB->insert_record('block_exaportviewemailshar', $insertdata);
                    $update->sharedemails = 1;
                    $DB->update_record('block_exaportview', $update);
                };
            } else {
                $update->sharedemails = 0;
                $DB->update_record('block_exaportview', $update);
            };
        };

        // Changing type of field sharedemails on table block_exaportview to int.
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('sharedemails', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', null);
        // Launch change of type for field sharedemails.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        };
        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2016040500, 'exaport');
    }

    if ($oldversion < 2016062700) {
        // Change tags itemtype from 'exaport_item' to 'block_exaportitem'
        // for more compatibility with Moodle v3.1.
        $sql = "UPDATE {tag_instance} SET itemtype='block_exaportitem' WHERE itemtype='exaport_item'";
        $DB->execute($sql);
    }

    if ($oldversion < 2019030702) {
        // add indexes
        $tableswithindexes = array(
            'block_exaportcate' => array('pid', 'userid', 'shareall', 'internshare', 'structure_shareall', 'structure_share'),
            'block_exaportcatshar' => array('catid', 'userid'),
            'block_exaportcatgroupshar' => array('catid', 'groupid'),
            'block_exaportitem' => array('userid', 'type', 'categoryid', 'shareall'),
            'block_exaportitemshar' => array('itemid', 'userid'),
            'block_exaportitemcomm' => array('itemid', 'userid'),
            'block_exaportview' => array('hash', 'userid', 'shareall'),
            'block_exaportviewblock' => array('viewid', 'itemid'),
            'block_exaportviewshar' => array('viewid', 'userid'),
            'block_exaportviewgroupshar' => array('viewid', 'groupid'),
            'block_exaportresume' => array('user_id'),
            'block_exaportresume_certif' => array('resume_id'),
            'block_exaportresume_edu' => array('resume_id'),
            'block_exaportresume_employ' => array('resume_id'),
            'block_exaportresume_mbrship' => array('resume_id'),
            'block_exaportresume_public' => array('resume_id'),
            'block_exaportresume_badges' => array('resumeid', 'badgeid'),
            'block_exaportcompresume_mm' => array('compid', 'resumeid'),
            'block_exaportcat_structshar' => array('catid', 'userid'),
            'block_exaportcat_strgrshar' => array('catid', 'groupid'),
            'block_exaportviewemailshar' => array('viewid'),
        );
        foreach ($tableswithindexes as $tablename => $indexes) {
            $table = new xmldb_table($tablename);
            foreach ($indexes as $indexname) {
                $index = new xmldb_index($indexname, XMLDB_INDEX_NOTUNIQUE, array($indexname));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }
        upgrade_block_savepoint(true, 2019030702, 'exaport');
    }

    if ($oldversion < 2019030703) {
        $table = new xmldb_table('block_exaportviewblock');
        $field = new xmldb_field('resume_itemtype', XMLDB_TYPE_CHAR, '15', null, null, false, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_block_savepoint(true, 2019030703, 'exaport');
    }

    if ($oldversion < 2019031500) {
        $table = new xmldb_table('block_exaportviewblock');
        $field = new xmldb_field('resume_withfiles', XMLDB_TYPE_INTEGER, '2', null, null, false, 0, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_block_savepoint(true, 2019031500, 'exaport');
    }

    if ($oldversion < 2019111202) {
        // change indexes again
        $tableswithindexes = array(
            'block_exaportcate' => array('pid', 'userid', 'shareall', 'internshare', 'structure_shareall', 'structure_share'),
            'block_exaportcatshar' => array('catid', 'userid'),
            'block_exaportcatgroupshar' => array('catid', 'groupid'),
            'block_exaportitem' => array('userid', 'type', 'categoryid', 'shareall'),
            'block_exaportitemshar' => array('itemid', 'userid'),
            'block_exaportitemcomm' => array('itemid', 'userid'),
            'block_exaportview' => array('hash', 'userid', 'shareall'),
            'block_exaportviewblock' => array('viewid', 'itemid'),
            'block_exaportviewshar' => array('viewid', 'userid'),
            'block_exaportviewgroupshar' => array('viewid', 'groupid'),
            'block_exaportresume' => array('user_id'),
            'block_exaportresume_certif' => array('resume_id'),
            'block_exaportresume_edu' => array('resume_id'),
            'block_exaportresume_employ' => array('resume_id'),
            'block_exaportresume_mbrship' => array('resume_id'),
            'block_exaportresume_public' => array('resume_id'),
            'block_exaportresume_badges' => array('resumeid', 'badgeid'),
            'block_exaportcompresume_mm' => array('compid', 'resumeid'),
            'block_exaportcat_structshar' => array('catid', 'userid'),
            'block_exaportcat_strgrshar' => array('catid', 'groupid'),
            'block_exaportviewemailshar' => array('viewid'),
        );
        foreach ($tableswithindexes as $tablename => $indexes) {
            $table = new xmldb_table($tablename);
            // delete all existing indexes
            $existingindexes = $DB->get_indexes($tablename);
            foreach ($existingindexes as $indexname => $eindex) {
                if (trim(strtolower($indexname)) != 'primary') {
                    $index = new xmldb_index($indexname, XMLDB_INDEX_NOTUNIQUE, $eindex['columns']);
                    if ($dbman->index_exists($table, $index)) {
                        $dbman->drop_index($table, $index);
                    }
                    // $DB->execute('DROP INDEX '.$eindex.' ON '.$DB->get_prefix().$tablename.' ');
                }
            }
            foreach ($indexes as $indexname) {
                $index = new xmldb_index($indexname, XMLDB_INDEX_NOTUNIQUE, array($indexname));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }
        upgrade_block_savepoint(true, 2019111202, 'exaport');
    }

    if ($oldversion < 2022090400 || 11 == 11) { // for any plugin version - we need to check these files and ask admin to delete them
        // delete redundant files
        $filenames = ['epop.php', 'epop_viewfile.php', 'epopal.php', 'epopalm.php'];

        $manual_deleting = [];
        foreach ($filenames as $filename) {
            $file_r_path = '/blocks/exaport/' . $filename;
            $filePath = $CFG->dirroot . $file_r_path;
            if (file_exists($filePath)) {
                if (is_writable(dirname($filePath)) && unlink($filePath)) {
                    // file deleted
                } else {
                    $manual_deleting[] = $file_r_path;
                }
            }
        }

        if (count($manual_deleting) > 0) {
            $message = 'We strongly recommend to delete these files from the server:<ul>';
            foreach ($manual_deleting as $f_name) {
                $message .= '<li>' . $f_name . '</li>';
            }
            $message .= '</ul>';
            echo '<div class="alert alert-warning alert-block fade in">' . $message . '</div>';
            upgrade_log(UPGRADE_LOG_ERROR, 'block_exaport', $message, null, null);
        }
        if ($oldversion < 2022090400) {
            upgrade_block_savepoint(true, 2022090400, 'exaport');
        }
    }

    if ($oldversion < 2022090600) {
        // Define a new field for table block_exaportresume
        $table = new xmldb_table('block_exaportresume');
        $field = new xmldb_field('url', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2022090600, 'exaport');
    }
    if ($oldversion < 2022092800) {
        //    Define a new field for table block_exaportresume
        $table = new xmldb_table('block_exaportresume');
        $field = new xmldb_field('linkedinurl', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('url', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        //        Exaport savepoint reached.
        upgrade_block_savepoint(true, 2022092800, 'exaport');
    }

    // TODO: delete structure fields / tables.

    if ($oldversion < 2022102800) {
        // Define field timecreated to be added to block_exaportitem.
        $table = new xmldb_table('block_exaportitem');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attachment');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2022102800, 'exaport');
    }

    if ($oldversion < 2023102600) {
        // Define a new field 'pdf_settings' for table block_exaportview
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('pdf_settings', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2023102600, 'exaport');
    }

    if ($oldversion < 2023121800) {
        // Define a new field 'pdf_settings' for table block_exaportview
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('layout_settings', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2023121800, 'exaport');
    }

    if ($oldversion < 2024030400) {
        // 'Personal information' block is removed from CV. But if the data is already filled - move this data into 'About me' CV content.

        // Some helper functions from 'blocks/exaport/lib/resumelib.php'
        $block_exaport_get_resume_params_record = function($userid = null) use ($DB) {
            if (!$userid) {
                return false;
            }
            $conditions = array("user_id" => $userid);
            return $DB->get_record('block_exaportresume', $conditions);
        };
        $block_exaport_set_resume_params = function($userid, $params = null) use ($DB, $block_exaport_get_resume_params_record) {
            $newresumeparams = (object)$params;
            if ($oldresumeparams = $block_exaport_get_resume_params_record($userid)) {
                $newresumeparams->id = $oldresumeparams->id;
                $DB->update_record('block_exaportresume', $newresumeparams);
            } else {
                $newresumeparams->user_id = $userid;
                $DB->insert_record("block_exaportresume", $newresumeparams);
            }
        };
        $block_exaport_get_user_preferences_record = function($userid = null) use ($DB) {
            if (!$userid) {
                return false;
            }
            $conditions = array("user_id" => $userid);
            return $DB->get_record('block_exaportuser', $conditions);
        };
        $block_exaport_set_user_preferences = function($userid, $preferences = null) use ($DB, $block_exaport_get_user_preferences_record) {
            $newuserpreferences = (object)$preferences;
            if ($olduserpreferences = $block_exaport_get_user_preferences_record($userid)) {
                $newuserpreferences->id = $olduserpreferences->id;
                $DB->update_record('block_exaportuser', $newuserpreferences);
            } else {
                $newuserpreferences->user_id = $userid;
                $DB->insert_record("block_exaportuser", $newuserpreferences);
            }
        };

        foreach ($DB->get_records('block_exaportuser') as $pdata) {
            $tempdescr = trim(strip_tags($pdata->description));
            block_exaport_get_user_preferences()->description;
            if ($tempdescr) {
                $userid = $pdata->user_id;
                $coverData = [];
                // get Resume data for the user
                $description = @$pdata->description ?: '';
                if ($description) {
                    $description = rtrim($description, '<br>');
                    $coverData[] = $description;
                }
                $resumedata = $block_exaport_get_resume_params_record($userid);
                if ($resumedata !== false) {
                    $cover = $resumedata->cover ?: '';
                    if (trim(strip_tags($resumedata->cover))) {
                        $coverData[] = $cover;
                    }
                    $newcover = implode('<br>', $coverData);
                    $block_exaport_set_resume_params($userid, array('cover' => $newcover));
                    // remove info from personal information
                    $block_exaport_set_user_preferences($userid, array('description' => ''));
                }
            }
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2024030400, 'exaport');
    }

    if ($oldversion < 2024032100) {
        $table = new xmldb_table('block_exaportitem');

        $field = new xmldb_field('project_description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'intro');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('project_process', XMLDB_TYPE_TEXT, null, null, null, null, null, 'project_description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('project_result', XMLDB_TYPE_TEXT, null, null, null, null, null, 'project_process');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2024032100, 'exaport');

    }

    if ($oldversion < 2024070300) {
        $table = new xmldb_table('block_exaportcate');

        $field = new xmldb_field('iconmerge', XMLDB_TYPE_INTEGER, '1', true, null, null, 0, 'structure_share');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2024070300, 'exaport');

    }

    if ($oldversion < 2024070301) {
        // Update a field for the table block_exaportresume
        $table = new xmldb_table('block_exaportresume');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2024070301, 'exaport');
    }

    if ($oldversion < 2024102200) {

        // Define field createdinapp to be added to block_exaportview.
        $table = new xmldb_table('block_exaportview');
        $field = new xmldb_field('createdinapp', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field createdinapp.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Exaport savepoint reached.
        upgrade_block_savepoint(true, 2024102200, 'exaport');
    }

    return $result;
}
