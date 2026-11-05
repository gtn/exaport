<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software under the GNU General Public License, version 3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

/** Integration coverage for template snapshots, responses and shared artefacts. */
final class view_structure_test extends \advanced_testcase {
    private function template(int $courseid, string $name = 'Reflection', ?array $definition = null): \stdClass {
        global $DB;
        $id = view_template::add_view($courseid, $name);
        view_template::save_structure($id, $courseid, $definition ?? [
            'nodes' => [['type' => 'field', 'label' => 'Learning goal', 'multiline' => true]],
        ]);
        return $DB->get_record('block_exaport_view_templ', ['id' => $id], '*', MUST_EXIST);
    }

    private function distribute(\stdClass $template, int $userid): \stdClass {
        global $DB;
        view_distributor::distribute_to_user($userid, [$template], $template->courseid);
        return $DB->get_record('block_exaportview', ['userid' => $userid, 'templateid' => $template->id], '*', MUST_EXIST);
    }

    public function test_distribution_identity_survives_renaming_and_same_titles_in_other_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->template($this->getDataGenerator()->create_course()->id);
        $second = $this->template($this->getDataGenerator()->create_course()->id);
        $view = $this->distribute($first, $owner->id);
        $DB->set_field('block_exaportview', 'name', 'My own title', ['id' => $view->id]);
        $this->assertSame(['created' => 0, 'skipped' => 1],
            view_distributor::distribute_to_user($owner->id, [$first], $first->courseid));
        $other = $this->distribute($second, $owner->id);
        $this->assertNotEquals($view->id, $other->id);
        $this->assertSame(2, $DB->count_records('block_exaportview', ['userid' => $owner->id]));
    }

    public function test_template_edits_and_student_answers_are_independent_snapshots(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $template = $this->template($course->id);
        $view = $this->distribute($template, $first->id);
        $initialblocks = view_structure::blocks($view->id);
        $block = reset($initialblocks);
        $DB->set_field('block_exaportviewblock', 'text', 'My answer', ['id' => $block->id]);
        view_template::save_structure($template->id, $course->id,
            ['nodes' => [['type' => 'field', 'label' => 'A new question']]]);
        $template = $DB->get_record('block_exaport_view_templ', ['id' => $template->id]);
        $other = $this->distribute($template, $second->id);
        $oldblocks = view_structure::blocks($view->id);
        $newblocks = view_structure::blocks($other->id);
        $this->assertSame('My answer', reset($oldblocks)->text);
        $this->assertSame('Learning goal', reset($oldblocks)->config['label']);
        $this->assertSame('A new question', reset($newblocks)->config['label']);
        $this->assertNotSame(reset($oldblocks)->templatekey, reset($newblocks)->templatekey);
        $this->assertLessThan($other->templaterevision, $view->templaterevision);
    }

    public function test_repeat_creates_blank_nodes_and_preserves_minimum(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $template = $this->template($this->getDataGenerator()->create_course()->id, 'Reflection', [
            'nodes' => [['type' => 'section', 'label' => 'Moment', 'repeatable' => true, 'minimum' => 2,
                'children' => [['type' => 'field', 'label' => 'Response']]]],
        ]);
        $owner = $this->getDataGenerator()->create_user();
        $view = $this->distribute($template, $owner->id);
        $blocks = view_structure::blocks($view->id);
        $first = view_structure::children($blocks)[0];
        $answer = view_structure::children($blocks, $first->templatekey)[0];
        $DB->set_field('block_exaportviewblock', 'text', 'Keep this answer', ['id' => $answer->id]);
        view_structure::repeat($view->id, $first->templatekey);
        $blocks = view_structure::blocks($view->id);
        $sections = view_structure::children($blocks);
        $this->assertCount(3, $sections);
        $this->assertSame('', view_structure::children($blocks, $sections[2]->templatekey)[0]->text);
        view_structure::remove($view->id, $sections[2]->templatekey);
        $this->assertSame('Keep this answer', $DB->get_field('block_exaportviewblock', 'text', ['id' => $answer->id]));
        $this->expectException(\invalid_parameter_exception::class);
        view_structure::remove($view->id, $first->templatekey);
    }

    public function test_expanded_repeat_limit_is_checked_before_any_view_is_created(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        view_structure::definition(['nodes' => [['type' => 'section', 'repeatable' => true, 'minimum' => 10,
            'children' => [['type' => 'section', 'repeatable' => true, 'minimum' => 10,
                'children' => [['type' => 'field'], ['type' => 'field']]]]]]]);
    }

    public function test_editor_duplicates_configuration_with_fresh_keys_and_import_drops_item_ids(): void {
        $this->resetAfterTest();
        $definition = view_structure::definition(['nodes' => [['type' => 'section', 'label' => 'Section',
            'children' => [['type' => 'artefact', 'label' => 'Photo', 'itemid' => 123, 'response' => 'Private']]]]]);
        $result = view_template_editor::update($definition, [], 'duplicate:' . $definition['nodes'][0]['key']);
        $this->assertCount(2, $result['nodes']);
        $this->assertNotSame($result['nodes'][0]['key'], $result['nodes'][1]['key']);
        $this->assertNotSame($result['nodes'][0]['children'][0]['key'], $result['nodes'][1]['children'][0]['key']);
        $this->assertArrayNotHasKey('itemid', $result['nodes'][1]['children'][0]);
        $this->assertArrayNotHasKey('response', $result['nodes'][1]['children'][0]);
    }

    public function test_optional_empty_slots_do_not_create_items_and_plain_responses_are_escaped(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $template = $this->template($this->getDataGenerator()->create_course()->id, 'Portfolio', ['nodes' => [
            ['type' => 'field', 'label' => 'Name'], ['type' => 'artefact', 'label' => 'Photo', 'accept' => 'image'],
        ]]);
        $view = $this->distribute($template, $owner->id);
        $blocks = view_structure::blocks($view->id);
        $field = reset($blocks);
        \block_exaport\form\view_structure::save_responses($view, $blocks,
            (object)['value_' . $field->templatekey => '<script>alert(1)</script>']);
        global $DB;
        $this->assertSame(0, $DB->count_records('block_exaportitem', ['userid' => $owner->id]));
        $html = view_structure::render($view);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString(get_string('template_slot_empty', 'block_exaport'), $html);
    }

    public function test_photo_and_reflection_are_normal_owned_content_with_scoped_view_access(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $template = $this->template($this->getDataGenerator()->create_course()->id, 'Reflection', ['nodes' => [
            ['type' => 'artefact', 'label' => 'Photo', 'accept' => 'image', 'reflection' => 'What did you learn?'],
        ]]);
        $view = $this->distribute($template, $owner->id);
        $blocks = view_structure::blocks($view->id);
        $slot = reset($blocks);
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'moment.png', 'mimetype' => 'image/png',
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        \block_exaport\form\view_structure::save_responses($view, $blocks, (object)[
            'file_' . $slot->templatekey => $draftid, 'reflection_' . $slot->templatekey => 'I learned to plan ahead.',
        ]);
        $blocks = view_structure::blocks($view->id);
        $filled = reset($blocks);
        $this->assertSame('item', $filled->type);
        $this->assertSame($owner->id, $DB->get_field('block_exaportitem', 'userid', ['id' => $filled->itemid]));
        $content = block_exaport_get_item_content_blocks($filled->itemid);
        $this->assertSame(['file', 'text'], array_column($content, 'type'));
        $access = 'id/' . $owner->id . '-' . $view->id;
        $html = view_structure::render($view, $access);
        $pdf = view_structure::render($view, $access, true);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('I learned to plan ahead.', $html);
        $this->assertStringContainsString('I learned to plan ahead.', $pdf);
        $this->assertStringContainsString('<img', $pdf);
        $token = \block_exaport\form\view_structure::token($blocks);
        $DB->set_field('block_exaportitemblock', 'content', 'A newer reflection', ['id' => $content[1]->id]);
        $this->assertNotSame($token, \block_exaport\form\view_structure::token($blocks));
        $this->assertNotFalse(block_exaport_get_item($filled->itemid, 'view/' . $access));
        $unrelatedid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id, 'type' => 'note', 'name' => 'Unrelated private artefact',
        ]);
        $this->assertFalse(block_exaport_get_item($unrelatedid, 'view/' . $access));
        $item = $DB->get_record('block_exaportitem', ['id' => $filled->itemid]);
        block_exaport_delete_item($item);
        $remaining = view_structure::blocks($view->id);
        $this->assertSame($slot->templatekey, reset($remaining)->templatekey);
        $this->assertSame('template_artefact', reset($remaining)->type);
        $this->assertEquals(0, reset($remaining)->itemid);
    }

    public function test_starter_import_validates_every_view_before_replacing_existing_templates(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $original = $this->template($course->id);
        set_config('starter_view_templates', json_encode([['name' => 'Broken', 'views' => [
            ['name' => 'Valid'], ['name' => 'Invalid', 'structure' => ['version' => 99]],
        ]]]), 'block_exaport');
        try {
            view_template::load_starter_template($course->id, 'Broken');
            $this->fail('Invalid imported structure was accepted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertTrue($DB->record_exists('block_exaport_view_templ', ['id' => $original->id]));
            $this->assertSame(1, $DB->count_records('block_exaport_view_templ', ['courseid' => $course->id]));
        }
    }

    public function test_distribution_shares_to_teachers_of_the_template_course_including_the_initiator(): void {
        global $DB, $COURSE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $otherteacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        foreach ([$teacher, $otherteacher] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        }
        $this->setUser($teacher);
        $COURSE = get_site(); // Same context as an enrolment task, without an open course page.
        set_config('alwaysnotifywhenshare', 0, 'block_exaport');
        $template = $this->template($course->id);
        $template->share_to_teachers = 1;
        $view = $this->distribute($template, $student->id);
        $this->assertTrue($DB->record_exists('block_exaportviewshar', ['viewid' => $view->id, 'userid' => $teacher->id]));
        $this->assertTrue($DB->record_exists('block_exaportviewshar', ['viewid' => $view->id, 'userid' => $otherteacher->id]));
    }

    public function test_server_validation_rejects_wrong_file_types_even_if_draft_was_created_elsewhere(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $template = $this->template($this->getDataGenerator()->create_course()->id, 'Images', [
            'nodes' => [['type' => 'artefact', 'label' => 'Photo', 'accept' => 'image']],
        ]);
        $view = $this->distribute($template, $owner->id);
        $blocks = view_structure::blocks($view->id);
        $slot = reset($blocks);
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'document.txt', 'mimetype' => 'text/plain',
        ], 'This is not a photograph');
        $form = new \block_exaport\form\view_structure(null, ['view' => $view, 'blocks' => $blocks]);
        $errors = $form->validation(['file_' . $slot->templatekey => $draftid], []);
        $this->assertArrayHasKey('file_' . $slot->templatekey, $errors);
    }

    public function test_narrowing_a_layout_keeps_every_template_column_visible(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $template = $this->template($this->getDataGenerator()->create_course()->id, 'Columns', [
            'columns' => 3, 'nodes' => [
                ['type' => 'field', 'label' => 'First', 'column' => 1],
                ['type' => 'field', 'label' => 'Second', 'column' => 2],
                ['type' => 'field', 'label' => 'Third', 'column' => 3],
            ],
        ]);
        $view = $this->distribute($template, $owner->id);
        $view->layout = 1;
        $html = view_structure::render($view, '', false, 1);
        foreach (['First', 'Second', 'Third'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_user_context_privacy_deletion_removes_text_only_template_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $template = $this->template($this->getDataGenerator()->create_course()->id);
        $view = $this->distribute($template, $owner->id);
        $untouched = $this->distribute($template, $other->id);
        \block_exaport\privacy\provider::delete_data_for_all_users_in_context(\context_user::instance($owner->id));
        $this->assertFalse($DB->record_exists('block_exaportview', ['id' => $view->id]));
        $this->assertFalse($DB->record_exists('block_exaportviewblock', ['viewid' => $view->id]));
        $this->assertTrue($DB->record_exists('block_exaportview', ['id' => $untouched->id]));
    }

    public function test_an_outdated_editor_cannot_overwrite_a_newer_template_revision(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $template = $this->template($course->id);
        view_template::save_structure($template->id, $course->id, [
            'nodes' => [['type' => 'field', 'label' => 'Newer question']],
        ], (int)$template->revision);
        try {
            view_template::save_structure($template->id, $course->id, ['nodes' => []], (int)$template->revision);
            $this->fail('A stale editor overwrote the newer template');
        } catch (\moodle_exception $e) {
            $this->assertSame('template_changed', $e->errorcode);
            $stored = $DB->get_field('block_exaport_view_templ', 'structure', ['id' => $template->id]);
            $this->assertSame('Newer question', view_structure::definition($stored)['nodes'][0]['label']);
        }
    }
}
