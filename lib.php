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

// Called from pluginfile.php
// to serve the file of a plugin
// urlformat:
// http://localhost/moodle20/pluginfile.php/17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// 17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// user context id (moodle standard)
// moudle name (moodle standard)
// file column name (moodle standard)
// access string according to exaport
// itemid (string)
// itemid
// file name.
function block_exaport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $USER, $CFG, $DB;

    $is_for_pdf = false;
    $pdfforuserid = 0;

    if ($p = array_search('forPdf', $args)) {
        // added to link of the file: /forPdf/--hash--/--viewid--/--curruserid--
        $pdfforuserid = array_pop($args);
        $viewid = array_pop($args);
        $pdf_hash = array_pop($args);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid]);
        if ($view && $view->hash == $pdf_hash) {
            $is_for_pdf = true;
        }
        unset($args[$p]);
    }

    if (!$is_for_pdf && $filearea != 'pdf_fontfamily') {
        // Always require login, at least guest.
        require_login();
    } else {
        // Login is not required if it is for PDF generation (accessible only from php).
        // Also if it is for getting custom .ttf font file
    }

    switch ($filearea) {
        case 'item_file':
            $filename = array_pop($args);
            $id = array_pop($args);
            if (array_pop($args) != 'itemid') {
                print_error('wrong params');
            }
            // Other params together are the access string.
            $access = join('/', $args);

            // Item exists?
            $item = block_exaport_get_item($id, $access, false, $is_for_pdf, $pdfforuserid);
            if (!$item) {
                print_error('Item not found');
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'item_content':
        case 'item_content_project_description':
        case 'item_content_project_process':
        case 'item_content_project_result':
            $filename = array_pop($args);
            $id = array_pop($args);
            if (array_pop($args) != 'itemid') {
                print_error('wrong params');
            }

            // Other params together are the access string.
            $access = join('/', $args);

            // Item exists?
            $item = block_exaport_get_item($id, $access);
            if (!$item) {
                print_error('Item not found');
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'view_content':
            $filename = array_pop($args);

            // Other params together are the access string.
            $access = join('/', $args);

            if (!$view = block_exaport_get_view_from_access($access, $is_for_pdf, $pdfforuserid)) {
                print_error("viewnotfound", "block_exaport");
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', $filearea, $view->id, '/', $filename);
            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'personal_information_view':
            $filename = array_pop($args);

            // Other params together are the access string.
            $access = join('/', $args);

            if (!$view = block_exaport_get_view_from_access($access)) {
                print_error("viewnotfound", "block_exaport");
            }

            // View has personal information?
            $sql = "SELECT b.* FROM {block_exaportviewblock} b" .
                " WHERE b.viewid=? AND" .
                " b.type='personal_information'";
            if (!$DB->record_exists_sql($sql, array($view->id))) {
                return false;
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', 'personal_information', $view->userid,
                '/', $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'personal_information_self':
            $filename = join('/', $args);

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', $USER->id, '/',
                $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'category_icon':
            $filename = array_pop($args);
            $categoryid = array_pop($args);

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($USER->id)->id, 'block_exaport', 'category_icon', $categoryid, '/',
                $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'resume_editor_cover':
        case 'resume_editor_goalspersonal':
        case 'resume_editor_goalsacademic':
        case 'resume_editor_goalscareers':
        case 'resume_editor_skillspersonal':
        case 'resume_editor_skillsacademic':
        case 'resume_editor_skillscareers':
        case 'resume_editor_interests':
            $resumeitemtypes = ['cover', 'interests', 'goalspersonal', 'goalsacademic', 'goalscareers', 'skillspersonal', 'skillsacademic', 'killscareers'];
            if ($is_for_pdf) {
                // $view is already defined
                // Simple checking: the view has a block with 'cover/goals.../skills...' CV?
                $sql = "SELECT b.* FROM {block_exaportviewblock} b" .
                    " WHERE b.viewid=? AND" .
                    " b.type IN ('cv_information', 'cv_group') AND b.resume_itemtype IN ('" . implode("', '", $resumeitemtypes) . "')";
                if (!$DB->record_exists_sql($sql, array($view->id))) {
                    print_error("viewnotfound", "block_exaport");
                }
                $viewuser = $view->userid;
            } else {
                $access = required_param('access', PARAM_RAW);
                if (strpos($access, 'resume') !== false) {
                    $resumeattrs = explode('/', $access);
                    $resumeid = $resumeattrs[1];
                    $resume = $DB->get_record('block_exaportresume', array("id" => $resumeid));
                    $viewuser = $resume->user_id;
                } else {
                    $view = block_exaport_get_view_from_access($access);
                    if (!$view) {
                        print_error("viewnotfound", "block_exaport");
                    }
                    // Simple checking: the view has a block with 'cover/goals.../skills...' CV?
                    $sql = "SELECT b.* FROM {block_exaportviewblock} b" .
                        " WHERE b.viewid=? AND" .
                        " b.type IN ('cv_information', 'cv_group') AND b.resume_itemtype IN ('" . implode("', '", $resumeitemtypes) . "')";
                    if (!$DB->record_exists_sql($sql, array($view->id))) {
                        print_error("viewnotfound", "block_exaport");
                    }
                    $viewuser = $view->userid;
                }
            }
        case 'resume_cover':
        case 'resume_interests':
        case 'resume_edu':
        case 'resume_employ':
        case 'resume_certif':
        case 'resume_public':
        case 'resume_mbrship':
        case 'resume_goalspersonal':
        case 'resume_goalsacademic':
        case 'resume_goalscareers':
        case 'resume_skillspersonal':
        case 'resume_skillsacademic':
        case 'resume_skillscareers':
            if (!$viewuser) {
                $viewuser = $USER->id;
            }
            $filename = array_pop($args);
            $id = array_pop($args);

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($viewuser)->id, 'block_exaport', $filearea, $id, '/', $filename);
            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        case 'pdf_fontfamily':
            $filename = array_pop($args);
            $itemid = array_pop($args); // Always 0.

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_system::instance()->id, 'block_exaport', 'pdf_fontfamily', $itemid, '/', $filename);

            // Serve file.
            if ($file) {
                send_stored_file($file);
            } else {
                return false;
            }
            break;
        default:
            die('wrong file area');
    }
}
