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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

class view_category_helper {
    /**
     * Synchronise the category assignments for a view.
     *
     * Only category ids that are owned by the same user as the view are
     * accepted; any other ids are silently dropped. The operation is wrapped
     * in a Moodle DB transaction so a partial failure cannot leave the view
     * with its categories wiped.
     *
     * @param int   $viewid      ID of the view to update.
     * @param int[] $categoryids Desired set of category ids (may contain
     *                           duplicates; deduplication is applied internally).
     * @return void  Returns early without making any changes if $viewid does not exist.
     */
    public static function sync_view_categories($viewid, array $categoryids) {
        global $DB;

        $view = $DB->get_record('block_exaportview', ['id' => (int)$viewid]);
        if (!$view) {
            return;
        }

        // Deduplicate and cast to int.
        $categoryids = array_values(array_unique(array_map('intval', $categoryids)));

        // Filter to categories owned by the view's owner.
        if (!empty($categoryids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
            $inparams['ownerid'] = (int)$view->userid;
            $ownedids = $DB->get_fieldset_select(
                'block_exaportcate',
                'id',
                "userid = :ownerid AND id $insql",
                $inparams
            );
            $categoryids = array_map('intval', $ownedids);
        }

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('block_exaportviewcate', ['viewid' => (int)$viewid]);
        foreach ($categoryids as $categoryid) {
            $DB->insert_record('block_exaportviewcate', (object)[
                'viewid' => (int)$viewid,
                'cateid' => (int)$categoryid,
            ]);
        }

        $transaction->allow_commit();
    }
}
