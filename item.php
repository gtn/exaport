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

const POSSIBLE_IFRAME_FIELDS = ['intro', 'project_description', 'project_process', 'project_result'];

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$confirm = optional_param("confirm", "", PARAM_BOOL);
$backtype = optional_param('backtype', 'all', PARAM_ALPHA);
$compids = optional_param('compids', '', PARAM_TEXT);
$backtype = block_exaport_check_item_type($backtype, true);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$cattype = optional_param('cattype', '', PARAM_ALPHA);
$descriptorselection = optional_param('descriptorselection', true, PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);

$url = '/blocks/exaport/item.php';
$PAGE->set_url($url, ['courseid' => $courseid, 'id' => $id, 'action' => $action]);

$conditions = array("id" => $courseid);

if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidcourseid", "block_exaport");
}

if ($CFG->branch < 31) {
    include($CFG->dirroot . '/tag/lib.php');
}

$allowedit = block_exaport_item_is_editable($id);
$allowresubmission = block_exaport_item_is_resubmitable($id);

if ($action == 'copytoself') {
    require_sesskey();
    if (!$ownerid = block_exaport_can_user_access_shared_item($USER->id, $id)) {
        die(block_exaport_get_string('bookmarknotfound'));

    }

    $conditions = array("id" => $id, "userid" => $ownerid);
    $sourceitem = $DB->get_record('block_exaportitem', $conditions);

    $copy = $sourceitem;

    unset($copy->id);
    $copy->userid = $USER->id;
    $copy->categoryid = 0;
    $copy->timemodified = time();
    $copy->shareall = 0;
    $copy->externaccess = 0;
    $copy->externcomment = 0;
    $copy->shareall = 0;

    $newitemid = $DB->insert_record('block_exaportitem', $copy);
    if ($copy->type == 'file') {
        $fs = get_file_storage();
        $fileinfo = array(
            'component' => 'block_exaport',
            'filearea' => 'item_file',
            'itemid' => $id);
        $ownerusercontext = context_user::instance($ownerid);
        $usercontext = context_user::instance($USER->id);
        $oldfiles = $fs->get_area_files($ownerusercontext->id, 'block_exaport', 'item_file', $id);
        foreach ($oldfiles as $f) {
            $newfileparams = array(
                'contextid' => $usercontext->id,
                'itemid' => $newitemid,
                'userid' => $USER->id,
            );
            $filecopy = $fs->create_file_from_storedfile($newfileparams, $f->get_id());
        };
    };

    $returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&categoryid=-1&userid=" . $ownerid;
    redirect($returnurl);
}

if ($id) {
    $conditions = array("id" => $id, "userid" => $USER->id);
    if (!$existing = $DB->get_record('block_exaportitem', $conditions)) {
        print_error("wrong" . $type . "id", "block_exaport");
    }
} else {
    $existing = false;
}

// Read + check type.
if ($existing) {
    $type = $existing->type;
} else {
    $type = optional_param('type', 'all', PARAM_ALPHA);
    $type = block_exaport_check_item_type($type, false);
    if (!$type) {
        print_error("badtype", "block_exaport");
    }
}

// Get competences from item if editing.
$exacompactive = block_exaport_check_competence_interaction();
if ($existing && $exacompactive) {
    // For the tree.
    $compstmp = block_exaport_get_active_comps_for_item($existing);
    if ($compstmp && is_array($compstmp) && array_key_exists('descriptors', $compstmp)) {
        $existing->compids_array = array_keys($compstmp['descriptors']);
    } else {
        $existing->compids_array = [];
    }
    // For form.
    $existing->compids = join(',', $existing->compids_array);
}
$cattype_params = '';
if ($cattype) {
    $catuser = $DB->get_field('block_exaportcate', 'userid', ['id' => $categoryid]);
    $cattype_params = '&type=shared&userid=' . $catuser;
}
$returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&categoryid=" . $categoryid . $cattype_params;

// Delete item.
if ($action == 'delete' && $allowedit) {
    if (!$existing) {
        print_error("bookmarknotfound", "block_exaport");
    }
    if (data_submitted() && $confirm && confirm_sesskey()) {
        require_sesskey();
        block_exaport_do_delete($existing, $returnurl, $courseid);
        redirect($returnurl);
    } else {
        $optionsyes = array('id' => $id, 'action' => 'delete', 'confirm' => 1, 'backtype' => $backtype, 'categoryid' => $categoryid,
            'sesskey' => sesskey(), 'courseid' => $courseid);
        $optionsno = array('userid' => $existing->userid, 'courseid' => $courseid, 'type' => $backtype,
            'categoryid' => $categoryid);
        if ($cattype == 'shared') {
            $optionsyes['cattype'] = 'shared';
            $optionsno['type'] = 'shared';
            // change user to category owner
            $optionsno['userid'] = $catuser;
        }

        block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), $action);
        // Ev. noch eintrag anzeigen!!!
        // blog _print _entry ( $existing);.
        echo '<br />';

        echo $OUTPUT->confirm(get_string("delete" . $type . "confirm", "block_exaport"), new moodle_url('item.php', $optionsyes),
            new moodle_url('view_items.php', $optionsno));
        echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer();
        die;
    }
}

if ($action == 'movetocategory' && $allowedit) {
    require_sesskey();

    if (!$existing) {
        die(block_exaport_get_string('bookmarknotfound'));
    }

    if (!$targetcategory = block_exaport_get_category(required_param('categoryid', PARAM_INT))) {
        die('target category not found');
    }

    $DB->update_record('block_exaportitem', (object)array(
        'id' => $existing->id,
        'categoryid' => $targetcategory->id,
    ));

    echo 'ok';
    exit;
}

require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_user::instance($USER->id));

$usetextareas = [];
foreach (POSSIBLE_IFRAME_FIELDS as $itemfield) {
    $usetextareas[$itemfield] = false;
    if ($existing && $existing->{$itemfield} && preg_match('!<iframe!i', $existing->{$itemfield})) {
        $usetextareas[$itemfield] = true;
    }
}

$categoryidforform = $categoryid;
if ($cattype == 'shared' && $categoryid === 0) {
    $categoryidforform = $existing->categoryid;
}
$editform = new block_exaport_item_edit_form($_SERVER['REQUEST_URI'] . '&type=' . $type,
    array('current' => $existing, 'useTextareas' => $usetextareas, 'textfieldoptions' => $textfieldoptions, 'course' => $course,
        'type' => $type, 'action' => $action, 'allowedit' => $allowedit, 'allowresubmission' => $allowresubmission, 'cattype' => $cattype, 'catid' => $categoryidforform));

if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($editform->no_submit_button_pressed()) {
    die("nosubmitbutton");
} else if (($fromform = $editform->get_data()) && $allowedit) {
    require_sesskey();

    switch ($action) {
        case 'add':
            $fromform->type = $type;
            $fromform->compids = $compids;

            block_exaport_do_add($fromform, $editform, $returnurl, $courseid, $textfieldoptions, $usetextareas);
            break;

        case 'edit':
            $fromform->type = $type;
            if (!$existing) {
                print_error("bookmarknotfound", "block_exaport");
            }

            block_exaport_do_edit($fromform, $editform, $returnurl, $courseid, $textfieldoptions, $usetextareas);
            break;

        default:
            print_error("unknownaction", "block_exaport");
    }

    redirect($returnurl);
}

$straction = "";
$extracontent = '';
// Gui setup.
$post = new stdClass();
$post->introformat = FORMAT_HTML;
$post->project_descriptionformat = FORMAT_HTML;
$post->project_processformat = FORMAT_HTML;
$post->project_resultformat = FORMAT_HTML;
$post->allowedit = $allowedit;

switch ($action) {
    case 'add':
        $post->action = $action;
        $post->courseid = $courseid;
        $post->categoryid = $categoryid;

        $straction = get_string('new');
        break;
    case 'edit':
        if (!$existing) {
            print_error("bookmarknotfound", "block_exaport");
        }
        $post->id = $existing->id;
        $post->name = $existing->name;
        $post->intro = $existing->intro;
        $post->project_description = $existing->project_description;
        $post->project_process = $existing->project_process;
        $post->project_result = $existing->project_result;
        $post->categoryid = $existing->categoryid;
        $post->userid = $existing->userid;
        $post->action = $action;
        $post->courseid = $courseid;
        $post->type = $existing->type;
        $post->compids = isset($existing->compids) ? $existing->compids : '';
        $post->langid = $existing->langid;
        if (!empty($CFG->usetags)) {
            if ($CFG->branch < 31) {
                $post->tags = tag_get_tags_array('block_exaportitem', $id);
            } else {
                $post->tags = core_tag_tag::get_item_tags_array('block_exaport', 'block_exaportitem', $post->id, core_tag_tag::BOTH_STANDARD_AND_NOT);
            }
        }

        foreach ($usetextareas as $fieldname => $usetextarea) {
            if (!$usetextarea) {
                $post = file_prepare_standard_editor($post, $fieldname, $textfieldoptions, context_user::instance($USER->id),
                    'block_exaport', 'item_content_' . $fieldname, $post->id);
            }
        }

        $straction = get_string('edit');
        $post->url = $existing->url;
        if ($type == 'file') {
            $file = block_exaport_get_item_files($post, false);
            $filelimit = 1;
            if ($CFG->block_exaport_multiple_files_in_item) {
                $filelimit = 10;
            }
            if ($file) {
                if (!is_array($file)) {
                    $file = array($file);
                }
                $extracontent = "<div class='block_eportfolio_center'>\n";
                foreach ($file as $fileindex => $fileobject) {
                    if (!$fileobject) {
                        continue;
                    }
                    $ffurl = "{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=portfolio/id/" . $post->userid . "&itemid=" .
                        $post->id . '&inst=' . $fileindex;

                    if ($fileobject->is_valid_image()) {
                        $extracontent .= "<div class=\"item-detail-image\"><img src=\"$ffurl\" alt=\"" . format_string($post->name) .
                            "\" /></div>";
                    } else {
                        $icon = $OUTPUT->pix_icon(file_file_icon($fileobject), '');
                        $extracontent .= "<p>" . $icon . ' ' .
                            $OUTPUT->action_link($ffurl, format_string($post->name), new popup_action ('click', $ffurl)) . "</p>";
                    }

                    // Filemanager for editing file.
                    $draftitemid = file_get_submitted_draft_itemid('file');
                    $context = context_user::instance($USER->id);
                    file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'item_file', $post->id,
                        array('subdirs' => false, 'maxfiles' => $filelimit, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
                    $post->file = $draftitemid;
                }
                $extracontent .= "</div>";
            }
            if (!$extracontent && !$post->url) {
                $extracontent = 'File not found';
            }
        }

        // Filemanager for editing icon picture.
        $draftitemid = file_get_submitted_draft_itemid('iconfile');
        $context = context_user::instance($USER->id);
        file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'item_iconfile', $post->id,
            array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        $post->iconfile = $draftitemid;

        break;
    default :
        print_error("unknownaction", "block_exaport");
}

$exacompactive = block_exaport_check_competence_interaction() && $descriptorselection;

if ($exacompactive) {
    $PAGE->requires->jquery();

    $PAGE->requires->js('/blocks/exaport/javascript/simpletreemenu.js', true);
    $PAGE->requires->css('/blocks/exaport/javascript/simpletree.css');

    $PAGE->requires->js('/blocks/exaport/javascript/jquery.colorbox.js', true);
    // $PAGE->
    $PAGE->requires->js('/blocks/exaport/javascript/jquery.colorbox.js', true);
    $PAGE->requires->css('/blocks/exaport/css/colorbox.css');
}

block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), $action);

if ($exacompactive) {
    echo '<fieldset id="general" style="border: 1px solid #ddd; margin: 10px;">';
    echo '<legend class="ftoggler"><b>' . get_string("competences", "block_exaport") . '</b></legend>';
    if (file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php')) {
        echo "<p style='margin-left: 5px;'><a class='competences' href='#'>" . get_string("selectcomps", "block_exaport") . "</a>";
    } else {
        echo "<p style='margin-left: 5px;'" . get_string("competences_old_version", "block_exaport");
    }
    echo "<div style='margin-left: 5px;' id='comptitles'></div></p>";
    echo '</fieldset>';
    ?>
    <div style="display: none">
        <div id='inline_comp_tree' style='padding: 10px; background: #fff;'>
            <h4>
                <?php echo get_string("opencomps", "block_exaport") ?>
            </h4>

            <a href="javascript:ddtreemenu.flatten('comptree', 'expand')"><?php echo get_string("expandcomps", "block_exaport") ?>
            </a> | <a href="javascript:ddtreemenu.flatten('comptree', 'contact')"><?php echo get_string("contactcomps",
                    "block_exaport") ?>
            </a>

            <?php echo block_exaport_build_comp_tree('item', $existing, $allowedit); ?>
        </div>
    </div>

    <script type="text/javascript">
        //<![CDATA[
        jQueryExaport(function ($) {

            $('#treeform :checkbox').click(function (e) {
                // Prevent item open/close.
                e.stopPropagation();
            });

            var $compids = $('input[name=compids]');
            var $descriptors = $('#treeform :checkbox');

            $(".competences").colorbox({
                width: "75%", height: "75%", inline: true, href: "#inline_comp_tree", onClosed: function () {
                    // Save ids to input field.
                    var compids = '';
                    $descriptors.filter(':checked').each(function () {
                        compids += this.value + ',';
                    });
                    $compids.val(compids);

                    build_competence_output();
                }
            });
            ddtreemenu.createTree("comptree", true);

            function build_competence_output() {
                var $tree = $('#comptree').clone();
                // Remove original id, conflicts with real tree.
                $tree.attr('id', 'comptree-selected');

                // Delete all not checked.
                $tree.find('li').each(function () {
                    if (!$(this).find(':checked').length) {
                        $(this).remove();
                    }
                });

                // Delete checkboxes.
                $tree.find(':checkbox').remove();

                $("#comptitles").empty().append($tree);
                ddtreemenu.createTree("comptree-selected", false);

                // Open all.
                ddtreemenu.flatten('comptree-selected', 'expand');
            }

            build_competence_output();
        });
        //]]>
    </script>
    <?php
}

$editform->set_data($post);
echo $OUTPUT->box($extracontent);
$editform->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

/**
 * Update item in the database
 */
function block_exaport_do_edit($post, $blogeditform, $returnurl, $courseid, $textfieldoptions, $usetextareas) {
    global $CFG, $USER, $DB;

    // Convert the type into the type by post data:
    block_exaport_convert_item_type($post);

    $post->timemodified = time();
    foreach ($usetextareas as $fieldname => $usetextarea) {
        if (!$usetextarea) {
            $post->{$fieldname . 'format'} = FORMAT_HTML;
            $post = file_postupdate_standard_editor($post, $fieldname, $textfieldoptions, context_user::instance($USER->id),
                'block_exaport', 'item_content_' . $fieldname, $post->id);
        }
    }

    if (!empty($post->url)) {
        if ($post->url == 'http://') {
            $post->url = "";
        } else if (strpos($post->url, 'http://') === false && strpos($post->url, 'https://') === false) {
            $post->url = "http://" . $post->url;
        }
    }

    $context = context_user::instance($USER->id);
    // Updating file.
    if ($post->type == 'file') {
        // Checking userquoata.
        $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->file);
        if (block_exaport_file_userquotecheck($uploadfilesizes, $post->id) &&
            block_exaport_get_maxfilesize_by_draftid_check($post->file)
        ) {
            file_save_draft_area_files($post->file, $context->id, 'block_exaport', 'item_file', $post->id,
                array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        };
    }

    // Icon for item.
    // Checking userquoata.
    $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->iconfile);
    if (block_exaport_file_userquotecheck($uploadfilesizes, $post->id) &&
        block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)
    ) {
        file_save_draft_area_files($post->iconfile, $context->id, 'block_exaport', 'item_iconfile', $post->id,
            array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
    };

    if ($DB->update_record('block_exaportitem', $post)) {
        block_exaport_add_to_log(SITEID, 'bookmark', 'update', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=edit',
            $post->name);
    } else {
        print_error('updateposterror', 'block_exaport', $returnurl);
    }
    $interaction = block_exaport_check_competence_interaction();
    if ($interaction) {
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $post->id, "eportfolioitem" => 1));
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
            array("activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
        $comps = $post->compids;
        if ($comps) {
            $comps = explode(",", $comps);
            $course = $DB->get_record('course', array("id" => $courseid));

            foreach ($comps as $comp) {
                if ($comp != 0) {
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1,
                            "activitytitle" => $post->name, "coursetitle" => $course->shortname));
                }
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                    array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id,
                        "userid" => $USER->id, "role" => 0));
            }
        }
    }

    // Tags.
    if ($CFG->branch < 31) {
        // Moodle before v3.1.
        tag_set('block_exaportitem', $post->id, $post->tags, 'block_exaport', context_user::instance($USER->id)->id);
    } else {
        // Moodle v3.1.
        core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $post->id, context_user::instance($USER->id),
            $post->tags);
    }

}

/**
 * Write a new item into database
 */
function block_exaport_do_add($post, $blogeditform, $returnurl, $courseid, $textfieldoptions, $usetextareas) {
    global $CFG, $USER, $DB;

    $post->userid = $USER->id;
    $post->timemodified = time();
    $post->courseid = $courseid;

    // Convert 'mixed' type into correct type by post data:
    if ($post->type == 'mixed') {
        block_exaport_convert_item_type($post);
    }

    if (!empty($post->url)) {
        if ($post->url == 'http://') {
            $post->url = "";
        } else if (strpos($post->url, 'http://') === false && strpos($post->url, 'https://') === false) {
            $post->url = "http://" . $post->url;
        }
    }

    foreach ($usetextareas as $fieldname => $usetextarea) {
        if (!$usetextarea) {
            $post->{$fieldname} = '';
        }
    }

    // Insert the new entry.
    if ($post->id = $DB->insert_record('block_exaportitem', $post)) {
        $postupdate = false;
        foreach ($usetextareas as $fieldname => $usetextarea) {
            if (!$usetextarea) {
                $post->{$fieldname . 'format'} = FORMAT_HTML;
                $post = file_postupdate_standard_editor($post, $fieldname, $textfieldoptions, context_user::instance($USER->id),
                    'block_exaport', 'item_content_' . $fieldname, $post->id);
                $postupdate = true;
            }
        }

        if ($postupdate) {
            $DB->update_record('block_exaportitem', $post);
        }

        $context = context_user::instance($USER->id);
        if ($post->type == 'file') {
            // Save uploaded file in user filearea
            // checking userquoata.
            $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->file);
            if (block_exaport_file_userquotecheck($uploadfilesizes, $post->id) &&
                block_exaport_get_maxfilesize_by_draftid_check($post->file)
            ) {
                file_save_draft_area_files($post->file, $context->id, 'block_exaport', 'item_file', $post->id,
                    array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
            }
        }

        // Icon picture.
        if ($post->iconfile) {
            // Checking userquoata.
            $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->iconfile);
            if (block_exaport_file_userquotecheck($uploadfilesizes, $post->id) &&
                block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)
            ) {
                file_save_draft_area_files($post->iconfile, $context->id, 'block_exaport', 'item_iconfile', $post->id,
                    array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
            }
        }

        $comps = $post->compids;
        if ($comps) {
            $comps = explode(",", $comps);
            $course = $DB->get_record('course', array("id" => $courseid));

            foreach ($comps as $comp) {
                if ($comp != 0) {
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1,
                            "activitytitle" => $post->name, "coursetitle" => $course->shortname));
                }
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                    array("compid" => $comp, "activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id,
                        "userid" => $USER->id, "role" => 0));
            }
        }
        block_exaport_add_to_log(SITEID, 'bookmark', 'add', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=add',
            $post->name);

        // Tags.
        if ($CFG->branch < 31) {
            // Moodle before v3.1.
            tag_set('block_exaportitem', $post->id, $post->tags, 'block_exaport', context_user::instance($USER->id)->id);
        } else {
            // Moodle v3.1.
            core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $post->id, context_user::instance($USER->id),
                $post->tags);
        }
    } else {
        print_error('addposterror', 'block_exaport', $returnurl);
    }
}

/**
 * Delete item from database
 */
function block_exaport_do_delete($post, $returnurl = "", $courseid = 0) {

    global $DB, $USER;

    // Try to delete the item file.
    block_exaport_file_remove($post);

    $conditions = array("id" => $post->id);
    $status = $DB->delete_records('block_exaportitem', $conditions);

    $interaction = block_exaport_check_competence_interaction();
    if ($interaction) {
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $post->id, "eportfolioitem" => 1));
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
            array("activityid" => $post->id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
        $DB->delete_records(BLOCK_EXACOMP_DB_ITEM_MM, array('itemid' => $post->id));
    }

    block_exaport_add_to_log(SITEID, 'blog', 'delete', 'item.php?courseid=' . $courseid . '&id=' . $post->id . '&action=delete&confirm=1',
        $post->name);

    if (!$status) {
        print_error('deleteposterror', 'block_exaport', $returnurl);
    }
}

function block_exaport_convert_item_type(&$post) {
    // 1. default type is 'note'
    $post->type = 'note';
    // 2. Check 'url' data.
    if (!empty($post->url) && $post->url) {
        $post->type = 'link';
    }
    // 3. Check 'file' uploading.
    if (!empty($post->file)) {
        $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->file);
        if ($uploadfilesizes > 0) {
            $post->type = 'file';
        }
    }
}
