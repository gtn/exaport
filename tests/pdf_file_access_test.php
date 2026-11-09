<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../lib/pdf_helpers.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * PDF generation must never supply the viewer's identity to file access checks.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_file_access_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private array $originalget;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->originalget = $_GET;
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
        set_config('block_exaport_enable_interaction_competences', 0);
    }

    protected function tearDown(): void {
        $_GET = $this->originalget;
        parent::tearDown();
    }

    public function test_legacy_pdf_arguments_cannot_impersonate_a_private_collection_owner(): void {
        $owner = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $this->setUser($outsider);

        // Older callers supplied a claimed owner after the access string. Those
        // arguments must not change the identity of the authenticated viewer.
        $this->assertEmpty(block_exaport_get_view_from_access('id/' . $owner->id . '-' . $viewid, true, $owner->id));
    }

    /** @return array Legitimate sharing channels, file areas and URL formats. */
    public static function shared_images(): array {
        $cases = [];
        foreach (['users', 'groups', 'everyone', 'public', 'email', 'category_public', 'category_groups'] as $channel) {
            foreach (['item_content_file', 'item_content_text'] as $area) {
                foreach ([true, false] as $slasharguments) {
                    $cases[$channel . '/' . $area . '/' . (int)$slasharguments] = [$channel, $area, $slasharguments];
                }
            }
        }
        return $cases;
    }

    /** Build an image inside an item selected by a genuinely shared collection. */
    private function fixture(string $channel = 'public', string $area = 'item_content_file', bool $slasharguments = true): \stdClass {
        global $DB, $CFG;
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        set_config('slasharguments', (int)$slasharguments);
        foreach (['shareall', 'sharegroups', 'externaccess', 'shareemails'] as $key) {
            set_config('block_exaport_disable_' . $key, 0);
        }
        $viewid = $this->create_view($owner, $channel === 'everyone' ? 1 : 0, $channel === 'public' ? 1 : 0);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid]);
        $access = 'id/' . $owner->id . '-' . $viewid;
        $setting = '';
        if ($channel === 'users') {
            $DB->insert_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $recipient->id]);
        } else if ($channel === 'everyone') {
            $setting = 'block_exaport_disable_shareall';
        } else if ($channel === 'public') {
            $access = 'hash/' . $owner->id . '-' . $view->hash;
            $setting = 'block_exaport_disable_externaccess';
        } else if ($channel === 'email') {
            $DB->set_field('block_exaportview', 'sharedemails', 1, ['id' => $viewid]);
            $DB->insert_record('block_exaportviewemailshar', ['viewid' => $viewid, 'hash' => 'ab12cd34']);
            $access = 'email/' . $view->hash . '-ab12cd34';
            $setting = 'block_exaport_disable_shareemails';
        }
        if (str_starts_with($channel, 'category_')) {
            $categoryid = $this->create_category($owner, internshare: 1, externaccess: 1);
            $childid = $this->create_category($owner, pid: $categoryid);
            $DB->insert_record('block_exaportviewcate', ['viewid' => $viewid, 'cateid' => $childid]);
            if ($channel === 'category_public') {
                $hash = $DB->get_field('block_exaportcate', 'hash', ['id' => $categoryid]);
                $access = 'category/hash/' . $owner->id . '-' . $hash;
                $_GET['viewid'] = $viewid;
                $setting = 'block_exaport_disable_externaccess';
            }
        }
        if (in_array($channel, ['groups', 'category_groups'], true)) {
            $cohort = $this->getDataGenerator()->create_cohort();
            cohort_add_member($cohort->id, $owner->id);
            cohort_add_member($cohort->id, $recipient->id);
            if ($channel === 'groups') {
                $DB->set_field('block_exaportview', 'shareall', 2, ['id' => $viewid]);
                $DB->insert_record('block_exaportviewgroupshar', ['viewid' => $viewid, 'groupid' => $cohort->id]);
            } else {
                $DB->set_field('block_exaportcate', 'shareall', 2, ['id' => $categoryid]);
                $DB->insert_record('block_exaportcatgroupshar', ['catid' => $categoryid, 'groupid' => $cohort->id]);
            }
            $setting = 'block_exaport_disable_sharegroups';
        }
        $itemid = (int)$DB->insert_record('block_exaportitem', [
            'userid' => $owner->id, 'name' => 'PDF image', 'type' => 'file', 'courseid' => SITEID,
        ]);
        $DB->insert_record('block_exaportviewblock', ['viewid' => $viewid, 'itemid' => $itemid, 'type' => 'item']);
        $block = block_exaport_create_content_block($itemid, $area === 'item_content_text' ? 'text' : 'file');
        $context = \context_user::instance($owner->id);
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'block_exaport', 'filearea' => $area,
            'itemid' => $block->id, 'filepath' => '/nested/', 'filename' => 'image space.png',
        ], $png);
        $path = '/' . $context->id . '/block_exaport/' . $area . '/view/' . $access .
            '/itemid/' . $itemid . '/blockid/' . $block->id . '/nested/image space.png';
        $url = \moodle_url::make_file_url($CFG->wwwroot . '/pluginfile.php', $path)->out(false);
        if (in_array($channel, ['public', 'email', 'category_public'], true)) {
            $this->setGuestUser();
        } else {
            $this->setUser($recipient);
        }
        return (object)compact('owner', 'recipient', 'viewid', 'itemid', 'block', 'context', 'file', 'url', 'setting', 'png', 'access');
    }

    #[DataProvider('shared_images')]
    public function test_pdf_images_follow_sharing_and_policy_revocation(string $channel, string $area, bool $slasharguments): void {
        global $DB, $USER;
        $f = $this->fixture($channel, $area, $slasharguments);
        $viewerid = $USER->id;
        $expected = 'data:image/png;base64,' . base64_encode($f->png);
        $this->assertSame($expected, block_exaport_pdf_image_data_url($f->url));
        $this->assertSame($viewerid, $USER->id);
        if ($f->setting !== '') {
            set_config($f->setting, 1);
        } else {
            $DB->delete_records('block_exaportviewshar', ['viewid' => $f->viewid]);
        }
        $this->assertSame('', block_exaport_pdf_image_data_url($f->url));
        if ($f->setting !== '') {
            set_config($f->setting, 0);
        } else {
            $DB->insert_record('block_exaportviewshar', ['viewid' => $f->viewid, 'userid' => $f->recipient->id]);
        }
        $this->assertSame($expected, block_exaport_pdf_image_data_url($f->url));
    }

    public function test_known_collection_hash_cannot_authorise_another_owners_file(): void {
        global $DB;
        $f = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $privateviewid = $this->create_view($other);
        $itemid = (int)$DB->insert_record('block_exaportitem', [
            'userid' => $other->id, 'name' => 'Private document', 'type' => 'file', 'courseid' => SITEID,
        ]);
        $block = block_exaport_create_content_block($itemid, 'file');
        $DB->insert_record('block_exaportviewblock', ['viewid' => $privateviewid, 'itemid' => $itemid, 'type' => 'item']);
        $context = \context_user::instance($other->id);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'block_exaport', 'filearea' => 'item_content_file',
            'itemid' => $block->id, 'filepath' => '/', 'filename' => 'document.pdf',
        ], '%PDF-1.4 private document');
        $args = ['view', 'id', $other->id . '-' . $privateviewid, 'itemid', $itemid,
            'blockid', $block->id, 'document.pdf'];
        $legacyargs = array_merge($args, ['forPdf',
            $DB->get_field('block_exaportview', 'hash', ['id' => $f->viewid]), $f->viewid, $other->id]);
        $this->assertFalse(block_exaport_pluginfile(null, null, $context, 'item_content_file', $legacyargs, false));
        $this->assertFalse(block_exaport_get_authorized_file($f->context, 'item_content_file', ['forPdf']));
        $this->setUser($f->owner);
        $this->assertFalse(block_exaport_get_authorized_file($context, 'item_content_file', $args));
        $this->setUser($other);
        $authorised = block_exaport_get_authorized_file($context, 'item_content_file', $args);
        $this->assertSame($file->get_content(), $authorised->get_content());
    }

    public function test_collection_images_require_item_inclusion_and_borrowed_item_shares(): void {
        global $DB;
        $f = $this->fixture();
        $DB->delete_records('block_exaportviewblock', ['viewid' => $f->viewid]);
        $this->assertSame('', block_exaport_pdf_image_data_url($f->url));
        $DB->insert_record('block_exaportviewblock', ['viewid' => $f->viewid, 'itemid' => $f->itemid, 'type' => 'item']);

        $other = $this->getDataGenerator()->create_user();
        $DB->set_field('block_exaportitem', 'userid', $other->id, ['id' => $f->itemid]);
        $DB->insert_record('block_exaportitemshar', ['itemid' => $f->itemid, 'userid' => $f->owner->id]);
        $context = \context_user::instance($other->id);
        get_file_storage()->create_file_from_storedfile(['contextid' => $context->id], $f->file);
        $url = str_replace('/' . $f->context->id . '/block_exaport/', '/' . $context->id . '/block_exaport/', $f->url);
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($url));
        $DB->delete_records('block_exaportitemshar', ['itemid' => $f->itemid]);
        $this->assertSame('', block_exaport_pdf_image_data_url($url));
    }

    public function test_only_local_pluginfile_images_can_be_embedded(): void {
        global $CFG;
        $f = $this->fixture();
        foreach (['https://other.example.test/image.png', 'file:///etc/passwd',
                str_replace($CFG->wwwroot, 'https://other.example.test', $f->url),
                str_replace('/block_exaport/', '/user/', $f->url),
                str_replace('/item_content_file/', '/unknown_area/', $f->url),
                $CFG->wwwroot . '/pluginfile.php?file[]=invalid'] as $url) {
            $this->assertSame('', block_exaport_pdf_image_data_url($url));
        }
        $fileinfo = ['contextid' => $f->context->id, 'component' => 'block_exaport', 'filearea' => 'item_content_file',
            'itemid' => $f->block->id, 'filepath' => '/nested/', 'filename' => 'private.txt'];
        get_file_storage()->create_file_from_string($fileinfo, 'Not an image');
        $this->assertSame('', block_exaport_pdf_image_data_url(str_replace('image%20space.png', 'private.txt', $f->url)));
    }

    public function test_cv_images_require_ownership_or_the_exact_shared_section(): void {
        global $DB, $CFG;
        $f = $this->fixture();
        $resumeid = $DB->insert_record('block_exaportresume', ['user_id' => $f->owner->id]);
        foreach (['cover', 'goalspersonal'] as $section) {
            get_file_storage()->create_file_from_storedfile([
                'filearea' => 'resume_editor_' . $section, 'itemid' => $resumeid, 'filepath' => '/', 'filename' => 'cv.png',
            ], $f->file);
        }
        $DB->insert_record('block_exaportviewblock', [
            'viewid' => $f->viewid, 'type' => 'cv_group', 'resume_itemtype' => 'cover',
        ]);
        $_GET['access'] = $f->access;
        $base = $CFG->wwwroot . '/pluginfile.php/' . $f->context->id . '/block_exaport/';
        $cover = $base . 'resume_editor_cover/' . $resumeid . '/cv.png';
        $goals = $base . 'resume_editor_goalspersonal/' . $resumeid . '/cv.png';
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($cover));
        $this->assertSame('', block_exaport_pdf_image_data_url($goals));
        $_GET['access'] = 'resume/' . $resumeid . '/' . $f->owner->id;
        $this->setUser($f->recipient);
        $this->assertSame('', block_exaport_pdf_image_data_url($cover));
        $this->setUser($f->owner);
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($cover));
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($goals));
    }

    public function test_collection_text_and_profile_images_follow_their_sharing_boundaries(): void {
        global $DB, $CFG;
        $f = $this->fixture();
        $base = $CFG->wwwroot . '/pluginfile.php/' . $f->context->id . '/block_exaport/';
        get_file_storage()->create_file_from_storedfile([
            'filearea' => 'view_content', 'itemid' => $f->viewid, 'filepath' => '/', 'filename' => 'view.png',
        ], $f->file);
        $url = $base . 'view_content/' . $f->access . '/view.png';
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($url));
        set_config('block_exaport_disable_externaccess', 1);
        $this->assertSame('', block_exaport_pdf_image_data_url($url));
        set_config('block_exaport_disable_externaccess', 0);

        get_file_storage()->create_file_from_storedfile([
            'filearea' => 'personal_information', 'itemid' => $f->owner->id, 'filepath' => '/', 'filename' => 'profile.png',
        ], $f->file);
        $url = $base . 'personal_information_view/' . $f->access . '/profile.png';
        $this->assertSame('', block_exaport_pdf_image_data_url($url));
        $DB->insert_record('block_exaportviewblock', ['viewid' => $f->viewid, 'type' => 'personal_information']);
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($url));
        $DB->delete_records('block_exaportviewblock', ['viewid' => $f->viewid, 'type' => 'personal_information']);
        $this->assertSame('', block_exaport_pdf_image_data_url($url));
    }

    public function test_legacy_item_images_and_custom_icons_require_collection_inclusion(): void {
        global $DB, $CFG;
        $f = $this->fixture();
        $urls = [];
        foreach (['item_file', 'item_iconfile', 'item_content'] as $area) {
            get_file_storage()->create_file_from_storedfile([
                'filearea' => $area, 'itemid' => $f->itemid, 'filepath' => '/', 'filename' => 'legacy.png',
            ], $f->file);
            $url = $CFG->wwwroot . '/pluginfile.php/' . $f->context->id . '/block_exaport/' . $area .
                '/view/' . $f->access . '/itemid/' . $f->itemid . '/legacy.png';
            $this->assertNotEmpty(block_exaport_pdf_image_data_url($url));
            $urls[] = $url;
        }
        $DB->delete_records('block_exaportviewblock', ['viewid' => $f->viewid]);
        foreach ($urls as $url) {
            $this->assertSame('', block_exaport_pdf_image_data_url($url));
        }
    }

    public function test_linked_cv_images_are_embedded_only_while_explicitly_selected(): void {
        global $DB, $CFG;
        $f = $this->fixture();
        $resumeid = $DB->insert_record('block_exaportresume', [
            'user_id' => $f->owner->id, 'cover' => '<img src="@@PLUGINFILE@@/linked.png">',
        ]);
        $file = get_file_storage()->create_file_from_storedfile([
            'filearea' => 'resume_editor_cover', 'itemid' => $resumeid, 'filepath' => '/', 'filename' => 'linked.png',
        ], $f->file);
        $block = block_exaport_create_content_block($f->itemid, 'personal', '', '', [
            'content' => \block_exaport\local\item_linked_content::configuration('personal', $f->owner->id, ['cover']),
        ]);
        $url = $CFG->wwwroot . '/pluginfile.php/' . $f->context->id . '/block_exaport/item_content_linked/view/' .
            $f->access . '/itemid/' . $f->itemid . '/blockid/' . $block->id . '/' . $file->get_id() . '/linked.png';
        $this->assertNotEmpty(block_exaport_pdf_image_data_url($url));
        $DB->set_field('block_exaportresume', 'cover', '<p>Image removed</p>', ['id' => $resumeid]);
        $this->assertSame('', block_exaport_pdf_image_data_url($url));
    }
}
