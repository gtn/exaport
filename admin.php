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

require_once(__DIR__ . '/inc.php');

require_login(0, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = '/blocks/exaport/admin.php';
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$backurl = $CFG->wwwroot . '/admin/settings.php?section=blocksettingexaport';
$action = required_param('action', PARAM_TEXT);

switch ($action) {
    case 'remove_shareall':
        if (optional_param('confirm', 0, PARAM_INT)) {
            require_sesskey();
            $sql = "UPDATE {block_exaportview} SET shareall=0";
            $DB->execute($sql);

            redirect($backurl);
            exit;
        }

        block_exaport_print_header("myportfolio");

        echo '<br />';
        echo $OUTPUT->confirm(block_exaport_get_string("delete_all_shareall"),
            new moodle_url('admin.php', array('action' => $action, 'confirm' => 1, 'sesskey' => sesskey())),
            $backurl);
        echo block_exaport_wrapperdivend();
        $OUTPUT->footer();

        exit;
        break;
    case 'create_trustedteacherproperty':
        require_sesskey();
        // Add new user profile field (checkbox): blockexaporttrustedteacher
        // for using together with exaport settings parameter: block_exaport_teachercanseeartifactsofstudents
        // checked user will be able to see all artifacts of own students.
        $shortfieldname = 'blockexaporttrustedteacher';
        if (!$DB->get_record('user_info_field', array('shortname' => $shortfieldname))) {
            if (optional_param('confirm', 0, PARAM_INT)) {
                $sql = "INSERT INTO {user_info_field} (shortname, name, datatype, description, descriptionformat, categoryid,
                      sortorder, required, locked, visible, forceunique, signup, defaultdata, defaultdataformat,
                      param1, param2, param3, param4, param5)
                 VALUES ('" . $shortfieldname . "', 'This teacher is trusted for viewing of all artifacts of own students', 'checkbox',
                    '<p>This teacher can see all artifacts of own students.</p><p>Use this option with care!</p>" .
                    "<p>Has sence only if the parameter \"block_exaport_teachercanseeartifactsofstudents\" is enabled " .
                    "in block_exaport settings</p>',
                    1, 1, 1, 0, 1, 0, 0, 0, '0', 0, null, null, null, null, null)";
                $DB->execute($sql);
                // Set config for auth plugins (TODO: other plugins?).
                $authplugins = array(
                    'auth_cas',
                    'auth_db',
                    'auth_ldap',
                    'auth_shibboleth');
                $options = array(
                    'field_map_profile_field_' => '',
                    'field_updatelocal_profile_field_' => 'oncreate',
                    'field_updateremote_profile_field_' => '0',
                    'field_lock_profile_field_' => 'locked');
                foreach ($authplugins as $plugin) {
                    foreach ($options as $optionname => $optionvalue) {
                        set_config($optionname . $shortfieldname, $optionvalue, $plugin);
                    }
                }
                redirect($backurl, block_exaport_get_string("block_exaport_profilefield_created"));
            }
            echo $OUTPUT->header();
            echo block_exaport_wrapperdivstart();
            echo $OUTPUT->confirm(block_exaport_get_string("block_exaport_confirm_profilefield_create"),
                new moodle_url('admin.php', array('action' => $action, 'confirm' => 1, 'sesskey' => sesskey())),
                $backurl);
        } else {
            echo $OUTPUT->header();
            echo block_exaport_wrapperdivstart();
            echo $OUTPUT->notification(block_exaport_get_string("block_exaport_confirm_profilefield_exists"),
                \core\output\notification::NOTIFY_SUCCESS);
            $backbutton = new single_button(new moodle_url($backurl), get_string('back'), 'post', true);
            echo $OUTPUT->render($backbutton);

        }
        echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer();
        exit;
        break;
}


die('error');
