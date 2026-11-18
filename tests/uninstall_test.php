<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/uninstall.php');

/**
 * Uninstall removes portfolio relationships without erasing Exacomp learning records.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_block_exaport_uninstall
 */
final class uninstall_test extends \advanced_testcase {
    /** @var string[] Minimal optional tables created only for the current test. */
    private array $createdtables = [];

    /** @var string[] Optional relationship tables, independent of Exacomp's PHP code. */
    private const TABLES = [
        'block_exacompcompactiv_mm',
        'block_exacompcompuser_mm',
        'block_exacompitem_mm',
        'block_exacompitemcollab_mm',
        'block_exacompprofilesettings',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // Missing/partial installations require DDL; never commit a PHPUnit rollback transaction implicitly.
        $this->preventResetByRollback();
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
    }

    protected function tearDown(): void {
        global $DB;

        foreach (array_reverse($this->createdtables) as $name) {
            $DB->get_manager()->drop_table(new \xmldb_table($name));
        }
        parent::tearDown();
    }

    /** Insert real database rows even when optional Exacomp is not installed. */
    private function insert_fixture(string $name, array $data): int {
        global $DB;

        $table = new \xmldb_table($name);
        $manager = $DB->get_manager();
        if (!$manager->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            foreach ($data as $field => $value) {
                $type = is_int($value) ? XMLDB_TYPE_INTEGER : XMLDB_TYPE_CHAR;
                $table->add_field($field, $type, is_int($value) ? '10' : '255', null, XMLDB_NOTNULL);
            }
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $manager->create_table($table);
            $this->createdtables[] = $name;
        }
        return (int)$DB->insert_record($name, $data);
    }

    public function test_uninstall_removes_only_portfolio_links_even_when_integration_is_disabled(): void {
        global $DB, $USER;

        $itemid = (int)$DB->insert_record('block_exaportitem', [
            'userid' => $USER->id, 'name' => 'Existing portfolio item', 'url' => '', 'attachment' => '',
        ]);
        $orphanid = $itemid + 1000;
        $compid = $this->insert_fixture('block_exacompdescriptors', ['title' => 'Keep the definition']);

        foreach (['block_exacompcompactiv_mm', 'block_exacompcompuser_mm'] as $table) {
            $data = ['activityid' => $itemid, 'compid' => $compid, 'comptype' => 0];
            if ($table === 'block_exacompcompuser_mm') {
                $data += ['userid' => (int)$USER->id, 'reviewerid' => (int)$USER->id];
            }
            // The same numeric activity ID can belong to a Moodle activity or a portfolio item.
            $keepid = $this->insert_fixture($table, $data + ['eportfolioitem' => 0]);
            $keep[$table] = $DB->get_records($table, ['id' => $keepid]);
            $this->insert_fixture($table, $data + ['eportfolioitem' => 1]);
            $data['activityid'] = $orphanid;
            $this->insert_fixture($table, $data + ['eportfolioitem' => 1]);
        }
        foreach ([$itemid, $orphanid] as $id) {
            $this->insert_fixture('block_exacompitem_mm', [
                'itemid' => $id, 'exacomp_record_id' => $compid, 'competence_type' => 0, 'timecreated' => time(),
            ]);
            $this->insert_fixture('block_exacompitemcollab_mm', ['itemid' => $id, 'userid' => (int)$USER->id]);
            $this->insert_fixture('block_exacompprofilesettings', [
                'block' => 'exaport', 'itemid' => $id, 'userid' => (int)$USER->id, 'feedback' => '1',
            ]);
        }
        foreach (['exacomp', 'exastud'] as $block) {
            $this->insert_fixture('block_exacompprofilesettings', [
                'block' => $block, 'itemid' => $itemid, 'userid' => (int)$USER->id, 'feedback' => '1',
            ]);
        }
        $this->insert_fixture('block_exacompcompuser', [
            'userid' => (int)$USER->id, 'reviewerid' => (int)$USER->id, 'compid' => $compid, 'courseid' => SITEID,
        ]);
        $this->insert_fixture('block_exacomplp_items', [
            'learningpathid' => 1, 'exampleid' => $itemid, 'sorting' => 0, 'visible' => 1,
        ]);
        foreach (['block_exacompdescriptors', 'block_exacompcompuser', 'block_exacomplp_items'] as $table) {
            $untouched[$table] = $DB->get_records($table);
        }
        $profiles = $DB->get_records_select('block_exacompprofilesettings', 'block <> ?', ['exaport']);

        $this->assertTrue(xmldb_block_exaport_uninstall());

        foreach ($keep as $table => $records) {
            $this->assertEquals($records, $DB->get_records($table));
        }
        foreach (['block_exacompitem_mm', 'block_exacompitemcollab_mm'] as $table) {
            $this->assertSame(0, $DB->count_records($table));
        }
        $this->assertEquals($profiles, $DB->get_records('block_exacompprofilesettings'));
        foreach ($untouched as $table => $records) {
            $this->assertEquals($records, $DB->get_records($table));
        }

        $this->assertTrue(xmldb_block_exaport_uninstall(), 'Cleanup must also be safe after an earlier uninstall attempt.');
        foreach ($keep + $untouched as $table => $records) {
            $this->assertEquals($records, $DB->get_records($table));
        }
        $this->assertEquals($profiles, $DB->get_records('block_exacompprofilesettings'));
    }

    public function test_uninstall_handles_absent_and_partially_removed_exacomp_tables(): void {
        global $DB, $USER;

        $removeid = $this->insert_fixture('block_exacompprofilesettings', [
            'block' => 'exaport', 'itemid' => 42, 'userid' => (int)$USER->id, 'feedback' => '1',
        ]);
        $keepid = $this->insert_fixture('block_exacompprofilesettings', [
            'block' => 'exacomp', 'itemid' => 42, 'userid' => (int)$USER->id, 'feedback' => '1',
        ]);
        $manager = $DB->get_manager();
        $hidden = [];
        try {
            foreach (self::TABLES as $index => $name) {
                if ($manager->table_exists(new \xmldb_table($name))) {
                    $temporary = 'block_exaport_uninstall_' . $index;
                    $manager->rename_table(new \xmldb_table($name), $temporary);
                    $hidden[$name] = $temporary;
                }
            }
            $this->assertTrue(xmldb_block_exaport_uninstall(), 'Exacomp may already have been uninstalled.');

            // Leave only the final optional table available: missing earlier tables must not stop cleanup.
            $profiletable = 'block_exacompprofilesettings';
            $manager->rename_table(new \xmldb_table($hidden[$profiletable]), $profiletable);
            unset($hidden[$profiletable]);
            $this->assertTrue(xmldb_block_exaport_uninstall());
            $this->assertFalse($DB->record_exists($profiletable, ['id' => $removeid]));
            $this->assertTrue($DB->record_exists($profiletable, ['id' => $keepid]));
        } finally {
            foreach ($hidden as $original => $temporary) {
                $manager->rename_table(new \xmldb_table($temporary), $original);
            }
        }
    }
}
