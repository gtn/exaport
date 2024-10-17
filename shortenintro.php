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

$entrys = $DB->get_records('block_exaportitem');

foreach ($entrys as $entry) {
    if (strlen($entry->intro) > 1200) {
        $update = new stdClass();
        $update->id = $entry->id;

        $update->intro = substr($entry->intro, 0, 1200);

        $DB->update_record('block_exaportitem', $update);

        echo 'Item intro from item with id ' . $entry->id . ' shortened';
    }
    if (strlen($entry->beispiel_angabe) > 1000) {
        $update = new stdClass();
        $update->id = $entry->id;

        $update->beispiel_angabe = substr($entry->beispiel_angabe, 0, 1300);

        $DB->update_record('block_exaportitem', $update);

        echo 'Item beispiel_angabe from item with id ' . $entry->id . ' shortened';
    }
}
