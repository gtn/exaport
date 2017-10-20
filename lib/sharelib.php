<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

namespace {

	use block_exaport\globals as g;

	function block_exaport_get_external_view_url(stdClass $view, $userid = -1) {
		global $CFG, $USER;
		if ($userid == -1) $userid = $USER->id; //bei epop wird userid mitgegeben, sonst aus global USER holen
		return $CFG->wwwroot.'/blocks/exaport/shared_view.php?access=hash/'.$userid.'-'.$view->hash;
	}

	function block_exaport_get_user_from_access($access, $epopaccess = false) {
		global $CFG, $USER, $DB;

		$accessPath = explode('/', $access);
		if (count($accessPath) != 2)
			return;

		if ($accessPath[0] == 'hash') {
			$hash = $accessPath[1];

			$conditions = array("user_hash" => $hash);
			if (!$portfolioUser = $DB->get_record("block_exaportuser", $conditions)) {
				// no portfolio user with this hash
				return;
			}
			$conditions = array("id" => $portfolioUser->user_id);
			if (!$user = $DB->get_record("user", $conditions)) {
				// user not found
				return;
			}

			// keine rechte �berpr�fung, weil �ber den hash user immer erreichbar ist aber nur die geshareten items angezeigt werden
			// vielleicht in zukunft eine externaccess eingenschaft f�r den user einf�gen?

			$user->access = new stdClass();
			$user->access->request = 'extern';

			return $user;
		} elseif ($accessPath[0] == 'id') {
			// guest not allowed
			// require exaport:use -> guest hasn't this right
			$context = context_system::instance();
			if ($epopaccess == false) require_capability('block/exaport:use', $context);

			$userid = $accessPath[1];

			$conditions = array("user_id" => $userid);
			if (!$portfolioUser = $DB->get_record("block_exaportuser", $conditions)) {
				// no portfolio user with this id
				return;
			}

			$conditions = array("id" => $portfolioUser->user_id);
			if (!$user = $DB->get_record("user", $conditions)) {
				// user not found
				return;
			}

			// no more checks needed

			$user->access = new stdClass();
			$user->access->request = 'intern';

			return $user;
		}
	}


	function block_exaport_get_view_from_access($access) {
		global $USER, $DB;

		if (!block_exaport_feature_enabled('views')) {
			// only allowed if views are enabled
			return;
		}

		$accessPath = explode('/', $access);
		if (count($accessPath) != 2)
			return;

		$view = null;

		if ($accessPath[0] == 'hash') {
			$hash = $accessPath[1];
			$hash = explode('-', $hash);

			if (count($hash) != 2)
				return;

			$userid = clean_param($hash[0], PARAM_INT);
			$hash = clean_param($hash[1], PARAM_ALPHANUM);
			//$userid = $hash[0];
			//$hash = $hash[1];

			if (empty($userid) || empty($hash)) {
				return;
			}
			$conditions = array("userid" => $userid, "hash" => $hash, "externaccess" => 1);
			if (!$view = $DB->get_record("block_exaportview", $conditions)) {
				// view not found
				return;
			}


			$view->access = new stdClass();
			$view->access->request = 'extern';
		} elseif ($accessPath[0] == 'id') {
			// guest not allowed
			// require exaport:use -> guest hasn't this right
			$context = context_system::instance();
			require_capability('block/exaport:use', $context);

			// Groups for user
			$usergroups = block_exaport_get_user_cohorts();

			$hash = $accessPath[1];
			$hash = explode('-', $hash);

			if (count($hash) != 2) {
				return;
			}

			$userid = clean_param($hash[0], PARAM_INT);
			$viewid = clean_param($hash[1], PARAM_INT);

			$view = $DB->get_record_sql("SELECT DISTINCT v.* FROM {block_exaportview} v".
				" LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid=?".
				(((is_array($usergroups)) && (count($usergroups) > 0)) ? "LEFT JOIN {block_exaportviewgroupshar} vgshar ON v.id=vgshar.viewid " : "").

				" WHERE v.userid=? AND v.id=? AND".
				" ((v.userid=?)". // myself
				"  OR (v.shareall=1)". // shared all
				"  OR (v.shareall=0 AND vshar.userid IS NOT NULL) ".
				($usergroups ? " OR vgshar.groupid IN (".join(',', array_keys($usergroups)).") " : "").
				")", array($USER->id, $userid, $viewid, $USER->id)); // shared for me

			if (!$view) {
				// view not found
				return;
			}

			$view->access = new stdClass();
			$view->access->request = 'intern';
		} else if ($accessPath[0] == 'email') {
			$hash = explode('-', $accessPath[1]);
			if (count($hash) != 2) {
				return;
			}

			list($viewHash, $emailHash) = $hash;

			if (!$view = $DB->get_record("block_exaportview", ["hash" => $viewHash])) {
				// view not found
				return;
			};

			if ($view->sharedemails != 1) {
				// view is not shared for any emails
				return;
			};

			// check email-phrase
			if (!$DB->record_exists('block_exaportviewemailshar', ['viewid' => $view->id, 'hash' => $emailHash])) {
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
		//echo $sql;die;
		if (!$item = $DB->get_record_sql($sql, array($id, $user->id))) {
			return false;
		} else {
			return $item;
		}
	}

	function block_exaport_get_elove_item($itemid, $userid, $authenticationinfo) {
		global $DB;
		//check if user is userid or if user is trainer of userid
		if ($userid == $authenticationinfo['user']->id)
			return $DB->get_record('block_exaportitem', array('id' => $itemid, 'userid' => $userid));
		else if ($DB->record_exists(BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS, array('trainerid' => $authenticationinfo['user']->id,
			'studentid' => $userid))
		)
			return $DB->get_record('block_exaportitem', array('id' => $itemid));
		else {
			$sql = "SELECT * FROM {block_exaportview} v 
					JOIN {block_exaportviewblock} vb ON v.id = vb.viewid AND vb.itemid = ?
					JOIN {block_exaportviewshar} vs ON v.id = vs.viewid AND vs.userid = ?";
			if ($DB->record_exists_sql($sql, array($itemid, $authenticationinfo['user']->id)))
				return $DB->get_record('block_exaportitem', array('id' => $itemid));

			return false;
		}
	}

	function block_exaport_epop_checkhash($userhash) {
		global $DB;

		$sql = "SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long=?";
		//echo $sql;die;
		if (!$user = $DB->get_record_sql($sql, array($userhash))) {
			return false;
		} else {
			return $user;
		}
	}

	function block_exaport_get_item($itemid, $access, $epopaccess = false) {
		global $CFG, $USER, $DB;

		$itemid = clean_param($itemid, PARAM_INT);

		$item = null;
		if (preg_match('!^view/(.+)$!', $access, $matches)) {
			// in view mode

			if (!$view = block_exaport_get_view_from_access($matches[1])) {
				throw new \block_exacomp\permission_exception("viewnotfound", "block_exaport");
			}
			//Parameter richtig?!
			//$conditions = array("viewid" => $view->id, "type" => 'item', "itemid" => $itemid);
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

			$viewblock = $DB->get_record_sql($sql, array($view->id, $itemid)); // nobody, but me
			if (!$viewblock) {
				// item not linked to view -> no rights
			}
			// share artefact can not only owner. So we find did share item to others users. If shared - take owner and insert into select.
			$sharable = block_exaport_can_user_access_shared_item($view->userid, $itemid);
			if ($sharable) {
				$ownerid = $sharable;
			} else {
				$ownerid = $view->userid;
			}
			$conditions = array("id" => $itemid, "userid" => $ownerid);
			if (!$item = $DB->get_record("block_exaportitem", $conditions)) {
				// item not found
				return;
			}

			$item->access = $view->access;
			$item->access->page = 'view';

			// comments allowed?
			if ($item->access->request == 'extern') {
				$item->allowComments = false;
				$item->showComments = block_exaport_external_comments_enabled() && $view->externcomment;
			} else {
				$item->allowComments = true;
				$item->showComments = true;
			}

		} elseif (preg_match('!^portfolio/(.+)$!', $access, $matches)) {
			// in user portfolio mode

			if (!$user = block_exaport_get_user_from_access($matches[1], $epopaccess)) {
				return;
			}

			if ($user->access->request == 'extern') {
				$conditions = array("id" => $itemid, "userid" => $user->id);
				if (!$item = $DB->get_record("block_exaportitem", $conditions, "externaccess", 1)) {
					// item not found
					return;
				}
			} else {
				// intern
				// shared artefacts.
				$sharable = block_exaport_can_user_access_shared_item($USER->id, $itemid);
				if ($sharable) {
					$ownerid = $sharable;
				} else {
					$ownerid = $USER->id;
				}

				$item = $DB->get_record('block_exaportitem', ['userid' => $ownerid, 'id' => $itemid]);
				if (!$item) {
					// item not found
					return;
				}
			}

			$item->access = $user->access;
			$item->access->page = 'portfolio';

			// comments allowed?
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

		// loop through all my courses
		foreach (get_my_courses($USER->id, 'fullname ASC') as $dbCourse) {

			$course = array(
				'id' => $dbCourse->id,
				'fullname' => $dbCourse->fullname,
			);

			$courses[$course['id']] = $course;
		}

		// move active course to first position
		if (isset($courses[$COURSE->id])) {
			$course = $courses[$COURSE->id];
			unset($courses[$COURSE->id]);
			$courses = array_merge(array($course['id'] => $course), $courses);
		}

		return $courses;
	}

	function exaport_get_view_shared_users($viewid) {
		global $DB;

		$sharedUsers = $DB->get_records_menu('block_exaportviewshar', array("viewid" => $viewid), null, 'userid, userid AS tmp');

		return $sharedUsers;
	}

	function exaport_get_view_shared_groups($viewid) {
		global $DB;

		$sharedGroups = $DB->get_records_menu('block_exaportviewgroupshar', array("viewid" => $viewid), null, 'groupid, groupid AS tmp');

		return $sharedGroups;
	}

	function exaport_get_view_shared_emails($viewid) {
		global $DB;

		$sharedEmails = $DB->get_records_menu('block_exaportviewemailshar', array("viewid" => $viewid), null, 'email, email AS tmp');

		return $sharedEmails;
	}

	function exaport_get_category_shared_users($catid) {
		global $DB;

		$sharedUsers = $DB->get_records_menu('block_exaportcatshar', array("catid" => $catid), null, 'userid, userid AS tmp');

		return $sharedUsers;
	}

	function exaport_get_category_shared_groups($catid) {
		global $DB;

		$sharedGroups = $DB->get_records_menu('block_exaportcatgroupshar', array("catid" => $catid), null, 'groupid, groupid AS tmp');

		return $sharedGroups;
	}

	function exaport_get_shareable_courses_with_users_for_view($viewid) {
		global $DB;

		$sharedUsers = exaport_get_view_shared_users($viewid);
		$courses = exaport_get_shareable_courses_with_users('sharing');

		foreach ($courses as $course) {
			foreach ($course->users as $user) {
				if (isset($sharedUsers[$user->id])) {
					$user->shared_to = true;
					unset($sharedUsers[$user->id]);
				} else {
					$user->shared_to = false;
				}
			}
		}

		if ($sharedUsers) {
			$extraUsers = array();

			foreach ($sharedUsers as $userid) {
				$user = $DB->get_record('user', array('id' => $userid), user_picture::fields());
				if (!$user)
					// doesn't exist anymore
					continue;

				$extraUsers[] = (object)array(
					'id' => $user->id,
					'name' => fullname($user),
					'rolename' => '',
					'shared_to' => true,
				);
			}

			array_unshift($courses, (object)array(
				'id' => -1,
				'fullname' => get_string('other_users_course', 'block_exaport'),
				'users' => $extraUsers,
			));
		}

		return $courses;
	}

	function exaport_get_shareable_courses_with_users($type) {
		global $USER, $COURSE;
		$courses = array();
		//, 'suspended' => 0, 'deleted' => 0
		// loop through all my courses
		foreach (enrol_get_my_courses(null, 'fullname ASC') as $dbCourse) {

			$course = (object)array(
				'id' => $dbCourse->id,
				'fullname' => $dbCourse->fullname,
				'users' => array(),
			);

			$context = context_course::instance($dbCourse->id);
			$roles = get_roles_used_in_context($context);
			//print_r($roles);

			foreach ($roles as $role) {
				$users = get_role_users($role->id, $context, false, user_picture::fields('u'), null, true, '', '', '', ' deleted=0 AND suspended=0');
				if (!$users) {
					continue;
				}

				foreach ($users as $user) {
					if ($user->id == $USER->id)
						continue;

					$course->users[$user->id] = (object)array(
						'id' => $user->id,
						'name' => fullname($user),
						'rolename' => $role->name ? $role->name : $role->shortname,
					);
				}
			}

			$courses[$course->id] = $course;
		}

		// move active course to first position
		if (isset($courses[$COURSE->id]) && ($type != 'shared_views')) {
			$course = $courses[$COURSE->id];
			unset($courses[$COURSE->id]);
			$courses = array_merge(array($course->id => $course), $courses);
		}

		// test courses
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
			// global groups
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

	function block_exaport_get_items_shared_to_user($userid, $onlyitems = false) {
		global $DB;

		// Categories for user groups
		$usercats = block_exaport_get_group_share_categories($userid);

		// All categories and users who shared.
		$category_columns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
		$categories = $DB->get_records_sql(
			"SELECT $category_columns, u.firstname, u.lastname, u.picture, COUNT(DISTINCT cshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups  ".
			" FROM {user} u".
			" JOIN {block_exaportcate} c ON u.id=c.userid".
			" LEFT JOIN {block_exaportcatshar} cshar ON c.id=cshar.catid AND cshar.userid=?".

			" LEFT JOIN {block_exaportviewgroupshar} cgshar ON c.id=cgshar.groupid ".
			" LEFT JOIN {block_exaportcatshar} cshar_total ON c.id=cshar_total.catid ".
			" WHERE (".
			"(".(block_exaport_shareall_enabled() ? 'c.shareall=1 OR ' : '')." cshar.userid IS NOT NULL) ".  // only show shared all, if enabled
			// Shared for you group
			($usercats ? " OR c.id IN (".join(',', array_keys($usercats)).") " : ""). // Add group shareing categories
			")".
			" AND c.userid!=? ". // don't show my own categories
			" AND internshare = 1 ".
			" GROUP BY $category_columns, u.firstname, u.lastname, u.picture".
			" ORDER BY u.lastname, u.firstname, c.name", array($userid, $userid));

		//$sharedcategories = $DB->get_records_menu('block_exaportcatshar', array("userid" => $userid), null, 'id, id AS tmp');

		// Get users for grouping later
		$shared_users = array();
		$shared_categories = array();
		foreach ($categories as $key => $categorie) {
			if (!in_array($categorie->userid, $shared_users))
				$shared_users[] = $categorie->userid;
			if (!in_array($categorie->id, $shared_categories))
				$shared_categories[] = $categorie->id;
		}

		// get sub categories (recursively)
		for ($i = 0; $i < count($shared_categories); $i++) {
			$sub_categories = $DB->get_records_menu('block_exaportcate', ['pid' => $shared_categories[$i]], null, 'id, id as tmp');
			foreach ($sub_categories as $categoryid) {
				if (!in_array($categoryid, $shared_categories)) {
					$shared_categories[] = $categoryid;
				}
			}
		}

		// Get items for every user
		$shared_categories_list = implode(',', $shared_categories);

		if ($onlyitems) {
			$shared_items = [];

			foreach ($shared_users as $key => $userid) {
				// Only items for customise blocks. for views_mod.php. Or for check is shared
				$query = "select i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments".
					" from {block_exaportitem} i".
					" left join {block_exaportcate} ic on i.categoryid = ic.id".
					" left join {block_exaportcate} ic2 on ic.pid = ic2.id".
					" left join {block_exaportitemcomm} com on com.itemid = i.id".
					" where i.userid=? AND categoryid IN (".$shared_categories_list.")".
					" GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid".
					" ORDER BY i.name";

				$user_items = $DB->get_records_sql($query, array($userid));
				$shared_items = $shared_items + $user_items;
			}

			return $shared_items;
		} else {
			$shared_artefacts_by_user = array();
			foreach ($shared_users as $key => $userid) {
				$shared_artefacts_by_user[$key]['userid'] = $userid;
				$shared_artefacts_by_user[$key]['fullname'] = fullname($DB->get_record('user', array('id' => $userid)));
				$shared_artefacts_by_user[$key]['items'] = $DB->get_records_sql('SELECT * FROM {block_exaportitem} WHERE userid=? AND categoryid IN ('.$shared_categories_list.')', array('id' => $userid));
				// delete empty categories
				if (count($shared_artefacts_by_user[$key]['items']) == 0) {
					unset($shared_artefacts_by_user[$key]);
				}
			}

			return $shared_artefacts_by_user;
		}
	}

	/**
	 * checks if user can access shared item
	 * @param $userid
	 * @param $itemid
	 * @return bool
	 */
	function block_exaport_can_user_access_shared_item($userid, $itemid) {
		$itemsforuser = block_exaport_get_items_shared_to_user($userid, true);
		if (array_key_exists($itemid, $itemsforuser)) {
			return $itemsforuser[$itemid]->userid;
		} else {
			return false;
		}
	}

	function block_exaport_get_group_share_categories($userid) {
		$usergroups = block_exaport_get_user_cohorts($userid);
		if (!$usergroups) {
			return [];
		}

		return g::$DB->get_records_sql("
			SELECT catid
			FROM {block_exaportcatgroupshar}
			WHERE groupid IN (".join(',', array_keys($usergroups)).")");
	}

	function block_exaport_get_group_share_views($userid) {
		$usergroups = block_exaport_get_user_cohorts($userid);
		if (!$usergroups) {
			return [];
		}

		return g::$DB->get_records_sql("
			SELECT viewid
			FROM {block_exaportviewgroupshar}
			WHERE groupid IN (".join(',', array_keys($usergroups)).")");
	}
}

namespace block_exaport {

	use block_exaport\globals as g;

	function get_categories_shared_to_user($userid) {
		global $DB;

		// Categories for user groups
		$usercats = block_exaport_get_group_share_categories($userid);

		// All categories and users who shared.
		$categories = $DB->get_records_sql(
			"SELECT c.*, COUNT(DISTINCT cshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups  ".
			" FROM {user} u".
			" JOIN {block_exaportcate} c ON u.id=c.userid".
			" LEFT JOIN {block_exaportcatshar} cshar ON c.id=cshar.catid AND cshar.userid=?".

			" LEFT JOIN {block_exaportviewgroupshar} cgshar ON c.id=cgshar.groupid ".
			" LEFT JOIN {block_exaportcatshar} cshar_total ON c.id=cshar_total.catid ".
			" WHERE (".
			"(".(block_exaport_shareall_enabled() ? 'c.shareall=1 OR ' : '')." cshar.userid IS NOT NULL) ".  // only show shared all, if enabled
			// Shared for you group
			($usercats ? " OR c.id IN (".join(',', array_keys($usercats)).") " : ""). // Add group shareing categories
			")".
			" AND c.userid!=? ". // don't show my own categories
			" AND internshare = 1 ".
			" GROUP BY c.id, c.userid, c.name, c.timemodified, c.shareall, u.firstname, u.lastname, u.picture".
			" ORDER BY u.lastname, u.firstname, c.name", array($userid, $userid));

		$tree = [];
		foreach ($categories as $category) {
			if (!isset($tree[$category->userid])) {
				$user = $tree[$category->userid] = $DB->get_record('user', ['id' => $category->userid]);
				$user->categories = [];
				$user->name = fullname($user);
				$user->url = g::$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.g::$COURSE->id.'&type=shared&userid='.$user->id;
			} else {
				$user = $tree[$category->userid];
			}

			$category->url = g::$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.g::$COURSE->id.'&type=shared&userid='.$user->id.'&categoryid='.$category->id;
			$category->icon = block_exaport_get_category_icon($category);

			$user->categories[$category->id] = $category;
		}

		return $tree;
	}
}