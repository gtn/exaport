<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (!file_exists($CFG->dirroot . '/blocks/exaport/lib/portfolio_plugin/'.basename(__FILE__))) {
	die('Exabis Eportfolio not installed');
}

require($CFG->dirroot . '/blocks/exaport/lib/portfolio_plugin/'.basename(__FILE__));
