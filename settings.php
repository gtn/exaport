<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

defined('MOODLE_INTERNAL') || die;

require_once __DIR__.'/lib/lib.php';

if ($ADMIN->fulltree) {
	$settings->add(new admin_setting_configcheckbox('block_exaport_allow_loginas', get_string('settings_allow_loginas_head', 'block_exaport'),
					   get_string('settings_allow_loginas_body', 'block_exaport'), 0, 1, 0));

	//Zusammenspiel exabis ePortfolio - exabis Competences
	$settings->add(new admin_setting_configcheckbox('block_exaport_enable_interaction_competences', get_string('settings_interaktion_exacomp_head', 'block_exaport'),
					   get_string('settings_interaktion_exacomp_body', 'block_exaport'), 1, 1, 0));

	if (block_exaport_course_has_desp()) {
		$settings->add(new admin_setting_configcheckbox('block_exaport_create_desp_categories', get_string('settings_create_desp_categories_head', 'block_exaport'),
						   get_string('settings_create_desp_categories_body', 'block_exaport'), 0, 1, 0));
	}
	
	$settings->add(new admin_setting_configcheckbox('block_exaport_disable_shareall', get_string('settings_disable_shareall_head', 'block_exaport'),
					   get_string('settings_disable_shareall_body', 'block_exaport', $CFG->wwwroot.'/blocks/exaport/admin.php?action=remove_shareall'), 0));

	$settings->add(new admin_setting_configcheckbox('block_exaport_disable_external_comments', get_string('settings_disable_external_comments_head', 'block_exaport'),
					   get_string('settings_disable_external_comments_body', 'block_exaport', $CFG->wwwroot.'/blocks/exaport/admin.php?action=remove_shareall'), 0));

	$settings->add(new admin_setting_configcheckbox('block_exaport_app_externaleportfolio', get_string('block_exaport_app_externaleportfolio_head', 'block_exaport'),
					   get_string('block_exaport_app_externaleportfolio_body', 'block_exaport'), 0));	
					   
	// max size of uploading file
	$maxbytes = 0;
	if (!empty($CFG->maxbytes)) {
		$maxbytes = $CFG->maxbytes;
	}
	$max_upload_choices = get_max_upload_sizes(0, 0, 0, $maxbytes);
	// maxbytes set to 0 will allow the maximum server limit for uploads
	$a = new stdClass();
	$a->sitemaxbytes = $maxbytes ? display_size($maxbytes) : reset($max_upload_choices);
	$a->settingsurl = $CFG->wwwroot.'/admin/settings.php?section=sitepolicies';
	$settings->add(new admin_setting_configselect('block_exaport_max_uploadfile_size', get_string('block_exaport_maxbytes', 'block_exaport'),
						get_string('block_exaport_maxbytes_body', 'block_exaport', $a), 0, $max_upload_choices));
 
	// Userquota.	
	$defaultuserquota = 104857600; // 100MB
	$a = new stdClass();
	$a->bytes = !empty($CFG->userquota) ? $CFG->userquota : $defaultuserquota;
	$a->settingsurl = $CFG->wwwroot.'/admin/settings.php?section=sitepolicies';
	$settings->add(new admin_setting_configtext('block_exaport_userquota', get_string('block_exaport_userquota', 'block_exaport'),
						get_string('block_exaport_userquota_body', 'block_exaport', $a), $defaultuserquota));
	
	$settings->add(new admin_setting_configcheckbox('block_exaport_app_alloweditdelete', get_string('block_exaport_app_alloweditdelete_head', 'block_exaport'),
			get_string('block_exaport_app_alloweditdelete_body', 'block_exaport'), 1));
	
}
