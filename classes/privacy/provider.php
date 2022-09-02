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
        foreach ($exaportcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $resumeData = [];
            $resume = block_exaport_get_resume_params($user->id, true);

            if ($resume) {
                $resume = array('resume' => $resume);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $resume);
                $writer = writer::with_context($context);
                $writer->export_data(['Exaport/Curriculum Vitae'], $contextdata);
            }

        }
        return true;


        // block_exacompcompuser
        // block_exacompexameval
        // get user's grades (reviews from teachers)
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $grades = array();
            $tree = block_exacomp_get_competence_tree($courseid);
            foreach ($tree as $subject) {
                if (!array_key_exists($subject->id, $grades)) {
                    $grades[$subject->id] = array();
                }
                $grades[$subject->id]['title'] = $subject->title;
                $grades[$subject->id]['titleshort'] = $subject->titleshort;
                $grades[$subject->id]['infolink'] = $subject->infolink;
                $grades[$subject->id]['description'] = $subject->description;
                $grades[$subject->id]['author'] = $subject->author;
                $assessment = block_exacomp_get_user_assesment_wordings($user->id, $subject->id, BLOCK_EXACOMP_TYPE_SUBJECT, $courseid);
                $grades[$subject->id]['assessment_grade'] = $assessment->grade;
                $grades[$subject->id]['assessment_niveau'] = $assessment->niveau;
                $grades[$subject->id]['assessment_selfgrade'] = $assessment->self_grade;
                $grades[$subject->id]['topics'] = array();
                foreach ($subject->topics as $topic) {
                    if (!array_key_exists($topic->id, $grades[$subject->id]['topics'])) {
                        $grades[$subject->id]['topics'][$topic->id] = array();
                    }
                    $grades[$subject->id]['topics'][$topic->id]['title'] = $topic->title;
                    $grades[$subject->id]['topics'][$topic->id]['description'] = $topic->description;
                    $assessment = block_exacomp_get_user_assesment_wordings($user->id, $topic->id, BLOCK_EXACOMP_TYPE_TOPIC, $courseid);
                    $grades[$subject->id]['topics'][$topic->id]['assessment_grade'] = $assessment->grade;
                    $grades[$subject->id]['topics'][$topic->id]['assessment_niveau'] = $assessment->niveau;
                    $grades[$subject->id]['topics'][$topic->id]['assessment_selfgrade'] = $assessment->self_grade;
                    $grades[$subject->id]['topics'][$topic->id]['descriptors'] = array();
                    foreach ($topic->descriptors as $descriptor) {
                        $assessment = block_exacomp_get_user_assesment_wordings($user->id, $descriptor->id, BLOCK_EXACOMP_TYPE_DESCRIPTOR, $courseid);
                        if (!array_key_exists($descriptor->id, $grades[$subject->id]['topics'][$topic->id]['descriptors'])) {
                            $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id] = array();
                        }
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['title'] = $descriptor->title;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['niveautitle'] = $descriptor->niveau_title;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['assessment_grade'] = $assessment->grade;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['assessment_niveau'] = $assessment->niveau;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['assessment_selfgrade'] = $assessment->self_grade;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'] = array();
                        foreach ($descriptor->examples as $example) {
                            $assessment = block_exacomp_get_user_assesment_wordings($user->id, $example->id, BLOCK_EXACOMP_TYPE_EXAMPLE, $courseid);
                            if ($assessment) {
                                if (!array_key_exists($example->id,
                                    $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'])) {
                                    $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id] =
                                        array();
                                }
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['assessment_grade'] =
                                    $assessment->grade;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['assessment_niveau'] =
                                    $assessment->niveau;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['assessment_selfgrade'] =
                                    $assessment->self_grade;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['title'] =
                                    $example->title;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['description'] =
                                    $example->description;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externalurl'] =
                                    $example->externalurl;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externalsolution'] =
                                    $example->externalsolution;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externaltask'] =
                                    $example->externaltask;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['author'] =
                                    $example->author;

                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id] =
                                    array_filter($grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]);
                            }
                        }
                        // TODO: subdescriptors?
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id] = array_filter($grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]);
                    }
                    $grades[$subject->id]['topics'][$topic->id] = array_filter($grades[$subject->id]['topics'][$topic->id]);
                }
                $grades[$subject->id] = array_filter($grades[$subject->id]);
            }
            if (count($grades)) {
                $grades = array('competences_overview' => $grades);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $grades);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/gradings'], $contextdata);
            }
        }

        // get user's grades (reviews AS a teacher)
        // does not kept real data of reviewed student. Only values. Is it correct?
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $grades = array();
            $tree = block_exacomp_get_competence_tree($courseid);
            foreach ($tree as $subject) {
                if (!array_key_exists($subject->id, $grades)) {
                    $grades[$subject->id] = array();
                }
                $grades[$subject->id]['title'] = $subject->title;
                $grades[$subject->id]['titleshort'] = $subject->titleshort;
                $grades[$subject->id]['infolink'] = $subject->infolink;
                $grades[$subject->id]['description'] = $subject->description;
                $grades[$subject->id]['author'] = $subject->author;
                $assessments = block_exacomp_get_teacher_assesment_wordings_array($user->id, $subject->id, BLOCK_EXACOMP_TYPE_SUBJECT, $courseid);
                $grades[$subject->id]['my_assessments'] = $assessments;
                $grades[$subject->id]['topics'] = array();
                foreach ($subject->topics as $topic) {
                    if (!array_key_exists($topic->id, $grades[$subject->id]['topics'])) {
                        $grades[$subject->id]['topics'][$topic->id] = array();
                    }
                    $grades[$subject->id]['topics'][$topic->id]['title'] = $topic->title;
                    $grades[$subject->id]['topics'][$topic->id]['description'] = $topic->description;
                    $assessments = block_exacomp_get_teacher_assesment_wordings_array($user->id, $topic->id, BLOCK_EXACOMP_TYPE_TOPIC, $courseid);
                    $grades[$subject->id]['topics'][$topic->id]['my_assessments'] = $assessments;
                    $grades[$subject->id]['topics'][$topic->id]['descriptors'] = array();
                    foreach ($topic->descriptors as $descriptor) {
                        $assessments = block_exacomp_get_teacher_assesment_wordings_array($user->id, $descriptor->id, BLOCK_EXACOMP_TYPE_DESCRIPTOR, $courseid);
                        if (!array_key_exists($descriptor->id, $grades[$subject->id]['topics'][$topic->id]['descriptors'])) {
                            $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id] = array();
                        }
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['title'] = $descriptor->title;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['niveautitle'] = $descriptor->niveau_title;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['my_assessment'] = $assessments;
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'] = array();
                        foreach ($descriptor->examples as $example) {
                            $assessments = block_exacomp_get_teacher_assesment_wordings_array($user->id, $example->id, BLOCK_EXACOMP_TYPE_EXAMPLE, $courseid);
                            if ($assessments) {
                                if (!array_key_exists($example->id,
                                    $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'])) {
                                    $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id] =
                                        array();
                                }
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['my_assessment'] = $assessments;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['title'] = $example->title;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['description'] = $example->description;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externalurl'] = $example->externalurl;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externalsolution'] = $example->externalsolution;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['externaltask'] = $example->externaltask;
                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]['author'] = $example->author;

                                $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id] =
                                    array_filter($grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]['examples'][$example->id]);
                            }
                        }
                        // TODO: subdescriptors?
                        $grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id] = array_filter($grades[$subject->id]['topics'][$topic->id]['descriptors'][$descriptor->id]);
                    }
                    $grades[$subject->id]['topics'][$topic->id] = array_filter($grades[$subject->id]['topics'][$topic->id]);
                }
                $grades[$subject->id] = array_filter($grades[$subject->id]);
            }
            if (count($grades)) {
                $grades = array('competences_reviews' => $grades);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $grades);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/reviews'], $contextdata);
            }

        }

        // block_exacompcmassign
        // does not need to export, because this data used only for comparing old<->new data
        // real data is exporting with quiz plugin

        // block_exacompcrossstud_mm
        // crossubjects related to students
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $crosssubjectsData = array();
            $crosssubjects = block_exacomp_get_cross_subjects_by_course($courseid, $user->id);
            foreach ($crosssubjects as $cross_subject) {
                $crosssubjectsData[$cross_subject->id] = array();
                $crosssubjectsData[$cross_subject->id]['title'] = $cross_subject->title;
                $crosssubjectsData[$cross_subject->id]['description'] = $cross_subject->description;
                $crosssubjectsData[$cross_subject->id]['subjects'] = array();
                $subjects = block_exacomp_get_competence_tree_for_cross_subject($courseid, $cross_subject, true, null, $user->id);
                foreach ($subjects as $subject) {
                    $crosssubjectsData[$cross_subject->id]['subjects'][] = $subject->title;
                }
                $assessment = block_exacomp_get_user_assesment_wordings($user->id, $cross_subject->id, BLOCK_EXACOMP_TYPE_CROSSSUB, $courseid);
                $crosssubjectsData[$cross_subject->id]['assessment_grade'] = $assessment->grade;
                $crosssubjectsData[$cross_subject->id]['assessment_niveau'] = $assessment->niveau;
                $crosssubjectsData[$cross_subject->id]['assessment_selfgrade'] = $assessment->self_grade;
                $crosssubjectsData[$cross_subject->id] = array_filter($crosssubjectsData[$cross_subject->id]);
                // all other data is in the subject/topic/... data (look above).  Is it true?
            }

            if (count($crosssubjectsData)) {
                $crosssubjectsData = array('crossubjects_reviews' => $crosssubjectsData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $crosssubjectsData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/crossubject gradings'], $contextdata);
            }
        }
        // crossubjects what I evaluate
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $crosssubjectsData = array();
            $crosssubjects = $DB->get_fieldset_select('block_exacompcompuser',
                'compid',
                ' reviewerid = ? AND comptype = ? ',
                [$user->id, BLOCK_EXACOMP_TYPE_CROSSSUB]
            );

            if ($crosssubjects) {
                $allcrossubjects = block_exacomp_get_crosssubjects();
                foreach ($crosssubjects as $crosssubjectid) {
                    if (!array_key_exists($crosssubjectid, $allcrossubjects)) {
                        continue;
                    }
                    $cross_subject = $allcrossubjects[$crosssubjectid];
                    $crosssubjectsData[$cross_subject->id] = array();
                    $crosssubjectsData[$cross_subject->id]['title'] = $cross_subject->title;
                    $crosssubjectsData[$cross_subject->id]['description'] = $cross_subject->description;
                    $subjects = block_exacomp_get_competence_tree_for_cross_subject($courseid, $cross_subject, true, null, $user->id);
                    $crosssubjectsData[$cross_subject->id]['subjects'] = array();
                    foreach ($subjects as $subject) {
                        $crosssubjectsData[$cross_subject->id]['subjects'][] = $subject->title;
                    }
                    $assessments = block_exacomp_get_teacher_assesment_wordings_array($user->id, $cross_subject->id,
                        BLOCK_EXACOMP_TYPE_CROSSSUB, $courseid);
                    $crosssubjectsData[$cross_subject->id]['my_assessment'] = $assessments;
                    $crosssubjectsData[$cross_subject->id] = array_filter($crosssubjectsData[$cross_subject->id]);
                    // all other data is in the subject/topic/... data (look above).  Is it true?
                }
            }

            if (count($crosssubjectsData)) {
                $crosssubjectsData = array('crossubjects_reviews' => $crosssubjectsData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $crosssubjectsData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/crossubject reviews'], $contextdata);
            }
        }

        // block_exacompdescrvisibility
        // which descriptors are visible
        // select only competences, which has relation to the student
        // if the table record has studentid = 0 (for all?) -  does not export
        // So: export only data, which is not default for user
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $descrvisiblesData = array();
            $descrhiddenData = array();
            $visibles = $DB->get_records_sql('SELECT d.title, dv.visible
                    FROM {' . BLOCK_EXACOMP_DB_DESCVISIBILITY . '} dv
                        LEFT JOIN {' . BLOCK_EXACOMP_DB_DESCRIPTORS . '} d ON dv.descrid = d.id
                    WHERE dv.studentid = ?
                        AND dv.courseid = ?
                    ',
                [$user->id, $courseid]
            );
            if ($visibles) {
                foreach ($visibles as $visible) {
                    if ($visible->visible) {
                        $descrvisiblesData[] = $visible->title;
                    } else {
                        $descrhiddenData[] = $visible->title;
                    }
                }
            }

            if (count($descrvisiblesData) || count($descrhiddenData)) {
                $descrvisibles = array('visible_competences' => $descrvisiblesData,
                    'hidden_competences' => $descrhiddenData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $descrvisibles);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/visible competences/descriptors'], $contextdata);
            }
        }

        // block_exacompexampvisibility
        // which examples are visible
        // select only materials, which has relation to the student
        // if the table record has studentid = 0 (for all?) -  does not export
        // So: export only data, which is not default for user
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $examvisiblesData = array();
            $examhiddenData = array();
            $visibles = $DB->get_records_sql('SELECT e.title, ev.visible
                    FROM {' . BLOCK_EXACOMP_DB_EXAMPVISIBILITY . '} ev
                        LEFT JOIN {' . BLOCK_EXACOMP_DB_EXAMPLES . '} e ON ev.exampleid = e.id
                    WHERE ev.studentid = ?
                        AND ev.courseid = ?
                    ',
                [$user->id, $courseid]
            );
            if ($visibles) {
                foreach ($visibles as $visible) {
                    if ($visible->visible) {
                        $examvisiblesData[] = $visible->title;
                    } else {
                        $examhiddenData[] = $visible->title;
                    }
                }
            }

            if (count($examvisiblesData) || count($examhiddenData)) {
                $examvisibles = array('visible_competences' => $examvisiblesData,
                    'hidden_competences' => $examhiddenData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $examvisibles);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/visible competences/examples'], $contextdata);
            }
        }

        // block_exacompexternaltrainer
        // external trainers for student
        $context = context_user::instance($user->id);
        $externaltrainersData = array();
        $externaltrainers = $DB->get_fieldset_sql('SELECT DISTINCT u.id
                FROM {' . BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS . '} et
                    LEFT JOIN {user} u ON et.trainerid = u.id
                WHERE et.studentid = ?
                ',
            [$user->id]
        );
        if ($externaltrainers) {
            foreach ($externaltrainers as $trainer) {
                $trainerobject = $DB->get_record('user', array('id' => $trainer));
                if ($trainerobject) {
                    $externaltrainersData[] = fullname($trainerobject);
                }
            }
        }
        if (count($externaltrainersData)) {
            $externaltrainersData = array('external_trainers' => $externaltrainersData);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $externaltrainersData);
            $writer = writer::with_context($context);
            $writer->export_data(['Exacomp/external/trainers'], $contextdata);
        }
        // my external students
        $context = context_user::instance($user->id);
        $externalstudentsData = array();
        $externalstudents = $DB->get_fieldset_sql('SELECT DISTINCT u.id
                FROM {' . BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS . '} et
                    LEFT JOIN {user} u ON et.studentid = u.id
                WHERE et.trainerid = ?
                ',
            [$user->id]
        );
        if ($externalstudents) {
            foreach ($externalstudents as $student) {
                $studentobject = $DB->get_record('user', array('id' => $student));
                if ($studentobject) {
                    $externalstudentsData[] = fullname($studentobject);
                }
            }
        }
        if (count($externalstudentsData)) {
            $externalstudentsData = array('external_students' => $externalstudentsData);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $externalstudentsData);
            $writer = writer::with_context($context);
            $writer->export_data(['Exacomp/external/students'], $contextdata);
        }

        // block_exacompprofilesettings
        $context = context_user::instance($user->id);
        $selectedCourcesData = array();
        $selectedCources = $DB->get_fieldset_sql('SELECT DISTINCT c.fullname
                FROM {block_exacompprofilesettings} ps
                    LEFT JOIN {course} c ON ps.itemid = c.id AND ps.block = ?
                WHERE ps.userid = ?
                ',
            ['exacomp', $user->id]
        );
        if ($selectedCources) {
            foreach ($selectedCources as $courseTitle) {
                $selectedCourcesData[] = $courseTitle; // title: is it enough?
            }
        }
        if (count($selectedCourcesData)) {
            $selectedCourcesData = array('courses_for_profile' => $selectedCourcesData);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, $selectedCourcesData);
            $writer = writer::with_context($context);
            $writer->export_data(['Exacomp/Competence profile/courses'], $contextdata);
        }

        // block_exacompschedule
        // which examples were added to student's scheduler
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $examplesData = array();
            $examples = $DB->get_records_sql(
                'SELECT DISTINCT e.title as example_title,
                                    s.creatorid as creator_id,
                                    s.timecreated as timecreated,
                                    s.timemodified as timemodified,
                                    s.sorting as sorting,
                                    s.start as startts,
                                    s.endtime as endts
                        FROM {' . BLOCK_EXACOMP_DB_SCHEDULE . '} s
                            LEFT JOIN {' . BLOCK_EXACOMP_DB_EXAMPLES . '} e ON e.id = s.exampleid
                        WHERE s.studentid = ?
                            AND s.courseid = ?
                            AND s.deleted = 0
                        ORDER BY s.sorting ',
                [$user->id, $courseid]
            );
            foreach ($examples as $example) {
                $creator = $DB->get_record('user', ['id' => $example->creator_id]);
                $examplesData[] = array_filter(array(
                    'scheduled_example' => $example->example_title,
                    'scheduled_author' => fullname($creator),
                    'scheduled_timecreated' => transform::datetime($example->timecreated),
                    'scheduled_timemodified' => transform::datetime($example->timemodified),
                    'scheduled_starttime' => ($example->startts ? transform::datetime($example->startts) : ''),
                    'scheduled_endtime' => ($example->endts ? transform::datetime($example->endts) : ''),
                    //'scheduled_sorting_position' => transform::datetime($example->timemodified),
                ));
            }

            if (count($examplesData)) {
                $examplesData = array('scheduled_examples' => $examplesData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $examplesData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/scheduled examples'], $contextdata);
            }
        }
        // which examples were added from me
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $examplesData = array();
            $examples = $DB->get_records_sql(
                'SELECT DISTINCT e.title as example_title,
                                    s.studentid as student_id,
                                    s.timecreated as timecreated,
                                    s.timemodified as timemodified,
                                    s.sorting as sorting,
                                    s.start as startts,
                                    s.endtime as endts
                        FROM {' . BLOCK_EXACOMP_DB_SCHEDULE . '} s
                            LEFT JOIN {' . BLOCK_EXACOMP_DB_EXAMPLES . '} e ON e.id = s.exampleid
                        WHERE s.creatorid = ?
                            AND s.courseid = ?
                            AND s.deleted = 0
                        ORDER BY s.sorting ',
                [$user->id, $courseid]
            );
            foreach ($examples as $example) {
                $student = $DB->get_record('user', ['id' => $example->student_id]);
                $examplesData[] = array_filter(array(
                    'scheduled_example' => $example->example_title,
                    //'scheduled_student' => fullname($student), // to add name of student?
                    'scheduled_timecreated' => transform::datetime($example->timecreated),
                    'scheduled_timemodified' => transform::datetime($example->timemodified),
                    'scheduled_starttime' => ($example->startts ? transform::datetime($example->startts) : ''),
                    'scheduled_endtime' => ($example->endts ? transform::datetime($example->endts) : ''),
                    // may be to add count of related students?
                    //'scheduled_sorting_position' => transform::datetime($example->timemodified),
                ));
            }

            if (count($examplesData)) {
                $examplesData = array('scheduled_examples' => $examplesData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $examplesData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/my scheduled examples'], $contextdata);
            }
        }

        // block_exacompsolutvisibility
        // solutions visibility
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $solutvisiblesData = array();
            $soluthiddenData = array();
            $visibles = $DB->get_records_sql('SELECT e.title, sol.visible
                    FROM {' . BLOCK_EXACOMP_DB_SOLUTIONVISIBILITY . '} sol
                        LEFT JOIN {' . BLOCK_EXACOMP_DB_EXAMPLES . '} e ON sol.exampleid = e.id
                    WHERE sol.studentid = ?
                        AND sol.courseid = ?
                    ',
                [$user->id, $courseid]
            );
            if ($visibles) {
                foreach ($visibles as $visible) {
                    if ($visible->visible) {
                        $solutvisiblesData[] = $visible->title;
                    } else {
                        $soluthiddenData[] = $visible->title;
                    }
                }
            }

            if (count($solutvisiblesData) || count($soluthiddenData)) {
                $solutvisiblesData = array('visible_solutions' => $solutvisiblesData,
                    'hidden_solutions' => $soluthiddenData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $solutvisiblesData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/visible competences/solutions'], $contextdata);
            }
        }

        // block_exacomptopicvisibility
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $topicvisiblesData = array();
            $topichiddenData = array();
            $visibles = $DB->get_records_sql('SELECT t.title, tv.visible
                    FROM {' . BLOCK_EXACOMP_DB_TOPICVISIBILITY . '} tv
                        LEFT JOIN {' . BLOCK_EXACOMP_DB_TOPICS . '} t ON tv.topicid = t.id
                    WHERE tv.studentid = ?
                        AND tv.courseid = ? AND tv.niveauid IS NULL
                    ',
                [$user->id, $courseid]
            );
            if ($visibles) {
                foreach ($visibles as $visible) {
                    if ($visible->visible) {
                        $topicvisiblesData[] = $visible->title;
                    } else {
                        $topichiddenData[] = $visible->title;
                    }
                }
            }

            if (count($topicvisiblesData) || count($topichiddenData)) {
                $topicsvisibles = array('visible_topics' => $topicvisiblesData,
                    'hidden_topics' => $topichiddenData);
                //$context = \context_course::instance($courseid);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $topicsvisibles);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/visible competences/topics'], $contextdata);
            }
        }

        // block_exacompwsdata
        // does not need to export, because it is temporary data for working of webservices

        // block_exacompcrosssubjects
        // which cross-subjects was added by me
        foreach ($exacompcoursescontexts as $context) {
            $courseid = $context->instanceid;
            $crossData = array();
            $crosssubjects = $DB->get_records_sql(
                'SELECT DISTINCT cs.title as cs_title,
                                    cs.description as cs_description,
                                    cs.shared as cs_shared,
                                    s.title as cs_subject,
                                    cs.groupcategory as cs_groupcategory 
                        FROM {' . BLOCK_EXACOMP_DB_CROSSSUBJECTS . '} cs
                            LEFT JOIN {' . BLOCK_EXACOMP_DB_SUBJECTS . '} s ON s.id = cs.subjectid                            
                        WHERE cs.creatorid = ?
                            AND cs.courseid = ?                            
                        ORDER BY cs.sorting ',
                [$user->id, $courseid]
            );
            foreach ($crosssubjects as $crosssubject) {
                $crossData[] = array_filter(array(
                    'title' => $crosssubject->cs_title,
                    'description' => $crosssubject->cs_description,
                    'shared' => $crosssubject->cs_shared,
                    'groupcategory ' => $crosssubject->cs_groupcategory,
                    'related_subject' => $crosssubject->cs_subject,
                ));
            }

            if (count($crossData)) {
                $crossData = array('crosssubjects' => $crossData);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $crossData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/my cross-subjects'], $contextdata);
            }
        }

        // block_exacompglobalgradings
        // global gradings for the students
        foreach ($exacompcoursescontexts as $context) {
            $gradingsData = array();
            $gradings = $DB->get_records_sql(
                'SELECT DISTINCT g.compid as compid,
                                    g.comptype as comptype,
                                    g.globalgradings as globalgradings                                     
                        FROM {' . BLOCK_EXACOMP_DB_GLOBALGRADINGS . '} g                                                        
                        WHERE g.userid = ?',
                [$user->id]
            );
            foreach ($gradings as $grading) {
                $gradingsData[] = array_filter(array(
                    'compid' => $grading->compid,
                    'comptype' => $grading->comptype,
                    'globalgradings' => $grading->globalgradings,
                ));
            }

            if (count($gradingsData)) {
                $gradingsData = array('globalgradings' => $gradingsData);
                $contextdata = helper::get_context_data($context, $user);
                $contextdata = (object) array_merge((array) $contextdata, $gradingsData);
                $writer = writer::with_context($context);
                $writer->export_data(['Exacomp/my global gradings data'], $contextdata);
            }
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