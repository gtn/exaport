<?php

/* * *************************************************************
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
 * ************************************************************* */

require_once dirname(__FILE__) . '/inc.php';
require_once dirname(__FILE__) . '/lib/sharelib.php';

global $OUTPUT, $CFG;

$courseid = required_param('courseid', PARAM_INT);
$sort = optional_param('sort', 'user', PARAM_TEXT);
$access = optional_param('access', 0, PARAM_TEXT);
// TODO: for what is the u parameter?
$u = optional_param('u',0, PARAM_INT);
require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('block/exaport:use', $context);

$url = '/blocks/exabis_competences/shared_views.php';
$PAGE->set_url($url);
$PAGE->requires->js('/blocks/exaport/javascript/jquery.js', true);
$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

$parsedsort = block_exaport_parse_sort($sort, array('course', 'user', 'view', 'timemodified'));
if ($parsedsort[0] == 'timemodified') {
    $sql_sort = " ORDER BY v.timemodified DESC, v.name, u.lastname, u.firstname";
    $parsedsort[1] = 'desc';
    if(strcmp($CFG->dbtype, "sqlsrv")==0){
    	$sql_sort = " ORDER BY v.timemodified DESC, cast(v.name AS varchar(max)), u.lastname, u.firstname";
    }
} elseif ($parsedsort[0] == 'view') {
    $sql_sort = " ORDER BY v.name, u.lastname, u.firstname";
    if(strcmp($CFG->dbtype, "sqlsrv")==0){
    	$sql_sort = " ORDER BY cast(v.name AS varchar(max)), u.lastname, u.firstname";
    }
} else {
    $sql_sort = " ORDER BY u.lastname, u.firstname, v.name";
    if(strcmp($CFG->dbtype, "sqlsrv")==0){
    	$sql_sort = " ORDER BY u.lastname, u.firstname, cast(v.name AS varchar(max))";
    }
}



block_exaport_print_header("sharedbookmarks");

$strheader = get_string("sharedbookmarks", "block_exaport");

echo "<div class='block_eportfolio_center'>\n";
if ($u>0){
$whre=" AND u.id=".$u;}
else $whre="";
$views = $DB->get_records_sql(
                "SELECT v.*, u.firstname, u.lastname, u.picture" .
                " FROM {user} AS u" .
                " JOIN {block_exaportview} v ON u.id=v.userid" .
                " LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid=?" .
                " WHERE (v.shareall=1 OR vshar.userid IS NOT NULL)".$whre .
                " $sql_sort", array($USER->id));

function exaport_search_views($views, $column, $value) {
    $viewsFound = array();
    foreach ($views as $view) {
        if ($view->{$column} == $value) {
            $view->found = true;
            $viewsFound[] = $view;
        }
    }
    return $viewsFound;
}

function exaport_print_views($views, $parsedsort) {
    global $CFG, $courseid, $OUTPUT, $DB;

    $sort = $parsedsort[0];

    echo get_string('sortby') . ': ';
    echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=course\"" .
    ($sort == 'course' ? ' style="font-weight: bold;"' : '') . ">" . get_string('course') . "</a> | ";
    echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=user\"" .
    ($sort == 'user' ? ' style="font-weight: bold;"' : '') . ">" . get_string('user') . "</a> | ";
    echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=view\"" .
    ($sort == 'view' ? ' style="font-weight: bold;"' : '') . ">" . get_string('view', 'block_exaport') . "</a> | ";
    echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=timemodified\"" .
    ($sort == 'timemodified' ? ' style="font-weight: bold;"' : '') . ">" . get_string('date', 'block_exaport') . "</a> ";
    echo '</div>';

	if ($sort == 'user') {
		// group by user
		$viewsByUser = array();
		
		foreach ($views as $view) {
			if (!isset($viewsByUser[$view->userid])) {
				$viewsByUser[$view->userid] = array(
					'user' => $DB->get_record('user', array("id" => $view->userid)),
					'views' => array()
				);
			}
			
			$viewsByUser[$view->userid]['views'][] = $view;
		}
		
		?>
			<style>
				.view-group {
					padding-bottom: 15px;
				}
				.view-group .view-group-content {
					display: none;
				}
				.view-group-open .view-group-content {
					display: block;
				}
				.view-group-header {
					border: 1px solid #333;
				}
				.view-group-header span {
					text-decoration: underline;
					cursor: pointer;
				}
				.view-group-content {
					padding: 10px 0 15px 0;
				}
				.view-group-view {
					padding: 5px 0;
				}
			</style>
			<script type="text/javascript">
			//<![CDATA[
				jQueryExaport(function($){
					$('.view-group-header span').on('click', function(){
						$(this).parents('.view-group').toggleClass('view-group-open');
					});
				});
			//]]>
			</script>
		<?php

		foreach ($viewsByUser as $item) {
			$curuser = $item['user'];
			
			echo '<div class="view-group">';
			echo '<div class="view-group-header">';
			echo $OUTPUT->user_picture($curuser, array("courseid" => $courseid)).' <span>'.fullname($curuser).' ('.count($item['views']).')</span>';
			echo '</div>';

			echo '<div class="view-group-content">';
			foreach ($item['views'] as $view) {
				echo '<div class="view-group-view">';
				echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">" .
				format_string($view->name) . "</a> ".userdate($view->timemodified);
				echo '</div>';
			}
			echo '</div>';

			echo '</div>';
		}
		return;
	}

    $table = new html_table();
    $table->width = "100%";
    $table->head = array();
    $table->size = array();
    $table->head = array('userpic' => get_string('userpic'), 'user' => get_string('user'), 'view' => get_string('view', 'block_exaport'), 'timemodified' => get_string("date", "block_exaport"));
    $table->data = array();

    if ($sort == 'course') {
        $table->head = array_merge(array('course' => get_string('course')), $table->head);

        $courses = exaport_get_shareable_courses_with_users('shared_views');
        foreach ($courses as $course) {
            foreach ($course['users'] as $user) {
                $viewsFound = exaport_search_views($views, 'userid', $user['id']);
                if ($viewsFound) {
                    foreach ($viewsFound as $view) {
                        $curuser = $DB->get_record('user', array("id" => $user['id']));
                        $table->data[] = array(
                            $course['fullname'],
                            $OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
                            fullname($view),
                            "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">" .
                            format_string($view->name) . "</a>",
                            userdate($view->timemodified)
                        );
                    }
                }
            }
        }

        // get views, which weren't printed yet
        foreach ($views as $view) {
            if (!empty($view->found))
                continue;
            $curuser = $DB->get_record('user', array("id" => $view->userid));
            $table->data[] = array(get_string('nocoursetogether', 'block_exaport'),
                $OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
                fullname($view),
                "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">" .
                format_string($view->name) . "</a>",
                userdate($view->timemodified)
            );
        }
    } else {
        foreach ($views as $view) {
            $curuser = $DB->get_record('user', array("id" => $view->userid));
            $table->data[] = array(
                $OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
                fullname($view),
                "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">" .
                format_string($view->name) . "</a>",
                userdate($view->timemodified)
            );
        }
    }

    $sorticon = $parsedsort[1] . '.gif';
    $table->head[$parsedsort[0]] .= " <img src=\"pix/$sorticon\" alt='" . get_string("updownarrow", "block_exaport") . "' />";

    $output = html_writer::table($table);
    echo $output;
}

echo '<div style="padding-bottom: 20px;">';

if (!$views) {
    echo get_string("nothingshared", "block_exaport");
} else {
    exaport_print_views($views, $parsedsort);
}

echo "<br /><br />";

echo "</div>";

echo $OUTPUT->footer($course);
