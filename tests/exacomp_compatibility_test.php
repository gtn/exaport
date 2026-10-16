<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport;

use block_exaport\local\exacomp_compatibility;

defined('MOODLE_INTERNAL') || die();

/** Installation and upgrade must reject unsafe optional Exacomp code. */
final class exacomp_compatibility_test extends \advanced_testcase {
    /** Invoke real installation/upgrade callbacks against temporary deployed code. */
    private function with_exacomp(?int $version, callable $call): void {
        global $CFG;
        class_exists(exacomp_compatibility::class);
        get_string('exacompupdaterequired', 'block_exaport');
        require_once(__DIR__ . '/../db/install.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        $directory = make_request_directory();
        if ($version !== null) {
            mkdir($directory . '/blocks/exacomp', 0777, true);
            file_put_contents($directory . '/blocks/exacomp/version.php',
                '<?php $plugin->component = "block_exacomp"; $plugin->version = ' . $version . ';');
        }
        $dirroot = $CFG->dirroot;
        try {
            $CFG->dirroot = $directory;
            $call();
        } finally {
            $CFG->dirroot = $dirroot;
        }
    }

    /** With no Exacomp code, neither installation nor recovery requires it. */
    public function test_standalone_installation_and_recovery_are_allowed(): void {
        $this->resetAfterTest(true);
        $this->with_exacomp(null, static function(): void {
            xmldb_block_exaport_install();
            xmldb_block_exaport_install_recovery();
        });
        $this->addToAssertionCount(1);
    }

    /** Old deployed code is unsafe even when its integration setting is disabled. */
    public function test_old_exacomp_blocks_installation_recovery_and_upgrade_before_migration(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $itemid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $user->id, 'name' => 'Keep legacy data', 'type' => 'link',
            'url' => 'https://example.test/legacy', 'intro' => '', 'timemodified' => 123,
        ]);
        $before = $DB->get_record('block_exaportitem', ['id' => $itemid]);
        $blockcount = $DB->count_records('block_exaportitemblock');
        $version = get_config('block_exaport', 'version');
        $this->with_exacomp(2026091403, function(): void {
            foreach ([
                static function(): void { xmldb_block_exaport_install(); },
                static function(): void { xmldb_block_exaport_install_recovery(); },
                static function(): void { xmldb_block_exaport_upgrade(2026091001); },
            ] as $call) {
                try {
                    $call();
                    $this->fail('Old Exacomp code was accepted');
                } catch (\moodle_exception $exception) {
                    $this->assertSame('exacompupdaterequired', $exception->errorcode);
                    $this->assertStringContainsString('update the Exacomp code', $exception->getMessage());
                }
            }
        });
        $this->assertEquals($before, $DB->get_record('block_exaportitem', ['id' => $itemid]));
        $this->assertSame($blockcount, $DB->count_records('block_exaportitemblock'));
        $this->assertSame($version, get_config('block_exaport', 'version'));
    }

    /** Staging both plugins together is allowed before Exacomp's upgrade savepoint. */
    public function test_compatible_deployed_exacomp_is_accepted_with_an_old_installed_version(): void {
        $this->resetAfterTest(true);
        set_config('version', 2026091403, 'block_exacomp');
        $this->with_exacomp(exacomp_compatibility::MIN_VERSION, static function(): void {
            xmldb_block_exaport_install();
            xmldb_block_exaport_install_recovery();
        });
        $this->assertSame('2026091403', get_config('block_exacomp', 'version'));
    }
}
