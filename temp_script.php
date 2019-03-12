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
set_time_limit(3600);
require_once(__DIR__.'/inc.php');

$context = context_system::instance();
$PAGE->set_context($context);
require_login(0, true);


$url = '/blocks/exaport/temp_script.php';
$PAGE->set_url($url);

echo block_exaport_wrapperdivstart();

echo "<div>\n";

echo '!!!!! delete this script !!!!!';
$fs = get_file_storage();

// make a copy of user
$originUserId = 48534;
//unset($cloneUsr->id);
$i = 4000;
$newusersLimit = 5000;

for ($k = 3; $k<=11275; $k++) {
    $r = new stdClass();
    $r->catid = $k;
    $r->userid = 23105;
    $newRecId = $DB->insert_record('block_exaportcatshar', $r);
}
exit;


while($i <= $newusersLimit) {
    unset($cloneUsr);
    $cloneUsr = $DB->get_record('user', ['id' => $originUserId]);

    // insert new user
    $cloneUsr->username = $cloneUsr->username.' - '.$i;
    // delete existing similar user
    $DB->delete_records('user', ['username' => $cloneUsr->username]);
    $cloneUsr->firstname = $cloneUsr->firstname.' - '.$i;
    $cloneUsr->lastname = $cloneUsr->lastname.' - '.$i;
    $newUserId = $DB->insert_record('user', $cloneUsr);
    $newContextUserId = context_user::instance($newUserId)->id;

    // CATEGORIES
    $oldCatToNew = array();
    $records = $DB->get_records('block_exaportcate', ['userid' => $originUserId]);
    foreach ($records as $record) {
        $record->userid = $newUserId;
        $newCatId = $DB->insert_record('block_exaportcate', $record);
        $oldCatToNew[$record->id] = $newCatId;
    }
    // change parents
    $records = $DB->get_records('block_exaportcate', ['userid' => $newUserId]);
    foreach ($records as $record) {
        if ($record->pid > 0 && array_key_exists($record->pid, $oldCatToNew)) {
            $DB->execute('UPDATE {block_exaportcate} SET pid = ? WHERE id = ?', [$oldCatToNew[$record->pid], $record->id]);
        }
    }

    // ITEMS
    $records = $DB->get_records('block_exaportitem', ['userid' => $originUserId]);
    $oldItemToNew = array();
    foreach ($records as $record) {
        $record->userid = $newUserId;
        $newRecId = $DB->insert_record('block_exaportitem', $record);
        $oldItemToNew[$record->id] = $newRecId;
    }
    // + files
    foreach ($oldItemToNew as $olditemid => $newitemid) {
        $files = $DB->get_records('files', ['component' => 'block_exaport', 'filearea' => 'item_file', 'itemid' => $olditemid]);
        foreach ($files as $file) {
            // for using API
            $fileEx = $fs->get_file($file->contextid, "block_exaport", "item_file", $olditemid, $file->filepath, $file->filename);
            if ($fileEx) {
                $fs->create_file_from_storedfile(array(
                        'contextid' => $newContextUserId,
                        'component' => 'block_exaport',
                        'filearea' => 'item_file',
                        'itemid' => $newitemid,
                        'userid' => $newUserId,
                ), $fileEx);
            }
            //$file->itemid = $newitemid;
            //$file->userid = $newUserId;
            //$newfile = $DB->insert_record('files', $file);
        }
    }

    // VIEWS
    $records = $DB->get_records('block_exaportview', ['userid' => $originUserId]);
    $oldViewToNew = array();
    foreach ($records as $record) {
        $record->userid = $newUserId;
        $record->hash = substr(md5(uniqid(rand(), true)), 3, 8);
        $newRecId = $DB->insert_record('block_exaportview', $record);
        $oldViewToNew[$record->id] = $newRecId;
    }

    // VIEW BLOCKS
    $records = $DB->get_records('block_exaportviewblock');
    foreach ($records as $record) {
        if (array_key_exists($record->viewid, $oldViewToNew)) {
            $record->viewid = $oldViewToNew[$record->viewid];
            $record->itemid = $oldItemToNew[$record->itemid];
            $newRecId = $DB->insert_record('block_exaportviewblock', $record);
        }
    }

    // SHARE VIEW TO USER
    $shareToUserId = 23105;
    foreach ($oldViewToNew as $vid) {
        $record = new stdClass();
        $record->viewid = $vid;
        $record->userid = $shareToUserId;
        $newRecId = $DB->insert_record('block_exaportviewshar', $record);
    }
    $i++;
}

echo "</div>";
echo block_exaport_wrapperdivend();

//echo $OUTPUT->footer();


