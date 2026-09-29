<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport\form;

defined('MOODLE_INTERNAL') || die();

/** Form for explicitly starting a read-only item-content audit. */
final class item_content_audit extends \moodleform {
    /** Define the audit filters and actions. */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'filters', get_string('audititemcontentfilters', 'block_exaport'));
        $mform->addElement('text', 'itemid', get_string('audititemcontentitemid', 'block_exaport'));
        $mform->setType('itemid', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('itemid', 'audititemcontentitemid', 'block_exaport');

        $mform->addElement('text', 'samplelimit', get_string('audititemcontentsamplelimit', 'block_exaport'));
        $mform->setType('samplelimit', PARAM_RAW_TRIMMED);
        $mform->setDefault('samplelimit', 20);
        $mform->addRule('samplelimit', null, 'required', null, 'client');
        $mform->addHelpButton('samplelimit', 'audititemcontentsamplelimit', 'block_exaport');

        $mform->addElement('advcheckbox', 'verbose', get_string('audititemcontentverbose', 'block_exaport'));
        $mform->addHelpButton('verbose', 'audititemcontentverbose', 'block_exaport');

        $buttons = [
            $mform->createElement('submit', 'runaudit', get_string('audititemcontentrun', 'block_exaport')),
            $mform->createElement('submit', 'downloadjson', get_string('audititemcontentdownloadjson', 'block_exaport')),
        ];
        $mform->addGroup($buttons, 'actions', '', [' '], false);
    }

    /** Validate positive IDs and the bounded sample limit. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $itemid = (string)($data['itemid'] ?? '');
        if ($itemid !== '' && (!ctype_digit($itemid) || (int)$itemid < 1)) {
            $errors['itemid'] = get_string('audititemcontentpositive', 'block_exaport');
        }
        $samplevalue = (string)($data['samplelimit'] ?? '');
        $samplelimit = (int)$samplevalue;
        if (!ctype_digit($samplevalue) || $samplelimit < 1 || $samplelimit > 1000) {
            $errors['samplelimit'] = get_string('audititemcontentsamplerange', 'block_exaport');
        }
        return $errors;
    }
}
