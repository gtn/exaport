<?php

require_once __DIR__.'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
require_login($courseid);

echo 'course id: '.$COURSE->id."<br />\n";

// desp block installed?
if (!is_dir(__DIR__.'/../desp'))
	die ('no desp installed');

$context = context_course::instance($COURSE->id);
if (!$DB->record_exists('block_instances', array('blockname'=>'desp', 'parentcontextid'=>$context->id)))
	die('no desp in course!');

if ($DB->record_exists('block_exaportcate', array('userid'=>$USER->id)))
	die('user has categories');
	
die ('create categories!');

