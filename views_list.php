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

require_once(__DIR__ . '/inc.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_RAW);

block_exaport_require_login($courseid);

$url = '/blocks/exaport/views_list.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

block_exaport_add_iconpack();

if (!$COURSE) {
    print_error("invalidcourseid", "block_exaport");
}

block_exaport_print_header("views");

echo "<div class='block_eportfolio_center'>";
echo $OUTPUT->box(text_to_html(get_string("explainingviews", "block_exaport")));
echo "</div>";

$userpreferences = block_exaport_get_user_preferences();

if (!$sort && $userpreferences && isset($userpreferences->viewsort)) {
    $sort = $userpreferences->viewsort;
}

// Check sorting.
$parsedsort = block_exaport_parse_view_sort($sort);
$sort = $parsedsort[0] . '.' . $parsedsort[1];

$sortkey = $parsedsort[0];

if ($parsedsort[1] == "desc") {
    $newsort = $sortkey . ".asc";
} else {
    $newsort = $sortkey . ".desc";
}
$sorticon = $parsedsort[1] . '.png';

$query = "select v.*" .
    " from {block_exaportview} v" .
    " where v.userid = ?" .
    block_exaport_view_sort_to_sql($parsedsort);

$views = $DB->get_records_sql($query, array($USER->id));

if (!$views) {
    echo get_string("noviews", "block_exaport");
} else {

    $table = new html_table();
    $table->width = "100%";

    $table->head = array();
    $table->size = array();

    $table->head['name'] = '<a href="' . s($_SERVER['PHP_SELF'] . '?courseid=' . $courseid . '&sort=' .
            ($sortkey == 'name' ? $newsort : 'name')) . '">' . get_string("name", "block_exaport") . "</a>";
    $table->size['name'] = "30";

    $table->head['timemodified'] = '<a href="' . s($_SERVER['PHP_SELF'] . '?courseid=' . $courseid . '&sort=' .
            ($sortkey == 'timemodified' ? $newsort : 'timemodified.desc')) . '">' . get_string("date", "block_exaport") . "</a>";
    $table->size['timemodified'] = "20";

    $table->head['accessoptions'] = get_string("accessoptions", "block_exaport");
    $table->size['accessoptions'] = "30";

    $table->head[] = '';
    $table->size[] = "10";

    // Add arrow to heading if available.
    if (isset($table->head[$sortkey])) {
        $table->head[$sortkey] = "<img src=\"pix/$sorticon\" alt='" .
            get_string("updownarrow", "block_exaport") . "' /> " . $table->head[$sortkey];
    }

    $table->data = array();
    $lastcat = "";

    $viewi = -1;
    foreach ($views as $view) {
        $viewi++;

        $table->data[$viewi] = array();

        $table->data[$viewi]['name'] = '<a href="' .
            s($CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $courseid . '&access=id/' . $USER->id . '-' . $view->id) . '">' .
            format_string($view->name) . "</a>";
        if ($view->description) {
            $table->data[$viewi]['name'] .= "<table width=\"98%\"><tr><td>" .
                format_text($view->description, FORMAT_HTML) . "</td></tr></table>";
        }

        $table->data[$viewi]['timemodified'] = userdate($view->timemodified);

        $table->data[$viewi]['accessoptions'] = '';
        if ($view->shareall == 1 && block_exaport_shareall_enabled()) {
            $table->data[$viewi]['accessoptions'] .= '<div>' . get_string("internalaccess", "block_exaport") . ':</div>' .
                '<div style="padding-left: 10px;">' . get_string("internalaccessall", "block_exaport") . '</div>';
        } else if ($view->shareall == 2 && block_exaport_shareall_enabled()) {
            // Read groups.
            $query = "SELECT name" .
                " FROM {groups} g," .
                " {block_exaportviewgroupshar} vshar WHERE g.id=vshar.groupid AND vshar.viewid=?" .
                " ORDER BY name";
            $groups = $DB->get_records_sql($query, array($view->id));

            if ($groups) {
                foreach ($groups as &$group) {
                    $group = $group->name;
                }
                $table->data[$viewi]['accessoptions'] .= '<div>' . get_string("internalaccessgroups", "block_exaport") . ':</div>' .
                    '<div style="padding-left: 10px;">' . join(', ', $groups) . '</div>';
            }
        } else {
            // Read users.
            $query = "SELECT " . $DB->sql_fullname() . " AS name" .
                " FROM {user} u," .
                " {block_exaportviewshar} vshar WHERE u.id=vshar.userid AND vshar.viewid=? AND u.deleted = 0 " .
                " ORDER BY name";
            $users = $DB->get_records_sql($query, array($view->id));

            if ($users) {
                foreach ($users as &$user) {
                    $user = $user->name;
                }
                $table->data[$viewi]['accessoptions'] .= '<div>' . get_string("internalaccessusers", "block_exaport") . ':</div>' .
                    '<div style="padding-left: 10px;">' . join(', ', $users) . '</div>';
            }
        }
        if (block_exaport_externaccess_enabled() && $view->externaccess) {
            if ($table->data[$viewi]['accessoptions']) {
                $style = 'padding-top: 10px;';
            } else {
                $style = '';
            }
            $url = block_exaport_get_external_view_url($view);
            $table->data[$viewi]['accessoptions'] .= '<div style="' . $style . '">' . get_string("externalaccess", "block_exaport") .
                ':</div><div style="padding-left: 10px;"><a href="' . $url . '" target="_blank">' . $url . '</a></div>';
        }

        $icons = '';
        $icons .= '<a title="' . get_string("edit", "block_exaport") . '" href="' .
            s(dirname($_SERVER['PHP_SELF']) . '/views_mod.php?courseid=' . $courseid . '&id=' . $view->id . '&sesskey=' . sesskey() .
                '&action=edit') . '">'
            . block_exaport_fontawesome_icon('pen-to-square', 'regular', 1)
            //                        .'<img src="'.$CFG->wwwroot.'/blocks/exaport/pix/editview.png" class="iconsmall" alt="'.get_string("edit").'" />'
            . '</a> ';

        $icons .= '<a title="' . get_string("delete", "block_exaport") . '" href="' .
            s(dirname($_SERVER['PHP_SELF']) . '/views_mod.php?courseid=' . $courseid . '&id=' . $view->id . '&sesskey=' . sesskey() .
                '&action=delete&confirm=1') . '">'
            . block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon'])
            //                        .'<img src="'.$CFG->wwwroot.'/blocks/exaport/pix/deleteview.png" class="iconsmall" alt="'.get_string("delete").'"/>'
            . '</a> ';

        $table->data[$viewi]['icons'] = $icons;
    }

    $output = html_writer::table($table);
    echo $output;
}

echo "<div class='block_eportfolio_center'>";

echo "<form action=\"{$CFG->wwwroot}/blocks/exaport/views_mod.php?sesskey=" . sesskey() . "\" method=\"post\">
        <fieldset>
          <input type=\"hidden\" name=\"action\" value=\"add\"/>
          <input type=\"hidden\" name=\"courseid\" value=\"$courseid\"/>";

echo "<input type=\"submit\" value=\"" . get_string("newview", "block_exaport") . "\" class=\"btn btn-primary\"/>";

echo "</fieldset>
      </form>";

echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
