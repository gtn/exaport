<?php
// …

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

    public static function get_metadata(collection $collection): collection
    {
        // block_exaportuser
        $collection->add_database_table('block_exaportuser', [
            'user_id' => 'privacy:metadata:block_exaportuser:user_id',
            'description' => 'privacy:metadata:block_exaportuser:description',
            // epop: needed?
//            'oezinstall' => '',
//            'import_oez_tstamp' => '',
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
            //'attachment' => 'privacy:metadata:block_exaportitem:userid',
            'timemodified' => 'privacy:metadata:block_exaportitem:timemodified',
            'courseid' => 'privacy:metadata:block_exaportitem:courseid',
            'shareall' => 'privacy:metadata:block_exaportitem:shareall',
            'externaccess' => 'privacy:metadata:block_exaportitem:externaccess',
            'externcomment' => 'privacy:metadata:block_exaportitem:externcomment',
            // epop: needed?
            //'isoez' => 'privacy:metadata:block_exaportitem:userid',
            //'beispiel_url' => 'privacy:metadata:block_exaportitem:userid',
            //'beispiel_angabe' => 'privacy:metadata:block_exaportitem:userid',
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
     * @param   int         $userid     The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
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

    static function export_resume_list_data(&$resume, $listName, $subcontextName, $fileAreaName, $resumeContext, $user) {
        $cleanProps = ['id', 'resume_id', 'resumeid', 'sorting'];
        $fileAreaNameEditor = str_replace('resume_', 'resume_editor_', $fileAreaName);
        if ($resume->{$listName}) {
            if (is_array($resume->{$listName})) {
                $i = 1;
                foreach ($resume->{$listName} as $itemId => $item) {
                    foreach ($cleanProps as $prop) {
                        if (property_exists($item, $prop)) {
                            unset($item->{$prop});
                        }
                    }
                    $itemData = array($listName => $item);
                    $contextdata = helper::get_context_data($resumeContext, $user);
                    $contextdata = (object)array_merge((array)$contextdata, $itemData);
                    $writer = writer::with_context($resumeContext);

                    $subcontextNameTemp = 'Exabis ePortfolio/Curriculum Vitae/' . $subcontextName . '/_' . $i . '_';
                    $writer->export_data([$subcontextNameTemp], $contextdata)
                        ->export_area_files([$subcontextNameTemp], 'block_exaport', $fileAreaName, $itemId);
                    $i++;
                }
            } elseif (is_string($resume->{$listName})) {
                $subcontextName = 'Exabis ePortfolio/Curriculum Vitae/' . $subcontextName;
                // just a string
                $writer = writer::with_context($resumeContext);
                $itemData = $writer->rewrite_pluginfile_urls([$subcontextName], 'block_exaport', $fileAreaNameEditor, $resume->id, $resume->{$listName});
                $itemData = format_text($itemData, FORMAT_HTML);
                $itemData = array($listName => $itemData);
                $contextdata = helper::get_context_data($resumeContext, $user);
                $contextdata = (object)array_merge((array)$contextdata, $itemData);

                $writer->export_data([$subcontextName], $contextdata);
                $writer->export_area_files([$subcontextName], 'block_exaport', $fileAreaNameEditor, $resume->id);
//                $writer->export_area_files(['Exabis ePortfolio/Curriculum Vitae/'], 'block_exaport', $fileAreaNameEditor, $resume->id);
            }
        }
    }

    static function export_resume_list_related_competencies($resumeid, $type, $subcontextName, $resumeContext, $user) {
        global $DB;
        if (block_exaport_check_competence_interaction()) {
            $comptitles = '';
            $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resumeid, "comptype" => $type));
            foreach ($competences as $competence) {
                $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), '*', IGNORE_MISSING);
                if ($competencesdb != null) {
                    $comptitles .= $competencesdb->title."<br>";
                };
            };
            $comptitles = array('competencies' => $comptitles);
            $contextdata = helper::get_context_data($resumeContext, $user);
            $contextdata = (object)array_merge((array)$contextdata, $comptitles);
            $writer = writer::with_context($resumeContext);

            $subcontextName = 'Exabis ePortfolio/Curriculum Vitae/'.$subcontextName;
            $writer->export_data([$subcontextName], $contextdata);
        }
    }


    static function attach_category_artifact_files($categoriesTree, $context, $subcontextName) {
        $writer = writer::with_context($context);

        foreach ($categoriesTree as $catId => $category) {
            // category icon
            $writer->export_area_files([$subcontextName.'/Categories'], 'block_exaport', 'category_icon', $catId);
            // items
            if ($category->items) {
                $addToSubContextName = '/Artifacts';
                foreach ($category->items as $itemId => $item) {
                    // item file
                    $writer->export_area_files([$subcontextName.$addToSubContextName], 'block_exaport', 'item_file', $itemId);
                    // item content (from html)
                    if ($item->intro) {
                        $item->intro = $writer->rewrite_pluginfile_urls([$subcontextName.$addToSubContextName], 'block_exaport', 'item_content', $itemId, $item->intro);
                    }
                    $writer->export_area_files([$subcontextName.$addToSubContextName], 'block_exaport', 'item_content', $itemId);
                    // item icon
                    $writer->export_area_files([$subcontextName.$addToSubContextName.'/Icons'], 'block_exaport', 'item_iconfile', $itemId);
                    // comment for item
                    $writer->export_area_files([$subcontextName.$addToSubContextName.'/Comments'], 'block_exaport', 'item_comment_file', $itemId);
                }
            }
            // subcategory
            if ($category->subcategories) {
                self::attach_category_artifact_files($category->subcategories, $context, $subcontextName);
            }
        }
    }


    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');
        require_once($CFG->dirroot . '/blocks/exaport/lib/resumelib.php');
//        require_once($CFG->dirroot . '/blocks/exacomp/lib/classes.php');
        if (empty($contextlist->count())) {
            //return;
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
        $resumeData = [];
        $resume = block_exaport_get_resume_params($user->id, true);

        if ($resume) {
            $resumeContext = context_user::instance($user->id);

            // cover
            static::export_resume_list_data($resume, 'cover', 'Cover', 'resume_cover', $resumeContext, $user);
            // educations
            static::export_resume_list_data($resume, 'educations', 'Educations', 'resume_edu', $resumeContext, $user);
            // employment
            static::export_resume_list_data($resume, 'employments', 'Employments', 'resume_employ', $resumeContext, $user);
            // certifications
            static::export_resume_list_data($resume, 'certifications', 'Certifications', 'resume_certif', $resumeContext, $user);
            // badges
            static::export_resume_list_data($resume, 'badges', 'Badges', 'resume_badges', $resumeContext, $user);
            // publications
            static::export_resume_list_data($resume, 'publications', 'Publications', 'resume_public', $resumeContext, $user);
            // memberships
            static::export_resume_list_data($resume, 'profmembershipments', 'Memberships', 'resume_mbrship', $resumeContext, $user);
            // goals
            static::export_resume_list_related_competencies($resume->id, 'goals', 'Goals/Educational standards', $resumeContext, $user);
            static::export_resume_list_data($resume, 'goalspersonal', 'Goals/Personal', 'resume_goalspersonal', $resumeContext, $user);
            static::export_resume_list_data($resume, 'goalsacademic', 'Goals/Academic', 'resume_goalsacademic', $resumeContext, $user);
            static::export_resume_list_data($resume, 'goalscareers', 'Goals/Career', 'resume_goalscareer', $resumeContext, $user);
            // skills
            static::export_resume_list_related_competencies($resume->id, 'skills', 'Skills/Educational standards', $resumeContext, $user);
            static::export_resume_list_data($resume, 'skillspersonal', 'Skills/Personal', 'resume_skillspersonal', $resumeContext, $user);
            static::export_resume_list_data($resume, 'skillsacademic', 'Skills/Academic', 'resume_skillsacademic', $resumeContext, $user);
            static::export_resume_list_data($resume, 'skillscareers', 'Skills/Career', 'resume_skillscareer', $resumeContext, $user);
            // interests
            static::export_resume_list_data($resume, 'interests', 'Interests', 'resume_interests', $resumeContext, $user);

        }



        // artifacts data (with categories)
        $categoriesTree = block_exaport_user_categories_into_tree($user->id, true, true);
        if ($categoriesTree) {
            // $context - user or course?
            $context = context_user::instance($user->id);

            $subContextName = 'Exabis ePortfolio/Portolio Artifacts';
            // attach files and format HTML
            self::attach_category_artifact_files($categoriesTree, $context, $subContextName);
            $categoriesTree = array('categories' => $categoriesTree);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $categoriesTree);
            $writer = writer::with_context($context);
            $writer->export_data([$subContextName], $contextdata);
        }


        $badges = block_exaport_get_all_user_badges($user->id);
        $resume = block_exaport_get_resume_params($user->id, true);

        // views
        $views = $DB->get_records("block_exaportview", ['userid' => $user->id]);
        $viewsArr = array();
        $copyProps = ['name', 'description', 'shareall', 'externaccess'];
        foreach ($views as $view) {
            $viewEntry = new stdClass();
            foreach ($copyProps as $prop) {
                if (property_exists($view, $prop)) {
                    $viewEntry->{$prop} = $view->{$prop};
                }
            }
            if ($viewEntry->description) {
                // files and HTML format
                $writer = writer::with_context($resumeContext);
                $viewEntry->description = $writer->rewrite_pluginfile_urls(['Exabis ePortfolio/Views'], 'block_exaport', 'view', $view->id, $viewEntry->description);
                $viewEntry->description = format_text($viewEntry->description, FORMAT_HTML);
                $writer->export_area_files(['Exabis ePortfolio/Views'], 'block_exaport', 'view', $view->id);
            }
            $viewEntry->timemodified = transform::datetime(@$view->timemodified);
            // add blocks
            $blocks = [];
            $allBlocks = $DB->get_records_sql('SELECT b.* 
                                FROM {block_exaportviewblock} b 
                                  WHERE b.viewid = ? 
                                ORDER BY b.positionx, b.positiony', array($view->id));
            foreach ($allBlocks as &$block) {
                $blockEntry = new stdClass();
                $blockEntry->content = '';
                if (@$block->block_title) {
                    $blockEntry->block_title = $block->block_title;
                }
                switch ($block->type) {
                    case 'headline': // headline
                        $blockEntry->content = $block->text;
                        break;
                    case 'item': // artifact
                        $item = $DB->get_record('block_exaportitem', ['id' => $block->itemid]);
                        if (!$item) {
                            continue 2;
                        }
                        if ($item->url && $item->url != "false") {
                            $blockEntry->url = $item->url;
                        }
                        if ($item->intro) {
                            $blockEntry->content = $item->intro;
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
                                    $competenciesoutput .= $competence->title.'<br>';
                                }
                                $blockEntry->competences = $competenciesoutput;
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
                        $personInfo = '';
                        if (isset($block->firstname) or isset($block->lastname)) {
                            if (isset($block->firstname)) {
                                $personInfo .= $block->firstname;
                            }
                            if (isset($block->lastname)) {
                                $personInfo .= ' '.$block->lastname;
                            }
                        };
                        if (isset($block->email)) {
                            $personInfo .= $block->email;
                        }
                        if (isset($block->text)) {
                            $personInfo .= $block->text;
                        }
                        $blockEntry->content = $personInfo;
                        break;
                    case 'media':
                        if (!empty($block->contentmedia)) {
                            $blockEntry->content .= $block->contentmedia;
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
                            $blockEntry->content .= $badge->name;
                            // badge file?
                            $blockEntry->content .= format_text($badge->description, FORMAT_HTML);
                        }
                        break;
                    case 'cv_information':
                        switch ($block->resume_itemtype) {
                            case 'edu':
                                if ($block->itemid && $resume && $resume->educations[$block->itemid]) {
                                    $itemData = $resume->educations[$block->itemid];
                                    // files?
//                                    $attachments = $itemData->attachments;
                                    $blockEntry->content .= $itemData->institution.': ';
                                    $blockEntry->content .= $itemData->qualname;
                                    if ($itemData->startdate != '' || $itemData->enddate != '') {
                                        $blockEntry->content .= ' (';
                                        if ($itemData->startdate != '') {
                                            $blockEntry->content .= $itemData->startdate;
                                        }
                                        if ($itemData->enddate != '') {
                                            $blockEntry->content .= ' - '.$itemData->enddate;
                                        }
                                        $blockEntry->content .= ')';
                                    }
                                    if ($itemData->qualdescription != '') {
                                        $blockEntry->content .= '; '.$itemData->qualdescription;
                                    }
                                }
                                break;
                            case 'employ':
                                if ($block->itemid && $resume && $resume->employments[$block->itemid]) {
                                    $itemData = $resume->employments[$block->itemid];
                                    // files?
//                                    $attachments = $itemData->attachments;
                                    $description = '';
                                    $description .= $itemData->jobtitle.': '.$itemData->employer;
                                    if ($itemData->startdate != '' || $itemData->enddate != '') {
                                        $description .= ' (';
                                        if ($itemData->startdate != '') {
                                            $description .= $itemData->startdate;
                                        }
                                        if ($itemData->enddate != '') {
                                            $description .= ' - '.$itemData->enddate;
                                        }
                                        $description .= ')';
                                    }
                                    if ($itemData->positiondescription != '') {
                                        $description .= '; '.$itemData->positiondescription;
                                    }
                                    $blockEntry->content .= $description;
                                }
                                break;
                            case 'certif':
                                if ($block->itemid && $resume && $resume->certifications[$block->itemid]) {
                                    $itemData = $resume->certifications[$block->itemid];
                                    // files?
//                                    $attachments = $itemData->attachments;
                                    $description = '';
                                    $description .= $itemData->title;
                                    if ($itemData->date != '') {
                                        $description .= ' ('.$itemData->date.')';
                                    }
                                    if ($itemData->description != '') {
                                        $description .= '; '.$itemData->description;
                                    }
                                    $blockEntry->content = $description;
                                }
                                break;
                            case 'public':
                                if ($block->itemid && $resume && $resume->publications[$block->itemid]) {
                                    $itemData = $resume->publications[$block->itemid];
                                    // files
//                                    $attachments = $itemData->attachments;
                                    $description = '';
                                    $description .= $itemData->title;
                                    if ($itemData->contribution != '') {
                                        $description .= ' ('.$itemData->contribution.')';
                                    }
                                    if ($itemData->date != '') {
                                        $description .= ' ('.$itemData->date.')';
                                    }
                                    if ($itemData->contributiondetails != '' || $itemData->url != '') {
                                        $description .= '; ';
                                        if ($itemData->contributiondetails != '') {
                                            $description .= $itemData->contributiondetails;
                                        }
                                        if ($itemData->url != '') {
                                            $description .= ' '.$itemData->url.' ';
                                        }
                                    }
                                    $blockEntry->content = $description;
                                }
                                break;
                            case 'mbrship':
                                if ($block->itemid && $resume && $resume->profmembershipments[$block->itemid]) {
                                    $itemData = $resume->profmembershipments[$block->itemid];
                                    // files?
//                                    $attachments = $itemData->attachments;
                                    $description = '';
                                    $description .= $itemData->title.' ';
                                    if ($itemData->startdate != '' || $itemData->enddate != '') {
                                        $description .= ' (';
                                        if ($itemData->startdate != '') {
                                            $description .= $itemData->startdate;
                                        }
                                        if ($itemData->enddate != '') {
                                            $description .= ' - '.$itemData->enddate;
                                        }
                                        $description .= ')';
                                    }
                                    if ($itemData->description != '') {
                                        $description .= '; '.$itemData->description;
                                    }
                                    $blockEntry->content = $description;
                                }
                                break;
                            case 'goalspersonal':
                            case 'goalsacademic':
                            case 'goalscareers':
                            case 'skillspersonal':
                            case 'skillsacademic':
                            case 'skillscareers':
                                // files?
//                                $attachments = @$resume->{$block->resume_itemtype.'_attachments'};
                                $description = '';
                                if ($resume && $resume->{$block->resume_itemtype}) {
                                    $description .= $resume->{$block->resume_itemtype}.' ';
                                }
                                $blockEntry->content = $description;
                                break;
                            case 'interests':
                                $description = '';
                                if ($resume->interests != '') {
                                    $description .= $resume->interests.' ';
                                }
                                $blockEntry->content = $description;
                                break;
                            default:
                                $blockEntry->content .= '!!! '.$block->resume_itemtype.' !!!';
                        }

                        break;
                    case 'text':
                    default:
                        $blockEntry->content .= format_text($block->text, FORMAT_HTML);
                        break;
                }
                $blockEntry->positionx = $block->positionx;
                $blockEntry->positiony = $block->positiony;
                if (!$blockEntry->content) {
                    unset($blockEntry->content);
                }

                $blocks[] = $blockEntry;
            }
            $viewEntry->blocks = $blocks;

            if (!$viewEntry->shareall) {
                unset($viewEntry->shareall);
            }
            if (!$viewEntry->externaccess) {
                unset($viewEntry->externaccess);
            }
            $viewsArr[] = $viewEntry;
        }
        if ($viewsArr) {
            $viewsArr = array('views' => $viewsArr);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $viewsArr);
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
            $commentsArr = [];
            foreach ($comments as $comment) {
                $commentEntry = new stdClass();
                $commentEntry->text = $comment->entry;
                $commentEntry->timemodified = transform::datetime(@$comment->timemodified);
                $itemEntry = new stdClass();
                $itemEntry->name = $comment->itemname;
                $userObj = $DB->get_record('user', ['id' => $comment->itemownerid]);
                $itemEntry->fromUser = fullname($userObj, $user->id);
                $commentEntry->item = $itemEntry;

                $commentsArr[] = $commentEntry;
            }
            $commentsArr = array('comments' => $commentsArr);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $commentsArr);
            $writer = writer::with_context($context);
            $writer->export_data(['Exabis ePortfolio/Comments/Artifacts'], $contextdata);
        }

    }

    public function deleteResumeData($resumeId)
    {
        global $DB;
        $DB->delete_records('block_exaportresume_certif', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_edu', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_employ', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_mbrship', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_linkedin', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_public', ['resume_id' => $resumeId]);
        $DB->delete_records('block_exaportresume_badges', ['resumeid' => $resumeId]);
        $DB->delete_records('block_exaportcompresume_mm', ['resumeid' => $resumeId]);
        return true;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
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
                self::deleteCategoryData($category->id);
            }
            $DB->delete_records('block_exaportcate', ['courseid' => $courseid]);

            // artifatcs
            $artifacts = $DB->get_records('block_exaportitem', ['courseid' => $courseid]);
            foreach ($artifacts as $artifact) {
                self::deleteArtifactData($artifact->id);
            }
            $DB->delete_records('block_exaportitem', ['courseid' => $courseid]);
            // other shared
            $DB->delete_records('block_exaportitemshar', ['courseid' => $courseid]);

            // resume
            $resumes = $DB->get_records('block_exaportresume', ['courseid' => $courseid]);
            foreach ($resumes as $resume) {
                self::deleteResumeData($resume->id);
            }
            $DB->delete_records('block_exaportresume', ['courseid' => $courseid]);

        }
        return;
    }

    public function deleteCategoryData($categoryId)
    {
        global $DB;
        $DB->delete_records('block_exaportcatshar', ['catid' => $categoryId]);
        $DB->delete_records('block_exaportcatgroupshar', ['catid' => $categoryId]);
        $DB->delete_records('block_exaportcat_structshar', ['catid' => $categoryId]);
        $DB->delete_records('block_exaportcat_strgrshar', ['catid' => $categoryId]);
        return true;
    }

    public function deleteArtifactData($artifactId)
    {
        global $DB;
        $DB->delete_records('block_exaportitemshar', ['itemid' => $artifactId]);
        $DB->delete_records('block_exaportitemcomm', ['itemid' => $artifactId]);
        $DB->delete_records('block_exaportviewblock', ['itemid' => $artifactId]);
        return true;
    }

    public function deleteViewData($viewId)
    {
        global $DB;
        $DB->delete_records('block_exaportviewblock', ['viewid' => $viewId]);
        $DB->delete_records('block_exaportviewshar', ['viewid' => $viewId]);
        $DB->delete_records('block_exaportviewgroupshar', ['viewid' => $viewId]);
        $DB->delete_records('block_exaportviewemailshar', ['viewid' => $viewId]);
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
                self::deleteResumeData($resume->id);
            }
            $DB->delete_records('block_exaportresume', ['user_id' => $userid]);

            // categories
            $cats = $DB->get_records('block_exaportcate', ['userid' => $userid, 'courseid' => $courseid]);
            foreach ($cats as $category) {
                self::deleteCategoryData($category->id);
            }
            $DB->delete_records('block_exaportcate', ['userid' => $userid, 'courseid' => $courseid]);

            // category relations to user (categories from other users)
            $DB->delete_records('block_exaportcatshar', ['userid' => $userid]);
            $DB->delete_records('block_exaportcat_structshar', ['userid' => $userid]);

            // artifacts
            $artifacts = $DB->get_records('block_exaportitem', ['userid' => $userid, 'courseid' => $courseid]);
            foreach ($artifacts as $artifact) {
                self::deleteArtifactData($artifact->id);
            }
            $DB->delete_records('block_exaportitem', ['userid' => $userid, 'courseid' => $courseid]);

            // artifact shares (into artefacts from other users)
            $DB->delete_records('block_exaportitemshar', ['userid' => $userid]);

            // my comments to artifacts
            $DB->delete_records('block_exaportitemcomm', ['userid' => $userid]);

            // views
            $views = $DB->get_records('block_exaportview', ['userid' => $userid]);
            foreach ($views as $view) {
                self::deleteViewData($view->id);
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

        list($inSql, $inParams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = $inParams;

        $select = " user_id {$inSql}";

        // personal data
        $DB->delete_records_select('block_exaportuser', $select, $params);

        // resume
        $resumes = $DB->get_record_select('block_exaportresume', $select, $params);
        foreach ($resumes as $resume) {
            self::deleteResumeData($resume->id);
        }
        $DB->delete_records_select('block_exaportresume', $select, $params);


        $select = " userid {$inSql}";

        // relations
        $DB->delete_records_select('block_exaportcatshar', $select, $params);
        $DB->delete_records_select('block_exaportcat_structshar', $select, $params);
        $DB->delete_records_select('block_exaportitemshar', $select, $params);
        $DB->delete_records_select('block_exaportitemcomm', $select, $params);
        $DB->delete_records_select('block_exaportview', $select, $params);
        $DB->delete_records_select('block_exaportviewshar', $select, $params);


        $params += ['courseid' => $courseid];
        $select = " userid {$inSql} AND courseid = :courseid ";

        // categories
        $cats = $DB->get_record_select('block_exaportcate', $select, $params);
        foreach ($cats as $category) {
            self::deleteCategoryData($category->id);
        }
        $DB->delete_records_select('block_exaportcate', $select, $params);

        // artifacts
        $artifacts = $DB->get_record_select('block_exaportitem', $select, $params);
        foreach ($artifacts as $artifact) {
            self::deleteArtifactData($artifact->id);
        }
        $DB->delete_records_select('block_exaportitem', $select, $params);


    }



}