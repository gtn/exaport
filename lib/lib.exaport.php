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
	$new_cat->id = g::$DB->insert_record("block_exaportcate", $curr_cat);

	$children = g::$DB->get_records("block_exaportcate", array('pid' => $new_cat->id));
	/*
	foreach ($children as $category) {
		copy_category_to_myself_iterator($category, $new_cat->id);
	}
	*/

	$items = g::$DB->get_records('block_exaportitem', ['categoryid' => $curr_cat->id]);
	foreach ($items as $item) {
		$new_item = new \stdClass();
		$new_item->userid = $item->userid;
		$new_item->type = $item->type;
		$new_item->categoryid = $item->$new_cat->id;
		$new_item->name = $item->name;
		$new_item->userid = $item->userid;
		$new_item->url = $item->url;
		$new_item->intro = $item->intro;
		$new_item->attachment = $item->attachment;
		$new_item->timemodified = $item->timemodified;
		$new_item->courseid = g::$COURSE->id;
		$new_item->sortorder = $item->sortorder;

		// TODO: kommentare
		// TODO: tags
	}

	return $new_cat;
}
