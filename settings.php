<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    
    //Zusammenspiel exabis ePortfolio - exabis Competences
    $settings->add(new admin_setting_configcheckbox('block_enable_interaction_competences', get_string('settings_interaktion_exacomp_head', 'block_exaport'),
                       get_string('settings_interaktion_exacomp_body', 'block_exaport'), 0, 1, 0));
    
   

    //setup default paths for following configs.
    if ($CFG->ostype == 'WINDOWS') {
        $default_pdf_to_text_cmd = "lib/xpdf/win32/pdftotext.exe -eol dos -enc UTF-8 -q";
        $default_word_to_text_cmd = "lib/antiword/win32/antiword/antiword.exe ";
        $default_word_to_text_env = "HOME={$CFG->dirroot}\\lib\\antiword\\win32";
    } else {
        $default_pdf_to_text_cmd = "lib/xpdf/linux/pdftotext -enc UTF-8 -eol unix -q";
        $default_word_to_text_cmd = "lib/antiword/linux/usr/bin/antiword";
        $default_word_to_text_env = "ANTIWORDHOME={$CFG->dirroot}/lib/antiword/linux/usr/share/antiword";
    }



}

