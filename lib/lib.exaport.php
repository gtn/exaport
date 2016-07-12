<?php

namespace block_exaport;

use block_exaport\globals as g;

// copy shared structure tree to user
function copy_category_to_myself($categoryid) {
	$root_cat = g::$DB->get_record("block_exaportcate", array('id' => $categoryid));
	if (!$root_cat) {
		throw new moodle_exception('category not found');
	}

	return _copy_category_to_myself_iterator($root_cat, 0);
}

function _copy_category_to_myself_iterator($curr_cat, $parentcatid) {
	global $CFG;
	$new_cat = new \stdClass();
	$new_cat->pid = $parentcatid;
	$new_cat->userid = g::$USER->id;
	if (!$parentcatid) {
		$new_cat->name = get_string('copyof', 'badges', $curr_cat->name);
	} else {
		$new_cat->name = $curr_cat->name;
	}
	$new_cat->timemodified = $curr_cat->timemodified;
	$new_cat->courseid = g::$COURSE->id;
	$new_cat->description = $curr_cat->description;
	$new_cat->id = g::$DB->insert_record("block_exaportcate", $new_cat);

	$children = g::$DB->get_records("block_exaportcate", array('pid' => $curr_cat->id));
	foreach ($children as $category) {
		_copy_category_to_myself_iterator($category, $new_cat->id);
	}

	$items = g::$DB->get_records('block_exaportitem', ['categoryid' => $curr_cat->id]);
	foreach ($items as $item) {
		$new_item = new \stdClass();
		$new_item->userid = g::$USER->id;
		$new_item->type = $item->type;
		$new_item->categoryid = $new_cat->id;
		$new_item->name = $item->name;
		$new_item->url = $item->url;
		$new_item->intro = $item->intro;
		$new_item->attachment = $item->attachment;
		$new_item->timemodified = $item->timemodified;
		$new_item->courseid = g::$COURSE->id;
		$new_item->sortorder = $item->sortorder;

		$new_item->id = g::$DB->insert_record('block_exaportitem', $new_item);

		// files
		$fs = get_file_storage();
		if ($file = block_exaport_get_item_file($item)) {
			$fs->create_file_from_storedfile(array(
				'contextid' => \context_user::instance(g::$USER->id)->id,
				'component' => 'block_exaport',
				'filearea' => 'item_file',
				'itemid' => $new_item->id,
			), $file);
		}
		if ($file = block_exaport_get_file($item, 'item_iconfile')) {
			$fs->create_file_from_storedfile(array(
				'contextid' => \context_user::instance(g::$USER->id)->id,
				'component' => 'block_exaport',
				'filearea' => 'item_iconfile',
				'itemid' => $new_item->id,
			), $file);
		}

		// comments
		$comments = g::$DB->get_records("block_exaportitemcomm", ["itemid" => $item->id], 'timemodified DESC');
		foreach ($comments as $comment) {
			$new_comment = new \stdClass();
			$new_comment->itemid = $new_item->id;
			$new_comment->userid = $comment->userid;
			$new_comment->entry = $comment->entry;
			$new_comment->timemodified = $comment->timemodified;
		}

		// tags
		if (!empty($CFG->usetags)) {
			if ($CFG->branch < 31) {
				// Moodle before v3.1
				include_once(g::$CFG->dirroot.'/tag/lib.php');
				$tags = tag_get_tags_array('block_exaportitem', $item->id);
				tag_set('block_exaportitem', $new_item->id, $tags, 'block_exaport', \context_user::instance(g::$USER->id)->id);
			} else {
				// Moodle v3.1
				$tags = core_tag_tag::get_item_tags_array('block_exaport', 'block_exaportitem', $item->id);	
				core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $new_item->id, \context_user::instance($USER->id), $tags);
			}
		}
	}

	return $new_cat;
}
