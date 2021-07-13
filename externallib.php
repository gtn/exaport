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

defined('MOODLE_INTERNAL') || die();

require(__DIR__.'/inc.php');
require_once($CFG->libdir.'/externallib.php');
require_once($CFG->libdir.'/weblib.php');
require_once($CFG->dirroot.'/lib/filelib.php');

use block_exaport\globals as g;

class block_exaport_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_items_parameters() {
        return new external_function_parameters([
            'level' => new external_value(PARAM_INT, 'id of level/parent category'),
            'type' => new external_value(PARAM_TEXT, 'shared or own category or all', VALUE_DEFAULT, 'category')
        ]);
    }

    /**
     * Returns categories and items for a particular level
     *
     * @ws-type-read
     * @param int level
     * @return array of course subjects
     */
    public static function get_items($level, $type) {
        global $CFG, $DB, $USER, $COURSE;

        $params = self::validate_parameters(self::get_items_parameters(), array('level' => $level, 'type' => $type));


        $results = array();

        if($type == "all" || $type == "category" || $level == 0){
            $conditions = array("pid" => $level, "userid" => $USER->id);
            $categories = $DB->get_records("block_exaportcate", $conditions);

            //RW add courseid if there is one:
            //better: when creating

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

        if($type == "all" || $type == "shared" || $level == 0) {
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
                                'courseid' => new external_value(PARAM_INT, 'id of the course this category belongs to',VALUE_OPTIONAL),
                                'owneruserid' => new external_value(PARAM_INT, 'userid of the owner of this category, if it is a category', VALUE_OPTIONAL)
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
                'owneruserid' => new external_value(PARAM_INT, 'id of owner of this file (needed for items in shared categories', VALUE_OPTIONAL),
        ]);
    }


    /**
     * Returns detailed information for a particular item
     *
     * @ws-type-read
     * @param int itemid
     * @return array of course subjects
     */
    public static function get_item($itemid, $owneruserid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_item_parameters(), array('itemid' => $itemid, 'owneruserid' => $owneruserid));

        $shared_item = false;
        if($owneruserid){
            $userid = $owneruserid;
            if($owneruserid != $USER->id){
                $shared_item = true; // this item is not the Item of the USER, but of somewone who shared it... needed later on
            }
        }else{
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
                if($shared_item){
                    $item->file = "{$CFG->wwwroot}/blocks/exaport/shared_item.php?access=portfolio/id/".$userid.
                        "&itemid=".$item->id."&wstoken=".static::wstoken();
                }else{
                    $item->file = "{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=portfolio/id/".$userid.
                        "&itemid=".$item->id."&wstoken=".static::wstoken();
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
                'filename' => new external_value (PARAM_TEXT, 'deprecated (was used for upload into private files)', VALUE_DEFAULT,
                        ''),
        ]);
    }

    /**
     * Adds a new item to the users portfolio
     *
     * @ws-type-write
     * @param int itemid
     * @return array of course subjects
     */
    public static function add_item($title, $categoryid, $url, $intro, $type, $fileitemid, $filename) {
        global $DB, $USER;

        $params = self::validate_parameters(self::add_item_parameters(),
                array('title' => $title, 'categoryid' => $categoryid, 'url' => $url, 'intro' => $intro, 'type' => $type,
                        'fileitemid' => $fileitemid, 'filename' => $filename));

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $file = null;

        if (!$file && $fileitemid) {
            $file = reset($fs->get_area_files($context->id, "user", "draft", $fileitemid, null, false));
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
                'filename' => new external_value (PARAM_TEXT, 'deprecated (was used for upload into private files)', VALUE_DEFAULT,
                        ''),
        ]);
    }

    /**
     * Edit an item from the users portfolio
     *
     * @ws-type-write
     * @param int itemid
     * @return array of course subjects
     */
    public static function update_item($id, $title, $url, $intro, $type, $fileitemid, $filename) {
        global $DB, $USER;

        $params = self::validate_parameters(self::update_item_parameters(),
                array('id' => $id, 'title' => $title, 'url' => $url, 'intro' => $intro, 'type' => $type,
                        'fileitemid' => $fileitemid, 'filename' => $filename));

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $file = null;

        if (!$file && $fileitemid) {
            $file = reset($fs->get_area_files($context->id, "user", "draft", $fileitemid, null, false));
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
     *
     * @return external_function_parameters
     */
    public static function delete_item_parameters() {
        return new external_function_parameters(
                array('id' => new external_value(PARAM_INT, 'item id'))
        );
    }

    /**
     * Delete an item from the users portfolio
     *
     * @ws-type-write
     * @param int itemid
     * @return array of course subjects
     */
    public static function delete_item($id) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::delete_item_parameters(), array('id' => $id));

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
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'status'),
                )
        );
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
     * @ws-type-read
     */
    public static function add_item_comment($itemid, $entry) {
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
        return new external_function_parameters(
                array()
        );

    }

    /**
     * List all available competencies
     *
     * @ws-type-read
     * @return array of e-Portfolio views
     */
    public static function list_competencies() {
        global $CFG, $DB, $USER;

        $courses = $DB->get_records('course', array());

        $descriptors = array();
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            if (is_enrolled($context, $USER)) {
                $query = "SELECT t.id as topdescrid, d.id,d.title,tp.title as topic,tp.id as topicid,".
                            " s.title as subject,s.id as subjectid,d.niveauid ".
                            " FROM {".BLOCK_EXACOMP_DB_DESCRIPTORS."} d, {".BLOCK_EXACOMP_DB_COURSETOPICS."} c, {".BLOCK_EXACOMP_DB_DESCTOPICS."} t, ".
                            " {".BLOCK_EXACOMP_DB_TOPICS."} tp, {".BLOCK_EXACOMP_DB_SUBJECTS."} s ".
                            " WHERE d.id=t.descrid AND t.topicid = c.topicid AND t.topicid=tp.id AND tp.subjid = s.id ".
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
     * @ws-type-read
     * @param int itemid, descriptorid, val
     * @return array of course subjects
     */
    public static function set_item_competence($itemid, $descriptorid, $val) {
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
    public static function get_views_parameters() {
        return new external_function_parameters(
                array()
        );

    }

    /**
     * Return available views
     *
     * @ws-type-read
     * @return array of e-Portfolio views
     */
    public static function get_views() {
        global $CFG, $DB, $USER;

        $conditions = array("userid" => $USER->id);
        $views = $DB->get_records("block_exaportview", $conditions);

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
    public static function get_views_returns() {
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
    public static function get_view_parameters() {
        return new external_function_parameters(
                array('id' => new external_value(PARAM_INT, 'view id'))
        );
    }

    /**
     * Return detailed view
     *
     * @ws-type-read
     * @param int id
     * @return detailed view including list of items
     */
    public static function get_view($id) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_view_parameters(), array('id' => $id));

        $conditions = array("id" => $id);
        $view = $DB->get_record("block_exaportview", $conditions);

        $result->id = $view->id;
        $result->name = $view->name;
        $result->description = $view->description;

        $conditions = array("viewid" => $id);
        $items = $DB->get_records("block_exaportviewblock", $conditions);

        $result->items = array();
        foreach ($items as $item) {
            if ($item->type == "item") {
                $conditions = array("id" => $item->itemid);
                $itemdb = $DB->get_record("block_exaportitem", $conditions);

                $resultitem = new stdClass();
                $resultitem->id = $itemdb->id;
                $resultitem->name = $itemdb->name;
                $resultitem->type = $itemdb->type;
                $result->items[] = $resultitem;
            }
        }

        return $result;
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function get_view_returns() {
        return new external_single_structure(
                array(
                        'id' => new external_value(PARAM_INT, 'id of view'),
                        'name' => new external_value(PARAM_TEXT, 'title of view'),
                        'description' => new external_value(PARAM_RAW, 'description of view'),
                        'items' => new external_multiple_structure(
                                new external_single_structure(
                                        array(
                                                'id' => new external_value(PARAM_INT, 'id of item'),
                                                'name' => new external_value(PARAM_TEXT, 'title of item'),
                                                'type' => new external_value(PARAM_TEXT, 'title of item (note,file,link,category)'),
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
    public static function add_view_parameters() {
        return new external_function_parameters(
                array(
                        'name' => new external_value(PARAM_TEXT, 'view title'),
                        'description' => new external_value(PARAM_TEXT, 'description'),
                )
        );
    }

    /**
     * Add a new view to the users portfolio
     *
     * @ws-type-write
     * @param String name, String description
     * @return success
     */
    public static function add_view($name, $description) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::add_view_parameters(), array('name' => $name, 'description' => $description));

        $viewid = $DB->insert_record("block_exaportview",
                array('userid' => $USER->id, 'name' => $name, 'description' => $description, 'timemodified' => time()));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function add_view_returns() {
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
    public static function update_view_parameters() {
        return new external_function_parameters(
                array(
                        'id' => new external_value(PARAM_INT, 'view id'),
                        'name' => new external_value(PARAM_TEXT, 'view title'),
                        'description' => new external_value(PARAM_TEXT, 'description'),
                )
        );
    }

    /**
     * Edit a view from the users portfolio
     *
     * @ws-type-write
     * @param int id, String name, String description
     * @return success
     */
    public static function update_view($id, $name, $description) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::update_view_parameters(),
                array('id' => $id, 'name' => $name, 'description' => $description));

        $record = new stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->description = $description;
        $DB->update_record("block_exaportview", $record);

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function update_view_returns() {
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
    public static function delete_view_parameters() {
        return new external_function_parameters(
                array(
                        'id' => new external_value(PARAM_INT, 'view id'),
                )
        );
    }

    /**
     * Delete a view from the users portfolio
     *
     * @ws-type-write
     * @param int id
     * @return success
     */
    public static function delete_view($id) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::delete_view_parameters(), array('id' => $id));

        $DB->delete_records("block_exaportview", array("id" => $id));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function delete_view_returns() {
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
    public static function get_all_items_parameters() {
        return new external_function_parameters(
                array()
        );
    }

    private static function wstoken() {
        return optional_param('wstoken', null, PARAM_ALPHANUM);
    }

    public static function get_items_for_category($categoryid) {
        $items = g::$DB->get_records("block_exaportitem", array("userid" => g::$USER->id, "categoryid" => $categoryid));
        foreach ($items as $item) {
            $item->file = "";
            $item->isimage = false;
            $item->filename = "";
            $item->mimetype = "";
            $item->intro = format_text($item->intro, FORMAT_HTML);

            if ($item->type == 'file') {
                if ($file = block_exaport_get_item_single_file($item)) {
					$item->file = g::$CFG->wwwroot."/blocks/exaport/portfoliofile.php?access=portfolio/id/".g::$USER->id."&itemid=".
							$item->id."&wstoken=".static::wstoken();
					$item->isimage = $file->is_valid_image();
					$item->filename = $file->get_filename();
					$item->mimetype = $file->get_mimetype();
                }
            }
        }

        return $items;
    }

    /**
     * Return all items, independent from level
     *
     * @ws-type-read
     * @return all items available
     */
    public static function get_all_items() {
        global $DB, $USER;

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
    public static function get_all_items_returns() {
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
                                                        'type' => new external_value(PARAM_TEXT,
                                                                'type of item (note,file,link,category)'),
                                                        'url' => new external_value(PARAM_TEXT, 'url'),
                                                        'intro' => new external_value(PARAM_RAW, 'description of item'),
                                                        'filename' => new external_value(PARAM_TEXT, 'title of item'),
                                                        'file' => new external_value(PARAM_URL, 'file url'),
                                                        'isimage' => new external_value(PARAM_BOOL, 'true if file is image'),
                                                        'mimetype' => new external_value(PARAM_TEXT, 'mimetype'),
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
    public static function add_view_item_parameters() {
        return new external_function_parameters(
                array(
                        'viewid' => new external_value(PARAM_INT, 'view id'),
                        'itemid' => new external_value(PARAM_INT, 'item id'),
                )
        );
    }

    /**
     * Add item to a view
     *
     * @ws-type-write
     * @param int viewid, itemid
     * @return success
     */
    public static function add_view_item($viewid, $itemid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::add_view_item_parameters(), array('viewid' => $viewid, 'itemid' => $itemid));

        $query = "SELECT MAX(positiony) from {block_exaportviewblock} WHERE viewid=?";
        $max = $DB->get_field_sql($query, array($viewid));
        $ycoord = intval($max) + 1;

        $blockid = $DB->insert_record("block_exaportviewblock",
                array("viewid" => $viewid, "itemid" => $itemid, "positionx" => 1, "positiony" => $ycoord, "type" => "item"));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function add_view_item_returns() {
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
    public static function delete_view_item_parameters() {
        return new external_function_parameters(
                array(
                        'viewid' => new external_value(PARAM_INT, 'view id'),
                        'itemid' => new external_value(PARAM_INT, 'item id'),
                )
        );
    }

    /**
     * Remove item from a view
     *
     * @ws-type-write
     * @param int viewid, itemid
     * @return success
     */
    public static function delete_view_item($viewid, $itemid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::delete_view_item_parameters(), array('viewid' => $viewid, 'itemid' => $itemid));
        $query = "SELECT MAX(positiony) from {block_exaportviewblock} WHERE viewid=? AND itemid=?";
        $max = $DB->get_field_sql($query, array($viewid, $itemid));
        $ycoord = intval($max);
        $DB->delete_records("block_exaportviewblock", array("viewid" => $viewid, "itemid" => $itemid, "positiony" => $ycoord));

        return array("success" => true);
    }

    /**
     * Returns desription of method return values
     *
     * @return external_single_structure
     */
    public static function delete_view_item_returns() {
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
     * @ws-type-write
     * @param int id, val
     * @return success
     */
    public static function view_grant_external_access($id, $val) {
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
     * @ws-type-read
     * @return all items available
     */
    public static function view_get_available_users() {
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
     * @ws-type-write
     * @param int id, val
     * @return success
     */
    public static function view_grant_internal_access_all($id, $val) {
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
     * @ws-type-write
     * @param int viewid, userid, val
     * @return success
     */
    public static function view_grant_internal_access($viewid, $userid, $val) {
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
     * @ws-type-read
     * @return array of e-Portfolio views
     */
    public static function get_category($categoryid) {
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
     * @ws-type-write
     * @param int viewid, userid, val
     * @return success
     */
    public static function delete_category($categoryid) {
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
     * @ws-type-read
     * @return all items available
     */
    public static function get_competencies_by_item($itemid) {
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
     * @ws-type-read
     * @return all items available
     */
    public static function get_users_by_view($viewid) {
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
     * @ws-type-write
     * @return all items available
     */
    public static function export_file_to_externalportfolio($component, $filearea, $filename, $filepath, $itemid) {
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
        require_once($CFG->dirroot.'/blocks/exacomp/upload_externalportfolio.php');
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
        return new external_function_parameters (array());
    }

    /**
     *
     * @return array
     */
    public static function get_user_information() {
        require_once(g::$CFG->dirroot."/user/lib.php");
        $data = user_get_user_details(g::$USER);
        unset($data['enrolledcourses']);
        unset($data['preferences']);

        return $data;
    }

    public static function get_user_information_returns() {
        return new external_single_structure (array(
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
                'app' => new external_value (PARAM_INT, 'app accessing this service (eg. dakora)'),
                'app_version' => new external_value (PARAM_INT, 'version of the app (eg. 4.6.0)'),
                'services' => new external_value (PARAM_INT, 'wanted webservice tokens (eg. exacomp,exaport)', VALUE_DEFAULT,
                        'moodle_mobile_app,exaportservices'),
        ));
    }

    /**
     * Returns description of method return values
     *
     * @return external_multiple_structure
     */
    public static function login_returns() {
        return new external_single_structure ([
                'user' => static::get_user_information_returns(),
                'config' => new external_single_structure([]),
                'tokens' => new external_multiple_structure (new external_single_structure ([
                        'service' => new external_value (PARAM_TEXT, 'name of service'),
                        'token' => new external_value (PARAM_TEXT, 'token of the service'),
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
                'config' => (object) [],
        ];
    }



    public static function get_shared_categories_parameters() {
        return new external_function_parameters(array());
    }

    /**
     *
     * @ws-type-read
     * @return array
     */
    public static function get_shared_categories() {
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
                    'courseid' => new external_value(PARAM_INT, 'id of the course this category belongs to',VALUE_OPTIONAL)
                )
            )
        );
    }

}
