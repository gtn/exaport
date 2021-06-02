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

require_once(__DIR__.'/inc.php');

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

    $isForPdf = false;
    $pdfforuserid = 0;
    if ($p = array_search('forPdf', $args) ) {
        // added to link of the file: /forPdf/--hash--/--viewid--/--curruserid--
        $pdfforuserid = array_pop($args);
        $viewid = array_pop($args);
        $pdfHash = array_pop($args);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid]);
        if ($view && $view->hash == $pdfHash) {
            $isForPdf = true;
        }
        unset($args[$p]);
    }

    if (!$isForPdf) {
        // Always require login, at least guest.
        require_login();
    } else {
        // login is not required if it it for PDF generation (accessible only from php)
    }

    if ($filearea == 'item_file') {
        $filename = array_pop($args);
        $id = array_pop($args);
        if (array_pop($args) != 'itemid') {
            print_error('wrong params');
        }
        // Other params together are the access string.
        $access = join('/', $args);

        // Item exists?
        $item = block_exaport_get_item($id, $access, false, $isForPdf, $pdfforuserid);
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
    } else if ($filearea == 'item_content') {
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
    } else if ($filearea == 'view_content') {
        $filename = array_pop($args);

        // Other params together are the access string.
        $access = join('/', $args);

        if (!$view = block_exaport_get_view_from_access($access)) {
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
    } else if ($filearea == 'personal_information_view') {
        $filename = array_pop($args);

        // Other params together are the access string.
        $access = join('/', $args);

        if (!$view = block_exaport_get_view_from_access($access)) {
            print_error("viewnotfound", "block_exaport");
        }

        // View has personal information?
        $sql = "SELECT b.* FROM {block_exaportviewblock} b".
                " WHERE b.viewid=? AND".
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
    } else if ($filearea == 'personal_information_self') {
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
    } else if ($filearea == 'category_icon') {
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
    } else if (in_array($filearea,
                    array('resume_cover', 'resume_interests', 'resume_edu', 'resume_employ', 'resume_certif', 'resume_public',
                            'resume_mbrship')) ||
            in_array($filearea,
                    array('resume_goalspersonal', 'resume_goalsacademic', 'resume_goalscareers', 'resume_skillspersonal',
                            'resume_skillsacademic', 'resume_skillscareers')) ||
            in_array($filearea, array('resume_editor_goalspersonal', 'resume_editor_goalsacademic', 'resume_editor_goalscareers',
                    'resume_editor_skillspersonal', 'resume_editor_skillsacademic', 'resume_editor_skillscareers'))
    ) {
        $filename = array_pop($args);
        $id = array_pop($args);

        // Get file.
        $fs = get_file_storage();
        $file = $fs->get_file(context_user::instance($USER->id)->id, 'block_exaport', $filearea, $id, '/', $filename);

        // Serve file.
        if ($file) {
            send_stored_file($file);
        } else {
            return false;
        }
    } else {
        die('wrong file area');
    }
}
