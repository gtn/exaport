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
require_once(__DIR__ . '/blockmediafunc.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$confirm = optional_param("confirm", "", PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);
$type = optional_param('type', 'content', PARAM_ALPHA);
if ($action == "add") {
    $type = "title";
}

$url = '/blocks/exaport/views_mod.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

if (!$COURSE) {
    print_error("invalidcourseid", "block_exaport");
}

block_exaport_add_iconpack(true);

// Include JS script.
$PAGE->requires->js_call_amd('block_exaport/views', 'initialise');
// $config = ['paths' => ['block_exaport/popover' => $CFG->wwwroot.'/blocks/exaport/javascript/popover.min']];
// $requirejs = 'require.config(' . json_encode($config) . ')';
// $PAGE->requires->js_amd_inline($requirejs);
$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/blocks/exaport/javascript/popover.min.js'), false);
$PAGE->requires->js('/blocks/exaport/javascript/popper.min.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/tippy-bundle.umd.js', true);
// $PAGE->requires->js_call_amd('block_exaport/config', 'initialise');
$PAGE->requires->css('/blocks/exaport/css/preloadinator.css', true);

if ($id) {
    $conditions = array("id" => $id, "userid" => $USER->id);
    if (!$view = $DB->get_record('block_exaportview', $conditions)) {
        print_error("wrongviewid", "block_exaport");
    }
} else {
    $view = new stdClass();
    $view->id = null;
}

if ($view && $action == 'userlist') {
    require_sesskey();

    echo json_encode(exaport_get_shareable_courses_with_users_for_view($view->id));
    exit;
}

if ($view && $action == 'grouplist') {
    require_sesskey();

    $sharedgroups = exaport_get_view_shared_groups($view->id);

    $groupgroups = block_exaport_get_shareable_groups_for_json();
    foreach ($groupgroups as $groupgroup) {
        foreach ($groupgroup->groups as $group) {
            $group->shared_to = isset($sharedgroups[$group->id]);
        }
    }
    echo json_encode($groupgroups);
    exit;
}

$returnurltolist = $CFG->wwwroot . '/blocks/exaport/views_list.php?courseid=' . $courseid;
$returnurl = $CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $courseid . '&id=' . $id .  '&action=edit';

// Delete item.
if ($action == 'delete') {
    require_sesskey();

    if (!$view) {
        print_error("viewnotfound", "block_exaport");
    }
    if (data_submitted() && $confirm && confirm_sesskey()) {
        $conditions = array("viewid" => $view->id);
        $DB->delete_records('block_exaportviewblock', $conditions);
        $conditions = array("id" => $view->id);
        $status = $DB->delete_records('block_exaportview', $conditions);

        block_exaport_add_to_log(SITEID, 'blog', 'delete',
            'views_mod.php?courseid=' . $courseid . '&id=' . $view->id . '&action=delete&confirm=1', $view->name);

        if (!$status) {
            print_error('deleteposterror', 'block_exaport', $returnurl);
        }
        redirect($returnurltolist);
    } else {
        $optionsyes = array('id' => $id, 'action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey(), 'courseid' => $courseid);
        $optionsno = array('courseid' => $courseid);

        block_exaport_print_header('views');
        echo '<br />';
        echo $OUTPUT->confirm(get_string("deletecheck", null, $view->name), new moodle_url('views_mod.php', $optionsyes),
            new moodle_url('views_list.php', $optionsno));
        echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer();
        die;
    }
}

if ($view) {
    $hassharedusers = !!$DB->count_records('block_exaportviewshar', array("viewid" => $view->id));
} else {
    $hassharedusers = false;
}

require_once($CFG->libdir . '/formslib.php');

class block_exaport_view_edit_form extends block_exaport_moodleform {

    public function definition() {
        global $CFG, $USER, $DB;
        $mform =& $this->_form;
        $mform->updateAttributes(array('class' => '', 'id' => 'view_edit_form'));

        $mform->setType('items', PARAM_RAW);
        $mform->setType('draft_itemid', PARAM_TEXT);
        $mform->setType('action', PARAM_TEXT);
        $mform->setType('courseid', PARAM_INT);
        $mform->setType('viewid', PARAM_INT);
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('autofill_artefacts', PARAM_TEXT);
        $mform->addElement('hidden', 'items');
        $mform->addElement('hidden', 'draft_itemid');
        $mform->addElement('hidden', 'action');
        $mform->addElement('hidden', 'courseid');
        $mform->addElement('hidden', 'viewid');
        $mform->addElement('hidden', 'autofill_artefacts');
        if (optional_param('type', 'content', PARAM_ALPHA) <> 'title' and optional_param("action", "", PARAM_ALPHA) <> 'add') {
            $mform->addElement('hidden', 'name');
        }

        switch ($this->_customdata['type']) {
            case "title":
                $mform->addElement('text', 'name', get_string("title", "block_exaport"), 'maxlength="255" size="60"');
                $mform->setType('name', PARAM_TEXT);
                $mform->addRule('name', get_string("titlenotemtpy", "block_exaport"), 'required', null, 'client');
                $mform->add_exaport_help_button('name', 'forms.view.name');

                $mform->addElement('editor', 'description_editor', get_string('viewdescription', 'block_exaport'),
                    array('rows' => '20', 'cols' => '5'),
                    array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
                $mform->setType('description', PARAM_RAW);
                $mform->add_exaport_help_button('description_editor', 'forms.view.description_editor');

                if ($this->_customdata['view']) {
                    // Auto generate view with the artefacts checkbox.
                    $artefacts = block_exaport_get_portfolio_items(1, null, true, true);
                    if (count($artefacts) > 0) {
                        if ($this->_customdata['view']->id > 0) {
                            foreach ($artefacts as $artefact) {
                                $allartefacts[] = $artefact->id;
                            }
                            $filledartefacts = explode(',', $this->_customdata['view']->autofill_artefacts);
                            sort($filledartefacts);
                            sort($allartefacts);
                            $diff = array_diff($allartefacts, $filledartefacts);
                            if (count($diff) > 0) {
                                $mform->addElement('checkbox', 'autofill_add', '', get_string('autofillview', 'block_exaport'));
                                $mform->add_exaport_help_button('autofill_add', 'forms.view.autofill_add');
                            }
                        } else {
                            $mform->addElement('checkbox', 'autofill', '', get_string('autofillview', 'block_exaport'));
                            $mform->add_exaport_help_button('autofill', 'forms.view.autofill');
                        }
                    }
                    // Share to cheacher checkbox.
                    $allteachers = block_exaport_get_course_teachers();
                    // If view is editing.
                    if ($this->_customdata['view']->id > 0) {
                        $allsharedusers = block_exaport_get_shared_users($this->_customdata['view']->id);
                        $diff = array_diff($allteachers, $allsharedusers);
                        // If there is teacher which does not share.
                        if ((count($allteachers) > 0) && (count($diff) > 0)) {
                            $mform->addElement('checkbox', 'sharetoteacher', '', get_string('sharetoteacher', 'block_exaport'));
                            $mform->add_exaport_help_button('sharetoteacher', 'forms.view.sharetoteacher');
                        }
                    } else { // If view is adding.
                        $mform->addElement('checkbox', 'sharetoteacher', '', get_string('sharetoteacher', 'block_exaport'));
                        $mform->add_exaport_help_button('sharetoteacher', 'forms.view.sharetoteacher');
                    }
                }

                if (block_exaport_course_has_desp()) {
                    $langcode = get_string("langcode", "block_desp");
                    $sql = "SELECT lang.id,lang." . $langcode . " as name " .
                        " FROM {block_desp_lang} lang " .
                        " WHERE id IN(" .
                        " SELECT langid FROM {block_desp_check_lang} WHERE userid=?)" .
                        " OR id IN (SELECT langid FROM {block_desp_lanhistories} WHERE userid=?) " .
                        " ORDER BY lang." .
                        $langcode;
                    $languages = $DB->get_records_sql_menu($sql, array($USER->id, $USER->id));
                    $languages[0] = '';
                    asort($languages);
                    $mform->addElement('select', 'langid', get_string("desp_language", "block_exaport"), $languages);
                    $mform->setType('langid', PARAM_INT);
                    $mform->add_exaport_help_button('langid', 'forms.view.langid');
                }
                break;
            case "layout":
                $radioarray = array();
                for ($i = 1; $i <= 10; $i++) {
                    $radioarray[] = $mform->createElement('radio', 'layout', '', '', $i);
                }
                $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
                break;
            case "content" :
                $mform->addElement('hidden', 'blocks');
                $mform->setType('blocks', PARAM_RAW);
                $mform->addElement('hidden', 'resume');
                $mform->setType('resume', PARAM_RAW);
                break;
            case "share" :
                if (block_exaport_externaccess_enabled()) {
                    $mform->addElement('checkbox', 'externaccess');
                    $mform->setType('externaccess', PARAM_INT);
                    $mform->add_exaport_help_button('externaccess', 'forms.view.externaccess');
                }

                $mform->addElement('checkbox', 'internaccess');
                $mform->setType('internaccess', PARAM_INT);
                $mform->add_exaport_help_button('internaccess', 'forms.view.internaccess');

                $mform->addElement('checkbox', 'externcomment');
                $mform->setType('externcomment', PARAM_INT);
                $mform->add_exaport_help_button('externcomment', 'forms.view.externcomment');

                if (block_exaport_shareall_enabled()) {
                    $mform->addElement('text', 'shareall');
                    $mform->setType('shareall', PARAM_INT);
                    $mform->add_exaport_help_button('shareall', 'forms.view.shareall');
                }

                if (block_exaport_shareemails_enabled()) {
                    $mform->addElement('checkbox', 'sharedemails');
                    $mform->setType('sharedemails', PARAM_INT);
                    $mform->add_exaport_help_button('sharedemails', 'forms.view.sharedemails');
                }

                break;
            default:
                break;
        }
        if ($this->_customdata['view']) {
            $this->add_action_buttons(false, get_string('savechanges'));
        } else {
            $this->add_action_buttons(false, get_string('add'));
        }
    }

    public function add_action_buttons($cancel = true, $submitlabel = null) {
        if (is_null($submitlabel)) {
            $submitlabel = get_string('savechanges');
        }
        $mform =& $this->_form;
        if ($cancel) {
            // When two elements we need a group
            $buttonarray = array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
            $buttonarray[] = &$mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            // No group needed
            $mform->addElement('submit', 'submitbutton', $submitlabel, ['class' => 'btn btn-primary']);
            $mform->closeHeaderBefore('submitbutton');
        }
    }

    public function to_array() {
        // Finalize the form definition if not yet done.
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        $form = $this->_form->toArray();

        $form['html_hidden_fields'] = '';
        $form['elements_by_name'] = array();

        foreach ($form['elements'] as $element) {
            if ($element['type'] == 'hidden') {
                $form['html_hidden_fields'] .= $element['html'];
            }
            $form['elements_by_name'][$element['name']] = $element;
        }

        return $form;
    }
}

$textfieldoptions = array('trusttext' => true,
    'subdirs' => true,
    'maxfiles' => 99,
    'context' => context_user::instance($USER->id)->id);

$editform = new block_exaport_view_edit_form($_SERVER['REQUEST_URI'],
    array('view' => $view, 'course' => $COURSE->id, 'action' => $action, 'type' => $type));

$message = '';

if ($editform->is_cancelled()) {
    redirect($returnurltolist);
} else if ($editform->no_submit_button_pressed()) {
    die("nosubmitbutton");
} else if ($formview = $editform->get_data()) {
    if ($type == 'title' or $action == 'add') {
        $formview = file_postupdate_standard_editor($formview, 'description', $textfieldoptions, context_user::instance($USER->id),
            'block_exaport', 'view', $view->id);
    }

    $dbview = $formview;
    $dbview->timemodified = time();
    if (!$view || !isset($view->hash)) {
        // Generate view hash.
        do {
            $hash = substr(md5(microtime()), 3, 8);
        } while ($DB->record_exists("block_exaportview", array("hash" => $hash)));
        $dbview->hash = $hash;
    }

    if ($type == 'share') {
        if (!block_exaport_externaccess_enabled() || empty($dbview->externaccess)) {
            $dbview->externaccess = 0;
        }
        if (empty($dbview->internaccess)) {
            $dbview->internaccess = 0;
        }
        if (!block_exaport_shareall_enabled() || !$dbview->internaccess || empty($dbview->shareall)) {
            $dbview->shareall = 0;
        }
        if (empty($dbview->externcomment)) {
            $dbview->externcomment = 0;
        }
        if (!block_exaport_shareemails_enabled() || empty($dbview->sharedemails)) {
            $dbview->sharedemails = 0;
        }
    }

    switch ($action) {
        case 'add':

            $dbview->userid = $USER->id;
            if (empty($dbview->layout) || $dbview->layout == 0) {
                $dbview->layout = 2;
            }
            if ($dbview->id = $DB->insert_record('block_exaportview', $dbview)) {
                // Auto fill with the artefacts.
                if (isset($dbview->autofill) and $dbview->autofill == 1) {
                    $filledartefacts = fill_view_with_artefacts($dbview->id);
                    $dbview->autofill_artefacts = $filledartefacts;
                    $DB->update_record('block_exaportview', $dbview);
                }
                // Auto Share to the teachers.
                if (isset($dbview->sharetoteacher) and $dbview->sharetoteacher == 1) {
                    block_exaport_share_view_to_teachers($dbview->id);
                }
                block_exaport_add_to_log(SITEID, 'bookmark', 'add',
                    'views_mod.php?courseid=' . $courseid . '&id=' . $dbview->id . '&action=add', $dbview->name);
            } else {
                print_error('addposterror', 'block_exaport', $returnurl);
            }
            break;

        case 'edit':
            if (!$view) {
                print_error("viewnotfound", "block_exaport");
            }

            $dbview->id = $view->id;
            if (empty($dbview->layout) || $dbview->layout == 0) {
                if (empty($view->layout) || $view->layout == 0) {
                    $dbview->layout = 2;
                } else {
                    $dbview->layout = $view->layout;
                }
            }
            // Add new artefacts if selected.
            if (isset($dbview->autofill_add) and $dbview->autofill_add == 1) {
                $filledartefacts = fill_view_with_artefacts($dbview->id, $dbview->autofill_artefacts);
                $dbview->autofill_artefacts = $filledartefacts;
            }
            // Auto Share to the teachers.
            if (isset($dbview->sharetoteacher) and $dbview->sharetoteacher == 1) {
                block_exaport_share_view_to_teachers($dbview->id);
            }
            if ($DB->update_record('block_exaportview', $dbview)) {
                block_exaport_add_to_log(SITEID, 'bookmark', 'update',
                    'item.php?courseid=' . $courseid . '&id=' . $dbview->id . '&action=edit', $dbview->name);
            } else {
                print_error('updateposterror', 'block_exaport', $returnurl);
            }

            break;

        default:
            print_error("unknownaction", "block_exaport");
            exit;
    }

    // Processing for blocks and shares.
    switch ($type) {
        case 'content':
            // Delete all blocks only if all ok with possible blocks preparing
            $torewriteblocks = false;

            try {
                // Add blocks.
                $blocks = file_save_draft_area_files(required_param('draft_itemid', PARAM_INT), context_user::instance($USER->id)->id,
                    'block_exaport', 'view_content', $view->id,
                    array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_user::instance($USER->id),
                        'maxbytes' => $CFG->block_exaport_max_uploadfile_size),
                    $formview->blocks);
                $blocks = json_decode($blocks) ?: [];

                foreach ($blocks as $block) {
                    $block->viewid = $dbview->id;

                    // clean block title. We need only clean text. Right?
                    if ($block->block_title) {
                        $block->block_title = htmlspecialchars(strip_tags($block->block_title));
                    }

                    // Media process.
                    if (!empty($block->type) && $block->type == 'media') {
                        if (!empty($block->contentmedia)) {
                            if (empty($block->width)) {
                                $block->width = 360;
                            } else {
                                $block->width = (int)$block->width;
                            }
                            if (empty($block->height)) {
                                $block->height = 240;
                            } else {
                                $block->height = (int)$block->height;
                            }
                            $block->contentmedia = process_media_url($block->contentmedia, $block->width, $block->height);
                        }

                        if (!empty($block->create_as_note)) {
                            $newitem = new stdClass;
                            $newitem->name = $block->block_title;
                            $newitem->type = 'note';
                            $newitem->categoryid = 0;
                            $newitem->userid = $USER->id;
                            $newitem->intro = $block->contentmedia;
                            $newitem->timemodified = time();

                            $block->itemid = $DB->insert_record('block_exaportitem', $newitem);
                            $block->type = 'item';
                            $block->block_title = '';
                            $block->contentmedia = '';
                            $block->width = 0;
                            $block->height = 0;
                        }
                    }

                }
            } catch (moodle_exception $e) {
                $message = block_exaport_get_string('Something wrong with blocks saving (code: 1694089814164)');
                break;
            }
            $torewriteblocks = true; // All ok.
            // Rewrite all blocks
            if ($torewriteblocks) {
                $DB->delete_records('block_exaportviewblock', array('viewid' => $dbview->id));
                foreach ($blocks as $block) {
                    $block->id = $DB->insert_record('block_exaportviewblock', $block);
                }
            }

            if (optional_param('ajax', 0, PARAM_INT)) {
                $ret = new stdClass;
                $ret->ok = true;
                file_prepare_draft_area($view->draft_itemid, context_user::instance($USER->id)->id, 'block_exaport', 'view_content',
                    $view->id, array('subdirs' => true, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size), null);
                $ret->blocks = json_encode(block_exaport_get_view_blocks($view));

                echo json_encode($ret);
                exit;
            }

            $message = block_exaport_get_string('view_saved');

            break;
        case 'share':
            // Delete all shared users.
            $DB->delete_records("block_exaportviewshar", array('viewid' => $dbview->id));
            // Add new shared users.
            if ($dbview->internaccess && !$dbview->shareall) {
                $shareusers = \block_exaport\param::optional_array('shareusers', PARAM_INT);

                foreach ($shareusers as $shareuser) {
                    $shareuser = clean_param($shareuser, PARAM_INT);
                    $shareitem = new stdClass();
                    $shareitem->viewid = $dbview->id;
                    $shareitem->userid = $shareuser;
                    $DB->insert_record("block_exaportviewshar", $shareitem);
                }
                // Message users, if they have shared.
                $notifyusers = optional_param_array('notifyusers', array(), PARAM_RAW);
                if (count($notifyusers) > 0) {
                    foreach ($notifyusers as $notifyuser) {
                        // Only notify if he also is shared.
                        if (isset($shareusers[$notifyuser])) {
                            // Notify.
                            $notificationdata = new \core\message\message();
                            $notificationdata->component = 'block_exaport';
                            $notificationdata->name = 'sharing';
                            $notificationdata->userfrom = $USER;
                            $notificationdata->userto = $DB->get_record('user', array('id' => $notifyuser));
                            // TODO: subject + message text.
                            $notificationdata->subject = get_string('i_shared', 'block_exaport');
                            $notificationdata->fullmessage = $CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=1&' .
                                'access=id/' . $USER->id . '-' . $dbview->id;
                            $notificationdata->fullmessageformat = FORMAT_PLAIN;
                            $notificationdata->fullmessagehtml = '';
                            $notificationdata->smallmessage = '';
                            $notificationdata->notification = 1;

                            $mailresult = message_send($notificationdata);
                        }
                    }
                }
            }

            // Delete all shared groups.
            $DB->delete_records("block_exaportviewgroupshar", array('viewid' => $dbview->id));
            // Add new groups sharing. shareall == 0 - users sharing; 1 - share for all; 2 - groups sharing.
            if ($dbview->internaccess && $dbview->shareall == 2) {
                $sharegroups = \block_exaport\param::optional_array('sharegroups', PARAM_INT);
                $usergroups = block_exaport_get_user_cohorts();

                foreach ($sharegroups as $groupid) {
                    if (!isset($usergroups[$groupid])) {
                        // Not allowed.
                        continue;
                    }
                    $DB->insert_record("block_exaportviewgroupshar", [
                        'viewid' => $dbview->id,
                        'groupid' => $groupid,
                    ]);
                }
            }

            if (optional_param('share_to_other_users_submit', '', PARAM_RAW)) {
                // Search button pressed -> redirect to search form.
                redirect(new moodle_url('/blocks/exaport/views_mod_share_user_search.php',
                    array('courseid' => $courseid, 'id' => $dbview->id,
                        'q' => optional_param('share_to_other_users_q', '', PARAM_RAW))));
                exit;
            }

            if ($dbview->sharedemails) {
                $newemails = optional_param('emailsforshare', '', PARAM_RAW);
                $newemails = preg_split('/(;|,|\s)/', $newemails);
                $newemails = array_map('trim', $newemails);
                $newemails = array_filter($newemails);
                $newemails = array_unique($newemails);
                if (count($newemails) > 0) {
                    $oldemails = array_values(exaport_get_view_shared_emails($view->id));
                    if ($oldemails !== $newemails) {
                        // Get old hashes for keep them in the database.
                        $oldemailshares = $DB->get_records_menu('block_exaportviewemailshar', array('viewid' => $view->id), '',
                            'email, hash');
                        // Delete all shares for this view.
                        $DB->delete_records('block_exaportviewemailshar', array('viewid' => $view->id));
                        $insertdata = new stdClass;
                        $insertdata->viewid = $view->id;
                        $hashesforemails = array();
                        foreach ($newemails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $insertdata->email = $email;
                                if (!in_array($email, $oldemails)) {
                                    // Insert new share.
                                    $insertdata->hash = block_exaport_securephrase_viewemail($view, $email);
                                } else {
                                    // Insert share with old hash.
                                    $insertdata->hash = $oldemailshares[$email];
                                }
                                $DB->insert_record('block_exaportviewemailshar', $insertdata);
                                $hashesforemails[$email] = $insertdata->hash;
                            }
                        }
                        // Send messages.
                        block_exaport_emailaccess_sendemails($view, $oldemails, $newemails, $hashesforemails);
                    }
                }
            }
            $message = block_exaport_get_string('view_sharing_updated');
            break;
        case 'layout':
            // Save additional layout settings
            $customLayoutSettings = optional_param_array('layoutSettings', [], PARAM_RAW);
            // remove urls from custom CSS:
            if (@$customLayoutSettings['customCss']) {
                $customLayoutSettings['customCss'] = preg_replace('/url\s*\((.*?)\)|@import\s*(.*?);/i', 'url(#)', $customLayoutSettings['customCss']);
            }
            $customLayoutSettings_serialized = serialize($customLayoutSettings);

            // Save layout settings into DB
            $view->layout_settings = $customLayoutSettings_serialized;
            $DB->update_record('block_exaportview', $view);
            break;
        default:
            break;
    }

    $returnurl = $CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $courseid . '&id=' . $dbview->id . '&action=edit';

    redirect($returnurl, $message);
}

// Gui setup.
$postview = ($view ? $view : new stdClass());
$postview->action = $action;
$postview->courseid = $courseid;
$postview->draft_itemid = null;

file_prepare_draft_area($postview->draft_itemid, context_user::instance($USER->id)->id, 'block_exaport', 'view_content', $view->id,
    array('subdirs' => true, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size), null);

// We need to copy additional files from the personal information to the views editor,
// just in case if the personal information is added.
copy_personal_information_draft_files($postview->draft_itemid, context_user::instance($USER->id)->id, 'block_exaport',
    'personal_information', $USER->id, array('subdirs' => true), null);
function copy_personal_information_draft_files($targetdraftitemid, $contextid, $component, $filearea, $itemid,
    array $options = null, $text = null) {
    global $USER;

    // Copy from filelib.php.
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();

    $filerecord = array('contextid' => $usercontext->id, 'component' => 'user',
        'filearea' => 'draft', 'itemid' => $targetdraftitemid);
    if (!is_null($itemid) and $files = $fs->get_area_files($contextid, $component, $filearea, $itemid)) {
        foreach ($files as $file) {
            if ($file->is_directory() and $file->get_filepath() === '/') {
                // We need a way to mark the age of each draft area,
                // by not copying the root dir we force it to be created automatically with current timestamp.
                continue;
            }
            if (!$options['subdirs'] and ($file->is_directory() or $file->get_filepath() !== '/')) {
                continue;
            }

            if ($tmp = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                $filerecord['itemid'], $file->get_filepath(), $file->get_filename())) {
                continue;
            }

            $draftfile = $fs->create_file_from_storedfile($filerecord, $file);
            // XXX: This is a hack for file manager (MDL-28666)
            // File manager needs to know the original file information before copying
            // to draft area, so we append these information in mdl_files.source field
            // {@link file_storage::search_references()}
            // {@link file_storage::search_references_count()}
            $sourcefield = $file->get_source();
            $newsourcefield = new stdClass;
            $newsourcefield->source = $sourcefield;
            $original = new stdClass;
            $original->contextid = $contextid;
            $original->component = $component;
            $original->filearea = $filearea;
            $original->itemid = $itemid;
            $original->filename = $file->get_filename();
            $original->filepath = $file->get_filepath();
            $newsourcefield->original = file_storage::pack_reference($original);
            $draftfile->set_source(serialize($newsourcefield));
            // End of file manager hack.
        }
    }
}

$postview->viewid = $view->id;

switch ($action) {
    case 'add':
        $postview->internaccess = 0;
        $postview->shareall = 0;

        $straction = get_string('new');
        break;
    case 'edit':
        if (!isset($postview->internaccess) && ($postview->shareall || $hassharedusers)) {
            $postview->internaccess = 1;
        }
        $straction = get_string('edit');
        break;
    default :
        print_error("unknownaction", "block_exaport");
}

if ($view) {
    $postview->blocks = json_encode(block_exaport_get_view_blocks($view));
    require_once(__DIR__ . '/lib/resumelib.php');
    $resumedata = block_exaport_get_resume_params($USER->id, true);
    $resumedata->cover = file_rewrite_pluginfile_urls($resumedata->cover, 'pluginfile.php',
        context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_cover', $resumedata->id);
    $postview->resume = json_encode($resumedata);
}

$tinylibpath = $CFG->libdir . '/editor/tinymce/lib.php';
if (file_exists($tinylibpath)) {
    require_once($tinylibpath);
    $tinymce = new tinymce_texteditor();
} else {
    // for Moodle 4.2 >
    $tinylibpath = $CFG->libdir . '/editor/tiny/lib.php';
    require_once($tinylibpath);
    $tinymce = new tiny_texteditor();
}

$PAGE->requires->css('/blocks/exaport/css/blocks.css');

block_exaport_print_header('views', $type);

$editform->set_data($postview);
if ($type <> 'title') {// For delete php notes.
    $form = $editform->to_array();
    echo $form['javascript'];
    echo '<form' . $form['attributes'] . '><div id="exaport-view-mod">';
    echo $form['html_hidden_fields'];
}

// Translations.
$translations = array(
    'name', 'role', 'nousersfound',
    'internalaccessgroups', 'grouptitle', 'membercount', 'nogroupsfound',
    'view_specialitem_headline', 'view_specialitem_headline_defaulttext', 'view_specialitem_text', 'view_specialitem_media',
    'view_specialitem_badge', 'view_specialitem_text_defaulttext',
    'viewitem', 'comments', 'category', 'link', 'type', 'personalinformation',
    'cvinformation', 'cvgroup', 'cofigureblock_cvinfo_education_history', 'cofigureblock_cvinfo_employment_history',
    'cofigureblock_cvinfo_certif', 'cofigureblock_cvinfo_public', 'cofigureblock_cvinfo_mbrship',
    'cofigureblock_cvinfo_goals', 'cofigureblock_cvinfo_skills', 'cofigureblock_cvinfo_interests', 'cofigureblock_cvinfo_cover',
    'resume_goalspersonal', 'resume_goalsacademic', 'resume_goalscareers',
    'resume_skillspersonal', 'resume_skillsacademic', 'resume_skillscareers',
    'delete', 'viewand',
    'file', 'note', 'link',
    'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs',
    'notify', 'emailaccess',
    'checkall',
);

$translations = array_flip($translations);
foreach ($translations as $key => &$value) {
    $value = block_exaport_get_string($key);
}
unset($value);

$portfolioitems = array();
if ($view->id) {
    $portfolioitems = block_exaport_get_portfolio_items();
    // Add potential sometime shared items (there is in {block_exaportviewblock} but now is NOT shared).
    $addwhere = '';
    if (isset($portfolioitems) && count($portfolioitems) > 0) {
        $addwhere .= ' AND i.id NOT IN (' . implode(',', array_keys($portfolioitems)) . ') ';
    }
    $query = " SELECT b.* " .
        " FROM {block_exaportviewblock} b, {block_exaportitem} i" .
        " WHERE b.viewid = ? AND b.itemid = i.id AND i.userid <> ? " .
        $addwhere .
        " ORDER BY b.positionx, b.positiony ";
    $allpotentialitems = $DB->get_records_sql($query, array($view->id, $USER->id));
    $portfolioshareditems = array();
    $allpotentialitemsids = array();
    foreach ($allpotentialitems as $item) {
        $allpotentialitemsids[] = $item->itemid;
        // if (!array_key_exists($item->itemid, $portfolioitems)) {
        // $portfolioitems = $portfolioitems + block_exaport_get_portfolio_items(0, $item->itemid);
        // }
    }
    if (count($allpotentialitemsids) > 0) {
        $portfolioitems = $portfolioitems + block_exaport_get_portfolio_items(0, $allpotentialitemsids);
    }
}

// add resume items
$resumeitems = block_exaport_get_resume_params($USER->id, true);
// convert editors images:
// cover
$resumeitems->cover = file_rewrite_pluginfile_urls(@$resumeitems->cover, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_cover', @$resumeitems->id);
$resumeitems->cover = block_exaport_add_view_access_parameter_to_url(@$resumeitems->cover, $view, ['src']);
// goals
$resumeitems->goalspersonal = file_rewrite_pluginfile_urls(@$resumeitems->goalspersonal, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_goalspersonal', @$resumeitems->id);
$resumeitems->goalspersonal = block_exaport_add_view_access_parameter_to_url(@$resumeitems->goalspersonal, $view, ['src']);
$resumeitems->goalsacademic = file_rewrite_pluginfile_urls(@$resumeitems->goalsacademic, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_goalsacademic', @$resumeitems->id);
$resumeitems->goalsacademic = block_exaport_add_view_access_parameter_to_url(@$resumeitems->goalsacademic, $view, ['src']);
$resumeitems->goalscareers = file_rewrite_pluginfile_urls(@$resumeitems->goalscareers, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_goalscareers', @$resumeitems->id);
$resumeitems->goalscareers = block_exaport_add_view_access_parameter_to_url(@$resumeitems->goalscareers, $view, ['src']);
// skills
$resumeitems->skillspersonal = file_rewrite_pluginfile_urls(@$resumeitems->skillspersonal, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_skillspersonal', @$resumeitems->id);
$resumeitems->skillspersonal = block_exaport_add_view_access_parameter_to_url(@$resumeitems->skillspersonal, $view, ['src']);
$resumeitems->skillsacademic = file_rewrite_pluginfile_urls(@$resumeitems->skillsacademic, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_skillsacademic', @$resumeitems->id);
$resumeitems->skillsacademic = block_exaport_add_view_access_parameter_to_url(@$resumeitems->skillsacademic, $view, ['src']);
$resumeitems->skillscareers = file_rewrite_pluginfile_urls(@$resumeitems->skillscareers, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_skillscareers', @$resumeitems->id);
$resumeitems->skillscareers = block_exaport_add_view_access_parameter_to_url(@$resumeitems->skillscareers, $view, ['src']);
// interests
$resumeitems->interests = file_rewrite_pluginfile_urls(@$resumeitems->interests, 'pluginfile.php',
    context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_interests', @$resumeitems->id);
$resumeitems->interests = block_exaport_add_view_access_parameter_to_url(@$resumeitems->interests, $view, ['src']);
?>
    <script type="text/javascript">

        var portfolioItems = <?php echo json_encode($portfolioitems); ?>;
        var resumeItems = <?php echo json_encode($resumeitems); ?>;
        ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);

    </script>
<?php

$rev = theme_get_revision();
echo "<!--[if IE]> <style> #link_thumbnail{ zoom: 0.2; } </style> <![endif]--> ";
switch ($type) {
    case 'content' :
        ?>
        <script type="text/javascript">
            //<![CDATA[
            M.yui.add_module({
                "editor_tinymce": {
                    "name": "editor_tinymce",
                    "fullpath": "<?php echo $CFG->wwwroot;?>/lib/javascript.php/<?php echo $rev;?>/lib/editor/tinymce/module.js",
                    "requires": []
                }
            });
            //]]>
        </script>
        <script type="text/javascript"
                src="<?php echo $CFG->wwwroot; ?>/lib/editor/tinymce/tiny_mce/<?php echo $tinymce->version; ?>/tiny_mce.js"></script>

        <?php
        echo '<div class="view-additional-help">';
        echo text_to_html(get_string('createpage', 'block_exaport'));
        // help information
        echo '<a href="#more_viewcontent_info" data-toggle="showmore" class="view-additional-help">' . get_string('moreinfolink', 'block_exaport') . '</a>';
        echo '<div id="more_viewcontent_info" style="display: none;">' . get_string('create_view_content_help_text', 'block_exaport') . '</div>';
        // help popup (changed to info by "show more info" text)
        /*echo '
            &nbsp;&nbsp;<a class="" data-modal="alert" data-modal-title-str=\'["create_view_content_help_title", "block_exaport"]\'
data-modal-content-str=\'["create_view_content_help_text", "block_exaport"]\' href="#"><img src="'.$OUTPUT->image_url('help', 'block_exaport').'" class="icon" alt="" /></a>
        ';*/
        echo '</div>';
        // View data form.
        echo '<div id="blocktype-list">';

        // Preview button.
        echo '<div style="float: right;">
            <a target="_blank"
                title="' . block_exaport_get_string('view_preview_help_title') . '"
                id="preview_link"
                data-help="' . block_exaport_get_string('view_preview_help') . '"
                href="' . s($CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $courseid . '&access=id/' . $USER->id . '-' . $view->id) . '">'
            . block_exaport_fontawesome_icon('eye', 'regular', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-view-icon'])
            . '</a></div>';
        $profileurl = new moodle_url('/user/profile.php');
        $cvurl = new moodle_url('/blocks/exaport/resume.php', ['courseid' => $courseid]);
        $itemsurl = new moodle_url('/blocks/exaport/view_items.php', ['courseid' => $courseid]);
        echo '<ul>
    <li class="portfolioElement" title="' . block_exaport_get_string('personalinformation') . '" block-type="personal_information"
            id="personal_information_adder"
            data-help="' . block_exaport_get_string('personalinformation_help', $profileurl->out()) . '">
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('id-card', 'regular', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/personal_info.png" />'
            . '<h4 class="blocktype-title js-hidden">' . block_exaport_get_string('personalinformation') . '</h4>
            <div class="blocktype-description js-hidden">' . block_exaport_get_string('personalinformation') . '</div>
        </a></div>
    </li>
    <li class="portfolioElement" title="' . block_exaport_get_string('cvinformation') . '" block-type="cv_information"
        id="cv_information_adder"
        data-help="' . block_exaport_get_string('cvinformation_help', $cvurl->out()) . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('address-book', 'regular', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/cv_info.png" />'
            . '<h4 class="blocktype-title js-hidden">' . block_exaport_get_string('cvinformation') . '</h4>
            <div class="blocktype-description js-hidden">' . block_exaport_get_string('cvinformation') . '</div>
        </a></div>
    </li>';
        echo '
    <li class="portfolioElement" title="' . block_exaport_get_string('headertext') . '" block-type="headline"
        id="headline_adder"
        data-help="' . block_exaport_get_string('headertext_help') . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('heading', 'solid', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .block_exaport_fontawesome_icon('grip-lines', 'solid', '3', ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/header_text.png" />'
            . '<h4 class="blocktype-title js-hidden">' . block_exaport_get_string('headertext') . '</h4>
            <div class="blocktype-description js-hidden">' . block_exaport_get_string('headertext') . '</div>
        </a></div>
    </li>
    <li class="portfolioElement" title="' . block_exaport_get_string('view_specialitem_text') . '" block-type="text"
        id="text_adder"
        data-help="' . block_exaport_get_string('view_specialitem_text_help') . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('file-lines', 'regular', 3, ['fa-border'], [], [], 'edit', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/text.png" />'
            . '<h4 class="blocktype-title js-hidden">' . block_exaport_get_string('view_specialitem_text') . '</h4>
            <div class="blocktype-description js-hidden">' . block_exaport_get_string('view_specialitem_text') . '</div>
        </a></div>
    </li>';
        echo '
    <li class="portfolioElement" title="' . block_exaport_get_string('items') . '" block-type="item"
        id="item_adder"
        data-help="' . block_exaport_get_string('items_help', $itemsurl->out()) . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('clone', 'regular', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/lists.png" />'
            . '<h4 class="blocktype-title js-hidden">' . get_string('items', 'block_exaport') . '</h4>
            <div class="blocktype-description js-hidden">' . get_string('selectitems', 'block_exaport') . '</div>
        </a></div>
    </li>
    <li class="portfolioElement" title="' . block_exaport_get_string('media') . '" block-type="media"
        id="media_adder"
        data-help="' . block_exaport_get_string('media_help') . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
            . block_exaport_fontawesome_icon('photo-film', 'solid', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
            //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media.png" />'
            . '<h4 class="blocktype-title js-hidden">' . block_exaport_get_string('media') . '</h4>
            <div class="blocktype-description js-hidden">' . block_exaport_get_string('selectitems') . '</div>
        </a></div>
    </li>';

        if (block_exaport_badges_enabled()) {
            $badgesurl = new moodle_url('/user/profile.php');
            echo '<li class="portfolioElement" title="' . get_string('badges', 'badges') . '" block-type="badge"
        id="badges_adder"
        data-help="' . block_exaport_get_string('badges_help', $badgesurl) . '" >
        <div class="blocktype" style="position: relative;"><a href="#">'
                . block_exaport_fontawesome_icon('award', 'solid', 3, ['fa-border'], [], [], '', [], [], [], ['exaport-view-block-adder-icon'])
                //            .'<img width="73" height="61" alt="Preview" src="'.$CFG->wwwroot.'/blocks/exaport/pix/badges.png" />'
                . '<h4 class="blocktype-title js-hidden">' . get_string('badges', 'badges') . '</h4>
            <div class="blocktype-description js-hidden">' . get_string('selectitems', 'block_exaport') . '</div>
        </a></div>
    </li>';
        }

        echo '</ul>';
        echo '</div>';

        $colslayout = array(
            "1" => 1, "2" => 2, "3" => 2, "4" => 2, "5" => 3, "6" => 3, "7" => 3, "8" => 4, "9" => 4, "10" => 5,
        );

        // Default layout.
        if (!isset($view->layout) || $view->layout == 0) {
            $view->layout = 2;
        }

        echo '<div class="view-middle">';

        echo '<div id="view-preview">';
        echo '<div class="view-group-header"><div>';
        echo block_exaport_fontawesome_icon('briefcase', 'solid', 1);
        echo get_string('viewdesign', 'block_exaport');
        echo '</div></div>';
        echo '<div>';
        echo '<table class="table_layout layout' . $view->layout . '"><tr>';
        for ($i = 1; $i <= $colslayout[$view->layout]; $i++) {
            echo '<td class="td' . $i . '">';
            echo '<ul class="portfolioDesignBlocks">';
            echo '</ul>';
            echo '</td>';
        }
        echo '</tr></table>';
        echo '</div>';
        echo '</div>';
        echo '<div class="clear"><span>&nbsp;</span></div>';
        echo '</div>';

        break;

    case 'title' :
        echo '<div class="mform">';
        echo '<fieldset class="clearfix"><legend class="ftoggler">' . get_string('viewinformation', 'block_exaport') . '</legend>';

        $data = new stdClass();
        $data->courseid = $courseid;
        $data->description = "";
        if (isset($view) and $view->id > 0) {
            $data->description = $view->description;
            $data->descriptionformat = FORMAT_HTML;
        }
        if ($data->description) {
            $draftideditor = file_get_submitted_draft_itemid('description');
            $currenttext = file_prepare_draft_area($draftideditor, context_user::instance($USER->id)->id, "block_exaport", "view",
                $view->id, array('subdirs' => true, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size), $data->description);
            $data->description = file_rewrite_pluginfile_urls($data->description, 'draftfile.php',
                context_user::instance($USER->id)->id, 'user', 'draft', $draftideditor);
            $data->description_editor = array('text' => $data->description,
                'format' => $data->descriptionformat, 'itemid' => $draftideditor);
        }
        $data->cataction = 'save';
        $data->edit = 1;
        $editform->set_data($data);

        $editform->display();
        echo '</fieldset>';
        echo '</div>';
        break;

    case 'layout' :
        if (!isset($view->layout) || $view->layout == 0) {
            $view->layout = 2;
        }
        echo '
            <p>' . get_string('chooselayout', 'block_exaport') . '</p>
            <div class="select_layout">
            <hr class="cb" />
                <div class="fl columnoption"><strong>' . get_string("viewlayoutgroup1", "block_exaport") . '</strong></div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="1" type="radio" ' .
            ($view->layout == 1 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-100.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout1", "block_exaport") . '</div>
                </div>
            <hr class="cb" />
                <div class="fl columnoption"><strong>' . get_string("viewlayoutgroup2", "block_exaport") . '</strong></div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="2" type="radio" ' .
            ($view->layout == 2 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-50-50.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout2", "block_exaport") . '</div>
                </div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="3" type="radio" ' .
            ($view->layout == 3 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-67-33.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout3", "block_exaport") . '</div>
                </div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="4" type="radio" ' .
            ($view->layout == 4 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-33-67.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout4", "block_exaport") . '</div>
                </div>
            <hr class="cb" />
                <div class="fl columnoption"><strong>' . get_string("viewlayoutgroup3", "block_exaport") . '</strong></div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="5" type="radio" ' .
            ($view->layout == 5 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-33-33-33.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout5", "block_exaport") . '</div>
                </div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="6" type="radio" ' .
            ($view->layout == 6 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-25-50-25.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout6", "block_exaport") . '</div>
                </div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="7" type="radio" ' .
            ($view->layout == 7 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-15-70-15.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout7", "block_exaport") . '</div>
                </div>
            <hr class="cb" />
                <div class="fl columnoption"><strong>' . get_string("viewlayoutgroup4", "block_exaport") . '</strong></div>
                    <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="8" type="radio" ' .
            ($view->layout == 8 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-25-25-25-25.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout8", "block_exaport") . '</div>
                </div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="9" type="radio" ' .
            ($view->layout == 9 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-20-30-30-20.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout9", "block_exaport") . '</div>
                </div>
            <hr class="cb" />
                <div class="fl columnoption"><strong>' . get_string("viewlayoutgroup5", "block_exaport") . '</strong></div>
                <div class="fl layoutoptions">
                    <div class="radiobutton"><input class="radio" name="layout" value="10" type="radio" ' .
            ($view->layout == 10 ? 'checked="checked"' : '') . ' /></div>
                    <div class="layoutimg"><img src="' . $CFG->wwwroot . '/blocks/exaport/pix/vl-20-20-20-20-20.png" alt="" /></div>
                    <div class="layoutdescription">' . get_string("viewlayout10", "block_exaport") . '</div>
                </div>
            </div>';

        if (@$CFG->block_exaport_allow_custom_layout) {
            if ($view->layout_settings) {
                $layoutSettings = unserialize($view->layout_settings);
            } else {
                $layoutSettings = [];
            }
            // Additional layout settings
            $fontSizes = block_exaport_layout_fontsizes();
            $borderWidths = block_exaport_layout_borderwidths();
            $selectedHeaderFontSize = @$layoutSettings['header_fontSize'] ?: '-1';
            $selectedTextFontSize = @$layoutSettings['text_fontSize'] ?: '-1';
            $selectedHeaderBorderWidth = @$layoutSettings['header_borderWidth'] ?: '-1';
            $selectedBlockBorderWidth = @$layoutSettings['block_borderWidth'] ?: '-1';
            $customLayoutCss = @$layoutSettings['customCss'] ?: '';
            $headerBold = @$layoutSettings['headerBold'] ?: false;
            // Container: collapsible.
            echo '<fieldset class="layout_settings clearfix view-group">
                <legend class="view-group-header">' . block_exaport_get_string('layout_settings') . '</legend>
                <div class="view-group-content clearfix">

                <div class="alert alert-info">' . block_exaport_get_string('layout_settings_description') . '</div>

                <div class="form-group row">
                        <div class="col-md-3 col-form-label">
                        </div>
                        <div class="col-md-3 col-form-label">
                            <strong>' . block_exaport_get_string('layout_settings_font_size') . '</strong>
                        </div>
                        <div class="col-md-3 col-form-label">
                            <strong>' . block_exaport_get_string('layout_settings_font_weight') . '</strong>
                        </div>
                        <div class="col-md-3 col-form-label">
                            <strong>' . block_exaport_get_string('layout_settings_border_width') . '</strong>
                        </div>
                </div>
                <div class="form-group row">
                        <div class="col-md-3 col-form-label">
                            <strong>' . block_exaport_get_string('layout_settings_view_headers') . '</strong>
                        </div>
                        <div class="col-md-3 col-form-label">
                            ' . html_writer::select($fontSizes, 'layoutSettings[header_fontSize]', $selectedHeaderFontSize, false, ['id' => 'header_fontSize']) . '
                        </div>
                        <div class="col-md-3 col-form-label">
                            ' . html_writer::checkbox('layoutSettings[headerBold]', '1', $headerBold) . '
                        </div>
                        <div class="col-md-3 col-form-label">
                            ' . html_writer::select($borderWidths, 'layoutSettings[header_borderWidth]', $selectedHeaderBorderWidth, false, ['id' => 'header_borderWidth']) . '<br>
                            <small>' . block_exaport_get_string('layout_settings_border_width_only_bottom') . '</small>
                        </div>
                </div>
                <div class="form-group row">
                        <div class="col-md-3 col-form-label">
                            <strong>' . block_exaport_get_string('layout_settings_view_content') . '</strong>
                        </div>
                        <div class="col-md-3 col-form-label">
                            ' . html_writer::select($fontSizes, 'layoutSettings[text_fontSize]', $selectedTextFontSize, false, ['id' => 'text_fontSize']) . '
                        </div>
                        <div class="col-md-3 col-form-label">

                        </div>
                        <div class="col-md-3 col-form-label">
                            ' . html_writer::select($borderWidths, 'layoutSettings[block_borderWidth]', $selectedBlockBorderWidth, false, ['id' => 'block_borderWidth']) . '
                        </div>
                </div>

                <div class="form-group row">
                    <div class="col-md-3 col-form-label">
                        <strong>' . html_writer::label(block_exaport_get_string('layout_settings_custom_css'), 'customCss') . '</strong><br>
                        <small>' . block_exaport_get_string('layout_settings_custom_css_description') . '</small>
                        </div>
                        <div class="col-md-9 form-inline">
                            ' . html_writer::tag(
                    'textarea',
                    $customLayoutCss,
                    array(
                        'id' => 'customCss',
                        'name' => 'layoutSettings[customCss]',
                        'class' => 'form-control',
                        'rows' => 5,
                        'cols' => 40,
                    )
                ) . '
                        </div>
                    </div>
                </div>
                </fieldset>';
        }
        break;

    case 'share' :
        echo '<div class="view-sharing view-group">';
        echo '<div class="view-group-header"><div>';
        echo block_exaport_fontawesome_icon('share-from-square', 'solid', 1);
        echo get_string('view_sharing', 'block_exaport');
        echo ': <span id="view-share-text"></span></div></div>';
        echo '<div class="">';
        echo '<div style="padding: 18px 22px"><table class="table_share">';

        if (block_exaport_externaccess_enabled() && has_capability('block/exaport:shareextern', context_system::instance())) {

            echo '<tr><td style="padding-right: 10px; width: 10px">';
            echo $form['elements_by_name']['externaccess']['html'];
            echo '</td><td>' . get_string("externalaccess", "block_exaport") . '</td></tr>';

            if ($view) {
                $url = block_exaport_get_external_view_url($view);
                // Only when editing a view, the external link will work!
                echo '<tr id="externaccess-settings"><td></td><td>';
                echo '<div style="padding: 4px;"><a href="' . $url . '">' . $url . '</a></div>';
                if (block_exaport_external_comments_enabled()) {
                    echo '<div style="padding: 4px 0;"><table>';
                    echo '<tr><td style="padding-right: 10px; width: 10px">';
                    echo '<input type="checkbox" name="externcomment" value="1"' .
                        ($postview->externcomment ? ' checked="checked"' : '') . ' />';
                    echo '</td><td>' . get_string("externcomment", "block_exaport") . '</td></tr>';
                    echo '</table></div>';
                }
                echo '</td></tr>';
            }

            echo '<tr><td style="height: 10px"></td></tr>';
        }

        if (has_capability('block/exaport:shareintern', context_system::instance())) {
            echo '<tr><td style="padding-right: 10px">';
            echo $form['elements_by_name']['internaccess']['html'];
            echo '</td><td>' . get_string("internalaccess", "block_exaport") . '</td></tr>';
            echo '<tr id="internaccess-settings"><td></td><td>';
            echo '<div style="padding: 4px 0;"><table>';
            if (block_exaport_shareall_enabled()) {
                echo '<tr><td style="padding-right: 10px; width: 10px">';
                echo '<input type="radio" name="shareall" value="1"' . ($postview->shareall == 1 ? ' checked="checked"' : '') . ' />';
                echo '</td><td>' . get_string("internalaccessall", "block_exaport") . '</td></tr>';
            }
            // Internal access for users.
            echo '<tr><td style="padding-right: 10px">';
            echo '<input type="radio" name="shareall" value="0"' . (!$postview->shareall ? ' checked="checked"' : '') . '/>';
            echo '</td><td>' . get_string("internalaccessusers", "block_exaport") . '</td></tr>';
            echo '<tr id="internaccess-users"><td></td><td>';
            if (block_exaport_shareall_enabled()) {
                // Show user search form.
                echo get_string("share_to_other_users", "block_exaport") . ':';
                echo '<div style="padding-bottom: 20px;">';
                echo '<input name="share_to_other_users_q" type="text" /> ';
                echo '<input name="share_to_other_users_submit" type="submit" value="' . get_string('search') . '" />';
                echo '</div>';
            }
            echo '<div id="sharing-userlist">userlist</div>';
            echo '</td></tr>';
            // Internal access for groups.
            echo '<tr><td style="padding-right: 10px">';
            echo '<input type="radio" name="shareall" value="2"' . ($postview->shareall == 2 ? ' checked="checked" ' : '') . '/>';
            echo '</td><td>' . get_string("internalaccessgroups", "block_exaport") . '</td></tr>';
            echo '<tr id="internaccess-groups"><td></td><td>';
            echo '<div id="sharing-grouplist">grouplist</div>';
            echo '</td></tr>';
            echo '</table></div>';
            echo '</td></tr>';
        }

        if (block_exaport_shareemails_enabled()) {
            echo '<tr><td style="height: 10px"></td></tr>';
            echo '<tr><td style="padding-right: 10px; width: 10px">';
            echo $form['elements_by_name']['sharedemails']['html'];
            echo '</td><td>' . get_string("emailaccess", "block_exaport") . '</td></tr>';

            if ($view) {
                $view->emailsforshare = implode(';', exaport_get_view_shared_emails($view->id));
                echo '<tr id="emailaccess-settings"><td></td><td>';
                echo get_string("emailaccessdescription", "block_exaport");
                echo '<textarea name="emailsforshare">' . str_replace(';', "\r\n", $view->emailsforshare) . '</textarea><br>';
                echo '</td></tr>';
            }
        }

        echo '</table></div>';
        echo '</div>';
        echo '</div>';
        break;
    default:
        break;
}

if ($type != 'title') {
    echo '<div style="padding-top: 20px; text-align: center; clear: both;">';
    echo $form['elements_by_name']['submitbutton']['html'];
    echo '</div>';
    echo '</div></form>';
}

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();

function block_exaport_emailaccess_sendemails(&$view, $oldemails, $newemails, $hashesforemails) {
    global $CFG, $USER, $DB;

    $userfrom = $USER;
    $userfrom->maildisplay = true;
    // New emails - need to send emails.
    $needtosend = array_diff($newemails, $oldemails);
    if (count($needtosend) > 0) {
        foreach ($needtosend as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $accessphrase = $hashesforemails[$email];
                $url = $CFG->wwwroot . '/blocks/exaport/shared_view.php?access=email/' . $view->hash . '-' . $accessphrase;
                $messagesubject = get_string("emailaccessmessagesubject", "block_exaport");
                $a = new stdClass();
                $a->url = $url;
                $a->sendername = fullname($USER);
                $a->viewname = $view->name;
                $messagetext = get_string("emailaccessmessage", "block_exaport", $a);
                $messagehtml = get_string("emailaccessmessageHTML", "block_exaport", $a);

                // Find user by email.
                $userconditions = array('email' => $email);
                if ($touser = $DB->get_record("user", $userconditions, '*')) {
                    // Send moodle message if the user exists.
                    $message = new \core\message\message();
                    $message->component = 'block_exaport';
                    $message->name = 'sharing';
                    $message->userfrom = $userfrom;
                    $message->userto = $touser;
                    $message->subject = $messagesubject;
                    $message->fullmessage = $messagetext;
                    $message->fullmessageformat = FORMAT_HTML;
                    $message->fullmessagehtml = $messagehtml;
                    $message->smallmessage = '';
                    $message->notification = 1;

                    message_send($message);
                } else {
                    $touser = new stdClass();
                    $touser->email = $email;
                    $touser->firstname = '';
                    $touser->lastname = '';
                    $touser->maildisplay = true;
                    $touser->mailformat = 1;
                    $touser->id = -99;
                    $touser->firstnamephonetic = '';
                    $touser->lastnamephonetic = '';
                    $touser->middlename = '';
                    $touser->alternatename = '';

                    email_to_user($touser, $USER, $messagesubject, $messagetext, $messagehtml);
                }
            }
        }
    }

    return true;
}
