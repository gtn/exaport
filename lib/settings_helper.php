<?php
defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../inc.php';


class block_exaport_layout_configtable extends admin_setting_configtext {

    public function get_setting() {
        $layout_settings = parent::get_setting();
        $result = unserialize($layout_settings ?? '');
        return $result;
    }

    public function write_setting($data) {
        // All layout options in single serialized array
        $result = [];
        if ($data) {
            foreach ($data as $parameter => $value) {
                $result[$parameter] = $value;
            }
        }
        $result = serialize($result);
        $result = parent::write_setting($result);
        return $result;
    }

    public function output_html($data, $query = '') {
        $return = '';
        $table = new html_table();
        $table->head = array('');
        // header
        $table->head = [
            '',
            block_exaport_get_string('layout_settings_font_size'),
            block_exaport_get_string('layout_settings_font_weight'),
            block_exaport_get_string('layout_settings_border_width'),
        ];
        // rows with settings
        // headers options
        $row = new html_table_row();
        $row->cells[] = new html_table_cell(block_exaport_get_string('layout_settings_view_headers'));
        $row->cells[] = html_writer::select(
            block_exaport_layout_fontsizes(),
            's__block_exaport_layout_settings[header_fontSize]',
            (@$data['header_fontSize'] > -1 ? @$data['header_fontSize'] : -1),
            false,
            ['id' => 'header_fontSize']);
        $row->cells[] = html_writer::checkbox('s__block_exaport_layout_settings[headerBold]', '1', (@$data['header_fontSize'] ? true : false));
        $row->cells[] = html_writer::select(
                block_exaport_layout_borderwidths(),
                's__block_exaport_layout_settings[header_borderWidth]',
                (@$data['header_borderWidth'] > -1 ? $data['header_borderWidth'] : -1),
                false,
                ['id' => 'header_borderWidth']) . '<br>' . html_writer::tag('small', block_exaport_get_string('layout_settings_border_width_only_bottom'));
        $table->data[] = $row;
        // content options
        $row = new html_table_row();
        $row->cells[] = new html_table_cell(block_exaport_get_string('layout_settings_view_content'));
        $row->cells[] = html_writer::select(
            block_exaport_layout_fontsizes(),
            's__block_exaport_layout_settings[text_fontSize]',
            (@$data['text_fontSize'] > -1 ? $data['text_fontSize'] : -1),
            false,
            ['id' => 'text_fontSize']);
        $row->cells[] = '';
        $row->cells[] = html_writer::select(
            block_exaport_layout_borderwidths(),
            's__block_exaport_layout_settings[block_borderWidth]',
            (@$data['block_borderWidth'] > -1 ? @$data['block_borderWidth'] : -1),
            false,
            ['id' => 'block_borderWidth']);
        $table->data[] = $row;
        // custom css
        $row = new html_table_row();
        $row->cells[] = new html_table_cell(block_exaport_get_string('layout_settings_custom_css'));
        $textareaCell = new \html_table_cell();
        $textareaCell->colspan = 3;
        $textareaCell->text = html_writer::tag(
            'textarea',
            (@$data['customCss'] ?: ''),
            array(
                'id' => 'customCss',
                'name' => 's__block_exaport_layout_settings[customCss]',
                'class' => 'form-control',
                'rows' => 5,
                'cols' => 40,
            )
        );
        $row->cells[] = $textareaCell;
        $table->data[] = $row;

        $return .= html_writer::table($table);

        // Get standard settings parameters template.
        $template = format_admin_setting($this, $this->visiblename, $return,
            $this->description, true, '', '', $query);
        // Hide some html for better view of these settings.
        $doc = new DOMDocument();
        $template = mb_convert_encoding($template, 'HTML-ENTITIES', 'UTF-8');
        $doc->loadHTML($template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $selector = new DOMXPath($doc);
        // Delete div with classes.
        $deletedivs = array('form-defaultinfo');
        foreach ($deletedivs as $deletediv) {
            foreach ($selector->query('//div[contains(attribute::class, "' . $deletediv . '")]') as $e) {
                $e->parentNode->removeChild($e);
            }
        }
        // Delete spans with classes
        $deletespans = array('form-shortname');
        foreach ($deletespans as $deletespan) {
            foreach ($selector->query('//span[contains(attribute::class, "' . $deletespan . '")]') as $e) {
                $e->parentNode->removeChild($e);
            }
        }
        //         Change col-sm-9 -> col-sm-12 if it is here.
        $template = $doc->saveHTML($doc->documentElement);
        return $template;
    }

}

// readonly input
class admin_setting_configtext_readonly extends admin_setting_configtext {

    public function output_html($data, $query='') {
        $html = parent::output_html($data, $query);
        $html = str_replace('<input ', '<input readonly ', $html);
        return $html;
    }

}

/**
 * special class to get/request passphrase for WordPress SSO integration
 */
class admin_setting_wpSSOregister extends admin_setting_configtext {

    public function output_html($data, $query='') {
        global $OUTPUT, $CFG;

        // Ignore the form in "upgrading" settings pages
        if (!empty($CFG->upgraderunning) || (
                isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'admin/upgradesettings.php') !== false
            ))
        {
            set_config('block_exaport_wp_sso_passphrase', '--not-used-yet--');
            return '';
        }

        $wpSSOurl = block_exaport_get_wpsso_url();
        $urlToSettings = new moodle_url('/admin/settings.php', ['section' => 'blocksettingexaport'], 'admin-block_exaport_mysource');
        if (!$wpSSOurl) {
            // NO CONFIGURED message
            $context = (object) [
                'message' => get_string('settings_exaport_wp_sso_error_no_url_configured', 'block_exaport'),
                'reloadUrl' => $urlToSettings->out(),
            ];
            $element = $OUTPUT->render_from_template('block_exaport/settings_wp_sso_passphrase_no_wpurl_configured', $context);

            return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', NULL, $query);
        }

        $currpassphrase = block_exaport_get_wpsso_passphrase();
        if (!$currpassphrase || $currpassphrase == '--not-used-yet--') {
            // NO registered passphrase
            $wpIntegration = new \wpIntegration(0, '');
            $element = $wpIntegration->exaportSettingsPasshpraseNotRegisteredForm();

            return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', NULL, $query);
        }

        // the passphrase os configured!

        $wpIntegration = new \wpIntegration(0, '');
        $element = $wpIntegration->exaportSettingsPasshpraseForm();

        return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', NULL, $query);

    }
}

// Add custom JS into settings.php
class block_exaport_admin_setting_withjs extends admin_setting {
    public function __construct($name, $visiblename, $description = '') {
        parent::__construct($name, $visiblename, $description, '');
    }

    public function get_setting() {
        return true;
    }

    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        global $CFG, $PAGE;

        $PAGE->requires->jquery();
        $PAGE->requires->js_call_amd('block_exaport/exaport_settings_wpsso', 'init');

        $script = ''; // empty string to make fake element;

        return format_admin_setting($this, $this->visiblename, $script, $this->description, false, '', '', $query);
    }
}
