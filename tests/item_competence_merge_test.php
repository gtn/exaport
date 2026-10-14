<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once(__DIR__ . '/../lib/item_competence_helpers.php');

/**
 * Exercise competence submissions and assignments outside the current tree.
 *
 * @package block_exaport
 * @covers \block_exaport\form\item_competences
 * @covers \block_exaport\output\item_competences
 * @covers ::block_exaport_validate_item_competenceids
 */
final class item_competence_merge_test extends \advanced_testcase {
    /** Create an owned item with two assignments absent from its owner's current tree. */
    private function create_fixture(): array {
        global $CFG, $DB, $PAGE, $OUTPUT;

        $this->resetAfterTest(true);
        $CFG->block_exaport_enable_interaction_competences = true;
        $CFG->block_exaport_app_alloweditdelete = true;
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($owner);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item.php');
        $OUTPUT = $PAGE->get_renderer('core');
        $item = (object)[
            'userid' => $owner->id, 'courseid' => $course->id, 'name' => 'Competence merge',
            'type' => 'note', 'intro' => '', 'url' => '', 'attachment' => '',
            'categoryid' => 0, 'timemodified' => time(),
        ];
        $item->id = $DB->insert_record('block_exaportitem', $item);
        $ids = [];
        foreach (['Former course competence', 'Other former competence'] as $title) {
            $id = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => $title]);
            $ids[] = (int)$id;
            $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, [
                'activityid' => $item->id, 'compid' => $id, 'eportfolioitem' => 1,
            ]);
        }
        return [$item, $ids];
    }

    /** Construct the same AJAX modal used by the browser, including its access checks. */
    private function create_form(\stdClass $item, array $ids): \block_exaport\form\item_competences {
        $ajax = \block_exaport\form\item_competences::mock_ajax_submit([
            'itemid' => $item->id, 'courseid' => $item->courseid, 'competenceids' => implode(',', $ids),
        ]);
        return new \block_exaport\form\item_competences(null, null, 'post', '', [], true, $ajax, true);
    }

    public function test_validation_accepts_existing_assignments_and_reports_forged_ids(): void {
        global $DB;

        [$item, $ids] = $this->create_fixture();
        $form = $this->create_form($item, $ids);
        $data = ['itemid' => $item->id, 'courseid' => $item->courseid, 'competenceids' => implode(',', $ids)];
        $this->assertEmpty($form->validation($data, []));
        $foreignid = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Unassigned private competence']);
        $data['competenceids'] = (string)$foreignid;
        $errors = $form->validation($data, []);
        $this->assertSame(get_string('invaliddata', 'error'), $errors['competenceids']);
        $data['competenceids'] = 'not-an-id';
        $this->assertArrayHasKey('competenceids', $form->validation($data, []));
        $this->assertEqualsCanonicalizing($ids, $DB->get_fieldset_select(
            BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'compid', 'activityid = ? AND eportfolioitem = 1', [$item->id]));
    }

    public function test_rejected_submission_shows_an_error_in_the_modal(): void {
        global $DB;

        [$item, $ids] = $this->create_fixture();
        $foreignid = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Unassigned competence']);
        $form = $this->create_form($item, [(int)$foreignid]);
        $this->assertNull($form->get_data());
        $this->assertStringContainsString(get_string('invaliddata', 'error'), $form->render());
        $this->assertEqualsCanonicalizing($ids, $DB->get_fieldset_select(
            BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'compid', 'activityid = ? AND eportfolioitem = 1', [$item->id]));
    }

    public function test_validation_reauthorizes_the_submitted_item(): void {
        [$item, $ids] = $this->create_fixture();
        $form = $this->create_form($item, $ids);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        $form->validation([
            'itemid' => $item->id, 'courseid' => $item->courseid, 'competenceids' => implode(',', $ids),
        ], []);
    }

    public function test_delegated_check_rejects_an_outdated_tree_snapshot(): void {
        global $DB;

        [$item, $ids] = $this->create_fixture();
        if (!method_exists(\block_exacomp\api::class, 'require_can_access_descriptors_for_exaport')) {
            $this->markTestSkipped('This Exacomp version does not provide the delegated descriptor check.');
        }
        $foreignid = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'No longer allowed']);
        $descriptor = new class((int)$foreignid) extends \block_exacomp\descriptor {
            private array $children = [];

            public function __construct(int $id) {
                $this->id = $id;
                $this->title = 'No longer allowed';
            }

            public function &get_subs(): array {
                return $this->children;
            }
        };

        $this->expectException(\block_exacomp_permission_exception::class);
        block_exaport_validate_item_competenceids($item, [(int)$foreignid], [$descriptor]);
    }

    public function test_submission_can_retain_and_explicitly_remove_former_assignments(): void {
        global $DB;

        [$item, $ids] = $this->create_fixture();
        $form = $this->create_form($item, [$ids[0]]);
        $this->assertNotNull($form->get_data());
        $result = $form->process_dynamic_submission();
        $this->assertStringContainsString('Former course competence', $result['content']);
        $this->assertStringNotContainsString('Other former competence', $result['content']);
        $this->assertEqualsCanonicalizing([$ids[0]], $DB->get_fieldset_select(
            BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'compid', 'activityid = ? AND eportfolioitem = 1', [$item->id]));
    }

    public function test_submission_can_clear_all_assignments(): void {
        global $DB;

        [$item, $ids] = $this->create_fixture();
        $form = $this->create_form($item, []);
        $this->assertNotNull($form->get_data());
        $form->process_dynamic_submission();
        $this->assertFalse($DB->record_exists(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $item->id, 'eportfolioitem' => 1]));
        $this->assertFalse($DB->record_exists(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
            ['activityid' => $item->id, 'eportfolioitem' => 1]));
    }

    public function test_picker_and_summary_show_only_persisted_out_of_tree_assignments(): void {
        global $DB, $OUTPUT;

        [$item, $ids] = $this->create_fixture();
        $foreignid = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Never assigned']);
        $otheritem = clone $item;
        unset($otheritem->id);
        $otheritem->userid = $this->getDataGenerator()->create_user()->id;
        $otheritem->id = $DB->insert_record('block_exaportitem', $otheritem);
        $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, [
            'activityid' => $otheritem->id, 'compid' => $foreignid, 'eportfolioitem' => 1,
        ]);
        $item->compids_array = array_merge($ids, [$foreignid]);
        $renderable = new \block_exaport\output\item_competences($item, true);
        $data = $renderable->export_for_template($OUTPUT);
        $picker = $OUTPUT->render_from_template('block_exaport/item_competence_picker', $data['picker']);
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/value="' . $id . '"[^>]*checked/', $picker);
        }
        $this->assertStringContainsString('Former course competence', $picker);
        $this->assertStringNotContainsString('Never assigned', $picker);

        $summary = $renderable->export_summary_for_template([]);
        $this->assertTrue($summary['hasselected']);
        $this->assertSame($ids, array_column($summary['selectednodes'][0]['children'], 'id'));
    }

    public function test_deleted_descriptor_remains_visible_and_removable(): void {
        global $DB, $OUTPUT;

        [$item, $ids] = $this->create_fixture();
        $DB->delete_records(BLOCK_EXACOMP_DB_DESCRIPTORS, ['id' => $ids[0]]);
        $item->compids_array = $ids;
        $renderable = new \block_exaport\output\item_competences($item, true);
        $data = $renderable->export_for_template($OUTPUT);
        $picker = $OUTPUT->render_from_template('block_exaport/item_competence_picker', $data['picker']);
        $this->assertStringContainsString(get_string('unavailablecompetence', 'block_exaport'), $picker);
        $this->assertMatchesRegularExpression('/value="' . $ids[0] . '"[^>]*checked/', $picker);
    }

    public function test_topic_assignment_does_not_expose_an_unassigned_descriptor_with_the_same_id(): void {
        global $DB, $OUTPUT;

        [$item, $ids] = $this->create_fixture();
        $foreignid = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Unassigned private descriptor']);
        $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, [
            'activityid' => $item->id, 'compid' => $foreignid, 'eportfolioitem' => 1,
            'comptype' => BLOCK_EXACOMP_TYPE_TOPIC,
        ]);
        $item->compids_array = array_merge($ids, [$foreignid]);
        $renderable = new \block_exaport\output\item_competences($item, true);
        $data = $renderable->export_for_template($OUTPUT);
        $picker = $OUTPUT->render_from_template('block_exaport/item_competence_picker', $data['picker']);
        $this->assertStringNotContainsString('Unassigned private descriptor', $picker);
    }
}
