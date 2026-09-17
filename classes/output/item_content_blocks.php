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

use context_user;
use renderable;
use renderer_base;
use templatable;

/**
 * Read-only content block rows for an Exaport item.
 */
class item_content_blocks implements renderable, templatable {

    /** @var array */
    private $blocks;

    /** @var \moodle_url|null */
    private $addurl;

    /** @var int */
    private $ownerid;

    /** @var bool */
    private $showaddbutton;

    /**
     * @param array $blocks Ordered item content block records.
     * @param \moodle_url|null $addurl URL for adding a text block.
     * @param int $ownerid User ID that owns the item content.
     * @param bool $showaddbutton Whether the visual add control should be shown.
     */
    public function __construct(array $blocks, $addurl, int $ownerid, bool $showaddbutton = true) {
        $this->blocks = $blocks;
        $this->addurl = $addurl;
        $this->ownerid = $ownerid;
        $this->showaddbutton = $showaddbutton;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];

        foreach ($this->blocks as $block) {
            $type = strtolower(trim((string)($block->type ?? '')));
            $typeinfo = $this->get_type_info($type);
            $typelabel = $typeinfo['label'];

            $rows[] = [
                'icon' => $output->pix_icon(
                    $typeinfo['icon'],
                    $typelabel,
                    'moodle',
                    ['class' => 'exaport-item-content-block-icon']
                ),
                'typelabel' => $typelabel,
                'title' => trim((string)($block->title ?? '')),
                'content' => $type === 'text' ? $this->format_content($block) : '',
                'preview' => $this->build_preview($block, $type),
            ];
        }

        return [
            'heading' => get_string('viewcontent', 'block_exaport'),
            'blocks' => $rows,
            'hasblocks' => !empty($rows),
            'texticon' => $output->pix_icon(
                'i/info',
                get_string('view_specialitem_text', 'block_exaport'),
                'moodle',
                ['class' => 'exaport-item-content-block-icon']
            ),
            'addicon' => $output->pix_icon('t/add', '', 'moodle', ['aria-hidden' => 'true']),
            'addlabel' => get_string('add', 'block_exaport') . ' ' .
                get_string('view_specialitem_text', 'block_exaport'),
            'addurl' => $this->addurl instanceof \moodle_url ? $this->addurl->out(false) : '',
            'showaddbutton' => $this->showaddbutton,
        ];
    }

    /**
     * @param string $type
     * @return array
     */
    private function get_type_info(string $type): array {
        switch ($type) {
            case 'text':
                return [
                    'icon' => 'i/info',
                    'label' => get_string('view_specialitem_text', 'block_exaport'),
                ];
            case 'link':
                return [
                    'icon' => 'i/url',
                    'label' => get_string('link', 'block_exaport'),
                ];
            case 'file':
                return [
                    'icon' => 'i/file',
                    'label' => get_string('file', 'block_exaport'),
                ];
            case 'media':
                return [
                    'icon' => 'i/file',
                    'label' => get_string('view_specialitem_media', 'block_exaport'),
                ];
            default:
                return [
                    'icon' => 'i/info',
                    'label' => $type !== '' ? $type : get_string('viewcontent', 'block_exaport'),
                ];
        }
    }

    /**
     * Build a short plain-text preview without adding file or pluginfile handling.
     *
     * @param \stdClass $block
     * @param string $type
     * @return string
     */
    private function build_preview(\stdClass $block, string $type): string {
        $title = trim((string)($block->title ?? ''));
        $content = trim((string)($block->content ?? ''));
        $url = trim((string)($block->url ?? ''));

        if ($type === 'text') {
            return $content === '' && $title === '' ? get_string('noentry', 'block_exaport') : '';
        }

        if ($type === 'link') {
            $values = [$url !== '' ? $url : $content];
        } else {
            $values = [$content, $url];
        }

        $values = array_filter($values, function($value) {
            return $value !== '';
        });
        $preview = trim(strip_tags(implode(' · ', $values)));

        if ($preview === '' && $title === '') {
            return get_string('noentry', 'block_exaport');
        }

        return $preview === '' ? '' : shorten_text($preview, 160, true);
    }

    /**
     * Format a text block using its stored Moodle format.
     *
     * @param \stdClass $block
     * @return string
     */
    private function format_content(\stdClass $block): string {
        $content = trim((string)($block->content ?? ''));
        if ($content === '') {
            return '';
        }

        $contentformat = isset($block->contentformat) ? (int)$block->contentformat : FORMAT_HTML;
        $ownercontext = context_user::instance($this->ownerid);
        $content = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            $ownercontext->id,
            'block_exaport',
            'item_content_text',
            $block->id
        );

        return format_text($content, $contentformat, [
            'context' => $ownercontext,
        ]);
    }
}
