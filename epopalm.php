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

$user = checkhash();
$gotologin = false;
if (!$user) {
    $gotologin = true;
} else {
    if ($user->auth == 'nologin' || $user->suspended != 0 || $user->deleted != 0) {
        $gotologin = true;
    } else {
        if ((!isloggedin())) {
            complete_user_login($user);
        }

        // If user is enrolled to a course with exaport, redirect to it
        // else redirect to moodle root.
        $mycourses = enrol_get_my_courses();
        $mycourses[] = $DB->get_record('course', array('id' => 1));

        foreach ($mycourses as $mycourse) {
            $mycoursecontext = context_course::instance($mycourse->id);

            if ($DB->record_exists('block_instances', array('blockname' => 'exaport', 'parentcontextid' => $mycoursecontext->id))) {
                redirect($CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$mycourse->id);
                die;
            }
        }
        unset($mycourses);
        unset($mycoursecontext);

        redirect($CFG->wwwroot);
        die;
    }
}
if ($gotologin == true) {
    $logurl = get_login_url();
    redirect($CFG->wwwroot);
    die;
}

function checkhash() {
    global $DB;
    global $USER;
    $userhash = optional_param('key', 0, PARAM_ALPHANUM);
    $sql = "SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long='".$userhash.
            "'";
    if (!$user = $DB->get_record_sql($sql)) {
        return false;
    } else {
        $USER = $user;
        return $user;
    }
}
