<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

use block_exaport\local\item_linked_content;
use context_user;
use moodle_url;

/**
 * Present the main form's working blocks using the normal item-content markup.
 *
 * Draft references are private to the editor's owner. Public/shared item rendering
 * continues to use item_content_blocks and its permanent, access-checked URLs.
 *
 * @package block_exaport
 */
class pending_item_content_blocks extends item_content_blocks {
    /** @var array Canonical editor values, in the same order as the presentation rows. */
    private array $states;

    /** @var int Trusted current owner, never a submitted context or user ID. */
    private int $editorownerid;

    /**
     * @param array $states Canonical working blocks.
     * @param \stdClass|null $item Authorized saved item, or null while creating one.
     * @param int $courseid Navigation course.
     * @param bool $editable Whether pending controls may still be used.
     */
    public function __construct(array $states, ?\stdClass $item, int $courseid, bool $editable = true) {
        global $DB, $USER;

        $this->editorownerid = (int)($item->userid ?? $USER->id);
        if ($this->editorownerid !== (int)$USER->id || !isloggedin() || isguestuser()) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        $this->states = array_values($states);
        $blocks = array_map(function(array $state) use ($item, $DB): \stdClass {
            $id = (int)($state['id'] ?? 0);
            $key = $state['key'] ?? '';
            if (!is_string($key) || ($id && $key !== 'block-' . $id) ||
                    (!$id && !preg_match('/\Anew-[a-zA-Z0-9_-]{1,80}\z/', $key))) {
                throw new \invalid_parameter_exception('Invalid pending block key');
            }
            if ($id && !$item) {
                throw new \invalid_parameter_exception('A saved block needs its authorized parent');
            }
            $saved = $id ? $DB->get_record('block_exaportitemblock', ['id' => $id, 'itemid' => $item->id]) : null;
            if ($id && !$saved && (empty($state['modified']) ||
                    $DB->record_exists('block_exaportitemblock', ['id' => $id]))) {
                throw new \invalid_parameter_exception('Content block does not belong to this item');
            }
            if ($saved && $saved->type !== ($state['type'] ?? '')) {
                throw new \invalid_parameter_exception('Content block type cannot be changed');
            }
            if (!in_array($state['type'] ?? '', \block_exaport_item_content_types(), true)) {
                throw new \invalid_parameter_exception('Unsupported content block type');
            }

            // Unchanged rows are read from the server. A submitted presentation
            // value must never select another saved block's files or CV sources.
            // A dirty block deleted by another client still has useful pending
            // text/drafts. Show that working copy while final Save reports the conflict.
            $block = $saved ? clone $saved : (object)['id' => $id, 'itemid' => (int)($item->id ?? 0)];
            $block->editorstored = (bool)$saved;
            $block->editorpending = !$saved || !empty($state['modified']);
            $block->editorstate = $state;
            if ($block->editorpending) {
                $block->type = $state['type'];
                $block->title = clean_param((string)($state['title'] ?? ''), PARAM_TEXT);
                $block->url = clean_param((string)($state['url'] ?? ''), PARAM_URL);
                $block->content = (string)($state['content_editor']['text'] ?? '');
                $block->contentformat = (int)($state['content_editor']['format'] ?? FORMAT_HTML);
            }
            return $block;
        }, $this->states);

        $addurls = [];
        foreach (\block_exaport_item_content_types() as $type) {
            $addurls[$type] = new moodle_url('/blocks/exaport/item_content_' . $type . '.php', [
                'courseid' => $courseid, 'itemid' => (int)($item->id ?? 0),
            ]);
        }
        parent::__construct($blocks, $addurls, $this->editorownerid, $editable, false);
    }

    /** Export the existing markup with stable editor keys and no immediate-save links. */
    public function export_for_template($output): array {
        $data = parent::export_for_template($output);
        foreach ($data['blocks'] as $index => &$row) {
            $row['key'] = (string)$this->states[$index]['key'];
            // The modal is part of this form. A fallback URL must not bypass its
            // pending state and commit a single block before the main Save.
            $row['editurl'] = '#';
            $row['deleteurl'] = '#';
        }
        unset($row);
        foreach ($data['addactions'] as &$action) {
            $action['url'] = '#';
        }
        unset($action);
        return $data;
    }

    /** Render a complete content section, including an empty insertion container. */
    public static function render_section(array $state, ?\stdClass $item, int $courseid, bool $editable = true): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('block_exaport');
        $section = new self($state['blocks'], $item, $courseid, $editable);
        return $renderer->render_from_template('block_exaport/item_content_blocks', $section->export_for_template($renderer));
    }

    /** Render exactly one row after its modal has returned validated pending data. */
    public static function render_row(array $state, ?\stdClass $item, int $courseid): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('block_exaport');
        $section = new self([$state], $item, $courseid);
        $data = $section->export_for_template($renderer);
        $row = $data['blocks'][0] + ['editlabel' => $data['editlabel'], 'deletelabel' => $data['deletelabel']];
        return $renderer->render_from_template('block_exaport/item_content_row', $row);
    }

    /** Load only the current user's accepted draft; unchanged saved files retain their existing URLs. */
    protected function load_block_files(\stdClass $block): array {
        $draftid = (int)($block->editorstate['files_filemanager'] ?? 0);
        if ($block->editorpending && $draftid) {
            return get_file_storage()->get_area_files(context_user::instance($this->editorownerid)->id,
                'user', 'draft', $draftid, 'filepath ASC, filename ASC', false);
        }
        return $block->editorstored ? parent::load_block_files($block) : [];
    }

    /** Draft URLs are owner-only and do not require a saved block ID. */
    protected function get_file_url(\stdClass $block, \stored_file $file): string {
        if ($file->get_component() === 'user' && $file->get_filearea() === 'draft') {
            return moodle_url::make_draftfile_url((int)$file->get_itemid(), $file->get_filepath(),
                $file->get_filename())->out(false);
        }
        return parent::get_file_url($block, $file);
    }

    /** Retain the normal editor's format and private draft references in the preview. */
    protected function format_content(\stdClass $block): string {
        if (!$block->editorpending) {
            return parent::format_content($block);
        }
        $editor = $block->editorstate['content_editor'] ?? [];
        $format = (int)($editor['format'] ?? FORMAT_HTML);
        if (!in_array($format, [(int)FORMAT_MOODLE, (int)FORMAT_HTML, (int)FORMAT_PLAIN, (int)FORMAT_MARKDOWN], true)) {
            throw new \invalid_parameter_exception('Unsupported editor format');
        }
        $text = (string)($editor['text'] ?? '');
        $context = context_user::instance($this->editorownerid);
        if (!empty($editor['itemid'])) {
            $text = file_rewrite_pluginfile_urls($text, 'draftfile.php', $context->id,
                'user', 'draft', (int)$editor['itemid']);
        }
        return format_text($text, $format, ['context' => $context, 'noclean' => false]);
    }

    /**
     * Resolve linked values live, while previewing their selected files privately.
     *
     * A pending CV configuration may include files the saved block does not expose
     * yet. Temporary File API references avoid inventing a public item/block URL.
     */
    protected function format_linked_content(\stdClass $block): string {
        if (!$block->editorpending) {
            return parent::format_linked_content($block);
        }
        $state = $block->editorstate;
        try {
            $block->content = item_linked_content::configuration($block->type, $this->editorownerid,
                (array)($state['selection'] ?? []), !empty($state['withfiles']));
        } catch (\invalid_parameter_exception $exception) {
            // Keep an unavailable selection in the hidden form state. Final
            // validation reports it; rendering must not silently drop the entry.
            return '';
        }
        $resolved = item_linked_content::resolve($block, $this->editorownerid);
        $html = $resolved['content'];
        if (!$resolved['editorfiles']) {
            return $html;
        }
        $draftid = file_get_unused_draft_itemid();
        $context = context_user::instance($this->editorownerid);
        foreach ($resolved['editorfiles'] as $id => $file) {
            $path = '/' . $id . '/';
            get_file_storage()->create_file_from_storedfile([
                'contextid' => $context->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftid, 'filepath' => $path, 'userid' => $this->editorownerid,
            ], $file);
            $url = moodle_url::make_draftfile_url($draftid, $path, $file->get_filename())->out(false);
            $placeholder = '@@PLUGINFILE@@/' . $id . '/' . rawurlencode($file->get_filename());
            $pattern = '~' . preg_quote($placeholder, '~') . '(?=["\'\s<>?#]|$)~u';
            $html = preg_replace_callback($pattern, static function() use ($url): string {
                return s($url);
            }, $html);
        }
        return $html;
    }
}
