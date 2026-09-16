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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Central helper for structured Exaport item content blocks.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_block {
    /** @var string */
    public const TYPE_TEXT = 'text';
    /** @var string */
    public const TYPE_FILE = 'file';
    /** @var string */
    public const TYPE_LINK = 'link';

    /**
     * Supported block types.
     *
     * @return array
     */
    public static function get_supported_types(): array {
        return [
            self::TYPE_TEXT,
            self::TYPE_FILE,
            self::TYPE_LINK,
        ];
    }

    /**
     * Whether the given item already uses structured blocks.
     *
     * @param int $itemid
     * @return bool
     */
    public static function item_uses_blocks(int $itemid): bool {
        global $DB;
        if (!self::table_exists()) {
            return false;
        }
        return $DB->record_exists('block_exaportitemblock', ['itemid' => $itemid]);
    }

    /**
     * Load one block for an item.
     *
     * @param int $blockid
     * @param int $itemid
     * @return \stdClass|null
     */
    public static function get_block(int $blockid, int $itemid = 0): ?\stdClass {
        global $DB;
        if (!self::table_exists()) {
            return null;
        }
        $conditions = ['id' => $blockid];
        if ($itemid > 0) {
            $conditions['itemid'] = $itemid;
        }
        $block = $DB->get_record('block_exaportitemblock', $conditions);
        return $block ?: null;
    }

    /**
     * Load ordered stored blocks for an item.
     *
     * @param int $itemid
     * @return array
     */
    public static function get_blocks(int $itemid): array {
        global $DB;
        if (!self::table_exists()) {
            return [];
        }
        return $DB->get_records('block_exaportitemblock', ['itemid' => $itemid], 'sortorder ASC, id ASC');
    }

    /**
     * Build display blocks for an item, falling back to legacy item fields.
     *
     * @param \stdClass $item
     * @param string $access
     * @return array
     */
    public static function get_display_blocks(\stdClass $item, string $access): array {
        $blocks = self::get_blocks((int)$item->id);
        if ($blocks) {
            return self::prepare_stored_display_blocks($item, $blocks, $access);
        }

        return self::get_legacy_display_blocks($item, $access);
    }

    /**
     * Prepare one stored block for editing.
     *
     * @param \stdClass $block
     * @param \stdClass $item
     * @return \stdClass
     */
    public static function prepare_block_for_edit(\stdClass $block, \stdClass $item): \stdClass {
        global $USER;

        $data = clone $block;
        $data->itemid = $item->id;
        $context = \context_user::instance($USER->id);
        $editoroptions = self::get_editor_options($item);

        if ($block->type === self::TYPE_TEXT || $block->type === self::TYPE_LINK) {
            $data = file_prepare_standard_editor(
                $data,
                'content',
                $editoroptions,
                $context,
                'block_exaport',
                'itemblock_content',
                $block->id
            );
        }

        if ($block->type === self::TYPE_FILE) {
            $draftitemid = file_get_submitted_draft_itemid('file');
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'block_exaport',
                'itemblock_file',
                $block->id,
                self::get_filemanager_options()
            );
            $data->file = $draftitemid;
        }

        return $data;
    }

    /**
     * Editor options for block content.
     *
     * @param \stdClass $item
     * @return array
     */
    public static function get_editor_options(\stdClass $item): array {
        return [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => 99,
            'context' => \context_user::instance($item->userid),
        ];
    }

    /**
     * File manager options for file blocks.
     *
     * @return array
     */
    public static function get_filemanager_options(): array {
        global $CFG;
        return [
            'subdirs' => false,
            'maxfiles' => 1,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
        ];
    }

    /**
     * Create a block for an item.
     *
     * @param \stdClass $item
     * @param string $type
     * @param \stdClass $data
     * @return int
     */
    public static function create_block(\stdClass $item, string $type, \stdClass $data): int {
        global $DB, $USER;

        self::validate_type($type);
        self::normalise_block_data($type, $data, true, 0, (int)$item->userid);

        $time = time();
        $record = (object)[
            'itemid' => $item->id,
            'type' => $type,
            'sortorder' => self::get_next_sortorder($item->id),
            'title' => trim((string)($data->title ?? '')),
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'url' => '',
            'timecreated' => $time,
            'timemodified' => $time,
        ];

        $blockid = (int)$DB->insert_record('block_exaportitemblock', $record);
        $context = \context_user::instance($USER->id);

        if ($type === self::TYPE_TEXT || $type === self::TYPE_LINK) {
            $editoroptions = self::get_editor_options($item);
            $updated = (object)['id' => $blockid, 'content' => '', 'contentformat' => FORMAT_HTML];
            $updated->content_editor = $data->content_editor;
            $updated = file_postupdate_standard_editor(
                $updated,
                'content',
                $editoroptions,
                $context,
                'block_exaport',
                'itemblock_content',
                $blockid
            );
            $updated->timemodified = $time;
            if ($type === self::TYPE_LINK) {
                $updated->title = $record->title;
                $updated->url = clean_param((string)$data->url, PARAM_URL);
            }
            if ($type === self::TYPE_TEXT) {
                $updated->title = $record->title;
            }
            $DB->update_record('block_exaportitemblock', $updated);
        } else if ($type === self::TYPE_FILE) {
            $uploadfilesizes = block_exaport_get_filessize_by_draftid($data->file);
            if (!block_exaport_file_userquotecheck($uploadfilesizes, $item->id)
                || !block_exaport_get_maxfilesize_by_draftid_check($data->file)) {
                throw new \moodle_exception('uploadfailed', 'block_exaport');
            }
            file_save_draft_area_files(
                $data->file,
                $context->id,
                'block_exaport',
                'itemblock_file',
                $blockid,
                self::get_filemanager_options()
            );
            $filename = self::get_block_file_name($blockid, $item->userid);
            $DB->update_record('block_exaportitemblock', (object)[
                'id' => $blockid,
                'title' => trim((string)($data->title ?: $filename)),
                'timemodified' => $time,
            ]);
        }

        return $blockid;
    }

    /**
     * Update an existing block.
     *
     * @param \stdClass $item
     * @param \stdClass $block
     * @param \stdClass $data
     * @return void
     */
    public static function update_block(\stdClass $item, \stdClass $block, \stdClass $data): void {
        global $DB, $USER;

        self::validate_type($block->type);
        self::normalise_block_data($block->type, $data, false, (int)$block->id, (int)$item->userid);

        $record = (object)[
            'id' => $block->id,
            'title' => trim((string)($data->title ?? '')),
            'timemodified' => time(),
        ];
        $context = \context_user::instance($USER->id);

        if ($block->type === self::TYPE_TEXT || $block->type === self::TYPE_LINK) {
            $record->content = $block->content;
            $record->contentformat = $block->contentformat ?: FORMAT_HTML;
            $record->content_editor = $data->content_editor;
            $record = file_postupdate_standard_editor(
                $record,
                'content',
                self::get_editor_options($item),
                $context,
                'block_exaport',
                'itemblock_content',
                $block->id
            );
            if ($block->type === self::TYPE_LINK) {
                $record->url = clean_param((string)$data->url, PARAM_URL);
            }
        } else if ($block->type === self::TYPE_FILE) {
            $uploadfilesizes = block_exaport_get_filessize_by_draftid($data->file);
            if (!block_exaport_file_userquotecheck($uploadfilesizes, $item->id)
                || !block_exaport_get_maxfilesize_by_draftid_check($data->file)) {
                throw new \moodle_exception('uploadfailed', 'block_exaport');
            }
            file_save_draft_area_files(
                $data->file,
                $context->id,
                'block_exaport',
                'itemblock_file',
                $block->id,
                self::get_filemanager_options()
            );
            if (!$record->title) {
                $record->title = self::get_block_file_name($block->id, $item->userid);
            }
        }

        $DB->update_record('block_exaportitemblock', $record);
    }

    /**
     * Delete one block and its files.
     *
     * @param \stdClass $item
     * @param \stdClass $block
     * @return void
     */
    public static function delete_block(\stdClass $item, \stdClass $block): void {
        global $DB;
        $fs = get_file_storage();
        $contextid = \context_user::instance($item->userid)->id;
        $fs->delete_area_files($contextid, 'block_exaport', 'itemblock_content', $block->id);
        $fs->delete_area_files($contextid, 'block_exaport', 'itemblock_file', $block->id);
        $DB->delete_records('block_exaportitemblock', ['id' => $block->id, 'itemid' => $item->id]);
        self::resequence_item($item->id);
    }

    /**
     * Delete all blocks for an item.
     *
     * @param \stdClass $item
     * @return void
     */
    public static function delete_item_blocks(\stdClass $item): void {
        foreach (self::get_blocks((int)$item->id) as $block) {
            self::delete_block($item, $block);
        }
    }

    /**
     * Save linear order for an item.
     *
     * @param int $itemid
     * @param array $blockids
     * @return void
     */
    public static function save_order(int $itemid, array $blockids): void {
        global $DB;
        $existing = self::get_blocks($itemid);
        $existingids = array_map('intval', array_keys($existing));
        $blockids = array_values(array_unique(array_map('intval', $blockids)));
        sort($existingids);
        $sortedsubmitted = $blockids;
        sort($sortedsubmitted);

        if ($existingids !== $sortedsubmitted) {
            throw new \moodle_exception('invalidblockorder', 'block_exaport');
        }

        $sortorder = 1;
        foreach ($blockids as $blockid) {
            $DB->update_record('block_exaportitemblock', (object)[
                'id' => $blockid,
                'sortorder' => $sortorder++,
                'timemodified' => time(),
            ]);
        }
    }

    /**
     * Collect plain summary text for item previews.
     *
     * @param \stdClass $item
     * @return string
     */
    public static function get_summary_text(\stdClass $item): string {
        if (self::item_uses_blocks((int)$item->id)) {
            foreach (self::get_blocks((int)$item->id) as $block) {
                if ($block->type === self::TYPE_TEXT || $block->type === self::TYPE_LINK) {
                    $text = trim(strip_tags((string)($block->content ?? '')));
                    if ($text !== '') {
                        return shorten_text($text, 140, true);
                    }
                }
                if ($block->type === self::TYPE_LINK && !empty($block->url)) {
                    return shorten_text((string)$block->url, 140, true);
                }
                if ($block->type === self::TYPE_FILE) {
                    $filename = self::get_block_file_name((int)$block->id, (int)$item->userid);
                    if ($filename !== '') {
                        return shorten_text($filename, 140, true);
                    }
                }
            }

            return '';
        }

        $legacyfields = [
            $item->intro ?? '',
            $item->project_description ?? '',
            $item->project_process ?? '',
            $item->project_result ?? '',
        ];
        foreach ($legacyfields as $legacyfield) {
            $text = trim(strip_tags((string)$legacyfield));
            if ($text !== '') {
                return shorten_text($text, 140, true);
            }
        }

        if (!empty($item->url)) {
            return shorten_text((string)$item->url, 140, true);
        }

        return self::get_legacy_primary_file_name($item);
    }

    /**
     * Export files associated with an item's effective content.
     *
     * @param \stdClass $item
     * @return array
     */
    public static function get_export_files(\stdClass $item): array {
        if (!self::item_uses_blocks((int)$item->id)) {
            return block_exaport_get_item_files_array($item);
        }

        $files = [];
        foreach (self::get_blocks((int)$item->id) as $block) {
            if ($block->type !== self::TYPE_FILE) {
                continue;
            }
            foreach (self::get_block_files($block, $item->userid) as $file) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /**
     * Export HTML body for an item.
     *
     * @param \stdClass $item
     * @param string $access
     * @return string
     */
    public static function get_export_html(\stdClass $item, string $access): string {
        $parts = [];
        foreach (self::get_display_blocks($item, $access) as $block) {
            if (!empty($block->title)) {
                $parts[] = '<h2>' . s($block->title) . '</h2>';
            }
            if ($block->type === self::TYPE_FILE) {
                foreach ($block->files as $file) {
                    $parts[] = '<p>' . s($file->get_filename()) . '</p>';
                }
                continue;
            }
            if (!empty($block->url)) {
                $parts[] = '<p>' . s($block->url) . '</p>';
            }
            if (!empty($block->contenthtml)) {
                $parts[] = $block->contenthtml;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Export plain text for an item.
     *
     * @param \stdClass $item
     * @param string $access
     * @return string
     */
    public static function get_export_text(\stdClass $item, string $access): string {
        $parts = [];
        foreach (self::get_display_blocks($item, $access) as $block) {
            if (!empty($block->title)) {
                $parts[] = $block->title;
            }
            if ($block->type === self::TYPE_FILE) {
                foreach ($block->files as $file) {
                    $parts[] = $file->get_filename();
                }
                continue;
            }
            if (!empty($block->url)) {
                $parts[] = $block->url;
            }
            if (!empty($block->contenthtml)) {
                $parts[] = trim(html_to_text($block->contenthtml, 0, false));
            }
        }

        return trim(implode("\n\n", array_filter($parts, function(string $part): bool {
            return trim($part) !== '';
        })));
    }

    /**
     * Copy stored blocks from one item to another.
     *
     * @param \stdClass $sourceitem
     * @param \stdClass $targetitem
     * @return void
     */
    public static function copy_blocks(\stdClass $sourceitem, \stdClass $targetitem): void {
        global $DB;

        $fs = get_file_storage();
        $sourcecontextid = \context_user::instance($sourceitem->userid)->id;
        $targetcontextid = \context_user::instance($targetitem->userid)->id;

        foreach (self::get_blocks((int)$sourceitem->id) as $sourceblock) {
            $newblock = clone $sourceblock;
            unset($newblock->id);
            $newblock->itemid = $targetitem->id;
            $newblock->timecreated = time();
            $newblock->timemodified = time();
            $newblockid = (int)$DB->insert_record('block_exaportitemblock', $newblock);

            foreach ($fs->get_area_files($sourcecontextid, 'block_exaport', 'itemblock_content', $sourceblock->id, 'id', false) as $file) {
                $fs->create_file_from_storedfile([
                    'contextid' => $targetcontextid,
                    'component' => 'block_exaport',
                    'filearea' => 'itemblock_content',
                    'itemid' => $newblockid,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ], $file);
            }

            foreach ($fs->get_area_files($sourcecontextid, 'block_exaport', 'itemblock_file', $sourceblock->id, 'id', false) as $file) {
                $fs->create_file_from_storedfile([
                    'contextid' => $targetcontextid,
                    'component' => 'block_exaport',
                    'filearea' => 'itemblock_file',
                    'itemid' => $newblockid,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ], $file);
            }
        }
    }

    /**
     * Load stored files for one file block.
     *
     * @param \stdClass $block
     * @param int $userid
     * @return array
     */
    public static function get_block_files(\stdClass $block, int $userid): array {
        $fs = get_file_storage();
        return $fs->get_area_files(
            \context_user::instance($userid)->id,
            'block_exaport',
            'itemblock_file',
            $block->id,
            'id',
            false
        );
    }

    /**
     * Build display structure for legacy item fields.
     *
     * @param \stdClass $item
     * @param string $access
     * @return array
     */
    public static function get_legacy_display_blocks(\stdClass $item, string $access): array {
        $blocks = [];

        $legacyfields = [
            'intro' => get_string('shortdescription', 'block_exaport'),
            'project_description' => get_string('project_description', 'block_exaport'),
            'project_process' => get_string('project_process', 'block_exaport'),
            'project_result' => get_string('project_result', 'block_exaport'),
        ];

        $files = block_exaport_get_item_files_array($item);
        if ($files) {
            foreach (array_values($files) as $index => $file) {
                $blocks[] = (object)[
                    'id' => 0,
                    'type' => self::TYPE_FILE,
                    'title' => '',
                    'url' => '',
                    'filename' => $file->get_filename(),
                    'files' => [$file],
                    'contenthtml' => '',
                    'fileurl' => (new \moodle_url('/blocks/exaport/portfoliofile.php', [
                        'access' => $access,
                        'itemid' => $item->id,
                        'inst' => $index,
                    ]))->out(false),
                ];
            }
        }

        if ($item->url && $item->url !== 'false') {
            $blocks[] = (object)[
                'id' => 0,
                'type' => self::TYPE_LINK,
                'title' => '',
                'url' => $item->url,
                'filename' => '',
                'files' => [],
                'contenthtml' => '',
            ];
        }

        foreach ($legacyfields as $field => $title) {
            $value = trim((string)($item->{$field} ?? ''));
            if ($value === '') {
                continue;
            }
            $filearea = $field === 'intro' ? 'item_content' : 'item_content_' . $field;
            $content = file_rewrite_pluginfile_urls(
                $value,
                'pluginfile.php',
                \context_user::instance($item->userid)->id,
                'block_exaport',
                $filearea,
                $access . '/itemid/' . $item->id
            );
            $blocks[] = (object)[
                'id' => 0,
                'type' => self::TYPE_TEXT,
                'title' => $title,
                'url' => '',
                'filename' => '',
                'files' => [],
                'contenthtml' => format_text($content, FORMAT_HTML),
            ];
        }

        return $blocks;
    }

    /**
     * Load first thumbnail-capable file from item blocks.
     *
     * @param \stdClass $item
     * @return \stored_file|false
     */
    public static function get_thumbnail_file(\stdClass $item) {
        foreach (self::get_blocks((int)$item->id) as $block) {
            if ($block->type !== self::TYPE_FILE) {
                continue;
            }
            foreach (self::get_block_files($block, (int)$item->userid) as $file) {
                if ($file->is_valid_image()) {
                    return $file;
                }
            }
        }

        return false;
    }

    /**
     * Ensure a valid block type.
     *
     * @param string $type
     * @return void
     */
    public static function validate_type(string $type): void {
        if (!in_array($type, self::get_supported_types(), true)) {
            throw new \moodle_exception('invalidblocktype', 'block_exaport');
        }
    }

    /**
     * Validate submitted data.
     *
     * @param string $type
     * @param \stdClass $data
     * @param bool $isnew
     * @param int $blockid
     * @param int $userid
     * @return void
     */
    private static function normalise_block_data(string $type, \stdClass $data, bool $isnew, int $blockid = 0, int $userid = 0): void {
        if ($type === self::TYPE_TEXT) {
            $text = trim(strip_tags((string)($data->content_editor['text'] ?? '')));
            if ($text === '') {
                throw new \moodle_exception('invalidblockcontent', 'block_exaport');
            }
        }

        if ($type === self::TYPE_FILE) {
            $draftitemid = (int)($data->file ?? 0);
            if (!$isnew && $blockid && $userid && self::block_has_stored_files($blockid, $userid)
                && (!$draftitemid || !self::draft_area_has_files($draftitemid))) {
                return;
            }
            if (!$draftitemid || !self::draft_area_has_files($draftitemid)) {
                throw new \moodle_exception('invalidblockcontent', 'block_exaport');
            }
        }

        if ($type === self::TYPE_LINK) {
            $url = clean_param((string)($data->url ?? ''), PARAM_URL);
            if (!$url) {
                throw new \moodle_exception('invalidblockurl', 'block_exaport');
            }
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new \moodle_exception('invalidblockurl', 'block_exaport');
            }
        }
    }

    /**
     * Prepare stored display blocks.
     *
     * @param \stdClass $item
     * @param array $blocks
     * @param string $access
     * @return array
     */
    private static function prepare_stored_display_blocks(\stdClass $item, array $blocks, string $access): array {
        $displayblocks = [];
        $contextid = \context_user::instance($item->userid)->id;

        foreach ($blocks as $block) {
            $display = clone $block;
            $display->files = [];
            $display->filename = '';
            $display->fileurl = '';
            $display->contenthtml = '';

            if ($block->type === self::TYPE_TEXT || $block->type === self::TYPE_LINK) {
                $content = file_rewrite_pluginfile_urls(
                    $block->content,
                    'pluginfile.php',
                    $contextid,
                    'block_exaport',
                    'itemblock_content',
                    $access . '/blockid/' . $block->id
                );
                $display->contenthtml = format_text($content, $block->contentformat ?: FORMAT_HTML);
            }

            if ($block->type === self::TYPE_FILE) {
                $display->files = self::get_block_files($block, $item->userid);
                $firstfile = reset($display->files);
                if ($firstfile) {
                    $display->filename = $firstfile->get_filename();
                    $display->fileurl = \moodle_url::make_pluginfile_url(
                        $firstfile->get_contextid(),
                        $firstfile->get_component(),
                        'itemblock_file/' . trim($access, '/') . '/blockid',
                        $block->id,
                        $firstfile->get_filepath(),
                        $firstfile->get_filename(),
                        false
                    )->out(false);
                }
            }

            $displayblocks[] = $display;
        }

        return $displayblocks;
    }

    /**
     * Check whether a draft area contains uploaded files.
     *
     * @param int $draftitemid
     * @return bool
     */
    private static function draft_area_has_files(int $draftitemid): bool {
        global $USER;

        if ($draftitemid <= 0 || empty($USER->id)) {
            return false;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $draftitemid, 'id', false);
        return !empty($files);
    }

    /**
     * Get the next sortorder value.
     *
     * @param int $itemid
     * @return int
     */
    private static function get_next_sortorder(int $itemid): int {
        global $DB;
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {block_exaportitemblock} WHERE itemid = ?', [$itemid]);
        return ((int)$max) + 1;
    }

    /**
     * Resequence item blocks after deletion.
     *
     * @param int $itemid
     * @return void
     */
    private static function resequence_item(int $itemid): void {
        global $DB;
        $sortorder = 1;
        foreach (self::get_blocks($itemid) as $block) {
            if ((int)$block->sortorder !== $sortorder) {
                $DB->update_record('block_exaportitemblock', (object)[
                    'id' => $block->id,
                    'sortorder' => $sortorder,
                    'timemodified' => time(),
                ]);
            }
            $sortorder++;
        }
    }

    /**
     * Read the current stored file name for a file block.
     *
     * @param int $blockid
     * @param int $userid
     * @return string
     */
    private static function get_block_file_name(int $blockid, int $userid): string {
        $block = (object)['id' => $blockid];
        $files = self::get_block_files($block, $userid);
        $file = reset($files);
        return $file ? $file->get_filename() : '';
    }

    /**
     * Whether a file block already has stored files.
     *
     * @param int $blockid
     * @param int $userid
     * @return bool
     */
    private static function block_has_stored_files(int $blockid, int $userid): bool {
        return self::get_block_file_name($blockid, $userid) !== '';
    }

    /**
     * Read the primary legacy file name for an item.
     *
     * @param \stdClass $item
     * @return string
     */
    private static function get_legacy_primary_file_name(\stdClass $item): string {
        $files = block_exaport_get_item_files_array($item);
        $file = reset($files);
        return $file ? shorten_text($file->get_filename(), 140, true) : '';
    }

    /**
     * Whether the item block table exists in the current installation.
     *
     * @return bool
     */
    private static function table_exists(): bool {
        global $DB;
        static $exists = null;
        if ($exists === null) {
            $exists = $DB->get_manager()->table_exists(new \xmldb_table('block_exaportitemblock'));
        }
        return $exists;
    }
}
