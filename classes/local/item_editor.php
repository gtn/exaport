<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../lib/item_content_helpers.php');
require_once(__DIR__ . '/../../lib/item_competence_helpers.php');

/**
 * Coordinates one form submission without creating unfinished item records.
 *
 * The browser owns working values and native draft IDs. Save writes only touched blocks
 * and explicit removals, using current permissions and records; later saves win.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_editor {
    private const FIELDS = ['name', 'intro', 'project_description', 'project_process', 'project_result', 'langid'];
    private const EDITORS = ['intro', 'project_description', 'project_process', 'project_result'];
    private const MAX_JSON_BYTES = 2097152;
    private const EXTRA_JSON_BYTES = 1048576;
    private const MIN_BLOCK_LIMIT = 200;
    private const EXTRA_BLOCKS = 100;

    /** Authorize a saved item, or authorize the context of a genuinely new item. */
    public static function context(int $itemid, int $courseid): ?\stdClass {
        global $DB;
        require_capability('block/exaport:use', \context_system::instance());
        if (!isloggedin() || isguestuser()) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if ($itemid) {
            return block_exaport_get_editable_item($itemid);
        }
        if ((int)$course->id !== SITEID && !can_access_course($course)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        return null;
    }

    /** Initial values do not prepare files until the learner opens a block's modal. */
    public static function initial_state(?\stdClass $item): array {
        $blocks = $item ? block_exaport_get_item_content_blocks((int)$item->id) : [];
        return ['blocks' => array_map([item_content_edit::class, 'from_record'], $blocks), 'removed' => []];
    }

    /** Reauthorize identity before a modal reads saved values or the submitter's drafts. */
    public static function pending_seed(string $json, int $itemid, int $courseid): array {
        $item = self::context($itemid, $courseid);
        [$raw] = self::decode_json($json, $item);
        $block = self::identify($raw, $item);
        return item_content_edit::normalise($raw, $item, $block, false, false);
    }

    /** Rebuild safe previews from submitted working values after form validation fails. */
    public static function display_state(string $json, int $itemid, int $courseid,
            bool $readonly = false): array {
        global $DB, $USER;
        if ($readonly && $itemid) {
            require_capability('block/exaport:use', \context_system::instance());
            if (!isloggedin() || isguestuser()) {
                throw new \moodle_exception('nopermissions', 'error');
            }
            // A review can lock an already open form. Its owner may still copy pending values;
            // no write path uses this presentation-only bypass of the editability check.
            $item = $DB->get_record('block_exaportitem', ['id' => $itemid, 'userid' => $USER->id], '*', MUST_EXIST);
        } else {
            $item = self::context($itemid, $courseid);
        }
        $state = self::decode_state($json, $item);
        $blocks = [];
        foreach ($state['blocks'] as $raw) {
            $block = self::identify($raw, $item, true);
            if (!$block && !empty($raw['id'])) {
                if (!self::boolean($raw['modified'])) {
                    continue; // An untouched deletion is not an instruction to recreate the block.
                }
                // Keep deleted pending text visible for copying. This identity authorizes no saved
                // file access, and final Save still rejects the missing row.
                $identity = (object)['id' => (int)$raw['id'], 'itemid' => (int)$item->id, 'type' => $raw['type']];
                $blocks[] = item_content_edit::normalise($raw, $item, $identity, false, false);
                continue;
            }
            $blocks[] = $block && !self::boolean($raw['modified']) ? item_content_edit::from_record($block) :
                item_content_edit::normalise($raw, $item, $block, false, false);
        }
        return ['blocks' => $blocks, 'removed' => $state['removed']];
    }

    /** Validate the complete request against current records; the form and commit share this boundary. */
    public static function validate_request(int $itemid, int $courseid, string $json,
            string $competencecsv, array $metadata, bool $competencesenabled): array {
        $item = self::context($itemid, $courseid);
        $state = self::decode_state($json, $item);
        $changes = [];
        $originals = [];
        foreach ($state['blocks'] as $raw) {
            $modified = self::boolean($raw['modified']);
            $block = self::identify($raw, $item, !$modified);
            if (!empty($raw['id']) && !$modified) {
                continue; // Untouched values never overwrite another tab's saved changes.
            }
            $changes[] = item_content_edit::normalise($raw, $item, $block, false);
            if ($block) {
                $originals[(int)$block->id] = $block;
            }
        }
        $removed = [];
        foreach ($state['removed'] as $id) {
            $removed[$id] = self::saved_block((int)$id, $item);
        }
        $competenceids = null;
        if (self::boolean($metadata['competencesmodified'] ?? false)) {
            if (!$competencesenabled || !block_exaport_check_competence_interaction()) {
                throw new \moodle_exception('nopermissions', 'error');
            }
            // Only an applied picker selection replaces assignments. Opening/saving other fields
            // leaves current assignments alone, including changes made through another path.
            $competenceids = block_exaport_validate_item_competenceids($item,
                block_exaport_parse_competenceids($competencecsv));
        }
        $plan = ['item' => $item, 'changes' => $changes, 'originals' => $originals,
            'removed' => $removed, 'competenceids' => $competenceids];
        self::validate_quota($item, $plan, $metadata);
        return $plan;
    }

    /** Save metadata and touched blocks together; the callback reuses the existing item add/edit functions. */
    public static function commit(int $itemid, int $courseid, \stdClass $data, callable $callback,
            bool $competencesenabled): int {
        global $DB, $USER;
        if (!isset($data->contentblocks) || !is_string($data->contentblocks) ||
                ($competencesenabled && (!property_exists($data, 'competenceids') || !is_string($data->competenceids)))) {
            self::invalid();
        }
        $lock = \core\lock\lock_config::get_lock_factory('block_exaport')->get_lock('itemquota:' . $USER->id, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                if ($itemid) {
                    self::lock_item($itemid);
                }
                $plan = self::validate_request($itemid, $courseid, $data->contentblocks,
                    (string)($data->competenceids ?? ''), (array)$data, $competencesenabled);
                self::guard_files($plan['item'], array_merge(array_values($plan['originals']), array_values($plan['removed'])));
                $result = $callback($data);
                $savedid = (int)($result ?: $data->id);
                $item = block_exaport_get_editable_item($savedid);
                foreach ($plan['removed'] as $block) {
                    block_exaport_delete_item_content_block($item, $block);
                }
                foreach ($plan['changes'] as $change) {
                    item_content_edit::save($item, $change, $plan['originals'][$change['id']] ?? null, false);
                }
                if ($plan['competenceids'] !== null) {
                    block_exaport_sync_item_competences($item, $plan['competenceids']);
                }
                $transaction->allow_commit();
                return $savedid;
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /** Obtain the parent write lock before reading mutable records; caller owns the transaction. */
    public static function lock_item(int $itemid): \stdClass {
        global $DB, $USER;
        if (!$DB->is_transaction_started()) {
            throw new \coding_exception('Item locking requires a delegated transaction');
        }
        // Moodle's DML has no portable SELECT FOR UPDATE API. This no-op UPDATE
        // acquires the same row lock without changing metadata in standalone block editors.
        $DB->execute('UPDATE {block_exaportitem} SET timemodified = timemodified WHERE id = ? AND userid = ?',
            [$itemid, $USER->id]);
        return block_exaport_get_editable_item($itemid);
    }

    /**
     * Retain original bytes while permanent references may be removed. Restoring a DB record
     * alone cannot undo filesystem deletion. Native draft references keep content-addressed bytes
     * alive through commit/rollback and are later reclaimed by Moodle's normal draft cleanup.
     */
    public static function guard_files(?\stdClass $item, array $blocks = []): void {
        if (!$item) {
            return;
        }
        $areas = self::parent_areas((int)$item->id);
        foreach ($blocks as $block) {
            if ($block) {
                $areas['item_content_file:' . $block->id] = ['item_content_file', (int)$block->id];
                $areas['item_content_text:' . $block->id] = ['item_content_text', (int)$block->id];
            }
        }
        $context = \context_user::instance((int)$item->userid);
        foreach ($areas as [$area, $id]) {
            if (get_file_storage()->get_area_files($context->id, 'block_exaport', $area, $id, 'id', false)) {
                $draftid = 0;
                file_prepare_draft_area($draftid, $context->id, 'block_exaport', $area, $id, ['subdirs' => true]);
            }
        }
    }

    /** Bound submitted JSON while allowing large existing items to retain their current content. */
    private static function decode_json(string $json, ?\stdClass $item): array {
        $current = self::initial_state($item);
        $maxbytes = max(self::MAX_JSON_BYTES, strlen(json_encode($current, JSON_THROW_ON_ERROR)) + self::EXTRA_JSON_BYTES);
        if ($json === '' || strlen($json) > $maxbytes) {
            self::invalid();
        }
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            self::invalid();
        }
        if (!is_array($value)) {
            self::invalid();
        }
        return [$value, max(self::MIN_BLOCK_LIMIT, count($current['blocks']) + self::EXTRA_BLOCKS)];
    }

    /** Duplicate or contradictory identities are invalid even though untouched values are not saved. */
    private static function decode_state(string $json, ?\stdClass $item): array {
        [$state, $maxblocks] = self::decode_json($json, $item);
        if (!isset($state['blocks'], $state['removed']) ||
                !is_array($state['blocks']) || !array_is_list($state['blocks']) ||
                !is_array($state['removed']) || !array_is_list($state['removed']) ||
                count($state['blocks']) > $maxblocks || count($state['removed']) > $maxblocks) {
            self::invalid();
        }
        $keys = [];
        $ids = [];
        foreach ($state['blocks'] as $raw) {
            if (!is_array($raw) || !isset($raw['key']) || !is_string($raw['key']) || isset($keys[$raw['key']])) {
                self::invalid();
            }
            $keys[$raw['key']] = true;
            $id = self::integer($raw['id'] ?? null);
            self::boolean($raw['modified'] ?? null);
            if ($id && isset($ids[$id])) {
                self::invalid();
            }
            if ($id) {
                $ids[$id] = true;
            }
        }
        $removed = [];
        foreach ($state['removed'] as $id) {
            $id = self::integer($id);
            if (!$id || isset($ids[$id]) || isset($removed[$id])) {
                self::invalid();
            }
            $removed[$id] = true;
        }
        return $state;
    }

    /** Client IDs identify only current blocks belonging to the authorized item. */
    private static function identify(array $raw, ?\stdClass $item, bool $allowmissing = false): ?\stdClass {
        $id = self::integer($raw['id'] ?? null);
        $key = $raw['key'] ?? '';
        if (!in_array($raw['type'] ?? '', block_exaport_item_content_types(), true)) {
            self::invalid();
        }
        if (!$id) {
            if (!is_string($key) || !preg_match('/\Anew-[A-Za-z0-9_-]{1,80}\z/', $key)) {
                self::invalid();
            }
            return null;
        }
        if ($key !== 'block-' . $id) {
            self::invalid();
        }
        $block = self::saved_block($id, $item, $allowmissing);
        if ($block && $block->type !== $raw['type']) {
            self::invalid();
        }
        return $block;
    }

    /** A missing untouched row may be omitted, but a foreign existing ID is always invalid. */
    private static function saved_block(int $id, ?\stdClass $item, bool $allowmissing = false): ?\stdClass {
        global $DB;
        if (!$item) {
            self::invalid();
        }
        $block = $DB->get_record('block_exaportitemblock', ['id' => $id, 'itemid' => $item->id]);
        if (!$block) {
            if ($allowmissing && !$DB->record_exists('block_exaportitemblock', ['id' => $id])) {
                return null;
            }
            self::invalid();
        }
        if (!in_array($block->type, block_exaport_item_content_types(), true)) {
            self::invalid();
        }
        return $block;
    }

    /** A quota preflight describes the resulting collection, not a sequence of intermediate states. */
    private static function validate_quota(?\stdClass $item, array $plan, array $metadata): void {
        global $DB, $CFG, $USER;
        foreach (self::FIELDS as $field) {
            if (isset($metadata[$field]) && !is_string($metadata[$field]) &&
                    !($field === 'langid' && is_int($metadata[$field]))) {
                self::invalid();
            }
        }
        $context = \context_user::instance($USER->id);
        // Complete every editor merge before validating or counting any destination.
        // A client can reuse one owned draft ID for several fields/blocks; a later
        // pasted-file merge must not enlarge an area we already checked or counted.
        foreach (self::EDITORS as $field) {
            $editor = $metadata[$field . '_editor'] ?? null;
            if ($editor !== null && (!is_array($editor) || !is_string($editor['text'] ?? '') ||
                    !in_array(self::integer($editor['format'] ?? FORMAT_HTML),
                        array_map('intval', [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_MARKDOWN]), true))) {
                self::invalid();
            }
            if (is_array($editor) && !empty($editor['itemid'])) {
                file_merge_draft_areas(self::integer($editor['itemid']), $context->id, $editor['text'], false);
            }
        }
        // Recheck block file policies against the final areas as well. They may
        // share a draft with an editor even though the normal UI uses distinct IDs.
        foreach ($plan['changes'] as $block) {
            item_content_edit::normalise($block, $item, $plan['originals'][$block['id']] ?? null, false);
        }
        $current = (int)$DB->get_field_sql('SELECT COALESCE(SUM(filesize),0) FROM {files}
            WHERE contextid = ? AND component = ? AND filename <> ?', [$context->id, 'block_exaport', '.']);
        $final = $current;
        foreach ($plan['removed'] as $block) {
            foreach (['item_content_file', 'item_content_text'] as $area) {
                $final -= self::area_bytes($context->id, $area, (int)$block->id);
            }
        }
        foreach ($plan['changes'] as $block) {
            $area = $block['type'] === 'file' ? 'item_content_file' : 'item_content_text';
            $draft = $block['type'] === 'file' ? $block['files_filemanager'] :
                ($block['type'] === 'text' ? $block['content_editor']['itemid'] : 0);
            if ($draft) {
                $final += self::draft_bytes($draft) - ($block['id'] ? self::area_bytes($context->id, $area, $block['id']) : 0);
            }
        }
        if (array_key_exists('iconfile', $metadata)) {
            $draft = self::integer($metadata['iconfile']);
            self::validate_parent_files($item, 'item_iconfile', $draft, 1, (int)$CFG->block_exaport_max_uploadfile_size, true);
            $final += self::draft_bytes($draft) - ($item ? self::area_bytes($context->id, 'item_iconfile', (int)$item->id) : 0);
        }
        foreach (self::EDITORS as $field) {
            $editor = $metadata[$field . '_editor'] ?? null;
            if (is_array($editor) && !empty($editor['itemid'])) {
                $draft = self::integer($editor['itemid']);
                self::validate_parent_files($item, 'item_content_' . $field, $draft, 99, 0, false);
                $final += self::draft_bytes($draft) - ($item ? self::area_bytes($context->id, 'item_content_' . $field, (int)$item->id) : 0);
            }
        }
        if ($final > max((int)($CFG->block_exaport_userquota ?? 104857600), $current)) {
            throw new \moodle_exception('userquotalimit', 'error');
        }
    }

    /** Parent file managers use the same retained-file policy as content blocks. */
    private static function validate_parent_files(?\stdClass $item, string $area, int $draft, int $limit,
            int $maxbytes, bool $image): void {
        global $USER;
        $context = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $old = $item ? $fs->get_area_files($context->id, 'block_exaport', $area, (int)$item->id, 'id', false) : [];
        $files = $draft ? $fs->get_area_files($context->id, 'user', 'draft', $draft, 'id', false) : [];
        if (count($files) > max($limit, count($old))) {
            self::invalid();
        }
        if ($draft && $old && !$fs->get_area_files($context->id, 'user', 'draft', $draft)) {
            self::invalid();
        }
        $retained = [];
        foreach ($old as $file) {
            $retained[$file->get_contenthash()] = ($retained[$file->get_contenthash()] ?? 0) + 1;
        }
        foreach ($files as $file) {
            $hash = $file->get_contenthash();
            if (!empty($retained[$hash])) {
                $retained[$hash]--;
                continue;
            }
            if (($image && strpos((string)$file->get_mimetype(), 'image/') !== 0) ||
                    ($maxbytes > 0 && $file->get_filesize() > $maxbytes &&
                    get_user_max_upload_file_size($context, $maxbytes, 0, 0, $USER->id) !== USER_CAN_IGNORE_FILE_SIZE_LIMITS)) {
                self::invalid();
            }
        }
    }

    private static function area_bytes(int $contextid, string $area, int $id): int {
        return array_sum(array_map(static function(\stored_file $file): int { return (int)$file->get_filesize(); },
            get_file_storage()->get_area_files($contextid, 'block_exaport', $area, $id, 'id', false)));
    }

    private static function draft_bytes(int $id): int {
        global $USER;
        return $id ? array_sum(array_map(static function(\stored_file $file): int { return (int)$file->get_filesize(); },
            get_file_storage()->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $id, 'id', false))) : 0;
    }

    private static function parent_areas(int $id): array {
        $areas = [['item_iconfile', $id]];
        foreach (self::EDITORS as $field) {
            $areas[] = ['item_content_' . $field, $id];
        }
        return $areas;
    }

    private static function boolean($value): bool {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            self::invalid();
        }
        return $value === true || $value === 1 || $value === '1';
    }

    private static function integer($value): int {
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int)$value < 0) {
            self::invalid();
        }
        return (int)$value;
    }

    private static function invalid(): void {
        throw new \moodle_exception('itemeditorinvalid', 'block_exaport');
    }

}
