<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../lib/scorm_export_helpers.php');

use block_exaport\local\item_linked_content;

/**
 * Integration coverage for live selections, authorisation and portable copies.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_linked_content_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
    }

    /** Create a parent item owned by a known user. */
    private function item(int $ownerid): \stdClass {
        global $DB;
        $id = $DB->insert_record('block_exaportitem', [
            'userid' => $ownerid, 'courseid' => SITEID, 'name' => 'Linked content', 'type' => 'note',
        ]);
        return $DB->get_record('block_exaportitem', ['id' => $id]);
    }

    /** Create a central CV and two individually selectable education entries. */
    private function cv(int $ownerid): array {
        global $DB;
        $resumeid = $DB->insert_record('block_exaportresume', [
            'user_id' => $ownerid, 'cover' => '<p>My introduction</p>',
        ]);
        $ids = [];
        foreach (['Selected qualification', 'Private qualification'] as $name) {
            $ids[] = (int)$DB->insert_record('block_exaportresume_edu', [
                'resume_id' => $resumeid, 'startdate' => '2020', 'institution' => 'University',
                'qualname' => $name, 'sorting' => count($ids),
                'qualdescription' => '<script>alert(1)</script><p onclick="alert(2)">Description</p>',
            ]);
        }
        return [$resumeid, $ids[0], $ids[1]];
    }

    /** Store a validated selection in a real block. */
    private function block(\stdClass $item, string $type, array $selection, bool $files = false): \stdClass {
        return block_exaport_create_content_block($item->id, $type, 'Selected information', '', [
            'content' => item_linked_content::configuration($type, (int)$item->userid, $selection, $files),
        ]);
    }

    /** Store an attachment in the existing CV file area. */
    private function file(int $ownerid, int $entryid, string $body = '<p>Safe attachment</p>'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($ownerid)->id, 'component' => 'block_exaport',
            'filearea' => 'resume_edu', 'itemid' => $entryid, 'filepath' => '/', 'filename' => 'report.html',
            'mimetype' => 'text/html',
        ], $body);
    }

    public function test_personal_fields_are_live_explicit_and_belong_to_the_owner(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user(['firstname' => 'Original', 'email' => 'private@example.test']);
        $item = $this->item($owner->id);
        $block = $this->block($item, 'personal', ['firstname']);
        $viewer = $this->getDataGenerator()->create_user(['firstname' => 'Viewer']);
        $this->setUser($viewer);
        $html = item_linked_content::render($block, (int)$owner->id);
        $this->assertStringContainsString('Original', $html);
        $this->assertStringNotContainsString('private@example.test', $html);
        $this->assertStringNotContainsString('Viewer', $html);
        $DB->set_field('user', 'firstname', '<script>Changed</script>', ['id' => $owner->id]);
        $html = item_linked_content::render($block, (int)$owner->id);
        $this->assertStringContainsString('&lt;script&gt;Changed&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('', item_linked_content::render($block, (int)$viewer->id));
    }

    public function test_cv_changes_are_live_but_new_and_deleted_entries_are_not_published(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        [$resumeid, $selected, $private] = $this->cv($owner->id);
        $block = $this->block($this->item($owner->id), 'cv', ['edu:' . $selected]);
        $html = item_linked_content::render($block, (int)$owner->id);
        $this->assertStringContainsString('Selected qualification', $html);
        $this->assertStringNotContainsString('Private qualification', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $DB->set_field('block_exaportresume_edu', 'qualname', 'Updated qualification', ['id' => $selected]);
        $this->assertStringContainsString('Updated qualification', item_linked_content::render($block, (int)$owner->id));
        $DB->delete_records('block_exaportresume_edu', ['id' => $selected]);
        $this->assertSame('', item_linked_content::render($block, (int)$owner->id));
        $this->assertTrue($DB->record_exists('block_exaportresume', ['id' => $resumeid]));
        $this->assertTrue($DB->record_exists('block_exaportresume_edu', ['id' => $private]));
    }

    /** @return array CV fields whose attachments belong to the central CV record. */
    public static function cv_attachment_fields(): array {
        return [
            'personal goals' => ['goalspersonal'],
            'academic goals' => ['goalsacademic'],
            'career goals' => ['goalscareers'],
            'personal skills' => ['skillspersonal'],
            'academic skills' => ['skillsacademic'],
            'career skills' => ['skillscareers'],
        ];
    }

    /**
     * Linked fields use the same attachment area as the existing CV editor and views.
     *
     * @dataProvider cv_attachment_fields
     */
    public function test_cv_field_attachments_belong_to_the_selected_cv(string $field): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        [$resumeid] = $this->cv($owner->id);
        $DB->set_field('block_exaportresume', $field, '<p>Selected CV field</p>', ['id' => $resumeid]);
        $context = \context_user::instance($owner->id);
        $fileinfo = [
            'contextid' => $context->id, 'component' => 'block_exaport', 'filearea' => 'resume_' . $field,
            'itemid' => $resumeid, 'filepath' => '/', 'filename' => 'evidence.txt', 'userid' => $owner->id,
        ];
        $file = get_file_storage()->create_file_from_string($fileinfo, 'CV evidence');
        // An unrelated itemid must not be used as a fallback for the selected CV's files.
        get_file_storage()->create_file_from_string(array_replace($fileinfo, [
            'itemid' => 0, 'filename' => 'unrelated.txt',
        ]), 'Unrelated bytes');

        $block = $this->block($this->item($owner->id), 'cv', [$field], true);
        $resolved = item_linked_content::resolve($block, (int)$owner->id);
        $this->assertSame([(int)$file->get_id()], array_keys($resolved['editorfiles']));
        $this->assertStringContainsString('evidence.txt', $resolved['content']);
        $this->assertStringNotContainsString('unrelated.txt', $resolved['content']);

        $block->content = item_linked_content::configuration('cv', (int)$owner->id, [$field], false);
        $this->assertSame([], item_linked_content::resolve($block, (int)$owner->id)['editorfiles']);
    }

    public function test_foreign_cv_selection_is_rejected_and_tampered_storage_is_not_rendered(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        [, $entry] = $this->cv($other->id);
        $item = $this->item($owner->id);
        $forged = (object)['itemid' => $item->id, 'id' => 99, 'type' => 'cv',
            'content' => json_encode(['ownerid' => (int)$owner->id, 'selection' => ['edu:' . $entry]])];
        $this->assertSame('', item_linked_content::render($forged, (int)$owner->id));
        $this->expectException(\invalid_parameter_exception::class);
        item_linked_content::configuration('cv', (int)$owner->id, ['edu:' . $entry]);
    }

    public function test_reading_an_empty_cv_does_not_create_personal_data(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $this->assertSame([], item_linked_content::options('cv', (int)$owner->id));
        $this->assertFalse($DB->record_exists('block_exaportresume', ['user_id' => $owner->id]));
    }

    public function test_about_me_exposes_only_referenced_editor_files_and_keeps_them_live(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        [$resumeid] = $this->cv($owner->id);
        $files = [];
        foreach (['photo.png', 'photo.png.backup'] as $filename) {
            $files[$filename] = get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($owner->id)->id, 'component' => 'block_exaport',
                'filearea' => 'resume_editor_cover', 'itemid' => $resumeid, 'filepath' => '/', 'filename' => $filename,
            ], 'example image');
        }
        $DB->set_field('block_exaportresume', 'cover', '<p>About me<img src="@@PLUGINFILE@@/photo.png.backup"></p>',
            ['id' => $resumeid]);
        $block = $this->block($this->item($owner->id), 'personal', ['cover']);
        $resolved = item_linked_content::resolve($block, (int)$owner->id);
        $this->assertSame([(int)$files['photo.png.backup']->get_id()], array_keys($resolved['editorfiles']));
        $DB->set_field('block_exaportresume', 'cover', '<p>Updated introduction</p>', ['id' => $resumeid]);
        $resolved = item_linked_content::resolve($block, (int)$owner->id);
        $this->assertSame([], $resolved['editorfiles']);
        $this->assertStringContainsString('Updated introduction', $resolved['content']);
    }

    /**
     * File responses send HTTP headers and need an isolated PHP process.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_attachment_routes_require_item_access_and_the_exact_current_selection(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        [, $selected, $private] = $this->cv($owner->id);
        $item = $this->item($owner->id);
        $block = $this->block($item, 'cv', ['edu:' . $selected], true);
        $file = $this->file($owner->id, $selected, '<script>alert(1)</script><p>Safe attachment</p>');
        $privatefile = $this->file($owner->id, $private);
        $context = \context_user::instance($owner->id);
        $args = ['itemid', $item->id, 'blockid', $block->id, $file->get_id(), $file->get_filename()];
        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);
        $this->assertFalse(block_exaport_pluginfile(null, null, $context, 'item_content_linked', $args, false));
        $this->setUser($owner);
        $html = item_linked_content::render($block, (int)$owner->id, 'portfolio/id/' . $owner->id);
        $this->assertStringContainsString('/item_content_linked/portfolio/id/', $html);
        $this->assertStringNotContainsString('/resume_edu/', $html);
        ob_start();
        try {
            block_exaport_pluginfile(null, null, $context, 'item_content_linked', $args, false, ['dontdie' => true]);
            $response = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('Safe attachment', $response);
        $this->assertStringNotContainsString('<script', $response);
        $args[4] = $privatefile->get_id();
        $this->assertFalse(block_exaport_pluginfile(null, null, $context, 'item_content_linked', $args, false));
        $args[4] = $file->get_id();
        $DB->set_field('block_exaportitemblock', 'content',
            item_linked_content::configuration('cv', (int)$owner->id, ['edu:' . $selected]), ['id' => $block->id]);
        $this->assertFalse(block_exaport_pluginfile(null, null, $context, 'item_content_linked', $args, false));
    }

    public function test_copying_preserves_own_links_and_snapshots_cross_owner_content_and_files(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        [, $selected] = $this->cv($owner->id);
        $source = $this->item($owner->id);
        $this->block($source, 'cv', ['edu:' . $selected], true);
        $file = $this->file($owner->id, $selected);
        $owncopy = $this->item($owner->id);
        block_exaport_copy_item_content($source, $owncopy);
        $this->assertSame('cv', block_exaport_get_item_content_blocks($owncopy->id)[0]->type);
        $other = $this->getDataGenerator()->create_user();
        $copy = $this->item($other->id);
        block_exaport_copy_item_content($source, $copy);
        $copiedblock = block_exaport_get_item_content_blocks($copy->id)[0];
        $this->assertSame('text', $copiedblock->type);
        $this->assertStringContainsString('Selected qualification', $copiedblock->content);
        $this->assertStringNotContainsString('Private qualification', $copiedblock->content);
        $this->assertNotFalse(get_file_storage()->get_file(\context_user::instance($other->id)->id,
            'block_exaport', 'item_content_text', $copiedblock->id, '/' . $file->get_id() . '/', $file->get_filename()));
        $DB->set_field('block_exaportresume_edu', 'qualname', 'Later change', ['id' => $selected]);
        $ownblock = block_exaport_get_item_content_blocks($owncopy->id)[0];
        $this->assertStringContainsString('Later change', item_linked_content::render($ownblock, (int)$owner->id));
        $this->assertStringNotContainsString('Later change', $copiedblock->content);
    }

    public function test_exports_snapshot_selected_values_and_package_same_named_source_files_separately(): void {
        $owner = $this->getDataGenerator()->create_user();
        [, $first, $second] = $this->cv($owner->id);
        $item = $this->item($owner->id);
        $this->block($item, 'cv', ['edu:' . $first, 'edu:' . $second], true);
        $this->file($owner->id, $first);
        $this->file($owner->id, $second, 'Different content');
        $projection = block_exaport_get_item_content_export_data($item);
        $this->assertSame('text', $projection[0]['type']);
        $this->assertStringNotContainsString('ownerid', $projection[0]['content']);
        $this->assertCount(2, $projection[0]['editorfiles']);
        $package = block_exaport_scorm_build_item_package($item, $projection, 'portfolio/page.html',
            static function(\stored_file $file, string $directory): string {
                return $directory . $file->get_filepath() . $file->get_filename();
            });
        $this->assertCount(2, array_unique($package['assets']));
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $package['html']);
        $this->assertCount(2, $package['manifest']['blocks'][0]['textassets']);
        $webservice = block_exaport_get_item_content_webservice_data($item, 'testtoken');
        $this->assertStringContainsString('/webservice/pluginfile.php/', $webservice['contentblocks'][0]->content);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $webservice['contentblocks'][0]->content);
    }

    public function test_badges_must_be_earned_and_remain_linked_to_current_issuance(): void {
        global $DB;
        set_config('enablebadges', 1);
        $owner = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        $badge = $generator->create_badge(['name' => 'Earned badge']);
        $unearned = $generator->create_badge(['name' => 'Not earned']);
        $generator->create_issued_badge(['badgeid' => $badge->id, 'userid' => $owner->id]);
        $block = $this->block($this->item($owner->id), 'badge', [(string)$badge->id]);
        $html = item_linked_content::render($block, (int)$owner->id);
        $this->assertStringContainsString('Earned badge', $html);
        $this->assertArrayNotHasKey($unearned->id, item_linked_content::options('badge', (int)$owner->id));
        $DB->set_field('badge', 'name', 'Updated badge', ['id' => $badge->id]);
        $this->assertStringContainsString('Updated badge', item_linked_content::render($block, (int)$owner->id));
        $DB->delete_records('badge_issued', ['badgeid' => $badge->id, 'userid' => $owner->id]);
        $this->assertSame('', item_linked_content::render($block, (int)$owner->id));
        set_config('enablebadges', 0);
        $this->assertSame([], item_linked_content::options('badge', (int)$owner->id));
    }

    public function test_dynamic_form_saves_links_and_rejects_foreign_selections(): void {
        global $DB, $USER;
        [, $entry] = $this->cv($USER->id);
        $item = $this->item($USER->id);
        $data = ['courseid' => SITEID, 'itemid' => $item->id, 'contenttype' => 'cv', 'operation' => 'save',
            'title' => 'My CV', 'selection' => ['edu:' . $entry], 'withfiles' => 0];
        $submit = static function(array $data): array {
            $data = \block_exaport\form\item_content::mock_ajax_submit($data);
            return \core_form\external\dynamic_form::execute(
                \block_exaport\form\item_content::class, http_build_query($data, '', '&')
            );
        };
        $response = $submit($data);
        $this->assertTrue($response['submitted']);
        $block = block_exaport_get_item_content_blocks($item->id)[0];
        $this->assertSame(['edu:' . $entry], item_linked_content::selection($block, (int)$USER->id)['selection']);
        $other = $this->getDataGenerator()->create_user();
        [, $foreign] = $this->cv($other->id);
        $response = $submit(array_replace($data, ['selection' => ['edu:' . $foreign]]));
        $this->assertFalse($response['submitted']);
        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
    }
}
