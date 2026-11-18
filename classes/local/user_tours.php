<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Synchronise Exaport's guides with Moodle's native User tours tool.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_tours {
    /** One quick-check marker for the complete list; installed revisions belong to each tour. */
    private const SIGNATURE_CONFIG = 'usertourssignature';

    /** Increase only the revision of a guide that should be replaced and shown again. */
    private const TOURS = [
        // The pending editor changes both workflows. Replacements must be offered even
        // to users who completed the original "save first" or immediate-save guides.
        'upload-create' => ['file' => 'create_item.json', 'version' => 2],
        'upload-content' => ['file' => 'add_file.json', 'version' => 2],
    ];

    /**
     * Add new guides and replace only guides with an older installed revision.
     *
     * Called by the ad-hoc task after plugin installation or upgrade.
     * Never call the User tours API from an upgrade step: its schema may still be old.
     *
     * @throws \moodle_exception When the registration lock cannot be obtained.
     */
    public static function register(): void {
        global $DB;

        $signature = hash('sha256', json_encode(self::TOURS));
        if (get_config('block_exaport', self::SIGNATURE_CONFIG) === $signature || !self::is_available()) {
            return;
        }

        // Serialise registration across concurrent task executions.
        $factory = \core\lock\lock_config::get_lock_factory('block_exaport');
        $lock = $factory->get_lock('usertours', 10);
        if (!$lock) {
            // Let Moodle retain and retry the task when another attempt holds the lock.
            throw new \moodle_exception('locktimeout');
        }

        try {
            // Read directly: another task may have completed registration while we waited.
            $installed = $DB->get_field('config_plugins', 'value', [
                'plugin' => 'block_exaport', 'name' => self::SIGNATURE_CONFIG,
            ]);
            if ($installed === $signature) {
                return;
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $pending = self::TOURS;
                foreach (self::managed_tours() as $tour) {
                    $identity = $tour->get_config('block_exaport_tour');
                    if ((int)$tour->get_config('block_exaport_tour_version', 0) < self::TOURS[$identity]['version']) {
                        // Moodle removes this tour's steps and completion preferences with it.
                        $tour->remove();
                    } else {
                        unset($pending[$identity]);
                    }
                }
                foreach ($pending as $identity => $definition) {
                    $json = file_get_contents(__DIR__ . '/../../db/usertours/' . $definition['file']);
                    $tour = \tool_usertours\manager::import_tour_from_json($json);
                    $tour->set_config('block_exaport_tour', $identity);
                    $tour->set_config('block_exaport_tour_version', $definition['version']);
                    $tour->mark_major_change();
                }
                set_config(self::SIGNATURE_CONFIG, $signature, 'block_exaport');
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                // Finish rollback before the registration lock is released or a task runner retries.
                $transaction->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /** Remove only Exaport's registered guides when the plugin is uninstalled. */
    public static function uninstall(): void {
        if (self::is_available()) {
            foreach (self::managed_tours() as $tour) {
                $tour->remove();
            }
        }
        unset_config(self::SIGNATURE_CONFIG, 'block_exaport');
    }

    /** The native tool can be absent or not yet installed during initial site setup. */
    private static function is_available(): bool {
        global $CFG, $DB;

        return is_readable($CFG->dirroot . '/' . $CFG->admin . '/tool/usertours/version.php') &&
            class_exists(\tool_usertours\manager::class) &&
            $DB->get_manager()->table_exists('tool_usertours_tours') &&
            $DB->get_manager()->table_exists('tool_usertours_steps');
    }

    /**
     * Find our own guides without relying on editable names or database-specific JSON queries.
     *
     * @return \tool_usertours\tour[] Guides bearing a known Exaport identity.
     */
    private static function managed_tours(): array {
        global $DB;

        $managed = [];
        foreach ($DB->get_records('tool_usertours_tours', null, 'id') as $record) {
            $config = json_decode($record->configdata);
            $identity = $config->block_exaport_tour ?? null;
            if (is_string($identity) && isset(self::TOURS[$identity])) {
                $managed[(int)$record->id] = \tool_usertours\tour::load_from_record($record);
            }
        }
        return $managed;
    }
}
