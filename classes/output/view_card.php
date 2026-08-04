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

use renderer_base;

/**
 * Output class for the view (collection) card (Bootstrap layout).
 *
 * Rendered as a stacked-paper card via the .card-view CSS modifier class.
 * Parallels item_card but for portfolio views.
 * Renders block_exaport/view_card.
 */
class view_card extends card {

    /** @var \stdClass $view */
    protected $view;

    /** @var bool $showcategories */
    protected bool $showcategories;

    /** @var int $categoryid */
    protected $categoryid;

    /**
     * Constructor.
     *
     * @param \stdClass $view           The view record (decorated with flatcategories, share_* fields).
     * @param int       $courseid       The course id.
     * @param string    $type           Access type, e.g. 'mine'.
     * @param int       $categoryid     The current category id (used for edit/delete URL context).
     * @param bool      $showcategories Whether to show category badge chips.
     */
    public function __construct(\stdClass $view, int $courseid, string $type, int $categoryid = 0,
                                bool $showcategories = false) {
        parent::__construct($courseid, $type);
        $this->view           = $view;
        $this->categoryid     = $categoryid;
        $this->showcategories = $showcategories;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER;

        $view     = $this->view;
        $courseid = $this->courseid;

        // The shared view URL used in views_list.php.
        $url = $CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $courseid
               . '&access=id/' . $view->userid . '-' . $view->id;

        $editurl   = $CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $courseid
                     . '&id=' . $view->id . '&sesskey=' . sesskey() . '&action=edit';
        $deleteurl = $CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $courseid
                     . '&id=' . $view->id . '&sesskey=' . sesskey() . '&action=delete&confirm=1';

        // Build category IDs for client-side filtering (same as item_card).
        $catids = [];
        if (!empty($view->flatcategories) && is_array($view->flatcategories)) {
            foreach ($view->flatcategories as $cat) {
                $catids[] = (int)$cat->id;
            }
        }

        $isownview = ((int)$view->userid === (int)$USER->id);

        // Share state.
        $isshared = !empty($view->share_all)
                 || !empty($view->share_users)
                 || !empty($view->share_groups)
                 || !empty($view->share_external);
        $sharedtooltip = $isshared ? block_exaport_get_view_share_tooltip($view) : '';

        $collectionicon = block_exaport_fontawesome_icon('layer-group', 'solid', 1, ['icon', 'fa-fw', 'me-1'], [],
            ['data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top',
             'data-bs-title'  => get_string('view', 'block_exaport')]);

        $sharedicon = block_exaport_fontawesome_icon(
            'handshake',
            'regular',
            1,
            ['icon', 'icon-shared'],
            [],
            [
                'data-bs-toggle'    => 'tooltip',
                'data-bs-placement' => 'top',
                'data-bs-title'     => s($sharedtooltip),
            ]
        );

        $data = $this->base_icons() + [
            'viewnamelower'  => strtolower($view->name),
            'catids'         => implode(',', $catids),
            'timemodified'   => (int)$view->timemodified,
            'viewid'         => (int)$view->id,
            'url'            => $url,
            'viewname'       => $view->name,
            'isownview'      => $isownview,
            'editurl'        => $editurl,
            'deleteurl'      => $deleteurl,
            'canedit'        => $isownview,
            'candelete'      => $isownview,
            'dateformatted'  => date('d.m.Y H:i', $view->timemodified),
            'collectionicon' => $collectionicon,
            'isshared'       => $isshared,
            'sharedicon'     => $sharedicon,
            'sharedtooltip'  => $sharedtooltip,
        ];

        if ($this->showcategories) {
            $data['categorybadges'] = block_exaport_render_item_category_badges($this->view);
        }

        return $data;
    }
}
