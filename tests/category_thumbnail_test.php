<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Regression tests for category previews and their sharing boundary.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_thumbnail_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private \stdClass $owner;
    private \stdClass $recipient;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->setUser($this->recipient);
    }

    private function category(int $pid = 0, int $internshare = 0): \stdClass {
        global $DB;
        $id = $this->create_category($this->owner, 'Category', $pid, $internshare);
        return $DB->get_record('block_exaportcate', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_internal_access_inherits_downwards_and_is_revocable(): void {
        global $DB;
        $privateparent = $this->category();
        $shared = $this->category($privateparent->id, 1);
        $child = $this->category($shared->id);
        $grandchild = $this->category($child->id);
        $sibling = $this->category($privateparent->id);
        $DB->insert_record('block_exaportcatshar', ['catid' => $shared->id, 'userid' => $this->recipient->id]);

        foreach ([$shared, $child, $grandchild] as $category) {
            $this->assertTrue(category_helper::can_access_thumbnail($category));
        }
        $this->assertFalse(category_helper::can_access_thumbnail($privateparent));
        $this->assertFalse(category_helper::can_access_thumbnail($sibling));

        $DB->delete_records('block_exaportcatshar', ['catid' => $shared->id]);
        $this->assertFalse(category_helper::can_access_thumbnail($grandchild));
        $this->setUser($this->owner);
        $this->assertTrue(category_helper::can_access_thumbnail($privateparent));
    }

    public function test_cohort_and_shareall_access_follow_existing_sharing_settings(): void {
        global $DB, $CFG;
        $category = $this->category(0, 1);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->recipient->id);
        $DB->insert_record('block_exaportcatgroupshar', ['catid' => $category->id, 'groupid' => $cohort->id]);
        $this->assertTrue(category_helper::can_access_thumbnail($category));

        $DB->delete_records('block_exaportcatgroupshar', ['catid' => $category->id]);
        $DB->set_field('block_exaportcate', 'shareall', 1, ['id' => $category->id]);
        $CFG->block_exaport_disable_shareall = 0;
        $this->assertTrue(category_helper::can_access_thumbnail($category));
        $CFG->block_exaport_disable_shareall = 1;
        $this->assertFalse(category_helper::can_access_thumbnail($category));
    }

    public function test_external_access_requires_current_hash_and_stays_inside_shared_subtree(): void {
        global $DB, $CFG;
        $CFG->block_exaport_disable_externaccess = 0;
        $root = $this->category();
        $child = $this->category($root->id);
        $unshared = $this->category();
        $DB->set_field('block_exaportcate', 'externaccess', 1, ['id' => $root->id]);
        $access = 'hash/' . $this->owner->id . '-' . $root->hash;
        $this->setGuestUser();

        $this->assertFalse(category_helper::can_access_thumbnail($child));
        $this->assertTrue(category_helper::can_access_thumbnail($child, $access));
        $this->assertFalse(category_helper::can_access_thumbnail($unshared, $access));
        $this->assertFalse(category_helper::can_access_thumbnail($child, $access . 'invalid'));
        $DB->set_field('block_exaportcate', 'externaccess', 0, ['id' => $root->id]);
        $this->assertFalse(category_helper::can_access_thumbnail($child, $access));
        $DB->set_field('block_exaportcate', 'externaccess', 1, ['id' => $root->id]);
        $CFG->block_exaport_disable_externaccess = 1;
        $this->assertFalse(category_helper::can_access_thumbnail($child, $access));
    }

    public function test_hidden_uploads_preserve_card_preview_and_legacy_merge_value_is_ignored(): void {
        global $DB, $PAGE;
        $category = $this->category();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($this->owner->id)->id,
            'component' => 'block_exaport', 'filearea' => 'category_icon', 'itemid' => $category->id,
            'filepath' => '/', 'filename' => 'picture name.png', 'mimetype' => 'image/png',
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWioAAAAASUVORK5CYII='));
        set_config('hide_category_thumbnail', 1, 'block_exaport');
        $category->url = '/category';
        $category->icon = block_exaport_get_category_icon($category);
        $category->iconmerge = 1; // Historical values must not suppress or composite the image.
        $DB->set_field('block_exaportcate', 'iconmerge', 1, ['id' => $category->id]);
        $output = $PAGE->get_renderer('block_exaport');
        $card = new \block_exaport\output\category_card($category, 1, 'shared', $category);
        $html = $output->render($card);

        $this->assertFalse(block_exaport_category_thumbnail_enabled());
        $this->assertStringContainsString('picture%20name.png', $category->icon);
        $this->assertStringContainsString('eportfolio-card-thumbnail-image', $html);
        $this->assertStringContainsString('col-card-folder', $html);
        $this->assertStringNotContainsString('<canvas', $html);
        $this->assertSame(1, (int)$DB->get_field('block_exaportcate', 'iconmerge', ['id' => $category->id]));
        $this->assertTrue($file->is_valid_image());

        $parenttile = new \block_exaport\output\category_card($category, 1, 'shared', $category, $category);
        $this->assertStringNotContainsString('eportfolio-card-thumbnail-image', $output->render($parenttile));
        $category->icon = null;
        $this->assertStringNotContainsString('eportfolio-card-thumbnail-image', $output->render($card));
    }

    public function test_file_route_rejects_private_categories_and_wrong_owner_contexts(): void {
        $category = $this->category();
        $context = \context_user::instance($this->owner->id);
        $args = [$category->id, 'picture.png'];
        $this->assertFalse(block_exaport_pluginfile(null, null, $context, 'category_icon', $args, false));
        $this->setUser($this->owner);
        $wrongcontext = \context_user::instance($this->recipient->id);
        $this->assertFalse(block_exaport_pluginfile(null, null, $wrongcontext, 'category_icon', $args, false));
    }
}
