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
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

block_exaport_setup_default_categories();

$url = '/blocks/exaport/category.php';
$PAGE->set_url($url, ['courseid' => $courseid,
    'action' => optional_param('action', '', PARAM_ALPHA),
    'id' => optional_param('id', '', PARAM_INT)]);

// Get userlist for sharing category.
if (optional_param('action', '', PARAM_ALPHA) == 'userlist') {
    echo json_encode(exaport_get_shareable_courses_with_users(''));
    exit;
}
// Get grouplist for sharing category.
if (optional_param('action', '', PARAM_ALPHA) == 'grouplist') {
    $id = required_param('id', PARAM_INT);

    $category = $DB->get_record("block_exaportcate", array(
        'id' => $id,
        'userid' => $USER->id,
    ));
    if (!$category) {
        throw new \block_exaport\moodle_exception('category_not_found');
    }

    $groupgroups = block_exaport_get_shareable_groups_for_json();
    foreach ($groupgroups as $groupgroup) {
        foreach ($groupgroup->groups as $group) {
            $group->shared_to = $DB->record_exists('block_exaportcatgroupshar', [
                'catid' => $category->id,
                'groupid' => $group->id,
            ]);
        }
    }
    echo json_encode($groupgroups);
    exit;
}

if (optional_param('action', '', PARAM_ALPHA) == 'addstdcat') {
    block_exaport_import_categories('lang_categories');
    redirect('view_items.php?courseid=' . $courseid);
}
if (optional_param('action', '', PARAM_ALPHA) == 'movetocategory') {
    confirm_sesskey();

    $category = $DB->get_record("block_exaportcate", array(
        'id' => required_param('id', PARAM_INT),
        'userid' => $USER->id,
    ));
    if (!$category) {
        die(block_exaport_get_string('category_not_found'));
    }

    if (!$targetcategory = block_exaport_get_category(required_param('categoryid', PARAM_INT))) {
        die('target category not found');
    }

    $DB->update_record('block_exaportcate', (object)array(
        'id' => $category->id,
        'pid' => $targetcategory->id,
    ));

    echo 'ok';
    exit;
}

if (optional_param('action', '', PARAM_ALPHA) == 'delete') {
    $id = required_param('id', PARAM_INT);

    $category = $DB->get_record("block_exaportcate", array(
        'id' => $id,
        'userid' => $USER->id,
    ));
    if (!$category) {
        throw new \block_exaport\moodle_exception('category_not_found');
    }

    if (optional_param('confirm', 0, PARAM_INT)) {
        confirm_sesskey();

        function block_exaport_recursive_delete_category($id) {
            global $DB;

            // Delete subcategories.
            if ($entries = $DB->get_records('block_exaportcate', array("pid" => $id))) {
                foreach ($entries as $entry) {
                    block_exaport_recursive_delete_category($entry->id);
                }
            }
            $DB->delete_records('block_exaportcate', array('pid' => $id));

            // Delete itemsharing.
            if ($entries = $DB->get_records('block_exaportitem', array("categoryid" => $id))) {
                foreach ($entries as $entry) {
                    $DB->delete_records('block_exaportitemshar', array('itemid' => $entry->id));
                }
            }

            // Delete items.
            $DB->delete_records('block_exaportitem', array('categoryid' => $id));
        }

        block_exaport_recursive_delete_category($category->id);

        if (!$DB->delete_records('block_exaportcate', array('id' => $category->id))) {
            $message = "Could not delete your record";
        } else {
            block_exaport_add_to_log($courseid, "bookmark", "delete category", "", $category->id);
            redirect('view_items.php?courseid=' . $courseid . '&categoryid=' . $category->pid);
        }
    }

    $optionsyes = array('action' => 'delete', 'courseid' => $courseid, 'confirm' => 1, 'sesskey' => sesskey(), 'id' => $id);
    $optionsno = array(
        'courseid' => $courseid,
        'categoryid' => optional_param('back', '', PARAM_TEXT) == 'same' ? $category->id : $category->pid,
    );

    $strbookmarks = get_string("myportfolio", "block_exaport");
    $strcat = get_string("categories", "block_exaport");

    block_exaport_print_header("myportfolio");

    echo '<br />';
    echo $OUTPUT->confirm(get_string("deletecategoryconfirm", "block_exaport", $category),
        new moodle_url('category.php', $optionsyes),
        new moodle_url('view_items.php', $optionsno));
    echo block_exaport_wrapperdivend();
    $OUTPUT->footer();

    exit;
}

require_once("$CFG->libdir/formslib.php");

class simplehtml_form extends block_exaport_moodleform {
    // Add elements to form.
    public function definition() {
        global $CFG;
        global $DB;
        global $USER;

        $id = optional_param('id', 0, PARAM_INT);
        $category = $DB->get_record_sql('
            SELECT c.id, c.name, c.pid, c.internshare, c.shareall, c.iconmerge
            FROM {block_exaportcate} c
            WHERE c.userid = ? AND id = ?
            ', array($USER->id, $id));
        if (!$category) {
            $category = new stdClass;
            $category->shareall = 0;
            $category->id = 0;
            $category->iconmerge = 0;
        };

        // Don't forget the underscore!
        $mform = $this->_form;
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
        $mform->add_exaport_help_button('name', 'forms.category.name');

        $mform->addElement('filemanager',
            'iconfile',
            get_string('iconfile', 'block_exaport'),
            null,
            array('subdirs' => false,
                'maxfiles' => 1,
                'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
                'accepted_types' => array('image', 'web_image')));
        $mform->add_exaport_help_button('iconfile', 'forms.category.iconfile');

        //        if (extension_loaded('gd') && function_exists('gd_info')) {
        // changed into Fontawesome and Javascript
        $mform->addElement('advcheckbox',
            'iconmerge',
            get_string('iconfile_merge', 'block_exaport'),
            get_string('iconfile_merge_description', 'block_exaport'),
            array('group' => 1),
            array(0, 1));
        $mform->add_exaport_help_button('iconmerge', 'forms.category.iconmerge');


        //        };

        // Sharing.
        if (has_capability('block/exaport:shareintern', context_system::instance())) {
            $mform->addElement('checkbox', 'internshare', get_string('share', 'block_exaport'));
            $mform->setType('internshare', PARAM_INT);
            $mform->add_exaport_help_button('internshare', 'forms.category.internshare');
            $mform->addElement('html', '<div id="internaccess-settings" class="fitem"">' .
                '<div class="fitemtitle"></div><div class="felement">');

            $mform->addElement('html', '<div style="padding: 4px 0;"><table width=100%>');
            // Share to all.
            if (block_exaport_shareall_enabled()) {
                $mform->addElement('html', '<tr><td>');
                $mform->addElement('html', '<input type="radio" name="shareall" value="1"' .
                    ($category->shareall == 1 ? ' checked="checked"' : '') . '/>');
                $mform->addElement('html', '</td><td>' . get_string('internalaccessall', 'block_exaport') . '</td></tr>');
                $mform->setType('shareall', PARAM_INT);
                $mform->addElement('html', '</td></tr>');
            }

            // Share to users.
            $mform->addElement('html', '<tr><td>');
            $mform->addElement('html', '<input type="radio" name="shareall" value="0"' .
                (!$category->shareall ? ' checked="checked"' : '') . '/>');
            $mform->addElement('html', '</td><td>' . get_string('internalaccessusers', 'block_exaport') . '</td></tr>');
            $mform->addElement('html', '</td></tr>');
            if ($category->id > 0) {
                $sharedusers = $DB->get_records_menu('block_exaportcatshar',
                    array("catid" => $category->id),
                    null,
                    'userid, userid AS tmp');
                $mform->addElement('html', '<script> var sharedusersarr = [];');
                foreach ($sharedusers as $i => $user) {
                    $mform->addElement('html', 'sharedusersarr[' . $i . '] = ' . $user . ';');
                }
                $mform->addElement('html', '</script>');
            }
            $mform->addElement('html', '<tr id="internaccess-users"><td></td>' .
                '<td><div id="sharing-userlist">userlist</div></td></tr>');

            // Share to groups.
            $mform->addElement('html', '<tr><td>');
            $mform->addElement('html', '<input type="radio" name="shareall" value="2"' .
                ($category->shareall == 2 ? ' checked="checked"' : '') . '/>');
            $mform->addElement('html', '</td><td>' . get_string('internalaccessgroups', 'block_exaport') . '</td></tr>');
            $mform->addElement('html', '</td></tr>');
            $mform->addElement('html', '<tr id="internaccess-groups"><td></td>' .
                '<td><div id="sharing-grouplist">grouplist</div></td></tr>');
            $mform->addElement('html', '</table></div>');
            $mform->addElement('html', '</div></div>');
        };

        $this->add_action_buttons();
    }

    // Custom validation should be added here.
    public function validation($data, $files) {
        return array();
    }
}

// Instantiate simplehtml_form.
$mform = new simplehtml_form(null, null, 'post', '', ['id' => 'categoryform']);

// Form processing and displaying is done here.
if ($mform->is_cancelled()) {
    $same = optional_param('back', '', PARAM_TEXT);
    $id = optional_param('id', 0, PARAM_INT);
    $pid = optional_param('pid', 0, PARAM_INT);
    redirect('view_items.php?courseid=' . $courseid . '&categoryid=' . ($same == 'same' ? $id : $pid));
} else if ($newentry = $mform->get_data()) {
    require_sesskey();
    $newentry->userid = $USER->id;
    $newentry->shareall = optional_param('shareall', 0, PARAM_INT);
    if (optional_param('internshare', 0, PARAM_INT) > 0) {
        $newentry->internshare = optional_param('internshare', 0, PARAM_INT);
    } else {
        $newentry->internshare = 0;
    }

    if ($newentry->id) {
        $DB->update_record("block_exaportcate", $newentry);
    } else {
        $newentry->id = $DB->insert_record("block_exaportcate", $newentry);
    }

    // Delete all shared users.
    $DB->delete_records("block_exaportcatshar", array('catid' => $newentry->id));
    // Add new shared users.
    if ($newentry->internshare && !$newentry->shareall) {
        $shareusers = \block_exaport\param::optional_array('shareusers', PARAM_INT);
        foreach ($shareusers as $shareuser) {
            $shareuser = clean_param($shareuser, PARAM_INT);
            $shareitem = new stdClass();
            $shareitem->catid = $newentry->id;
            $shareitem->userid = $shareuser;
            $DB->insert_record("block_exaportcatshar", $shareitem);
        };
    };

    // Delete all shared groups.
    $DB->delete_records("block_exaportcatgroupshar", array('catid' => $newentry->id));
    // Add new shared groups.
    if ($newentry->internshare && $newentry->shareall == 2) {
        $sharegroups = \block_exaport\param::optional_array('sharegroups', PARAM_INT);
        $usergroups = block_exaport_get_user_cohorts();

        foreach ($sharegroups as $groupid) {
            if (!isset($usergroups[$groupid])) {
                // Not allowed.
                continue;
            }
            $DB->insert_record("block_exaportcatgroupshar", [
                'catid' => $newentry->id,
                'groupid' => $groupid,
            ]);
        }
    }

    // Icon for item.
    $context = context_user::instance($USER->id);
    $uploadfilesizes = block_exaport_get_filessize_by_draftid($newentry->iconfile);
    // Merge with folder icon.
    // FontAwesome icons uses icon merge by JS in Frontend. So, this code is redundant now
    // (also, from now we have new category field 'iconmerge')
    /*if (isset($newentry->iconmerge) && $newentry->iconmerge == 1 && $uploadfilesizes > 0) {
        $fs = get_file_storage();
        $image = $DB->get_record_sql('SELECT * '.
                'FROM {files} '.
                'WHERE contextid = ? '.
                'AND component = "user" '.
                'AND filearea="draft" '.
                'AND itemid = ? '.
                'AND filename<>"."',
                array($context->id, $newentry->iconfile));
        if ($image) {
            $fileimage = $fs->get_file($context->id, 'user', 'draft', $newentry->iconfile, '/', $image->filename);
            $imagecontent = $fileimage->get_content();
            // Merge images.
            $imicon = imagecreatefromstring($imagecontent);
            $imfolder = imagecreatefrompng($CFG->dirroot.'/blocks/exaport/pix/folder_tile.png');

            imagealphablending($imfolder, false);
            imagesavealpha($imfolder, true);

            // Max width/height.
            $maxwidth = 150;
            $maxheight = 80;
            $skew = 10;
            $imicon = skewscaleimage($imicon, $maxwidth, $maxheight, $skew);

            $swidth = imagesx($imfolder);
            $sheight = imagesy($imfolder);
            $owidth = imagesx($imicon);
            $oheight = imagesy($imicon);
            $x = 0;
            $y = 0;
            // Overlay's opacity (in percent).
            $opacity = 75;

            // Coordinates - only for current folder icon..
            imagecopymerge($imfolder,
                    $imicon,
                    $swidth / 2 - $owidth / 2,
                    $sheight / 2 - $oheight / 2 + 10,
                    0,
                    0,
                    $owidth,
                    $oheight,
                    $opacity);

            ob_start();
            imagepng($imfolder);
            $imagedata = ob_get_contents();
            ob_end_clean();

            // Simple checking to PNG.
            if (stripos($imagedata, 'png') == 1) {
                // Delete old file.
                $fileimage->delete();
                // Create file containing new image.
                $fileinfo = array(
                        'contextid' => $context->id,
                        'component' => 'user',
                        'filearea' => 'draft',
                        'itemid' => $image->itemid,
                        'filepath' => '/',
                        'filename' => $image->filename);
                $fs->create_file_from_string($fileinfo, $imagedata);
            };
            imagedestroy($imicon);
            imagedestroy($imfolder);
        };
    };
    unset($newentry->iconmerge);*/
    // Checking userquoata.
    $userquotecheck = block_exaport_file_userquotecheck($uploadfilesizes, $newentry->id);
    $filesizecheck = block_exaport_get_maxfilesize_by_draftid_check($newentry->iconfile);
    if ($userquotecheck && $filesizecheck) {
        file_save_draft_area_files($newentry->iconfile,
            $context->id,
            'block_exaport',
            'category_icon',
            $newentry->id,
            array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
    };

    redirect('view_items.php?courseid=' . $courseid . '&categoryid=' .
        ($newentry->back == 'same' ? $newentry->id : $newentry->pid));
} else {
    block_exaport_print_header("myportfolio");

    $category = null;
    if ($id = optional_param('id', 0, PARAM_INT)) {
        $category = $DB->get_record_sql('
            SELECT c.id, c.name, c.pid, c.internshare, c.shareall, c.iconmerge
            FROM {block_exaportcate} c
            WHERE c.userid = ? AND id = ?
        ', array($USER->id, $id));
    }
    if (!$category) {
        $category = new stdClass;
    }

    $category->courseid = $courseid;
    if (!isset($category->id)) {
        $category->id = null;
    }
    $category->back = optional_param('back', '', PARAM_TEXT);
    if (empty($category->pid)) {
        $category->pid = optional_param('pid', 0, PARAM_INT);
    }

    // Filemanager for editing icon picture.
    $draftitemid = file_get_submitted_draft_itemid('iconfile');
    $context = context_user::instance($USER->id);
    file_prepare_draft_area($draftitemid,
        $context->id,
        'block_exaport',
        'category_icon',
        $category->id,
        array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
    $category->iconfile = $draftitemid;

    $mform->set_data($category);
    $mform->display();
    echo block_exaport_wrapperdivend();

    $PAGE->requires->js('/blocks/exaport/javascript/category.js', true);

    // Translations.
    $translations = array(
        'name', 'role', 'nousersfound',
        'internalaccessgroups', 'grouptitle', 'membercount', 'nogroupsfound',
        'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs',
        'notify', 'checkall',
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

function skewscaleimage($srcimg, $maxwidth = 100, $maxheight = 100, $skew = 10) {
    $w = imagesx($srcimg);
    $h = imagesy($srcimg);
    // Scale.
    if ($h > $maxheight) {
        $koeff = $h / $maxheight;
        $newwidth = $w / $koeff;
        $srcimg = imagescale($srcimg, $newwidth, $maxheight);
        $h = $maxheight;
        $w = imagesx($srcimg);
    }
    if ($w > $maxwidth) {
        $srcimg = imagescale($srcimg, $maxwidth);
        $w = $maxwidth;
        $h = imagesy($srcimg);
    }
    // Skew it.
    $neww = abs($h * tan(deg2rad($skew)) + $w);
    $step = tan(deg2rad($skew));
    $dstimg = imagecreatetruecolor($neww, $h);
    $bgcolour = imagecolorallocate($dstimg, 0, 0, 0);
    imagecolortransparent($dstimg, $bgcolour);
    imagefill($dstimg, 0, 0, $bgcolour);

    for ($i = 0; $i < $h; $i++) {
        imagecopyresampled($dstimg, $srcimg, $neww - ($w + $step * $i), $i, 0, $i, $w, 1, $w, 1);
    }

    return $dstimg;
}
