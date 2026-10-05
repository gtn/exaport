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

// The renderable is autoloaded from item-edit and other paths which do not use
// the standalone-page bootstrap that loads the procedural content helpers.
require_once(__DIR__ . '/../../lib/item_content_helpers.php');

use context_user;
use moodle_url;
use renderable;
use templatable;

/**
 * Read-only content block rows for an Exaport item.
 */
class item_content_blocks implements renderable, templatable {

    /**
     * Whether exported presentation data contains usable primary content.
     *
     * Empty file blocks and title-only rows intentionally do not count as a
     * replacement for a missing legacy file.
     *
     * @param array $data Data returned by export_for_template().
     * @return bool
     */
    public static function has_displayable_content(array $data): bool {
        foreach ($data['blocks'] ?? [] as $block) {
            if (($block['content'] ?? '') !== '' || !empty($block['linkurl']) || !empty($block['hasfiles'])) {
                return true;
            }
        }
        return false;
    }

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

    /** @var int Navigation course ID used by standalone fallbacks. */
    private $courseid = 0;

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
        $candidateurls = $addurl instanceof moodle_url ? [$addurl] : (is_array($addurl) ? $addurl : []);
        foreach ($candidateurls as $candidateurl) {
            if ($candidateurl instanceof moodle_url) {
                $this->courseid = (int)$candidateurl->get_param('courseid');
                break;
            }
        }
    }

    /**
     * @param \renderer_base|\core\output\renderer_base $output Moodle renderer.
     * @return array
     */
    public function export_for_template($output): array {
        $rows = [];

        foreach ($this->blocks as $block) {
            $type = strtolower(trim((string)($block->type ?? '')));
            $typeinfo = $this->get_type_info($type);
            $typelabel = $typeinfo['label'];

            $row = [
                'id' => (int)$block->id,
                'type' => $type,
                'icon' => $this->get_type_icon($output, $typeinfo, $typelabel),
                'typelabel' => $typelabel,
                'title' => trim((string)($block->title ?? '')),
                'content' => $type === 'text' ? $this->format_content($block) : '',
                'preview' => $this->build_preview($block, $type),
            ];
            if ($this->showaddbutton && $this->addurl !== null) {
                $baseparams = ['courseid' => $this->courseid, 'itemid' => $block->itemid, 'blockid' => $block->id];
                $row['editurl'] = (new moodle_url('/blocks/exaport/item_content_' . $type . '.php', $baseparams))->out(false);
                $row['deleteurl'] = (new moodle_url('/blocks/exaport/item_content_' . $type . '.php',
                    $baseparams + ['operation' => 'delete']))->out(false);
                $row['canedit'] = true;
            }
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
            'editlabel' => get_string('edit'),
            'deletelabel' => get_string('delete'),
        ];
    }

    /**
     * Build the add menu entries.
     *
     * @param \renderer_base|\core\output\renderer_base $output Renderer used for icons.
     * @return array
     */
    private function get_add_actions($output): array {
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
                'type' => $type,
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
     * @param \renderer_base|\core\output\renderer_base $output Renderer used for file icons.
     * @return array
     */
    private function get_block_files(\stdClass $block, $output): array {
        $storedfiles = \block_exaport_get_item_content_files($this->ownerid, (int)$block->id);
        $files = [];
        foreach ($storedfiles as $file) {
            $url = $this->get_file_url($block, $file);
            $isimage = strpos((string)$file->get_mimetype(), 'image/') === 0;
            $isvideo = strpos((string)$file->get_mimetype(), 'video/') === 0;
            $isaudio = strpos((string)$file->get_mimetype(), 'audio/') === 0;
            $files[] = [
                'name' => $file->get_filename(),
                'url' => $url,
                'isimage' => $isimage,
                'isvideo' => $isvideo,
                'isaudio' => $isaudio,
                'isdownload' => !$isimage && !$isvideo && !$isaudio,
                'mimetype' => $file->get_mimetype(),
                'size' => display_size($file->get_filesize()),
                'icon' => $isimage ? '' : $output->pix_icon(file_file_icon($file), $file->get_filename(), 'moodle'),
            ];
        }
        return $files;
    }

    /**
     * Render non-interactive structured content for the PDF collection path.
     *
     * This deliberately consumes the same normalized data as the Mustache view,
     * while avoiding controls and browser-only behaviour.
     *
     * @param \renderer_base|\core\output\renderer_base $output Moodle renderer.
     * @param array|null $data Previously exported template data, when available.
     * @return string Safe HTML.
     */
    public function render_for_pdf($output, ?array $data = null): string {
        $data = $data ?? $this->export_for_template($output);
        $html = '';
        foreach ($data['blocks'] as $block) {
            $html .= \html_writer::start_div('exaport-item-content-pdf');
            if ($block['title'] !== '') {
                $html .= \html_writer::tag('strong', $block['title']);
            }
            if ($block['content'] !== '') {
                $html .= \html_writer::div($block['content']);
            }
            if (!empty($block['linkurl'])) {
                $html .= \html_writer::div(\html_writer::link($block['linkurl'], $block['linkurl']));
            }
            foreach ($block['files'] ?? [] as $file) {
                if ($file['isimage']) {
                    $html .= \html_writer::empty_tag('img', [
                        'src' => $file['url'],
                        'alt' => $file['name'],
                        'class' => 'exaport-item-content-thumbnail',
                    ]);
                } else {
                    $html .= \html_writer::div(\html_writer::link($file['url'], $file['name']) .
                        ' (' . s($file['size']) . ')', 'exaport-item-content-file');
                }
            }
            $html .= \html_writer::end_div();
        }
        return $html;
    }

    /**
     * Build an access-aware pluginfile URL for a block file.
     *
     * @param \stdClass $block Content block record.
     * @param \stored_file $file Stored file.
     * @return string
     */
    private function get_file_url(\stdClass $block, \stored_file $file): string {
        return \block_exaport_get_item_content_file_url(
            (int)$block->itemid,
            (int)$block->id,
            $file,
            (string)($this->access ?? '')
        );
    }

    /**
     * @param string $type
     * @return array
     */
    private function get_type_info(string $type): array {
        switch ($type) {
            case 'text':
                return [
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
     * @param \renderer_base|\core\output\renderer_base $output Moodle renderer.
     * @param array $typeinfo
     * @param string $alt
     * @return string
     */
    private function get_type_icon($output, array $typeinfo, string $alt): string {
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
        $fileitemid = (string)$block->id;
        if (trim((string)$this->access, '/') !== '') {
            // Keep the parent item and authorization route in editor-file URLs. The file
            // area's actual itemid remains the content block ID at the end of this route.
            $fileitemid = trim((string)$this->access, '/') . '/itemid/' . (int)$block->itemid .
                '/blockid/' . (int)$block->id;
        }
        $content = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            $ownercontext->id,
            'block_exaport',
            'item_content_text',
            $fileitemid
        );

        return format_text($content, $contentformat, [
            'context' => $ownercontext,
        ]);
    }
}
