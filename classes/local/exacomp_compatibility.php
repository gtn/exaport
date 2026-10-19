<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Optional Exacomp compatibility check before installing or upgrading Exaport. */
final class exacomp_compatibility {
    /** First Exacomp release that guards structured-content integration calls. */
    public const MIN_VERSION = 2026101903;

    /**
     * Reject old Exacomp code before Exaport changes its content storage.
     *
     * Inspect the deployed code, not the installed version: both plugins may be
     * staged for the same Moodle upgrade. Exacomp's runtime guard keeps calls
     * blocked until Exaport's migration has completed. No Exacomp is also valid.
     */
    public static function require_compatible_code(): void {
        global $CFG;

        $directory = $CFG->dirroot . '/blocks/exacomp';
        if (!is_dir($directory)) {
            return;
        }

        $versionfile = $directory . '/version.php';
        $plugin = new \stdClass();
        if (is_readable($versionfile)) {
            $plugin = (static function(string $path): \stdClass {
                $plugin = new \stdClass();
                require($path);
                return $plugin;
            })($versionfile);
        }

        if (($plugin->component ?? '') !== 'block_exacomp' || (int)($plugin->version ?? 0) < self::MIN_VERSION) {
            throw new \moodle_exception('exacompupdaterequired', 'block_exaport', '', self::MIN_VERSION);
        }
    }
}
