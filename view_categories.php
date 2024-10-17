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

global $OUTPUT;
$courseid = optional_param('courseid', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);

$pid = optional_param('pid', '', PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);
$cataction = optional_param('cataction', '', PARAM_ALPHA);
$catconfirm = optional_param('catconfirm', 0, PARAM_INT);
$delid = optional_param('delid', 0, PARAM_INT);
$editid = optional_param('editid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

block_exaport_setup_default_categories();

$url = '/blocks/exaport/view_categories.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
block_exaport_print_header("categories");

echo '<div class="block_eportfolio_center">';

echo "<br />";

echo $OUTPUT->box(text_to_html(get_string("explaincategories", "block_exaport")));

echo '</div>';

if ($cataction) {
    if ($catconfirm) {
        if (!confirm_sesskey()) {
            error('Bad Session Key');
        }
        $newentry = new stdClass();
        $newentry->name = $name;
        $newentry->timemodified = time();
        $newentry->course = $courseid;
        $message = '';
        switch ($cataction) {
            case "add":
                $newentry->userid = $USER->id;
                if ($pid > 0) {
                    $newentry->pid = $pid;
                } else {
                    $newentry->pid = 0;
                }

                if (!$newentry->id = $DB->insert_record("block_exaportcate", $newentry)) {
                    error("Could not insert this category");
                } else {
                    block_exaport_add_to_log($courseid, "bookmark", "add category", "", $newentry->id);
                    $message = get_string("categorysaved", "block_exaport");
                }
                break;
            case "edit":
                $conditions = array("id" => $editid, "userid" => $USER->id);
                if (($editid > 0) && ($editrecord = $DB->get_record("block_exaportcate", $conditions))) {
                    $newentry->id = $editid;
                    echo $OUTPUT->box_start("center", "40%", "#ccffbb");
                    ?>
                    <div class="block_eportfolio_center">
                    <form method="post"
                          action="<?php echo $CFG->wwwroot; ?>/blocks/exaport/view_categories.php?courseid=<?php echo $courseid; ?>&amp;edit=1">
                        <fieldset>
                            <input type="text" name="name" value="<?php echo s($editrecord->name) ?>"/>
                            <input type="hidden" name="pid" value="<?php echo $editrecord->pid == 0 ? "-1" : $editrecord->pid; ?>"/>
                            <input type="hidden" name="courseid" value="<?php p($courseid); ?>"/>
                            <input type="hidden" name="cataction" value="editconfirm"/>
                            <input type="submit" name="Submit" value="<?php echo get_string("change", "block_exaport") ?>"/>
                            <input type="hidden" name="catconfirm" value="1"/>
                            <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>"/>
                            <input type="hidden" name="editid" value="<?php echo $editrecord->id ?>"/>
                        </fieldset>
                    </form></div><?php
                    echo $OUTPUT->box_end();
                    echo block_exaport_wrapperdivend();
                    $OUTPUT->footer($course);
                    exit;
                } else {
                    error("Wrong id for edit");
                }
                break;
            case "editconfirm":
                $newentry->id = $editid;
                $newentry->userid = $USER->id;

                if ($pid > 0) {
                    $newentry->pid = $pid;
                } else {
                    $newentry->pid = 0;
                }
                $conditions = array("id" => $newentry->id, "userid" => $USER->id);
                if ($DB->count_records("block_exaportcate", $conditions) == 1) {
                    if (!$DB->update_record("block_exaportcate", $newentry)) {
                        error("Could not update your categories");
                    } else {
                        block_exaport_add_to_log($courseid, "bookmark", "update category", "", $newentry->id);
                        $message = get_string("categoryedited", "block_exaport");
                    }
                } else {
                    error("Wrong id for edit");
                }
                break;
            case "delete":
                if ($catconfirm == 1) {
                    $optionsyes = array('cataction' => 'delete', 'courseid' => $courseid, 'catconfirm' => 2, 'sesskey' => sesskey(),
                        'delid' => $delid, 'edit' => 1);
                    $optionsno = array('courseid' => $courseid, 'edit' => 1, 'sesskey' => sesskey());

                    $strbookmarks = get_string("myportfolio", "block_exaport");
                    $strcat = get_string("categories", "block_exaport");

                    echo '<br />';
                    echo $OUTPUT->confirm(get_string("deletecategoryconfirm", "block_exaport"),
                        new moodle_url('view_categories.php', $optionsyes), new moodle_url('view_categories.php', $optionsno));
                    echo block_exaport_wrapperdivend();
                    $OUTPUT->footer();
                    die;
                } else if ($catconfirm == 2) {
                    if ($delid > 0) {
                        $newentry->id = $delid;
                        $conditions = array("id" => $newentry->id, "userid" => $USER->id);
                        if (!$DB->delete_records('block_exaportcate', $conditions)) {
                            $message = "Could not delete your record";
                        } else {
                            $conditions = array("categoryid" => $delid);
                            if ($entries = $DB->get_records_select('block_exaportitem', null, $conditions, '', 'id')) {
                                foreach ($entries as $entry) {
                                    $DB->delete_records('block_exaportitemshar', array('itemid' => $entry->id));
                                }
                            }
                            $DB->delete_records('block_exaportitem', array('categoryid' => $delid));

                            block_exaport_add_to_log($courseid, "bookmark", "delete category", "", $newentry->id);
                            $message = get_string("categorydeleted", "block_exaport");
                        }
                    } else {
                        $message = "Wrong id for delete";
                    }
                }
                break;
        }
        echo $OUTPUT->box("<div class='block_eportfolio_center'>$message</div>");
    }
}

if ($edit == 1) {
    if (!confirm_sesskey()) {
        error('Bad Session Key');
    }
    echo '<div class="block_eportfolio_centerw">';
    echo '<table style="margin-left:auto;margin-right:auto;" border="0" cellspacing="5" cellpadding="5">';
    $conditions = array("userid" => $USER->id, "pid" => 0);
    $outercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
    if ($outercategories) {

        echo '<tr><td class="block_eportfolio_bold">' . get_string("maincategory", "block_exaport") .
            '</td><td class="block_eportfolio_bold">' . get_string("subcategory", "block_exaport") . '</td></tr>';
        echo '<tr>';
        rekedit($outercategories, $courseid, 1, 0);
    }
    echo '</tr>';
    echo '<tr>';
    echo '<td valign="top">';

    echo '<form method="post" action="' . $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $courseid . '&amp;edit=1">';
    echo '<fieldset>';
    echo '<input type="text" name="name" />';
    echo '<input type="hidden" name="pid" value="-1" />';
    echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
    echo '<input type="submit" name="Submit" value="' . get_string("new") . '" />';
    echo '<input type="hidden" name="cataction" value="add" />';
    echo '<input type="hidden" name="catconfirm" value="1" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '</fieldset>';
    echo '</form>';
    echo '</td>';
    echo '<td valign="top"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td valign="top" style="text-align:center" colspan="2"><form method="post" action="' . $CFG->wwwroot .
        '/blocks/exaport/view_categories.php?courseid=' . $courseid . '"><fieldset><input type="submit" name="submit" value="' .
        get_string("endedit", "block_exaport") . '" /><input type="hidden" name="sesskey" value="' . sesskey() .
        '" /></fieldset></form></td>';
    echo '</tr>';
    echo '</table></div>';
} else {
    echo '<div class="block_eportfolio_categories">';
    $conditions = array("userid" => $USER->id, "pid" => 0);
    $owncats = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name ASC");
    if ($owncats) {
        echo "<ul>";

        rekview($owncats);
        echo "</ul>";
    }

    echo '<div class="block_eportfolio_centerw">';

    echo '<form method="post" action="' . $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $courseid . '&amp;edit=1">';
    echo '<fieldset>';
    echo '<input type="submit" name="submit" value="' . get_string("edit") . '" />';
    echo '<input type="hidden" name="edit" value="1" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
    echo '</fieldset>';
    echo '</form>';
    echo '</div></div>';
}
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function rekedit($outercategories, $courseid, $first, $level) {
    global $USER, $DB, $CFG;
    $firstinner = 1;

    foreach ($outercategories as $curcategory) {
        if ($firstinner == 0 & $first != 1) {
            for ($i = 0; $i < $level; $i++) {
                echo '<td valign="top"></td>';
            }
        }
        $params = array('userid' => $USER->id, 'pid' => $curcategory->id);

        $countinnercategories = $DB->count_records_select("block_exaportcate", "userid = ? AND pid = ?", $params);
        $conditions = array("userid" => $USER->id, "pid" => $curcategory->id);
        $innercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");

        echo '<td valign="top">';
        echo format_string($curcategory->name);
        echo '<a href="' . $CFG->wwwroot . '/blocks/exaport/view_categories.php?cataction=edit&amp;sesskey=' . sesskey() .
            '&amp;catconfirm=1&amp;courseid=' . $courseid . '&amp;editid=' . $curcategory->id . '&amp;edit=1">' .
            '<img src="' . $CFG->wwwroot . '/pix/i/edit.gif" width="16" height="16" alt="' . get_string("edit") . '" /></a>';

        if ($countinnercategories == 0) {
            echo '<a href="' . $CFG->wwwroot . '/blocks/exaport/view_categories.php?cataction=delete&amp;sesskey=' . sesskey() .
                '&amp;catconfirm=1&amp;courseid=' . $courseid . '&amp;delid=' . $curcategory->id . '&amp;edit=1">' .
                '<img src="' . $CFG->wwwroot . '/pix/t/delete.gif" width="11" height="11" alt="' . get_string("delete") . '" /></a>';
        }
        echo '</td>';

        if ($innercategories) {
            rekedit($innercategories, $courseid, 0, $level + 1);
            for ($i = 0; $i <= $level; $i++) {
                echo '<td valign="top"></td>';
            }
        }
        echo '<td valign="top">';
        echo '<form method="post" action="' . $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $courseid . '&amp;edit=1">';
        echo '<fieldset>';
        echo '<input type="text" name="name" value ="Subkategorie von ' . $curcategory->name . '"/>';
        echo '<input type="hidden" name="pid" value="' . $curcategory->id . '" />';
        echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
        echo '<input type="hidden" name="cataction" value="add" />';
        echo '<input type="submit" name="Submit" value="' . get_string("new") . '" />';
        echo '<input type="hidden" name="catconfirm" value="1" />';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '</fieldset>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        $firstinner = 0;
    }

}

function rekview($owncats) {
    global $DB, $USER;
    foreach ($owncats as $owncat) {
        echo '<li>' . format_string($owncat->name);

        $conditions = array("userid" => $USER->id, "pid" => $owncat->id);
        $innerowncats = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name ASC");

        if ($innerowncats) {
            echo "<ul>";
            rekview($innerowncats);
            echo "</ul>";
        }
        echo "</li>";
    }
}
