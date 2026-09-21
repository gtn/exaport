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
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Read-only content block rows for an Exaport item.
 */
class item_content_blocks implements renderable, templatable {

    /** @var array */
    private $blocks;

    /** @var moodle_url[]|moodle_url|null */
    private $addurl;

    /** @var int */
    private $ownerid;

    /** @var bool */
    private $showaddbutton;

    /** @var bool */
    private $showheading;

    /** @var string|null */
    private $access;

    /**
     * @param array $blocks Ordered item content block records.
     * @param moodle_url[]|moodle_url|null $addurl URLs for adding supported blocks.
     * @param int $ownerid User ID that owns the item content.
     * @param bool $showaddbutton Whether the visual add control should be shown.
     * @param bool $showheading Whether the section should render its own heading.
     * @param string|null $access Optional Exaport access path used in pluginfile URLs.
     */
    public function __construct(
        array $blocks,
        $addurl,
        int $ownerid,
        bool $showaddbutton = true,
        bool $showheading = true,
        ?string $access = null
    ) {
        $this->blocks = $blocks;
        $this->addurl = $addurl;
        $this->ownerid = $ownerid;
        $this->showaddbutton = $showaddbutton;
        $this->showheading = $showheading;
        $this->access = $access;
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

            $row = [
                'icon' => $this->get_type_icon($output, $typeinfo, $typelabel),
                'typelabel' => $typelabel,
                'title' => trim((string)($block->title ?? '')),
                'content' => $type === 'text' ? $this->format_content($block) : '',
                'preview' => $this->build_preview($block, $type),
            ];
            if ($type === 'link' && !empty($block->url)) {
                $row['linkurl'] = clean_param($block->url, PARAM_URL);
            } else if ($type === 'file') {
                $row['files'] = $this->get_block_files($block, $output);
                $row['hasfiles'] = !empty($row['files']);
            }
            $rows[] = $row;
        }

        $addactions = $this->get_add_actions($output);

        return [
            'heading' => get_string('viewcontent', 'block_exaport'),
            'blocks' => $rows,
            'hasblocks' => !empty($rows),
            'addicon' => $output->pix_icon('t/add', '', 'moodle', ['aria-hidden' => 'true']),
            'addlabel' => get_string('addcontentblock', 'block_exaport'),
            'addactions' => $addactions,
            'hasaddactions' => !empty($addactions),
            'showaddbutton' => $this->showaddbutton,
            'showheading' => $this->showheading,
        ];
    }

    /**
     * Build the add menu entries.
     *
     * @param renderer_base $output Renderer used for icons.
     * @return array
     */
    private function get_add_actions(renderer_base $output): array {
        if ($this->addurl instanceof moodle_url) {
            $urls = ['text' => $this->addurl];
        } else if (is_array($this->addurl)) {
            $urls = $this->addurl;
        } else {
            return [];
        }

        $actions = [];
        foreach (['text', 'link', 'file'] as $type) {
            if (empty($urls[$type]) || !($urls[$type] instanceof moodle_url)) {
                continue;
            }
            $typeinfo = $this->get_type_info($type);
            $actions[] = [
                'url' => $urls[$type]->out(false),
                'icon' => $this->get_type_icon($output, $typeinfo, ''),
                'label' => $typeinfo['label'],
            ];
        }
        return $actions;
    }

    /**
     * Export files attached to a file block.
     *
     * @param \stdClass $block Content block record.
     * @param renderer_base $output Renderer used for file icons.
     * @return array
     */
    private function get_block_files(\stdClass $block, renderer_base $output): array {
        $context = context_user::instance($this->ownerid);
        $storedfiles = get_file_storage()->get_area_files(
            $context->id,
            'block_exaport',
            'item_content_file',
            $block->id,
            'filename ASC',
            false
        );
        $files = [];
        foreach ($storedfiles as $file) {
            $url = $this->get_file_url($block, $file);
            $isimage = strpos((string)$file->get_mimetype(), 'image/') === 0;
            $files[] = [
                'name' => $file->get_filename(),
                'url' => $url,
                'isimage' => $isimage,
                'icon' => $isimage ? '' : $output->pix_icon(file_file_icon($file), $file->get_filename(), 'moodle'),
            ];
        }
        return $files;
    }

    /**
     * Build an access-aware pluginfile URL for a block file.
     *
     * @param \stdClass $block Content block record.
     * @param \stored_file $file Stored file.
     * @return string
     */
    private function get_file_url(\stdClass $block, \stored_file $file): string {
        global $CFG;

        $parts = [];
        if ($this->access !== null && $this->access !== '') {
            $parts[] = trim($this->access, '/');
        }
        $parts[] = 'itemid/' . (int)$block->itemid;
        $parts[] = 'blockid/' . (int)$block->id;
        $parts[] = $file->get_filename();
        $path = '/' . context_user::instance($this->ownerid)->id . '/block_exaport/item_content_file/' .
            implode('/', $parts);

        return file_encode_url($CFG->wwwroot . '/pluginfile.php', $path, true);
    }

    /**
     * @param string $type
     * @return array
     */
    private function get_type_info(string $type): array {
        switch ($type) {
            case 'text':
                return [
                    'icon' => null,
                    'icontext' => 'T',
                    'label' => get_string('view_specialitem_text', 'block_exaport'),
                ];
            case 'link':
                return [
                    'icon' => 'e/insert_edit_link',
                    'label' => get_string('link', 'block_exaport'),
                ];
            case 'file':
                return [
                    'icon' => 'e/insert_edit_image',
                    'label' => get_string('file', 'block_exaport'),
                ];
            case 'media':
                return [
                    'icon' => 'e/insert_edit_image',
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
     * Render a stable icon for a content block type.
     *
     * @param renderer_base $output
     * @param array $typeinfo
     * @param string $alt
     * @return string
     */
    private function get_type_icon(renderer_base $output, array $typeinfo, string $alt): string {
        if (isset($typeinfo['icontext'])) {
            return \html_writer::tag('span', $typeinfo['icontext'], [
                'class' => 'exaport-item-content-type-icon exaport-item-content-text-icon',
                'aria-hidden' => 'true',
            ]);
        }

        return $output->pix_icon($typeinfo['icon'], $alt, 'moodle', [
            'class' => 'exaport-item-content-type-icon',
        ]);
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
