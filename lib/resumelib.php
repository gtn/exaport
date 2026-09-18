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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class block_exaport_resume_editor_form extends block_exaport_moodleform {

    public function definition() {

        global $CFG, $USER, $DB, $COURSE;
        $mform =& $this->_form;

        $param = $this->_customdata['field'];
        $withfiles = $this->_customdata['withfiles'];
        if (!$withfiles) {
            $withfiles = false;
        }

        $mform->addElement('html', '<div class="block_eportfolio_center">' . $this->_customdata['formheader'] . '</div>');

        $mform->addElement('editor', $param . '_editor', get_string('resume_' . $param, 'block_exaport'), null,
            array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        $mform->add_exaport_help_button($param . '_editor', 'forms.resume.' . $param . '_editor');

        if ($withfiles) {
            $mform->addElement('filemanager', 'attachments', get_string('resume_files', 'block_exaport'), null,
                array('subdirs' => false, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'maxfiles' => 5));
            $mform->add_exaport_help_button('attachments', 'forms.resume.attachments');
        }

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $this->add_action_buttons();
    }

}

class block_exaport_resume_multifields_form extends block_exaport_moodleform {

    public function definition() {

        global $CFG, $USER, $DB;
        $mform =& $this->_form;

        $attributestext = array('size' => '50');
        $attributestextarea = array('cols' => '47');

        $inputs = $this->_customdata['inputs'];

        // Form's header.
        $mform->addElement('html', '<div class="block_eportfolio_center">' . $this->_customdata['formheader'] . '</div>');

        if (isset($inputs) && is_array($inputs) && count($inputs) > 0) {
            foreach ($inputs as $fieldname => $fieldtype) {
                list ($type, $required) = explode(':', $fieldtype . ":");
                switch ($type) {
                    case 'text' :
                        $mform->addElement('text', $fieldname, get_string('resume_' . $fieldname, 'block_exaport'), $attributestext);
                        $mform->setType($fieldname, PARAM_RAW);
                        $mform->add_exaport_help_button($fieldname, 'forms.resume.' . $fieldname);
                        break;
                    case 'textarea' :
                        $mform->addElement('textarea', $fieldname, get_string('resume_' . $fieldname, 'block_exaport'),
                            $attributestextarea);
                        $mform->setType($fieldname, PARAM_RAW);
                        $mform->add_exaport_help_button($fieldname, 'forms.resume.' . $fieldname);
                        break;
                    case 'filearea' :
                        $mform->addElement('filemanager', 'attachments', get_string('resume_' . $fieldname, 'block_exaport'), null,
                            array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
                        $mform->add_exaport_help_button('attachments', 'forms.resume.attachments_' . $fieldname);
                        break;
                };
                // Required field.
                if ($required == 'required') {
                    $mform->addRule($fieldname, null, 'required');
                }
            }
        };

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'resume_id');
        $mform->setType('resume_id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $this->add_action_buttons();
    }

}

class block_exaport_resume_checkboxlist_form extends block_exaport_moodleform {

    public function definition() {

        global $CFG, $USER, $DB;
        $mform =& $this->_form;
        $records = $this->_customdata['records'];
        // Form's header.
        $mform->addElement('html', '<div class="block_eportfolio_center">' . $this->_customdata['formheader'] . '</div>');

        if (isset($records) && is_array($records) && count($records) > 0) {
            foreach ($records as $record) {
                $mform->addElement('checkbox', 'check[' . $record['id'] . ']', $record['title'], $record['description']);
            }
        };

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'resume_id');
        $mform->setType('resume_id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $this->add_action_buttons();
    }

}

function block_exaport_resume_checkboxeslist_form($resume, $edit, $data) {
    global $DB, $CFG, $USER, $OUTPUT;

    $showiinformation = false;

    $records = array();
    switch ($edit) {
        case 'badges':
            $badges = block_exaport_get_all_user_badges();
            foreach ($badges as $badge) {
                $badgeimage = block_exaport_get_user_badge_image($badge);
                $records[$badge->id]['id'] = $badge->id;
                $records[$badge->id]['title'] = $badgeimage . $badge->name;
                $dateformat = get_string('strftimedate', 'langconfig');
                $records[$badge->id]['description'] = userdate($badge->dateissued, $dateformat) . ': ' . $badge->description;
            };
            $defaultvalues = $DB->get_records('block_exaportresume_' . $edit, array('resumeid' => $resume->id), null, 'badgeid');
            break;
    }

    $formheader = get_string('edit', "block_exaport") . ': ' . get_string('resume_' . $edit, "block_exaport");
    $workform = new block_exaport_resume_checkboxlist_form($_SERVER['REQUEST_URI'] . '#' . $edit,
        array('formheader' => $formheader, 'records' => $records));
    $data->check = $defaultvalues;
    $data->resume_id = $resume->id;
    $workform->set_data($data);
    if ($workform->is_cancelled()) {
        $showiinformation = true;
    } else if ($fromform = $workform->get_data()) {
        $DB->delete_records('block_exaportresume_' . $edit, array('resumeid' => $resume->id));
        // Save records.
        $sorting = 0;
        if (isset($fromform->check)) {
            $newrecords = array_keys($fromform->check);
        } else {
            $newrecords = array();
        }
        foreach ($newrecords as $id) {
            switch ($edit) {
                case 'badges':
                    $dataobject = new stdClass();
                    $dataobject->resumeid = $resume->id;
                    $dataobject->badgeid = $id;
                    $dataobject->sorting = $sorting + 10;
                    $DB->insert_record('block_exaportresume_' . $edit, $dataobject);
                    $sorting = $sorting + 10;
                    break;
            };
        };
        $showiinformation = true;
    } else {
        echo block_exaport_resume_header();
        $workform->display();
    };
    return $showiinformation;
}

function block_exaport_resume_prepare_block_mm_data($resume, $id, $typeblock, $displayinputs, $data) {
    global $DB, $CFG, $USER, $OUTPUT;

    $showinformation = false;
    $formheader = get_string('edit', "block_exaport") . ': ' . get_string('resume_' . $typeblock, "block_exaport");
    $workform = new block_exaport_resume_multifields_form($_SERVER['REQUEST_URI'] . '#' . $typeblock,
        array('formheader' => $formheader, 'inputs' => $displayinputs));
    $data->resume_id = $resume->id;
    $workform->set_data($data);

    if ($workform->is_cancelled()) {
        $showinformation = true;
    } else if ($fromform = $workform->get_data()) {
        // Save record.
        $fromform->user_id = $USER->id;
        $itemid = block_exaport_set_resume_mm($typeblock, $fromform);
        // Save uploaded file in 'resume_education' filearea.
        $context = context_user::instance($USER->id);
        // Checking userquota.
        $uploadfilesizes = block_exaport_get_filessize_by_draftid($fromform->attachments);
        if (block_exaport_file_userquotecheck($uploadfilesizes) &&
            block_exaport_get_maxfilesize_by_draftid_check($fromform->attachments)
        ) {
            file_save_draft_area_files($fromform->attachments, $context->id, 'block_exaport', 'resume_' . $typeblock, $itemid,
                array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        };
        echo "<div class='block_eportfolio_center'>" .
            $OUTPUT->box(get_string('resume_' . $typeblock . "saved", "block_exaport"), 'center') . "</div>";
        $showinformation = true;
    } else {
        if ($id > 0) {
            // Edit existing record.
            // Files.
            $draftitemid = file_get_submitted_draft_itemid('attachments');
            $context = context_user::instance($USER->id);
            file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'resume_' . $typeblock, $id,
                array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
            // All data to form.
            $data = $DB->get_record("block_exaportresume_" . $typeblock, array('id' => $id, 'resume_id' => $resume->id));
            $data->attachments = $draftitemid;
            $workform->set_data($data);
        }
        echo block_exaport_resume_header();
        $workform->display();
    };

    return $showinformation;
}

function block_exaport_get_resume_params_record($userid = null) {
    global $DB;

    if (is_null($userid)) {
        global $USER;
        $userid = $USER->id;
    }
    $conditions = array("user_id" => $userid);
    return $DB->get_record('block_exaportresume', $conditions);
}

/**
 * returns the CV data, organized as needed
 * @param int $userid
 * @param bool $full add full CV information
 * @param bool $attachmentasarealfile add real file information (real filesystem path)
 * @return false|mixed|stdClass
 * @throws coding_exception
 * @throws dml_exception
 */
function block_exaport_get_resume_params($userid = null, $full = false, $attachmentasarealfile = false) {
    global $DB, $CFG;
    if ($userid === null) {
        global $USER;
        $userid = $USER->id;
    }

    $resumeparams = block_exaport_get_resume_params_record($userid);

    // Create a new table record if no resume yet (TODO: may be to move it into block_exaport_get_resume_params_record()?)
    if (!$resumeparams) {
        $newresumeparams = new stdClass();
        $newresumeparams->user_id = $userid;
        $newresumeparams->cover = get_string("resume_template_newresume", "block_exaport");
        $DB->insert_record("block_exaportresume", $newresumeparams);
        $resumeparams = block_exaport_get_resume_params_record($userid);
    }

    // add related parameters of resume
    if ($full && $resumeparams) {
        // TODO: add images?
        $fs = get_file_storage();
        $context = context_user::instance($userid);
        $import_attachments = function($type, $recordid) use ($fs, $context, $CFG, $attachmentasarealfile) {
            $result = null;
            $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_' . $type, $recordid, 'filename', false);
            if (count($files) > 0) {
                $result = array();
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $url = $CFG->wwwroot . '/pluginfile.php/' . $file->get_contextid() . '/block_exaport/resume_' . $type . '/' . $file->get_itemid() .
                        '/' . $filename;
                    $addfiledata = array('filename' => $filename, 'fileurl' => $url);
                    if ($attachmentasarealfile) {
                        // add file data as a moodle file
                        $addfiledata['moodlefile'] = $file;
                    }
                    $result[] = $addfiledata;
                };
            }
            return $result;
        };

        // educations
        $educations = block_exaport_resume_get_educations(@$resumeparams->id);
        if ($educations) {
            foreach ($educations as $education) {
                $education->attachments = $import_attachments('edu', $education->id);
            }
            $resumeparams->educations = $educations;
        }
        // employments
        $employments = block_exaport_resume_get_employments(@$resumeparams->id);
        if ($employments) {
            foreach ($employments as $employment) {
                $employment->attachments = $import_attachments('employ', $employment->id);
            }
            $resumeparams->employments = $employments;
        }
        // certifications
        $certifications = block_exaport_resume_get_certificates(@$resumeparams->id);
        if ($certifications) {
            foreach ($certifications as $certification) {
                $certification->attachments = $import_attachments('certif', $certification->id);
            }
            $resumeparams->certifications = $certifications;
        }
        // publications
        $publications = block_exaport_resume_get_publications(@$resumeparams->id);
        if ($publications) {
            foreach ($publications as $publication) {
                if ($publication->url) {
                    if (strpos($publication->url, 'http://') !== 0 && strpos($publication->url, 'https://') !== 0) {
                        $publication->url = 'http://' . $publication->url;
                    }
                }
                $publication->attachments = $import_attachments('public', $publication->id);
            }
            $resumeparams->publications = $publications;
        }
        // Professional memberships
        $profmembershipments = block_exaport_resume_get_profmembershipments(@$resumeparams->id);
        if ($profmembershipments) {
            foreach ($profmembershipments as $profmembershipment) {
                $profmembershipment->attachments = $import_attachments('mbrship', $profmembershipment->id);
            }
            $resumeparams->profmembershipments = $profmembershipments;
        }
        // add files to skills and goals
        $elements = array('personal', 'academic', 'careers');
        foreach ($elements as $element) {
            $resumeparams->{'goals' . $element . '_attachments'} = $import_attachments('goals' . $element, $resumeparams->id);
            $resumeparams->{'skills' . $element . '_attachments'} = $import_attachments('skills' . $element, $resumeparams->id);
        }
        // badges
        $badges = block_exaport_resume_get_badges($resumeparams->id);
        if ($badges) {
            $badges_data = [];
            foreach ($badges as $badges_mm_rec) {
                $badge = $DB->get_record_sql('SELECT b.*, bi.dateissued, bi.uniquehash ' .
                    ' FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid=' . intval($userid) .
                    ' WHERE b.id=? ',
                    array('id' => $badges_mm_rec->badgeid));
                $badge_entry = new stdClass();
                $badge_entry->id = $badge->id;
                $badge_entry->name = $badge->name;
                $badge_entry->image = block_exaport_get_user_badge_image($badge, true);
                // Add image file as attachment
                $attachment = block_exaport_get_user_badge_image($badge, false, true);
                $badge_entry->attachments = [];
                if ($attachment instanceof stored_file) {
                    $filename = $attachment->get_filename();
                    $url = $badge_entry->image; // the same url as ->image
                    $addfiledata = array('filename' => $filename, 'fileurl' => $url);
                    if ($attachmentasarealfile) {
                        // add file data as a moodle file
                        $addfiledata['moodlefile'] = $attachment;
                    }
                    $badge_entry->attachments = [$addfiledata];
                }
                $badge_entry->description = $badge->description;
                $badge_entry->date = userdate($badge->dateissued, get_string('strftimedate', 'langconfig'));
                $badges_data[] = $badge_entry;
            }
            $resumeparams->badges = $badges_data;
        }
    }

    return $resumeparams;
}

function block_exaport_set_resume_params($userid, $params = null) {
    global $DB;

    if (is_null($params) && (is_array($userid) || is_object($userid))) {
        global $USER;
        $params = $userid;
        $userid = $USER->id;
    }

    $newresumeparams = new stdClass();

    if (is_object($params)) {
        $newresumeparams = $params;
    } else if (is_array($params)) {
        $newresumeparams = (object)$params;
    }

    if ($oldresumeparams = block_exaport_get_resume_params_record($userid)) {
        $newresumeparams->id = $oldresumeparams->id;
        $DB->update_record('block_exaportresume', $newresumeparams);
    } else {
        $newresumeparams->user_id = $userid;
        $DB->insert_record("block_exaportresume", $newresumeparams);
    }
}

function block_exaport_set_resume_mm($table, $fromform) {
    global $DB;
    if ($fromform->id < 1) {
        $fromform->sorting = block_exaport_get_max_sorting($table, $fromform->resume_id) + 10; // Step of sorting.
        $id = $DB->insert_record('block_exaportresume_' . $table, $fromform);
    } else if ($fromform->id > 0) {
        $DB->update_record('block_exaportresume_' . $table, $fromform);
        $id = $fromform->id;
    }
    return $id;
}

function block_exaport_resume_get_educations($resumeid) {
    return block_exaport_resume_get_mm_records('edu', array('resume_id' => $resumeid));
}

function block_exaport_resume_get_employments($resumeid) {
    return block_exaport_resume_get_mm_records('employ', array('resume_id' => $resumeid));
}

function block_exaport_resume_get_certificates($resumeid) {
    return block_exaport_resume_get_mm_records('certif', array('resume_id' => $resumeid));
}

function block_exaport_resume_get_badges($resumeid) {
    return block_exaport_resume_get_mm_records('badges', array('resumeid' => $resumeid));
}

function block_exaport_resume_get_publications($resumeid) {
    return block_exaport_resume_get_mm_records('public', array('resume_id' => $resumeid));
}

function block_exaport_resume_get_profmembershipments($resumeid) {
    return block_exaport_resume_get_mm_records('mbrship', array('resume_id' => $resumeid));
}

function block_exaport_resume_get_mm_records($table, $conditions) {
    global $DB;
    $wherearr = array();
    $params = array();

    foreach ($conditions as $field => $value) {
        $wherearr[] = $field . ' = ? ';
        $params[] = $value;
    }
    $where = implode(' AND ', $wherearr);
    $records = $DB->get_records_sql('SELECT * FROM {block_exaportresume_' . $table . '} WHERE ' . $where . ' ORDER BY sorting', $params);
    return $records;
}

function block_exaport_resume_templating_mm_records($courseid, $type, $headertitle, $records, $filescolumn = 1, $updowncolumn = 1,
    $editcolumn = 1) {
    global $CFG, $DB, $OUTPUT, $USER;
    if (count($records) < 1) {
        return '';
    };
    $table = new html_table();
    $table->width = "100%";
    $table->head = array();
    $table->size = array();
    $table->head['title'] = get_string('resume_' . $headertitle, 'block_exaport');
    if ($filescolumn) {
        $table->head['files'] = get_string('resume_files', 'block_exaport');
    };
    if ($updowncolumn) {
        $table->head['down'] = '';
        $table->head['up'] = '';
    };
    if ($editcolumn) {
        $table->head['icons'] = '';
    };

    if ($filescolumn) {
        $table->size['files'] = '40px';
    };
    if ($updowncolumn) {
        $table->size['down'] = '16px';
        $table->size['up'] = '16px';
    };
    if ($editcolumn) {
        $table->size['icons'] = '40px';
    };

    $table->data = array();
    $itemindex = -1;
    $idprev = 0;
    $idnext = 0;
    $keys = array_keys($records);

    foreach ($records as $key => $record) {
        $itemindex++;
        // Title/description block.
        switch ($type) {
            case 'edu':
                $position = block_exaport_html_secure($record->qualname, FORMAT_PLAIN);
                if ($position) {
                    $position .= ' (' . block_exaport_html_secure($record->qualtype, FORMAT_PLAIN) . ')';
                } else {
                    $position .= block_exaport_html_secure($record->qualtype, FORMAT_PLAIN);
                };
                if ($position) {
                    $position .= ' ' . get_string('in', 'block_exaport') . ' ';
                }
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->qualdescription) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= $position . block_exaport_html_secure($record->institution, FORMAT_PLAIN) . '</strong>';
                if ($record->qualdescription) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>' . block_exaport_html_secure($record->startdate, FORMAT_PLAIN) .
                    (isset($record->enddate) && $record->enddate <> '' ? ' - ' . block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '') . '</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($record->qualdescription) . '</div>';
                break;
            case 'employ':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->positiondescription) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->jobtitle, FORMAT_PLAIN) . ': ' . block_exaport_html_secure($record->employer, FORMAT_PLAIN) . '</strong>';
                if ($record->positiondescription) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>' . block_exaport_html_secure($record->startdate, FORMAT_PLAIN) .
                    (isset($record->enddate) && $record->enddate <> '' ? ' - ' . block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '') . '</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($record->positiondescription) . '</div>';
                break;
            case 'certif':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN) . '</strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>' . block_exaport_html_secure($record->date, FORMAT_PLAIN) . '</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($record->description) . '</div>';
                break;
            case 'public':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->contributiondetails) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN) . ' (' . block_exaport_html_secure($record->contribution, FORMAT_PLAIN) . ')</strong>';
                if ($record->contributiondetails) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>' . block_exaport_html_secure($record->date, FORMAT_PLAIN) . '</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($record->contributiondetails);
                if ($record->url) {
                    $table->data[$itemindex]['title'] .= '<br><a href="' . s($record->url) . '">' . s($record->url) . '</a>';
                };
                $table->data[$itemindex]['title'] .= '</div>';
                break;
            case 'mbrship':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN) . '</strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>' . block_exaport_html_secure($record->startdate, FORMAT_PLAIN) .
                    (isset($record->enddate) && $record->enddate <> '' ? ' - ' . block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '') . '</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($record->description) . '</div>';
                break;
            case 'badges':
                $badge = $DB->get_record_sql('SELECT b.*, bi.dateissued, bi.uniquehash ' .
                    ' FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid=' . $USER->id .
                    ' WHERE b.id=? ',
                    array('id' => $record->badgeid));
                $table->data[$itemindex]['title'] = '<strong>';
                if ($badge->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($badge->name, FORMAT_PLAIN) . '</strong>';
                if ($badge->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $dateformat = get_string('strftimedate', 'langconfig');
                $badgeimage = block_exaport_get_user_badge_image($badge);
                $table->data[$itemindex]['title'] .= '<div>' . userdate($badge->dateissued, $dateformat) . '</div>' .
                    '<div class="expandable-text hidden">' . block_exaport_html_secure($badge->description) . $badgeimage . '</div>';
                break;
            default:
                break;
        }
        // Count of files.
        if ($filescolumn) {
            $fs = get_file_storage();
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_' . $type, $record->id, 'filename', false);
            $countfiles = count($files);
            if ($countfiles > 0) {
                $table->data[$itemindex]['files'] = '<a href="#" class="expandable-head">' . $countfiles . '</a>' .
                    '<div class="expandable-text hidden">' . block_exaport_resume_list_files($type, $files) . '</div>';
            } else {
                $table->data[$itemindex]['files'] = '0';
            };
        };
        // Links to up/down.
        if ($updowncolumn) {
            if ($itemindex < count($records) - 1) {
                $idnext = $keys[$itemindex + 1];
            };
            $linktoup = '<a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '&action=sortchange&type=' . $type .
                '&id1=' . $record->id . '&id2=' . $idnext . '&sesskey=' . sesskey() . '">'
                . block_exaport_fontawesome_icon('chevron-down', 'solid', 1)
                //                    .'<img src="pix/down_16.png" alt="'.get_string("down").'" />'
                . '</a>';
            $linktodown = '<a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid .
                '&action=sortchange&type=' . $type . '&id1=' . $record->id . '&id2=' . $idprev . '&sesskey=' . sesskey() . '">'
                . block_exaport_fontawesome_icon('chevron-up', 'solid', 1)
                //                    .'<img src="pix/up_16.png" alt="'.get_string("up").'" />'
                . '</a>';
            $table->data[$itemindex]['up'] = '&nbsp';
            $table->data[$itemindex]['down'] = '&nbsp';
            if ($itemindex < count($records) - 1) {
                $table->data[$itemindex]['up'] = $linktoup;
            };
            if ($itemindex > 0) {
                $table->data[$itemindex]['down'] = $linktodown;
            };
            $idprev = $record->id;
        };
        // Links to edit / delete.
        if ($editcolumn) {
            $table->data[$itemindex]['icons'] = ' <a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid .
                '&action=edit&type=' . $type . '&id=' . $record->id . '&sesskey=' . sesskey() . '">' .
                block_exaport_fontawesome_icon('pen-to-square', 'regular', 1) .
                //                    '<img src="pix/edit.png" alt="'.get_string("edit").'" />'.
                '</a>' .
                ' <a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '&action=delete&type=' . $type . '&id=' .
                $record->id . '">' .
                block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon', 'mt-2']) .
                //                    '<img src="pix/del.png" alt="'.get_string("delete").'"/>'.
                '</a>';
        };
    };
    return html_writer::table($table);
}

// Goals and skills.
function block_exaport_resume_templating_list_goals_skills($courseid, $resume, $type, $tabletitle) {
    global $CFG, $DB, $OUTPUT, $USER;
    $elements = array('personal', 'academic', 'careers');
    $table = new html_table();
    $table->width = "100%";
    $table->head = array();
    $table->size = array();
    $table->head['title'] = get_string('resume_' . $type, 'block_exaport');
    $table->head['files'] = get_string('resume_files', 'block_exaport');
    $table->head['icons'] = '';
    $table->size['files'] = '40px';
    $table->size['icons'] = '40px';

    $itemindex = 0;
    // Competencies.
    if (block_exaport_check_competence_interaction()) {
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(BLOCK_EXACOMP_DB_DESCRIPTORS)) {
            $table->data[$itemindex]['title'] = get_string('resume_' . $type . 'comp', 'block_exaport') .
                ' / <span style="color:red;">Error: Please install latest version of Exabis Competence Grid</span>';
            $table->data[$itemindex]['files'] = '';
            $table->data[$itemindex]['icons'] = '';
        } else {
            $comptitles = '';
            $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => $type));
            foreach ($competences as $competence) {
                $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*',
                    $strictness = IGNORE_MISSING);
                if ($competencesdb != null) {
                    $comptitles .= $competencesdb->title . '<br>';
                };
            };
            if ($comptitles <> '') {
                $table->data[$itemindex]['title'] = '<a name="' . $type . 'comp"></a><a href="#" class="expandable-head">' .
                    get_string('resume_' . $type . 'comp', 'block_exaport') . '</a>';
            } else {
                $table->data[$itemindex]['title'] = '<a name="' . $type . 'comp"></a>' .
                    get_string('resume_' . $type . 'comp', 'block_exaport');
            }
            $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . $comptitles . '</div>';
            $table->data[$itemindex]['files'] = '';
            // Links to edit / delete.
            if (file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php')) {
                $table->data[$itemindex]['icons'] = ' <a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' .
                    $courseid . '&action=edit&type=' . $type . 'comp&id=' . $resume->id . '&sesskey=' . sesskey() . '">' .
                    block_exaport_fontawesome_icon('pen-to-square', 'regular', 1) .
                    //                        '<img src="pix/edit.png" alt="'.get_string("edit").'" />'.
                    '</a>';
            } else {
                $table->data[$itemindex]['icons'] = '';
            }
        };

    };

    foreach ($elements as $element) {
        $itemindex++;
        // Title and description.
        $description = '';
        $description = $resume->{$type . $element};
        $description = file_rewrite_pluginfile_urls($description, 'pluginfile.php',
            context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_' . $type . $element, $resume->id);
        $description = block_exaport_add_view_access_parameter_to_url($description, 'resume/' . $resume->id . '/' . $USER->id, ['src']);
        $description = trim($description);
        if (preg_replace('/\<br(\s*)?\/?\>/i', "", $description) == '') {
            // If text is only <br> (html-editor can return this).
            $description = '';
        }
        $table->data[$itemindex]['title'] = '';
        $fs = get_file_storage();
        $context = context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_' . $type . $element, $resume->id, 'filename', false);
        // Count of files.
        $countfiles = count($files);
        if ($countfiles > 0) {
            $table->data[$itemindex]['files'] = '<a href="#" class="expandable-head">' . $countfiles . '</a>' .
                '<div class="expandable-text hidden">' . block_exaport_resume_list_files($type . $element, $files) . '</div>';
        } else {
            $table->data[$itemindex]['files'] = '0';
        };
        if ($description <> '') {
            $table->data[$itemindex]['title'] = '<a name="' . $type . $element . '"></a><a href="#" class="expandable-head">' .
                get_string('resume_' . $type . $element, 'block_exaport') . '</a>';
            $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">' . block_exaport_html_secure($description) . '</div>';
        } else {
            $table->data[$itemindex]['title'] = '<a name="' . $type . $element . '"></a>' .
                get_string('resume_' . $type . $element, 'block_exaport');
        };
        // Links to edit / delete.
        $table->data[$itemindex]['icons'] = ' <a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid .
            '&action=edit&type=' . $type . $element . '&id=' . $resume->id . '&sesskey=' . sesskey() . '">' .
            block_exaport_fontawesome_icon('pen-to-square', 'regular', 1) .
            //                '<img src="pix/edit.png" alt="'.get_string("edit").'" />'.
            '</a>';
    };

    $tablecontent = html_writer::table($table);
    return $tablecontent;
}

function block_exaport_resume_list_files($filearea, $files) {
    global $CFG;
    $listfiles = '<ul class="resume_listfiles">';
    foreach ($files as $file) {
        $filename = $file->get_filename();
        $url = $CFG->wwwroot . '/pluginfile.php/' . $file->get_contextid() . '/block_exaport/resume_' . $filearea . '/' . $file->get_itemid() .
            '/' . $filename;
        $listfiles .= '<li>' . html_writer::link($url, $filename) . '</li>';
    };
    $listfiles .= '<ul>';

    return $listfiles;
}

function block_exaport_resume_mm_delete($table, $conditions) {
    global $DB, $USER;
    $DB->delete_records('block_exaportresume_' . $table, $conditions);
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_' . $table, $conditions['id']);
    foreach ($files as $file) {
        $file->delete();
    };
}

function block_exaport_get_max_sorting($table, $resumeid) {
    global $DB;
    return $DB->get_field_sql('SELECT MAX(sorting) FROM {block_exaportresume_' . $table . '} WHERE resume_id=?', array($resumeid));
}

function block_exaport_resume_competences_form($resume, $id, $typeblock) {
    global $DB;

    $type = substr($typeblock, 0, -4); // Skillscomp -> skills / goalscomp -> goals.
    $save = optional_param('submitbutton', '', PARAM_RAW);
    $cancel = optional_param('cancel', '', PARAM_RAW);
    $resume->descriptors = array();
    if ($cancel) {
        return true;
    }

    if ($save) {
        $interaction = block_exaport_check_competence_interaction();
        if ($interaction) {
            $DB->delete_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => $type));
            $compids = optional_param_array('desc', array(), PARAM_INT);
            if (count($compids) > 0) {
                foreach ($compids as $compid) {
                    $DB->insert_record('block_exaportcompresume_mm',
                        array("resumeid" => $resume->id, "compid" => $compid, "comptype" => $type));
                }
            }
        }
        return true;
    }
    $content = block_exaport_resume_header();
    $resume->descriptors = array_keys($DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id,
        "comptype" => $type), null, 'compid'));
    $content .= '<div class="block_eportfolio_center">' . get_string('edit', "block_exaport") . ': ' .
        get_string('resume_' . $typeblock, "block_exaport") . '</div>';
    $content .= block_exaport_build_comp_tree($typeblock, $resume);
    echo $content;
    return false;
}

function block_exaport_get_user_badge_image($badge, $just_url = false, $return_file = false) {
    // $src = '/pluginfile.php/'.context_user::instance($badge->usercreated)->id.'/badges/userbadge/'.$badge->id.'/'.
    // $badge->uniquehash;
    // Find badge by id.
    if (!$badge) {
        return '';
    }
    $context_id = 1;
    if ($badge->courseid) {
        $context = context_course::instance($badge->courseid);
        $context_id = $context->id;
    }
    if ($return_file) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context_id, 'badges', 'badgeimage', $badge->id, false);
        $file = null;
        foreach ($files as $filet) {
            if ($filet->get_filename() !== '.') {
                // only f1 ???
                if (strpos($filet->get_filename(), 'f1') === 0) {
                    $file = $filet;
                    break;
                }
            }
        }
        return $file;
    } else {
        $src = (string)moodle_url::make_pluginfile_url($context_id, 'badges', 'badgeimage', $badge->id, '/', 'f1');
    }
    if ($just_url) {
        return $src;
    }
    $img = '<img src="' . $src . '" style="float: left; margin: 0px 10px;">';
    return $img;
}

function europass_xml($resumeid = 0) {
    global $USER, $DB, $CFG;
    $resume = $DB->get_record('block_exaportresume', ['id' => $resumeid, 'user_id' => $USER->id]);

    $schemeID = 'Test-0001';
    $schemeAgencyName = 'EUROPASS';

    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;

    // Helper: create an element with optional text content (text is XML-escaped via createTextNode).
    $textElement = function($name, $value) use ($dom) {
        $node = $dom->createElement($name);
        if ($value !== null && $value !== '') {
            $node->appendChild($dom->createTextNode((string)$value));
        }
        return $node;
    };

    // Root.
    $root = $dom->createElement('Candidate');
    $root->setAttribute('xsi:schemaLocation', 'http://www.europass.eu/1.0 Candidate.xsd');
    $root->setAttribute('xmlns', 'http://www.europass.eu/1.0');
    $root->setAttribute('xmlns:oa', 'http://www.openapplications.org/oagis/9');
    $root->setAttribute('xmlns:eures', 'http://www.europass_eures.eu/1.0');
    $root->setAttribute('xmlns:hr', 'http://www.hr-xml.org/3');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

    // hr:DocumentID.
    $documentID = $dom->createElement('hr:DocumentID');
    $documentID->setAttribute('schemeID', $schemeID);
    $documentID->setAttribute('schemeName', 'DocumentIdentifier');
    $documentID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $documentID->setAttribute('schemeVersionID', '4.0');
    $root->appendChild($documentID);

    $userFirstName = clean_param($USER->firstname, PARAM_ALPHAEXT);
    $userLastName = clean_param($USER->lastname, PARAM_ALPHAEXT);
    $userCountry = !empty($USER->country) ? strtolower($USER->country) : '';

    // CandidateSupplier.
    $candidateSupplier = $dom->createElement('CandidateSupplier');
    $partyID = $dom->createElement('hr:PartyID');
    $partyID->setAttribute('schemeID', $schemeID);
    $partyID->setAttribute('schemeName', 'PartyID');
    $partyID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $partyID->setAttribute('schemeVersionID', '1.0');
    $candidateSupplier->appendChild($partyID);
    $candidateSupplier->appendChild($textElement('hr:PartyName', 'Owner'));

    $personContact = $dom->createElement('PersonContact');
    $supplierName = $dom->createElement('PersonName');
    $supplierName->appendChild($textElement('oa:GivenName', $userFirstName));
    $supplierName->appendChild($textElement('hr:FamilyName', $userLastName));
    $personContact->appendChild($supplierName);
    if (!empty($USER->email)) {
        $com = $dom->createElement('Communication');
        $com->appendChild($textElement('ChannelCode', 'Email'));
        $com->appendChild($textElement('oa:URI', $USER->email));
        $personContact->appendChild($com);
    }
    $candidateSupplier->appendChild($personContact);
    $candidateSupplier->appendChild($textElement('hr:PrecedenceCode', '1'));
    $root->appendChild($candidateSupplier);

    // CandidatePerson.
    $candidatePerson = $dom->createElement('CandidatePerson');

    $personName = $dom->createElement('PersonName');
    $personName->appendChild($textElement('oa:GivenName', $userFirstName));
    $personName->appendChild($textElement('hr:FamilyName', $userLastName));
    $candidatePerson->appendChild($personName);

    if (!empty($USER->email)) {
        $com = $dom->createElement('Communication');
        $com->appendChild($textElement('ChannelCode', 'Email'));
        $com->appendChild($textElement('oa:URI', $USER->email));
        $candidatePerson->appendChild($com);
    }

    // Phone numbers (1, 2) — extract optional dialing code from a leading "+nn" / "00nn".
    $phoneTypes = ['work', 'mobile'];
    $phoneIndex = 0;
    foreach ([1, 2] as $n) {
        $phone = $USER->{'phone' . $n} ?? '';
        if (!$phone) {
            continue;
        }
        preg_match('!^(?:(?:\(?(?:00|\+)([1-4]\d\d|[1-9]\d?)\)?)?[\-\.\ \\\/]?)?((?:\(?\d{1,}\)?[\-\.\ \\\/]?){0,})!i', $phone, $matches);
        $countryDialing = $matches[1] ?? '';
        $phoneNumber = trim($matches[2] ?? '');
        if (!$phoneNumber) {
            continue;
        }
        $com = $dom->createElement('Communication');
        $com->appendChild($textElement('ChannelCode', 'Telephone'));
        $com->appendChild($textElement('UseCode', $phoneTypes[$phoneIndex] ?? 'work'));
        if ($countryDialing) {
            $com->appendChild($textElement('CountryDialing', $countryDialing));
        }
        $com->appendChild($textElement('oa:DialNumber', $phoneNumber));
        if ($userCountry) {
            $com->appendChild($textElement('CountryCode', $userCountry));
        }
        $candidatePerson->appendChild($com);
        $phoneIndex++;
    }

    // Home address.
    $userAddress = trim((string)($USER->address ?? ''));
    $userCity = trim((string)($USER->city ?? ''));
    if ($userAddress || $userCity || $userCountry) {
        $com = $dom->createElement('Communication');
        $com->appendChild($textElement('UseCode', 'home'));
        $address = $dom->createElement('Address');
        $address->setAttribute('type', 'home');
        if ($userAddress) {
            $address->appendChild($textElement('oa:AddressLine', $userAddress));
        }
        if ($userCity) {
            $address->appendChild($textElement('oa:CityName', $userCity));
        }
        if ($userCountry) {
            $address->appendChild($textElement('CountryCode', $userCountry));
        }
        $com->appendChild($address);
        $candidatePerson->appendChild($com);
    }

    if ($userCountry) {
        $candidatePerson->appendChild($textElement('NationalityCode', $userCountry));
    }
    $root->appendChild($candidatePerson);

    // CandidateProfile.
    $candidateProfile = $dom->createElement('CandidateProfile');
    // Populated profile sections that should appear in <RenderingInformation>/<SectionsOrder>.
    $sectionTitles = [];
    // languageCode: prefer the user's Moodle language, fall back to the UI language; trim any region suffix (e.g. de_at → de).
    $lang = !empty($USER->lang) ? $USER->lang : current_language();
    $candidateProfile->setAttribute('languageCode', strtolower(strtok($lang, '_')));

    $profileID = $dom->createElement('hr:ID');
    $profileID->setAttribute('schemeID', $schemeID);
    $profileID->setAttribute('schemeName', 'CandidateProfileID');
    $profileID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $profileID->setAttribute('schemeVersionID', '1.0');
    $candidateProfile->appendChild($profileID);

    if (!empty($resume->cover)) {
        $candidateProfile->appendChild($textElement('hr:ExecutiveSummary', clean_for_external_xml($resume->cover)));
    } else {
        $candidateProfile->appendChild($dom->createElement('hr:ExecutiveSummary'));
    }

    // EmploymentHistory.
    $employments = $DB->get_records('block_exaportresume_employ', ['resume_id' => $resume->id], 'sorting');
    $employmentHistory = $dom->createElement('EmploymentHistory');
    $currentYear = (int)date('Y');
    foreach ($employments as $employment) {
        $employerHistory = $dom->createElement('EmployerHistory');
        if (!empty($employment->employer)) {
            $employerHistory->appendChild($textElement('hr:OrganizationName', clean_for_external_xml($employment->employer)));
        }
        if (!empty($employment->employeraddress)) {
            $orgContact = $dom->createElement('OrganizationContact');
            $orgCom = $dom->createElement('Communication');
            $orgAddress = $dom->createElement('Address');
            $orgAddress->appendChild($textElement('oa:CityName', clean_for_external_xml($employment->employeraddress)));
            if ($userCountry) {
                $orgAddress->appendChild($textElement('CountryCode', $userCountry));
            }
            $orgCom->appendChild($orgAddress);
            $orgContact->appendChild($orgCom);
            $employerHistory->appendChild($orgContact);
        }

        $positionHistory = $dom->createElement('PositionHistory');
        if (!empty($employment->jobtitle)) {
            $title = $textElement('PositionTitle', clean_for_external_xml($employment->jobtitle));
            $title->setAttribute('typeCode', 'FREETEXT');
            $positionHistory->appendChild($title);
        }

        $period = $dom->createElement('eures:EmploymentPeriod');
        $isOngoing = false;
        if (!empty($employment->startdate)) {
            $date = get_europass_date($employment->startdate);
            if ($date) {
                $sd = $dom->createElement('eures:StartDate');
                $sd->appendChild($textElement('hr:FormattedDateTime', $date));
                $period->appendChild($sd);
            }
        }
        if (!empty($employment->enddate)) {
            $endYear = (int)get_europass_date($employment->enddate, 'Y');
            $isOngoing = $endYear && $currentYear < $endYear;
            $date = get_europass_date($employment->enddate);
            if ($date && !$isOngoing) {
                $ed = $dom->createElement('eures:EndDate');
                $ed->appendChild($textElement('hr:FormattedDateTime', $date));
                $period->appendChild($ed);
            }
        } else {
            $isOngoing = true;
        }
        $period->appendChild($textElement('hr:CurrentIndicator', $isOngoing ? 'true' : 'false'));
        $positionHistory->appendChild($period);

        if (!empty($employment->positiondescription)) {
            $positionHistory->appendChild($textElement('oa:Description', clean_for_external_xml($employment->positiondescription)));
        }
        if (!empty($employment->employeraddress)) {
            $positionHistory->appendChild($textElement('City', clean_for_external_xml($employment->employeraddress)));
        }
        if ($userCountry) {
            $positionHistory->appendChild($textElement('Country', $userCountry));
        }

        $employerHistory->appendChild($positionHistory);
        $employmentHistory->appendChild($employerHistory);
    }
    $candidateProfile->appendChild($employmentHistory);
    if ($employments) {
        $sectionTitles[] = ['title' => 'work-experience', 'custom' => false];
    }

    // EducationHistory.
    $educations = $DB->get_records('block_exaportresume_edu', ['resume_id' => $resume->id], 'sorting');
    $educationHistory = $dom->createElement('EducationHistory');
    foreach ($educations as $education) {
        $eoa = $dom->createElement('EducationOrganizationAttendance');
        if (!empty($education->institution)) {
            $eoa->appendChild($textElement('hr:OrganizationName', clean_for_external_xml($education->institution)));
        }
        if (!empty($education->institutionaddress) || $userCountry) {
            $orgContact = $dom->createElement('OrganizationContact');
            $orgCom = $dom->createElement('Communication');
            $orgAddress = $dom->createElement('Address');
            if (!empty($education->institutionaddress)) {
                $orgAddress->appendChild($textElement('oa:CityName', clean_for_external_xml($education->institutionaddress)));
            }
            if ($userCountry) {
                $orgAddress->appendChild($textElement('CountryCode', $userCountry));
            }
            $orgCom->appendChild($orgAddress);
            $orgContact->appendChild($orgCom);
            $eoa->appendChild($orgContact);
        }

        $attendancePeriod = $dom->createElement('AttendancePeriod');
        $isOngoing = false;
        if (!empty($education->startdate)) {
            $date = get_europass_date($education->startdate);
            if ($date) {
                $sd = $dom->createElement('StartDate');
                $sd->appendChild($textElement('hr:FormattedDateTime', $date));
                $attendancePeriod->appendChild($sd);
            }
        }
        if (!empty($education->enddate)) {
            $endYear = (int)get_europass_date($education->enddate, 'Y');
            $isOngoing = $endYear && $currentYear < $endYear;
            $date = get_europass_date($education->enddate);
            if ($date && !$isOngoing) {
                $ed = $dom->createElement('EndDate');
                $ed->appendChild($textElement('hr:FormattedDateTime', $date));
                $attendancePeriod->appendChild($ed);
            }
        } else {
            $isOngoing = true;
        }
        $attendancePeriod->appendChild($textElement('Ongoing', $isOngoing ? 'true' : 'false'));
        $eoa->appendChild($attendancePeriod);

        // EducationDegree — DegreeName is required by the editor.
        $degreeName = !empty($education->qualname)
            ? $education->qualname
            : (!empty($education->qualtype) ? $education->qualtype : block_exaport_get_string('resume_qualification'));
        $degree = $dom->createElement('EducationDegree');
        $degree->appendChild($textElement('hr:DegreeName', clean_for_external_xml($degreeName)));
        $degree->appendChild($dom->createElement('NationalClassification'));
        $degree->appendChild($dom->createElement('CreditType'));
        $degree->appendChild($dom->createElement('NumberOfCredit'));
        $eoa->appendChild($degree);

        $educationHistory->appendChild($eoa);
    }
    $candidateProfile->appendChild($educationHistory);
    if ($educations) {
        $sectionTitles[] = ['title' => 'education-training', 'custom' => false];
    }

    // eures:Licenses (placeholder).
    $candidateProfile->appendChild($dom->createElement('eures:Licenses'));

    // <Certifications> (formal certifications like PMP, AWS, …) — placeholder; we don't capture this kind in Moodle.
    $candidateProfile->appendChild($dom->createElement('Certifications'));

    // PublicationHistory.
    $publications = $DB->get_records('block_exaportresume_public', ['resume_id' => $resume->id], 'sorting');
    $pubHistory = $dom->createElement('PublicationHistory');
    foreach ($publications as $publication) {
        $pub = $dom->createElement('Publication');
        $pub->appendChild($dom->createElement('hr:FormattedPublicationDescription'));
        if (!empty($publication->title)) {
            $pub->appendChild($textElement('Title', clean_for_external_xml($publication->title)));
        }
        if (!empty($publication->date)) {
            $year = get_europass_date($publication->date, 'Y');
            if ($year) {
                $pub->appendChild($textElement('Year', $year));
            }
        }
        if (!empty($publication->contribution)) {
            $pub->appendChild($textElement('Reference', clean_for_external_xml($publication->contribution)));
        }
        if (!empty($publication->url)) {
            $pub->appendChild($textElement('Link', $publication->url));
        }
        if (!empty($publication->contributiondetails)) {
            $pub->appendChild($textElement('Authors', clean_for_external_xml($publication->contributiondetails)));
        }
        $pubHistory->appendChild($pub);
    }
    $candidateProfile->appendChild($pubHistory);
    if ($publications) {
        $sectionTitles[] = ['title' => 'publication', 'custom' => false];
    }

    // Empty placeholder sections (matching the editor's reference output).
    $candidateProfile->appendChild($dom->createElement('PersonQualifications'));
    $candidateProfile->appendChild($dom->createElement('EmploymentReferences'));
    $candidateProfile->appendChild($dom->createElement('CreativeWorks'));
    $candidateProfile->appendChild($dom->createElement('Projects'));
    $candidateProfile->appendChild($dom->createElement('SocialAndPoliticalActivities'));

    // Skills (placeholder; user's free-text skills/goals are added below as <Others>).
    $candidateProfile->appendChild($dom->createElement('Skills'));

    // NetworksAndMemberships.
    // NetworksAndMemberships → SocialAndNetworkingActivityType: <Activity>{Title, Date, Description} + <Location> as sibling.
    $networks = $dom->createElement('NetworksAndMemberships');
    $mbrships = $DB->get_records('block_exaportresume_mbrship', ['resume_id' => $resume->id], 'sorting');
    foreach ($mbrships as $mbrship) {
        $m = $dom->createElement('NetworkAndMembership');
        $activity = $dom->createElement('Activity');
        if (!empty($mbrship->title)) {
            $activity->appendChild($textElement('Title', clean_for_external_xml($mbrship->title)));
        }
        $hasStart = !empty($mbrship->startdate) && get_europass_date($mbrship->startdate);
        $hasEnd = !empty($mbrship->enddate) && get_europass_date($mbrship->enddate);
        if ($hasStart || $hasEnd || empty($mbrship->enddate)) {
            $dateNode = $dom->createElement('Date');
            $isOngoing = false;
            if ($hasStart) {
                $sd = $dom->createElement('StartDate');
                $sd->appendChild($textElement('hr:FormattedDateTime', get_europass_date($mbrship->startdate)));
                $dateNode->appendChild($sd);
            }
            if (!empty($mbrship->enddate)) {
                $endYear = (int) get_europass_date($mbrship->enddate, 'Y');
                $isOngoing = $endYear && $currentYear < $endYear;
                if ($hasEnd && !$isOngoing) {
                    $ed = $dom->createElement('EndDate');
                    $ed->appendChild($textElement('hr:FormattedDateTime', get_europass_date($mbrship->enddate)));
                    $dateNode->appendChild($ed);
                }
            } else {
                $isOngoing = true;
            }
            $dateNode->appendChild($textElement('Ongoing', $isOngoing ? 'true' : 'false'));
            $activity->appendChild($dateNode);
        }
        if (!empty($mbrship->description)) {
            $activity->appendChild($textElement('Description', clean_for_external_xml($mbrship->description)));
        }
        $m->appendChild($activity);
        $networks->appendChild($m);
    }
    $candidateProfile->appendChild($networks);
    if ($mbrships) {
        $sectionTitles[] = ['title' => 'membership', 'custom' => false];
    }

    $candidateProfile->appendChild($dom->createElement('ConferencesAndSeminars'));
    $candidateProfile->appendChild($dom->createElement('VoluntaryWorks'));

    // CourseCertifications (Moodle resume "Certifications" + Badges).
    $courseCerts = $dom->createElement('CourseCertifications');
    $certifications = $DB->get_records('block_exaportresume_certif', ['resume_id' => $resume->id], 'sorting');
    foreach ($certifications as $certification) {
        $cc = $dom->createElement('CourseCertification');
        if (!empty($certification->title)) {
            $cc->appendChild($textElement('Title', clean_for_external_xml($certification->title)));
        }
        if (!empty($certification->date)) {
            $date = get_europass_date($certification->date);
            if ($date) {
                $fid = $dom->createElement('eures:FirstIssuedDate');
                $fid->appendChild($textElement('hr:FormattedDateTime', $date));
                $cc->appendChild($fid);
            }
        }
        if (!empty($certification->description)) {
            $cc->appendChild($textElement('oa:Description', clean_for_external_xml($certification->description)));
        }
        $courseCerts->appendChild($cc);
    }
    $badges = block_exaport_resume_get_badges($resume->id);
    if ($badges && is_array($badges)) {
        foreach ($badges as $badge) {
            $rsbadge = $DB->get_record_sql(
                'SELECT b.*, bi.dateissued, bi.uniquehash ' .
                ' FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid=? ' .
                ' WHERE b.id=? ',
                [$USER->id, $badge->badgeid]
            );
            if (!$rsbadge) {
                continue;
            }
            $cc = $dom->createElement('CourseCertification');
            $cc->appendChild($textElement('Title', clean_for_external_xml($rsbadge->name)));
            if (!empty($rsbadge->dateissued)) {
                $fid = $dom->createElement('eures:FirstIssuedDate');
                $fid->appendChild($textElement('hr:FormattedDateTime', date('Y-m-d', $rsbadge->dateissued)));
                $cc->appendChild($fid);
            }
            if (!empty($rsbadge->description)) {
                $cc->appendChild($textElement('oa:Description', clean_for_external_xml($rsbadge->description)));
            }
            $courseCerts->appendChild($cc);
        }
    }
    $candidateProfile->appendChild($courseCerts);
    if ($certifications || ($badges && is_array($badges) && count($badges))) {
        $sectionTitles[] = ['title' => 'certifications', 'custom' => false];
    }

    // Free-text skills, goals, interests and competence linkages → <Others> (schema's catch-all).
    // <Others> wraps an OtherType (restricted ActivityType): Title (required) + Description (no oa: prefix per schema 3.38.6).
    // Each item produces its own <Others> wrapper (schema: <Other> cardinality is 0..1 inside <Others>).
    // The editor groups all <Others> with the same <Title> into one custom UI section.
    $appendOthers = function($sectionTitle, $items) use ($dom, $candidateProfile, $textElement, &$sectionTitles) {
        $items = array_filter($items, fn($it) => !empty($it[1]));
        if (!$items) {
            return;
        }
        foreach ($items as [$itemTitle, $itemContent]) {
            $others = $dom->createElement('Others');
            $others->appendChild($textElement('Title', $sectionTitle));
            $other = $dom->createElement('Other');
            $other->appendChild($textElement('Title', $itemTitle ?: $sectionTitle));
            $other->appendChild($textElement('Description', clean_for_external_xml($itemContent)));
            $others->appendChild($other);
            $candidateProfile->appendChild($others);
        }
        $sectionTitles[] = ['title' => $sectionTitle, 'custom' => true];
    };

    $skillCompetences = '';
    foreach ($DB->get_records('block_exaportcompresume_mm', ['resumeid' => $resume->id, 'comptype' => 'skills']) as $competence) {
        $cdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['id' => $competence->compid], '*', IGNORE_MISSING);
        if ($cdb) {
            $skillCompetences .= '<p>' . $cdb->title . '</p>';
        }
    }
    $appendOthers(block_exaport_get_string('resume_skills'), [
        [block_exaport_get_string('resume_skillscomp'), $skillCompetences],
        [block_exaport_get_string('resume_skillspersonal'), $resume->skillspersonal ?? ''],
        [block_exaport_get_string('resume_skillscareers'), $resume->skillscareers ?? ''],
        [block_exaport_get_string('resume_skillsacademic'), $resume->skillsacademic ?? ''],
    ]);

    $goalCompetences = '';
    foreach ($DB->get_records('block_exaportcompresume_mm', ['resumeid' => $resume->id, 'comptype' => 'goals']) as $competence) {
        $cdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['id' => $competence->compid], '*', IGNORE_MISSING);
        if ($cdb) {
            $goalCompetences .= '<p>' . $cdb->title . '</p>';
        }
    }
    $appendOthers(block_exaport_get_string('resume_goals'), [
        [block_exaport_get_string('resume_goalscomp'), $goalCompetences],
        [block_exaport_get_string('resume_goalspersonal'), $resume->goalspersonal ?? ''],
        [block_exaport_get_string('resume_goalscareers'), $resume->goalscareers ?? ''],
        [block_exaport_get_string('resume_goalsacademic'), $resume->goalsacademic ?? ''],
    ]);

    // Interests → dedicated <HobbiesAndInterests> with HobbyOrInterest items (ActivityType: Title required, Description optional).
    if (!empty($resume->interests)) {
        $hobbies = $dom->createElement('HobbiesAndInterests');
        $hobby = $dom->createElement('HobbyOrInterest');
        $hobby->appendChild($textElement('Title', block_exaport_get_string('resume_interests')));
        $hobby->appendChild($textElement('Description', clean_for_external_xml($resume->interests)));
        $hobbies->appendChild($hobby);
        $candidateProfile->appendChild($hobbies);
        $sectionTitles[] = ['title' => 'hobbies-interests', 'custom' => false];
    }

    $root->appendChild($candidateProfile);

    // RenderingInformation: SectionsOrder lists populated sections in a fixed display order
    // (education before work experience, then the rest), regardless of XML element order.
    $rendering = $dom->createElement('RenderingInformation');
    $design = $dom->createElement('Design');
    $design->appendChild($textElement('Template', 'Template2'));
    $design->appendChild($textElement('Color', 'Default'));
    $design->appendChild($textElement('FontSize', 'Medium'));
    $design->appendChild($textElement('Logo', 'FirstPage'));
    $design->appendChild($textElement('PageNumbers', 'false'));
    $sections = $dom->createElement('SectionsOrder');
    $sectionDisplayOrder = [
        'education-training',
        'work-experience',
        'certifications',
        'publication',
        'membership',
        'hobbies-interests',
    ];
    // Dedup by title (the same custom title may have been pushed multiple times via several appendOthers calls).
    $seen = [];
    $unique = [];
    foreach ($sectionTitles as $s) {
        if (isset($seen[$s['title']])) {
            continue;
        }
        $seen[$s['title']] = true;
        $unique[] = $s;
    }
    // Standard sections in fixed order, custom sections after them in insertion order.
    usort($unique, function ($a, $b) use ($sectionDisplayOrder) {
        if ($a['custom'] !== $b['custom']) {
            return $a['custom'] <=> $b['custom'];
        }
        if ($a['custom']) {
            return 0;
        }
        $ai = array_search($a['title'], $sectionDisplayOrder, true);
        $bi = array_search($b['title'], $sectionDisplayOrder, true);
        return ($ai === false ? PHP_INT_MAX : $ai) <=> ($bi === false ? PHP_INT_MAX : $bi);
    });
    foreach ($unique as $s) {
        $section = $dom->createElement('Section');
        $section->appendChild($textElement('Title', $s['title']));
        if ($s['custom']) {
            $section->appendChild($textElement('Custom', 'true'));
        }
        $sections->appendChild($section);
    }
    $design->appendChild($sections);
    $rendering->appendChild($design);
    $root->appendChild($rendering);

    $dom->appendChild($root);
    return $dom->saveXML();
}

function get_europass_date($string_date, $format = 'Y-m-d') {
    if ($string_date === null || trim((string)$string_date) === '') {
        return '';
    }
    $string_date = trim((string)$string_date);

    // Already-clean ISO formats (YYYY, YYYY-MM, YYYY-MM-DD) → schema-valid as-is.
    // Catch this BEFORE DateTime, which otherwise parses bare "2018" as time 20:18 and returns today's date.
    if (preg_match('!^(\d{4})(-\d{2}(-\d{2})?)?$!', $string_date, $m)) {
        return $format === 'Y' ? $m[1] : $string_date;
    }

    // Free-form input ("March 2018", "Sept. 2020", …).
    try {
        return (new \DateTime($string_date))->format($format);
    } catch (\Exception $e) {
        // Fallback: extract a 4-digit year if there is one.
        if (preg_match('!(19|20|21)\d{2}!', $string_date, $m)) {
            return $m[0];
        }
        return '';
    }
}

// Clean text for XML. Images, links, e.t.c.
function clean_for_external_xml($text = '') {
    $result = $text;
    // Img.
    $result = preg_replace("/<img[^>]+\>/i", "", $result);
    // html
    $result = block_exaport_html_secure($result);
    return $result;
}
