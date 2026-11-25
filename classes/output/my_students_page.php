<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

use core_collator;
use moodle_url;
use renderable;
use renderer_base;
use single_select;
use templatable;

/**
 * Renders the students whose portfolios a trusted teacher may browse.
 *
 * @package block_exaport
 * @copyright 2026 GTN - Global Training Network GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_students_page implements renderable, templatable {

    /** @var array Students returned by block_exaport_get_students_for_teacher(). */
    protected array $students;

    /** @var int Current course context for portfolio links and roster grouping. */
    protected int $courseid;

    /**
     * Constructor.
     *
     * @param array $students Legacy roster records, including courseids and courses.
     * @param int $courseid Current course ID.
     */
    public function __construct(array $students, int $courseid) {
        $this->students = $students;
        $this->courseid = $courseid;
    }

    /**
     * Export each student once, prioritising students related to the current course.
     *
     * @param renderer_base $output Renderer used by the template system.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $courses = enrol_get_my_courses(null, 'fullname ASC');
        $coursepicker = '';
        if ($courses) {
            $options = [SITEID => s(get_string('fulllistofcourses'))];
            foreach ($courses as $course) {
                $name = html_entity_decode(strip_tags(format_string($course->fullname)), ENT_QUOTES, 'UTF-8');
                $options[$course->id] = s($name);
            }
            $url = new moodle_url('/blocks/exaport/shared_with_me.php', ['mode' => 'students']);
            $selectedcourse = isset($options[$this->courseid]) ? $this->courseid : SITEID;
            $picker = new single_select($url, 'courseid', $options, $selectedcourse, null);
            $picker->set_label(s(get_string('course')));
            $picker->attributes['id'] = 'block-exaport-students-course';
            $coursepicker = $output->render($picker);
        }

        $groups = ['thiscourse' => [], 'othercourses' => []];
        foreach ($this->students as $student) {
            $courseids = array_map('intval', $student->courseids);
            $coursenames = array_map(static function(string $name): string {
                return html_entity_decode(strip_tags(format_string($name)), ENT_QUOTES, 'UTF-8');
            }, $student->courses);
            $group = in_array($this->courseid, $courseids, true) ? 'thiscourse' : 'othercourses';
            $groups[$group][] = [
                'id' => (int)$student->id,
                'name' => fullname($student),
                'courses' => implode(', ', $coursenames),
                'url' => (new moodle_url('/blocks/exaport/view_items.php', [
                    'courseid' => $this->courseid,
                    'type' => 'sharedstudent',
                    'userid' => (int)$student->id,
                ]))->out(false),
            ];
        }

        $exportedgroups = [];
        foreach ($groups as $key => $students) {
            if (!$students) {
                continue;
            }
            core_collator::asort_array_of_arrays_by_key($students, 'name');
            $exportedgroups[] = [
                'key' => $key,
                'label' => get_string($key, 'block_exaport'),
                'students' => array_values($students),
            ];
        }

        return [
            'coursepicker' => $coursepicker,
            'hasstudents' => (bool)$exportedgroups,
            'groups' => $exportedgroups,
            'emptytext' => get_string('nousersfound'),
            'headeruser' => get_string('user'),
            'headercourses' => get_string('courses'),
            'headeractions' => get_string('actions'),
            'browselabel' => get_string('browseportfolio', 'block_exaport'),
        ];
    }
}
