<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Register the document-upload guides with Moodle's User tours tool.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upload_tours {
    /** Registration revision; existing tours remain under the administrator's control. */
    private const VERSION = 1;

    /** Stable identities also travel with exported/imported tour definitions. */
    private const TOURS = [
        'upload-create' => 'create_item.json',
        'upload-content' => 'add_file.json',
    ];

    /**
     * Install each guide once without overwriting edits, disabled tours or later deletions.
     *
     * Called by the post-upgrade ad-hoc task, or by the first item page before cron runs.
     * Never call the User tours API from an upgrade step: its schema may still be old.
     */
    public static function register(): void {
        global $DB;

        if (!self::is_available() || (int)get_config('block_exaport', 'uploadtoursversion') >= self::VERSION) {
            return;
        }

        // First visits can happen concurrently after a fresh Moodle installation.
        $factory = \core\lock\lock_config::get_lock_factory('block_exaport');
        $lock = $factory->get_lock('uploadtours', 10);
        if (!$lock) {
            return;
        }

        try {
            // Read directly: another request may have completed registration while we waited.
            $installed = $DB->get_field('config_plugins', 'value', [
                'plugin' => 'block_exaport', 'name' => 'uploadtoursversion',
            ]);
            if ((int)$installed >= self::VERSION) {
                return;
            }

            $transaction = $DB->start_delegated_transaction();
            $existing = self::managed_tours();
            foreach (self::TOURS as $identity => $filename) {
                if (in_array($identity, $existing, true)) {
                    continue;
                }
                $json = file_get_contents(__DIR__ . '/../../db/usertours/' . $filename);
                $tour = \tool_usertours\manager::import_tour_from_json($json);
                $tour->mark_major_change();
            }
            set_config('uploadtoursversion', self::VERSION, 'block_exaport');
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /** Remove only Exaport's registered guides when the plugin is uninstalled. */
    public static function uninstall(): void {
        if (self::is_available()) {
            foreach (self::managed_tours() as $id => $identity) {
                \tool_usertours\tour::instance($id)->remove();
            }
        }
        unset_config('uploadtoursversion', 'block_exaport');
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
     * @return array<int, string> Tour IDs mapped to their stable Exaport identities.
     */
    private static function managed_tours(): array {
        global $DB;

        $managed = [];
        foreach ($DB->get_records('tool_usertours_tours', null, 'id', 'id, configdata') as $record) {
            $config = json_decode($record->configdata);
            $identity = $config->block_exaport_tour ?? null;
            if (is_string($identity) && isset(self::TOURS[$identity])) {
                $managed[(int)$record->id] = $identity;
            }
        }
        return $managed;
    }
}
