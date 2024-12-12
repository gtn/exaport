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

namespace block_exaport\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        // block_exaportuser
        $collection->add_database_table('block_exaportuser', [
            'user_id' => 'privacy:metadata:block_exaportuser:user_id',
            'description' => 'privacy:metadata:block_exaportuser:description',
            // epop: needed?
            // 'oezinstall' => '',
            // 'import_oez_tstamp' => '',
            'view_items_layout' => 'privacy:metadata:block_exaportuser:view_items_layout',
        ], 'privacy:metadata:block_exaportuser');

        // block_exaportcate
        $collection->add_database_table('block_exaportcate', [
            'pid' => 'privacy:metadata:block_exaportcate:pid',
            'userid' => 'privacy:metadata:block_exaportcate:userid',
            'name' => 'privacy:metadata:block_exaportcate:name',
            'timemodified' => 'privacy:metadata:block_exaportcate:timemodified',
            'courseid' => 'privacy:metadata:block_exaportcate:courseid',
            // epop: needed?
            // 'isoez' => '',
            'description' => 'privacy:metadata:block_exaportcate:description',
            'subjid' => 'privacy:metadata:block_exaportcate:subjid',
            'topicid' => 'privacy:metadata:block_exaportcate:topicid',
            'source' => 'privacy:metadata:block_exaportcate:source',
            'sourceid' => 'privacy:metadata:block_exaportcate:sourceid',
            'parent_ids' => 'privacy:metadata:block_exaportcate:parent_ids',
            'parent_titles' => 'privacy:metadata:block_exaportcate:parent_titles',
            'stid' => 'privacy:metadata:block_exaportcate:stid',
            'sourcemod' => 'privacy:metadata:block_exaportcate:sourcemod',
            'name_short' => 'privacy:metadata:block_exaportcate:name_short',
            'shareall' => 'privacy:metadata:block_exaportcate:shareall',
            'internshare' => 'privacy:metadata:block_exaportcate:internshare',
            'structure_shareall' => 'privacy:metadata:block_exaportcate:structure_shareall',
            'structure_share' => 'privacy:metadata:block_exaportcate:structure_share',
        ], 'privacy:metadata:block_exaportcate');

        // block_exaportcatshar
        // do we need use shared categories as personal data? I think - no

        // block_exaportcatgroupshar
        // do we need use shared categories as personal data? I think - no

        // block_exaportitem
        $collection->add_database_table('block_exaportitem', [
            'userid' => 'privacy:metadata:block_exaportitem:userid',
            'type' => 'privacy:metadata:block_exaportitem:type',
            'categoryid' => 'privacy:metadata:block_exaportitem:categoryid',
            'name' => 'privacy:metadata:block_exaportitem:name',
            'url' => 'privacy:metadata:block_exaportitem:url',
            'intro' => 'privacy:metadata:block_exaportitem:intro',
            // looks like 'attachment' is not used anymore: right? (filestorage is used)
            // 'attachment' => 'privacy:metadata:block_exaportitem:userid',
            'timemodified' => 'privacy:metadata:block_exaportitem:timemodified',
            'courseid' => 'privacy:metadata:block_exaportitem:courseid',
            'shareall' => 'privacy:metadata:block_exaportitem:shareall',
            'externaccess' => 'privacy:metadata:block_exaportitem:externaccess',
            'externcomment' => 'privacy:metadata:block_exaportitem:externcomment',
            // epop: needed?
            // 'isoez' => 'privacy:metadata:block_exaportitem:userid',
            // 'beispiel_url' => 'privacy:metadata:block_exaportitem:userid',
            // 'beispiel_angabe' => 'privacy:metadata:block_exaportitem:userid',
            'example_url' => 'privacy:metadata:block_exaportitem:example_url',

            // fileurl - needed?
            'fileurl' => 'privacy:metadata:block_exaportitem:fileurl',
            'exampid' => 'privacy:metadata:block_exaportitem:exampid',
            'langid' => 'privacy:metadata:block_exaportitem:langid',
            'source' => 'privacy:metadata:block_exaportitem:source',
            'sourceid' => 'privacy:metadata:block_exaportitem:sourceid',
            'iseditable' => 'privacy:metadata:block_exaportitem:iseditable',
            'parentid' => 'privacy:metadata:block_exaportitem:parentid',
        ], 'privacy:metadata:block_exaportitem');

        // looks is not used:
        // block_exaportitemshar

        // block_exaportitemcomm
        $collection->add_database_table('block_exaportitemcomm', [
            'itemid' => 'privacy:metadata:block_exaportitemcomm:itemid',
            'userid' => 'privacy:metadata:block_exaportitemcomm:userid',
            'entry' => 'privacy:metadata:block_exaportitemcomm:entry',
            'timemodified' => 'privacy:metadata:block_exaportitemcomm:timemodified',
        ], 'privacy:metadata:block_exaportitemcomm');

        // block_exaportview
        $collection->add_database_table('block_exaportview', [
            'userid' => 'privacy:metadata:block_exaportview:userid',
            'name' => 'privacy:metadata:block_exaportview:name',
            'description' => 'privacy:metadata:block_exaportview:description',
            'timemodified' => 'privacy:metadata:block_exaportview:timemodified',
            'shareall' => 'privacy:metadata:block_exaportview:shareall',
            'externaccess' => 'privacy:metadata:block_exaportview:externaccess',
            'externcomment' => 'privacy:metadata:block_exaportview:externcomment',
            'langid' => 'privacy:metadata:block_exaportview:langid',
            'layout' => 'privacy:metadata:block_exaportview:layout',
            'sharedemails' => 'privacy:metadata:block_exaportview:sharedemails',
            'autofill_artefacts' => 'privacy:metadata:block_exaportview:autofill_artefacts',
        ], 'privacy:metadata:block_exaportview');

        // block_exaportviewblock
        // does not contains personal information. But needed to delete

        // block_exaportviewshar
        // block_exaportviewgroupshar
        // does not needed to kept shared views... right?

        // block_exaportresume
        $collection->add_database_table('block_exaportresume', [
            'user_id' => 'privacy:metadata:block_exaportresume:user_id',
            'courseid' => 'privacy:metadata:block_exaportresume:courseid',
            'cover' => 'privacy:metadata:block_exaportresume:cover',
            'interests' => 'privacy:metadata:block_exaportresume:interests',
            'goalspersonal' => 'privacy:metadata:block_exaportresume:goalspersonal',
            'goalsacademic' => 'privacy:metadata:block_exaportresume:goalsacademic',
            'goalscareers' => 'privacy:metadata:block_exaportresume:goalscareers',
            'skillspersonal' => 'privacy:metadata:block_exaportresume:skillspersonal',
            'skillsacademic' => 'privacy:metadata:block_exaportresume:skillsacademic',
            'skillscareers' => 'privacy:metadata:block_exaportresume:skillscareers',
        ], 'privacy:metadata:block_exaportresume');

        // block_exaportresume_certif
        // block_exaportresume_edu
        // block_exaportresume_employ
        // block_exaportresume_mbrship
        // block_exaportresume_linkedin
        // block_exaportresume_public
        // block_exaportresume_badges
        // block_exaportcompresume_mm
        // no personal data, but related to resume

        // block_exaportcat_structshar  - is this used?
        // block_exaportcat_strgrshar - is this used?
        // block_exaportviewemailshar
        // no personal data. just relations

        // this plugin stores user's files
        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:core_files'
        );

        return $collection;

    }


    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $contextlist->add_user_context($userid);

        $sql = 'SELECT c.id
                    FROM {context} c
                        INNER JOIN {block_instances} bi ON bi.blockname = ? AND bi.parentcontextid = c.id AND c.contextlevel = ?
                        INNER JOIN {block_exaportcate} cat ON cat.courseid = c.instanceid
                        INNER JOIN {block_exaportitem} item ON item.courseid = c.instanceid
                        INNER JOIN {block_exaportresume} res ON res.courseid = c.instanceid
                    WHERE cat.userid = ?
                          OR item.userid = ?
                          OR res.user_id = ?
        ';

        $params = ['exaport', CONTEXT_COURSE, $userid, $userid, $userid];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (get_class($context) != 'context_course') {
            return;
        }
        $courseid = $context->instanceid;
        if ($courseid) {
            $params = ['courseid' => $courseid];

            $sql = "SELECT userid as userid FROM {block_exaportcate} WHERE courseid = :courseid "; // categories
            $userlist->add_from_sql('userid', $sql, $params);
            $sql = "SELECT userid as userid FROM {block_exaportitem} WHERE courseid = :courseid "; // artifacts
            $userlist->add_from_sql('userid', $sql, $params);
            $sql = "SELECT userid as userid FROM {block_exaportitemshar} WHERE courseid = :courseid "; // relations: needed?
            $userlist->add_from_sql('userid', $sql, $params);
            $sql = "SELECT user_id as userid FROM {block_exaportresume} WHERE courseid = :courseid "; // resume
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    public static function export_resume_list_data(&$resume, $list_name, $subcontext_name, $file_area_name, $resume_context, $user) {
        $clean_props = ['id', 'resume_id', 'resumeid', 'sorting'];
        $file_area_name_editor = str_replace('resume_', 'resume_editor_', $file_area_name);
        if ($resume->{$list_name}) {
            if (is_array($resume->{$list_name})) {
                $i = 1;
                foreach ($resume->{$list_name} as $item_id => $item) {
                    foreach ($clean_props as $prop) {
                        if (property_exists($item, $prop)) {
                            unset($item->{$prop});
                        }
                    }
                    $item_data = array($list_name => $item);
                    $contextdata = helper::get_context_data($resume_context, $user);
                    $contextdata = (object)array_merge((array)$contextdata, $item_data);
                    $writer = writer::with_context($resume_context);

                    $subcontext_name_temp = 'Exabis ePortfolio/Curriculum Vitae/' . $subcontext_name . '/_' . $i . '_';
                    $writer->export_data([$subcontext_name_temp], $contextdata)
                        ->export_area_files([$subcontext_name_temp], 'block_exaport', $file_area_name, $item_id);
                    $i++;
                }
            } else if (is_string($resume->{$list_name})) {
                $subcontext_name = 'Exabis ePortfolio/Curriculum Vitae/' . $subcontext_name;
                // just a string
                $writer = writer::with_context($resume_context);
                $item_data = $writer->rewrite_pluginfile_urls([$subcontext_name], 'block_exaport', $file_area_name_editor, $resume->id, $resume->{$list_name});
                $item_data = format_text($item_data, FORMAT_HTML);
                $item_data = array($list_name => $item_data);
                $contextdata = helper::get_context_data($resume_context, $user);
                $contextdata = (object)array_merge((array)$contextdata, $item_data);

                $writer->export_data([$subcontext_name], $contextdata);
                $writer->export_area_files([$subcontext_name], 'block_exaport', $file_area_name_editor, $resume->id);
                // $writer->export_area_files(['Exabis ePortfolio/Curriculum Vitae/'], 'block_exaport', $file_area_name_editor, $resume->id);
            }
        }
    }

    public static function export_resume_list_related_competencies($resumeid, $type, $subcontext_name, $resume_context, $user) {
        global $DB;
        if (block_exaport_check_competence_interaction()) {
            $comptitles = '';
            $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resumeid, "comptype" => $type));
            foreach ($competences as $competence) {
                $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), '*', IGNORE_MISSING);
                if ($competencesdb != null) {
                    $comptitles .= $competencesdb->title . "<br>";
                };
            };
            $comptitles = array('competencies' => $comptitles);
            $contextdata = helper::get_context_data($resume_context, $user);
            $contextdata = (object)array_merge((array)$contextdata, $comptitles);
            $writer = writer::with_context($resume_context);

            $subcontext_name = 'Exabis ePortfolio/Curriculum Vitae/' . $subcontext_name;
            $writer->export_data([$subcontext_name], $contextdata);
        }
    }


    public static function attach_category_artifact_files($categories_tree, $context, $subcontext_name) {
        $writer = writer::with_context($context);

        foreach ($categories_tree as $cat_id => $category) {
            // category icon
            $writer->export_area_files([$subcontext_name . '/Categories'], 'block_exaport', 'category_icon', $cat_id);
            // items
            if ($category->items) {
                $add_tosubcontext_name = '/Artifacts';
                foreach ($category->items as $item_id => $item) {
                    // item file
                    $writer->export_area_files([$subcontext_name . $add_tosubcontext_name], 'block_exaport', 'item_file', $item_id);
                    // item content (from html)
                    if ($item->intro) {
                        $item->intro = $writer->rewrite_pluginfile_urls([$subcontext_name . $add_tosubcontext_name], 'block_exaport', 'item_content', $item_id, $item->intro);
                    }
                    $writer->export_area_files([$subcontext_name . $add_tosubcontext_name], 'block_exaport', 'item_content', $item_id);
                    // item icon
                    $writer->export_area_files([$subcontext_name . $add_tosubcontext_name . '/Icons'], 'block_exaport', 'item_iconfile', $item_id);
                    // comment for item
                    $writer->export_area_files([$subcontext_name . $add_tosubcontext_name . '/Comments'], 'block_exaport', 'item_comment_file', $item_id);
                }
            }
            // subcategory
            if ($category->subcategories) {
                self::attach_category_artifact_files($category->subcategories, $context, $subcontext_name);
            }
        }
    }


    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');
        require_once($CFG->dirroot . '/blocks/exaport/lib/resumelib.php');
        // require_once($CFG->dirroot . '/blocks/exacomp/lib/classes.php');
        if (empty($contextlist->count())) {
            // return;
        }
        $user = $contextlist->get_user();
        // got only context_cources
        $exaportcoursescontexts = $contextlist->get_contexts();
        foreach ($exaportcoursescontexts as $k => $context) {
            if (get_class($context) != 'context_course') {
                unset($exaportcoursescontexts[$k]);
            }
        }

        // resume data
        $courseid = $context->instanceid;
        $resume_data = [];
        $resume = block_exaport_get_resume_params($user->id, true);

        if ($resume) {
            $resume_context = context_user::instance($user->id);

            // cover
            static::export_resume_list_data($resume, 'cover', 'Cover', 'resume_cover', $resume_context, $user);
            // educations
            static::export_resume_list_data($resume, 'educations', 'Educations', 'resume_edu', $resume_context, $user);
            // employment
            static::export_resume_list_data($resume, 'employments', 'Employments', 'resume_employ', $resume_context, $user);
            // certifications
            static::export_resume_list_data($resume, 'certifications', 'certifications', 'resume_certif', $resume_context, $user);
            // badges
            static::export_resume_list_data($resume, 'badges', 'Badges', 'resume_badges', $resume_context, $user);
            // publications
            static::export_resume_list_data($resume, 'publications', 'Publications', 'resume_public', $resume_context, $user);
            // memberships
            static::export_resume_list_data($resume, 'profmembershipments', 'Memberships', 'resume_mbrship', $resume_context, $user);
            // goals
            static::export_resume_list_related_competencies($resume->id, 'goals', 'Goals/Educational standards', $resume_context, $user);
            static::export_resume_list_data($resume, 'goalspersonal', 'Goals/Personal', 'resume_goalspersonal', $resume_context, $user);
            static::export_resume_list_data($resume, 'goalsacademic', 'Goals/Academic', 'resume_goalsacademic', $resume_context, $user);
            static::export_resume_list_data($resume, 'goalscareers', 'Goals/Career', 'resume_goalscareer', $resume_context, $user);
            // skills
            static::export_resume_list_related_competencies($resume->id, 'skills', 'Skills/Educational standards', $resume_context, $user);
            static::export_resume_list_data($resume, 'skillspersonal', 'Skills/Personal', 'resume_skillspersonal', $resume_context, $user);
            static::export_resume_list_data($resume, 'skillsacademic', 'Skills/Academic', 'resume_skillsacademic', $resume_context, $user);
            static::export_resume_list_data($resume, 'skillscareers', 'Skills/Career', 'resume_skillscareer', $resume_context, $user);
            // interests
            static::export_resume_list_data($resume, 'interests', 'Interests', 'resume_interests', $resume_context, $user);

        }

        // artifacts data (with categories)
        $categories_tree = block_exaport_user_categories_into_tree($user->id, true, true);
        if ($categories_tree) {
            // $context - user or course?
            $context = context_user::instance($user->id);

            $subcontext_name = 'Exabis ePortfolio/Portolio Artifacts';
            // attach files and format HTML
            self::attach_category_artifact_files($categories_tree, $context, $subcontext_name);
            $categories_tree = array('categories' => $categories_tree);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object)array_merge((array)$contextdata, $categories_tree);
            $writer = writer::with_context($context);
            $writer->export_data([$subcontext_name], $contextdata);
        }

        $badges = block_exaport_get_all_user_badges($user->id);
        $resume = block_exaport_get_resume_params($user->id, true);

        // views
        $views = $DB->get_records("block_exaportview", ['userid' => $user->id]);
        $viewsArr = array();
        $copyProps = ['name', 'description', 'shareall', 'externaccess'];
        foreach ($views as $view) {
            $view_entry = new stdClass();
            foreach ($copyProps as $prop) {
                if (property_exists($view, $prop)) {
                    $view_entry->{$prop} = $view->{$prop};
                }
            }
            if ($view_entry->description) {
                // files and HTML format
                $writer = writer::with_context($resume_context);
                $view_entry->description = $writer->rewrite_pluginfile_urls(['Exabis ePortfolio/Views'], 'block_exaport', 'view', $view->id, $view_entry->description);
                $view_entry->description = format_text($view_entry->description, FORMAT_HTML);
                $writer->export_area_files(['Exabis ePortfolio/Views'], 'block_exaport', 'view', $view->id);
            }
            $view_entry->timemodified = transform::datetime(@$view->timemodified);
            // add blocks
            $blocks = [];
            $all_blocks = $DB->get_records_sql('SELECT b.*
                                FROM {block_exaportviewblock} b
                                  WHERE b.viewid = ?
                                ORDER BY b.positionx, b.positiony', array($view->id));
            foreach ($all_blocks as &$block) {
                $block_entry = new stdClass();
                $block_entry->content = '';
                if (@$block->block_title) {
                    $block_entry->block_title = $block->block_title;
                }
                switch ($block->type) {
                    case 'headline': // headline
                        $block_entry->content = $block->text;
                        break;
                    case 'item': // artifact
                        $item = $DB->get_record('block_exaportitem', ['id' => $block->itemid]);
                        if (!$item) {
                            continue 2;
                        }
                        if ($item->url && $item->url != "false") {
                            $block_entry->url = $item->url;
                        }
                        if ($item->intro) {
                            $block_entry->content = $item->intro;
                        }
                        if (block_exaport_check_competence_interaction()) {
                            $comps = block_exaport_get_active_comps_for_item($item);
                            if ($comps && is_array($comps) && array_key_exists('descriptors', $comps)) {
                                $competencies = $comps['descriptors'];
                            } else {
                                $competencies = null;
                            }

                            if ($competencies) {
                                $competenciesoutput = "";
                                foreach ($competencies as $competence) {
                                    $competenciesoutput .= $competence->title . '<br>';
                                }
                                $block_entry->competences = $competenciesoutput;
                            }
                        }
                        switch ($item->type) {
                            case 'file':
                                break;
                            case 'link':
                                break;
                        }
                        break;
                    case 'personal_information':
                        // add picture?
                        $person_info = '';
                        if (isset($block->firstname) or isset($block->lastname)) {
                            if (isset($block->firstname)) {
                                $person_info .= $block->firstname;
                            }
                            if (isset($block->lastname)) {
                                $person_info .= ' ' . $block->lastname;
                            }
                        };
                        if (isset($block->email)) {
                            $person_info .= $block->email;
                        }
                        if (isset($block->text)) {
                            $person_info .= $block->text;
                        }
                        $block_entry->content = $person_info;
                        break;
                    case 'media':
                        if (!empty($block->contentmedia)) {
                            $block_entry->content .= $block->contentmedia;
                        }
                        break;
                    case 'badge':
                        if (count($badges) > 0) {
                            // badge can be deleted, but relation is still existing in DB, so check all existing badges:
                            $badge = null;
                            foreach ($badges as $tmp) {
                                if ($tmp->id == $block->itemid) {
                                    $badge = $tmp;
                                    break;
                                };
                            };
                            if (!$badge) {
                                // Badge not found.
                                continue 2;
                            }
                            $block_entry->content .= $badge->name;
                            // badge file?
                            $block_entry->content .= format_text($badge->description, FORMAT_HTML);
                        }
                        break;
                    case 'cv_information':
                        switch ($block->resume_itemtype) {
                            case 'cover':
                                if ($resume && $resume->cover) {
                                    $block_entry->content .= $resume->cover;
                                }
                                break;
                            case 'edu':
                                if ($block->itemid && $resume && $resume->educations[$block->itemid]) {
                                    $item_data = $resume->educations[$block->itemid];
                                    // files?
                                    // $attachments = $item_data->attachments;
                                    $block_entry->content .= $item_data->institution . ': ';
                                    $block_entry->content .= $item_data->qualname;
                                    if ($item_data->startdate != '' || $item_data->enddate != '') {
                                        $block_entry->content .= ' (';
                                        if ($item_data->startdate != '') {
                                            $block_entry->content .= $item_data->startdate;
                                        }
                                        if ($item_data->enddate != '') {
                                            $block_entry->content .= ' - ' . $item_data->enddate;
                                        }
                                        $block_entry->content .= ')';
                                    }
                                    if ($item_data->qualdescription != '') {
                                        $block_entry->content .= '; ' . $item_data->qualdescription;
                                    }
                                }
                                break;
                            case 'employ':
                                if ($block->itemid && $resume && $resume->employments[$block->itemid]) {
                                    $item_data = $resume->employments[$block->itemid];
                                    // files?
                                    // $attachments = $item_data->attachments;
                                    $description = '';
                                    $description .= $item_data->jobtitle . ': ' . $item_data->employer;
                                    if ($item_data->startdate != '' || $item_data->enddate != '') {
                                        $description .= ' (';
                                        if ($item_data->startdate != '') {
                                            $description .= $item_data->startdate;
                                        }
                                        if ($item_data->enddate != '') {
                                            $description .= ' - ' . $item_data->enddate;
                                        }
                                        $description .= ')';
                                    }
                                    if ($item_data->positiondescription != '') {
                                        $description .= '; ' . $item_data->positiondescription;
                                    }
                                    $block_entry->content .= $description;
                                }
                                break;
                            case 'certif':
                                if ($block->itemid && $resume && $resume->certifications[$block->itemid]) {
                                    $item_data = $resume->certifications[$block->itemid];
                                    // files?
                                    // $attachments = $item_data->attachments;
                                    $description = '';
                                    $description .= $item_data->title;
                                    if ($item_data->date != '') {
                                        $description .= ' (' . $item_data->date . ')';
                                    }
                                    if ($item_data->description != '') {
                                        $description .= '; ' . $item_data->description;
                                    }
                                    $block_entry->content = $description;
                                }
                                break;
                            case 'public':
                                if ($block->itemid && $resume && $resume->publications[$block->itemid]) {
                                    $item_data = $resume->publications[$block->itemid];
                                    // files
                                    // $attachments = $item_data->attachments;
                                    $description = '';
                                    $description .= $item_data->title;
                                    if ($item_data->contribution != '') {
                                        $description .= ' (' . $item_data->contribution . ')';
                                    }
                                    if ($item_data->date != '') {
                                        $description .= ' (' . $item_data->date . ')';
                                    }
                                    if ($item_data->contributiondetails != '' || $item_data->url != '') {
                                        $description .= '; ';
                                        if ($item_data->contributiondetails != '') {
                                            $description .= $item_data->contributiondetails;
                                        }
                                        if ($item_data->url != '') {
                                            $description .= ' ' . $item_data->url . ' ';
                                        }
                                    }
                                    $block_entry->content = $description;
                                }
                                break;
                            case 'mbrship':
                                if ($block->itemid && $resume && $resume->profmembershipments[$block->itemid]) {
                                    $item_data = $resume->profmembershipments[$block->itemid];
                                    // files?
                                    // $attachments = $item_data->attachments;
                                    $description = '';
                                    $description .= $item_data->title . ' ';
                                    if ($item_data->startdate != '' || $item_data->enddate != '') {
                                        $description .= ' (';
                                        if ($item_data->startdate != '') {
                                            $description .= $item_data->startdate;
                                        }
                                        if ($item_data->enddate != '') {
                                            $description .= ' - ' . $item_data->enddate;
                                        }
                                        $description .= ')';
                                    }
                                    if ($item_data->description != '') {
                                        $description .= '; ' . $item_data->description;
                                    }
                                    $block_entry->content = $description;
                                }
                                break;
                            case 'goalspersonal':
                            case 'goalsacademic':
                            case 'goalscareers':
                            case 'skillspersonal':
                            case 'skillsacademic':
                            case 'skillscareers':
                                // files?
                                // $attachments = @$resume->{$block->resume_itemtype.'_attachments'};
                                $description = '';
                                if ($resume && $resume->{$block->resume_itemtype}) {
                                    $description .= $resume->{$block->resume_itemtype} . ' ';
                                }
                                $block_entry->content = $description;
                                break;
                            case 'interests':
                                $description = '';
                                if ($resume->interests != '') {
                                    $description .= $resume->interests . ' ';
                                }
                                $block_entry->content = $description;
                                break;
                            default:
                                $block_entry->content .= '!!! ' . $block->resume_itemtype . ' !!!';
                        }

                        break;
                    case 'text':
                    default:
                        $block_entry->content .= format_text($block->text, FORMAT_HTML);
                        break;
                }
                $block_entry->positionx = $block->positionx;
                $block_entry->positiony = $block->positiony;
                if (!$block_entry->content) {
                    unset($block_entry->content);
                }

                $blocks[] = $block_entry;
            }
            $view_entry->blocks = $blocks;

            if (!$view_entry->shareall) {
                unset($view_entry->shareall);
            }
            if (!$view_entry->externaccess) {
                unset($view_entry->externaccess);
            }
            $viewsArr[] = $view_entry;
        }
        if ($viewsArr) {
            $viewsArr = array('views' => $viewsArr);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object)array_merge((array)$contextdata, $viewsArr);
            $writer = writer::with_context($context);
            $writer->export_data(['Exabis ePortfolio/Views'], $contextdata);
        }

        // comments to artifacts
        $comments = $DB->get_records_sql('SELECT c.*, i.name as itemname, i.userid as itemownerid
                                FROM {block_exaportitemcomm} c
                                  LEFT JOIN {block_exaportitem} i ON i.id = c.itemid
                                WHERE c.userid = ?
                                ORDER BY c.timemodified', array($user->id));
        if ($comments) {
            $comments_arr = [];
            foreach ($comments as $comment) {
                $comment_entry = new stdClass();
                $comment_entry->text = $comment->entry;
                $comment_entry->timemodified = transform::datetime(@$comment->timemodified);
                $item_entry = new stdClass();
                $item_entry->name = $comment->itemname;
                $user_obj = $DB->get_record('user', ['id' => $comment->itemownerid]);
                $item_entry->fromUser = fullname($user_obj, $user->id);
                $comment_entry->item = $item_entry;

                $comments_arr[] = $comment_entry;
            }
            $comments_arr = array('comments' => $comments_arr);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object)array_merge((array)$contextdata, $comments_arr);
            $writer = writer::with_context($context);
            $writer->export_data(['Exabis ePortfolio/Comments/Artifacts'], $contextdata);
        }

    }

    public function delete_resume_data($resume_id) {

        global $DB;
        $DB->delete_records('block_exaportresume_certif', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_edu', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_employ', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_mbrship', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_linkedin', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_public', ['resume_id' => $resume_id]);
        $DB->delete_records('block_exaportresume_badges', ['resumeid' => $resume_id]);
        $DB->delete_records('block_exaportcompresume_mm', ['resumeid' => $resume_id]);
        return true;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if (get_class($context) != 'context_course') {
            return;
        }
        $courseid = $context->instanceid;
        if ($courseid) {

            // categories
            $cats = $DB->get_records('block_exaportcate', ['courseid' => $courseid]);
            foreach ($cats as $category) {
                self::delete_category_data($category->id);
            }
            $DB->delete_records('block_exaportcate', ['courseid' => $courseid]);

            // artifatcs
            $artifacts = $DB->get_records('block_exaportitem', ['courseid' => $courseid]);
            foreach ($artifacts as $artifact) {
                self::delete_atifact_data($artifact->id);
            }
            $DB->delete_records('block_exaportitem', ['courseid' => $courseid]);
            // other shared
            $DB->delete_records('block_exaportitemshar', ['courseid' => $courseid]);

            // resume
            $resumes = $DB->get_records('block_exaportresume', ['courseid' => $courseid]);
            foreach ($resumes as $resume) {
                self::delete_resume_data($resume->id);
            }
            $DB->delete_records('block_exaportresume', ['courseid' => $courseid]);

        }
        return;
    }

    public function delete_category_data($category_id) {
        global $DB;
        $DB->delete_records('block_exaportcatshar', ['catid' => $category_id]);
        $DB->delete_records('block_exaportcatgroupshar', ['catid' => $category_id]);
        $DB->delete_records('block_exaportcat_structshar', ['catid' => $category_id]);
        $DB->delete_records('block_exaportcat_strgrshar', ['catid' => $category_id]);
        return true;
    }

    public function delete_atifact_data($artifact_id) {
        global $DB;
        $DB->delete_records('block_exaportitemshar', ['itemid' => $artifact_id]);
        $DB->delete_records('block_exaportitemcomm', ['itemid' => $artifact_id]);
        $DB->delete_records('block_exaportviewblock', ['itemid' => $artifact_id]);
        return true;
    }

    public function delete_view_data($view_id) {
        global $DB;
        $DB->delete_records('block_exaportviewblock', ['viewid' => $view_id]);
        $DB->delete_records('block_exaportviewshar', ['viewid' => $view_id]);
        $DB->delete_records('block_exaportviewgroupshar', ['viewid' => $view_id]);
        $DB->delete_records('block_exaportviewemailshar', ['viewid' => $view_id]);
        return true;
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        $exaportcoursescontexts = $contextlist->get_contexts();
        foreach ($exaportcoursescontexts as $k => $context) {
            if (get_class($context) != 'context_course') {
                unset($exaportcoursescontexts[$k]);
            }
        }
        foreach ($exaportcoursescontexts as $context) {
            $courseid = $context->instanceid;

            // user's data
            $DB->delete_records('block_exaportuser', ['user_id' => $userid]);

            // resume
            $resumes = $DB->get_records('block_exaportresume', ['user_id' => $userid]);
            foreach ($resumes as $resume) {
                self::delete_resume_data($resume->id);
            }
            $DB->delete_records('block_exaportresume', ['user_id' => $userid]);

            // categories
            $cats = $DB->get_records('block_exaportcate', ['userid' => $userid, 'courseid' => $courseid]);
            foreach ($cats as $category) {
                self::delete_category_data($category->id);
            }
            $DB->delete_records('block_exaportcate', ['userid' => $userid, 'courseid' => $courseid]);

            // category relations to user (categories from other users)
            $DB->delete_records('block_exaportcatshar', ['userid' => $userid]);
            $DB->delete_records('block_exaportcat_structshar', ['userid' => $userid]);

            // artifacts
            $artifacts = $DB->get_records('block_exaportitem', ['userid' => $userid, 'courseid' => $courseid]);
            foreach ($artifacts as $artifact) {
                self::delete_atifact_data($artifact->id);
            }
            $DB->delete_records('block_exaportitem', ['userid' => $userid, 'courseid' => $courseid]);

            // artifact shares (into artefacts from other users)
            $DB->delete_records('block_exaportitemshar', ['userid' => $userid]);

            // my comments to artifacts
            $DB->delete_records('block_exaportitemcomm', ['userid' => $userid]);

            // views
            $views = $DB->get_records('block_exaportview', ['userid' => $userid]);
            foreach ($views as $view) {
                self::delete_view_data($view->id);
            }
            $DB->delete_records('block_exaportview', ['userid' => $userid]);

            // shared views (shared from other users)
            $DB->delete_records('block_exaportviewshar', ['userid' => $userid]);

        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (get_class($context) != 'context_course') {
            return;
        }
        $courseid = $context->instanceid;

        list($in_sql, $inParams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = $inParams;

        $select = " user_id {$in_sql}";

        // personal data
        $DB->delete_records_select('block_exaportuser', $select, $params);

        // resume
        $resumes = $DB->get_record_select('block_exaportresume', $select, $params);
        foreach ($resumes as $resume) {
            self::delete_resume_data($resume->id);
        }
        $DB->delete_records_select('block_exaportresume', $select, $params);

        $select = " userid {$in_sql}";

        // relations
        $DB->delete_records_select('block_exaportcatshar', $select, $params);
        $DB->delete_records_select('block_exaportcat_structshar', $select, $params);
        $DB->delete_records_select('block_exaportitemshar', $select, $params);
        $DB->delete_records_select('block_exaportitemcomm', $select, $params);
        $DB->delete_records_select('block_exaportview', $select, $params);
        $DB->delete_records_select('block_exaportviewshar', $select, $params);

        $params += ['courseid' => $courseid];
        $select = " userid {$in_sql} AND courseid = :courseid ";

        // categories
        $cats = $DB->get_record_select('block_exaportcate', $select, $params);
        foreach ($cats as $category) {
            self::delete_category_data($category->id);
        }
        $DB->delete_records_select('block_exaportcate', $select, $params);

        // artifacts
        $artifacts = $DB->get_record_select('block_exaportitem', $select, $params);
        foreach ($artifacts as $artifact) {
            self::delete_atifact_data($artifact->id);
        }
        $DB->delete_records_select('block_exaportitem', $select, $params);

    }
}

?>
