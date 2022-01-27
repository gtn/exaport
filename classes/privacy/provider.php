<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Privacy Subsystem implementation for block_exaport.
 *
 * @package    block_exaport
 * @author     Jwalit Shah <jwalitshah@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_exaport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * The block_quickmail.
 *
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_exaportuser',
            [
                'user_id' => 'privacy:metadata:block_exaportuser:user_id',
                'description' => 'privacy:metadata:block_exaportuser:description',
            ],
            'privacy:metadata:block_exaportuser'
        );

        $collection->add_database_table(
            'block_exaportcate',
            [
                'userid' => 'privacy:metadata:block_exaportcate:userid',
                'name' => 'privacy:metadata:block_exaportcate:name',
                'courseid' => 'privacy:metadata:block_exaportcate:courseid',
            ],
            'privacy:metadata:block_exaportcate'
        );

        $collection->add_database_table(
            'block_exaportcatshar',
            [
                'userid' => 'privacy:metadata:block_exaportcatshar:userid',
            ],
            'privacy:metadata:block_exaportcatshar'
        );

        $collection->add_database_table(
            'block_exaportitem',
            [
                'userid' => 'privacy:metadata:block_exaportitem:userid',
                'courseid' => 'privacy:metadata:block_exaportitem:courseid',
                'externaccess' => 'privacy:metadata:block_exaportitem:externaccess',
                'externcomment' => 'privacy:metadata:block_exaportitem:externcomment',
            ],
            'privacy:metadata:block_exaportitem'
        );

        $collection->add_database_table(
            'block_exaportitemshar',
            [
                'itemid' => 'privacy:metadata:block_exaportitemshar:itemid',
                'userid' => 'privacy:metadata:block_exaportitemshar:userid',
                'original' => 'privacy:metadata:block_exaportitemshar:original',
                'courseid' => 'privacy:metadata:block_exaportitemshar:courseid',
            ],
            'privacy:metadata:block_exaportitemshar'
        );

        $collection->add_database_table(
            'block_exaportitemcomm',
            [
                'userid' => 'privacy:metadata:block_exaportitemcomm:userid',
            ],
            'privacy:metadata:block_exaportitemcomm'
        );

        $collection->add_database_table(
            'block_exaportview',
            [
                'userid' => 'privacy:metadata:block_exaportview:userid',
                'externaccess' => 'privacy:metadata:block_exaportview:externaccess',
                'externcomment' => 'privacy:metadata:block_exaportview:externcomment',
            ],
            'privacy:metadata:block_exaportview'
        );

        $collection->add_database_table(
            'block_exaportviewshar',
            [
                'userid' => 'privacy:metadata:block_exaportviewshar:userid',
            ],
            'privacy:metadata:block_exaportviewshar'
        );

        $collection->add_database_table(
            'block_exaportresume',
            [
                'user_id' => 'privacy:metadata:block_exaportresume:user_id',
                'courseid' => 'privacy:metadata:block_exaportresume:courseid',
            ],
            'privacy:metadata:block_exaportresume'
        );

        $collection->add_database_table(
            'block_exaportcat_structshar',
            [
                'userid' => 'privacy:metadata:block_exaportcat_structshar:userid',
            ],
            'privacy:metadata:block_exaportcat_structshar'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'contextlevel'  => CONTEXT_USER,
            'userid'        => $userid,
        ];

        $sql = "SELECT c.id
                  FROM {block_exaportuser} beu
                  JOIN {context} c ON c.instanceid = beu.user_id AND c.contextlevel = :contextlevel
                 WHERE beu.user_id = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportcate} bec
                  JOIN {context} c ON c.instanceid = bec.userid AND c.contextlevel = :contextlevel
                 WHERE bec.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportcatshar} becs
                  JOIN {context} c ON c.instanceid = becs.userid AND c.contextlevel = :contextlevel
                 WHERE becs.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportitem} bei
                  JOIN {context} c ON c.instanceid = bei.userid AND c.contextlevel = :contextlevel
                 WHERE bei.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportitemshar} beis
                  JOIN {context} c ON c.instanceid = beis.userid AND c.contextlevel = :contextlevel
                 WHERE beis.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportitemcomm} beic
                  JOIN {context} c ON c.instanceid = beic.userid AND c.contextlevel = :contextlevel
                 WHERE beic.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportview} bev
                  JOIN {context} c ON c.instanceid = bev.userid AND c.contextlevel = :contextlevel
                 WHERE bev.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportviewshar} bevs
                  JOIN {context} c ON c.instanceid = bevs.userid AND c.contextlevel = :contextlevel
                 WHERE bevs.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportresume} ber
                  JOIN {context} c ON c.instanceid = ber.user_id AND c.contextlevel = :contextlevel
                 WHERE ber.user_id = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {block_exaportcat_structshar} becs
                  JOIN {context} c ON c.instanceid = becs.userid AND c.contextlevel = :contextlevel
                 WHERE becs.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // TODO: Implement export_user_data() method.
        global $DB;
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $sql1 = "SELECT beu.*,
                  FROM {block_exaportuser} beu
                 WHERE beu.user_id = :userid
              ORDER BY beu.id";

        $sql2 = "SELECT bec.*,
                  FROM {block_exaportcate} bec
                 WHERE bec.user_id = :userid
              ORDER BY bec.id";

        $sql3 = "SELECT becs.*,
                  FROM {block_exaportcatshar} becs
                 WHERE becs.userid = :userid
              ORDER BY becs.id";

        $sql4 = "SELECT bei.*,
                  FROM {block_exaportitem} bei
                 WHERE bei.user = :userid
              ORDER BY bei.id";

        $sql5 = "SELECT beis.*,
                  FROM {block_exaportitemshar} beis
                 WHERE beis.userid = :userid
              ORDER BY beis.id";

        $sql6 = "SELECT beic.*,
                  FROM {block_exaportitemcomm} beic
                 WHERE beic.userid = :userid
              ORDER BY beic.id";

        $sql7 = "SELECT bev.*,
                  FROM {block_exaportview} bev
                 WHERE bev.userid = :userid
              ORDER BY bev.id";

        $sql8 = "SELECT bevs.*,
                  FROM {block_exaportviewshar} bevs
                 WHERE bevs.userid = :userid
              ORDER BY bevs.id";

        $sql9 = "SELECT ber.*,
                  FROM {block_exaportresume} ber
                 WHERE ber.user_id = :userid
              ORDER BY ber.id";

        $sql10 = "SELECT becss.*,
                  FROM {block_exaportcat_structshar} becss
                 WHERE becss.userid = :userid
              ORDER BY becss.id";

        $params = [
            'userid' => $userid
        ];

        $exaportbeu = $DB->get_records_sql($sql1, $params);
        $exaportbec = $DB->get_records_sql($sql2, $params);
        $exaportbecs = $DB->get_records_sql($sql3, $params);
        $exaportbei = $DB->get_records_sql($sql4, $params);
        $exaportbeis = $DB->get_records_sql($sql5, $params);
        $exaportbeic = $DB->get_records_sql($sql6, $params);
        $exaportbev = $DB->get_records_sql($sql7, $params);
        $exaportbevs = $DB->get_records_sql($sql8, $params);
        $exaportber = $DB->get_records_sql($sql9, $params);
        $exaportbecss = $DB->get_records_sql($sql10, $params);

        $data = (object) [
            'exaportuser' => $exaportbeu,
            'exaportcate' => $exaportbec,
            'exaportcatshar' => $exaportbecs,
            'exaportitem' => $exaportbei,
            'exaportitemshar' => $exaportbeis,
            'exaportitemcomm' => $exaportbeic,
            'exaportview' => $exaportbev,
            'exaportviewshar' => $exaportbevs,
            'exaportresume' => $exaportber,
            'exaportcat_structshar' => $exaportbecss,
        ];

        $subcontext = [
            get_string('pluginname', 'block_export'),
        ];

        writer::with_context($context)->export_data($subcontext, $data);

        $exaportbeu->close();
        $exaportbec->close();
        $exaportbecs->close();
        $exaportbei->close();
        $exaportbeis->close();
        $exaportbeic->close();
        $exaportbev->close();
        $exaportbevs->close();
        $exaportber->close();
        $exaportbecss->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('block_exaportuser', ['user_id' => $userid]);
        $DB->delete_records('block_exaportcate', ['userid' => $userid]);
        $DB->delete_records('block_exaportcatshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportitem', ['userid' => $userid]);
        $DB->delete_records('block_exaportitemshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportitemcomm', ['userid' => $userid]);
        $DB->delete_records('block_exaportview', ['userid' => $userid]);
        $DB->delete_records('block_exaportviewshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportresume', ['user_id' => $userid]);
        $DB->delete_records('block_exaportcat_structshar', ['userid' => $userid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('block_exaportuser', ['user_id' => $userid]);
        $DB->delete_records('block_exaportcate', ['userid' => $userid]);
        $DB->delete_records('block_exaportcatshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportitem', ['userid' => $userid]);
        $DB->delete_records('block_exaportitemshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportitemcomm', ['userid' => $userid]);
        $DB->delete_records('block_exaportview', ['userid' => $userid]);
        $DB->delete_records('block_exaportviewshar', ['userid' => $userid]);
        $DB->delete_records('block_exaportresume', ['user_id' => $userid]);
        $DB->delete_records('block_exaportcat_structshar', ['userid' => $userid]);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT * FROM {block_exaportcate}";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);

        $sql = "SELECT * FROM {block_exaportitem}";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);

        $sql = "SELECT * FROM {block_exaportitemshar}";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();

        // Sanity check that context is at the course context level.
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->set_field_select('block_exaportuser', 'user_id', 0, "user_id {$insql}", $inparams);
        $DB->set_field_select('block_exaportcate', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportcatshar', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportitem', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportitemshar', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportitemcomm', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportview', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportviewshar', 'userid', 0, "userid {$insql}", $inparams);
        $DB->set_field_select('block_exaportresume', 'user_id', 0, "user_id {$insql}", $inparams);
        $DB->set_field_select('block_exaportcat_structshar', 'userid', 0, "userid {$insql}", $inparams);
    }
}
