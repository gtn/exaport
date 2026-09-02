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

use core_text;
use renderable;
use renderer_base;
use templatable;

/**
 * Output class for the "My shares" overview page (my_shares.php).
 * Renders block_exaport/my_shares_page.
 */
class my_shares_page implements renderable, templatable {

    /** @var array $rows */
    protected $rows;

    /** @var int $courseid */
    protected $courseid;

    /** @var string $searchcontrols */
    protected $searchcontrols;

    /**
     * Constructor.
     *
     * @param array  $rows           Rows returned by \block_exaport\share_overview::get_my_shares().
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
        $exportedrows = [];
        foreach ($this->rows as $row) {
            $share = $row->shareinfo ??
                \block_exaport\share_overview::build_share_info($row->entity_type, (int)$row->id, $row);
            $tooltip = block_exaport_get_share_tooltip($share);
            $exportedrows[] = [
                'typeicon'       => $this->build_type_icon($row),
                'typelabel'      => get_string($row->entity_type, 'block_exaport'),
                'title'          => format_string($row->title),
                'titlenamelower' => core_text::strtolower(strip_tags(format_string($row->title))),
                'editurl'        => $this->build_edit_url($row),
                'shareurl'       => $this->build_share_url($row),
                'sharedwithtext' => block_exaport_get_share_summary($share),
                'sharetooltip'   => $tooltip,
                'hastooltip'     => $share->is_shared(),
                'commentcount'   => (int)($row->comment_cnt ?? 0),
                'ellipsisicon'   => block_exaport_fontawesome_icon('ellipsis-vertical', 'solid', 1),
                'editicon'       => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
                'shareicon'      => block_exaport_fontawesome_icon('share-nodes', 'solid', 1),
                'deleteicon'     => block_exaport_fontawesome_icon('trash-can', 'regular', 1),
                'editlabel'      => get_string('edit'),
                'sharelabel'     => get_string('share', 'block_exaport'),
                'deletelabel'    => get_string('delete'),
            ];
        }

        return [
            'hasrows'        => (bool)$exportedrows,
            'rows'           => $exportedrows,
            'nothingtext'    => get_string('nothingsharedbyme', 'block_exaport'),
            'headertitle'    => get_string('title', 'block_exaport'),
            'headertype'     => get_string('type', 'block_exaport'),
            'headershared'   => get_string('sharedwith', 'block_exaport'),
            'headercomments' => get_string('comments', 'block_exaport'),
            'headeractions'  => '',
            'searchcontrols' => $this->searchcontrols,
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
     * Build the edit URL for a row.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_edit_url(\stdClass $row): string {
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
                    '&id=' . $row->id . '&action=edit';
            default:
                return '#';
        }
    }

    /**
     * Build the share/settings URL for a row.
     *
     * For views this links directly to the share tab; for items and categories it links
     * to the edit page which contains the sharing form.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function build_share_url(\stdClass $row): string {
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

}
