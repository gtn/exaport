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
 * Output class for the "My shares" overview page (my_shares.php).
 * Renders block_exaport/my_shares.
 */
class my_shares_page implements renderable, templatable {

    /** @var array $rows */
    protected $rows;

    /** @var int $courseid */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param array $rows     Rows returned by \block_exaport\share_overview::get_my_shares().
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
        global $CFG;

        $exportedrows = [];
        foreach ($this->rows as $row) {
            $exportedrows[] = [
                'typelabel'      => get_string($row->entity_type, 'block_exaport'),
                'title'          => format_string($row->title),
                'url'            => $this->build_url($row),
                'sharedwithtext' => $this->build_shared_with_text($row),
                'commentcount'   => (int)($row->comment_cnt ?? 0),
            ];
        }

        return [
            'hasrows'        => (bool)$exportedrows,
            'rows'           => $exportedrows,
            'nothingtext'    => get_string('nothingshared', 'block_exaport'),
            'headertitle'    => get_string('title', 'block_exaport'),
            'headertype'     => get_string('type', 'block_exaport'),
            'headershared'   => get_string('sharedwith', 'block_exaport'),
            'headercomments' => get_string('comments', 'block_exaport'),
        ];
    }

    /**
     * Build a link to the entity's own edit/detail page.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_url(\stdClass $row): string {
        global $CFG;

        switch ($row->entity_type) {
            case 'item':
                return $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $this->courseid .
                    '&id=' . $row->id . '&action=edit';
            case 'category':
                return $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $this->courseid .
                    '&id=' . $row->id . '&action=edit';
            case 'view':
                return $CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $this->courseid .
                    '&id=' . $row->id . '&type=share&action=edit';
            default:
                return '#';
        }
    }

    /**
     * Build the human-readable "shared with" summary text for a row.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_shared_with_text(\stdClass $row): string {
        if (!empty($row->shareall)) {
            return get_string('sharedwith_shareall', 'block_exaport');
        }
        if (!empty($row->cnt_shared_groups)) {
            return (int)$row->cnt_shared_groups > 1
                ? get_string('sharedwith_group_cnt', 'block_exaport', (int)$row->cnt_shared_groups)
                : get_string('sharedwith_group', 'block_exaport');
        }
        if (!empty($row->cnt_shared_users)) {
            return (int)$row->cnt_shared_users > 1
                ? get_string('sharedwith_user_cnt', 'block_exaport', (int)$row->cnt_shared_users)
                : get_string('sharedwith_user', 'block_exaport');
        }
        if (!empty($row->externaccess)) {
            return get_string('externalaccess', 'block_exaport');
        }

        return '';
    }
}
