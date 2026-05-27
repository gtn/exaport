<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

defined('MOODLE_INTERNAL') || die;

// require_once __DIR__ . '/lib/exabis_special_id_generator.php';

require_once(__DIR__ . '/lib/lib.php');
require_once __DIR__ . '/lib/settings_helper.php';

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('block_exaport_allow_loginas',
        get_string('settings_allow_loginas_head_alternative', 'block_exaport'),
        get_string('settings_allow_loginas_body', 'block_exaport'), 0, 1, 0));

    // Zusammenspiel exabis ePortfolio - exabis Competences.
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_interaction_competences',
        get_string('settings_interaktion_exacomp_head', 'block_exaport'),
        get_string('settings_interaktion_exacomp_body', 'block_exaport'), 1, 1, 0));

    if (block_exaport_course_has_desp()) {
        $settings->add(new admin_setting_configcheckbox('block_exaport_create_desp_categories',
            get_string('settings_create_desp_categories_head', 'block_exaport'),
            get_string('settings_create_desp_categories_body', 'block_exaport'), 0, 1, 0));
    }

    $settings->add(new admin_setting_configcheckbox('block_exaport_disable_shareall',
        get_string('settings_disable_shareall_head', 'block_exaport'),
        get_string('settings_disable_shareall_body', 'block_exaport',
            $CFG->wwwroot . '/blocks/exaport/admin.php?action=remove_shareall&sesskey=' . sesskey()), 0));

    $settings->add(new admin_setting_configcheckbox('block_exaport_disable_externaccess',
        get_string('settings_disable_externaccess_head', 'block_exaport'),
        get_string('settings_disable_externaccess_body', 'block_exaport'), 0));

    $settings->add(new admin_setting_configcheckbox('block_exaport_disable_shareemails',
        get_string('settings_disable_shareemails_head', 'block_exaport'),
        get_string('settings_disable_shareemails_body', 'block_exaport'), 0));

    $settings->add(new admin_setting_configcheckbox('block_exaport_disable_external_comments',
        get_string('settings_disable_external_comments_head', 'block_exaport'),
        get_string('settings_disable_external_comments_body', 'block_exaport',
            $CFG->wwwroot . '/blocks/exaport/admin.php?action=remove_shareall&sesskey=' . sesskey()), 0));

    /*
    $settings->add(new admin_setting_configcheckbox('block_exaport_app_externaleportfolio',
        get_string('block_exaport_app_externaleportfolio_head', 'block_exaport'),
        get_string('block_exaport_app_externaleportfolio_body', 'block_exaport'), 0));
    */

    // Max size of uploading file.
    $maxbytes = 0;
    if (!empty($CFG->maxbytes)) {
        $maxbytes = $CFG->maxbytes;
    }
    $maxuploadchoices = get_max_upload_sizes(0, 0, 0, $maxbytes);
    // Maxbytes set to 0 will allow the maximum server limit for uploads.
    $a = new stdClass();
    $a->sitemaxbytes = $maxbytes ? display_size($maxbytes) : reset($maxuploadchoices);
    $a->settingsurl = $CFG->wwwroot . '/admin/settings.php?section=sitepolicies#admin-maxbytes';
    $settings->add(new admin_setting_configselect('block_exaport_max_uploadfile_size',
        get_string('block_exaport_maxbytes', 'block_exaport'),
        get_string('block_exaport_maxbytes_body', 'block_exaport', $a), 0, $maxuploadchoices));

    // Userquota.
    $defaultuserquota = 104857600; // 100MB.
    $a = new stdClass();
    $a->bytes = !empty($CFG->userquota) ? $CFG->userquota : $defaultuserquota;
    $a->settingsurl = $CFG->wwwroot . '/admin/settings.php?section=sitepolicies#admin-userquota';
    $settings->add(new admin_setting_configtext('block_exaport_userquota', get_string('block_exaport_userquota', 'block_exaport'),
        get_string('block_exaport_userquota_body', 'block_exaport', $a), $defaultuserquota));

    $settings->add(new admin_setting_configcheckbox('block_exaport_app_alloweditdelete',
        get_string('block_exaport_app_alloweditdelete_head_alternative', 'block_exaport'),
        get_string('block_exaport_app_alloweditdelete_body', 'block_exaport'), 1));

    // Teacher can see all artifacts from own students
    // check profile fiedl exists
    if (!$field = $DB->get_record('user_info_field', array('shortname' => 'blockexaporttrustedteacher'))) {
        $link = $CFG->wwwroot . '/blocks/exaport/admin.php?action=create_trustedteacherproperty&sesskey=' . sesskey();
        $linktocreateuserproperty = '<a href="' . $link . '" target="_blank">';
        $linktocreateuserproperty .= get_string('block_exaport_teachercanseeartifactsofstudents_configurationlink',
            'block_exaport');
        $linktocreateuserproperty .= '</a><br />';
    } else {
        $linktocreateuserproperty = '';
    }
    $settings->add(new admin_setting_configcheckbox('block_exaport_teachercanseeartifactsofstudents',
        get_string('block_exaport_teachercanseeartifactsofstudents_head_alternative', 'block_exaport'),
        get_string('block_exaport_teachercanseeartifactsofstudents_body', 'block_exaport', $linktocreateuserproperty), 0));

    // Items with multiple files
    $settings->add(new admin_setting_configcheckbox('block_exaport_multiple_files_in_item',
        get_string('block_exaport_multiplefilesinitem', 'block_exaport'),
        get_string('block_exaport_multiplefilesinitem_body', 'block_exaport'), 0));

    // Enable "Copy shared category to my portfolio" (artifacts)
    $settings->add(new admin_setting_configcheckbox('block_exaport_copy_category_to_my',
        get_string('block_exaport_copytomyportfolio', 'block_exaport'),
        get_string('block_exaport_copytomyportfolio_body', 'block_exaport'), 0));

    $layoutKeys = ['moodle_bootstrap', 'clean_old'];
    $layouts = array_combine($layoutKeys, array_map(function($layoutKey) {
        return get_string('block_exaport_used_layout_' . $layoutKey, 'block_exaport');
    }, $layoutKeys));

    $settings->add(new admin_setting_configcheckbox('block_exaport/alwaysnotifywhenshare',
        get_string('alwaysnotifywhenshare', 'block_exaport'),
        get_string('alwaysnotifywhenshare_description', 'block_exaport'),
        0));

    $settings->add(new admin_setting_configselect('block_exaport_used_layout',
        get_string('block_exaport_used_layout', 'block_exaport'),
        get_string('block_exaport_used_layout_body', 'block_exaport', $a), 0, $layouts));

    /*
    // Export settings
    $settings->add(new admin_setting_heading('exaport/export_settings',
        get_string('settings_export_settings_heading', 'block_exaport'),
        ''));
    // Generate mysource if it is empty
    $id = get_config('block_exaport', 'mysource');
    if (!$id || !block_exaport\exabis_special_id_generator::validate_id($id)) {
        set_config('mysource', block_exaport\exabis_special_id_generator::generate_random_id('EXAPORT'), 'block_exaport');
    }
    $settings->add(new admin_setting_configtext('block_exaport/mysource',
        get_string('settings_exaport_mysource', 'block_exaport'),
        get_string('settings_exaport_mysource_body', 'block_exaport'),
        ''));
    $settings->add(new admin_setting_configcheckbox('block_exaport/wp_sso_enabled',
        get_string('settings_exaport_wp_sso_enabled', 'block_exaport'),
        get_string('settings_exaport_wp_sso_enabled_body', 'block_exaport'),
        0));
    $settings->add(new admin_setting_configtext('block_exaport/wp_sso_url',
        get_string('settings_exaport_wp_sso_url', 'block_exaport'),
        get_string('settings_exaport_wp_sso_url_body', 'block_exaport'),
        'https://exacloud.at/'));
    // add JS - like a fake option
    $settings->add(new block_exaport_admin_setting_withjs(
        'block_exaport_button_with_js',
        '',
        ''
    ));
    $settings->add(new admin_setting_wpSSOregister('block_exaport/wp_sso_passphrase',
        get_string('settings_exaport_wp_sso_passphrase', 'block_exaport'),
        '',//get_string('settings_exaport_wp_sso_passphrase_body', 'block_exaport'),
        '--not-used-yet--'));


    */
    // Navigation areas.
    $settings->add(new admin_setting_heading('exaport/navigation_areas',
        get_string('settings_navigation_areas_heading', 'block_exaport'),
        ''));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_whyeportfolio',
        get_string('settings_enable_whyeportfolio_head', 'block_exaport'),
        get_string('settings_enable_whyeportfolio_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_resume',
        get_string('settings_enable_resume_head', 'block_exaport'),
        get_string('settings_enable_resume_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_myportfolio',
        get_string('settings_enable_myportfolio_head', 'block_exaport'),
        get_string('settings_enable_myportfolio_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_views',
        get_string('settings_enable_views_head', 'block_exaport'),
        get_string('settings_enable_views_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_shared_views',
        get_string('settings_enable_shared_views_head', 'block_exaport'),
        get_string('settings_enable_shared_views_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_shared_categories',
        get_string('settings_enable_shared_categories_head', 'block_exaport'),
        get_string('settings_enable_shared_categories_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_importexport',
        get_string('settings_enable_importexport_head', 'block_exaport'),
        get_string('settings_enable_importexport_body', 'block_exaport'), 1));
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_category_distribution',
        get_string('settings_enable_category_distribution_head', 'block_exaport'),
        get_string('settings_enable_category_distribution_body', 'block_exaport'), 1));

    // View custom template settings
    $settings->add(new admin_setting_heading('exaport/layout_settings',
        get_string('settings_layout_settings_heading', 'block_exaport'),
        ''));
    // allow "custom layouts" for view owners
    $settings->add(new admin_setting_configcheckbox('block_exaport_allow_custom_layout',
        get_string('block_exaport_allowcustomlayout_head', 'block_exaport'),
        get_string('block_exaport_allowcustomlayout_body', 'block_exaport'), 0));
    //  the table with layout settings
    $settings->add(new block_exaport_layout_configtable('block_exaport_layout_settings', block_exaport_get_string('settings_layout_settings_description'), '', ''));

    // Category distribution starter templates
    $settings->add(new admin_setting_heading('exaport/category_distribution_settings',
        get_string('settings_category_distribution_heading', 'block_exaport'),
        get_string('settings_category_distribution_description', 'block_exaport')));

    // Generic starter category templates (JSON).
    $default_templates = json_encode(array(
        array(
            'name' => 'Generic starter template',
            'tree' => array(
                'name' => 'Portfolio',
                'share_to_teachers' => 0,
                'children' => array(
                    array('name' => 'Evidence', 'share_to_teachers' => 0),
                    array('name' => 'Reflections', 'share_to_teachers' => 0),
                    array('name' => 'Feedback', 'share_to_teachers' => 0),
                    array('name' => 'Assessments', 'share_to_teachers' => 0),
                ),
            ),
        ),
    ), JSON_UNESCAPED_UNICODE);

    $settings->add(new admin_setting_configtextarea('block_exaport/starter_templates',
        get_string('settings_starter_templates', 'block_exaport'),
        get_string('settings_starter_templates_description', 'block_exaport'),
        $default_templates,
        PARAM_TEXT, 60, 10));

    // Generic starter view templates (JSON).
    $default_view_templates = json_encode(array(
        array(
            'name' => 'Generic starter template',
            'views' => array(
                array(
                    'name' => 'Portfolio',
                    'description' => 'This view has been automatically created',
                    'share_to_teachers' => 1,
                ),
            ),
        ),
    ), JSON_UNESCAPED_UNICODE);

    $settings->add(new admin_setting_configtextarea('block_exaport/starter_view_templates',
        get_string('settings_starter_view_templates', 'block_exaport'),
        get_string('settings_starter_view_templates_description', 'block_exaport'),
        $default_view_templates,
        PARAM_TEXT, 60, 10));


}
