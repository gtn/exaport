<?php
// This file is part of Moodle - http://moodle.org/.
defined('MOODLE_INTERNAL') || die();

$callbacks = [[
    'hook' => \core\hook\after_config::class,
    'callback' => [\block_exaport\local\migration_access::class, 'after_config'],
]];
