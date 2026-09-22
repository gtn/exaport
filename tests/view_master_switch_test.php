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
        ];
    }

    /**
     * Master-off revokes external, internal and email access despite forged subordinate values.
     *
     * @dataProvider internal_mode_provider
     * @param int $internalmode Internal sharing mode.
     */
    public function test_disabled_master_switch_revokes_all_channels(int $internalmode): void {
        global $DB;

        $fixture = $this->create_shared_view_fixture($internalmode);
        $this->setUser($this->recipient);
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->externalaccess));
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->internalaccess));
        $this->assertNotEmpty(block_exaport_get_view_from_access($fixture->emailaccess));

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
        $this->persist_normalized_state($fixture->viewid, $normalized, false,
            [$this->recipient->id], [$fixture->cohortid]);

        $view = $DB->get_record('block_exaportview', ['id' => $fixture->viewid], '*', MUST_EXIST);
        $this->assertEquals(0, $view->externaccess);
        $this->assertEquals(0, $view->shareall);
        $this->assertEquals(0, $view->sharedemails);
        $this->assertFalse($DB->record_exists('block_exaportviewshar', ['viewid' => $fixture->viewid]));
        $this->assertFalse($DB->record_exists('block_exaportviewgroupshar', ['viewid' => $fixture->viewid]));

        // Email rows intentionally retain their stable hashes, but sharedemails=0 makes them unusable.
        $this->assertTrue($DB->record_exists('block_exaportviewemailshar', ['viewid' => $fixture->viewid]));
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
            $fixture = $this->create_unshared_view_fixture();
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
     * Create a view with all three channels and one selected internal audience.
     *
     * @param int $internalmode Internal mode.
     * @return \stdClass
     */
    private function create_shared_view_fixture(int $internalmode): \stdClass {
        global $DB;

        $fixture = $this->create_unshared_view_fixture();
        $DB->set_field('block_exaportview', 'externaccess', 1, ['id' => $fixture->viewid]);
        $DB->set_field('block_exaportview', 'sharedemails', 1, ['id' => $fixture->viewid]);

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
     * @return \stdClass
     */
    private function create_unshared_view_fixture(): \stdClass {
        global $DB;

        $viewid = $this->create_view($this->owner);
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
     */
    private function persist_normalized_state(int $viewid, \stdClass $state, bool $shareenabled,
            array $userids = [], array $groupids = []): void {
        global $DB;

        $DB->update_record('block_exaportview', (object)[
            'id' => $viewid,
            'externaccess' => $state->externaccess,
            'externcomment' => $state->externcomment,
            'shareall' => $state->shareall,
            'sharedemails' => $state->sharedemails,
        ]);
        sharing_service::save_internal_shares('view', $viewid,
            $shareenabled && !empty($state->internaccess), (int)$state->shareall, $userids, [], $groupids);
    }
}
