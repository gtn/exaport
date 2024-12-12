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

echo 'course id: ' . $COURSE->id . "<br />\n";

// Desp block installed?
if (!is_dir(__DIR__ . '/../desp')) {
    die ('no desp installed');
}

$context = context_course::instance($COURSE->id);
if (!$DB->record_exists('block_instances', array('blockname' => 'desp', 'parentcontextid' => $context->id))) {
    die('no desp in course!');
}

if ($DB->record_exists('block_exaportcate', array('userid' => $USER->id))) {
    die('user has categories');
}

die ('create categories!');
