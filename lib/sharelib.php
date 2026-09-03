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

namespace {

    use block_exaport\globals as g;

    defined('MOODLE_INTERNAL') || die();

    function block_exaport_get_external_view_url(stdClass $view, $userid = -1) {
        global $CFG, $USER;
        if ($userid == -1) {
            $userid = $USER->id;
        }
        // Bei epop wird userid mitgegeben, sonst aus global USER holen.
        return $CFG->wwwroot . '/blocks/exaport/shared_view.php?access=hash/' . $userid . '-' . $view->hash;
    }

    function block_exaport_get_external_category_url(stdClass $category, $userid = -1) {
        global $CFG, $USER;
        if ($userid == -1) {
            $userid = $USER->id;
        }
        // Mirror the shared-view hash format to keep one predictable external access pattern.
        return $CFG->wwwroot . '/blocks/exaport/view_items.php?access=hash/' . $userid . '-' . $category->hash;
    }

    /**
     * Build the sharing tooltip from resolved share detail.
     *
     * When $html is true (default): lines are joined with '<br><br>' and user/group names
     * are escaped with s() — suitable for data-bs-title with data-bs-html="true".
     * When $html is false: lines are joined with ' | ' and names are NOT escaped —
     * suitable for plain title="" attributes (the caller is responsible for attribute escaping).
     *
     * @param \block_exaport\share_info $share Resolved sharing detail.
     * @param bool                      $html  True for HTML output, false for plain text.
     * @return string Tooltip string.
     */
    function block_exaport_get_share_tooltip(\block_exaport\share_info $share, bool $html = true): string {
        $lines = [];
        if ($share->all) {
            $lines[] = block_exaport_get_string('share_tooltip_all');
        } else if ($share->users) {
            $names = $html ? implode(', ', array_map('s', $share->users)) : implode(', ', $share->users);
            $lines[] = block_exaport_get_string('share_tooltip_users', $names);
        }
        if ($share->groups) {
            $names = $html ? implode(', ', array_map('s', $share->groups)) : implode(', ', $share->groups);
            $lines[] = block_exaport_get_string('share_tooltip_groups', $names);
        }
        if ($share->external) {
            $lines[] = block_exaport_get_string('share_tooltip_external');
        }
        return implode($html ? '<br><br>' : ' | ', $lines);
    }

    /**
     * Build the short sharing summary used by sharing overview pages.
     *
     * @param \block_exaport\share_info $share Resolved sharing detail
     * @return string
     */
    function block_exaport_get_share_summary(\block_exaport\share_info $share): string {
        if ($share->all) {
            return block_exaport_get_string('sharedwith_shareall');
        }
        if (count($share->groups) > 1) {
            return block_exaport_get_string('sharedwith_group_cnt', count($share->groups));
        }
        if ($share->groups) {
            return block_exaport_get_string('sharedwith_group');
        }
        if (count($share->users) > 1) {
            return block_exaport_get_string('sharedwith_user_cnt', count($share->users));
        }
        if ($share->users) {
            return block_exaport_get_string('sharedwith_onlyme');
        }
        if ($share->external) {
            return block_exaport_get_string('sharedwith_shareexternal');
        }
        return '';
    }

    function block_exaport_get_user_from_access($access, $epopaccess = false) {
        global $DB;

        $accesspath = explode('/', $access);
        if (count($accesspath) != 2) {
            return;
        }

        if ($accesspath[0] == 'hash') {
            $hash = $accesspath[1];

            $conditions = array("user_hash" => $hash);
            if (!$portfoliouser = $DB->get_record("block_exaportuser", $conditions)) {
                // No portfolio user with this hash.
                return;
            }
            $conditions = array("id" => $portfoliouser->user_id);
            if (!$user = $DB->get_record("user", $conditions)) {
                // User not found.
                return;
            }

            // Keine rechte �berpr�fung, weil �ber den hash user immer erreichbar ist aber nur die geshareten items
            // angezeigt werden vielleicht in zukunft eine externaccess eingenschaft f�r den user einf�gen?

            $user->access = new stdClass();
            $user->access->request = 'extern';

            return $user;
        } else if ($accesspath[0] == 'id') {
            // Guest not allowed
            // require exaport:use -> guest hasn't this right.
            $context = context_system::instance();
            if ($epopaccess == false) {
                require_capability('block/exaport:use', $context);
            }

            $userid = $accesspath[1];

            $conditions = array("user_id" => $userid);
            $userpreferences = block_exaport_get_user_preferences($userid); // We need it for creating record if it is not existing.
            if (!$portfoliouser = $DB->get_record("block_exaportuser", $conditions)) {
                // TODO: why is this needed?
                // No portfolio user with this id.
                return;
            }

            $conditions = array("id" => $portfoliouser->user_id);
            if (!$user = $DB->get_record("user", $conditions)) {
                // User not found.
                return;
            }

            // No more checks needed.

            $user->access = new stdClass();
            $user->access->request = 'intern';

            return $user;
        }
    }

    function block_exaport_get_view_from_access($access, $pdfaccess = false, $pdfforuserid = 0) {
        global $USER, $DB;

        if (!block_exaport_feature_enabled('views')) {
            // Only allowed if views are enabled.
            return;
        }

        $accesspath = explode('/', $access);
        // Standard modes (hash, id, email) use exactly 2 path segments.
        // Category mode uses 3 segments: "category/hash/{userid}-{categoryhash}".
        $accesstype = $accesspath[0];
        if ($accesstype !== 'category' && count($accesspath) != 2) {
            return;
        }
        if ($accesstype === 'category' && count($accesspath) != 3) {
            return;
        }

        $view = null;

        if ($accesspath[0] == 'hash') {

            if (!block_exaport_externaccess_enabled()) {
                return;
            }

            $hash = $accesspath[1];
            $hash = explode('-', $hash);

            if (count($hash) != 2) {
                return;
            }

            $userid = clean_param($hash[0], PARAM_INT);
            $hash = clean_param($hash[1], PARAM_ALPHANUM);

            if (empty($userid) || empty($hash)) {
                return;
            }
            $conditions = array("userid" => $userid, "hash" => $hash, "externaccess" => 1);
            if (!$view = $DB->get_record("block_exaportview", $conditions)) {
                // View not found.
                return;
            }

            $view->access = new stdClass();
            $view->access->request = 'extern';
        } else if ($accesspath[0] == 'id') {
            // Guest not allowed.
            // require exaport:use -> guest hasn't this right.
            $context = context_system::instance();
            if (!$pdfaccess) {
                require_capability('block/exaport:use', $context);
            }
            // Groups for user.
            $usergroups = block_exaport_get_user_cohorts();

            $hash = $accesspath[1];
            $hash = explode('-', $hash);

            if (count($hash) != 2) {
                return;
            }

            if ($pdfaccess && $pdfforuserid > 0) {
                $userid = $pdfforuserid;
                $myuserid = $pdfforuserid;
            } else {
                $userid = clean_param($hash[0], PARAM_INT);
                $myuserid = $USER->id;
            }
            $viewid = clean_param($hash[1], PARAM_INT);

            $tempjoin = '';
            if (is_array($usergroups) && count($usergroups) > 0) {
                $tempjoin .= " LEFT JOIN {block_exaportviewgroupshar} vgshar ON v.id = vgshar.viewid";
            }

            // Category-based grant: build the set of view ids reachable via shared categories.
            // NOTE: This runs on every internal view access (not only category-granted ones) because it sits
            // on the hot path for all id/-based view lookups. Cost scales with the number of categories shared
            // to the user: one subtree query (block_exaport_get_owned_category_tree_ids) per shared category,
            // followed by a final IN (...) clause. This is deliberately eager for simplicity; if it becomes a
            // bottleneck it could be made lazy (only evaluated when the primary WHERE fails) or cached in a
            // static variable for the duration of the request.
            $categoryviewids = \block_exaport\view_helper::get_category_shared_view_ids($myuserid);
            $categoryclause = '';
            $categoryparams = [];
            if (!empty($categoryviewids)) {
                [$catinsql, $categoryparams] = $DB->get_in_or_equal($categoryviewids, SQL_PARAMS_QM);
                $categoryclause = " OR v.id $catinsql";
            }

            $params = array_merge([$myuserid, $userid, $viewid, $myuserid], $categoryparams);
            $view = $DB->get_record_sql("SELECT DISTINCT v.* FROM {block_exaportview} v" .
                " LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid = ?" .
                $tempjoin .
                " WHERE v.userid = ? AND v.id = ? AND" .
                " ((v.userid = ?)" . // Myself.
                (block_exaport_shareall_enabled() ? " OR (v.shareall = 1)" : "") . // Shared all.
                "  OR (vshar.userid IS NOT NULL) " .
                ($usergroups ? " OR vgshar.groupid IN (" . join(',', array_keys($usergroups)) . ") " : "") .
                $categoryclause .
                ")", $params); // Shared for me.
            if (!$view) {
                // View not found.
                return;
            }

            $view->access = new stdClass();
            $view->access->request = 'intern';
        } else if ($accesspath[0] == 'category') {
            // External category-hash access for views: "category/hash/{userid}-{categoryhash}".
            // The category access token is the remainder of the path: "hash/{userid}-{categoryhash}".
            $categoryaccess = $accesspath[1] . '/' . $accesspath[2];
            if (!$category = block_exaport_get_category_from_access($categoryaccess)) {
                return;
            }

            $categoryids = block_exaport_get_owned_category_tree_ids($category->id, $category->userid);
            if (empty($categoryids)) {
                return;
            }

            $viewidparam = optional_param('viewid', 0, PARAM_INT);
            if (empty($viewidparam)) {
                return;
            }

            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_QM);
            $params = array_merge([$viewidparam, $category->userid], $inparams);
            $view = $DB->get_record_sql(
                "SELECT DISTINCT v.*
                   FROM {block_exaportview} v
                   JOIN {block_exaportviewcate} vc ON vc.viewid = v.id
                  WHERE v.id = ?
                    AND v.userid = ?
                    AND vc.cateid $insql",
                $params
            );
            if (!$view) {
                return;
            }

            $view->access = new stdClass();
            $view->access->request = 'extern';
        } else if ($accesspath[0] == 'email') {

            if (!block_exaport_shareemails_enabled()) {
                return;
            }

            $hash = explode('-', $accesspath[1]);
            if (count($hash) != 2) {
                return;
            }

            list($viewhash, $emailhash) = $hash;

            if (!$view = $DB->get_record("block_exaportview", ["hash" => $viewhash])) {
                // View not found.
                return;
            };

            if ($view->sharedemails != 1) {
                // View is not shared for any emails.
                return;
            };

            // Check email-phrase.
            if (!$DB->record_exists('block_exaportviewemailshar', ['viewid' => $view->id, 'hash' => $emailhash])) {
                return;
            };

            $view->access = new stdClass();
            $view->access->request = 'extern';
        }
        return $view;
    }

    function block_exaport_get_category_from_access($access) {
        global $DB;

        if (!block_exaport_externaccess_enabled()) {
            // Keep one central guard so all category external entry points fail closed when the feature is disabled.
            return;
        }

        $accesspath = explode('/', $access);
        if (count($accesspath) != 2 || $accesspath[0] !== 'hash') {
            return;
        }

        $hashparts = explode('-', $accesspath[1]);
        if (count($hashparts) != 2) {
            return;
        }

        $userid = clean_param($hashparts[0], PARAM_INT);
        $hash = clean_param($hashparts[1], PARAM_ALPHANUM);
        if (empty($userid) || empty($hash) || strlen($hash) !== 8) {
            // Category hashes are fixed-length (CHAR(8)); reject any unexpected token length early.
            return;
        }

        // externaccess=1 is mandatory so a leaked old hash cannot be used after the owner disables sharing.
        $conditions = ['userid' => $userid, 'hash' => $hash, 'externaccess' => 1];
        $category = $DB->get_record('block_exaportcate', $conditions);
        if (!$category) {
            return;
        }

        $category->access = new stdClass();
        $category->access->request = 'extern';
        return $category;
    }

    /**
     * Recursive function to get all category IDs in the tree rooted at $categoryid that are owned by $userid.
     * @param int $categoryid
     * @param int $userid
     * @param array $visited
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    function block_exaport_get_owned_category_tree_ids($categoryid, $userid, &$visited = []) {
        global $DB;

        $categoryid = clean_param($categoryid, PARAM_INT);
        $userid = clean_param($userid, PARAM_INT);
        if (empty($categoryid) || empty($userid)) {
            return [];
        }

        if (isset($visited[$categoryid])) {
            // Defensive loop-breaker: category trees should be acyclic, but we never trust data integrity blindly for access checks.
            return [];
        }

        $rootcategory = $DB->get_record('block_exaportcate', ['id' => $categoryid, 'userid' => $userid], 'id');
        if (!$rootcategory) {
            return [];
        }

        $visited[$categoryid] = true;
        $ids = [$categoryid];
        $children = $DB->get_records('block_exaportcate', ['pid' => $categoryid, 'userid' => $userid], '', 'id');
        foreach ($children as $child) {
            $ids = array_merge($ids, block_exaport_get_owned_category_tree_ids($child->id, $userid, $visited));
        }

        return array_values(array_unique($ids));
    }

    function block_exaport_get_item_epop($id, $user) {
        global $DB;
        $sql = "SELECT i.* FROM {block_exaportitem} i WHERE id=? AND userid=?";
        if (!$item = $DB->get_record_sql($sql, array($id, $user->id))) {
            return false;
        } else {
            return $item;
        }
    }

    function block_exaport_get_item_for_webservice($itemid, $itemOwnerid, $currentUserid) {
        global $DB;
        // Check if user is userid or if user is trainer of userid.
        if ($itemOwnerid == $currentUserid) {
            return $DB->get_record('block_exaportitem', array('id' => $itemid, 'userid' => $itemOwnerid));
        }

        // old external trainer logic
        $found = $DB->record_exists(BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS, array('trainerid' => $currentUserid, 'studentid' => $itemOwnerid));
        if ($found) {
            return $DB->get_record('block_exaportitem', array('id' => $itemid));
        }

        // in a view shared with user?
        $sql = "SELECT * FROM {block_exaportview} v " .
            " JOIN {block_exaportviewblock} vb ON v.id = vb.viewid AND vb.itemid = ? " .
            " JOIN {block_exaportviewshar} vs ON v.id = vs.viewid AND vs.userid = ? ";
        $found = $DB->record_exists_sql($sql, array($itemid, $currentUserid));
        if ($found) {
            return $DB->get_record('block_exaportitem', array('id' => $itemid));
        }

        // in an exacomp course (for diggr+ / dakora+)
        if (class_exists('\block_exacomp\api')) {
            $courseid = $DB->get_field('block_exaportitem', 'courseid', array('id' => $itemid));
            if ($courseid && block_exacomp_is_teacher($courseid, $currentUserid)) {
                return $DB->get_record('block_exaportitem', array('id' => $itemid));
            }
        }

        return false;
    }

    function block_exaport_epop_checkhash($userhash) {
        global $DB;

        $sql = "SELECT u.* " .
            " FROM {user} u " .
            " INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id " .
            " WHERE eu.user_hash_long=?";
        if (!$user = $DB->get_record_sql($sql, array($userhash))) {
            return false;
        } else {
            return $user;
        }
    }

    function block_exaport_get_item($itemid, $access, $epopaccess = false, $pdfaccess = false, $pdfforuserid = 0) {
        global $CFG, $USER, $DB;

        $itemid = clean_param($itemid, PARAM_INT);

        $item = null;
        if (preg_match('!^view/(.+)$!', $access, $matches)) {
            // In view mode.
            if (!$view = block_exaport_get_view_from_access($matches[1], $pdfaccess, $pdfforuserid)) {
                throw new \block_exacomp\permission_exception("viewnotfound", "block_exaport");
            }
            // Parameter richtig?!
            if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
                $sql = "SELECT b.* FROM {block_exaportviewblock} b
                        WHERE b.viewid=? AND
                        b.itemid=? AND
                        CAST(b.type AS varchar) = 'item'
                        LIMIT 1";
            } else {
                $sql = "SELECT b.* FROM {block_exaportviewblock} b
                        WHERE b.viewid=? AND
                        b.itemid=? AND
                        b.type = 'item'
                        LIMIT 1";
            }

            $viewblock = $DB->get_record_sql($sql, array($view->id, $itemid)); // Nobody, but me.

            // Share artefact can not only owner. So we find did share item to others users.
            // If shared - take owner and insert into select.
            $sharable = block_exaport_can_user_access_shared_item($view->userid, $itemid);
            if ($sharable) {
                $ownerid = $sharable;
            } else {
                $ownerid = $view->userid;
            }
            $conditions = array("id" => $itemid, "userid" => $ownerid);
            if (!$item = $DB->get_record("block_exaportitem", $conditions)) {
                // Item not found.
                return;
            }
            $item->access = $view->access;
            $item->access->page = 'view';
            // Comments allowed?
            if ($item->access->request == 'extern') {
                $item->allowComments = false;
                $item->showComments = block_exaport_external_comments_enabled() && $view->externcomment;
            } else {
                $item->allowComments = true;
                $item->showComments = true;
            }

        } else if (preg_match('!^portfolio/(.+)$!', $access, $matches)) {
            // In user portfolio mode.
            if (!$user = block_exaport_get_user_from_access($matches[1], $epopaccess)) {
                return;
            }

            if ($user->access->request == 'extern') {
                $conditions = array("id" => $itemid, "userid" => $user->id);
                if (!$item = $DB->get_record("block_exaportitem", $conditions, "externaccess", 1)) {
                    // Item not found.
                    return;
                }
            } else {
                // Intern
                // Shared artefacts.
                $sharable = block_exaport_can_user_access_shared_item($USER->id, $itemid);
                if ($sharable) {
                    $ownerid = $sharable;
                } else {
                    $ownerid = $USER->id;
                }

                $item = $DB->get_record('block_exaportitem', ['userid' => $ownerid, 'id' => $itemid]);
                if (!$item) {
                    // Item not found.
                    return;
                }
            }

            $item->access = $user->access;
            $item->access->page = 'portfolio';
            // Comments allowed?
            if ($item->access->request == 'extern') {
                $item->allowComments = false;
                $item->showComments = $item->externcomment;
            } else {
                $item->allowComments = true;
                $item->showComments = true;
            }
        } else if (preg_match('!^category/(.+)$!', $access, $matches)) {
            // External shared category mode.
            if (!$category = block_exaport_get_category_from_access($matches[1])) {
                return;
            }

            // Only allow files from items that belong to this shared category tree AND the owner.
            $categoryids = block_exaport_get_owned_category_tree_ids($category->id, $category->userid);
            if (empty($categoryids)) {
                return;
            }

            [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_QM);
            $params = array_merge([$itemid, $category->userid], $params);
            $sql = "SELECT i.*
                      FROM {block_exaportitem} i
                      JOIN {block_exaportitemcate} ic ON ic.itemid = i.id
                     WHERE i.id = ?
                       AND i.userid = ?
                       AND ic.cateid $insql";
            $item = $DB->get_record_sql($sql, $params);
            if (!$item) {
                return;
            }

            $item->access = new stdClass();
            $item->access->request = 'extern';
            $item->access->page = 'category';
            // External viewers are anonymous guests, so interactive operations are intentionally disabled.
            $item->allowComments = false;
            // Show comments read-only if the category owner enabled externcomment and the global setting allows it.
            $item->showComments = block_exaport_external_comments_enabled() && !empty($category->externcomment);
        } else {
            return;
        }

        $item->access->access = $access;
        $item->access->parentAccess = substr($item->access->access, strpos($item->access->access, '/') + 1);

        return $item;
    }

    function exaport_get_shareable_courses() {
        global $USER, $COURSE;

        $courses = array();

        // Loop through all my courses.
        foreach (get_my_courses($USER->id, 'fullname ASC') as $dbcourse) {

            $course = array(
                'id' => $dbcourse->id,
                'fullname' => $dbcourse->fullname,
            );

            $courses[$course['id']] = $course;
        }

        // Move active course to first position.
        if (isset($courses[$COURSE->id])) {
            $course = $courses[$COURSE->id];
            unset($courses[$COURSE->id]);
            $courses = array_merge(array($course['id'] => $course), $courses);
        }

        return $courses;
    }

    // TODO: test
    function exaport_is_any_notifying_enabled_for_view($view) {
        global $DB;
        /*
         * $shared = g::$DB->record_exists('block_exaportviewshar') ||
            g::$DB->record_exists('block_exaportviewgroupshar') ||
            g::$DB->record_exists('block_exaportviewemailshar') ||
            g::$DB->record_exists('block_exaportcatshar') ||
            g::$DB->record_exists('block_exaportcatgroupshar');
         */
        $shared = $DB->record_exists('block_exaportviewshar', array('viewid' => $view->id, 'notify' => 1)) ||
            $DB->record_exists('block_exaportviewgroupshar', array('viewid' => $view->id)) || // TODO: add notify to group sharing?
            (isset($view->sharedemails) && $view->sharedemails && $DB->record_exists('block_exaportviewemailshar', array('viewid' => $view->id))); // TODO: check this in general
        return $shared;
    }

    function exaport_notify_single_user($dbviewid, $notifyuserid, $name, $subject, $courseid){
        global $USER, $CFG;
        $url = $CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $courseid . '&access=id/' . $USER->id . '-' . $dbviewid;
        exaport_send_notification($notifyuserid, $name, $subject, $url);
    }

    /**
     * Generic low-level notification helper.
     *
     * @param int    $notifyuserid  Recipient user ID.
     * @param string $name          Message provider name (e.g. 'viewupdated', 'categoryupdated').
     * @param string $subject       Notification subject string.
     * @param string $url           Full URL to include in the notification body.
     */
    function exaport_send_notification($notifyuserid, $name, $subject, $url) {
        global $USER, $DB;
        $notificationdata = new \core\message\message();
        $notificationdata->component = 'block_exaport';
        $notificationdata->name = $name;
        $notificationdata->userfrom = $USER;
        $notificationdata->userto = $DB->get_record('user', array('id' => $notifyuserid));
        $notificationdata->subject = $subject;
        $notificationdata->fullmessage = $url;
        $notificationdata->fullmessageformat = FORMAT_HTML;
        $notificationdata->fullmessagehtml = '<a href="' . $url . '">' . $url . '</a>';
        $notificationdata->smallmessage = '';
        $notificationdata->notification = 1;
        message_send($notificationdata);
    }

    /**
     * Send notifications to all users who have a shared category with notify=1 enabled,
     * when a new item is added to that category.
     *
     * @param int $categoryid  The category ID the item was added to.
     * @param int $courseid    The course ID (used to build the URL).
     */
    function exaport_send_category_notifications($categoryid, $courseid) {
        global $USER, $DB, $CFG;

        $category = $DB->get_record('block_exaportcate', array('id' => $categoryid));
        if (!$category || !$category->internshare) {
            return;
        }

        $a = (object)[
            'sendername' => fullname($USER),
            'title' => $category->name,
        ];
        $subject = get_string('i_updated_category', 'block_exaport', $a);
        $url = $CFG->wwwroot . '/blocks/exaport/shared_categories.php?courseid=' . $courseid;

        // Notify individual shared users with notify=1.
        $notifieduserids = array();
        $sharedusers = $DB->get_records('block_exaportcatshar', array('catid' => $categoryid));
        foreach ($sharedusers as $share) {
            if (empty($share->notify) || $share->userid == $USER->id) {
                continue;
            }
            exaport_send_notification($share->userid, 'categoryupdated', $subject, $url);
            $notifieduserids[$share->userid] = true;
        }

        // Notify members of shared groups (cohorts).
        $sharedgroups = $DB->get_records('block_exaportcatgroupshar', array('catid' => $categoryid));
        foreach ($sharedgroups as $groupshare) {
            $members = $DB->get_records('cohort_members', array('cohortid' => $groupshare->groupid));
            foreach ($members as $member) {
                if ($member->userid == $USER->id) {
                    continue;
                }
                if (isset($notifieduserids[$member->userid])) {
                    continue; // Already notified via individual share.
                }
                exaport_send_notification($member->userid, 'categoryupdated', $subject, $url);
                $notifieduserids[$member->userid] = true;
            }
        }
    }
    function exaport_send_notifications($dbview, $courseid, $update = true) {
        // Notify shared users.
        global $USER, $DB, $CFG;
         //if courseid is null, get it from $COURSE
         if ($courseid == null) {
             global $COURSE;
             $courseid = $COURSE->id;
         }
         // Prepare placeholders for subject strings requiring {$a->sendername} and {$a->title}.
         $a = (object)[
             'sendername' => fullname($USER),
             'title' => $dbview->name,
         ];
         $subject = $update ? get_string('i_updated', 'block_exaport', $a) : get_string('i_shared', 'block_exaport', $a);
         $name = $update ? 'viewupdated' : 'sharing';

        $sharedusers = exaport_get_view_shared_users($dbview->id);
        foreach ($sharedusers as $userid => $shareinfo) {
            if (!empty($shareinfo->notify)) {
                exaport_notify_single_user($dbview->id, $userid, $name, $subject, $courseid);
            }
        }

        // Notify users in shared groups.
        $sharedgroups = exaport_get_view_shared_groups($dbview->id);
        if (!empty($sharedgroups)) {
            $viewurl = $CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $courseid . '&access=id/' . $USER->id . '-' . $dbview->id;
            // Get all cohort members for each shared group
            foreach ($sharedgroups as $groupid) {
                $cohortmembers = $DB->get_records('cohort_members', array('cohortid' => $groupid));
                foreach ($cohortmembers as $member) {
                    // Skip if user is the owner of the view
                    if ($member->userid == $USER->id) {
                        continue;
                    }

                    // Check if this user already got a notification (might be directly shared too)
                    if (isset($sharedusers[$member->userid]) && !empty($sharedusers[$member->userid]->notify)) {
                        continue; // Already notified
                    }

                    exaport_send_notification($member->userid, $name, $subject, $viewurl);
                }
            }
        }

        // Notify shared emails.
        // TODO: test if this works... does it do what we want?
        $sharedemails = exaport_get_view_shared_emails($dbview->id);
        if ($sharedemails && count($sharedemails) > 0) {
            $oldemails = []; // No previous state, just send to all.
            $newemails = array_values($sharedemails);
            $hashesforemails = $DB->get_records_menu('block_exaportviewemailshar', array('viewid' => $dbview->id), '', 'email, hash');
            block_exaport_emailaccess_sendemails($dbview, $oldemails, $newemails, $hashesforemails, true);
        }
    }

    function exaport_get_view_shared_users($viewid) {
        global $DB;

        // $sharedusers = $DB->get_records_menu('block_exaportviewshar', array("viewid" => $viewid), null, 'userid, userid AS tmp');
        $sharedusers = $DB->get_records('block_exaportviewshar', array('viewid' => $viewid), null, 'userid, notify'); // the first field "userid" will be used as key, which is important!
        // TODO: check if for all usages it works still

        return $sharedusers;
    }

    function exaport_get_view_shared_groups($viewid) {
        global $DB;

        $sharedgroups = $DB->get_records_menu('block_exaportviewgroupshar',
            array("viewid" => $viewid), null, 'groupid, groupid AS tmp');

        return $sharedgroups;
    }

    function exaport_get_view_shared_emails($viewid) {
        global $DB;

        $sharedemails = $DB->get_records_menu('block_exaportviewemailshar',
            array("viewid" => $viewid), null, 'email, email AS tmp');

        return $sharedemails;
    }

    function exaport_get_category_shared_users($catid) {
        global $DB;

        $sharedusers = $DB->get_records_menu('block_exaportcatshar', array("catid" => $catid), null, 'userid, userid AS tmp');

        return $sharedusers;
    }

    function exaport_get_category_shared_groups($catid) {
        global $DB;

        $sharedgroups = $DB->get_records_menu('block_exaportcatgroupshar',
            array("catid" => $catid), null, 'groupid, groupid AS tmp');

        return $sharedgroups;
    }

    function exaport_get_picture_fields() {
        global $CFG;
        $moodle_version = $CFG->version;
        if (class_exists('\core_user\fields')) {
            // since user_picture::fields() uses a deprecated moodle function, this is the workaround:
            $fields = \core_user\fields::get_picture_fields();
        } else {
            $fields = user_picture::fields();
        }
        if (!is_array($fields) && is_string($fields)) {
            $fields = explode(',', $fields);
        }
        return $fields;
    }

    /**
     * Configuration registry for the "share this entity with users/groups" UI.
     *
     * Collections (views), categories and items are shared in exactly the same way, only the
     * database tables and id column names differ. Keeping that knowledge in one place allows
     * category.php, views_mod.php and item.php to use one single implementation of the
     * userlist/grouplist AJAX endpoints instead of three slightly diverging copies.
     *
     * 'notfoundstring' is the language string a caller should use when it wants to report a
     * missing/foreign entity itself - the AJAX endpoints below deliberately don't use it,
     * see block_exaport_sharing_owned_entity_id().
     *
     * 'internaccessfield' is the column that enables internal sharing for this entity type
     * (null if the entity type has no such flag), 'editpage'/'editparams' point back to the
     * page where the entity's sharing settings are edited and 'headeritem'/'headersubitem'
     * select the navigation entry of that page. They are used by
     * block_exaport_sharing_user_search_page().
     *
     * @param string $entitytype One of 'view', 'category', 'item'.
     * @return object Configuration with the table/column names used for this entity type.
     */
    function block_exaport_get_sharing_entity_config(string $entitytype) {
        $configs = [
            'view' => (object)[
                'entitytable' => 'block_exaportview',
                'idfield' => 'viewid',
                'usersharetable' => 'block_exaportviewshar',
                'groupsharetable' => 'block_exaportviewgroupshar',
                'coursestype' => 'sharing',
                'notfoundstring' => 'viewnotfound',
                'internaccessfield' => 'internaccess',
                'editpage' => '/blocks/exaport/views_mod.php',
                'editparams' => ['type' => 'share', 'action' => 'edit'],
                'headeritem' => 'views',
                'headersubitem' => 'share',
                // Collections may be shared to users the owner is no longer enrolled with,
                // so those users are listed in an extra pseudo course to keep them removable.
                // Categories and items historically never did this and their share forms only
                // process users coming from the owner's own courses, so enabling it there would
                // change (not just deduplicate) their behaviour - therefore it stays view-only.
                'extrausers' => true,
            ],
            'category' => (object)[
                'entitytable' => 'block_exaportcate',
                'idfield' => 'catid',
                'usersharetable' => 'block_exaportcatshar',
                'groupsharetable' => 'block_exaportcatgroupshar',
                'coursestype' => '',
                'notfoundstring' => 'category_not_found',
                'internaccessfield' => 'internshare',
                'editpage' => '/blocks/exaport/category.php',
                'editparams' => ['action' => 'edit'],
                'headeritem' => 'myportfolio',
                'headersubitem' => null,
                'extrausers' => false,
            ],
            'item' => (object)[
                'entitytable' => 'block_exaportitem',
                'idfield' => 'itemid',
                'usersharetable' => 'block_exaportitemshar',
                'groupsharetable' => 'block_exaportitemgroupshar',
                'coursestype' => '',
                'notfoundstring' => 'bookmarknotfound',
                // Items have no separate "internal access" flag, sharing is derived from the
                // existing share rows plus shareall - therefore there is nothing to flip here.
                'internaccessfield' => null,
                'editpage' => '/blocks/exaport/item.php',
                'editparams' => ['action' => 'edit'],
                'headeritem' => 'myportfolio',
                'headersubitem' => null,
                'extrausers' => false,
            ],
        ];

        if (!isset($configs[$entitytype])) {
            throw new coding_exception('Unknown exaport sharing entity type: ' . $entitytype);
        }

        return $configs[$entitytype];
    }

    /**
     * Ownership check for the sharing AJAX endpoints.
     *
     * Returns the entity id if the current user owns the entity, 0 otherwise. Callers then build
     * the list without any share state, which is the safest possible answer: a user who does not
     * own an entity must never learn to whom it is shared. Returning 0 instead of throwing also
     * keeps the "new, not yet saved entity" case (id = 0) working, which the sharing dialogs rely
     * on to render an empty selection list.
     *
     * @param object $config Entity configuration, see block_exaport_get_sharing_entity_config().
     * @param int $entityid
     * @return int The entity id, or 0 if the current user is not the owner.
     */
    function block_exaport_sharing_owned_entity_id($config, int $entityid): int {
        global $DB, $USER;

        if ($entityid <= 0) {
            return 0;
        }
        if (!$DB->record_exists($config->entitytable, ['id' => $entityid, 'userid' => $USER->id])) {
            // Not the user's entity: don't expose any sharing info.
            return 0;
        }

        return $entityid;
    }

    /**
     * AJAX endpoint: list all courses the current user may share an entity with.
     *
     * Shared by category.php, views_mod.php and item.php so that the sharing dialog logic exists
     * only once. Outputs the JSON structure expected by javascript/exaport.js and exits.
     *
     * This deliberately does NOT resolve any course's users (that used to happen eagerly for
     * every enrolled course via exaport_get_shareable_courses_with_users(), which calls
     * get_roles_used_in_context()/get_role_users() once per role per course - an N x M set of
     * calls that made opening this dialog slow on installations with many courses). Instead the
     * frontend fetches a single course's users lazily via
     * block_exaport_ajax_sharing_userlist_course(), either on first expand or eagerly for
     * courses flagged has_shared_users below, so the "already shared courses auto-expand"
     * behaviour keeps working without eagerly loading everything.
     *
     * require_sesskey() is enforced for all entity types. category.php used to be missing this
     * check; enforcing it here is a deliberate security hardening.
     *
     * @param string $entitytype One of 'view', 'category', 'item'.
     * @param int $entityid Id of the entity being edited, 0 for a not yet saved one.
     */
    function block_exaport_ajax_sharing_userlist(string $entitytype, int $entityid) {
        global $DB;

        require_sesskey();

        $config = block_exaport_get_sharing_entity_config($entitytype);
        $entityid = block_exaport_sharing_owned_entity_id($config, $entityid);

        $sharedusers = [];
        if ($entityid > 0) {
            // The first field is used as the array key, which is important here.
            $sharedusers = $DB->get_records($config->usersharetable, [$config->idfield => $entityid],
                null, 'userid, notify');
        }

        $courses = exaport_get_shareable_courses_list();

        // Work out which of the already-shared users are enrolled in which of the listed
        // courses. This only ever queries the (usually tiny) set of already-shared user ids, so
        // it stays cheap regardless of how many courses/users exist, unlike resolving every
        // course's users up front.
        $sharedusercourseids = $sharedusers
            ? exaport_get_courseids_for_shared_users(array_keys($sharedusers), array_keys($courses))
            : [];
        foreach ($sharedusercourseids as $userid => $userscourseids) {
            foreach ($userscourseids as $courseid) {
                $courses[$courseid]->has_shared_users = true;
            }
        }

        // Users the entity is shared to but who are not enrolled in any of the owner's courses.
        if ($config->extrausers && $sharedusers) {
            $extrauserids = array_diff(array_keys($sharedusers), array_keys($sharedusercourseids));
            $extrausers = [];
            if ($extrauserids) {
                // Since user_picture::fields() uses a deprecated moodle function, this is the workaround.
                $fields = implode(',', exaport_get_picture_fields());

                foreach ($extrauserids as $userid) {
                    $user = $DB->get_record('user', ['id' => $userid], $fields);
                    if (!$user) {
                        // Doesn't exist anymore.
                        continue;
                    }

                    $extrausers[] = (object)[
                        'id' => $user->id,
                        'name' => fullname($user),
                        'rolename' => '',
                        'shared_to' => true,
                    ];
                }
            }

            if ($extrausers) {
                // Users are embedded directly (unlike the other, real courses) since there is no
                // course to lazily fetch them from - the frontend renders this pseudo-course
                // straight away and marks it open, same as before.
                $courses = ['-1' => (object)[
                    'id' => -1,
                    'fullname' => get_string('other_users_course', 'block_exaport'),
                    'users' => $extrausers,
                ]] + $courses;
            }
        }

        echo json_encode(array_values($courses));
        exit;
    }

    /**
     * AJAX endpoint: list the shareable users of exactly one course.
     *
     * Split out of block_exaport_ajax_sharing_userlist() so the "share to users" dialog can
     * lazily fetch a single course's users only when needed (on first expand of that course, or
     * eagerly for courses flagged has_shared_users by block_exaport_ajax_sharing_userlist()),
     * instead of resolving every enrolled course's users up front. See that function's docblock
     * for why this split exists.
     *
     * Enforces the exact same ownership/sesskey checks as block_exaport_ajax_sharing_userlist(),
     * via the same helpers, plus an explicit enrol_get_my_courses() membership check for
     * $courseid so this endpoint can't be used to peek at users of a course the caller has
     * nothing to do with.
     *
     * The entry points dispatch to this for action=userlistcourse. The action value must not
     * contain an underscore, because the entry points read it with PARAM_ALPHA, which strips
     * everything that is not a plain letter.
     *
     * @param string $entitytype One of 'view', 'category', 'item'.
     * @param int $entityid Id of the entity being edited, 0 for a not yet saved one.
     * @param int $courseid Id of the course to list shareable users for.
     */
    function block_exaport_ajax_sharing_userlist_course(string $entitytype, int $entityid, int $courseid) {
        global $DB;

        require_sesskey();

        $config = block_exaport_get_sharing_entity_config($entitytype);
        $entityid = block_exaport_sharing_owned_entity_id($config, $entityid);

        // Only ever resolve users of courses the current user is actually enrolled in - the same
        // restriction the eager, all-courses code path used to get "for free" by only ever
        // looping over enrol_get_my_courses().
        $mycourses = enrol_get_my_courses();
        if (!isset($mycourses[$courseid])) {
            echo json_encode([]);
            exit;
        }

        $users = exaport_get_course_shareable_users($courseid);

        $sharedusers = [];
        if ($entityid > 0) {
            $sharedusers = $DB->get_records($config->usersharetable, [$config->idfield => $entityid],
                null, 'userid, notify');
        }

        foreach ($users as $user) {
            if (isset($sharedusers[$user->id])) {
                $user->shared_to = true;
                $user->notify_user = (bool)$sharedusers[$user->id]->notify;
            } else {
                $user->shared_to = false;
                $user->notify_user = false;
            }
        }

        echo json_encode(array_values($users));
        exit;
    }

    /**
     * AJAX endpoint: list all cohort groups the current user may share an entity with.
     *
     * Shared by category.php, views_mod.php and item.php, see
     * block_exaport_ajax_sharing_userlist() for the reasoning. Outputs the JSON structure
     * expected by javascript/exaport.js and exits.
     *
     * If the current user does not own the entity, the groups are returned without any share
     * state (shared_to = false) instead of throwing an exception: this is the same behaviour as
     * the userlist endpoint and it never leaks sharing information of somebody else's entity.
     *
     * @param string $entitytype One of 'view', 'category', 'item'.
     * @param int $entityid Id of the entity being edited, 0 for a not yet saved one.
     */
    function block_exaport_ajax_sharing_grouplist(string $entitytype, int $entityid) {
        global $DB;

        require_sesskey();

        $config = block_exaport_get_sharing_entity_config($entitytype);
        $entityid = block_exaport_sharing_owned_entity_id($config, $entityid);

        $sharedgroups = [];
        if ($entityid > 0) {
            $sharedgroups = $DB->get_records_menu($config->groupsharetable, [$config->idfield => $entityid],
                null, 'groupid, groupid AS tmp');
        }

        $groupgroups = block_exaport_get_shareable_groups_for_json();
        foreach ($groupgroups as $groupgroup) {
            foreach ($groupgroup->groups as $group) {
                $group->shared_to = isset($sharedgroups[$group->id]);
            }
        }

        echo json_encode($groupgroups);
        exit;
    }

    /**
     * Toggle direct user shares of an entity, used by the "search any user" sharing page.
     *
     * Extracted so that the search page exists only once for collections, categories and items
     * instead of triplicating the same add/delete logic. The caller is responsible for the
     * ownership check (see block_exaport_sharing_owned_entity_id()) and for require_sesskey().
     *
     * @param object $config Entity configuration, see block_exaport_get_sharing_entity_config().
     * @param int $entityid Id of the (owned) entity.
     * @param array $shareusers userid => truthy value if the entity should be shared to that user.
     */
    function block_exaport_sharing_toggle_shared_users($config, int $entityid, array $shareusers) {
        global $DB;

        $sharedusers = $DB->get_records_menu($config->usersharetable, [$config->idfield => $entityid],
            null, 'userid, userid AS tmp');

        foreach ($shareusers as $userid => $share) {
            $userid = (int)$userid;
            if ($share && !isset($sharedusers[$userid])) {
                // Add, but only for users that really exist.
                if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
                    continue;
                }
                $DB->insert_record($config->usersharetable, (object)[
                    $config->idfield => $entityid,
                    'userid' => $userid,
                ]);
            } else if (!$share && isset($sharedusers[$userid])) {
                // Delete.
                $DB->delete_records($config->usersharetable, [$config->idfield => $entityid, 'userid' => $userid]);
            }
        }

        // Sharing to single users only makes sense if internal access is on and "share to all" is off,
        // so the flags are updated exactly like the collection only version of this page always did.
        $update = ['id' => $entityid, 'shareall' => 0];
        if ($config->internaccessfield) {
            $update[$config->internaccessfield] = 1;
        }
        $DB->update_record($config->entitytable, (object)$update);
    }

    /**
     * Page: search any moodle user and toggle direct sharing of one entity with them.
     *
     * This is the generalized version of the former views_mod_share_user_search.php. Collections,
     * categories and items all share with single users in exactly the same way, so the search,
     * the result list and the toggling live here once and the per entity type differences flow
     * through block_exaport_get_sharing_entity_config().
     *
     * Only the owner of the entity may see or change anything, the search results and the share
     * state are never rendered for anybody else.
     *
     * @param string $entitytype One of 'view', 'category', 'item'.
     */
    function block_exaport_sharing_user_search_page(string $entitytype) {
        global $DB, $OUTPUT, $PAGE, $CFG;

        $config = block_exaport_get_sharing_entity_config($entitytype);

        $courseid = required_param('courseid', PARAM_INT);
        $id = required_param('id', PARAM_INT);
        $q = trim(optional_param('q', '', PARAM_TEXT));

        block_exaport_require_login($courseid);

        $context = context_system::instance();
        $PAGE->set_url('/blocks/exaport/share_user_search.php',
            ['courseid' => $courseid, 'entitytype' => $entitytype, 'id' => $id]);

        if (!$course = $DB->get_record('course', ['id' => $courseid])) {
            throw new \block_exaport\moodle_exception('invalidcourseid');
        }

        // Ownership check, identical to the one used by the sharing AJAX endpoints.
        if (!block_exaport_sharing_owned_entity_id($config, $id)) {
            throw new \block_exaport\moodle_exception($config->notfoundstring);
        }

        $shareusers = optional_param_array('shareusers', null, PARAM_INT);
        if ($shareusers) {
            require_sesskey();
            block_exaport_sharing_toggle_shared_users($config, $id, $shareusers);
        }

        $backurl = new moodle_url($config->editpage,
            ['courseid' => $courseid, 'id' => $id, 'sesskey' => sesskey()] + $config->editparams);
        $searchurl = new moodle_url('/blocks/exaport/share_user_search.php',
            ['courseid' => $courseid, 'entitytype' => $entitytype, 'id' => $id, 'q' => $q, 'sesskey' => sesskey()]);
        $backlink = '<a href="' . $backurl->out() . '">' . get_string('back') . '</a>';

        block_exaport_print_header($config->headeritem, $config->headersubitem);

        echo $backlink . '<br /><br />';

        echo '<form method="get" action="' . $CFG->wwwroot . '/blocks/exaport/share_user_search.php">';
        echo '<input type="hidden" name="courseid" value="' . s($courseid) . '" />';
        echo '<input type="hidden" name="entitytype" value="' . s($entitytype) . '" />';
        echo '<input type="hidden" name="id" value="' . s($id) . '" />';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '<input name="q" type="text" value="' . s($q) . '" />';
        echo '<input value="' . get_string('search') . '" type="submit" />';
        echo '</form>';

        if ($q) {
            $users = get_users_listing('firstname', 'ASC', 0, 10, $q, '', '', '', array(), $context);

            if ($users) {
                $sharedusers = $DB->get_records_menu($config->usersharetable, [$config->idfield => $id],
                    null, 'userid, userid AS tmp');

                echo '<form method="post" action="' . $searchurl->out() . '" style="padding-top: 10px;">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
                echo '<table width="70%">';
                echo '<tr><th align="center">' . get_string('strshare', 'block_exaport') . '</th>';
                echo '<th align="left">' . get_string('name') . '</th></tr>';

                foreach ($users as $user) {
                    $sharedto = isset($sharedusers[$user->id]);

                    echo '<tr><td align="center" width="50">';
                    echo '<input class="shareusers" type="hidden" name="shareusers[' . $user->id . ']" value="" />';
                    echo '<input class="shareusers" type="checkbox" name="shareusers[' . $user->id . ']" value="' . $user->id . '"' .
                        ($sharedto ? ' checked="checked"' : '') . ' />';
                    echo '</td><td align="center">' . s(fullname($user)) . '</td></tr>';
                }
                echo '</table>';
                echo $backlink . '&nbsp;&nbsp;&nbsp;';
                echo '<input value="' . get_string('savechanges') . '" type="submit" />';
                echo '</form>';
            } else {
                echo get_string('nousersfound');
            }
        }

        echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer($course);
    }

    /**
     * Resolves the shareable users (with roles) of exactly one course.
     *
     * Extracted out of exaport_get_shareable_courses_with_users() so this - the expensive part,
     * one get_role_users() call per role used in the course - can be invoked for a single course
     * only, by block_exaport_ajax_sharing_userlist_course(), instead of always for every enrolled
     * course as exaport_get_shareable_courses_with_users() still does for its own callers.
     *
     * @param int $courseid
     * @return object[] Keyed by user id: {id, name, rolename}.
     */
    function exaport_get_course_shareable_users(int $courseid): array {
        global $USER;

        $users = array();

        $context = context_course::instance($courseid);
        $roles = get_roles_used_in_context($context);

        foreach ($roles as $role) {
            // since user_picture::fields('u') uses a deprecated moodle function, this is the workaround:
            $fields = exaport_get_picture_fields();
            foreach ($fields as $key => $field) {
                $fields[$key] = 'u.' . $field;
            }
            $fields = implode(',', $fields);
            $roleusers = get_role_users($role->id, $context, false, $fields, null, true, '', '', '',
                ' deleted=0 AND suspended=0');

            if (!$roleusers) {
                continue;
            }

            foreach ($roleusers as $user) {
                if ($user->id == $USER->id) {
                    continue;
                }

                $users[$user->id] = (object)array(
                    'id' => $user->id,
                    'name' => fullname($user),
                    'rolename' => $role->name ? $role->name : $role->shortname,
                );
            }
        }

        return $users;
    }

    /**
     * Cheap list of courses a "share to users" dialog may pick from, without resolving any
     * course's users - see block_exaport_ajax_sharing_userlist() for why this split exists.
     *
     * @return object[] Keyed by course id: {id, fullname, has_shared_users}. has_shared_users
     *                   always starts out false here, it is filled in by the caller.
     */
    function exaport_get_shareable_courses_list(): array {
        global $COURSE;

        $courses = array();
        foreach (enrol_get_my_courses(null, 'fullname ASC') as $dbcourse) {
            $courses[$dbcourse->id] = (object)array(
                'id' => $dbcourse->id,
                'fullname' => $dbcourse->fullname,
                'has_shared_users' => false,
            );
        }

        // Move active course to first position, same as exaport_get_shareable_courses_with_users().
        if (isset($courses[$COURSE->id])) {
            $course = $courses[$COURSE->id];
            unset($courses[$COURSE->id]);
            $courses = array($course->id => $course) + $courses;
        }

        return $courses;
    }

    /**
     * Cheap lookup of which of the given courses each of the given users is enrolled in.
     *
     * Used to work out which courses already have shares (so they can be auto-expanded/eagerly
     * fetched) and which shared users are not enrolled in any of the owner's courses ("extra
     * users"), without ever calling get_roles_used_in_context()/get_role_users(): the query below
     * only ever touches the (normally tiny) set of already-shared user ids, so it stays cheap
     * however many courses the owner is enrolled in.
     *
     * @param int[] $userids
     * @param int[] $courseids
     * @return array userid => int[] of courseids (a subset of $courseids) the user is enrolled in.
     */
    function exaport_get_courseids_for_shared_users(array $userids, array $courseids): array {
        global $DB;

        $result = array();
        if (!$userids || !$courseids) {
            return $result;
        }

        list($useridsql, $uparams) = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'shu');
        list($courseidsql, $cparams) = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'shc');
        $sql = "SELECT DISTINCT ue.userid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid $useridsql AND e.courseid $courseidsql";
        $rows = $DB->get_recordset_sql($sql, $uparams + $cparams);
        foreach ($rows as $row) {
            $result[$row->userid][] = $row->courseid;
        }
        $rows->close();

        return $result;
    }

    function exaport_get_shareable_courses_with_users($type) {
        global $COURSE;
        $courses = array();

        // Loop through all my courses.
        foreach (enrol_get_my_courses(null, 'fullname ASC') as $dbcourse) {
            $courses[$dbcourse->id] = (object)array(
                'id' => $dbcourse->id,
                'fullname' => $dbcourse->fullname,
                'users' => exaport_get_course_shareable_users($dbcourse->id),
            );
        }
        // Move active course to first position.
        if (isset($courses[$COURSE->id]) && ($type != 'shared_views')) {
            $course = $courses[$COURSE->id];
            unset($courses[$COURSE->id]);
            // $courses = array_merge(array($course->id => $course), $courses);
            $courses = array($course->id => $course) + $courses;
        }

        // Test courses.
        /*
        $courses[] = array(
            'id' => 1004,
            'fullname' => 'test 4',
            'users' => array(
                array(
                    'id' => 100001,
                    'name' => 'non existing 100001',
                    'rolename' => ''
                ),
                array(
                    'id' => 100002,
                    'name' => 'non existing 100002',
                    'rolename' => ''
                ),
                array(
                    'id' => 100003,
                    'name' => 'non existing 100003',
                    'rolename' => ''
                ),
            )
        );
        $courses[] = array(
            'id' => 1005,
            'fullname' => 'test 5',
            'users' => array(
                array(
                    'id' => 100001,
                    'name' => 'non existing 100001',
                    'rolename' => ''
                ),
            )
        );
        $courses[] = array(
            'id' => 1006,
            'fullname' => 'test 6',
            'users' => array(
                array(
                    'id' => 100005,
                    'name' => 'non existing 100005',
                    'rolename' => ''
                ),
                array(
                    'id' => 100001,
                    'name' => 'non existing 100001',
                    'rolename' => ''
                ),
                array(
                    'id' => 100006,
                    'name' => 'non existing 100006',
                    'rolename' => ''
                ),
            )
        );
        */

        return $courses;
    }

    function block_exaport_get_shareable_groups_for_json() {
        $cohorts = block_exaport_get_user_cohorts();
        if (!$cohorts) {
            return [];
        }

        foreach ($cohorts as $cohort) {
            $cohort->member_cnt = g::$DB->count_records("cohort_members", array("cohortid" => $cohort->id));
        }

        return [
            // Global groups.
            (object)[
                'name' => get_string('cohorts', 'cohort'),
                'groups' => $cohorts,
            ],
        ];
    }

    function block_exaport_get_user_cohorts($userid = null) {
        if ($userid === null) {
            $userid = g::$USER->id;
        }

        return g::$DB->get_records_sql("
            SELECT c.id, c.name, c.description
            FROM {cohort} c
            JOIN {cohort_members} cm ON cm.cohortid=c.id
            WHERE cm.userid=?
            ORDER BY c.name
        ", [$userid]);
    }

    function block_exaport_get_items_shared_to_user($userid, $onlyitems = false, $itemid = null) {
        global $DB;

        // Items shared directly (block_exaportitemshar) or via a group/cohort share
        // (block_exaportitemgroupshar), independent of category sharing.
        $directshareditemids = block_exaport_get_directly_shared_item_ids($userid, $itemid);

        // Categories for user groups.
        $usercats = block_exaport_get_group_share_categories($userid);
        // All categories and users who shared.
        $categorycolumns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
        $itemwhere = '';
        /* if ($itemid) {
            if (is_array($itemid) && count($itemid) > 0) {
                $itemwhere = ' AND i.id IN ('.implode(',', $itemid).') ';
            } elseif ($itemid > 0) {
                $itemwhere = ' AND i.id = '.intval($itemid).' ';
            }
        }*/
        $categories = $DB->get_records_sql(
            "SELECT $categorycolumns, u.firstname, u.lastname, u.picture, " .
            " COUNT(DISTINCT cshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups  " .
            " FROM {user} u " .
            " JOIN {block_exaportcate} c ON u.id = c.userid " .
            " LEFT JOIN {block_exaportcatshar} cshar ON c.id = cshar.catid AND cshar.userid = ?" .

            " LEFT JOIN {block_exaportcatgroupshar} cgshar ON c.id = cgshar.catid " .
            " LEFT JOIN {block_exaportcatshar} cshar_total ON c.id = cshar_total.catid " .
            " WHERE (" .
            "(" . (block_exaport_shareall_enabled() ? 'c.shareall = 1 OR ' : '') . " cshar.userid IS NOT NULL) " .
            // Only show shared all, if enabled.
            // Shared for you group.
            (count($usercats) > 0 ? " OR c.id IN (" . implode(',', array_keys($usercats)) . ") " : "") . // Add group shareing categories.
            ")" .
            " AND c.userid != ? " . // Don't show my own categories.
            " AND internshare = 1 " .
            " AND u.deleted = 0 " .
            $itemwhere .
            " GROUP BY $categorycolumns, u.firstname, u.lastname, u.picture" .
            " ORDER BY u.lastname, u.firstname, c.name", array($userid, $userid));
        // return array();
        // Get users for grouping later.
        $sharedusers = array();
        $sharedcategories = array();
        foreach ($categories as $key => $categorie) {
            if (!in_array($categorie->userid, $sharedusers)) {
                $sharedusers[] = $categorie->userid;
            }
            if (!in_array($categorie->id, $sharedcategories)) {
                $sharedcategories[] = $categorie->id;
            }
        }

        // Get sub categories (recursively).
        $get_subcats = function($parent_id) use (&$get_subcats, &$sharedcategories, $DB) {
            $subcategories = $DB->get_records_menu('block_exaportcate', ['pid' => $parent_id], null, 'id, id as tmp');
            foreach ($subcategories as $categoryid) {
                if (!in_array($categoryid, $sharedcategories)) {
                    $sharedcategories[] = $categoryid;
                }
                $get_subcats($categoryid);
            }
        };
        for ($i = 0, $c = count($sharedcategories); $i < $c; $i++) {
            $get_subcats($sharedcategories[$i]);
            /*$subcategories = $DB->get_records_menu('block_exaportcate', ['pid' => $sharedcategories[$i]], null, 'id, id as tmp');
            foreach ($subcategories as $categoryid) {
                if (!in_array($categoryid, $sharedcategories)) {
                    $sharedcategories[] = $categoryid;
                }
            }*/
        }
        // filter categories by needed itemid
        if ($itemid) {
            if (!is_array($itemid)) {
                $items = array($itemid);
            } else {
                $items = $itemid;
            }
            $cat_from_items = $DB->get_records_sql_menu(' SELECT DISTINCT ic.cateid, ic.cateid as tmp FROM {block_exaportitemcate} ic JOIN {block_exaportitem} i ON i.id = ic.itemid WHERE i.id IN (' . implode(',', $items) . ') ');
            $sharedcategories = array_intersect($sharedcategories, $cat_from_items);
        }

        // Get items for every user.
        $sharedcategorieslist = implode(',', $sharedcategories);
        if (count($sharedcategories) > 100) {
            $sharedcategorieslistchunked = array_chunk($sharedcategories, 100);
        } else {
            $sharedcategorieslistchunked = $sharedcategorieslist;
        }

        if ($onlyitems) {
            $shareditems = [];
            // Only items for customise blocks. for views_mod.php. Or for check is shared.
            $selectfunc = function($userid, $catlist) {
                global $DB;
                if (!$catlist) {
                    return array();
                }
                $query = "SELECT DISTINCT i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, " .
                    " ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments" .
                    " FROM {block_exaportitem} i" .
                    " LEFT JOIN {block_exaportitemcate} icat ON icat.itemid = i.id" .
                    " LEFT JOIN {block_exaportcate} ic on icat.cateid = ic.id" .
                    " LEFT JOIN {block_exaportcate} ic2 on ic.pid = ic2.id" .
                    " LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id" .
                    " WHERE i.userid=? AND icat.cateid IN (" . $catlist . ")" .
                    " GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid" .
                    " ORDER BY i.name";
                $useritems = $DB->get_records_sql($query, array($userid));
                return $useritems;
            };
            foreach ($sharedusers as $key => $userid) {
                if (count($sharedcategories) <= 100) {
                    $useritems = $selectfunc($userid, $sharedcategorieslist);
                    $shareditems = $shareditems + $useritems;
                } else {
                    // divide to many queries: TODO: is it helping?
                    foreach ($sharedcategorieslistchunked as $sharedcats) {
                        $sharedcategorieslist = implode(',', $sharedcats);
                        $useritems = $selectfunc($userid, $sharedcategorieslist);
                        $shareditems = $shareditems + $useritems;
                    }
                }

            }

            // Add directly (and group) shared items on top of the category-based ones.
            if ($directshareditemids) {
                $directitems = block_exaport_get_item_share_details($directshareditemids);
                $shareditems = $shareditems + $directitems;
            }

            return $shareditems;
        } else {
            $sharedartefactsbyuser = array();
            foreach ($sharedusers as $key => $userid) {
                $sharedartefactsbyuser[$key]['userid'] = $userid;
                $sharedartefactsbyuser[$key]['fullname'] = fullname($DB->get_record('user', array('id' => $userid)));
                $items = $DB->get_records_sql('SELECT i.* FROM {block_exaportitem} i ' .
                    ' JOIN {block_exaportitemcate} ic ON ic.itemid = i.id' .
                    ' WHERE i.userid=? AND ic.cateid IN (' . $sharedcategorieslist . ')',
                    array('userid' => $userid));
                $sharedartefactsbyuser[$key]['items'] = $items;
                // Delete empty categories.
                if (count($sharedartefactsbyuser[$key]['items']) == 0) {
                    unset($sharedartefactsbyuser[$key]);
                }
            }

            // Add directly (and group) shared items, grouped by their owner.
            if ($directshareditemids) {
                [$insql, $inparams] = $DB->get_in_or_equal($directshareditemids);
                $directitems = $DB->get_records_sql("SELECT i.* FROM {block_exaportitem} i WHERE i.id $insql", $inparams);

                $ownerids = array_unique(array_map(function($item) {
                    return $item->userid;
                }, $directitems));
                $owners = $ownerids ? $DB->get_records_list('user', 'id', $ownerids) : [];

                foreach ($directitems as $directitem) {
                    $owner = $directitem->userid;
                    $existingkey = null;
                    foreach ($sharedartefactsbyuser as $key => $entry) {
                        if ($entry['userid'] == $owner) {
                            $existingkey = $key;
                            break;
                        }
                    }
                    if ($existingkey === null) {
                        $existingkey = $owner . '_direct';
                        $sharedartefactsbyuser[$existingkey] = [
                            'userid' => $owner,
                            'fullname' => isset($owners[$owner]) ? fullname($owners[$owner]) : '',
                            'items' => [],
                        ];
                    }
                    $sharedartefactsbyuser[$existingkey]['items'][$directitem->id] = $directitem;
                }
            }

            return $sharedartefactsbyuser;
        }
    }

    /**
     * Returns the ids of items directly shared to a user (block_exaportitemshar),
     * or via one of the user's group/cohort shares (block_exaportitemgroupshar).
     * Honors optional time-limited sharing (timestart/timeend) on individual shares.
     *
     * @param int $userid
     * @param int|array|null $itemid optional item id (or array of ids) to restrict the check to
     * @return array list of item ids
     */
    function block_exaport_get_directly_shared_item_ids($userid, $itemid = null) {
        global $DB;

        $itemwhere = '';
        if ($itemid) {
            $items = is_array($itemid) ? $itemid : [$itemid];
            $itemwhere = ' AND i.id IN (' . implode(',', array_map('intval', $items)) . ') ';
        }

        $now = time();
        $itemids = $DB->get_fieldset_sql(
            "SELECT DISTINCT i.id
               FROM {block_exaportitem} i
               JOIN {block_exaportitemshar} ishar ON ishar.itemid = i.id AND ishar.userid = ?
                AND (ishar.timestart IS NULL OR ishar.timestart = 0 OR ishar.timestart <= ?)
                AND (ishar.timeend IS NULL OR ishar.timeend = 0 OR ishar.timeend >= ?)
              WHERE 1=1 $itemwhere",
            [$userid, $now, $now]
        );

        $usergroupids = array_keys(block_exaport_get_user_cohorts($userid));
        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $groupitemids = $DB->get_fieldset_sql(
                "SELECT DISTINCT i.id
                   FROM {block_exaportitem} i
                   JOIN {block_exaportitemgroupshar} igshar ON igshar.itemid = i.id AND igshar.groupid $insql
                  WHERE 1=1 $itemwhere",
                $inparams
            );
            $itemids = array_unique(array_merge($itemids, $groupitemids));
        }

        return $itemids;
    }

    /**
     * Loads item details (in the same shape as the category-shared items query) for a
     * given set of item ids, for use by block_exaport_get_items_shared_to_user().
     *
     * @param array $itemids
     * @return array item records keyed by id
     */
    function block_exaport_get_item_share_details($itemids) {
        global $DB;

        if (!$itemids) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids);

        return $DB->get_records_sql(
            "SELECT DISTINCT i.id, i.name, i.type, i.intro AS intro, i.url AS link, " .
            " ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) AS comments " .
            " FROM {block_exaportitem} i " .
            " LEFT JOIN {block_exaportitemcate} icat ON icat.itemid = i.id " .
            " LEFT JOIN {block_exaportcate} ic ON icat.cateid = ic.id " .
            " LEFT JOIN {block_exaportcate} ic2 ON ic.pid = ic2.id " .
            " LEFT JOIN {block_exaportitemcomm} com ON com.itemid = i.id " .
            " WHERE i.id $insql " .
            " GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.name, ic.id, ic2.name, i.userid " .
            " ORDER BY i.name",
            $inparams
        );
    }

    /**
     * checks if user can access shared item
     *
     * @param $userid
     * @param $itemid
     * @return bool
     */
    function block_exaport_can_user_access_shared_item($userid, $itemid) {
        global $DB, $USER;
        // At first - check teacher access.
        if (block_exaport_user_can_see_artifacts_of_students()) {
            // The owner of item is a student of teacher
            $students = block_exaport_get_students_for_teacher($userid);
            $itemdata = $DB->get_record('block_exaportitem', array('id' => $itemid));
            if (array_key_exists($itemdata->userid, $students)) {
                return $itemdata->userid;
            }
        }
        $itemdata = $DB->get_record('block_exaportitem', ['id' => $itemid], 'id, userid, shareall');
        if ($itemdata && (int)$itemdata->shareall === 1 && block_exaport_shareall_enabled()) {
            return $itemdata->userid;
        }
        // Check direct item share (block_exaportitemshar), incl. optional time-limited sharing.
        if ($ownerid = block_exaport_get_direct_item_share_owner($userid, $itemid)) {
            return $ownerid;
        }
        // Check access by self sharing
        $itemsforuser = block_exaport_get_items_shared_to_user($userid, true, $itemid);
        if (array_key_exists($itemid, $itemsforuser)) {
            return $itemsforuser[$itemid]->userid;
        }
        // Check items in self category (other users can put items to my category
        if ($item = $DB->get_record('block_exaportitem', ['id' => $itemid])) {
            // Check if item belongs to a category owned by current user.
            $itemcatids = $DB->get_fieldset_select('block_exaportitemcate', 'cateid', 'itemid = ?', [$itemid]);
            foreach ($itemcatids as $catid) {
                $itemcat = $DB->get_record('block_exaportcate', ['id' => $catid]);
                if ($itemcat && $itemcat->userid == $USER->id) {
                    return $item->userid;
                }
            }
            // if I also have the same shared category - I can see items in this category
            $sharedcatids = [];
            $sharedcategories = \block_exaport\get_categories_shared_to_user($USER->id);
            if ($sharedcategories) {
                foreach ($sharedcategories as $shcat) {
                    $sharedcatids = array_merge($sharedcatids, array_keys($shcat->categories));
                }
            }
            if (array_intersect($itemcatids, $sharedcatids)) {
                return $item->userid;
            }
        }
        return false;
    }

    /**
     * Checks if an item is directly shared to a user, either individually
     * (block_exaportitemshar) or via one of the user's group/cohort shares
     * (block_exaportitemgroupshar). Honors optional time-limited sharing
     * (timestart/timeend) on the individual share record.
     *
     * @param int $userid the user who wants to access the item
     * @param int $itemid the item being accessed
     * @return int|false the item owner's userid if access is granted, false otherwise
     */
    function block_exaport_get_direct_item_share_owner($userid, $itemid) {
        global $DB;

        $now = time();
        $item = $DB->get_record_sql(
            "SELECT i.userid
               FROM {block_exaportitem} i
               JOIN {block_exaportitemshar} ishar ON ishar.itemid = i.id
              WHERE i.id = ?
                AND ishar.userid = ?
                AND (ishar.timestart IS NULL OR ishar.timestart = 0 OR ishar.timestart <= ?)
                AND (ishar.timeend IS NULL OR ishar.timeend = 0 OR ishar.timeend >= ?)",
            [$itemid, $userid, $now, $now]
        );
        if ($item) {
            return $item->userid;
        }

        $usergroupids = array_keys(block_exaport_get_user_cohorts($userid));
        if ($usergroupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($usergroupids);
            $item = $DB->get_record_sql(
                "SELECT i.userid
                   FROM {block_exaportitem} i
                   JOIN {block_exaportitemgroupshar} igshar ON igshar.itemid = i.id
                  WHERE i.id = ?
                    AND igshar.groupid $insql",
                array_merge([$itemid], $inparams)
            );
            if ($item) {
                return $item->userid;
            }
        }

        return false;
    }

    function block_exaport_get_group_share_categories($userid) {
        $usergroups = block_exaport_get_user_cohorts($userid);
        if (!$usergroups) {
            return [];
        }

        return g::$DB->get_records_sql("
            SELECT DISTINCT catid
            FROM {block_exaportcatgroupshar}
            WHERE groupid IN (" . join(',', array_keys($usergroups)) . ")");
    }

    function block_exaport_get_group_share_views($userid) {
        $usergroups = block_exaport_get_user_cohorts($userid);
        if (!$usergroups) {
            return [];
        }

        return g::$DB->get_records_sql("
            SELECT viewid
            FROM {block_exaportviewgroupshar}
            WHERE groupid IN (" . join(',', array_keys($usergroups)) . ")");
    }

    function block_exaport_user_can_see_artifacts_of_students() {
        global $CFG, $USER;
        if ($CFG->block_exaport_teachercanseeartifactsofstudents) {
            // The $USER->profile['blockexaporttrustedteacher'] is not working, because it is session data
            // And it is not updating in real time
            // so, I use the code below with $userclone.
            $userclone = clone($USER);
            require_once($CFG->dirroot . '/user/profile/lib.php');
            require_once($CFG->dirroot . '/user/lib.php');
            profile_load_data($userclone);
            // Only if this user is checked as trusted teacher and only if it is a teacher!
            if (isset($userclone)
                && isset($userclone->profile_field_blockexaporttrustedteacher)
                && $userclone->profile_field_blockexaporttrustedteacher == 1
                && block_exaport_user_is_teacher()) {
                return true;
            }
        }
        return false;
    }
}

namespace block_exaport {

    use block_exaport\globals as g;

    function get_categories_shared_to_user($userid) {
        global $DB, $USER;

        // Categories for user groups.
        $usercats = block_exaport_get_group_share_categories($userid);

        // All categories and users who shared.
        $categories = $DB->get_records_sql(
            ' SELECT c.*
                    FROM {block_exaportcate} c
                      JOIN {user} u ON u.id = c.userid
                      LEFT JOIN {block_exaportcatshar} cshar ON c.id = cshar.catid AND cshar.userid = ?
                      LEFT JOIN {block_exaportcatgroupshar} cgshar ON c.id = cgshar.catid
                    WHERE (
                        (' . (block_exaport_shareall_enabled() ? ' c.shareall = 1 OR ' : '') . ' cshar.userid IS NOT NULL) ' .
            // Only show shared all, if enabled
            // Shared for you group.
            ($usercats ? ' OR c.id IN (' . join(',', array_keys($usercats)) . ') ' : '') . // Add group sharing categories.
            ')
                          AND c.userid != ? ' . // Don't show my own categories.
            ' AND internshare = 1
                          AND u.deleted = 0
                    ORDER BY u.lastname, u.firstname, c.name', array($userid, $USER->id));

        // add subcategories (TODO: check!)
        foreach ($categories as $cuid => $cat) {
            $subcategories = $DB->get_records_menu('block_exaportcate', ['pid' => $cuid], null, 'id, id as tmp');
            foreach ($subcategories as $categoryid) {
                if (!array_key_exists($categoryid, $categories)) {
                    $categories[$categoryid] = $DB->get_record('block_exaportcate', ['id' => $categoryid]);
                }
            }
        }

        $tree = [];
        foreach ($categories as $category) {
            if (!isset($tree[$category->userid])) {
                $user = $tree[$category->userid] = $DB->get_record('user', ['id' => $category->userid]);
                $user->categories = [];
                $user->name = fullname($user);
                $user->url = g::$CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . g::$COURSE->id .
                    '&type=shared&userid=' . $user->id;
            } else {
                $user = $tree[$category->userid];
            }

            $category->url = g::$CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . g::$COURSE->id .
                '&type=shared&userid=' . $user->id . '&categoryid=' . $category->id;
            $category->icon = block_exaport_get_category_icon($category);

            $user->categories[$category->id] = $category;
        }
        return $tree;
    }
}
