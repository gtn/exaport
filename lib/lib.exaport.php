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

namespace block_exaport;

use block_exaport\globals as g;

// Copy shared structure tree to user.
function copy_category_to_myself($categoryid) {
    $rootcat = g::$DB->get_record("block_exaportcate", array('id' => $categoryid));
    if (!$rootcat) {
        throw new moodle_exception('category not found');
    }

    return _copy_category_to_myself_iterator($rootcat, 0);
}

function _copy_category_to_myself_iterator($currcat, $parentcatid) {
    global $CFG, $USER;
    $newcat = new \stdClass();
    $newcat->pid = $parentcatid;
    $newcat->userid = g::$USER->id;
    if (!$parentcatid) {
        $newcat->name = get_string('copyof', 'badges', $currcat->name);
    } else {
        $newcat->name = $currcat->name;
    }
    $newcat->timemodified = $currcat->timemodified;
    $newcat->courseid = g::$COURSE->id;
    $newcat->description = $currcat->description;
    $newcat->id = g::$DB->insert_record("block_exaportcate", $newcat);

    $children = g::$DB->get_records("block_exaportcate", array('pid' => $currcat->id));
    foreach ($children as $category) {
        _copy_category_to_myself_iterator($category, $newcat->id);
    }

    $items = g::$DB->get_records('block_exaportitem', ['categoryid' => $currcat->id]);
    foreach ($items as $item) {
        $newitem = new \stdClass();
        $newitem->userid = g::$USER->id;
        $newitem->type = $item->type;
        $newitem->categoryid = $newcat->id;
        $newitem->name = $item->name;
        $newitem->url = $item->url;
        $newitem->intro = $item->intro;
        $newitem->attachment = $item->attachment;
        $newitem->timemodified = $item->timemodified;
        $newitem->courseid = g::$COURSE->id;
        $newitem->sortorder = $item->sortorder;

        $newitem->id = g::$DB->insert_record('block_exaportitem', $newitem);

        // Files.
        $fs = get_file_storage();
        if ($file = block_exaport_get_item_files($item)) {
            foreach ($file as $fileindex => $fileobject) {
                if ($fileobject) {
                    $fs->create_file_from_storedfile(array(
                        'contextid' => \context_user::instance(g::$USER->id)->id,
                        'component' => 'block_exaport',
                        'filearea' => 'item_file',
                        'itemid' => $newitem->id,
                    ), $fileobject);
                }
            }
        }
        if ($file = block_exaport_get_single_file($item, 'item_iconfile')) {
            $fs->create_file_from_storedfile(array(
                'contextid' => \context_user::instance(g::$USER->id)->id,
                'component' => 'block_exaport',
                'filearea' => 'item_iconfile',
                'itemid' => $newitem->id,
            ), $file);
        }

        // Comments.
        $comments = g::$DB->get_records("block_exaportitemcomm", ["itemid" => $item->id], 'timemodified DESC');
        foreach ($comments as $comment) {
            $newcomment = new \stdClass();
            $newcomment->itemid = $newitem->id;
            $newcomment->userid = $comment->userid;
            $newcomment->entry = $comment->entry;
            $newcomment->timemodified = $comment->timemodified;
        }

        // Tags.
        if (!empty($CFG->usetags)) {
            if ($CFG->branch < 31) {
                // Moodle before v3.1.
                include_once(g::$CFG->dirroot . '/tag/lib.php');
                $tags = tag_get_tags_array('block_exaportitem', $item->id);
                tag_set('block_exaportitem', $newitem->id, $tags, 'block_exaport', \context_user::instance(g::$USER->id)->id);
            } else {
                // Moodle v3.1.
                $tags = \core_tag_tag::get_item_tags_array('block_exaport', 'block_exaportitem', $item->id);
                \core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $newitem->id, \context_user::instance($USER->id),
                    $tags);
            }
        }
    }

    return $newcat;
}
