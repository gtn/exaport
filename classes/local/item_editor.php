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
 * The browser owns its working values; the encrypted baseline contains only trusted
 * identities and fingerprints. Neither modal Apply nor preview rendering writes blocks.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_editor {
    private const FIELDS = ['name', 'intro', 'project_description', 'project_process', 'project_result', 'langid'];
    private const EDITORS = ['intro', 'project_description', 'project_process', 'project_result'];

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

    /** Open the displayed state and its fingerprints from the same supported block records. */
    public static function open(?\stdClass $item, int $courseid, bool $competencesenabled): array {
        $blocks = $item ? block_exaport_get_item_content_blocks((int)$item->id) : [];
        return ['state' => self::initial_state($item, $blocks),
            'baseline' => self::baseline($item, $courseid, $competencesenabled, $blocks)];
    }

    /** Initial values; optional records let the caller share one trusted opening snapshot. */
    public static function initial_state(?\stdClass $item, ?array $blocks = null): array {
        $blocks = $item ? ($blocks ?? block_exaport_get_item_content_blocks((int)$item->id)) : [];
        return ['blocks' => array_map([item_content_edit::class, 'from_record'], $blocks), 'removed' => []];
    }

    /** Record selected IDs, preserving Exacomp's native policy for former assignments. */
    public static function competence_ids(?\stdClass $item): array {
        global $DB;
        if (!$item || !block_exaport_check_competence_interaction()) {
            return [];
        }
        $ids = block_exaport_normalize_competenceids($DB->get_fieldset_select(
            BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'compid', 'activityid = ? AND eportfolioitem = 1', [$item->id]));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    /** Native encryption authenticates the small baseline; uploaded bytes stay in the File API. */
    public static function baseline(?\stdClass $item, int $courseid, bool $competencesenabled,
            ?array $records = null): string {
        global $USER;
        self::context((int)($item->id ?? 0), $courseid);
        // A second row read could authenticate a concurrent edit which the opening form never showed.
        $records = $item ? ($records ?? block_exaport_get_item_content_blocks((int)$item->id)) : [];
        $blocks = [];
        foreach ($records as $block) {
            $blocks[(int)$block->id] = ['type' => $block->type, 'hash' => self::block_hash($item, $block)];
        }
        $state = self::initial_state($item, $records);
        $textareas = [];
        foreach (self::EDITORS as $field) {
            $textareas[$field] = $item && !empty($item->{$field}) && preg_match('/<iframe/i', $item->{$field}) === 1;
        }
        return \core\encryption::encrypt(json_encode([
            'itemid' => (int)($item->id ?? 0), 'courseid' => $courseid, 'userid' => (int)$USER->id,
            'session' => hash('sha256', sesskey()), 'created' => time(), 'nonce' => bin2hex(random_bytes(16)),
            'metadata' => $item ? self::metadata_hash($item) : '', 'blocks' => $blocks,
            'competenceids' => self::competence_ids($item), 'competencesenabled' => $competencesenabled,
            'textareas' => $textareas,
            'maxblocks' => max(200, count($blocks) + 100),
            'maxbytes' => max(2097152, strlen(json_encode($state)) + 1048576),
        ], JSON_THROW_ON_ERROR));
    }

    /** A baseline is usable only in the session and item context that issued it. */
    public static function read_baseline(string $token, int $itemid, int $courseid): array {
        global $USER;
        try {
            if ($token === '' || strlen($token) > 2097152) {
                self::invalid();
            }
            $base = json_decode(\core\encryption::decrypt($token), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($base) || ($base['itemid'] ?? -1) !== $itemid || ($base['courseid'] ?? -1) !== $courseid ||
                    ($base['userid'] ?? 0) !== (int)$USER->id ||
                    !hash_equals($base['session'] ?? '', hash('sha256', sesskey())) ||
                    ($base['created'] ?? 0) < time() - DAYSECS || !isset($base['blocks'], $base['nonce'])) {
                self::invalid();
            }
            return $base;
        } catch (\Throwable $exception) {
            self::invalid();
        }
    }

    /** Validate identity before a modal loads any saved data or any user's draft files. */
    public static function pending_seed(string $json, string $token, int $itemid, int $courseid): array {
        $item = self::context($itemid, $courseid);
        $base = self::read_baseline($token, $itemid, $courseid);
        try {
            $raw = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            self::invalid();
        }
        if (!is_array($raw) || strlen($json) > $base['maxbytes']) {
            self::invalid();
        }
        $block = self::identify($raw, $item, $base);
        if ($block && !hash_equals($base['blocks'][(int)$block->id]['hash'], self::block_hash($item, $block))) {
            self::conflict();
        }
        return item_content_edit::normalise($raw, $item, $block, false, false);
    }

    /** Rebuild safe previews from pending values after normal form validation fails. */
    public static function display_state(string $json, string $token, int $itemid, int $courseid,
            bool $readonly = false): array {
        global $DB, $USER;
        if ($readonly && $itemid) {
            require_capability('block/exaport:use', \context_system::instance());
            if (!isloggedin() || isguestuser()) {
                throw new \moodle_exception('nopermissions', 'error');
            }
            // A review can lock the item after the editor was opened. Its owner may
            // still view and copy their pending values; no write path uses this bypass.
            $item = $DB->get_record('block_exaportitem', ['id' => $itemid, 'userid' => $USER->id], '*', MUST_EXIST);
        } else {
            $item = self::context($itemid, $courseid);
        }
        $base = self::read_baseline($token, $itemid, $courseid);
        $state = self::decode_state($json, $base);
        $blocks = [];
        foreach ($state['blocks'] as $raw) {
            $block = self::identify($raw, $item, $base, true);
            if (!$block && !empty($raw['id'])) {
                if (empty($raw['modified'])) {
                    continue; // An untouched concurrent deletion is not an instruction to recreate it.
                }
                // Only presentation identity is reconstructed from our authenticated baseline.
                // A missing dirty row remains visible for copying; final Save still rejects it.
                $identity = (object)['id' => (int)$raw['id'], 'itemid' => (int)$item->id,
                    'type' => $base['blocks'][(int)$raw['id']]['type']];
                $blocks[] = item_content_edit::normalise($raw, $item, $identity, false, false);
                continue;
            }
            $blocks[] = $block && empty($raw['modified']) ? item_content_edit::from_record($block) :
                item_content_edit::normalise($raw, $item, $block, false, false);
        }
        return ['blocks' => $blocks, 'removed' => $state['removed']];
    }

    /** Normalize and reauthorize the complete request; reusable by form validation and commit. */
    public static function validate_request(int $itemid, int $courseid, string $token, string $json,
            string $competencecsv, array $metadata, bool $competencesenabled): array {
        global $SESSION;
        $base = self::read_baseline($token, $itemid, $courseid);
        $completed = $SESSION->block_exaport_item_saves[$base['nonce']] ?? null;
        if ($completed) {
            if (!hash_equals($completed['hash'], self::request_hash($token, $json, $competencecsv, $metadata)) ||
                    ($completed['drafts'] ?? null) !== self::request_drafts($json, $metadata)) {
                self::conflict();
            }
            // Moodle validates the parent form before commit() sees a repeated POST. Do not
            // reject the caller's own successful save as an intervening metadata change.
            return ['replayid' => (int)$completed['id']];
        }
        $item = self::context($itemid, $courseid);
        $state = self::decode_state($json, $base);
        if ($item && !hash_equals($base['metadata'], self::metadata_hash($item))) {
            self::conflict();
        }
        $changes = [];
        $originals = [];
        foreach ($state['blocks'] as $raw) {
            $block = self::identify($raw, $item, $base, empty($raw['modified']));
            if (!empty($raw['id']) && empty($raw['modified'])) {
                continue;
            }
            if ($block && !hash_equals($base['blocks'][(int)$block->id]['hash'], self::block_hash($item, $block))) {
                self::conflict();
            }
            $normal = item_content_edit::normalise($raw, $item, $block, false);
            $changes[] = $normal;
            if ($block) {
                $originals[(int)$block->id] = $block;
            }
        }
        $removed = [];
        foreach ($state['removed'] as $id) {
            $raw = ['id' => $id, 'key' => 'block-' . $id, 'type' => $base['blocks'][$id]['type'] ?? ''];
            $block = self::identify($raw, $item, $base);
            if (!hash_equals($base['blocks'][$id]['hash'], self::block_hash($item, $block))) {
                self::conflict();
            }
            $removed[$id] = $block;
        }
        $competenceids = null;
        if ($competencesenabled) {
            $ids = block_exaport_parse_competenceids($competencecsv);
            sort($ids, SORT_NUMERIC);
            if ($ids !== $base['competenceids']) {
                if (empty($base['competencesenabled']) || self::competence_ids($item) !== $base['competenceids']) {
                    self::conflict();
                }
                $competenceids = block_exaport_validate_item_competenceids($item, $ids);
            }
        }
        $plan = ['item' => $item, 'base' => $base, 'changes' => $changes, 'originals' => $originals,
            'removed' => $removed, 'competenceids' => $competenceids];
        self::validate_quota($item, $plan, $metadata);
        return $plan;
    }

    /**
     * Atomically save metadata and pending changes. The callback reuses the existing item add/edit functions.
     *
     * App writers also update the parent before its blocks. A parent write acquires that row's DB lock;
     * checking fingerprints afterwards observes any app transaction that had already acquired it.
     */
    public static function commit(int $itemid, int $courseid, \stdClass $data, callable $callback,
            bool $competencesenabled): int {
        global $DB, $SESSION, $USER;
        if ($competencesenabled && !property_exists($data, 'competenceids')) {
            self::invalid();
        }
        $base = self::read_baseline((string)$data->editorbaseline, $itemid, $courseid);
        $requesthash = self::request_hash((string)$data->editorbaseline, (string)$data->contentblocks,
            (string)($data->competenceids ?? ''), (array)$data);
        $nonce = $base['nonce'];
        $completed = $SESSION->block_exaport_item_saves[$nonce] ?? null;
        if ($completed) {
            if (!hash_equals($completed['hash'], $requesthash) || !$DB->record_exists('block_exaportitem',
                    ['id' => $completed['id'], 'userid' => $USER->id]) ||
                    ($completed['drafts'] ?? null) !== self::request_drafts((string)$data->contentblocks, (array)$data)) {
                self::conflict();
            }
            return (int)$completed['id'];
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
                $plan = self::validate_request($itemid, $courseid, (string)$data->editorbaseline,
                    (string)$data->contentblocks, (string)($data->competenceids ?? ''), (array)$data, $competencesenabled);
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
                // Snapshot after all File API normalization, while a failed lookup
                // can still roll back the save rather than strand a completed POST.
                $draftsnapshot = self::request_drafts((string)$data->contentblocks, (array)$data);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
            // This is a small cache of completed saves, never the pending editor state. Moodle's
            // session lock serialises browser retries; a repeated POST returns its already saved item.
            $SESSION->block_exaport_item_saves = array_filter($SESSION->block_exaport_item_saves ?? [],
                static function(array $entry): bool { return $entry['created'] >= time() - DAYSECS; });
            $SESSION->block_exaport_item_saves[$nonce] = ['id' => $savedid, 'hash' => $requesthash,
                'drafts' => $draftsnapshot, 'created' => time()];
            return $savedid;
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

    /** Strict schema bounds are independent of the number or size of uploaded file bytes. */
    private static function decode_state(string $json, array $base): array {
        try {
            if ($json === '' || strlen($json) > $base['maxbytes']) {
                self::invalid();
            }
            $state = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($state) || !isset($state['blocks'], $state['removed']) ||
                    !is_array($state['blocks']) || !array_is_list($state['blocks']) ||
                    !is_array($state['removed']) || !array_is_list($state['removed']) ||
                    count($state['blocks']) > $base['maxblocks'] || count($state['removed']) > count($base['blocks'])) {
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
                if (!$id || !isset($base['blocks'][$id]) || isset($ids[$id]) || isset($removed[$id])) {
                    self::invalid();
                }
                $removed[$id] = true;
            }
            return $state;
        } catch (\JsonException $exception) {
            self::invalid();
        }
    }

    /** Client identities never grant access to records outside the signed initial set. */
    private static function identify(array $raw, ?\stdClass $item, array $base, bool $allowmissing = false): ?\stdClass {
        global $DB;
        $id = self::integer($raw['id'] ?? null);
        $key = $raw['key'] ?? '';
        if (!$id) {
            if (!is_string($key) || !preg_match('/\Anew-[A-Za-z0-9_-]{1,100}\z/', $key) ||
                    !in_array($raw['type'] ?? '', block_exaport_item_content_types(), true)) {
                self::invalid();
            }
            return null;
        }
        if (!$item || $key !== 'block-' . $id || !isset($base['blocks'][$id]) ||
                ($raw['type'] ?? '') !== $base['blocks'][$id]['type']) {
            self::invalid();
        }
        $block = $DB->get_record('block_exaportitemblock', ['id' => $id, 'itemid' => $item->id]);
        if (!$block) {
            if ($allowmissing) {
                return null;
            }
            self::conflict();
        }
        if ($block->type !== $raw['type']) {
            self::conflict();
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

    /** Fingerprints contain saved references, never the changing contents of a linked CV source. */
    private static function block_hash(\stdClass $item, \stdClass $block): string {
        return hash('sha256', json_encode([(array)$block,
            self::files_hash((int)$item->userid, 'item_content_file', (int)$block->id),
            self::files_hash((int)$item->userid, 'item_content_text', (int)$block->id)], JSON_THROW_ON_ERROR));
    }

    private static function metadata_hash(\stdClass $item): string {
        global $DB, $CFG;
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = (string)($item->{$field} ?? '');
        }
        // The existing metadata saver also writes the form's navigation course.
        // Detect an API course change before that older value can overwrite it.
        $values['courseid'] = (int)$item->courseid;
        $values['categories'] = array_map('intval', $DB->get_fieldset_select('block_exaportitemcate', 'cateid', 'itemid = ?', [$item->id]));
        sort($values['categories'], SORT_NUMERIC);
        $values['tags'] = !empty($CFG->usetags) ? array_values(\core_tag_tag::get_item_tags_array('block_exaport',
            'block_exaportitem', $item->id, \core_tag_tag::BOTH_STANDARD_AND_NOT)) : [];
        sort($values['tags'], SORT_STRING);
        $values['shareall'] = (int)($item->shareall ?? 0);
        $values['users'] = $DB->get_records('block_exaportitemshar', ['itemid' => $item->id], 'userid', 'userid,notify');
        $values['groups'] = $DB->get_fieldset_select('block_exaportitemgroupshar', 'groupid', 'itemid = ?', [$item->id]);
        sort($values['groups'], SORT_NUMERIC);
        foreach (self::parent_areas((int)$item->id) as [$area, $id]) {
            $values[$area] = self::files_hash((int)$item->userid, $area, $id);
        }
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    private static function files_hash(int $userid, string $area, int $id): array {
        $result = [];
        foreach (get_file_storage()->get_area_files(\context_user::instance($userid)->id,
                'block_exaport', $area, $id, 'filepath,filename', false) as $file) {
            $result[] = [$file->get_filepath(), $file->get_filename(), $file->get_contenthash(),
                $file->get_source(), $file->get_author(), $file->get_license()];
        }
        return $result;
    }

    private static function parent_areas(int $id): array {
        $areas = [['item_iconfile', $id]];
        foreach (self::EDITORS as $field) {
            $areas[] = ['item_content_' . $field, $id];
        }
        return $areas;
    }

    /** Hash business inputs, not Moodle's submission marker or values added by the save callback. */
    private static function request_hash(string $token, string $json, string $competences, array $data): string {
        $values = ['baseline' => $token, 'blocks' => $json, 'competences' => $competences];
        foreach (self::FIELDS as $field) {
            $values[$field] = (string)($data[$field] ?? '');
            if (isset($data[$field . '_editor'])) {
                $values[$field . '_editor'] = $data[$field . '_editor'];
            }
        }
        $categories = array_values(array_unique(array_filter(array_map('intval',
            (array)($data['categoryids'] ?? [])), static function(int $id): bool { return $id > 0; })));
        sort($categories, SORT_NUMERIC);
        $values['categoryids'] = $categories;
        $values['tags'] = (array)($data['tags'] ?? []);
        $values['iconfile'] = (int)($data['iconfile'] ?? 0);
        foreach (['shareenabled', 'shareall'] as $field) {
            $values[$field] = (int)($data[$field] ?? optional_param($field, 0, PARAM_INT));
        }
        foreach (['shareusers', 'notifyusers', 'sharegroups'] as $field) {
            $values[$field] = (array)($data[$field] ?? optional_param_array($field, [], PARAM_INT));
        }
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    /**
     * A draft ID names mutable files. A browser Back edit can replace bytes without
     * changing any submitted field. Save the final logical draft contents separately
     * from the business-input hash: native editor merging may add files during Save,
     * so a pre-save fingerprint would incorrectly reject an otherwise identical retry.
     */
    private static function request_drafts(string $json, array $data): array {
        global $USER;
        $ids = [];
        if (!empty($data['iconfile'])) {
            $ids[self::integer($data['iconfile'])] = true;
        }
        foreach (self::EDITORS as $field) {
            if (!empty($data[$field . '_editor']['itemid'])) {
                $ids[self::integer($data[$field . '_editor']['itemid'])] = true;
            }
        }
        try {
            $state = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            self::invalid();
        }
        foreach ($state['blocks'] ?? [] as $block) {
            $id = $block['type'] === 'file' ? ($block['files_filemanager'] ?? 0) :
                ($block['type'] === 'text' ? ($block['content_editor']['itemid'] ?? 0) : 0);
            if ($id) {
                $ids[self::integer($id)] = true;
            }
        }
        ksort($ids, SORT_NUMERIC);
        $result = [];
        $context = \context_user::instance($USER->id);
        foreach ($ids as $id => $unused) {
            $files = [];
            foreach (get_file_storage()->get_area_files($context->id, 'user', 'draft', $id,
                    'filepath,filename', false) as $file) {
                // Source wrappers, IDs and timestamps can be normalized by Moodle.
                // These values describe the content/metadata the user actually edits.
                $files[] = [$file->get_filepath(), $file->get_filename(), $file->get_contenthash(),
                    (int)$file->get_filesize(), $file->get_author(), $file->get_license()];
            }
            $result[$id] = hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
        }
        return $result;
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

    private static function conflict(): void {
        throw new \moodle_exception('itemeditorconflict', 'block_exaport');
    }
}
