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

use context_course;
use context_user;
use core_text;

/**
 * Shared mutation helpers for structured item content blocks.
 */
class item_content_mutation_helper {
    /** @var array */
    private const SUPPORTED_TYPES = ['text', 'link', 'file', 'media'];

    /**
     * Supported block types.
     *
     * @return array
     */
    public static function get_supported_block_types(): array {
        return self::SUPPORTED_TYPES;
    }

    /**
     * Return add-action template data.
     *
     * @param \stdClass $item
     * @param string $access
     * @param string $backtype
     * @return array
     */
    public static function get_add_action_links(\stdClass $item, string $access, string $backtype = ''): array {
        $actions = [];
        foreach (self::get_supported_block_types() as $type) {
            $actions[] = [
                'type' => $type,
                'label' => get_string('itemblockaddtype', 'block_exaport', self::get_block_type_label($type)),
                'url' => self::get_block_action_url($item, $access, 'add', $type, 0, $backtype)->out(false),
            ];
        }

        return $actions;
    }

    /**
     * Build a block action URL.
     *
     * @param \stdClass $item
     * @param string $access
     * @param string $action
     * @param string $type
     * @param int $blockid
     * @param string $backtype
     * @return \moodle_url
     */
    public static function get_block_action_url(\stdClass $item, string $access, string $action, string $type = '',
                                                int $blockid = 0, string $backtype = ''): \moodle_url {
        $params = [
            'courseid' => (int)$item->courseid,
            'itemid' => (int)$item->id,
            'action' => $action,
        ];

            if ($type !== '') {
                $params['type'] = $type;
            }
            if ($blockid > 0) {
                $params['blockid'] = $blockid;
            }
            if ($backtype !== '') {
                $params['backtype'] = $backtype;
        }

        return new \moodle_url('/blocks/exaport/item_content_block.php', $params);
    }

    /**
     * Canonical owner access path for block mutation redirects.
     *
     * @param \stdClass $item
     * @return string
     */
    public static function get_manage_access(\stdClass $item): string {
        return 'portfolio/id/' . (int)$item->userid;
    }

    /**
     * Build the return URL for an item detail page.
     *
     * @param \stdClass $item
     * @param string $access
     * @param string $backtype
     * @return \moodle_url
     */
    public static function get_return_url(\stdClass $item, string $access, string $backtype = ''): \moodle_url {
        $params = [
            'courseid' => (int)$item->courseid,
            'access' => $access,
            'itemid' => (int)$item->id,
        ];

        if ($backtype !== '') {
            $params['backtype'] = $backtype;
        }

        return new \moodle_url('/blocks/exaport/shared_item.php', $params);
    }

    /**
     * Load an owner-editable item and optional block.
     *
     * @param int $courseid
     * @param int $itemid
     * @param int $blockid
     * @return array
     */
    public static function require_manage_context(int $courseid, int $itemid, int $blockid = 0): array {
        global $DB, $USER;

        require_login($courseid);
        require_capability('block/exaport:use', context_course::instance($courseid));

        $item = $DB->get_record('block_exaportitem', ['id' => $itemid, 'userid' => $USER->id, 'courseid' => $courseid]);
        if (!$item) {
            throw new \moodle_exception('bookmarknotfound', 'block_exaport');
        }
        if (!block_exaport_item_is_editable($item->id)) {
            throw new \moodle_exception('itemblockeditingnotallowed', 'block_exaport');
        }

        $block = null;
        if ($blockid > 0) {
            $block = item_content_helper::get_item_block_record((int)$item->id, $blockid);
            if (!$block) {
                throw new \moodle_exception('itemblocknotfound', 'block_exaport');
            }
        }

        return [$item, $block];
    }

    /**
     * Check whether the type is supported.
     *
     * @param string $type
     * @return bool
     */
    public static function is_supported_block_type(string $type): bool {
        $type = core_text::strtolower(trim($type));
        return in_array($type, self::get_supported_block_types(), true);
    }

    /**
     * Validate and normalize a block type.
     *
     * @param string $type
     * @return string
     */
    public static function require_supported_block_type(string $type): string {
        $type = core_text::strtolower(trim($type));
        if (!self::is_supported_block_type($type)) {
            throw new \moodle_exception('itemblockunsupportedtype', 'block_exaport');
        }

        return $type;
    }

    /**
     * Prepare form defaults and draft areas.
     *
     * @param \stdClass $item
     * @param int $courseid
     * @param string $type
     * @param \stdClass|null $block
     * @param string $backtype
     * @return \stdClass
     */
    public static function prepare_form_data(\stdClass $item, int $courseid, string $type,
                                             ?\stdClass $block = null, string $backtype = ''): \stdClass {
        $type = self::require_supported_block_type($type);

        $data = $block ? clone $block : new \stdClass();
        $data->itemid = (int)$item->id;
        $data->courseid = $courseid;
        $data->backtype = $backtype;
        $data->type = $type;
        $data->blockid = $block ? (int)$block->id : 0;
        $data->title = $data->title ?? '';
        $data->url = $data->url ?? '';
        $data->content = $data->content ?? '';
        $data->contentformat = $data->contentformat ?? FORMAT_HTML;

        $context = context_user::instance($item->userid);
        if ($block) {
            $data = file_prepare_standard_editor(
                $data,
                'content',
                self::get_editor_options($item),
                $context,
                'block_exaport',
                item_content_helper::CONTENT_FILEAREA,
                $block->id
            );
        } else {
            $draftid = file_get_submitted_draft_itemid('content_editor');
            file_prepare_draft_area(
                $draftid,
                $context->id,
                'block_exaport',
                item_content_helper::CONTENT_FILEAREA,
                0,
                self::get_editor_options($item)
            );
            $data->content_editor = [
                'text' => '',
                'format' => FORMAT_HTML,
                'itemid' => $draftid,
            ];
        }

        if (in_array($type, ['file', 'media'], true)) {
            $draftitemid = file_get_submitted_draft_itemid('attachments');
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'block_exaport',
                item_content_helper::FILEAREA,
                $block ? $block->id : 0,
                self::get_attachment_options($type)
            );
            $data->attachments = $draftitemid;
        }

        return $data;
    }

    /**
     * Create a new block from form data.
     *
     * @param \stdClass $item
     * @param string $type
     * @param \stdClass $data
     * @return \stdClass
     */
    public static function create_block(\stdClass $item, string $type, \stdClass $data): \stdClass {
        global $DB;

        $type = self::require_supported_block_type($type);
        $now = time();
        $block = (object)[
            'itemid' => (int)$item->id,
            'type' => $type,
            'sortorder' => 0,
            'title' => '',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'url' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $block->id = (int)$DB->insert_record('block_exaportitemblock', $block);
        $block->sortorder = $block->id * 10;

        return self::save_block($item, $block, $data);
    }

    /**
     * Update an existing block from form data.
     *
     * @param \stdClass $item
     * @param \stdClass $block
     * @param \stdClass $data
     * @return \stdClass
     */
    public static function update_block(\stdClass $item, \stdClass $block, \stdClass $data): \stdClass {
        self::require_supported_block_type((string)$block->type);
        return self::save_block($item, $block, $data);
    }

    /**
     * Delete a block and its files.
     *
     * @param \stdClass $item
     * @param \stdClass $block
     * @return void
     */
    public static function delete_block(\stdClass $item, \stdClass $block): void {
        global $DB;

        $context = context_user::instance($item->userid, IGNORE_MISSING);
        if ($context) {
            $fs = get_file_storage();
            foreach (item_content_helper::get_block_fileareas() as $filearea) {
                $fs->delete_area_files($context->id, 'block_exaport', $filearea, $block->id);
            }
        }

        if ($DB->record_exists('block_exaportitemblock', ['id' => $block->id, 'itemid' => $item->id])) {
            $DB->delete_records('block_exaportitemblock', ['id' => $block->id, 'itemid' => $item->id]);
            self::touch_item($item);
        }
    }

    /**
     * Normalize a URL using Moodle validation.
     *
     * @param string $url
     * @return string
     */
    public static function normalize_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'http://' . $url;
        }

        return trim(clean_param($url, PARAM_URL));
    }

    /**
     * Validate and normalize a block link URL.
     *
     * @param string $url
     * @return string
     */
    public static function validate_link_url(string $url): string {
        $normalized = self::normalize_url($url);
        $parts = $normalized !== '' ? parse_url($normalized) : false;
        if ($normalized === '' || $parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \moodle_exception('invalidurl');
        }

        return $normalized;
    }

    /**
     * Human-readable block type label.
     *
     * @param string $type
     * @return string
     */
    public static function get_block_type_label(string $type): string {
        $type = self::require_supported_block_type($type);
        return get_string('itemblocktype_' . $type, 'block_exaport');
    }

    /**
     * Delete confirmation text.
     *
     * @param \stdClass $block
     * @return string
     */
    public static function get_delete_confirmation_text(\stdClass $block): string {
        $a = (object)[
            'type' => self::is_supported_block_type((string)$block->type)
                ? self::get_block_type_label((string)$block->type)
                : trim((string)$block->type),
            'title' => trim((string)($block->title ?? '')),
        ];

        return get_string('itemblockdeleteconfirm', 'block_exaport', $a);
    }

    /**
     * Editor options for structured block content.
     *
     * @param \stdClass $item
     * @return array
     */
    public static function get_editor_options(\stdClass $item): array {
        global $CFG;

        return [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => 99,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
            'context' => context_user::instance($item->userid),
        ];
    }

    /**
     * Filemanager options for attachments.
     *
     * @param string $type
     * @return array
     */
    public static function get_attachment_options(string $type): array {
        global $CFG;

        $type = self::require_supported_block_type($type);
        $options = [
            'subdirs' => false,
            'maxfiles' => 10,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
        ];

        if ($type === 'media') {
            $options['accepted_types'] = ['image', 'web_image', 'audio', 'video'];
        }

        return $options;
    }

    /**
     * Persist block content and files.
     *
     * @param \stdClass $item
     * @param \stdClass $block
     * @param \stdClass $data
     * @return \stdClass
     */
    private static function save_block(\stdClass $item, \stdClass $block, \stdClass $data): \stdClass {
        global $DB;

        $type = self::require_supported_block_type((string)$block->type);
        $record = clone $block;
        $record->title = trim((string)($data->title ?? ''));
        $record->url = '';
        $record->content = $record->content ?? '';
        $record->contentformat = $record->contentformat ?? FORMAT_HTML;
        $record->timemodified = time();

        if ($type === 'link') {
            $record->url = self::validate_link_url((string)($data->url ?? ''));
        }

        $editorrecord = clone $record;
        $editorrecord->content_editor = $data->content_editor ?? [
            'text' => '',
            'format' => FORMAT_HTML,
            'itemid' => file_get_submitted_draft_itemid('content_editor'),
        ];
        $editorrecord = file_postupdate_standard_editor(
            $editorrecord,
            'content',
            self::get_editor_options($item),
            context_user::instance($item->userid),
            'block_exaport',
            item_content_helper::CONTENT_FILEAREA,
            $block->id
        );
        $record->content = $editorrecord->content;
        $record->contentformat = $editorrecord->contentformat;

        if (in_array($type, ['file', 'media'], true)) {
            $draftid = (int)($data->attachments ?? 0);
            self::validate_attachment_draft($draftid, $item, $block);
            file_save_draft_area_files(
                $draftid,
                context_user::instance($item->userid)->id,
                'block_exaport',
                item_content_helper::FILEAREA,
                $block->id,
                self::get_attachment_options($type)
            );
        }

        $DB->update_record('block_exaportitemblock', $record);
        self::touch_item($item, (int)$record->timemodified);

        return $DB->get_record('block_exaportitemblock', ['id' => $block->id], '*', MUST_EXIST);
    }

    /**
     * Calculate the next sortorder for a new block.
     *
     * @param int $itemid
     * @return int
     */
    private static function validate_attachment_draft(int $draftid, \stdClass $item, \stdClass $block): void {
        global $DB, $CFG;

        $contextid = context_user::instance($item->userid)->id;
        $draftstats = $DB->get_record_sql(
            "SELECT COALESCE(SUM(filesize), 0) AS allfilesize, COALESCE(MAX(filesize), 0) AS maxfilesize
               FROM {files}
              WHERE contextid = ?
                AND component = 'user'
                AND filearea = 'draft'
                AND itemid = ?",
            [$contextid, $draftid]
        );
        $existingfilesize = $DB->get_field_sql(
            "SELECT COALESCE(SUM(filesize), 0)
               FROM {files}
              WHERE contextid = ?
                AND component = 'block_exaport'
                AND filearea = ?
                AND itemid = ?",
            [$contextid, item_content_helper::FILEAREA, $block->id]
        );
        $totalfilesize = $DB->get_field_sql(
            "SELECT COALESCE(SUM(filesize), 0)
               FROM {files}
              WHERE contextid = ?
                AND component = 'block_exaport'",
            [$contextid]
        );

        if ($CFG->block_exaport_max_uploadfile_size > 0 && (int)$draftstats->maxfilesize > $CFG->block_exaport_max_uploadfile_size) {
            throw new \moodle_exception('maxbytes', 'moodle');
        }
        if ($CFG->block_exaport_userquota > 0
            && ((int)$totalfilesize - (int)$existingfilesize + (int)$draftstats->allfilesize) > $CFG->block_exaport_userquota) {
            throw new \moodle_exception('userquotalimit', 'block_exaport');
        }
    }

    /**
     * Touch the parent item.
     *
     * @param \stdClass $item
     * @param int|null $timemodified
     * @return void
     */
    private static function touch_item(\stdClass $item, ?int $timemodified = null): void {
        global $DB;

        $timemodified = $timemodified ?? time();
        $DB->set_field('block_exaportitem', 'timemodified', $timemodified, ['id' => $item->id]);
        $item->timemodified = $timemodified;
    }
}
