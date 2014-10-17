<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2006 exabis internet solutions <info@exabis.at>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once dirname(__FILE__).'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$context = context_system::instance();

if (! $course = $DB->get_record("course", array("id" => $courseid)) ) {
	error("That's an invalid course id");
}

$url = '/blocks/exaport/view_items.php';
$PAGE->set_url($url);


block_exaport_print_header("bookmarks");


echo '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>';

echo "<div class='box generalbox'>";
if (block_exaport_course_has_desp()) $pref="desp_";
else $pref="";
echo $OUTPUT->box( text_to_html(get_string($pref."explaining","block_exaport")) , "center");
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->itemsort)) {
	$sort = $userpreferences->itemsort;
}

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



block_exaport_setup_default_categories();

// read all categories
$categories = $DB->get_records_sql('
	SELECT c.id, c.name, c.pid, COUNT(i.id) AS item_cnt
	FROM {block_exaportcate} c
	LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND '.block_exaport_get_item_where().'
	WHERE c.userid = ?
	GROUP BY c.id, c.name, c.pid
	ORDER BY c.name ASC
', array($USER->id));

// build a tree according to parent
$categoriesByParent = array();
foreach ($categories as $category) {
	if (!isset($categoriesByParent[$category->pid])) $categoriesByParent[$category->pid] = array();
	$categoriesByParent[$category->pid][] = $category;
}

// the main root category
$rootCategory = block_exaport_get_root_category();
$categories[0] = $rootCategory;

// what's the current category? invalid / no category = root
if (isset($categories[$categoryid])) {
	$currentCategory = $categories[$categoryid];
} else {
	$currentCategory = $rootCategory;
}

// what's the parent category?
if (isset($categories[$currentCategory->pid])) {
	$parentCategory = $categories[$currentCategory->pid];
} else {
	$parentCategory = null;
}

// what's the display layout: tiles / details?
$layout = optional_param('layout', '', PARAM_TEXT);
if (!$layout && isset($userpreferences->view_items_layout)) $layout = $userpreferences->view_items_layout;
if ($layout != 'details') $layout = 'tiles'; // default = tiles

// save user preferences
block_exaport_set_user_preferences(array('itemsort'=>$sort, 'view_items_layout'=>$layout));

echo '<div class="excomdos_cont">';

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


echo '<div class="excomdos_additem"><div class="excomdos_additem_content">';
echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?action=add&courseid='.$courseid.'&pid='.$categoryid.'">'.
	'<img src="pix/folder_new_32.png" /><br />'.get_string("category", "block_exaport")."</a></span>";
echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=link">'.
	'<img src="pix/link_new_32.png" /><br />'.get_string("link", "block_exaport")."</a></span>";
echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=file">'.
	'<img src="pix/file_new_32.png" /><br />'.get_string("file", "block_exaport")."</a></span>";
echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?action=add&courseid='.$courseid.'&sesskey='.sesskey().'&categoryid='.$categoryid.'&type=note">'.
	'<img src="pix/note_new_32.png" /><br />'.get_string("note", "block_exaport")."</a></span>";
//anzeigen wenn kategorien vorhanden zum importieren aus sprachfile
$categories = trim(get_string("lang_categories", "block_exaport"));
if ($categories){
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?action=addstdcat&courseid='.$courseid.'">'.
		'<img src="pix/folder_new_32.png" /><br />'.get_string("addstdcat", "block_exaport")."</a></span>";
}
echo '</div>';

echo '<div class="excomdos_changeview"><p>';
			//<span>Zoom:</span>
			//<span><img src="tilezoomin.png" alt="Zoom in" /><img src="tilezoomout.png" alt="Zoom out" class="excomdos_padlf" /></span>
echo '<span>'.block_exaport_get_string('change_layout').':</span>';
if ($layout == 'tiles') {
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$categoryid.'&layout=details">'.
	'<img src="pix/view_list.png" alt="Tile View" /><br />'.block_exaport_get_string("details")."</a></span>";
} else {
	echo '<span><a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$categoryid.'&layout=tiles">'.
	'<img src="pix/view_tile.png" alt="Tile View" /><br />'.block_exaport_get_string("tiles")."</a></span>";
}

echo '<span><a target="_blank" href="'.$CFG->wwwroot.'/blocks/exaport/view_items_print.php?courseid='.$courseid.'">'.
'<img src="pix/view_print.png" alt="Tile View" /><br />'.get_string("printerfriendly", "group")."</a></span>";

echo '</p></div></div>';
		
echo '<div class="excomdos_cat">';
echo block_exaport_get_string('current_category').': ';
echo '<b>'.$currentCategory->name.'</b> ';
if ($currentCategory->id > 0) {
	echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentCategory->id.'&action=edit&back=same"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
	echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$currentCategory->id.'&action=delete&back=same"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>';
}
echo '</div>';

$sql_sort = block_exaport_item_sort_to_sql($parsedsort, false);
//echo $sql_sort;

$condition = array($USER->id, $currentCategory->id);

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
	", $condition);

if ($items || !empty($categoriesByParent[$currentCategory->id]) || $parentCategory) {
	// show output only if we have items, or we have subcategories, or we are in a subcategory

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
			$table->data[$item_i]['type'] = '<img src="pix/folder_32.png" alt="'.block_exaport_get_string('category').'">';
			
			$table->data[$item_i]['name'] = 
				'<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$parentCategory->id.'">parent: '.$parentCategory->name.'</a>';
			$table->data[$item_i][] = null;
			$table->data[$item_i][] = null;
		}
		
		if (!empty($categoriesByParent[$currentCategory->id])) {
			foreach ($categoriesByParent[$currentCategory->id] as $category) {
				$item_i++;
				$table->data[$item_i] = array();
				$table->data[$item_i]['type'] = '<img src="pix/folder_32.png" alt="'.block_exaport_get_string('category').'">';
				$table->data[$item_i]['name'] = 
					'<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id.'">'.$category->name.'</a>';

				$table->data[$item_i][] = null;
				$table->data[$item_i]['icons'] = 
					'<span class="excomdos_listicons">'.
					' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=edit"><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
					' <a href="'.$CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=delete"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>'.
					'</span>';
			}
		}

		$itemscnt = count($items);
		foreach ($items as $item) {
			$item_i++;

			$table->data[$item_i] = array();

			$table->data[$item_i]['type'] = '<img src="pix/'.$item->type.'_32.png" alt="'.get_string($item->type, "block_exaport").'">';

			$table->data[$item_i]['name'] = "<a href=\"".s("{$CFG->wwwroot}/blocks/exaport/shared_item.php?courseid=$courseid&access=portfolio/id/".$USER->id."&itemid=$item->id&backtype=&att=".$item->attachment)."\">" . $item->name . "</a>";
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
			
			if ($item->comments > 0) {
				$icons .= '<span class="excomdos_listcomments">'.$item->comments.'<img src="pix/comments.png" alt="file"></span>';
			}
			
			$icons .= block_exaport_get_item_comp_icon($item);
			
			// copy files to course
			if ($item->type == 'file' && block_exaport_feature_enabled('copy_to_course'))
				$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/copy_item_to_course.php?courseid='.$courseid.'&itemid='.$item->id.'&backtype=">'.get_string("copyitemtocourse", "block_exaport").'</a>';

			$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=edit&backtype="><img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
			$icons .= ' <a href="'.$CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=delete&categoryid='.$categoryid.'"><img src="pix/del.png" alt="' . get_string("delete"). '"/></a>';
			
			$icons = '<span class="excomdos_listicons">'.$icons.'</span>';

			$table->data[$item_i]['icons'] = $icons;
		}

		echo html_writer::table($table);
	} else {
		echo '<div class="excomdos_tiletable">';

		if ($parentCategory) {
			$url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$parentCategory->id;
			?>
			<div class="excomdos_tile excomdos_tile_category id-<?php echo $parentCategory->id; ?>">
				<div class="excomdos_tilehead">
					<span class="excomdos_tileinfo">
						<?php echo block_exaport_get_string('category_up'); ?>
						<br>
					</span>
			</div>
			<div class="excomdos_tileimage">
				<a href="<?php echo $url; ?>"><img src="pix/folder_tile.png"></a>
			</div>
			<div class="exomdos_tiletitle">
				<a href="<?php echo $url; ?>"><?php echo $parentCategory->name; ?></a>
			</div>
			</div>
			<?php
		}
		
		if (!empty($categoriesByParent[$currentCategory->id])) {
			foreach ($categoriesByParent[$currentCategory->id] as $category) {
				$url = $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id;
				?>
				<div class="excomdos_tile excomdos_tile_category id-<?php echo $category->id; ?>">
					<div class="excomdos_tilehead">
						<span class="excomdos_tileinfo">
							<?php echo block_exaport_get_string('category'); ?>
						</span>
						<span class="excomdos_tileedit">
							<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=edit'; ?>"><img src="pix/edit.png" alt="file"></a>
							<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/category.php?courseid='.$courseid.'&id='.$category->id.'&action=delete'; ?>"><img src="pix/del.png" alt="file"></a>
						</span>
				</div>
				<div class="excomdos_tileimage">
					<a href="<?php echo $url; ?>"><img src="pix/folder_tile.png"></a>
				</div>
				<div class="exomdos_tiletitle">
					<a href="<?php echo $url; ?>"><?php echo $category->name; ?></a>
				</div>
				</div>
				<?php
			}
		}

		foreach ($items as $item) {
			$url = $CFG->wwwroot.'/blocks/exaport/shared_item.php?courseid='.$courseid.'&access=portfolio/id/'.$USER->id.'&itemid='.$item->id;
			?>
			<div class="excomdos_tile excomdos_tile_item id-<?php echo $item->id; ?>">
				<div class="excomdos_tilehead">
					<span class="excomdos_tileinfo">
						<?php echo get_string($item->type, "block_exaport"); ?>
						<br><span class="excomdos_tileinfo_time"><?php echo userdate($item->timemodified); ?></span>
					</span>
					<span class="excomdos_tileedit">
						<?php 
							if ($item->comments > 0) {
								echo '<span class="excomdos_listcomments">'.$item->comments.'<img src="pix/comments.png" alt="file"></span>';
							}

							echo block_exaport_get_item_comp_icon($item);
						?>
						<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=edit'; ?>"><img src="pix/edit.png" alt="file"></a>
						<a href="<?php echo $CFG->wwwroot.'/blocks/exaport/item.php?courseid='.$courseid.'&id='.$item->id.'&sesskey='.sesskey().'&action=delete&categoryid='.$categoryid; ?>"><img src="pix/del.png" alt="file"></a>
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
} else {
	echo block_exaport_get_string("nobookmarksall", "block_exaport");
}

echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();


function block_exaport_get_item_comp_icon($item) {
	global $DB;
	
	if (!block_exaport_check_competence_interaction())
		return;
	
	$array = block_exaport_get_competences($item, 0);

	if(!count($array))
		return;
		
	// if item is assoziated with competences display them
	$competences = "";
	foreach($array as $element){

		$conditions = array("id" => $element->compid);
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
