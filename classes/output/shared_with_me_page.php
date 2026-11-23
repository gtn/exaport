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

use core_collator;
use core_text;
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
     * @param string $searchcontrols Pre-rendered HTML for the filter bar (optional).
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
        global $CFG, $DB;

        $ownerids = array_unique(array_column($this->rows, 'owner_userid'));
        $owners = $ownerids ? $DB->get_records_list('user', 'id', $ownerids) : [];
        $senders = [];
        foreach ($owners as $owner) {
            $senders[$owner->id] = ['id' => (int)$owner->id, 'name' => fullname($owner)];
        }
        core_collator::asort_array_of_arrays_by_key($senders, 'name');

        $courseids = array_diff(array_unique(array_filter(array_column($this->rows, 'courseid'))), [SITEID]);
        $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname') : [];
        $unspecifiedcourse = get_string('share_course_unspecified', 'block_exaport');

        $copyfields = empty($CFG->block_exaport_copy_category_to_my) ? [] : [
            'url' => (new \moodle_url('/blocks/exaport/shared_with_me.php'))->out(false),
            'sesskey' => sesskey(),
            'courseid' => $this->courseid,
        ];
        $hascopyactions = false;
        $dateformat = get_string('strftimedate', 'langconfig');
        $exportedrows = [];
        foreach ($this->rows as $row) {
            $ownerid = (int)$row->owner_userid;
            $share = $row->shareinfo ??
                \block_exaport\share_overview::build_share_info($row->entity_type, (int)$row->id, $row);
            $coursename = isset($courses[$row->courseid])
                ? format_string($courses[$row->courseid]->fullname)
                : $unspecifiedcourse;
            $title = format_string($row->title);
            $timemodified = (int)($row->timemodified ?? 0);
            $copyform = $copyfields && $row->entity_type === 'category'
                ? $copyfields + ['categoryid' => (int)$row->id] : null;
            $hascopyactions = $hascopyactions || $copyform !== null;

            $exportedrows[] = [
                'typeicon'        => $this->build_type_icon($row),
                'typelabel'       => get_string($row->entity_type, 'block_exaport'),
                'title'           => $title,
                'titlenamelower'  => core_text::strtolower(html_entity_decode(strip_tags($title))),
                'entitytype'      => $row->entity_type,
                'entityid'        => (int)$row->id,
                'timemodified'    => $timemodified,
                'updateddate'     => $timemodified ? userdate($timemodified, $dateformat) : '',
                'url'             => $this->build_url($row),
                'ownerid'         => $ownerid,
                'ownername'       => $senders[$ownerid]['name'] ?? '',
                'coursename'      => $coursename,
                'sharemodetext'   => block_exaport_get_share_summary($share),
                'sharetooltip'    => block_exaport_get_share_tooltip($share),
                'hastooltip'      => $share->is_shared(),
                'commentcount'    => (int)($row->comment_cnt ?? 0),
                'hascomments'     => (int)($row->comment_cnt ?? 0) > 0,
                'copyform'        => $copyform,
            ];
        }

        return [
            'hasrows'          => (bool)$exportedrows,
            'rows'             => $exportedrows,
            'hassenders'       => (bool)$senders,
            'senders'          => array_values($senders),
            'groupbysender'    => (bool)get_user_preferences('block_exaport_group_by_sender', 0),
            'hascopyactions'   => $hascopyactions,
            'copycategory'     => $hascopyactions ? get_string('copycategory', 'block_exaport') : '',
            'nothingtext'      => get_string('nothingsharedwithme', 'block_exaport'),
            'columns'          => [
                ['sortfield' => 'name', 'label' => get_string('title', 'block_exaport')],
                ['sortfield' => 'type', 'label' => get_string('type', 'block_exaport')],
                ['sortfield' => 'sender', 'label' => get_string('share_filter_shared_by', 'block_exaport')],
                ['sortfield' => 'course', 'label' => get_string('course', 'block_exaport')],
                ['sortfield' => 'date', 'label' => get_string('share_filter_updated', 'block_exaport')],
                ['sortfield' => 'sharedwith', 'label' => get_string('sharedwith', 'block_exaport')],
                ['sortfield' => 'comments', 'label' => get_string('comments', 'block_exaport')],
            ],
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
