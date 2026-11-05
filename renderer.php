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

defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . '/inc.php');

class block_exaport_renderer extends plugin_renderer_base {

    /**
     * Render the shared layout chooser for views and course view templates.
     *
     * @param int $selected Selected layout ID
     * @return string HTML for the ten existing layouts
     */
    public function view_layout_selector(int $selected): string {
        $groups = [
            1 => [1 => '100'],
            2 => [2 => '50-50', 3 => '67-33', 4 => '33-67'],
            3 => [5 => '33-33-33', 6 => '25-50-25', 7 => '15-70-15'],
            4 => [8 => '25-25-25-25', 9 => '20-30-30-20'],
            5 => [10 => '20-20-20-20-20'],
        ];
        $html = html_writer::tag('p', get_string('chooselayout', 'block_exaport'));
        $html .= html_writer::start_div('select_layout clearfix');
        foreach ($groups as $columns => $layouts) {
            $groupname = get_string('viewlayoutgroup' . $columns, 'block_exaport');
            $html .= html_writer::empty_tag('hr', ['class' => 'cb']);
            $html .= html_writer::div(html_writer::tag('strong', $groupname), 'fl columnoption');
            foreach ($layouts as $id => $widths) {
                $description = get_string('viewlayout' . $id, 'block_exaport');
                $inputid = 'view-layout-' . $id;
                $attributes = ['type' => 'radio', 'class' => 'radio', 'name' => 'layout', 'value' => $id,
                    'id' => $inputid, 'aria-label' => $groupname . ' – ' . $description];
                if ($selected === $id) {
                    $attributes['checked'] = 'checked';
                }
                $html .= html_writer::start_div('fl layoutoptions');
                $html .= html_writer::div(html_writer::empty_tag('input', $attributes), 'radiobutton');
                $image = html_writer::empty_tag('img', ['src' => $this->image_url('vl-' . $widths, 'block_exaport'), 'alt' => '']);
                $html .= html_writer::div(html_writer::label($image, $inputid), 'layoutimg');
                $html .= html_writer::div(html_writer::label($description, $inputid), 'layoutdescription');
                $html .= html_writer::end_div();
            }
        }
        return $html . html_writer::end_div();
    }

    /**
     * in moodle33 pix_url was renamed to image_url
     */
    public function image_url($imagename, $component = 'moodle') {
        if (method_exists(get_parent_class($this), 'image_url')) {
            // return call_user_func_array(['parent::class', 'image_url'], func_get_args());
            return parent::image_url($imagename, $component);
        } else {
            // return call_user_func_array(['parent::class', 'pix_url'], func_get_args());
            return parent::pix_url($imagename, $component);
        }
    }


    public function get_theme_dir() {
        return $this->get_theme_config()->dir;
    }

    public function get_theme_config() {
        return $this->page->theme;
    }

}

