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

require_once($CFG->libdir . '/formslib.php');

global $attachedfilenames, $attachedfiledatas, $attachedfilemimetypes;
$attachedfilenames = array();
$attachedfiledatas = array();
$attachedfilemimetypes = array();

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

function block_exaport_get_resume_params($userid = null, $full = false) {
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
        $import_attachments = function($type, $recordid) use ($fs, $context, $CFG) {
            $result = null;
            $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_' . $type, $recordid, 'filename', false);
            if (count($files) > 0) {
                $result = array();
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $url = $CFG->wwwroot . '/pluginfile.php/' . $file->get_contextid() . '/block_exaport/resume_' . $type . '/' . $file->get_itemid() .
                        '/' . $filename;
                    $result[] = array('filename' => $filename, 'fileurl' => $url);
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
                $badge_entry->name = $badge->name;
                $badge_entry->image = block_exaport_get_user_badge_image($badge, true);
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

function block_exaport_get_user_badge_image($badge, $just_url = false) {
    // $src = '/pluginfile.php/'.context_user::instance($badge->usercreated)->id.'/badges/userbadge/'.$badge->id.'/'.
    // $badge->uniquehash;
    // Find badge by id.
    if (!$badge) {
        return '';
    }
    if (!$badge->courseid) {
        // For badges with courseid = NULL.
        $src = (string)moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
    } else {
        $context = context_course::instance($badge->courseid);
        $src = (string)moodle_url::make_pluginfile_url($context->id,
            'badges', 'badgeimage', $badge->id, '/', 'f1', false);
    }
    if ($just_url) {
        return $src;
    }
    $img = '<img src="' . $src . '" style="float: left; margin: 0px 10px;">';
    return $img;
}

function europass_xml($resumeid = 0) {
    global $USER, $DB, $SITE, $CFG;
    global $attachedfilenames, $attachedfiledatas, $attachedfilemimetypes;
    $xml = '';
    $resume = $DB->get_record('block_exaportresume', array("id" => $resumeid, 'user_id' => $USER->id));
    $yearPattern = "/^[12]\d{3}$/";

    $language_code = 'en';
    $scheme_id = 'exaportTest-0001';
    $scheme_name = 'DocumentIdentifier';
    $scheme_agency_name = 'EUROPASS';
    $scheme_version_id = '4.0';
    //Everything that i commented out is original

    $dom = new DOMDocument('1.0', 'utf-8');
    $root = $dom->createElement('SkillsPassport');
    //$root = $dom->createElement('Candidate');
    //$root->setAttribute('xsi:schemaLocation', 'http://www.europass.eu/1.0 Candidate.xsd');
    //$root->setAttribute('xmlns', 'http://www.europass.eu/1.0');
    $root->setAttribute('xmlns', 'http://europass.cedefop.europa.eu/Europass');
    //$root->setAttribute('xmlns:oa', 'http://www.openapplications.org/oagis/9');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttribute('xsi:schemaLocation', 'http://europass.cedefop.europa.eu/Europass http://europass.cedefop.europa.eu/xml/EuropassSchema_V3.0.xsd');
    $root->setAttribute('locale', "en");
    //$root->setAttribute('xmlns:eures', 'http://www.europass_eures.eu/1.0');
    //$root->setAttribute('xmlns:hr', 'http://www.hr-xml.org/3');
    // --- was not commented by me--
    // $root->setAttribute('majorVersionID', '3');
    // $root->setAttribute('minorVersionID', '2');
    // document ID
    $documentID = $dom->createElement('hr:DocumentID');
    $documentID->setAttribute('scheme_id', $scheme_id);
    $documentID->setAttribute('scheme_name', $scheme_name);
    $documentID->setAttribute('scheme_agency_name', $scheme_agency_name);
    $documentID->setAttribute('scheme_version_id', $scheme_version_id);

    // supplier
    /*
    $candidate_supplier = $dom->createElement('candidate_supplier');
    $party_id = $dom->createElement('hr:party_id');
    $party_id->setAttribute('scheme_id', $scheme_id);
    $party_id->setAttribute('scheme_name', 'party_id');
    $party_id->setAttribute('scheme_agency_name', $scheme_agency_name);
    $party_id->setAttribute('scheme_version_id', '1.0');
    $candidate_supplier->appendChild($party_id);
    $party_name = $dom->createElement('hr:party_name');
    $text = $dom->createTextNode($SITE->fullname.': Exabis ePortfolio CV');
    $party_name->appendChild($text);
    $candidate_supplier->appendChild($party_name);
    $root->appendChild($candidate_supplier);
    */


    // candidate
    /*
    $learner_info= $dom->createElement('LearnerInfo');
    $identification = $dom->createElement('identification');
    $person_name= $dom->createElement('PersonName');
    $firstName= $dom->createElement('FirstName');
    $text= $dom->createTextNode($USER->firstname);
    $firstName->appendChild($text);
    $surName= $dom->createElement('SurName');
    $text= $dom->createTextNode($USER->lastname);
    $surName->appendChild($text);
    $person_name->appendChild($firstName);
    $person_name->appendChild($surName);
    $identification->appendChild($person_name);
    $learner_info->appendChild($identification);
    */

    //$candidate_person = $dom->createElement('candidate_person');
    $learner_info = $dom->createElement('LearnerInfo');
    $identification_tag = $dom->createElement('Identification');
    // name
    //$person_name = $dom->createElement('person_name');
    $person_name = $dom->createElement('PersonName');
    //$given_name = $dom->createElement('oa:given_name');
    $first_name = $dom->createElement('FirstName');
    $user_firstName = clean_param($USER->firstname, PARAM_ALPHAEXT);
    $text = $dom->createTextNode($user_firstName);
    $first_name->appendChild($text);
    $family_name = $dom->createElement('Surname');
    $user_lastname = clean_param($USER->lastname, PARAM_ALPHAEXT);
    $text = $dom->createTextNode($user_lastname);
    $family_name->appendChild($text);
    $person_name->appendChild($first_name);
    $person_name->appendChild($family_name);
    $identification_tag->appendChild($person_name);
    // contact data

    $communication = $dom->createElement('ContactInfo');
    $identification_tag->appendChild($communication);


    // for phone numbers we need to know country code and phone number. So, use this code
    $phone_types = ['home', 'mobile'];
    $numb = [1, 2];
    $i = 0;
    foreach ($numb as $n) {
        if ($USER->{'phone' . $n}) {
            preg_match("~^(?:(?:\(?(?:00|\+)([1-4]\d\d|[1-9]\d?)\)?)?[\-\.\ \\\/]?)?((?:\(?\d{1,}\)?[\-\.\ \\\/]?){0,})(?:[\-\.\ \\\/]?(?:#|ext\.?|extension|x)[\-\.\ \\\/]?(\d+))?$~i", $USER->{'phone' . $n}, $matches);
            $country_code = @$matches[1];
            $phone_number = @$matches[2];
            if ($phone_number) {
                if ($country_code && $country_code > 0) { // get default country code from Moodle settings
                    europass_fill_communication_item($dom, $identification_tag, 'Telephone', ['oa:DialNumber' => $phone_number, 'use_code' => $phone_types[$i++], 'CountryDialing' => $country_code]);
                } else if ($CFG->country) {
                    // todo: find phone code by country code?
                    // europass_fill_communication_item($dom, $candidate_person, 'Telephone', ['oa:DialNumber' => $phone_number, 'use_code' => $phone_types[$i++], 'CountryCode' => strtolower($CFG->country)]);
                }

            }
        }
    }

    europass_fill_communication_item($dom, $communication, 'Email', ['Contact' => @$USER->email]);
    europass_fill_communication_item($dom, $identification_tag, 'Web', ['oa:URI' => @$USER->url]);
    europass_fill_communication_item($dom, $identification_tag, 'InstantMessage', ['oa:URI' => @$USER->icq, 'OtherTitle' => 'ICQ', 'use_code' => 'other']);
    europass_fill_communication_item($dom, $identification_tag, 'InstantMessage', ['oa:URI' => @$USER->skype, 'OtherTitle' => 'Skype', 'use_code' => 'other']);
    europass_fill_communication_item($dom, $identification_tag, 'InstantMessage', ['oa:URI' => @$USER->yahoo, 'OtherTitle' => 'Yahoo', 'use_code' => 'other']);
    europass_fill_communication_item($dom, $identification_tag, 'InstantMessage', ['oa:URI' => @$USER->aim, 'OtherTitle' => 'AIM', 'use_code' => 'other']);
    europass_fill_communication_item($dom, $identification_tag, 'InstantMessage', ['oa:URI' => @$USER->msn, 'OtherTitle' => 'MSN', 'use_code' => 'other']);

    $learner_info->appendChild($identification_tag);
    $root->appendChild($learner_info);

    // candidate_profile
    $educations_list = $dom->createElement('EducationList');
    $educations_list->setAttribute('language_code', 'en');

    /*
    $ID = $dom->createElement('hr:ID');
    $ID->setAttribute('scheme_id', $scheme_id);
    $ID->setAttribute('scheme_name', 'CandidateProfileID');
    $ID->setAttribute('scheme_agency_name', $scheme_agency_name);
    $ID->setAttribute('scheme_version_id', '1.0');

    $educations_list->appendChild($ID);
    */


    // user picture.
    /*$fs = get_file_storage();
    $img_types = array('png', 'jpg', 'jpeg');
    $i = 0;
    do {
        $file = $fs->get_file(context_user::instance($USER->id)->id, 'user', 'icon', 0, '/', 'f3.' . $img_types[$i]);
        $i++;
    } while (!$file && $i < count($img_types));
    if ($file) {
        $base64content = base64_encode('data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content())); // double encoding!!!!!
        europass_add_attachment($dom, $educations_list, $base64content, 'photo', 'ProfilePicture');
    };
	*/
    // personal information
    /*
    $executive_summary = $dom->createElement('hr:executive_summary');
    $executive_summary->appendChild($dom->createTextNode(clean_html_to_plain_text($resume->cover)));
    $educations_list->appendChild($executive_summary);
    */


    $employments = $DB->get_records('block_exaportresume_employ', array("resume_id" => $resume->id), 'sorting');
    // $workexperiencelist = europass_xml_employers_educations($dom, 'WorkExperience', $resume->employments);
    $work_experience = $dom->createElement('WorkExperienceList');

    foreach ($employments as $employment) {
        $work = $dom->createElement('WorkExperience');
        $organization_info = $dom->createElement('Employer');
        // title
        $label = $dom->createElement('Label');
        $organization_name = $dom->createElement('Name');
        $text = $dom->createTextNode(clean_for_external_xml($employment->employer));
        $organization_name->appendChild($text);
        $organization_info->appendChild($organization_name);
        $position_title = $dom->createElement('Position');
        $label->appendChild($dom->createTextNode($employment->jobtitle));
        $position_title->appendChild($label);
        $period_tag = $dom->createElement('Period');
        $ongoing = $dom->createElement('Current');
        $isOngoing= false;
        $currentYear= (int) date("Y");
        // start date
        if ($employment->startdate) {
            $start_date = $dom->createElement('From');
            $year = extractYear($employment->startdate);
            $period_tag->appendChild($start_date);
            $start_date->setAttribute('year', $year);
            $start_date->setAttribute('month', "01");
            $start_date->setAttribute('day', "01");
        }
        // end date
        if (!$employment->enddate){
            $ongoing->appendChild($dom->createTextNode('true'));
        }
        if ($employment->enddate) {
            $year = extractYear($employment->enddate);
            $isOngoing = $currentYear < (int)$year;
            if ($year && !$isOngoing) {
                $end_date = $dom->createElement('To');
                $end_date->setAttribute('year', $year);
                $end_date->setAttribute('month', "01");
                $end_date->setAttribute('day', "01");
                $ongoing->appendChild($dom->createTextNode('false'));
                $period_tag->appendChild($end_date);
            }
            if($isOngoing) {
                $ongoing->appendChild($dom->createTextNode('true'));
            }
        }
        $period_tag->appendChild($ongoing);
        $description = $dom->createElement('Activities');
        $text = $dom->createTextNode(clean_for_external_xml($employment->positiondescription));
        $description->appendChild($text);
        $work->appendChild($position_title);
        $work->appendChild($description);
        $work->appendChild($organization_info);
        $work->appendChild($period_tag);
        $work_experience->appendChild($work);
    }
    $learner_info->appendChild($work_experience);


    // EducationList / Education history.
    $educations = $DB->get_records('block_exaportresume_edu', array("resume_id" => $resume->id), 'sorting');
    //[id] => 1
    // [resume_id] => 1
    // [startdate] => marth 2010
    // [enddate] => april 2010
    // [institution] => Education 1
    // [institutionaddress] => address1
    // [qualtype] => type1
    // [qualname] => my title name1
    // [qualdescription] => description of qualification 1
    // [sorting] => 10

    foreach ($educations as $education) {
        // title
        $education_history = $dom->createElement('Education');
        $organization_info = $dom->createElement('Organisation');
        $organization_name = $dom->createElement('Name');
        $title_tag = $dom->createElement('Title');
        $text = $dom->createTextNode(clean_for_external_xml($education->institution));
        $organization_name->appendChild($text);
        $organization_info->appendChild($organization_name);
        $activities = $dom->createElement('Activities');
        $activities->appendChild($dom->createTextNode($education->qualdescription));
        $education_history->appendChild($organization_info);
        $attendance_period = $dom->createElement('Period');
        $ongoing = $dom->createElement('Current');
        if (empty($education->qualtype) && !empty($education->qualname)) {
            $title_tag->appendChild($dom->createTextNode($education->qualname));
        }
        if (!empty($education->qualtype)) {
            $title_tag->appendChild($dom->createTextNode($education->qualtype));
        } else {
            $title_tag->appendChild($dom->createTextNode(block_exaport_get_string("resume_qualification")));
        }
        // start date
        if ($education->startdate) {
            $start_date = $dom->createElement('From');
            $year = extractYear($education->startdate);
            $start_date->setAttribute('year', $year);
            $attendance_period->appendChild($start_date);
        }
        // end date
        if ($education->enddate) {
            $year = extractYear($education->enddate);
            if ($year) {
                $end_date = $dom->createElement('To');
                $end_date->setAttribute('year', $year);
                $attendance_period->appendChild($end_date);
                $ongoing->appendChild($dom->createTextNode('false'));
            }
        } else {
            $ongoing->appendChild($dom->createTextNode('true'));
        }
        // current
        $attendance_period->appendChild($ongoing);
        $education_history->appendChild($attendance_period);
        $education_history->appendChild($activities);
        $education_history->appendChild($title_tag);
        $educations_list->appendChild($education_history);
    }

    // Skills
    // skills - Career skills
    $skillcontent = $dom->createElement('Skills');
    if ($resume->skillscareers) {
        europassAddOthersPartToCandiadateProfile($skillcontent, $dom, $learner_info, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillscareers'), $resume->skillscareers);
    }
    // skills - Academic skills
    if ($resume->skillsacademic) {
        europassAddOthersPartToCandiadateProfile($skillcontent, $dom, $learner_info, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillsacademic'), $resume->skillsacademic);
    }
    // skills - Personal skills
    if ($resume->skillspersonal) {
        europassAddOthersPartToCandiadateProfile($skillcontent, $dom, $learner_info, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillspersonal'), $resume->skillspersonal);
    }
    // skills - Educational standards
    $skillscontent = '';
    $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'skills'));
    foreach ($competences as $competence) {
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*', $strictness = IGNORE_MISSING);
        if ($competencesdb != null) {
            $skillscontent .= '<p>' . $competencesdb->title . '</p>';
        };
    };
    if ($skillscontent) {
        europassAddOthersPartToCandiadateProfile($skillcontent, $dom, $learner_info, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillscomp'), $skillscontent);
    }


    // certificates
    $certifications = $DB->get_records('block_exaportresume_certif', array("resume_id" => $resume->id), 'sorting');
    $certi = $dom->createElement('AchievementList');

    if ($certifications && is_array($certifications)) {
        // list($sertificationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_certif');
        // europassAddOthersPartToCandiadateProfile($dom, $candidate_profile, block_exaport_get_string('resume_certif'), '', $sertificationsstring);
        foreach ($certifications as $certification) {
            $certification_node = $dom->createElement('Achievement');
            $certification_name = $dom->createElement('Title');
            $code = $dom->createElement('Code');
            $label = $dom->createElement('Label');
            $code->appendChild($dom->createTextNode('honors_awards'));
            $label->appendChild($dom->createTextNode($certification->title));
            $certification_name->appendChild($code);
            $certification_name->appendChild($label);
            $certification_node->appendChild($certification_name);
            if ($certification->date) {
                $year = extractYear($certification->date);
                $formatted_date_time = $dom->createElement('Date');
                $formatted_date_time->setAttribute('year', $year);
                $certification_node->appendChild($formatted_date_time);
            }
            $description = $dom->createElement('Description');
            $text = $dom->createTextNode($certification->description);
            $description->appendChild($text);
            $certification_node->appendChild($description);
            // attachment
            europass_xml_attachfile($dom, $learner_info, $certification_node, 'certif', [$certification->id], 'DOC');
            $certi->appendChild($certification_node);
        }
        $learner_info->appendChild($certi);
    }

    // Books, publications.
    $publications = $DB->get_records('block_exaportresume_public', array("resume_id" => $resume->id), 'sorting');
    if ($publications && is_array($publications)) {
        // list($publicationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_public');
        // europassAddOthersPartToCandiadateProfile($dom, $candidate_profile, block_exaport_get_string('resume_public'), '', $publicationsstring);
        foreach ($publications as $publication) {
            $publication_node = $dom->createElement('Achievement');
            $publication_title = $dom->createElement('Title');
            $publication_label = $dom->createElement('Label');
            $publication_code = $dom->createElement('Code');

            $publication_code->appendChild($dom->createTextNode("publications"));
            $publication_description = $dom->createElement("Description");
            $publication_label->appendChild($dom->createTextNode($publication->title));
            $publication_description->appendChild($dom->createTextNode($publication->date . "<br>"));
            $publication_description->appendChild($dom->createTextNode($publication->contribution . "<br>"));
            $publication_description->append($dom->createTextNode($publication->contributiondetails . "<br>"));
            $publication_description->append($dom->createTextNode($publication->url));
            $publication_title->appendChild($publication_code);
            $publication_title->appendChild($publication_label);
            $publication_node->appendChild($publication_title);
            $publication_node->appendChild($publication_description);


            $certi->appendChild($publication_node);

        }

        //
    }

    $badges = block_exaport_resume_get_badges($resume->id);
    if ($badges && is_array($badges)) {
        // list($publicationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_public');
        // europassAddOthersPartToCandiadateProfile($dom, $candidate_profile, block_exaport_get_string('resume_public'), '', $publicationsstring);
        foreach ($badges as $badge) {

            $rsbadge = $DB->get_record_sql('SELECT b.*, bi.dateissued, bi.uniquehash ' .
                ' FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid=' . intval($USER->id) .
                ' WHERE b.id=? ',
                array('id' => $badge->badgeid));

            $badge_node = $dom->createElement('Achievement');
            $badge_title = $dom->createElement('Title');
            $badge_label = $dom->createElement('Label');
            $badge_code = $dom->createElement('Code');

            $badge_code->appendChild($dom->createTextNode('certifications'));
            $badge_description = $dom->createElement('Description');
            $badge_label->appendChild($dom->createTextNode($rsbadge->name));
            $badge_description->appendChild($dom->createTextNode($rsbadge->description));

            $badge_title->appendChild($badge_code);
            $badge_title->appendChild($badge_label);
            $badge_node->appendChild($badge_title);
            $badge_node->appendChild($badge_description);


            $certi->appendChild($badge_node);

        }

        //
    }

    // Memberships.

    $mbrships = $DB->get_records('block_exaportresume_mbrship', array("resume_id" => $resume->id), 'sorting');

    if ($mbrships && is_array($mbrships)) {
        foreach ($mbrships as $mbrship) {
            $membership_node = $dom->createElement('Achievement');
            $membership_code = $dom->createElement('Code');
            $membership_label = $dom->createElement('Label');
            $membership_title = $dom->createElement('Title');
            $membership_description = $dom->createElement('Description');

            $membership_label->appendChild($dom->createTextNode($mbrship->title));
            $membership_code->appendChild($dom->createTextNode("memberships"));
            $membership_description->appendChild($dom->createTextNode($mbrship->description));


            $membership_title->appendChild($membership_label);
            $membership_title->appendChild($membership_code);
            $membership_node->appendChild($membership_title);
            $membership_node->appendChild($membership_description);

            $certi->appendChild($membership_node);
        }
    }

    /*
    if ($mbrshipstring) {
        europassAddOthersPartToCandiadateProfile($dom, $educations_list, block_exaport_get_string('resume_mbrship'), '', $mbrshipstring);
        // europass_xml_attachfile($dom, $candidate_profile, $publication_node, 'public', [$publication->id], 'DOC'); files?
    }
    // Goals.
    // goals - Personal goals
    if ($resume->goalspersonal) {
        europassAddOthersPartToCandiadateProfile($dom, $educations_list, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalspersonal'), $resume->goalspersonal);
    }
    // goals - Academic goals
    if ($resume->goalsacademic) {
        europassAddOthersPartToCandiadateProfile($dom, $educations_list, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalsacademic'), $resume->goalsacademic);
    }
    // goals - Careers goals
    if ($resume->goalscareers) {
        europassAddOthersPartToCandiadateProfile($dom, $educations_list, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalscareers'), $resume->goalscareers);
    }
    // goals - Educational standards
    $goalsstring = '';
    $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'goals'));
    foreach ($competences as $competence) {
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*', $strictness = IGNORE_MISSING);
        if ($competencesdb != null) {
            $goalsstring .= $competencesdb->title.'<br>';
        };
    };
    if ($goalsstring) {
        europassAddOthersPartToCandiadateProfile($dom, $educations_list, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalscomp'), $goalsstring);
    }
    */

    // Interests.
    /*
    if ($resume->interests) {
        $hobbies_and_interests = $dom->createElement('hobbies_and_interests');
        $hobby_or_interest = $dom->createElement('hobby_or_interest');
        $Title = $dom->createElement('Title');
        $text = $dom->createTextNode(block_exaport_get_string('resume_interests'));
        $Title->appendChild($text);
        $hobby_or_interest->appendChild($Title);
        $description = $dom->createElement('description');
        $text = $dom->createTextNode($resume->interests);
        $description->appendChild($text);
        $hobby_or_interest->appendChild($description);
        $hobbies_and_interests->appendChild($hobby_or_interest);
        $educations_list->appendChild($hobbies_and_interests);
    }
    */

    $learner_info->appendChild($educations_list);

    $dom->appendChild($root);
    $dom->formatOutput = true;
    $xml .= $dom->saveXML();

    // Save to file for development.
    /* $strXML = $xml; file_put_contents('d:/incom/testXML.xml', $strXML); */
    return $xml;
}

function europassAddOthersPartToCandiadateProfile($skillscontent, &$dom, &$candidateProfile, $sectionTitle, $title, $description) {

    $others = $dom->createElement('Other');
    $DescriptionNode = $dom->createElement('Description');
    $text = $dom->createTextNode($description);
    $DescriptionNode->appendChild($text);
    $others->appendChild($DescriptionNode);

    $skillscontent->appendChild($others);

    $candidateProfile->appendChild($skillscontent);
}

function extractYear($date) {
    $yearPattern = "/^[12]\d{3}$/";

    if (preg_match($yearPattern, $date)) {
        return $date;
    }

    $date_parts = preg_split('/[.\-\s]+/', $date);

    foreach ($date_parts as $part) {
        if (preg_match($yearPattern, $part)) {
            return $part;
        }
    }

    return null;
}

function get_europass_date($string_date, $format = 'Y') {
    try {
        $date = new \DateTime($string_date);
        $date = $date->format($format);
    } catch (\Exception $e) {
        $date = '';
    }
    return $date;
}

function europass_add_attachment(&$dom, &$candidateProfile, $file_content, $fileType, $instructions, $filename = '', $description = '', $documentTitle = '', $mimecode = '') {
    $attachment = $dom->createElement('eures:attachment');

    $file_content_node = $dom->createElement('oa:embedded_data');
    if ($mimecode) {
        $file_content_node->setAttribute('mimeCode', $mimecode);
    }
    if ($filename) {
        $file_content_node->setAttribute('filename', $filename);
    }
    $text = $dom->createTextNode($file_content);
    $file_content_node->appendChild($text);
    $attachment->appendChild($file_content_node);

    $file_type_node = $dom->createElement('oa:file_type');
    $text = $dom->createTextNode($fileType);
    $file_type_node->appendChild($text);
    $attachment->appendChild($file_type_node);

    $instructions_node = $dom->createElement('hr:Instructions');
    $text = $dom->createTextNode($instructions);
    $instructions_node->appendChild($text);
    $attachment->appendChild($instructions_node);

    $additional_params = array(
        'filename' => 'oa:file_name',
        'description' => 'oa:description',
        'documentTitle' => 'hr:DocumentTitle',
    );
    foreach ($additional_params as $param => $nodeName) {
        if (${'' . $param}) {
            $node = $dom->createElement($nodeName);
            $text = $dom->createTextNode(${'' . $param});
            $node->appendChild($text);
            $attachment->appendChild($node);
        }
    }

    $candidateProfile->appendChild($attachment);
}

function europass_fill_communication_item(&$dom, &$paren_node, $channel_code, $nodes) {
    $inserted = false;
    $communication = $dom->createElement('Email'); // new Communicate node!
    $i = 0;
    foreach ($nodes as $nodeName => $value) {
        if ($value) {
            $node = $dom->createElement($nodeName);
            $text = $dom->createTextNode($value);
            $node->appendChild($text);
            $communication->appendChild($node);
            $i++;
        }
    }
    if ($i == count($nodes)) {
        $inserted = true; // all nodes must have values
    }

    if ($inserted) {
        $communication->appendChild($node);
        $paren_node->appendChild($communication);
    }

}

function europass_fill_sub_element_text(&$dom, &$paren_node, $nodeName, $value) {
    if ($value) {
        $node = $dom->createElement($nodeName);
        $text = $dom->createTextNode($value);
        $node->appendChild($text);
        $paren_node->appendChild($node);
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

function clean_html_to_plain_text($text = '') {
    $breaks = ['<br />', '<br>', '<br/>'];
    $content = str_ireplace($breaks, "\r\n", $text);
    $content = strip_tags($content);
    return $content;
}

function get_date_params_from_string($datestring) {
    $datearr = date_parse($datestring);
    if ($datearr['year']) {
        $year = $datearr['year'];
    } else if (preg_match('/(19|20|21)\d{2}/', $datestring, $maches)) {
        $year = $maches[0];
    } else {
        $year = '';
    }
    if ($datearr['month']) {
        $month = $datearr['month'];
    } else {
        $month = '';
    }
    if ($datearr['day']) {
        $day = $datearr['day'];
    } else {
        $day = '';
    }
    $dateparams['year'] = $year;
    if ($month <> '') {
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $month = str_pad($month, 4, "-", STR_PAD_LEFT);
        $dateparams['month'] = $month;
    };
    if ($day <> '') {
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $day = str_pad($day, 5, "-", STR_PAD_LEFT);
        $dateparams['day'] = $day;
    }
    return $dateparams;
}

// attachment files
/**
 * @param DOMDocument $dom
 * @param DOMElement $candidateProfile
 * @param DOMElement $parentNode
 * @param string $type
 * @param array $ids
 * @param string $instructions
 * @return mixed
 * @throws coding_exception
 */
function europass_xml_attachfile(&$dom, &$candidateProfile, &$parentNode, $type, $ids = array(), $instructions = 'ProfilePicture') {
    // non implemented yet in new Europass?
    return true;

    global $USER;
    $files = array();
    $fs = get_file_storage();
    // Achievement's files.
    switch ($type) {
        case 'certif':
            $filearea = 'resume_certif';
            break;
        case 'public':
            $filearea = 'resume_publication';
            break;
        case 'membership':
            $filearea = 'resume_membership';
            break;
        case 'skills':
            foreach ($ids as $id) {
                $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                        'block_exaport', 'resume_skillspersonal', $id, 'filename', false);
                $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                        'block_exaport', 'resume_skillsacademic', $id, 'filename', false);
            };
            $filearea = 'resume_skillscareers';
            break;
        case 'goals':
            foreach ($ids as $id) {
                $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                        'block_exaport', 'resume_goalspersonal', $id, 'filename', false);
                $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                        'block_exaport', 'resume_goalsacademic', $id, 'filename', false);
            };
            $filearea = 'resume_goalscareers';
            break;
        default:
            $filearea = 'none';
    };
    foreach ($ids as $id) {
        $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                'block_exaport', $filearea, $id, 'filename', false);
    };

    if (count($files) > 0) {

        /* if ($candidateProfile->getElementsByTagName("eures:attachment")->length == 0) {
            $attachment = $dom->createElement('eures:attachment');
            $candidateProfile->appendChild($attachment);
        } else {
            $attachment = $candidateProfile->getElementsByTagName('eures:attachment')[0];
        }*/

        foreach ($files as $file) {
            $file_name_id = '';

            if ($parentNode) {
                // Insert reference to the Parent node
                $attachment_reference = $dom->createElement('eures:attachment_reference');
                $description = $dom->createElement('oa:description');
                $text = $dom->createTextNode('1111111111');
                $description->appendChild($text);
                $attachment_reference->appendChild($description);

                $x_path = $dom->createElement('hr:x_path');
                $rand_str = substr(str_shuffle(MD5(microtime())), 0, 5);
                $file_name_id = $rand_str . '_' . $file->get_filename();
                $xpath_full = '/Candidate/candidate_profile/attachment/oa:file_name[text()=\'' . $file_name_id . '\']';
                $text = $dom->createTextNode($xpath_full);
                $x_path->appendChild($text);
                $attachment_reference->appendChild($x_path);
                $parentNode->appendChild($attachment_reference);
            }

            // insert attachment main data
            $attachment = $dom->createElement('eures:attachment');

            $embedded_data = $dom->createElement('oa:embedded_data');
            $embedded_data_content = base64_encode($file->get_content());
            $embedded_data_content_node = $dom->createTextNode($embedded_data_content);
            $embedded_data_content_node->appendChild($embedded_data_content_node);
            $embedded_data->appendChild($embedded_data_content_node);
            $attachment->appendChild($embedded_data);

            $file_type = $dom->createElement('oa:file_type');
            $text = $dom->createTextNode('DOC');
            $file_type->appendChild($text);
            $attachment->appendChild($file_type);

            $Instructions = $dom->createElement('hr:Instructions');
            $text = $dom->createTextNode($instructions);
            $Instructions->appendChild($text);
            $attachment->appendChild($Instructions);

            if ($file_name_id) {
                $file_name = $dom->createElement('oa:file_name');
                $text = $dom->createTextNode($file_name_id);
                $file_name->appendChild($text);
                $attachment->appendChild($file_name);
            }

            $candidateProfile->appendChild($attachment);
        };

    };

}

// Get string from resume block.
function list_for_resume_elements($resumeid, $tablename) {
    global $DB, $USER;
    $itemsids = array();
    $items = $DB->get_records($tablename, array("resume_id" => $resumeid));
    $itemsstring = '<ul>';
    foreach ($items as $ind => $item) {
        $itemsstring .= '<li>';
        $itemsids[] = $ind;
        switch ($tablename) {
            case 'block_exaportresume_certif':
                $itemsstring .= $item->title;
                $itemsstring .= ' (' . $item->date . ')';
                $itemsstring .= ($item->description ? ". " : "") . $item->description;
                break;
            case 'block_exaportresume_public':
                $itemsstring .= $item->title;
                $itemsstring .= ' (' . $item->date . '). ';
                $itemsstring .= $item->contribution;
                $itemsstring .= ($item->contributiondetails ? ": " : "") . $item->contributiondetails;
                break;
            case 'block_exaportresume_mbrship':
                $itemsstring .= $item->title;
                $itemsstring .= ' (' . $item->startdate . ($item->enddate ? "-" . $item->enddate : "") . ')';
                $itemsstring .= ($item->description ? ". " : "") . $item->description;
                break;
            default:
                $itemsstring .= '';
        };

        $itemsstring .= '</li>';
    }
    $itemsstring .= '</ul>';
    return array($itemsstring, $itemsids);
}

?>
