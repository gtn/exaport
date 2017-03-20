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

require_once __DIR__.'/inc.php';

use block_exaport\globals as g;

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_TEXT);
$layout = optional_param('layout', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_TEXT);

block_exaport_require_login($courseid);

$context = context_system::instance();

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
	$sort = $userpreferences->itemsort;
}

if ($type != 'shared') $type = 'mine';

// what's the display layout: tiles / details?
if (!$layout && isset($userpreferences->view_items_layout)) $layout = $userpreferences->view_items_layout;
if ($layout != 'details') $layout = 'tiles'; // default = tiles

// check sorting
$parsedsort = block_exaport_parse_item_sort($sort, false);
$sort = $parsedsort[0].'.'.$parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
	$newsort = $sortkey.".asc";
} else {
	$newsort = $sortkey.".desc";
}
$sorticon = $parsedsort[1].'.png';
$sql_sort = block_exaport_item_sort_to_sql($parsedsort, false);

block_exaport_setup_default_categories();

if ($type == 'shared') {
	$rootCategory = (object)[
		'id' => 0,
		'pid' => 0,
		'name' => block_exaport_get_string('shareditems_category'),
		'item_cnt' => '',
		'url' => $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$COURSE->id.'&type=shared',
	];

	$sharedUsers = block_exaport\get_categories_shared_to_user($USER->id);
	$selectedUser = $userid && isset($sharedUsers[$userid]) ? $sharedUsers[$userid] : null;

	/*
	if (!$selectedUser) {
		$currentCategory = $rootCategory;
		$parentCategory = null;
		$subCategories = $sharedUsers;

		foreach ($subCategories as $category) {
	        $userpicture = new user_picture($category);
			$userpicture->size = ($layout == 'tiles' ? 100 : 32);
			$category->icon = $userpicture->get_url($PAGE);
		}

		$items = [];
	} else {
		$currentCategory = $selectedUser;
		$subCategories = $selectedUser->categories;
		$parentCategory = $rootCategory;
		$items = [];
	}
	*/
	if (!$selectedUser || !$categoryid) {
		throw new moodle_exception('wrong category/userid');
	} else {
		$category_columns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
		$categories = $DB->get_records_sql("
			SELECT
				{$category_columns}
				, COUNT(i.id) AS item_cnt
			FROM {block_exaportcate} c
			LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND ".block_exaport_get_item_where()."
			WHERE c.userid = ?
			GROUP BY
				{$category_columns}
			ORDER BY c.name ASC
		", array($selectedUser->id));

		function category_allowed($selectedUser, $categories, $category) {
			while ($category) {
				if (isset($selectedUser->categories[$category->id])) {
					return true;
				} elseif ($category->pid && isset($categories[$category->pid])) {
					$category = $categories[$category->pid];
				} else {
					break;
				}
			}

			return false;
		}


		// build a tree according to parent
		$categoriesByParent = [];
		foreach ($categories as $category) {
			$category->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&type=shared&userid='.$userid.'&categoryid='.$category->id;
			$category->icon = block_exaport_get_category_icon($category);

			if (!isset($categoriesByParent[$category->pid])) $categoriesByParent[$category->pid] = array();
			$categoriesByParent[$category->pid][] = $category;
		}

		if (!isset($categories[$categoryid])) {
			throw new moodle_exception('not allowed');
		}

		$currentCategory = $categories[$categoryid];
		$subCategories = !empty($categoriesByParent[$currentCategory->id]) ? $categoriesByParent[$currentCategory->id] : [];
		if (isset($categories[$currentCategory->pid]) && category_allowed($selectedUser, $categories, $categories[$currentCategory->pid])) {
			$parentCategory = $categories[$currentCategory->pid];
		} else {
			$parentCategory = (object)[
				'id' => 0,
				'url' => new moodle_url('shared_categories.php', ['courseid'=>$COURSE->id]),
				'name' => '',
			];
		}

		if (!category_allowed($selectedUser, $categories, $currentCategory)) {
			throw new moodle_exception('not allowed');
		}

		$items = $DB->get_records_sql("
			SELECT i.*, COUNT(com.id) As comments
			FROM {block_exaportitem} i
			LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
			WHERE i.categoryid=?
				AND ".block_exaport_get_item_where()."	
			GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro, 
			i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess, 
			i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url, 
			i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, 
			i.iseditable, i.example_url, i.parentid
			$sql_sort
		", [$currentCategory->id]);
	}

} else {
	// read all categories
	$category_columns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
	$categories = $DB->get_records_sql("
		SELECT
			{$category_columns}
			, COUNT(i.id) AS item_cnt
		FROM {block_exaportcate} c
		LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND ".block_exaport_get_item_where()."
		WHERE c.userid = ?
		GROUP BY
			{$category_columns}
		ORDER BY c.name ASC
	", array($USER->id));

	foreach ($categories as $category) {
		$category->url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id;
		$category->icon = block_exaport_get_category_icon($category);
	}

	// build a tree according to parent
	$categoriesByParent = array();
	foreach ($categories as $category) {
		if (!isset($categoriesByParent[$category->pid])) $categoriesByParent[$category->pid] = array();
		$categoriesByParent[$category->pid][] = $category;
	}

	// the main root category
	$rootCategory = block_exaport_get_root_category();
	$categories[0] = $rootCategory;

	if (isset($categories[$categoryid])) {
		$currentCategory = $categories[$categoryid];
	} else {
		$currentCategory = $rootCategory;
	}

	// what's the parent category?
	if ($currentCategory->id && isset($categories[$currentCategory->pid])) {
		$parentCategory = $categories[$currentCategory->pid];
	} else {
		$parentCategory = null;
	}

	$subCategories = !empty($categoriesByParent[$currentCategory->id]) ? $categoriesByParent[$currentCategory->id] : [];

	// Common items.
	$items = $DB->get_records_sql("
			SELECT i.*, COUNT(com.id) As comments
			FROM {block_exaportitem} i
			LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
			WHERE i.userid = ? AND i.categoryid=?
				AND ".block_exaport_get_item_where()."	
			GROUP BY i.id, i.userid, i.type, i.categoryid, i.name, i.url, i.intro, 
			i.attachment, i.timemodified, i.courseid, i.shareall, i.externaccess, 
			i.externcomment, i.sortorder, i.isoez, i.fileurl, i.beispiel_url, 
			i.exampid, i.langid, i.beispiel_angabe, i.source, i.sourceid, 
			i.iseditable, i.example_url, i.parentid
			$sql_sort
		", [$USER->id, $currentCategory->id]);
}

$PAGE->set_url($currentCategory->url);

block_exaport_print_header($type == 'shared' ? 'shared_categories' : "myportfolio");

// echo '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>';

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) $pref="desp_";
else $pref="";
echo $OUTPUT->box( text_to_html(get_string($pref."explaining","block_exaport")) , "center");
echo "</div>";

// save user preferences
block_exaport_set_user_preferences(array('itemsort'=>$sort, 'view_items_layout'=>$layout));

echo '<div class="excomdos_cont excomdos_cont-type-'.$type.'">';

if ($type == 'mine') {
	//echo block_exaport_get_string('categories').': ';
	echo get_string("categories","block_exaport").": ";
	echo '<select onchange="document.location.href=\''.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid=\'+this.value;">';
	echo '<option value="">';
	echo $rootCategory->name;
	if ($rootCategory->item_cnt) echo ' ('.$rootCategory->item_cnt.' '.block_exaport_get_string($rootCategory->item_cnt == 1?'item':'items').')';
	echo '</option>';
	function block_exaport_print_category_select($categoriesByParent, $currentCategoryid, $pid=0, $level=0) {
		if (!isset($categoriesByParent[$pid])) return;

		foreach ($categoriesByParent[$pid] as $category) {
			echo '<option value="'.$category->id.'"'.($currentCategoryid == $category->id?' selected="selected"':'').'>';
			if ($level)
				echo str_repeat('&nbsp;', 4*$level).' &rarr;&nbsp; ';
			echo $category->name;
			if ($category->item_cnt) echo ' ('.$category->item_cnt.' '.block_exaport_get_string($category->item_cnt == 1?'item':'items').')';
			echo '</option>';
			block_exaport_print_category_select($categoriesByParent, $currentCategoryid,
				$category->id, $level+1);
		}
	}
	block_exaport_print_category_select($categoriesByParent, $currentCategory->id);
	echo '</select>';
}

echo '<div class="excomdos_additem">';
if ($type == 'mine') {
	echo '<div class="excomdos_additem_content">';
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?action=add&courseid='.$courseid.'&pid='.$categoryid.'">'.
		'<img src="pix/folder_new_32.png" /><br />'.get_string("category", "block_exaport")."</a></span>";
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.'&type=link">'.
		'<img src="pix/link_new_32.png" /><br />'.get_string("link", "block_exaport")."</a></span>";
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.'&type=file">'.
		'<img src="pix/file_new_32.png" /><br />'.get_string("file", "block_exaport")."</a></span>";
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&categoryid='.$categoryid.'&type=note">'.
		'<img src="pix/note_new_32.png" /><br />'.get_string("note", "block_exaport")."</a></span>";
	//anzeigen wenn kategorien vorhanden zum importieren aus sprachfile
	$categories = trim(get_string("lang_categories", "block_exaport"));
	if ($categories){
		echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?action=addstdcat&courseid='.$courseid.'">'.
			'<img src="pix/folder_new_32.png" /><br />'.get_string("addstdcat", "block_exaport")."</a></span>";
	}
	echo '</div>';
}

echo '<div class="excomdos_changeview"><p>';
			//<span>Zoom:</span>
			//<span><img src="tilezoomin.png" alt="Zoom in" /><img src="tilezoomout.png" alt="Zoom out" class="excomdos_padlf" /></span>
echo '<span>'.block_exaport_get_string('change_layout').':</span>';
if ($layout == 'tiles') {
	echo '<span><a href="'.$PAGE->url->out(true, ['layout' => 'details']).'">'.
	'<img src="pix/view_list.png" alt="Tile View" /><br />'.block_exaport_get_string("details")."</a></span>";
} else {
	echo '<span><a href="'.$PAGE->url->out(true, ['layout' => 'tiles']).'">'.
	'<img src="pix/view_tile.png" alt="Tile View" /><br />'.block_exaport_get_string("tiles")."</a></span>";
}

if ($type == 'mine') {
	echo '<span><a target="_blank" href="'.$CFG->wwwroot.'/blocks/exaport/view_items_print.php?courseid='.$courseid.'">'.
	'<img src="pix/view_print.png" alt="Tile View" /><br />'.get_string("printerfriendly", "group")."</a></span>";
}
echo '</p></div></div>';

echo '<div class="excomdos_cat">';
echo block_exaport_get_string('current_category').': ';

echo '<b>';
if ($type == 'shared' && $selectedUser) {
	echo $selectedUser->name.' / ';
}
echo $currentCategory->name;
echo '</b> ';

if ($type == 'mine' && $currentCategory->id > 0) {
	if (@$currentCategory->internshare && (count(exaport_get_category_shared_users($currentCategory->id)) > 0 || count(exaport_get_category_shared_groups($currentCategory->id)) > 0 || $currentCategory->shareall==1)) {
		echo ' <img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
	}
	echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentCategory->id.'&action=edit&back=same"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
	echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentCategory->id.'&action=delete&back=same"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>';
} elseif ($type == 'shared' && $selectedUser && $categoryid) {
	// when category selected, allow copy
	/*
	$url = $PAGE->url->out(true, ['action'=>'copy']);
	echo '<button onclick="document.location.href=\'shared_categories.php?courseid='.$courseid.'&action=copy&categoryid='.$categoryid.'\'">'.block_exaport_get_string('copycategory').'</button>';
	*/
}
echo '</div>';

if ($layout == 'details') {
	$table = new html_table();
	$table->width = "100%";

	$table->head = array();
	$table->size = array();

	$table->head['type'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
			($sortkey == 'type' ? $newsort : 'type') ."'>" . get_string("type", "block_exaport") . "</a>";
	$table->size['type'] = "10";

	$table->head['name'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
			($sortkey == 'name' ? $newsort : 'name') ."'>" . get_string("name", "block_exaport") . "</a>";
	$table->size['name'] = "60";

	$table->head['date'] = "<a href='{$CFG->wwwroot}/blocks/exaport/view_items.php?courseid=$courseid&categoryid=$categoryid&sort=".
			($sortkey == 'date' ? $newsort : 'date.desc') ."'>" . get_string("date", "block_exaport") . "</a>";
	$table->size['date'] = "20";

	$table->head['icons'] = '';
	$table->size['icons'] = "10";

	// add arrow to heading if available
	if (isset($table->head[$sortkey]))
		$table->head[$sortkey] = "<img src=\"pix/$sorticon\" alt='".get_string("updownarrow", "block_exaport")."' /> ".$table->head[$sortkey];

	$table->data = Array();
	$item_i = -1;

	if ($parentCategory) {
		// if isn't parent category, show link to go to parent category
		$item_i++;
		$table->data[$item_i] = array();
		$table->data[$item_i]['type'] = '<img src="pix/folderup_32.png" alt="'.block_exaport_get_string('category').'">';

		$table->data[$item_i]['name'] =
			'<a href="'.$parentCategory->url.'">'.$parentCategory->name.'</a>';
		$table->data[$item_i][] = null;
		$table->data[$item_i][] = null;
	}

	foreach ($subCategories as $category) {
		// Checking for shared items. If userid is null - show users, if userid > 0 - need to show items from user.
		$item_i++;
		$table->data[$item_i] = array();
		$table->data[$item_i]['type'] = '<img src="'.(@$category->icon ?: 'pix/folder_32_user.png').'" style="max-width:32px">';

		$table->data[$item_i]['name'] =
			'<a href="'.$category->url.'">'.$category->name.'</a>';

		$table->data[$item_i][] = null;

		if ($type == 'mine' && $category->id > 0) {
			$table->data[$item_i]['icons'] = '<span class="excomdos_listicons">';
			if ((isset($category->internshare) && $category->internshare == 1) && (count(exaport_get_category_shared_users($category->id)) > 0 || count(exaport_get_category_shared_groups($category->id)) > 0 || (isset($category->shareall) && $category->shareall==1))) {
				$table->data[$item_i]['icons'] .= '<img src="pix/noteitshared.gif" alt="file" title="shared to other users">';
			};
			if (@$category->structure_share) {
				$table->data[$item_i]['icons'] .= ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
			}

			$table->data[$item_i]['icons'] .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=edit"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
			' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=delete"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>'.
				'</span>';
		} else { // Category with shared items.
			$table->data[$item_i]['icons'] = '';
		}
	}

	$itemscnt = count($items);
	foreach ($items as $item) {
		$url = $CFG->wwwroot.'/blocks/exaport/shared_item.php?courseid='.$courseid.'&access=portfolio/id/'.$item->userid.'&itemid='.$item->id;

		$item_i++;

		$table->data[$item_i] = array();

		$table->data[$item_i]['type'] = '<img src="pix/'.$item->type.'_32.png" alt="'.get_string($item->type, "block_exaport").'">';

		$table->data[$item_i]['name'] = "<a href=\"".s($url)."\">" . $item->name . "</a>";
		if ($item->intro) {
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);

			$shortIntro = substr(trim(strip_tags($intro)), 0, 20);
			if(preg_match_all('#(?:<iframe[^>]*)(?:(?:/>)|(?:>.*?</iframe>))#i', $intro, $matches)) {
				$shortIntro = $matches[0][0];
			}

			if (!$intro) {
				// no intro
			} elseif ($shortIntro == $intro) {
				// very short one
				$table->data[$item_i]['name'] .= "<table width=\"50%\"><tr><td width=\"50px\">".format_text($intro, FORMAT_HTML)."</td></tr></table>";
			} else {
				// display show/hide buttons
				$table->data[$item_i]['name'] .=
				'<div><div id="short-preview-'.$item_i.'"><div>'.$shortIntro.'...</div>
				<a href="javascript:long_preview_show('.$item_i.')">['.get_string('more').'...]</a>
				</div>
				<div id="long-preview-'.$item_i.'" style="display: none;"><div>'.$intro.'</div>
				<a href="javascript:long_preview_hide('.$item_i.')">['.strtolower(get_string('hide')).'...]</a>
				</div>';
			}
		}

		$table->data[$item_i]['date'] = userdate($item->timemodified);

		$icons = '';

		// Link to export to my portfolio
		if ($currentCategory->id == -1) {
			$table->data[$item_i]['icons'] = '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=copytoself'.'"><img src="pix/import.png" title="'.get_string('make_it_yours', "block_exaport").'"></a>';
			continue;
		};

		if (isset($item->comments) && $item->comments > 0) {
			$icons .= '<span class="excomdos_listcomments">'.$item->comments.'<img src="pix/comments.png" alt="file"></span>';
		}

		$icons .= block_exaport_get_item_comp_icon($item);

		// copy files to course
		if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course'))
			$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/copy_item_to_course.php?courseid='.$courseid.'&itemid='.$item->id.'&backtype=">'.get_string("copyitemtocourse", "block_exaport").'</a>';

		if ($type == 'mine') {
			$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&action=edit"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
			if ($allowEdit = block_exaport_item_is_editable($item->id))
				$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&action=delete&categoryid='.$categoryid.'"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>';
			else
				$icons .= '<img src="pix/deleteview.png" alt="' . get_string("delete"). '">';
		}

		$icons = '<span class="excomdos_listicons">'.$icons.'</span>';

		$table->data[$item_i]['icons'] = $icons;
	}

	echo html_writer::table($table);
} else {
	echo '<div class="excomdos_tiletable">';

	if ($parentCategory) {
		?>
		<div class="excomdos_tile excomdos_tile_fixed excomdos_tile_category id-<?php echo $parentCategory->id; ?>">
			<div class="excomdos_tilehead">
				<span class="excomdos_tileinfo">
					<?php echo block_exaport_get_string('category_up'); ?>
					<br>
				</span>
		</div>
		<div class="excomdos_tileimage">
			<a href="<?php echo $parentCategory->url; ?>"><img src="pix/folderup_tile.png"></a>
		</div>
		<div class="exomdos_tiletitle">
			<a href="<?php echo $parentCategory->url; ?>"><?php echo $parentCategory->name; ?></a>
		</div>
		</div>
		<?php
	}

	foreach ($subCategories as $category) {
		?>
		<div class="excomdos_tile <?php if ($type == 'shared') echo 'excomdos_tile_fixed'; ?> excomdos_tile_category id-<?php echo $category->id; ?>">
			<div class="excomdos_tilehead">
				<span class="excomdos_tileinfo">
					<?php
						if ($currentCategory->id == -1) {
							echo block_exaport_get_string('user');
						} else {
							echo block_exaport_get_string('category');
						}
					?>
				</span>
				<span class="excomdos_tileedit">
					<?php
					if ($category->id == -1) {
						//
					} elseif ($type == 'shared') {
						?>
							<img src="pix/noteitshared.gif" alt="file" title="shared to other users">
						<?php
					} else {
						// type == mine
						if (@$category->internshare && (count(exaport_get_category_shared_users($category->id)) > 0 || count(exaport_get_category_shared_groups($category->id)) > 0 || (isset($category->shareall) && $category->shareall==1))) { ?>
							<img src="pix/noteitshared.gif" alt="file" title="shared to other users">
						<?php };
						if (@$category->structure_share) {
							echo ' <img src="pix/sharedfolder.png" title="shared to other users as a structure">';
						};?>
						<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=edit'; ?>"><img src="pix/edit.png" alt="file"></a>
						<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=delete'; ?>"><img src="pix/del.png" alt="file"></a>
					<?php
					}
					?>
				</span>
		</div>
		<div class="excomdos_tileimage">
			<a href="<?php echo $category->url; ?>">
					<?php
					$img_url = @$category->icon ?: 'pix/folder_tile.png';
					echo '<img src="'.$img_url.'">';
					?>
			</a>
		</div>
		<div class="exomdos_tiletitle">
			<a href="<?php echo $category->url; ?>"><?php echo $category->name; ?></a>
		</div>
		</div>
		<?php
	}

	foreach ($items as $item) {
		$url = $CFG->wwwroot.'/blocks/exaport/shared_item.php?courseid='.$courseid.'&access=portfolio/id/'.$item->userid.'&itemid='.$item->id;
		?>
		<div class="excomdos_tile excomdos_tile_item id-<?php echo $item->id; ?>">
			<div class="excomdos_tilehead">
				<span class="excomdos_tileinfo">
					<?php echo get_string($item->type, "block_exaport"); ?>
					<br><span class="excomdos_tileinfo_time"><?php echo userdate($item->timemodified); ?></span>
				</span>
				<span class="excomdos_tileedit">
					<?php
					if ($currentCategory->id == -1) {
						// Link to export to portfolio
						echo '<a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=copytoself'.'"><img src="pix/import.png" title="'.get_string('make_it_yours', "block_exaport").'"></a>';
					} else {
						if ($item->comments > 0) {
							echo '<span class="excomdos_listcomments">'.$item->comments.'<img src="pix/comments.png" alt="file"></span>';
						}
						echo block_exaport_get_item_comp_icon($item);

						if ($type == 'mine') {
							?>
							<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&action=edit'; ?>"><img src="pix/edit.png" alt="file"></a>
							<?php if($allowEdit = block_exaport_item_is_editable($item->id)) { ?>
								<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&action=delete&categoryid='.$categoryid; ?>"><img src="pix/del.png" alt="file"></a>
							<?php } else { ?>
								<img src="pix/deleteview.png" alt="file">
								<?php
							}
						}
					}
					?>
				</span>
		</div>
		<div class="excomdos_tileimage">
			<a href="<?php echo $url; ?>"><img alt="<?php echo $item->name ?>" title="<?php echo $item->name ?>" src="<?php echo $CFG->wwwroot.'/blocks/exaport/item_thumb.php?item_id='.$item->id; ?>" /></a>
		</div>
		<div class="exomdos_tiletitle">
			<a href="<?php echo $url; ?>"><?php echo $item->name; ?></a>
		</div>
		</div>
		<?php
	}

	echo '</div>';
}

//	echo block_exaport_get_string("nobookmarksall", "block_exaport");


echo '<div style="clear: both;">&nbsp;</div>';
echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();


function block_exaport_get_item_comp_icon($item) {
	global $DB;

	if (!block_exaport_check_competence_interaction())
		return;

	$compids = block_exaport_get_active_compids_for_item($item);

	if(!$compids)
		return;

	// if item is assoziated with competences display them
	$competences = "";
	foreach($compids as $compid){

		$conditions = array("id" => $compid);
		$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING);

		if($competencesdb != null){
			$competences .= $competencesdb->title.'<br>';
		}
	}
	$competences = str_replace("\r", "", $competences);
	$competences = str_replace("\n", "", $competences);
	$competences = str_replace("\"", "&quot;", $competences);
	$competences = str_replace("'", "&prime;", $competences);

	return '<a onmouseover="Tip(\''.$competences.'\')" onmouseout="UnTip()"><img src="pix/comp.png" alt="'.'competences'.'" /></a>';
}
