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

    function block_exaport_get_external_view_url(stdClass $view, $userid = -1) {
        global $CFG, $USER;
        if ($userid == -1) {
            $userid = $USER->id;
        }
        // Bei epop wird userid mitgegeben, sonst aus global USER holen.
        return $CFG->wwwroot . '/blocks/exaport/shared_view.php?access=hash/' . $userid . '-' . $view->hash;
    }

    function block_exaport_get_user_from_access($access, $epopaccess = false) {
        global  $DB;

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
        if (count($accesspath) != 2) {
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
            $view = $DB->get_record_sql("SELECT DISTINCT v.* FROM {block_exaportview} v" .
                " LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid = ?" .
                $tempjoin .
                " WHERE v.userid = ? AND v.id = ? AND" .
                " ((v.userid = ?)" . // Myself.
                "  OR (v.shareall = 1)" . // Shared all.
                "  OR (v.shareall = 0 AND vshar.userid IS NOT NULL) " .
                ($usergroups ? " OR vgshar.groupid IN (" . join(',', array_keys($usergroups)) . ") " : "") .
                ")", array($myuserid, $userid, $viewid, $myuserid)); // Shared for me.
            if (!$view) {
                // View not found.
                return;
            }

            $view->access = new stdClass();
            $view->access->request = 'intern';
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

    function exaport_get_view_shared_users($viewid) {
        global $DB;

        $sharedusers = $DB->get_records_menu('block_exaportviewshar', array("viewid" => $viewid), null, 'userid, userid AS tmp');

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

    function exaport_get_shareable_courses_with_users_for_view($viewid) {
        global $DB;

        $sharedusers = exaport_get_view_shared_users($viewid);
        $courses = exaport_get_shareable_courses_with_users('sharing');

        foreach ($courses as $course) {
            foreach ($course->users as $user) {
                if (isset($sharedusers[$user->id])) {
                    $user->shared_to = true;
                    unset($sharedusers[$user->id]);
                } else {
                    $user->shared_to = false;
                }
            }
        }

        if ($sharedusers) {
            $extrausers = array();

            foreach ($sharedusers as $userid) {
                // since user_picture::fields() uses a deprecated moodle function, this is the workaround:
                $fields = exaport_get_picture_fields();
                $fields = implode(',', $fields);
                $user = $DB->get_record('user', array('id' => $userid), $fields);
                if (!$user) {
                    // Doesn't exist anymore.
                    continue;
                }

                $extrausers[] = (object)array(
                    'id' => $user->id,
                    'name' => fullname($user),
                    'rolename' => '',
                    'shared_to' => true,
                );
            }

            array_unshift($courses, (object)array(
                'id' => -1,
                'fullname' => get_string('other_users_course', 'block_exaport'),
                'users' => $extrausers,
            ));
        }

        return $courses;
    }

    function exaport_get_shareable_courses_with_users($type) {
        global $USER, $COURSE;
        $courses = array();

        // Loop through all my courses.
        foreach (enrol_get_my_courses(null, 'fullname ASC') as $dbcourse) {

            $course = (object)array(
                'id' => $dbcourse->id,
                'fullname' => $dbcourse->fullname,
                'users' => array(),
            );

            $context = context_course::instance($dbcourse->id);
            $roles = get_roles_used_in_context($context);

            foreach ($roles as $role) {
                // since user_picture::fields('u') uses a deprecated moodle function, this is the workaround:
                $fields = exaport_get_picture_fields();
                foreach ($fields as $key => $field) {
                    $fields[$key] = 'u.' . $field;
                }
                $fields = implode(',', $fields);
                $users = get_role_users($role->id, $context, false, $fields, null, true, '', '', '',
                    ' deleted=0 AND suspended=0');

                if (!$users) {
                    continue;
                }

                foreach ($users as $user) {
                    if ($user->id == $USER->id) {
                        continue;
                    }

                    $course->users[$user->id] = (object)array(
                        'id' => $user->id,
                        'name' => fullname($user),
                        'rolename' => $role->name ? $role->name : $role->shortname,
                    );
                }
            }

            $courses[$course->id] = $course;
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
            // ($itemwhere ? " JOIN {block_exaportitem} i ON c.id = i.categoryid "." " : "").
            " LEFT JOIN {block_exaportcatshar} cshar ON c.id = cshar.catid AND cshar.userid = ?" .

            " LEFT JOIN {block_exaportviewgroupshar} cgshar ON c.id = cgshar.groupid " .
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
            $cat_from_items = $DB->get_records_sql_menu(' SELECT DISTINCT categoryid, categoryid as tmp FROM {block_exaportitem} WHERE id IN (' . implode(',', $items) . ') ');
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
                    " LEFT JOIN {block_exaportcate} ic on i.categoryid = ic.id" .
                    " LEFT JOIN {block_exaportcate} ic2 on ic.pid = ic2.id" .
                    " LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id" .
                    " WHERE i.userid=? AND categoryid IN (" . $catlist . ")" .
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

            return $shareditems;
        } else {
            $sharedartefactsbyuser = array();
            foreach ($sharedusers as $key => $userid) {
                $sharedartefactsbyuser[$key]['userid'] = $userid;
                $sharedartefactsbyuser[$key]['fullname'] = fullname($DB->get_record('user', array('id' => $userid)));
                $items = $DB->get_records_sql('SELECT * FROM {block_exaportitem} ' .
                    ' WHERE userid=? AND categoryid IN (' . $sharedcategorieslist . ')',
                    array('userid' => $userid));
                $sharedartefactsbyuser[$key]['items'] = $items;
                // Delete empty categories.
                if (count($sharedartefactsbyuser[$key]['items']) == 0) {
                    unset($sharedartefactsbyuser[$key]);
                }
            }

            return $sharedartefactsbyuser;
        }
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
        // Check access by self sharing
        $itemsforuser = block_exaport_get_items_shared_to_user($userid, true, $itemid);
        if (array_key_exists($itemid, $itemsforuser)) {
            return $itemsforuser[$itemid]->userid;
        }
        // Check items in self category (other users can put items to my category
        if ($item = $DB->get_record('block_exaportitem', ['id' => $itemid])) {
            if ($item->categoryid > 0) { // not root category
                $itemcat = $DB->get_record('block_exaportcate', ['id' => $item->categoryid]);
                if ($itemcat->userid == $USER->id) {
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
            if (in_array($item->categoryid, $sharedcatids)) {
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
                      LEFT JOIN {block_exaportviewgroupshar} cgshar ON c.id = cgshar.groupid
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

