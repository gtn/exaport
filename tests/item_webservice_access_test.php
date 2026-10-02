<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/sharelib.php');

/**
 * Tests item authorization used by web-service file requests.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_webservice_access_test extends \advanced_testcase {

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
}
