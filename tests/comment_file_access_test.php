<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Comment downloads must respect the same visibility as the shared page.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class comment_file_access_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('block_exaport_disable_externaccess', 0);
        set_config('block_exaport_disable_external_comments', 0);
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
    }

    /** Create an attachment belonging to a comment on an item in a public collection. */
    private function fixture(): \stdClass {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $itemid = (int)$DB->insert_record('block_exaportitem', ['userid' => $owner->id, 'name' => 'Commented item']);
        $viewid = $this->create_view($owner, externaccess: 1);
        $DB->insert_record('block_exaportviewblock', ['viewid' => $viewid, 'itemid' => $itemid, 'type' => 'item']);
        $hash = $DB->get_field('block_exaportview', 'hash', ['id' => $viewid]);
        $access = 'view/hash/' . $owner->id . '-' . $hash;
        $commentid = (int)$DB->insert_record('block_exaportitemcomm', [
            'itemid' => $itemid, 'userid' => $owner->id, 'entry' => 'Feedback', 'timemodified' => time(),
        ]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id, 'component' => 'block_exaport',
            'filearea' => 'item_comment_file', 'itemid' => $commentid, 'filepath' => '/', 'filename' => 'feedback.txt',
        ], 'Feedback attachment');
        return (object)compact('owner', 'itemid', 'viewid', 'access', 'commentid', 'file');
    }

    public function test_external_downloads_follow_comment_visibility_and_global_policy(): void {
        global $DB;
        $f = $this->fixture();
        $this->setGuestUser();
        foreach ([0, 1, 0] as $visible) {
            $DB->set_field('block_exaportview', 'externcomment', $visible, ['id' => $f->viewid]);
            $item = block_exaport_get_item($f->itemid, $f->access);
            $file = block_exaport_get_authorized_item_comment_file($item, $f->commentid);
            $this->assertSame((bool)$visible, (bool)$file);
            if ($file) {
                $this->assertSame($f->file->get_content(), $file->get_content());
            }
        }
        $DB->set_field('block_exaportview', 'externcomment', 1, ['id' => $f->viewid]);
        set_config('block_exaport_disable_external_comments', 1);
        $item = block_exaport_get_item($f->itemid, $f->access);
        $this->assertFalse(block_exaport_get_authorized_item_comment_file($item, $f->commentid));
    }

    public function test_internal_and_authorised_owner_downloads_remain_available(): void {
        global $DB;
        $f = $this->fixture();
        $recipient = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_exaportviewshar', ['viewid' => $f->viewid, 'userid' => $recipient->id]);
        $this->setUser($recipient);
        set_config('block_exaport_disable_external_comments', 1);
        $item = block_exaport_get_item($f->itemid, 'view/id/' . $f->owner->id . '-' . $f->viewid);
        $this->assertNotFalse(block_exaport_get_authorized_item_comment_file($item, $f->commentid));
        $this->setUser($f->owner);
        $item = $DB->get_record('block_exaportitem', ['id' => $f->itemid]);
        $this->assertNotFalse(block_exaport_get_authorized_item_comment_file($item, $f->commentid));
    }

    public function test_comment_and_attachment_must_belong_to_the_authorised_item(): void {
        global $DB;
        $f = $this->fixture();
        $this->setUser($f->owner);
        $otherid = $DB->insert_record('block_exaportitem', ['userid' => $f->owner->id, 'name' => 'Other item']);
        $other = $DB->get_record('block_exaportitem', ['id' => $otherid]);
        $this->assertFalse(block_exaport_get_authorized_item_comment_file($other, $f->commentid));
        $item = $DB->get_record('block_exaportitem', ['id' => $f->itemid]);
        $this->assertFalse(block_exaport_get_authorized_item_comment_file($item, PHP_INT_MAX));
        $f->file->delete();
        $this->assertFalse(block_exaport_get_authorized_item_comment_file($item, $f->commentid));
    }

    /** @return array Older item records may supply these hidden-comment values. */
    public static function hidden_values(): array {
        return [[null], [0], ['0'], [false]];
    }

    #[DataProvider('hidden_values')]
    public function test_legacy_hidden_comment_values_deny_downloads($visibility): void {
        global $DB;
        $f = $this->fixture();
        $item = $DB->get_record('block_exaportitem', ['id' => $f->itemid]);
        $item->showComments = $visibility;
        $this->assertFalse(block_exaport_get_authorized_item_comment_file($item, $f->commentid));
    }
}
