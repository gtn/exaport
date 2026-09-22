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
require_once(__DIR__ . '/lib/externlib.php');
require_once(__DIR__ . '/lib/item_competence_helpers.php');
require_once(__DIR__ . '/blockmediafunc.php');

use function block_exaport\common\print_error;

$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$commentid = optional_param('commentid', 0, PARAM_INT);
$commentdelete = optional_param('comment_delete', 0, PARAM_INT);
$commentedit = optional_param('comment_edit', 0, PARAM_INT);
$backtype = optional_param('backtype', 0, PARAM_TEXT);

$context = context_system::instance();
$PAGE->set_context($context);
require_login(0, true);

$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/blocks/exaport/javascript/vedeo-js/video.js'), true);
$PAGE->requires->css('/blocks/exaport/javascript/vedeo-js/video-js.css');
$item = block_exaport_get_item($itemid, $access);

if (!$item) {
    print_error("bookmarknotfound", "block_exaport");
}

$item->intro = process_media_url($item->intro, 320, 240);

if ($commentdelete) {
    require_sesskey();

    $conditions = array("id" => $commentid, "userid" => $USER->id, "itemid" => $itemid);
    if ($DB->count_records("block_exaportitemcomm", $conditions) == 1) {
        $DB->delete_records("block_exaportitemcomm", $conditions);

        $fs = get_file_storage();
        if ($file = block_exaport_get_item_comment_file($commentid)) {
            // This deletes the file and the directory entry.
            $fs->delete_area_files($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid());
        }

    } else {
        $actionis = optional_param('action', 0, PARAM_BOOL);
        if (!$actionis) {
            // If comment_delete is set and form is submitted, comment was immediatly deleted and cant be deleted anymore, no error.
            print_error("commentnotfound", "block_exaport");
        }
    }
}

$conditions = array("id" => $item->userid);
if (!$user = $DB->get_record("user", $conditions)) {
    print_error("nouserforid", "block_exaport");
}

$url = '/blocks/exaport/shared_item.php';
$PAGE->set_url($url);

if ($item->allowComments) {
    require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

    if (block_exaport_check_competence_interaction()) {
        $itemexample = $DB->get_record(BLOCK_EXACOMP_DB_ITEM_MM, array('itemid' => $itemid));
        $teachervalue = $itemexample ? $itemexample->teachervalue : 0;
    } else {
        $teachervalue = 0;
    }

    $commentseditform = new block_exaport_comment_edit_form($PAGE->url,
        array('gradingpermission' => block_exaport_has_grading_permission($itemid), 'itemgrade' => $teachervalue,
            'is_editing' => ($commentedit > 0)));

    if ($commentseditform->is_cancelled()) {
        $tempvar = 1; // For code checker.
    } else if ($commentseditform->no_submit_button_pressed()) {
        $tempvar = 1; // For code checker.
    } else if ($fromform = $commentseditform->get_data()) {
        switch ($action) {
            case 'add':
                block_exaport_do_add_comment($item, $fromform);

                $prms = 'access=' . $access . '&itemid=' . $itemid;
                if (!empty($backtype)) {
                    $prms .= '&backtype=' . $backtype;
                }
                redirect($CFG->wwwroot . '/blocks/exaport/shared_item.php?' . $prms);
                break;
            case 'edit':
                require_sesskey();
                $conditions = array('id' => $commentid, 'userid' => $USER->id, 'itemid' => $itemid);
                if ($DB->count_records('block_exaportitemcomm', $conditions) == 1) {
                    $updatecomment = new stdClass();
                    $updatecomment->id = $commentid;
                    $updatecomment->entry = $fromform->entry['text'];
                    $updatecomment->timemodified = time();
                    $DB->update_record('block_exaportitemcomm', $updatecomment);

                    $fs = get_file_storage();
                    if (isset($fromform->file) && $fromform->file !== '') {
                        $draftitemid = (int)$fromform->file;
                        $draftfiles = $fs->get_area_files(context_user::instance($USER->id)->id,
                            'user', 'draft', $draftitemid, 'id', false);
                        if ($draftfiles) {
                            $contextid = context_system::instance()->id;
                            $fs->delete_area_files($contextid, 'block_exaport', 'item_comment_file', $commentid);
                            file_save_draft_area_files($draftitemid, $contextid, 'block_exaport', 'item_comment_file', $commentid);
                        }
                    }
                }
                $prms = 'access=' . $access . '&itemid=' . $itemid;
                if (!empty($backtype)) {
                    $prms .= '&backtype=' . $backtype;
                }
                redirect($CFG->wwwroot . '/blocks/exaport/shared_item.php?' . $prms);
                break;
        }
    }
}

if ($item->access->page == 'view') {
    if ($item->access->request == 'intern') {
        block_exaport_print_header("views");
    } else {
        block_exaport_print_header("shared_views");
    }
} else if ($item->access->page == 'category') {
    // External category access: use simple page header (similar to shared_view.php).
    $PAGE->set_context(context_system::instance());
    $PAGE->requires->css('/blocks/exaport/css/shared_view.css');
    $categoryowner = $DB->get_record('user', ['id' => $item->userid, 'deleted' => 0]);
    $PAGE->set_title(get_string('externaccess', 'block_exaport'));
    // set_heading() does not auto-escape, so escape the user-controlled name to prevent XSS.
    $PAGE->set_heading(get_string('externaccess', 'block_exaport') .
        ($categoryowner ? ' ' . s(fullname($categoryowner)) : ''));
    echo $OUTPUT->header();
} else if ($item->access->page == 'portfolio') {
    if ($item->userid == $USER->id) {
        block_exaport_print_header("myportfolio");
    } else {
        block_exaport_print_header("shared_categories");
    }
}

echo block_exaport_wrapperdivstart();

// IF FORM DATA -> INSERT.
$data = optional_param_array('data', array(), PARAM_RAW);
if (count($data) > 0) {
    foreach ($data as $key => $desc) {
        if (!empty($data[$key])) {
            // Die Einträge in ein Array speichern.
            $values[] = $key;
        }
    }
    block_exaport_set_competences($values, $item, $USER->id);
}
echo "<div>\n";
block_exaport_print_extern_item($item, $access);

echo block_exaport_render_item_competence_summary($item);

if ($item->allowComments) {
    $newcomment = new stdClass();
    if ($commentedit > 0) {
        $editingcomment = $DB->get_record('block_exaportitemcomm',
            array('id' => $commentedit, 'userid' => $USER->id, 'itemid' => $itemid));
        if ($editingcomment) {
            $newcomment->action = 'edit';
            $newcomment->commentid = $editingcomment->id;
            $newcomment->itemid = $itemid;
            $newcomment->entry = array('text' => $editingcomment->entry, 'format' => FORMAT_HTML);
            $draftitemid = file_get_submitted_draft_itemid('file');
            if ($existingfile = block_exaport_get_item_comment_file($editingcomment->id)) {
                file_prepare_draft_area($draftitemid, $existingfile->get_contextid(), 'block_exaport',
                    'item_comment_file', $editingcomment->id);
            }
            $newcomment->file = $draftitemid;
        }
    }
    if (!isset($newcomment->action)) {
        $newcomment->action = 'add';
        $newcomment->courseid = $COURSE->id;
        $newcomment->timemodified = time();
        $newcomment->itemid = $itemid;
        $newcomment->userid = $USER->id;
        $newcomment->access = $access;
        $newcomment->backtype = $backtype;
    }

    block_exaport_show_comments($item, $access, $backtype, $commentedit > 0 ? $commentedit : 0);

    $commentseditform->set_data($newcomment);
    $commentseditform->display();

    if ($commentedit > 0) {
        $prms = 'access=' . urlencode($access) . '&itemid=' . $itemid;
        if (!empty($backtype)) {
            $prms .= '&backtype=' . urlencode($backtype);
        }
        $cancelurl = $CFG->wwwroot . '/blocks/exaport/shared_item.php?' . $prms;
        echo '<p><a href="' . s($cancelurl) . '">' . block_exaport_get_string('cancel_edit_comment') . '</a></p>';
    }
} else if ($item->showComments) {
    block_exaport_print_extcomments($item->id);
}

if ($item->access->page == 'view') {
    $backlink = 'shared_view.php?access=' . $item->access->parentAccess;
} else if ($item->access->page == 'category') {
    $backlink = 'view_items.php?access=' . $item->access->parentAccess;
} else {
    // Intern.
    if ($item->userid == $USER->id) {
        $backlink = '';
    }
    $backlink = '';
}

if ($backlink) {
    echo "<br /><a href=\"{$CFG->wwwroot}/blocks/exaport/" . $backlink . "\">" . get_string("back", "block_exaport") . "</a><br /><br />";
}

echo "</div>";
echo block_exaport_wrapperdivend();

echo $OUTPUT->footer();

function block_exaport_show_comments($item, $access, $backtype = '', $editingcommentid = 0) {
    global $CFG, $USER, $COURSE, $DB, $OUTPUT;

    // Build a clean base URL from known parameters to avoid URL growth and injection.
    $baseparams = 'access=' . urlencode($access) . '&itemid=' . (int)$item->id;
    if (!empty($backtype)) {
        $baseparams .= '&backtype=' . urlencode($backtype);
    }
    $baseurl = $CFG->wwwroot . '/blocks/exaport/shared_item.php?' . $baseparams;

    $conditions = array("itemid" => $item->id);
    $comments = $DB->get_records("block_exaportitemcomm", $conditions, 'timemodified DESC');

    if ($comments) {
        $templatecomments = [];
        foreach ($comments as $comment) {
            // When editing a comment, only render that specific comment.
            if ($editingcommentid > 0 && $comment->id != $editingcommentid) {
                continue;
            }

            $picture = '';
            if ($comment->userid == -1) {
                $author = s(block_exaport_get_comment_author_name($comment->userid));
            } else {
                $user = $DB->get_record('user', array('id' => $comment->userid));
                $picture = $OUTPUT->user_picture($user, ['size' => 48]);
                $authorurl = new moodle_url('/user/view.php', [
                    'id' => $comment->userid,
                    'course' => $COURSE->id,
                ]);
                $author = html_writer::link($authorurl, s(block_exaport_get_comment_author_name($comment->userid)));
            }
            $by = new stdClass();
            $by->name = $author;
            $by->date = userdate($comment->timemodified);
            $templatecomment = [
                'picture' => $picture,
                'authorline' => get_string('bynameondate', 'forum', $by),
                'content' => format_text($comment->entry),
                'canmanage' => $comment->userid == $USER->id && $editingcommentid == 0,
                'editurl' => $baseurl . '&comment_edit=' . $comment->id,
                'deleteurl' => $baseurl . '&commentid=' . $comment->id . '&comment_delete=1&sesskey=' . sesskey(),
                'editlabel' => block_exaport_get_string('editcomment'),
                'deletelabel' => block_exaport_get_string('delete'),
                'deleteonclick' => s('return confirm(' .
                    json_encode(block_exaport_get_string('comment_delete_confirmation')) . ')'),
            ];
            if ($file = block_exaport_get_item_comment_file($comment->id)) {
                $fileurl = $CFG->wwwroot .
                    "/blocks/exaport/portfoliofile.php?access={$access}&itemid={$item->id}&commentid={$comment->id}";
                $templatecomment['file'] = [
                    'url' => $fileurl,
                    'name' => $file->get_filename(),
                    'size' => display_size($file->get_filesize()),
                    'label' => get_string('file', 'block_exaport'),
                ];
            }
            $templatecomments[] = $templatecomment;
        }

        echo $OUTPUT->render_from_template('block_exaport/shared_item_comments', [
            'heading' => get_string('comments', 'block_exaport'),
            'comments' => $templatecomments,
        ]);
    }
}

function block_exaport_do_add_comment($item, $post) {
    global $USER, $COURSE, $DB;

    $post->userid = $USER->id;
    $post->timemodified = time();
    $post->course = $COURSE->id;
    $post->entry = $post->entry["text"];

    if (block_exaport_has_grading_permission($item->id) && isset($post->itemgrade)) {
        $itemexample = $DB->get_record(BLOCK_EXACOMP_DB_ITEM_MM, array('itemid' => $item->id));
        if ($itemexample) {
            $itemexample->teachervalue = $post->itemgrade;
            $DB->update_record(BLOCK_EXACOMP_DB_ITEM_MM, $itemexample);

            // Check for example additional info and set it.
            $exampleeval = $DB->get_record(BLOCK_EXACOMP_DB_EXAMPLEEVAL,
                array('courseid' => $item->courseid, 'exampleid' => $itemexample->exampleid, 'studentid' => $item->userid));
            if ($exampleeval) {
                $exampleeval->additionalinfo = $post->itemgrade;
                $DB->update_record(BLOCK_EXACOMP_DB_EXAMPLEEVAL, $exampleeval);
            }
        }
    }

    // Insert the new comment.
    $post->id = $DB->insert_record('block_exaportitemcomm', $post);

    file_save_draft_area_files($post->file, context_system::instance()->id, 'block_exaport', 'item_comment_file', $post->id,
        array('subdirs' => 0, 'maxfiles' => 1));

    block_exaport_add_to_log(SITEID, 'exaport', 'add', 'view_item.php?type=' . $item->type, $post->entry);
}
