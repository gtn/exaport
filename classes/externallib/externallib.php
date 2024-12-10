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

namespace block_exaport\externallib;

defined('MOODLE_INTERNAL') || die();

require(__DIR__ . '/../../inc.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/weblib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

use block_exaport\globals as g;
use context_course;
use context_user;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use stdClass;

class externallib extends \external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_items_parameters() {
        return new external_function_parameters([
            'level' => new external_value(PARAM_INT, 'id of level/parent category'),
            'type' => new external_value(PARAM_TEXT, 'shared or own category or all', VALUE_DEFAULT, 'category'),
        ]);
    }

    /**
     * Returns categories and items for a particular level
     *
     * @disabled-ws-type-read
     * @param int level
     * @return array of course subjects
     */
    public static function get_items($level, $type) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER, $COURSE;

        $params = self::validate_parameters(self::get_items_parameters(), array('level' => $level, 'type' => $type));

        $results = array();

        if ($type == "all" || $type == "category" || $level == 0) {
            $conditions = array("pid" => $level, "userid" => $USER->id);
            $categories = $DB->get_records("block_exaportcate", $conditions);

            // RW add courseid if there is one:
            // better: when creating

            foreach ($categories as $category) {
                $result = new stdClass();
                $result->id = $category->id;
                $result->name = $category->name;
                $result->type = "category";
                $result->parent = $category->pid;
                $result->courseid = $category->courseid ? $category->courseid : 0;

                $result->amount = self::block_exaport_count_items($category->id, 0);

                $results[] = $result;
            }

            $items = $DB->get_records("block_exaportitem", array("userid" => $USER->id, "categoryid" => $level), '',
                'id,name,type, 0 as parent, 0 as amount');
            //         foreach($items as $item){ //to avoid a missing required key in single structure error
            //             $item->courseid = 0;
            //         }
        }

        if ($type == "all" || $type == "shared" || $level == 0) {
            if ($level == 0) {
                // Shared categories:
                $sqlsort = " ORDER BY c.name, u.lastname, u.firstname";
                $usercats = block_exaport_get_group_share_categories($USER->id);
                $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
                $sharedcategories = block_exaport_get_shared_categories($categorycolumns, $usercats, $sqlsort);

                foreach ($sharedcategories as $category) {
                    $result = new stdClass();
                    $result->id = $category->id;
                    $result->name = $category->name;
                    $result->type = "shared";
                    $result->parent = $category->pid;
                    $result->courseid = $category->courseid ? $category->courseid : 0;
                    $result->owneruserid = $category->userid;

                    $result->amount = self::block_exaport_count_items($category->id, 0);

                    $results[] = $result;
                }
            } else { // subcategories of the clicked shared category
                $conditions = array("pid" => $level); // ommit the userid, since it is shared TODO: security?
                $categories = $DB->get_records("block_exaportcate", $conditions);

                foreach ($categories as $category) {
                    $result = new stdClass();
                    $result->id = $category->id;
                    $result->name = $category->name;
                    $result->type = "shared";
                    $result->parent = $category->pid;
                    $result->courseid = $category->courseid ? $category->courseid : 0;
                    $result->owneruserid = $category->userid;

                    $result->amount = self::block_exaport_count_items($category->id, 0);

                    $results[] = $result;
                }

                // same as for "category" but without the userid constraint
                $items = $DB->get_records("block_exaportitem", array("categoryid" => $level), '',
                    'id,name,type, 0 as parent, 0 as amount, userid as owneruserid');

            }
        }

        $results = array_merge($results, $items);

        return $results;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_items_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of item'),
                    'name' => new external_value(PARAM_TEXT, 'title of item'),
                    'type' => new external_value(PARAM_TEXT, 'title of item (note,file,link,category,shared)'),
                    'parent' => new external_value(PARAM_TEXT, 'iff item is a cat, parent-cat is returned'),
                    'amount' => new external_value(PARAM_INT,
                        'iff item is a cat, amount of items in the category, otherwise 0'),
                    'courseid' => new external_value(PARAM_INT, 'id of the course this category belongs to', VALUE_OPTIONAL),
                    'owneruserid' => new external_value(PARAM_INT, 'userid of the owner of this category, if it is a category', VALUE_OPTIONAL),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_item_parameters() {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'id of item'),
            'owneruserid' => new external_value(PARAM_INT, 'id of owner of this file (needed for items in shared categories', VALUE_DEFAULT, null),
        ]);
    }


    /**
     * Returns detailed information for a particular item
     *
     * @disabled-ws-type-read
     * @param int itemid
     * @return array of course subjects
     */
    public static function get_item($itemid, $owneruserid = null) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_item_parameters(), array('itemid' => $itemid, 'owneruserid' => $owneruserid));

        $shared_item = false;
        if ($owneruserid) {
            $userid = $owneruserid;
            if ($owneruserid != $USER->id) {
                $shared_item = true; // this item is not the Item of the USER, but of somewone who shared it... needed later on
            }
        } else {
            $userid = $USER->id;
        }

        $conditions = array("id" => $itemid, "userid" => $userid);
        $item = $DB->get_record("block_exaportitem", $conditions, 'id,userid,type,categoryid,name,intro,url', MUST_EXIST);
        $category = $DB->get_field("block_exaportcate", "name", array("id" => $item->categoryid));

        if (!$category) {
            $category = "Hauptkategorie";
        }

        $item->category = $category;
        $item->file = "";
        $item->isimage = false;
        $item->filename = "";
        $item->mimetype = "";
        $item->intro = format_text($item->intro, FORMAT_HTML);

        if ($item->type == 'file') {
            if ($file = block_exaport_get_item_single_file($item)) {
                if ($shared_item) {
                    $item->file = "{$CFG->wwwroot}/blocks/exaport/shared_item.php?access=portfolio/id/" . $userid .
                        "&itemid=" . $item->id . "&wstoken=" . static::wstoken();
                } else {
                    $item->file = "{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=portfolio/id/" . $userid .
                        "&itemid=" . $item->id . "&wstoken=" . static::wstoken();
                }

                $item->isimage = $file->is_valid_image();
                $item->filename = $file->get_filename();
                $item->mimetype = $file->get_mimetype();
            }
        }

        $item->comments = g::$DB->get_records('block_exaportitemcomm', ['itemid' => $item->id], 'timemodified ASC');
        foreach ($item->comments as $comment) {
            // TODO: optimize: read user only once, or maybe add to sql statement?
            $user = $DB->get_record('user', ['id' => $comment->userid]);
            $comment->userfullname = $user ? fullname($user) : '';
        }

        return $item;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function get_item_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'id of item'),
                'name' => new external_value(PARAM_TEXT, 'title of item'),
                'type' => new external_value(PARAM_TEXT, 'type of item (note,file,link,category)'),
                'category' => new external_value(PARAM_TEXT, 'title of category'),
                'url' => new external_value(PARAM_TEXT, 'url'),
                'intro' => new external_value(PARAM_RAW, 'description of item'),
                'filename' => new external_value(PARAM_TEXT, 'title of item'),
                'file' => new external_value(PARAM_URL, 'file url'),
                'isimage' => new external_value(PARAM_BOOL, 'true if file is image'),
                'mimetype' => new external_value(PARAM_TEXT, 'mimetype'),
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT),
                            'userid' => new external_value(PARAM_INT),
                            'userfullname' => new external_value(PARAM_TEXT),
                            'timemodified' => new external_value(PARAM_INT),
                            'entry' => new external_value(PARAM_TEXT),
                        )
                    )
                ),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function add_item_parameters() {
        return new external_function_parameters([
            'title' => new external_value(PARAM_TEXT, 'item title'),
            'categoryid' => new external_value(PARAM_INT, 'categoryid'),
            'url' => new external_value(PARAM_URL, 'url'),
            'intro' => new external_value(PARAM_RAW, 'introduction'),
            'type' => new external_value(PARAM_TEXT, 'type of item (note,file,link)', VALUE_DEFAULT, ''),
            'fileitemid' => new external_value(PARAM_INT, 'itemid for draft-area files; for "private" files is ignored',
                VALUE_DEFAULT, null),
            'filename' => new external_value(PARAM_TEXT, 'deprecated (was used for upload into private files)', VALUE_DEFAULT,
                ''),
        ]);
    }

    /**
     * Adds a new item to the users portfolio
     *
     * @disabled-ws-type-write
     * @param int itemid
     * @return array of course subjects
     */
    public static function add_item($title, $categoryid, $url, $intro, $type, $fileitemid, $filename) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $DB, $USER;

        $params = self::validate_parameters(self::add_item_parameters(),
            array('title' => $title, 'categoryid' => $categoryid, 'url' => $url, 'intro' => $intro, 'type' => $type,
                'fileitemid' => $fileitemid, 'filename' => $filename));

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $file = null;

        if (!$file && $fileitemid) {
            $file = current($fs->get_area_files($context->id, "user", "draft", $fileitemid, null, false));
        }
        if (!$file && $filename) {
            $file = $fs->get_file($context->id, "user", "private", 0, "/", $filename);
        }

        if (!$type) {
            if ($file) {
                $type = 'file';
            } else if ($url) {
                $type = 'link';
            } else {
                $type = 'note';
            }
        }

        $itemid = $DB->insert_record("block_exaportitem",
            array('userid' => $USER->id, 'name' => $title, 'categoryid' => $categoryid, 'url' => $url, 'intro' => $intro,
                'type' => $type, 'timemodified' => time()));

        // If a file is added we need to copy the file from the user/private filearea to block_exaport/item_file
        // with the itemid from above.
        if ($file) {
            $fs->create_file_from_storedfile(array(
                'contextid' => $context->id,
                'component' => 'block_exaport',
                'filearea' => 'item_file',
                'itemid' => $itemid,
            ), $file);
        }

        return ["success" => true];
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function add_item_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function update_item_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'item id'),
            'title' => new external_value(PARAM_TEXT, 'item title'),
            // TODO: categoryid for later?
            'url' => new external_value(PARAM_TEXT, 'url'),
            'intro' => new external_value(PARAM_RAW, 'introduction'),
            'type' => new external_value(PARAM_TEXT, 'type of item (note,file,link)', VALUE_DEFAULT, ''),
            'fileitemid' => new external_value(PARAM_INT,
                'itemid for draft-area files; for "private" files is ignored, use \'0\' to delete the file', VALUE_DEFAULT,
                null),
            'filename' => new external_value(PARAM_TEXT, 'deprecated (was used for upload into private files)', VALUE_DEFAULT,
                ''),
        ]);
    }

    /**
     * Edit an item from the users portfolio
     *
     * @disabled-ws-type-write
     * @param int itemid
     * @return array of course subjects
     */
    public static function update_item($id, $title, $url, $intro, $type, $fileitemid, $filename) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $DB, $USER;

        $params = self::validate_parameters(self::update_item_parameters(),
            array('id' => $id, 'title' => $title, 'url' => $url, 'intro' => $intro, 'type' => $type,
                'fileitemid' => $fileitemid, 'filename' => $filename));

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $file = null;

        if (!$file && $fileitemid) {
            $file = current($fs->get_area_files($context->id, "user", "draft", $fileitemid, null, false));
        }
        if (!$file && $filename) {
            $file = $fs->get_file($context->id, "user", "private", 0, "/", $filename);
        }

        if (!$type) {
            if ($file) {
                $type = 'file';
            } else if ($url) {
                $type = 'link';
            } else {
                $type = 'note';
            }
        }

        $record = new stdClass();
        $record->id = $id;
        $record->name = $title;
        $record->url = $url;
        $record->intro = $intro;
        $record->type = $type;

        $DB->update_record("block_exaportitem", $record);

        if ($file) {
            block_exaport_file_remove($DB->get_record("block_exaportitem", array("id" => $id)));

            $fs->create_file_from_storedfile(array(
                'contextid' => $context->id,
                'component' => 'block_exaport',
                'filearea' => 'item_file',
                'itemid' => $id,
            ), $file);
        } else if ($fileitemid === 0) {
            block_exaport_file_remove($DB->get_record("block_exaportitem", array("id" => $id)));
        }

        return ["success" => true];
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function update_item_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     */
    public static function delete_item_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'item id'),
        ]);
    }

    /**
     * Delete an item from the users portfolio
     *
     * @disabled-ws-type-write
     */
    public static function delete_item(int $id) {
        global $DB, $USER;

        [
            'id' => $id,
        ] = self::validate_parameters(self::delete_item_parameters(), [
            'id' => $id,
        ]);

        // check permission
        $item = $DB->get_record('block_exaportitem', [
            'id' => $id,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        block_exaport_file_remove($DB->get_record("block_exaportitem", array("id" => $id)));

        $DB->delete_records("block_exaportitem", array('id' => $id));

        $interaction = block_exaport_check_competence_interaction();
        if ($interaction) {
            $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $id, "eportfolioitem" => 1));
            $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                array("activityid" => $id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
        }

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function delete_item_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'status'),
        ));
    }

    public static function add_item_comment_parameters() {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT),
            'entry' => new external_value(PARAM_RAW),
        ]);
    }

    /**
     * Add a comment to an item
     *
     * @disabled-ws-type-read
     */
    public static function add_item_comment($itemid, $entry) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        $params = self::validate_parameters(self::add_item_comment_parameters(), ['itemid' => $itemid, 'entry' => $entry]);

        // TODO: check if can add comment.

        g::$DB->insert_record("block_exaportitemcomm", [
            'itemid' => $itemid,
            'userid' => g::$USER->id,
            'entry' => $entry,
            'timemodified' => time(),
        ]);

        return ["success" => true];
    }

    public static function add_item_comment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'status'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function list_competencies_parameters() {
        return new external_function_parameters([]);

    }

    /**
     * List all available competencies
     *
     * @disabled-ws-type-read
     * @return array of e-Portfolio views
     */
    public static function list_competencies() {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $courses = $DB->get_records('course', array());

        $descriptors = array();
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            if (is_enrolled($context, $USER)) {
                $query = "SELECT t.id as topdescrid, d.id,d.title,tp.title as topic,tp.id as topicid," .
                    " s.title as subject,s.id as subjectid,d.niveauid " .
                    " FROM {" . BLOCK_EXACOMP_DB_DESCRIPTORS . "} d, {" . BLOCK_EXACOMP_DB_COURSETOPICS . "} c, {" . BLOCK_EXACOMP_DB_DESCTOPICS . "} t, " .
                    " {" . BLOCK_EXACOMP_DB_TOPICS . "} tp, {" . BLOCK_EXACOMP_DB_SUBJECTS . "} s " .
                    " WHERE d.id=t.descrid AND t.topicid = c.topicid AND t.topicid=tp.id AND tp.subjid = s.id " .
                    " AND c.courseid = ?";

                $query .= " ORDER BY s.title,tp.title,d.sorting";
                $alldescr = $DB->get_records_sql($query, array($course->id));
                if (!$alldescr) {
                    $alldescr = array();
                }
                foreach ($alldescr as $descr) {
                    $descriptors[] = $descr;
                }
            }
        }

        $competencies = array();
        foreach ($descriptors as $descriptor) {
            if (!array_key_exists($descriptor->subjectid, $competencies)) {
                $competencies[$descriptor->subjectid] = new stdClass();
                $competencies[$descriptor->subjectid]->id = $descriptor->subjectid;
                $competencies[$descriptor->subjectid]->name = $descriptor->subject;
                $competencies[$descriptor->subjectid]->topics = array();
            }

            if (!array_key_exists($descriptor->topicid, $competencies[$descriptor->subjectid]->topics)) {
                $competencies[$descriptor->subjectid]->topics[$descriptor->topicid] = new stdClass();
                $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->id = $descriptor->topicid;
                $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->name = $descriptor->topic;
                $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->descriptors = array();
            }

            $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->descriptors[$descriptor->id] = new stdClass();
            $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->descriptors[$descriptor->id]->id = $descriptor->id;
            $temptitle = $descriptor->title; // Only for code checker.
            $competencies[$descriptor->subjectid]->topics[$descriptor->topicid]->descriptors[$descriptor->id]->name = $temptitle;

        }

        return $competencies;

    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function list_competencies_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of subject'),
                    'name' => new external_value(PARAM_TEXT, 'title of subject'),
                    'topics' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'id of topic'),
                                'name' => new external_value(PARAM_TEXT, 'title of topic'),
                                'descriptors' => new external_multiple_structure(
                                    new external_single_structure(
                                        array(
                                            'id' => new external_value(PARAM_INT,
                                                'id of descriptor'),
                                            'name' => new external_value(PARAM_TEXT,
                                                'name of descriptor'),
                                        )
                                    )
                                ),
                            )
                        )
                    ),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_item_competence_parameters() {
        return new external_function_parameters(
            array('itemid' => new external_value(PARAM_INT, 'item id'),
                'descriptorid' => new external_value(PARAM_INT, 'descriptor id'),
                'val' => new external_value(PARAM_INT, '1 to assign, 0 to unassign'),
            )
        );

    }

    /**
     * assign a competence to an item
     *
     * @disabled-ws-type-read
     * @param int itemid, descriptorid, val
     * @return array of course subjects
     */
    public static function set_item_competence($itemid, $descriptorid, $val) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::set_item_competence_parameters(),
            array('itemid' => $itemid, 'descriptorid' => $descriptorid, 'val' => $val));

        if ($val == 1) {
            $item = $DB->get_record("block_exaportitem", array("id" => $itemid));
            $course = $DB->get_record("course", array("id" => $item->courseid));
            $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                array('compid' => $descriptorid, 'activityid' => $itemid, 'eportfolioitem' => 1, 'activitytitle' => $item->name,
                    'coursetitle' => $course->shortname, 'comptype' => BLOCK_EXACOMP_TYPE_DESCRIPTOR));
            $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                array("compid" => $descriptorid, "activityid" => $itemid, "eportfolioitem" => 1, "reviewerid" => $USER->id,
                    "userid" => $USER->id, "role" => 0, 'comptype' => BLOCK_EXACOMP_TYPE_DESCRIPTOR));
        } else if ($val == 0) {
            $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                array('compid' => $descriptorid, 'activityid' => $itemid, 'eportfolioitem' => 1,
                    'comptype' => BLOCK_EXACOMP_TYPE_DESCRIPTOR));
            $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                array("compid" => $descriptorid, 'activityid' => $itemid, 'eportfolioitem' => 1,
                    'comptype' => BLOCK_EXACOMP_TYPE_DESCRIPTOR));
        }

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function set_item_competence_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_list_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Return available views
     *
     * @ws-type-read
     * @return array of e-Portfolio views
     */
    public static function view_list() {
        global $DB, $USER;

        self::validate_parameters(self::view_list_parameters(), []);

        $views = $DB->get_records("block_exaportview", ["userid" => $USER->id], 'name'); // , 'createdinapp' => 1]);

        $results = array();

        foreach ($views as $view) {
            $result = new stdClass();
            $result->id = $view->id;
            $result->name = $view->name;
            $result->description = $view->description;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function view_list_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of view'),
                    'name' => new external_value(PARAM_TEXT, 'title of view'),
                    'description' => new external_value(PARAM_RAW, 'description of view'),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_details_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'view id'),
        ]);
    }

    /**
     * Return detailed view
     *
     * @ws-type-read
     */
    public static function view_details(int $id) {
        global $DB, $USER;

        [
            'id' => $id,
        ] = self::validate_parameters(self::view_details_parameters(), [
            'id' => $id,
        ]);

        // checking the permission
        $view = $DB->get_record('block_exaportview', [
            'id' => $id,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $result = (object)[];
        $result->id = $view->id;
        $result->name = $view->name;
        $result->description = $view->description ?: '';
        $result->advanced_url = (new \moodle_url('/blocks/exaport/views_mod.php', ['action' => 'edit', 'courseid' => 1, 'id' => $view->id]))->out(false);
        $result->externaccess = $view->externaccess;
        if ($view->externaccess) {
            $result->external_url = block_exaport_get_external_view_url($view);
        }

        $blocks = $DB->get_records("block_exaportviewblock", array("viewid" => $id), 'positionx, positiony');

        $result->blocks = [];
        foreach ($blocks as $block) {
            $resultBlock = (object)[
                'id' => $block->id,
                'type' => $block->type,
                'itemid' => 0,
                'title' => $block->block_title,
                'text' => $block->text,
                'url' => '',
                'files' => [],
            ];

            if ($block->type == "item") {
                $conditions = array("id" => $block->itemid);
                $item = $DB->get_record("block_exaportitem", $conditions);
                if (!$item) {
                    // item missing, if there is incorrect db state
                    continue;
                }

                $item = static::make_item_result($item);

                $resultBlock->title = $item->name;
                $resultBlock->text = $item->description;
                $resultBlock->url = $item->url;
                $resultBlock->files = $item->files;
            }

            if ($block->type == 'headline') {
                // bei headline steht die headline im text feld, stattdessen in das title feld verschieben
                $resultBlock->title = $resultBlock->title ?: $resultBlock->text;
                $resultBlock->text = '';
            }

            $result->blocks[] = $resultBlock;
        }

        return $result;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_details_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'id of view'),
            'name' => new external_value(PARAM_TEXT, 'title of view'),
            'description' => new external_value(PARAM_TEXT, 'description of view'),
            'externaccess' => new external_value(PARAM_BOOL),
            'external_url' => new external_value(PARAM_TEXT, 'url for external (public) access', VALUE_OPTIONAL),
            'advanced_url' => new external_value(PARAM_TEXT),
            'blocks' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'id of block'),
                'type' => new external_value(PARAM_TEXT, 'ENUM(item,headline,text,other) there are other values also possible!'),
                'itemid' => new external_value(PARAM_INT, 'id of item'),
                'title' => new external_value(PARAM_TEXT, 'title'),
                'text' => new external_value(PARAM_RAW, 'description'),
                'url' => new external_value(PARAM_TEXT, 'url'),
                'files' => new external_multiple_structure(new external_single_structure([
                    'filename' => new external_value(PARAM_TEXT, 'filename'),
                    'url' => new external_value(PARAM_URL, 'file url'),
                    'mimetype' => new external_value(PARAM_TEXT, 'mime type for file'),
                ])),
            ])),
        ));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_add_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'view title'),
            'description' => new external_value(PARAM_TEXT, 'description'),
        ]);
    }

    /**
     * Add a new view to the users portfolio
     *
     * @ws-type-write
     */
    public static function view_add(string $name, string $description) {
        global $DB, $USER;

        [
            'name' => $name,
            'description' => $description,
        ] = self::validate_parameters(self::view_add_parameters(), [
            'name' => $name,
            'description' => $description,
        ]);

        // Generate view hash, external share is on by default!
        do {
            $hash = substr(md5(microtime()), 3, 8);
        } while ($DB->record_exists("block_exaportview", array("hash" => $hash)));

        // Add default PDF settings
        // only 'username' enabled
        $pdfsettings = [
            'showmetadata' => 1,
            'showusername' => 1,
        ];
        $pdfsettings = serialize($pdfsettings);

        $viewid = $DB->insert_record("block_exaportview", [
            'userid' => $USER->id,
            'name' => $name,
            'description' => $description,
            'timemodified' => time(),
            'externaccess' => 1,
            'externcomment' => 0,
            'hash' => $hash,
            'createdinapp' => 1,
            'pdf_settings' => $pdfsettings,
        ]);

        return ["success" => true, 'id' => $viewid];
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_add_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'status'),
            'id' => new external_value(PARAM_INT),
        ));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_update_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'view id'),
            'name' => new external_value(PARAM_TEXT, 'view title'),
            'description' => new external_value(PARAM_TEXT, 'description'),
            'externaccess' => new external_value(PARAM_BOOL, '', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Edit a view from the users portfolio
     *
     * @ws-type-write
     */
    public static function view_update(int $id, string $name, string $description, ?bool $externaccess) {
        global $DB, $USER;

        [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'externaccess' => $externaccess,
        ] = self::validate_parameters(self::view_update_parameters(), [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'externaccess' => $externaccess,
        ]);

        // check permission
        $DB->get_record('block_exaportview', [
            'id' => $id,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $record = new stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->description = $description;
        if ($externaccess !== null) {
            $record->externaccess = $externaccess;
        }

        $DB->update_record("block_exaportview", $record);

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_update_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'status'),
        ));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_delete_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Delete a view from the users portfolio
     *
     * @ws-type-write
     */
    public static function view_delete(int $id) {
        global $DB, $USER;

        [
            'id' => $id,
        ] = self::validate_parameters(self::view_delete_parameters(), [
            'id' => $id,
        ]);

        // check permission
        $DB->get_record('block_exaportview', [
            'id' => $id,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $DB->delete_records("block_exaportview", array("id" => $id));

        $DB->delete_records("block_exaportviewblock", array("viewid" => $id));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_delete_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'status'),
        ));
    }

    private static function wstoken() {
        return optional_param('wstoken', null, PARAM_ALPHANUM);
    }

    private static function get_items_for_category($categoryid) {
        $items = g::$DB->get_records("block_exaportitem", array("userid" => g::$USER->id, "categoryid" => $categoryid));

        $result_items = [];
        foreach ($items as $item) {
            $result_items[] = static::make_item_result($item);
        }

        return $result_items;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_all_user_items_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Return all items from user
     *
     * @ws-type-read
     */
    public static function get_all_user_items() {
        global $DB, $USER;

        self::validate_parameters(self::get_all_user_items_parameters(), []);

        $categories = $DB->get_records("block_exaportcate", array("userid" => $USER->id));

        $rootcategory = new stdClass();
        $rootcategory->id = 0;
        $rootcategory->pid = 0;
        $rootcategory->name = "Hauptkategorie";
        $rootcategory->items = static::get_items_for_category(0);
        array_unshift($categories, $rootcategory);

        foreach ($categories as $category) {
            $category->items = static::get_items_for_category($category->id);
        }

        return $categories;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_all_user_items_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of category'),
                    'pid' => new external_value(PARAM_TEXT, 'parentid'),
                    'name' => new external_value(PARAM_TEXT, 'title of category'),
                    'items' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'id of item'),
                                'name' => new external_value(PARAM_TEXT, 'title of item'),
                                'url' => new external_value(PARAM_TEXT, 'url'),
                                'description' => new external_value(PARAM_RAW, 'description of item'),
                                'files' => new external_multiple_structure(new external_single_structure([
                                    'filename' => new external_value(PARAM_TEXT, 'filename'),
                                    'url' => new external_value(PARAM_URL, 'file url'),
                                    'mimetype' => new external_value(PARAM_TEXT, 'mime type for file'),
                                ])),
                                // 'type' => new external_value(PARAM_TEXT, 'type of item ENUM(note,file,link)'),
                                // 'filename' => new external_value(PARAM_TEXT, 'title of item'),
                                // 'file' => new external_value(PARAM_URL, 'file url'),
                                // 'isimage' => new external_value(PARAM_BOOL, 'true if file is image'),
                                // 'mimetype' => new external_value(PARAM_TEXT, 'mimetype'),
                            )
                        )
                    ),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_block_add_parameters() {
        return new external_function_parameters([
            'viewid' => new external_value(PARAM_INT, 'view id'),
            'type' => new external_value(PARAM_TEXT, 'ENUM(item,headline,text)'),
            'itemid' => new external_value(PARAM_INT, 'item id', VALUE_DEFAULT, 0),
            'title' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'text' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Add item to a view
     *
     * @ws-type-write
     */
    public static function view_block_add(int $viewid, string $type, int $itemid, string $title, string $text) {
        global $DB, $USER;

        [
            'viewid' => $viewid,
            'type' => $type,
            'itemid' => $itemid,
            'title' => $title,
            'text' => $text,
        ] = static::validate_parameters(static::view_block_add_parameters(), [
            'viewid' => $viewid,
            'type' => $type,
            'itemid' => $itemid,
            'title' => $title,
            'text' => $text,
        ]);

        // check permissions
        $view = $DB->get_record('block_exaportview', [
            'id' => $viewid,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $newBlockData = null;

        if ($type == 'item') {
            // check values
            if ($title || $text) {
                throw new \moodle_exception('invalidparams - title and text must be empty for item');
            }

            // check permission
            $item = $DB->get_record('block_exaportitem', [
                'id' => $itemid,
                'userid' => $USER->id,
            ], '*', MUST_EXIST);

            $existingBlock = $DB->get_record("block_exaportviewblock", array("viewid" => $viewid, "itemid" => $itemid, "type" => "item"));

            if (!$existingBlock) {
                // only add once

                $newBlockData = [
                    "type" => "item",
                    "itemid" => $itemid,
                ];
            }
        } elseif ($type == 'headline') {
            if ($itemid || $text) {
                throw new \moodle_exception('invalidparams - itemid and text must be empty for headline');
            }

            $newBlockData = [
                'type' => 'headline',
                'block_title' => '',
                'text' => $title,
            ];
        } elseif ($type == 'text') {
            if ($itemid) {
                throw new \moodle_exception('invalidparams - itemid must be empty for text');
            }

            $newBlockData = [
                'type' => 'text',
                'block_title' => $title,
                'text' => $text,
            ];
        } else {
            throw new \moodle_exception('invalidparams - either itemid or header must be set');
        }

        if ($newBlockData) {
            $query = "SELECT MAX(positiony) from {block_exaportviewblock} WHERE viewid=?";
            $max = $DB->get_field_sql($query, array($viewid));
            $ycoord = intval($max) + 1;

            $blockid = $DB->insert_record("block_exaportviewblock", [
                    "viewid" => $viewid,
                    "positionx" => 1,
                    "positiony" => $ycoord,
                ] + $newBlockData);
        }

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_block_add_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_block_delete_parameters() {
        return new external_function_parameters([
            'viewid' => new external_value(PARAM_INT, 'view id'),
            'blockid' => new external_value(PARAM_INT, 'block id'),
        ]);
    }

    /**
     * Remove item from a view
     *
     * @ws-type-write
     */
    public static function view_block_delete($viewid, $blockid) {
        global $DB, $USER;

        [
            'viewid' => $viewid,
            'blockid' => $blockid,
        ] = static::validate_parameters(static::view_block_delete_parameters(), [
            'viewid' => $viewid,
            'blockid' => $blockid,
        ]);

        // check permissions
        $view = $DB->get_record('block_exaportview', [
            'id' => $viewid,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $DB->delete_records("block_exaportviewblock", array("viewid" => $viewid, "id" => $blockid));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_block_delete_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_grant_external_access_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'view id'),
                'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck'),
            )
        );
    }

    /**
     * Grant external access to a view
     *
     * @disabled-ws-type-write
     */
    public static function view_grant_external_access($id, $val) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::view_grant_external_access_parameters(), array('id' => $id, 'val' => $val));

        $record = new stdClass();
        $record->id = $id;

        if ($val == 0) {
            $record->externaccess = 0;
        } else {
            $record->externaccess = 1;
        }

        $record->externcomment = 0;
        $DB->update_record("block_exaportview", $record);

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_grant_external_access_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_get_available_users_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    /**
     * Get users who can get access
     *
     * @disabled-ws-type-read
     * @return all items available
     */
    public static function view_get_available_users() {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $mycourses = enrol_get_users_courses($USER->id, true);

        $usersincontext = array();
        foreach ($mycourses as $course) {
            $enrolledusers = get_enrolled_users(context_course::instance($course->id));
            foreach ($enrolledusers as $user) {
                if (!in_array($user, $usersincontext)) {
                    $usersincontext[] = $user;
                }
            }
        }

        $users = array();
        foreach ($usersincontext as $user) {
            $usertemp = new stdClass();
            $usertemp->id = $user->id;
            $usertemp->firstname = $user->firstname;
            $usertemp->lastname = $user->lastname;
            $users[] = $usertemp;
        }

        return $users;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function view_get_available_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of user'),
                    'firstname' => new external_value(PARAM_TEXT, 'firstname of user'),
                    'lastname' => new external_value(PARAM_TEXT, 'lastname of user'),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_grant_internal_access_all_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'view id'),
                'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck'),
            )
        );
    }

    /**
     * Grant internal access to a view to all users
     *
     * @disabled-ws-type-write
     */
    public static function view_grant_internal_access_all($id, $val) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::view_grant_internal_access_all_parameters(), array('id' => $id, 'val' => $val));

        $record = new stdClass();
        $record->id = $id;

        if ($val == 0) {
            $record->shareall = 0;
        } else {
            $record->shareall = 1;
        }

        $record->externcomment = 0;
        $DB->update_record("block_exaportview", $record);

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_grant_internal_access_all_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_grant_internal_access_parameters() {
        return new external_function_parameters(
            array(
                'viewid' => new external_value(PARAM_INT, 'view id'),
                'userid' => new external_value(PARAM_INT, 'user id'),
                'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck'),
            )
        );
    }

    /**
     * Grant internal access to a view to one user
     *
     * @disabled-ws-type-write
     */
    public static function view_grant_internal_access($viewid, $userid, $val) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::view_grant_internal_access_parameters(),
            array('viewid' => $viewid, 'userid' => $userid, 'val' => $val));

        if ($val == 1) {
            $blockid = $DB->insert_record("block_exaportviewshar", array("viewid" => $viewid, "userid" => $userid));
        }
        if ($val == 0) {
            $DB->delete_records("block_exaportviewshar", array("viewid" => $viewid, "userid" => $userid));
        }

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function view_grant_internal_access_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_category_parameters() {
        return new external_function_parameters(
            array(
                'categoryid' => new external_value(PARAM_INT, 'cat id'),
            )
        );

    }

    /**
     * Get category infor
     *
     * @disabled-ws-type-read
     * @return array of e-Portfolio views
     */
    public static function get_category($categoryid) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB;

        $cat = $DB->get_record("block_exaportcate", array("id" => $categoryid), "name");

        $amount = $DB->count_records('block_exaportitem', array('categoryid' => $categoryid));

        return array('name' => $cat->name, 'items' => $amount);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_category_returns() {
        return new external_single_structure(
            array(
                'name' => new external_value(PARAM_TEXT, 'title of category'),
                'items' => new external_value(PARAM_INT, 'amount of category items'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function delete_category_parameters() {
        return new external_function_parameters(
            array(
                'categoryid' => new external_value(PARAM_INT, 'cat id'),
            )
        );
    }

    /**
     * Delete category
     *
     * @disabled-ws-type-write
     */
    public static function delete_category($categoryid) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::delete_category_parameters(), array('categoryid' => $categoryid));

        self::block_exaport_recursive_delete_category($categoryid);

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function delete_category_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status'),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_competencies_by_item_parameters() {
        return new external_function_parameters(
            array(
                'itemid' => new external_value(PARAM_INT, 'item id'),
            )
        );
    }

    /**
     * Get competence ids for a ePortfolio item
     *
     * @disabled-ws-type-read
     * @return all items available
     */
    public static function get_competencies_by_item($itemid) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::get_competencies_by_item_parameters(), array('itemid' => $itemid));

        return $DB->get_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            array("activityid" => $itemid, "eportfolioitem" => 1, "comptype" => BLOCK_EXACOMP_TYPE_DESCRIPTOR), "",
            "compid as competenceid");
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_competencies_by_item_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'competenceid' => new external_value(PARAM_INT, 'id of competence'),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_users_by_view_parameters() {
        return new external_function_parameters(
            array(
                'viewid' => new external_value(PARAM_INT, 'view id'),
            )
        );
    }

    /**
     * Get view users
     *
     * @disabled-ws-type-read
     * @return all items available
     */
    public static function get_users_by_view($viewid) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_users_by_view_parameters(), array('viewid' => $viewid));

        return $DB->get_records("block_exaportviewshar", array("viewid" => $viewid));
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_users_by_view_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userid' => new external_value(PARAM_INT, 'id of user'),
                )
            )
        );
    }

    private static function block_exaport_count_items($categoryid, $items = 0) {
        global $DB;

        $items += $DB->count_records('block_exaportitem', array('categoryid' => $categoryid));

        foreach ($DB->get_records('block_exaportcate', array('pid' => $categoryid)) as $child) {
            $items += self::block_exaport_count_items($child->id, $items);
        }

        return $items;
    }

    private static function block_exaport_recursive_delete_category($id) {
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

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function export_file_to_externalportfolio_parameters() {
        return new external_function_parameters(
            array(
                'component' => new external_value(PARAM_RAW, 'filestorage - component'),
                'filearea' => new external_value(PARAM_RAW, 'filestorage - filearea'),
                'filename' => new external_value(PARAM_RAW, 'filestorage - filename'),
                'filepath' => new external_value(PARAM_RAW, 'filestorage - filepath'),
                'itemid' => new external_value(PARAM_INT, 'filestorage - itemid'),
            )
        );
    }

    /**
     * Export file to external portfolio
     *
     * @disabled-ws-type-write
     * @return all items available
     */
    public static function export_file_to_externalportfolio($component, $filearea, $filename, $filepath, $itemid) {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        if (!$CFG->block_exaport_app_externaleportfolio) {
            return array('success' => 'export_to_exaport', 'linktofile' => '');
        }

        $params = self::validate_parameters(self::export_file_to_externalportfolio_parameters(),
            array('component' => $component, 'filearea' => $filearea,
                'filename' => $filename, 'filepath' => $filepath, 'itemid' => $itemid));
        if (empty($component) || empty($filearea) || empty($filename) || empty($filepath)) {
            throw new invalid_parameter_exception('There is not enough parametersy');
        };
        // Preparing for transmission of data.

        $fileparams = $params;
        unset($fileparams['license']);
        unset($fileparams['author']);

        // Script for export.
        require_once($CFG->dirroot . '/blocks/exacomp/upload_externalportfolio.php');
        // Return variables from Global of upload_externalportfolio.php.
        return array('success' => $success, 'linktofile' => $result_querystring);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_multiple_structure
     */
    public static function export_file_to_externalportfolio_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_TEXT, 'status'),
                'linktofile' => new external_value(PARAM_TEXT, 'link to file'),
            )
        );
    }

    public static function get_user_information_parameters() {
        return new external_function_parameters([]);
    }

    /**
     *
     * @return array
     */
    public static function get_user_information() {
        require_once(g::$CFG->dirroot . "/user/lib.php");
        $data = user_get_user_details(g::$USER);
        unset($data['enrolledcourses']);
        unset($data['preferences']);

        return $data;
    }

    public static function get_user_information_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'ID of the user'),
            'username' => new external_value(PARAM_RAW, 'The username', VALUE_OPTIONAL),
            'firstname' => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
            'lastname' => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
            'fullname' => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
            'email' => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
            'firstaccess' => new external_value(PARAM_INT, 'first access to the site (0 if never)', VALUE_OPTIONAL),
            'lastaccess' => new external_value(PARAM_INT, 'last access to the site (0 if never)', VALUE_OPTIONAL),
            'auth' => new external_value(PARAM_PLUGIN, 'Auth plugins include manual, ldap, imap, etc', VALUE_OPTIONAL),
            'confirmed' => new external_value(PARAM_INT, 'Active user: 1 if confirmed, 0 otherwise', VALUE_OPTIONAL),
            'lang' => new external_value(PARAM_SAFEDIR, 'Language code such as "en", must exist on server', VALUE_OPTIONAL),
            'url' => new external_value(PARAM_URL, 'URL of the user', VALUE_OPTIONAL),
            'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version'),
            'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version'),
        ));
    }

    public static function login_parameters() {
        return new external_function_parameters(array(
            'app' => new external_value(PARAM_INT, 'app accessing this service (eg. dakora)'),
            'app_version' => new external_value(PARAM_INT, 'version of the app (eg. 4.6.0)'),
            'services' => new external_value(PARAM_INT, 'wanted webservice tokens (eg. exacomp,exaport)', VALUE_DEFAULT,
                'moodle_mobile_app,exaportservices'),
        ));
    }

    /**
     * Returns description of method return values
     *
     * @return external_multiple_structure
     */
    public static function login_returns() {
        return new external_single_structure([
            'user' => static::get_user_information_returns(),
            'config' => new external_single_structure([]),
            'tokens' => new external_multiple_structure(new external_single_structure([
                'service' => new external_value(PARAM_TEXT, 'name of service'),
                'token' => new external_value(PARAM_TEXT, 'token of the service'),
            ]), 'requested tokens'),
        ]);
    }

    /**
     * webservice called through token.php
     *
     * @ws-type-read
     * @return array
     */
    public static function login() {
        return [
            'user' => static::get_user_information(),
            'config' => (object)[],
        ];
    }


    public static function get_shared_categories_parameters() {
        return new external_function_parameters(array());
    }

    /**
     *
     * @disabled-ws-type-read
     * @return array
     */
    public static function get_shared_categories() {
        throw new \moodle_exception('disabled, old dakora code, needs security check!');

        global $CFG, $DB, $USER;

        $params = static::validate_parameters(self::get_shared_categories_parameters(), array());


        // Categories for user groups.
        $sqlsort = " ORDER BY c.name, u.lastname, u.firstname";
        $usercats = block_exaport_get_group_share_categories($USER->id);
        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        $categories = block_exaport_get_shared_categories($categorycolumns, $usercats, $sqlsort);

        return $categories;
    }

    /**
     * Returns description of method return values
     *
     * @return external_multiple_structure
     */
    public static function get_shared_categories_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of item'),
                    'name' => new external_value(PARAM_TEXT, 'title of item'),
                    'courseid' => new external_value(PARAM_INT, 'id of the course this category belongs to', VALUE_OPTIONAL),
                )
            )
        );
    }

    public static function view_block_add_multiple_parameters() {
        return new external_function_parameters(array(
            'viewid' => new external_value(PARAM_INT),
            'itemids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ));
    }

    /**
     * @ws-type-write
     */
    public static function view_block_add_multiple(int $viewid, array $itemids) {
        [
            'viewid' => $viewid,
            'itemids' => $itemids,
        ] = static::validate_parameters(static::view_block_add_multiple_parameters(), [
            'viewid' => $viewid,
            'itemids' => $itemids,
        ]);

        foreach ($itemids as $itemid) {
            static::view_block_add($viewid, 'item', $itemid, '', '');
        }

        return [
            'success' => true,
        ];
    }

    public static function view_block_add_multiple_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'status'),
        ]);
    }

    private static function make_item_result(object $item): object {
        $result_item = (object)[];
        $result_item->id = $item->id;
        $result_item->name = $item->name;
        $result_item->url = $item->url;

        // $result_item->type = $item->type;
        // $result_item->file = "";
        // $result_item->isimage = false;
        // $result_item->filename = "";
        // $result_item->mimetype = "";
        $result_item->description = format_text($item->intro, FORMAT_HTML);
        $result_item->files = [];

        foreach (block_exaport_get_item_files_array($item) as $file) {
            $result_file = (object)[];
            $result_file->url = g::$CFG->wwwroot . "/blocks/exaport/portfoliofile.php?access=portfolio/id/" . g::$USER->id . "&itemid=" . $item->id . "&wstoken=" . static::wstoken();
            // $result_file->isimage = $file->is_valid_image();
            $result_file->filename = $file->get_filename();
            $result_file->mimetype = $file->get_mimetype();
            $result_item->files[] = $result_file;
        }

        return $result_item;
    }

    public static function view_block_sorting_parameters() {
        return new external_function_parameters(array(
            'viewid' => new external_value(PARAM_INT),
            'blockids' => new external_multiple_structure(
                new external_value(PARAM_INT)
            ),
        ));
    }

    /**
     * @ws-type-write
     */
    public static function view_block_sorting(int $viewid, array $blockids) {
        global $DB, $USER;

        [
            'viewid' => $viewid,
            'blockids' => $blockids,
        ] = static::validate_parameters(static::view_block_sorting_parameters(), [
            'viewid' => $viewid,
            'blockids' => $blockids,
        ]);

        $view = $DB->get_record('block_exaportview', [
            'id' => $viewid,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $blocks = $DB->get_records("block_exaportviewblock", array("viewid" => $viewid));

        $sorting = 1;
        foreach ($blockids as $blockid) {
            if (empty($blocks[$blockid])) {
                // item not in this learningpath
                continue;
            }

            $DB->update_record('block_exaportviewblock', [
                'id' => $blockid,
                'positiony' => $sorting++,
            ]);
        }

        return [
            'success' => true,
        ];
    }

    public static function view_block_sorting_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'status'),
        ));
    }
}
