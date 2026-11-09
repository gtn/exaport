<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport;

use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Tests item authorization used by web-service file requests.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_webservice_access_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    private function create_item(int $userid): \stdClass {
        global $DB;

        $item = (object) [
            'userid' => $userid,
            'name' => 'Web-service access test item',
            'url' => '',
            'attachment' => '',
        ];
        $item->id = $DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    private function share_item_in_view(\stdClass $item, int $recipientid): void {
        global $DB;

        $viewid = $DB->insert_record('block_exaportview', (object) [
            'userid' => $item->userid,
            'name' => 'Shared view',
        ]);
        $DB->insert_record('block_exaportviewblock', (object) [
            'viewid' => $viewid,
            'itemid' => $item->id,
            'type' => 'item',
        ]);
        $DB->insert_record('block_exaportviewshar', (object) [
            'viewid' => $viewid,
            'userid' => $recipientid,
        ]);
    }

    private function configure_legacy_trainer_table(): void {
        global $DB;

        if (!defined('BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS')) {
            define('BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS', 'block_exaport_test_trainers');
        }

        $table = new \xmldb_table(BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS);
        $manager = $DB->get_manager();
        if (!$manager->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('trainerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $manager->create_table($table);
        }
    }

    public function test_owner_can_access_without_exacomp(): void {
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);

        $actual = block_exaport_get_item_for_webservice($item->id, $owner->id, $owner->id);
        $this->assertEquals($item->id, $actual->id);
        $this->assertEquals($owner->id, $actual->userid);
    }

    public function test_shared_recipient_can_access_without_exacomp(): void {
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);
        $this->share_item_in_view($item, $recipient->id);

        $actual = block_exaport_get_item_for_webservice($item->id, $owner->id, $recipient->id);
        $this->assertEquals($item->id, $actual->id);
        $this->assertEquals($owner->id, $actual->userid);
    }

    public function test_missing_item_is_rejected(): void {
        $owner = $this->getDataGenerator()->create_user();

        $this->assertFalse(block_exaport_get_item_for_webservice(PHP_INT_MAX, $owner->id, $owner->id));
    }

    public function test_url_owner_mismatch_is_rejected(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);

        $this->assertFalse(block_exaport_get_item_for_webservice($item->id, $other->id, $other->id));
    }

    public function test_trainer_can_access_own_students_item(): void {
        global $DB;

        $this->configure_legacy_trainer_table();
        $trainer = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $item = $this->create_item($student->id);
        $DB->insert_record(BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS, (object) [
            'trainerid' => $trainer->id,
            'studentid' => $student->id,
        ]);

        $actual = block_exaport_get_item_for_webservice($item->id, $student->id, $trainer->id);
        $this->assertEquals($item->id, $actual->id);
        $this->assertEquals($student->id, $actual->userid);
    }

    public function test_trainer_cannot_use_forged_owner_for_another_students_item(): void {
        global $DB;

        $this->configure_legacy_trainer_table();
        $trainer = $this->getDataGenerator()->create_user();
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();
        $itemb = $this->create_item($studentb->id);
        $DB->insert_record(BLOCK_EXACOMP_DB_EXTERNAL_TRAINERS, (object) [
            'trainerid' => $trainer->id,
            'studentid' => $studenta->id,
        ]);

        $this->assertFalse(
            block_exaport_get_item_for_webservice($itemb->id, $studenta->id, $trainer->id));
    }

    /** @return array Changes that must remove access through a collection. */
    public static function collection_changes(): array {
        return ['item selection' => [false], 'borrowed item sharing' => [true]];
    }

    #[DataProvider('collection_changes')]
    public function test_collection_file_access_follows_current_permissions(bool $borrowed): void {
        global $DB;
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
        set_config('block_exaport_enable_interaction_competences', 0);
        $collectionowner = $this->getDataGenerator()->create_user();
        $fileowner = $borrowed ? $this->getDataGenerator()->create_user() : $collectionowner;
        $recipient = $this->getDataGenerator()->create_user();
        $this->setUser($recipient);
        $item = $this->create_item($fileowner->id);
        $viewid = $this->create_view($collectionowner);
        $DB->insert_record('block_exaportviewblock', ['viewid' => $viewid, 'itemid' => $item->id, 'type' => 'item']);
        $DB->insert_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $recipient->id]);
        if ($borrowed) {
            $DB->insert_record('block_exaportitemshar', ['itemid' => $item->id, 'userid' => $collectionowner->id]);
        }
        $block = block_exaport_create_content_block($item->id, 'file');
        $context = \context_user::instance($fileowner->id);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'block_exaport', 'filearea' => 'item_content_file',
            'itemid' => $block->id, 'filepath' => '/', 'filename' => 'attachment.txt',
        ], 'Attachment content');
        $access = 'view/id/' . $collectionowner->id . '-' . $viewid;
        $args = ['webservice', (string)$fileowner->id, 'itemid', $item->id, 'blockid', $block->id, 'attachment.txt'];
        $this->assertNotEmpty(block_exaport_get_item($item->id, $access));
        $this->assertSame($file->get_content(),
            block_exaport_get_authorized_file($context, 'item_content_file', $args)->get_content());

        if ($borrowed) {
            $DB->delete_records('block_exaportitemshar', ['itemid' => $item->id]);
        } else {
            $DB->set_field('block_exaportviewblock', 'type', 'text', ['viewid' => $viewid, 'itemid' => $item->id]);
        }
        $this->assertEmpty(block_exaport_get_item($item->id, $access));
        $this->assertFalse(block_exaport_get_authorized_file($context, 'item_content_file', $args));

        // Another independent, valid collection grant must continue to work.
        $this->share_item_in_view($item, $recipient->id);
        $this->assertNotEmpty(block_exaport_get_item_for_webservice($item->id, $fileowner->id, $recipient->id));
        $this->assertNotFalse(block_exaport_get_authorized_file($context, 'item_content_file', $args));
    }
}
