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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

use core_text;
use context_user;

/**
 * Helper methods for structured item content blocks.
 */
class item_content_helper {
    /** @var string filearea for item block attachments */
    public const FILEAREA = 'itemblock_file';

    /**
     * Load all content blocks for an item in display order.
     *
     * @param \stdClass $item
     * @return array
     */
    public static function get_item_block_records(\stdClass $item): array {
        global $DB;

        return array_values($DB->get_records(
            'block_exaportitemblock',
            ['itemid' => $item->id],
            'sortorder ASC, id ASC'
        ));
    }

    /**
     * Load and export all content blocks for an item.
     *
     * @param \stdClass $item
     * @param string $access
     * @return array
     */
    public static function export_item_blocks(\stdClass $item, string $access): array {
        $blocks = [];
        foreach (self::get_item_block_records($item) as $block) {
            $blocks[] = self::export_item_block($item, $access, $block);
        }

        return $blocks;
    }

    /**
     * Load one content block only when it belongs to the current item.
     *
     * @param int $itemid
     * @param int $blockid
     * @return \stdClass|null
     */
    public static function get_item_block_record(int $itemid, int $blockid): ?\stdClass {
        global $DB;

        $block = $DB->get_record('block_exaportitemblock', ['id' => $blockid, 'itemid' => $itemid]);
        return $block ?: null;
    }

    /**
     * Delete all stored files for an item's content blocks.
     *
     * @param \stdClass $item
     * @return void
     */
    public static function delete_item_block_files(\stdClass $item): void {
        $context = context_user::instance($item->userid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        $fs = get_file_storage();
        foreach (self::get_item_block_records($item) as $block) {
            $fs->delete_area_files($context->id, 'block_exaport', self::FILEAREA, $block->id);
        }
    }

    /**
     * Delete all structured blocks and their stored files for an item.
     *
     * @param \stdClass $item
     * @return void
     */
    public static function delete_item_blocks(\stdClass $item): void {
        global $DB;

        self::delete_item_block_files($item);
        $DB->delete_records('block_exaportitemblock', ['itemid' => $item->id]);
    }

    /**
     * Copy all structured blocks and their files to another item.
     *
     * @param \stdClass $sourceitem
     * @param int $targetitemid
     * @param int $targetuserid
     * @return void
     */
    public static function copy_item_blocks(\stdClass $sourceitem, int $targetitemid, int $targetuserid): void {
        global $DB;

        $sourcecontext = context_user::instance($sourceitem->userid, IGNORE_MISSING);
        $targetcontext = context_user::instance($targetuserid, IGNORE_MISSING);
        if (!$sourcecontext || !$targetcontext) {
            return;
        }

        $fs = get_file_storage();
        foreach (self::get_item_block_records($sourceitem) as $block) {
            $newblock = clone $block;
            unset($newblock->id);
            $newblock->itemid = $targetitemid;
            $newblock->timecreated = time();
            $newblock->timemodified = time();
            $newblockid = (int)$DB->insert_record('block_exaportitemblock', $newblock);

            foreach (self::get_block_files($sourceitem, (int)$block->id) as $file) {
                if (!$file || $file->is_directory()) {
                    continue;
                }

                $fs->create_file_from_storedfile([
                    'contextid' => $targetcontext->id,
                    'component' => 'block_exaport',
                    'filearea' => self::FILEAREA,
                    'itemid' => $newblockid,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'userid' => $targetuserid,
                ], $file);
            }
        }
    }

    /**
     * Export one block for Mustache rendering.
     *
     * @param \stdClass $item
     * @param string $access
     * @param \stdClass $block
     * @return array
     */
    protected static function export_item_block(\stdClass $item, string $access, \stdClass $block): array {
        $type = core_text::strtolower(trim((string)($block->type ?? '')));
        $title = trim((string)($block->title ?? ''));
        $contenthtml = self::format_block_content($item, $access, $block);
        $files = self::export_block_files($item, $access, $block);
        $url = trim(clean_param((string)($block->url ?? ''), PARAM_URL));

        $data = [
            'blockid' => (int)$block->id,
            'type' => $type,
            'sortorder' => (int)($block->sortorder ?? 0),
            'hasheading' => $title !== '',
            'heading' => format_string($title),
            'hascontent' => $contenthtml !== '',
            'contenthtml' => $contenthtml,
            'emptycontenttext' => get_string('itemblockemptycontent', 'block_exaport'),
        ];

        switch ($type) {
            case 'text':
                $data['istext'] = true;
                break;

            case 'link':
                $data['islink'] = true;
                $data['hasurl'] = $url !== '';
                $data['url'] = $url;
                $data['linktext'] = $title !== '' ? format_string($title) :
                    ($url !== '' ? preg_replace('~^https?://~i', '', $url) : '');
                $data['emptylinktext'] = get_string('itemblockemptylink', 'block_exaport');
                break;

            case 'file':
                $data['isfile'] = true;
                $data['hasfiles'] = !empty($files);
                $data['files'] = $files;
                $data['emptyfiletext'] = get_string('itemblockemptyfile', 'block_exaport');
                break;

            case 'media':
                $data['ismedia'] = true;
                $data['hasfiles'] = !empty($files);
                $data['files'] = $files;
                $data['emptyfiletext'] = get_string('itemblockemptymedia', 'block_exaport');
                break;

            default:
                $data['isunknown'] = true;
                $data['unsupportedtext'] = get_string('itemblockunsupported', 'block_exaport');
                break;
        }

        return $data;
    }

    /**
     * Rewrite pluginfile URLs for block content and format the result.
     *
     * @param \stdClass $item
     * @param string $access
     * @param \stdClass $block
     * @return string
     */
    protected static function format_block_content(\stdClass $item, string $access, \stdClass $block): string {
        $content = trim((string)($block->content ?? ''));
        if ($content === '') {
            return '';
        }

        $rewritten = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            context_user::instance($item->userid)->id,
            'block_exaport',
            self::FILEAREA,
            self::get_pluginfile_itemid($access, (int)$item->id, (int)$block->id)
        );

        return format_text($rewritten, (int)($block->contentformat ?? FORMAT_HTML));
    }

    /**
     * Export stored files attached to a block.
     *
     * @param \stdClass $item
     * @param string $access
     * @param \stdClass $block
     * @return array
     */
    protected static function export_block_files(\stdClass $item, string $access, \stdClass $block): array {
        $files = [];
        foreach (self::get_block_files($item, (int)$block->id) as $file) {
            if (!$file || $file->is_directory()) {
                continue;
            }

            $mimetype = (string)$file->get_mimetype();
            $files[] = [
                'name' => $file->get_filename(),
                'url' => self::get_block_file_url($file, $access, (int)$item->id),
                'size' => display_size($file->get_filesize()),
                'mimetype' => $mimetype,
                'isimage' => $file->is_valid_image(),
                'isvideo' => str_starts_with($mimetype, 'video/'),
                'isaudio' => str_starts_with($mimetype, 'audio/'),
            ];
        }

        return $files;
    }

    /**
     * Return the stored files for a block.
     *
     * @param \stdClass $item
     * @param int $blockid
     * @return array
     */
    public static function get_block_files(\stdClass $item, int $blockid): array {
        if (!context_user::instance($item->userid, IGNORE_MISSING)) {
            return [];
        }

        $fs = get_file_storage();
        return $fs->get_area_files(
            context_user::instance($item->userid)->id,
            'block_exaport',
            self::FILEAREA,
            $blockid,
            'filename ASC',
            false
        );
    }

    /**
     * Build an access-controlled pluginfile URL for a block file.
     *
     * @param \stored_file $file
     * @param string $access
     * @param int $itemid
     * @return string
     */
    public static function get_block_file_url(\stored_file $file, string $access, int $itemid): string {
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            self::FILEAREA,
            self::get_pluginfile_itemid($access, $itemid, (int)$file->get_itemid()),
            $file->get_filepath(),
            $file->get_filename(),
            false,
            false
        )->out(false);
    }

    /**
     * Parse pluginfile arguments for an item block attachment.
     *
     * @param array $args
     * @return array
     */
    public static function parse_block_file_args(array $args): array {
        $accessposition = array_search('access', $args, true);
        $itemidposition = array_search('itemid', $args, true);
        $blockidposition = array_search('blockid', $args, true);

        if ($accessposition === false || $itemidposition === false || $blockidposition === false
            || $accessposition + 1 !== $itemidposition - 1 || $blockidposition <= $itemidposition + 1) {
            return [];
        }

        $access = self::decode_access_path($args[$accessposition + 1] ?? '');
        $itemid = clean_param($args[$itemidposition + 1] ?? 0, PARAM_INT);
        $blockid = clean_param($args[$blockidposition + 1] ?? 0, PARAM_INT);
        $fileparts = array_slice($args, $blockidposition + 2);

        if ($access === '' || empty($itemid) || empty($blockid) || empty($fileparts)) {
            return [];
        }

        $filename = array_pop($fileparts);
        if ($filename === '') {
            return [];
        }

        return [
            'access' => $access,
            'itemid' => (int)$itemid,
            'blockid' => (int)$blockid,
            'filepath' => '/' . ($fileparts ? implode('/', $fileparts) . '/' : ''),
            'filename' => $filename,
        ];
    }

    /**
     * Encode an access path into a single safe URL segment.
     *
     * @param string $access
     * @return string
     */
    public static function encode_access_path(string $access): string {
        $encoded = base64_encode(trim($access, '/'));
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    /**
     * Decode an access path from a safe URL segment.
     *
     * @param string $access
     * @return string
     */
    public static function decode_access_path(string $access): string {
        if ($access === '') {
            return '';
        }

        $padding = strlen($access) % 4;
        if ($padding !== 0) {
            $access .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($access, '-_', '+/'), true);
        if ($decoded === false) {
            return '';
        }

        return trim($decoded, '/');
    }

    /**
     * Return the pluginfile itemid token used for block content files.
     *
     * @param string $access
     * @param int $itemid
     * @param int $blockid
     * @return string
     */
    public static function get_pluginfile_itemid(string $access, int $itemid, int $blockid): string {
        return 'access/' . self::encode_access_path($access) . '/itemid/' . $itemid . '/blockid/' . $blockid;
    }
}
