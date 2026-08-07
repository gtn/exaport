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
 * Renders block_exaport/shared_with_me.
 */
class shared_with_me_page implements renderable, templatable {

    /** @var array $rows */
    protected $rows;

    /** @var int $courseid */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param array $rows     Rows returned by \block_exaport\share_overview::get_shared_with_me().
     * @param int   $courseid The current course id.
     */
    public function __construct(array $rows, int $courseid) {
        $this->rows     = $rows;
        $this->courseid = $courseid;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $exportedrows = [];
        foreach ($this->rows as $row) {
            $owner = $DB->get_record('user', ['id' => $row->owner_userid]);
            $coursename = '';
            if (!empty($row->courseid)) {
                $course = $DB->get_record('course', ['id' => $row->courseid], 'id, shortname, fullname');
                $coursename = $course ? format_string($course->fullname) : '';
            }

            $exportedrows[] = [
                'typelabel'     => get_string($row->entity_type, 'block_exaport'),
                'title'         => format_string($row->title),
                'url'           => $this->build_url($row),
                'ownername'     => $owner ? fullname($owner) : '',
                'coursename'    => $coursename,
                'sharemodetext' => $row->share_mode === 'group'
                    ? get_string('sharedwith_group', 'block_exaport')
                    : get_string('sharedwith_onlyme', 'block_exaport'),
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
        ];
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
