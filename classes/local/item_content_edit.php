<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../lib/item_content_helpers.php');

/** Shared block data, validation and persistence for the item editor and standalone forms. */
final class item_content_edit {
    /** Project an owned stored block into the editor without creating any draft block records. */
    public static function from_record(\stdClass $block): array {
        global $USER;
        $state = [
            'key' => 'block-' . $block->id, 'id' => (int)$block->id, 'type' => $block->type,
            'title' => (string)($block->title ?? ''), 'modified' => false,
            'url' => (string)($block->url ?? ''), 'files_filemanager' => 0,
            'content_editor' => ['text' => (string)($block->content ?? ''),
                'format' => (int)($block->contentformat ?? FORMAT_HTML), 'itemid' => 0],
            'selection' => [], 'withfiles' => false,
        ];
        if (in_array($block->type, item_linked_content::TYPES, true)) {
            $configuration = item_linked_content::selection($block, (int)$USER->id);
            $state['selection'] = $configuration['selection'];
            $state['withfiles'] = !empty($configuration['withfiles']);
            $state['content_editor'] = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        }
        return $state;
    }

    /** Convert the existing Moodle form fields to the same data used by the main form. */
    public static function from_form(\stdClass $data, string $key = ''): array {
        $state = (array)$data;
        $state['id'] = (int)($data->blockid ?? 0);
        $state['key'] = $key ?: (string)($data->blockkey ?? $data->key ??
            ($state['id'] ? 'block-' . $state['id'] : 'new-standalone'));
        $state['type'] = $data->contenttype ?? '';
        $state['modified'] = true;
        return $state;
    }

    /**
     * Validate editable values against trusted item/block records, not client-supplied ownership.
     *
     * Initial modal rendering permits empty required values; Apply and final Save do not.
     */
    public static function normalise(array $raw, ?\stdClass $item, ?\stdClass $block,
            bool $checkquota = false, bool $validate = true): array {
        global $USER;
        $id = self::integer($raw['id'] ?? 0);
        $type = $raw['type'] ?? '';
        if (!is_string($type) || !in_array($type, block_exaport_item_content_types(), true) ||
                ($block && (!$item || (int)$block->itemid !== (int)$item->id ||
                    $id !== (int)$block->id || $type !== $block->type)) ||
                (!$block && $id !== 0)) {
            self::invalid();
        }
        $key = $raw['key'] ?? ($id ? 'block-' . $id : 'new-standalone');
        if (!is_string($key) || !preg_match('/\A(?:block-[1-9][0-9]*|new-[a-zA-Z0-9_-]{1,80})\z/', $key) ||
                ($id && $key !== 'block-' . $id) || (!$id && !str_starts_with($key, 'new-'))) {
            self::invalid();
        }
        $title = self::text($raw['title'] ?? '');
        if (\core_text::strlen($title) > 255) {
            self::invalid();
        }
        $modified = self::boolean($raw['modified'] ?? false);
        $state = ['key' => $key, 'id' => $id, 'type' => $type, 'title' => $title,
            'modified' => !$id || $modified, 'files_filemanager' => 0,
            'content_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'url' => '', 'selection' => [], 'withfiles' => false];

        if ($type === 'file') {
            $state['files_filemanager'] = self::integer($raw['files_filemanager'] ?? 0);
            if ($validate) {
                block_exaport_check_item_content_file_submission($item, $block,
                    $state['files_filemanager'], $checkquota);
            }
        } else if ($type === 'text') {
            $editor = $raw['content_editor'] ?? [];
            if (!is_array($editor) || !is_string($editor['text'] ?? '')) {
                self::invalid();
            }
            $format = self::integer($editor['format'] ?? FORMAT_HTML);
            if (!in_array($format, [(int)FORMAT_MOODLE, (int)FORMAT_HTML, (int)FORMAT_PLAIN, (int)FORMAT_MARKDOWN], true)) {
                self::invalid();
            }
            $state['content_editor'] = ['text' => $editor['text'] ?? '', 'format' => $format,
                'itemid' => self::integer($editor['itemid'] ?? 0)];
            if ($validate) {
                $context = \context_user::instance((int)$USER->id);
                if (!$state['content_editor']['itemid'] && extract_draft_file_urls_from_text(
                        $state['content_editor']['text'], false, $context->id, 'user', 'draft')) {
                    $state['content_editor']['itemid'] = file_get_unused_draft_itemid();
                }
                if ($state['content_editor']['itemid']) {
                    // Core merges files referenced from other draft areas when saving editor HTML.
                    // Perform that merge before our attachment policy and the aggregate quota check.
                    $state['content_editor']['text'] = file_merge_draft_areas($state['content_editor']['itemid'],
                        $context->id, $state['content_editor']['text'], false);
                }
                self::validate_editor_files($state['content_editor']['itemid'], $item, $block);
            }
        } else if ($type === 'link') {
            $url = self::text($raw['url'] ?? '', PARAM_URL);
            if (\core_text::strlen($url) > 255 || ($validate && $url === '')) {
                self::invalid();
            }
            $state['url'] = $url;
        } else {
            $selection = $raw['selection'] ?? [];
            if (!is_array($selection) || count($selection) > 500) {
                self::invalid();
            }
            foreach ($selection as $value) {
                if (!is_int($value) && !is_string($value)) {
                    self::invalid();
                }
            }
            $state['selection'] = array_values(array_unique(array_map('strval', $selection)));
            $state['withfiles'] = $type === 'cv' && self::boolean($raw['withfiles'] ?? false);
            if ($validate) {
                // Linked blocks persist the selection; current CV/profile values remain authoritative.
                self::linked_configuration($type, (int)($item->userid ?? $USER->id),
                    $state['selection'], $state['withfiles']);
            }
        }
        return $state;
    }

    /** Prepare a separate modal working copy so cancelling cannot mutate an applied block. */
    public static function prepare(array $state, ?\stdClass $item, ?\stdClass $block): array {
        global $USER;
        $context = \context_user::instance((int)($item->userid ?? $USER->id));
        $data = $state + ['blockid' => (int)($block->id ?? 0), 'contenttype' => $state['type']];
        $data['blockid'] = (int)($block->id ?? 0);
        $data['contenttype'] = $state['type'];
        if ($state['type'] === 'file' || $state['type'] === 'text') {
            $isfile = $state['type'] === 'file';
            $accepted = $isfile ? (int)$state['files_filemanager'] : (int)$state['content_editor']['itemid'];
            $draftid = 0;
            if ($accepted > 0 && !empty($state['modified'])) {
                $draftid = file_get_unused_draft_itemid();
                // Copying into a fresh draft preserves the original-file source metadata. Preparing a
                // draft from another draft would wrap that metadata again and disturb unchanged files.
                file_merge_draft_area_into_draft_area($accepted, $draftid);
                if (!$isfile) {
                    $text = file_rewrite_urls_to_pluginfile($state['content_editor']['text'], $accepted);
                    $data['content_editor']['text'] = file_rewrite_pluginfile_urls($text, 'draftfile.php',
                        $context->id, 'user', 'draft', $draftid);
                }
            } else {
                $text = !$isfile ? (string)($block->content ?? $state['content_editor']['text']) : null;
                $prepared = file_prepare_draft_area($draftid, $context->id, 'block_exaport',
                    $isfile ? 'item_content_file' : 'item_content_text', $block ? (int)$block->id : null,
                    ['subdirs' => true], $text);
                if (!$isfile) {
                    $data['content_editor']['text'] = $prepared ?? '';
                }
            }
            if ($isfile) {
                $data['files_filemanager'] = $draftid;
            } else {
                $data['content_editor']['itemid'] = $draftid;
            }
        }
        return $data;
    }

    /**
     * Persist one validated block. The caller owns the transaction and original-file guards.
     *
     * This is shared by the main Save and the explicit standalone block-page Save.
     */
    public static function save(\stdClass $item, array $state, ?\stdClass $block = null,
            bool $checkquota = true): ?\stdClass {
        global $DB;
        $state = self::normalise($state, $item, $block, $checkquota);
        $block = $block ?: block_exaport_create_content_block((int)$item->id, $state['type'],
            $state['title'], $state['type'] === 'link' ? $state['url'] : '');
        $changes = (object)['id' => $block->id, 'title' => $state['title'], 'timemodified' => time()];
        // Preserve unrelated historical metadata on retained rows; block IDs never change on edit.
        if ($state['type'] === 'link') {
            $changes->url = $state['url'];
        } else if (in_array($state['type'], item_linked_content::TYPES, true)) {
            $changes->content = self::linked_configuration($state['type'], (int)$item->userid,
                $state['selection'], $state['withfiles']);
            $changes->contentformat = FORMAT_HTML;
        }
        $DB->update_record('block_exaportitemblock', $changes);
        $context = \context_user::instance((int)$item->userid);
        $data = (object)$state;
        if ($state['type'] === 'text') {
            $options = block_exaport_item_content_editor_options();
            // Validation permits only retained editor attachments. Do not let core's upload filters
            // truncate grandfathered files after they have already passed that validation.
            $options['subdirs'] = true;
            $options['maxfiles'] = -1;
            $data = file_postupdate_standard_editor($data, 'content', $options, $context,
                'block_exaport', 'item_content_text', (int)$block->id);
            $DB->update_record('block_exaportitemblock', (object)['id' => $block->id,
                'content' => $data->content, 'contentformat' => $data->contentformat]);
        } else if ($state['type'] === 'file') {
            $options = block_exaport_item_content_file_options($item, $block);
            $options['maxbytes'] = 0;
            file_postupdate_standard_filemanager($data, 'files', $options, $context,
                'block_exaport', 'item_content_file', (int)$block->id);
            if (!block_exaport_get_item_content_files((int)$item->userid, (int)$block->id)) {
                block_exaport_delete_item_content_block($item, $block);
                return null;
            }
        }
        return $DB->get_record('block_exaportitemblock', ['id' => $block->id], '*', MUST_EXIST);
    }

    /** Read a draft only through the current submitter's context. */
    public static function draft_files(int $draftid): array {
        global $USER;
        return $draftid > 0 ? get_file_storage()->get_area_files(\context_user::instance($USER->id)->id,
            'user', 'draft', $draftid, 'id', false) : [];
    }

    /** Existing imported editor files may be kept, but this editor does not offer new uploads. */
    private static function validate_editor_files(int $draftid, ?\stdClass $item, ?\stdClass $block): void {
        if (!$draftid) {
            return;
        }
        $original = [];
        if ($item && $block) {
            $files = get_file_storage()->get_area_files(\context_user::instance($item->userid)->id,
                'block_exaport', 'item_content_text', (int)$block->id, 'id', false);
            foreach ($files as $file) {
                $original[$file->get_filepath() . $file->get_filename() . ':' . $file->get_contenthash()] = true;
            }
        }
        global $USER;
        if ($original && !get_file_storage()->get_file(\context_user::instance($USER->id)->id,
                'user', 'draft', $draftid, '/', '.')) {
            self::invalid();
        }
        foreach (self::draft_files($draftid) as $file) {
            $key = $file->get_filepath() . $file->get_filename() . ':' . $file->get_contenthash();
            if (!isset($original[$key])) {
                self::invalid();
            }
        }
    }

    /** Strictly parse ids so malformed JSON cannot silently become a new record. */
    private static function integer($value): int {
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int)$value < 0) {
            self::invalid();
        }
        return (int)$value;
    }

    private static function text($value, string $type = PARAM_TEXT): string {
        if (!is_string($value)) {
            self::invalid();
        }
        return clean_param($value, $type);
    }

    private static function boolean($value): bool {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            self::invalid();
        }
        return $value === true || $value === 1 || $value === '1';
    }

    /** An unavailable live reference needs an actionable form error, rather than an API parameter error. */
    private static function linked_configuration(string $type, int $ownerid, array $selection, bool $withfiles): string {
        try {
            return item_linked_content::configuration($type, $ownerid, $selection, $withfiles);
        } catch (\invalid_parameter_exception $error) {
            throw new \moodle_exception('linkedcontentselectionrequired', 'block_exaport');
        }
    }

    private static function invalid(): void {
        throw new \moodle_exception('itemeditorinvalid', 'block_exaport');
    }
}
