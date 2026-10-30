<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** A positively identified source-byte finding, documented before metadata migration begins. */
final class migration_source_exception extends \coding_exception {
    /** @var string Stable administrator-report reason. */
    public $reason;

    public function __construct(string $reason) {
        $this->reason = $reason;
        parent::__construct('Legacy migration source verification failed: ' . $reason);
    }
}
