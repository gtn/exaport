<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/sharelib.php');
require_once($CFG->dirroot . '/blocks/exaport/tests/fixtures/exaport_test_helpers_trait.php');

/**
 * Characterizes the authoritative master switch for view sharing channels.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_master_switch_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** @var \stdClass View owner. */
    private $owner;

    /** @var \stdClass Internal recipient. */
    private $recipient;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('block_exaport_disable_externaccess', 0);
        set_config('block_exaport_disable_shareall', 0);
        set_config('block_exaport_disable_shareemails', 0);

        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->setUser($this->owner);
    }

    /**
     * Return every supported internal audience for the combined-channel scenario.
     *
     * @return array
     */
    public static function internal_mode_provider(): array {
        return [
            'everyone' => [1],
            'direct user' => [0],
            'group' => [2],
            'email hidden by site policy' => [1, false],
        ];
    }

    /**
     * Master-off revokes external, internal and email access despite forged subordinate values.
     *
     * @dataProvider internal_mode_provider
     * @param int $internalmode Internal sharing mode.
     * @param bool $siteemailenabled Whether the site exposes email sharing.
     */
    public function test_disabled_master_switch_revokes_all_channels(int $internalmode, bool $siteemailenabled = true): void {
        global $DB;

        $fixture = $this->create_shared_view_fixture($internalmode);
        $this->setUser($this->recipient);
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->externalaccess));
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->internalaccess));
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->emailaccess));
        set_config('block_exaport_disable_shareemails', (int)!$siteemailenabled);

        // Model a forged form submission: every child control is checked while the authoritative
        // master switch is off. Normalization must discard every subordinate value.
        $submitted = (object)[
            'externaccess' => 1,
            'internaccess' => 1,
            'shareall' => $internalmode,
            'externcomment' => 1,
            'sharedemails' => 1,
        ];
        $normalized = sharing_service::normalize_view_channels($submitted, false);
        $this->assertSame(0, $normalized->externaccess);
        $this->assertSame(0, $normalized->internaccess);
        $this->assertSame(0, $normalized->shareall);
        $this->assertSame(0, $normalized->externcomment);
        $this->assertSame(0, $normalized->sharedemails);

        $this->setUser($this->owner);
        $invitations = $this->persist_normalized_state($fixture->viewid, $normalized, false,
            [$this->recipient->id], [$fixture->cohortid], $this->recipient->email);
        $this->assertSame([], $invitations);

        $view = $DB->get_record('block_exaportview', ['id' => $fixture->viewid], '*', MUST_EXIST);
        $this->assertEquals(0, $view->externaccess);
        $this->assertEquals(0, $view->shareall);
        $this->assertEquals(0, $view->sharedemails);
        $this->assertFalse($DB->record_exists('block_exaportviewshar', ['viewid' => $fixture->viewid]));
        $this->assertFalse($DB->record_exists('block_exaportviewgroupshar', ['viewid' => $fixture->viewid]));

        $this->assertFalse($DB->record_exists('block_exaportviewemailshar', ['viewid' => $fixture->viewid]));
        $this->setUser($this->recipient);
        $this->assertEmpty(block_exaport_get_view_from_access($fixture->externalaccess));
        $this->assertEmpty(block_exaport_get_view_from_access($fixture->internalaccess));
        $this->assertEmpty(block_exaport_get_view_from_access($fixture->emailaccess));
    }

    /**
     * With the master enabled, external, internal and email channels remain independent.
     */
    public function test_enabled_master_switch_preserves_independent_channels(): void {
        $channels = [
            'external' => (object)[
                'externaccess' => 1,
                'internaccess' => 0,
                'shareall' => 0,
                'externcomment' => 1,
                'sharedemails' => 0,
            ],
            'internal' => (object)[
                'externaccess' => 0,
                'internaccess' => 1,
                'shareall' => 0,
                'externcomment' => 0,
                'sharedemails' => 0,
            ],
            'email' => (object)[
                'externaccess' => 0,
                'internaccess' => 0,
                'shareall' => 0,
                'externcomment' => 0,
                'sharedemails' => 1,
            ],
        ];

        foreach ($channels as $enabledchannel => $submitted) {
            $fixture = $this->create_view_fixture($enabledchannel === 'email');
            $normalized = sharing_service::normalize_view_channels($submitted, true);
            $userids = $enabledchannel === 'internal' ? [$this->recipient->id] : [];
            $this->persist_normalized_state($fixture->viewid, $normalized, true, $userids);

            $this->setUser($this->recipient);
            $this->assertSame($enabledchannel === 'external',
                !empty(block_exaport_get_view_from_access($fixture->externalaccess)));
            $this->assertSame($enabledchannel === 'internal',
                !empty(block_exaport_get_view_from_access($fixture->internalaccess)));
            $this->assertSame($enabledchannel === 'email',
                !empty(block_exaport_get_view_from_access($fixture->emailaccess)));
            $this->setUser($this->owner);
        }
    }

    /**
     * Supply recipient inputs that clear or preserve the saved list.
     *
     * @return array
     */
    public static function email_recipient_list_provider(): array {
        return [
            'explicitly empty' => [true, '', false],
            'only separators and whitespace' => [true, " ;,\r\n\t ", false],
            'omitted' => [true, null, true],
            'view email channel off with list omitted' => [false, null, false],
            'view email channel off with stale list' => [false, 'stale@example.test', false],
        ];
    }

    /**
     * An enabled, submitted list is authoritative, even when it contains no recipients.
     *
     * @dataProvider email_recipient_list_provider
     * @param bool $enabled Whether the view has email sharing enabled.
     * @param string|null $recipientlist Submitted list, or null when omitted.
     * @param bool $preserved Whether the saved recipient should remain.
     */
    public function test_save_email_recipient_list(bool $enabled, ?string $recipientlist, bool $preserved): void {
        global $DB;

        $view = $this->create_email_sharing_view();
        $conditions = ['viewid' => $view->id];
        $original = $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash');
        $this->assertNotEmpty($original);

        $view->sharedemails = (int)$enabled;
        $this->assertSame([], sharing_service::save_view_sharing_settings($view, true, $recipientlist));

        $saved = $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash');
        $this->assertSame($preserved ? $original : [], $saved);
        $this->assertEquals((int)$enabled, $DB->get_field('block_exaportview', 'sharedemails', ['id' => $view->id]));
    }

    /**
     * Hiding email sharing at site level preserves the owner's setting and recipients.
     */
    public function test_site_policy_preserves_email_sharing_while_master_switch_is_enabled(): void {
        global $DB;

        $view = $this->create_email_sharing_view();
        $conditions = ['viewid' => $view->id];
        $original = $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash');
        $this->assertNotEmpty($original);
        set_config('block_exaport_disable_shareemails', 1);
        $view = sharing_service::normalize_view_channels($view, true);
        $this->assertSame(0, $view->sharedemails);

        $this->assertSame([], sharing_service::save_view_sharing_settings($view, true, 'stale@example.test'));

        $this->assertEquals(1, $DB->get_field('block_exaportview', 'sharedemails', ['id' => $view->id]));
        $this->assertSame($original, $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash'));
    }

    /**
     * Supply both ways to enable email sharing on a view with old grants.
     *
     * @return array
     */
    public static function legacy_email_recipient_list_provider(): array {
        return [
            'omitted list' => [false],
            'same address explicitly submitted' => [true],
        ];
    }

    /**
     * Enabling a historically disabled channel discards its old grants.
     *
     * @dataProvider legacy_email_recipient_list_provider
     * @param bool $submitrecipient Whether the recipient was explicitly submitted again.
     */
    public function test_enabling_legacy_disabled_email_sharing_discards_old_grants(bool $submitrecipient): void {
        global $DB;

        $fixture = $this->create_view_fixture();
        $view = $DB->get_record('block_exaportview', ['id' => $fixture->viewid], '*', MUST_EXIST);
        $conditions = ['viewid' => $view->id];
        $email = $this->recipient->email;
        $legacyhash = $DB->get_field('block_exaportviewemailshar', 'hash', $conditions + ['email' => $email], MUST_EXIST);
        $this->assertEquals(0, $view->sharedemails);
        $view->sharedemails = 1;

        $invitations = sharing_service::save_view_sharing_settings($view, true, $submitrecipient ? $email : null);

        $saved = $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash');
        if ($submitrecipient) {
            $newhash = $invitations[$email] ?? null;
            $this->assertNotEmpty($newhash);
            $this->assertNotSame($legacyhash, $newhash);
            $this->assertSame([$email => $newhash], $invitations);
        } else {
            $this->assertSame([], $invitations);
        }
        $this->assertSame($invitations, $saved);
        $this->assertEquals(1, $DB->get_field('block_exaportview', 'sharedemails', ['id' => $view->id]));
        $this->setUser($this->recipient);
        $this->assertEmpty(block_exaport_get_view_from_access($fixture->emailaccess));
    }

    /**
     * The internal recipient search leaves email settings out of its partial save.
     */
    public function test_partial_sharing_save_preserves_email_configuration(): void {
        global $DB;

        $view = $this->create_email_sharing_view();
        $conditions = ['viewid' => $view->id];
        $original = $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash');
        $this->assertNotEmpty($original);
        unset($view->sharedemails);
        $view->externaccess = 1;

        $this->assertSame([], sharing_service::save_view_sharing_settings($view, true, null));

        $savedview = $DB->get_record('block_exaportview', ['id' => $view->id], '*', MUST_EXIST);
        $this->assertEquals(1, $savedview->externaccess);
        $this->assertEquals(1, $savedview->sharedemails);
        $this->assertSame($original, $DB->get_records_menu('block_exaportviewemailshar', $conditions, '', 'email, hash'));
    }

    /**
     * Reinviting a recipient after disabling email sharing creates a fresh link.
     */
    public function test_reinviting_after_disabling_email_sharing_keeps_old_link_revoked(): void {
        global $DB;

        $viewid = $this->create_view($this->owner);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid], '*', MUST_EXIST);
        $email = $this->recipient->email;
        $view->sharedemails = 1;
        $view = sharing_service::normalize_view_channels($view, true);
        $original = sharing_service::save_view_sharing_settings($view, true, $email);
        $this->assertNotEmpty($original[$email] ?? null);

        $view->sharedemails = 0;
        $this->assertSame([], sharing_service::save_view_sharing_settings($view, true, null));
        $view->sharedemails = 1;
        $this->assertSame([], sharing_service::save_view_sharing_settings($view, true, null));
        $this->assertFalse($DB->record_exists('block_exaportviewemailshar', ['viewid' => $viewid]));

        $reinvited = sharing_service::save_view_sharing_settings($view, true, $email);
        $this->assertNotEmpty($reinvited[$email] ?? null);
        $this->assertNotSame($original[$email], $reinvited[$email]);
        $this->setUser($this->recipient);
        $this->assertEmpty(block_exaport_get_view_from_access('email/' . $view->hash . '-' . $original[$email]));
        $this->assertNotEmpty(block_exaport_get_view_from_access('email/' . $view->hash . '-' . $reinvited[$email]));
    }

    /**
     * Editing a list keeps retained links and only returns invitations for new recipients.
     */
    public function test_update_email_recipient_list_only_affects_current_view(): void {
        global $DB;

        $view = $this->create_email_sharing_view();
        $otherview = $this->create_email_sharing_view();
        $otherconditions = ['viewid' => $otherview->id];
        $othershares = $DB->get_records_menu('block_exaportviewemailshar', $otherconditions, '', 'email, hash');
        $retainedemail = 'retained@example.test';
        $retainedhash = md5('retained-' . $view->id);
        $DB->insert_record('block_exaportviewemailshar', (object)[
            'viewid' => $view->id,
            'email' => $retainedemail,
            'hash' => $retainedhash,
        ]);
        $newemail = 'new@example.test';

        $invitations = sharing_service::save_view_sharing_settings($view, true, "$retainedemail\n$newemail");

        $saved = $DB->get_records_menu('block_exaportviewemailshar', ['viewid' => $view->id], '', 'email, hash');
        $this->assertCount(2, $saved);
        $this->assertSame($retainedhash, $saved[$retainedemail] ?? null);
        $this->assertArrayNotHasKey($this->recipient->email, $saved);
        $this->assertNotEmpty($saved[$newemail] ?? null);
        $this->assertSame([$newemail => $saved[$newemail]], $invitations);
        $this->assertSame($othershares, $DB->get_records_menu('block_exaportviewemailshar', $otherconditions, '', 'email, hash'));
    }

    /**
     * Create an owned view with email sharing enabled and one saved recipient.
     *
     * @return \stdClass
     */
    private function create_email_sharing_view(): \stdClass {
        global $DB;

        $fixture = $this->create_view_fixture(true);
        return $DB->get_record('block_exaportview', ['id' => $fixture->viewid], '*', MUST_EXIST);
    }

    /**
     * Create a view with all three channels and one selected internal audience.
     *
     * @param int $internalmode Internal mode.
     * @return \stdClass
     */
    private function create_shared_view_fixture(int $internalmode): \stdClass {
        global $DB;

        $fixture = $this->create_view_fixture(true);
        $DB->set_field('block_exaportview', 'externaccess', 1, ['id' => $fixture->viewid]);

        $cohortid = 0;
        $userids = [];
        $groupids = [];
        if ($internalmode === 0) {
            $userids = [$this->recipient->id];
        } else if ($internalmode === 2) {
            $cohort = $this->getDataGenerator()->create_cohort(['name' => 'View recipients']);
            cohort_add_member($cohort->id, $this->owner->id);
            cohort_add_member($cohort->id, $this->recipient->id);
            $cohortid = (int)$cohort->id;
            $groupids = [$cohortid];
        }
        sharing_service::save_internal_shares('view', $fixture->viewid, true, $internalmode,
            $userids, [], $groupids);
        $fixture->cohortid = $cohortid;
        return $fixture;
    }

    /**
     * Create a view and stable access strings for all three channels.
     *
     * @param bool $emailenabled Whether the saved email channel is enabled.
     * @return \stdClass
     */
    private function create_view_fixture(bool $emailenabled = false): \stdClass {
        global $DB;

        $viewid = $this->create_view($this->owner);
        $DB->set_field('block_exaportview', 'sharedemails', (int)$emailenabled, ['id' => $viewid]);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid], '*', MUST_EXIST);
        $emailhash = md5('recipient-' . $viewid);
        $DB->insert_record('block_exaportviewemailshar', (object)[
            'viewid' => $viewid,
            'email' => $this->recipient->email,
            'hash' => $emailhash,
        ]);
        return (object)[
            'viewid' => $viewid,
            'cohortid' => 0,
            'externalaccess' => 'hash/' . $this->owner->id . '-' . $view->hash,
            'internalaccess' => 'id/' . $this->owner->id . '-' . $viewid,
            'emailaccess' => 'email/' . $view->hash . '-' . $emailhash,
        ];
    }

    /**
     * Persist normalized channel flags and internal recipients like the view controller does.
     *
     * @param int $viewid View id.
     * @param \stdClass $state Normalized state.
     * @param bool $shareenabled Master sharing state.
     * @param int[] $userids Direct recipients.
     * @param int[] $groupids Cohort recipients.
     * @param string|null $recipientlist Submitted email recipients.
     * @return array Invitation hashes keyed by new email addresses.
     */
    private function persist_normalized_state(int $viewid, \stdClass $state, bool $shareenabled,
            array $userids = [], array $groupids = [], ?string $recipientlist = null): array {
        $state->id = $viewid;
        return sharing_service::save_view_sharing_settings($state, $shareenabled, $recipientlist, $userids, [], $groupids);
    }
}
