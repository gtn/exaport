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

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

/**
 * Output class for the "Shared with me" overview page (shared_with_me.php).
 * Renders block_exaport/shared_with_me_page.
 */
class shared_with_me_page implements renderable, templatable {

    /** @var array $rows */
    protected $rows;

    /** @var int $courseid */
    protected $courseid;

    /** @var string $searchcontrols */
    protected $searchcontrols;

    /**
     * Constructor.
     *
     * @param array  $rows           Rows returned by \block_exaport\share_overview::get_shared_with_me().
     * @param int    $courseid       The current course id.
     * @param string $searchcontrols Pre-rendered HTML for the search/sort controls bar (optional).
     */
    public function __construct(array $rows, int $courseid, string $searchcontrols = '') {
        $this->rows           = $rows;
        $this->courseid       = $courseid;
        $this->searchcontrols = $searchcontrols;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $ownerids = array_unique(array_map(function($row) {
            return $row->owner_userid;
        }, $this->rows));
        $owners = $ownerids ? $DB->get_records_list('user', 'id', $ownerids) : [];

        $courseids = array_unique(array_filter(array_map(function($row) {
            return $row->courseid;
        }, $this->rows)));
        $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, shortname, fullname') : [];

        $exportedrows = [];
        foreach ($this->rows as $row) {
            $owner = $owners[$row->owner_userid] ?? null;
            $coursename = !empty($row->courseid) && isset($courses[$row->courseid])
                ? format_string($courses[$row->courseid]->fullname)
                : '';

            $exportedrows[] = [
                'typeicon'        => $this->build_type_icon($row),
                'typelabel'       => get_string($row->entity_type, 'block_exaport'),
                'title'           => format_string($row->title),
                'titlenamelower'  => core_text::strtolower(strip_tags(format_string($row->title))),
                'url'             => $this->build_url($row),
                'ownername'       => $owner ? fullname($owner) : '',
                'coursename'      => $coursename,
                'sharemodetext'   => $this->build_share_mode_text($row),
                'commentcount'    => (int)($row->comment_cnt ?? 0),
                'hascomments'     => (int)($row->comment_cnt ?? 0) > 0,
            ];
        }

        return [
            'hasrows'          => (bool)$exportedrows,
            'rows'             => $exportedrows,
            'nothingtext'      => get_string('nothingshared', 'block_exaport'),
            'headertitle'      => get_string('title', 'block_exaport'),
            'headertype'       => get_string('type', 'block_exaport'),
            'headeruser'       => get_string('user'),
            'headercourse'     => get_string('course', 'block_exaport'),
            'headersharedwith' => get_string('sharedwith', 'block_exaport'),
            'headercomments'   => get_string('comments', 'block_exaport'),
            'searchcontrols'   => $this->searchcontrols,
        ];
    }

    /**
     * Build the type icon HTML for a row.
     *
     * Delegates to \block_exaport\share_overview::build_type_icon() to avoid
     * duplicating the icon-selection logic between my_shares_page and shared_with_me_page.
     *
     * @param \stdClass $row
     * @return string HTML
     */
    protected function build_type_icon(\stdClass $row): string {
        return \block_exaport\share_overview::build_type_icon($row->entity_type, $row->type ?? '');
    }

    /**
     * Build the human-readable "shared with" / share mode text shown in the last column.
     *
     * For a row that was shared directly (share_mode = 'user') this describes the owner's
     * share scope; for a group share it notes the group/cohort origin.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_share_mode_text(\stdClass $row): string {
        if ($row->share_mode === 'group') {
            return get_string('sharedwith_group', 'block_exaport');
        }
        return get_string('sharedwith_onlyme', 'block_exaport');
    }

    /**
     * Build a link to view the shared entity.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_url(\stdClass $row): string {
        global $CFG;

        switch ($row->entity_type) {
            case 'item':
                return $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $this->courseid .
                    '&itemid=' . $row->id . '&access=portfolio/id/' . $row->owner_userid;
            case 'category':
                return $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $this->courseid .
                    '&type=shared&userid=' . $row->owner_userid . '&categoryid=' . $row->id;
            case 'view':
                return $CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $this->courseid .
                    '&access=id/' . $row->owner_userid . '-' . $row->id;
            default:
                return '#';
        }
    }
}
