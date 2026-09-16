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
 * Read-only structured item content area.
 */
class item_content_area implements renderable, templatable {
    /** @var \stdClass */
    protected $item;
    /** @var string */
    protected $access;
    /** @var array|null */
    protected $blocks;
    /** @var array */
    protected $options;

    /**
     * Constructor.
     *
     * @param \stdClass $item
     * @param string $access
     * @param array|null $blocks
     */
    public function __construct(\stdClass $item, string $access, ?array $blocks = null, array $options = []) {
        $this->item = $item;
        $this->access = $access;
        $this->blocks = $blocks;
        $this->options = $options;
    }

    /**
     * Export template data.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $blocks = $this->blocks ?? \block_exaport\item_content_helper::export_item_blocks($this->item, $this->access, $this->options);
        $canmanage = !empty($this->options['canmanage']);

        return [
            'hasblocks' => !empty($blocks),
            'blocks' => $blocks,
            'heading' => get_string('itemcontentarea', 'block_exaport'),
            'emptytitle' => get_string('itemcontentemptytitle', 'block_exaport'),
            'emptytext' => get_string('itemcontentempty', 'block_exaport'),
            'canmanage' => $canmanage,
            'hasaddactions' => $canmanage,
            'addactions' => $canmanage
                ? \block_exaport\item_content_mutation_helper::get_add_action_links(
                    $this->item,
                    $this->access,
                    (string)($this->options['backtype'] ?? '')
                )
                : [],
        ];
    }
}
