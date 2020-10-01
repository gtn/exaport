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

require_once($CFG->libdir.'/formslib.php');

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

        $mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');

        $mform->addElement('editor', $param.'_editor', get_string('resume_'.$param, 'block_exaport'), null,
                array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        $mform->addExaportHelpButton($param.'_editor', 'forms.resume.'.$param.'_editor');

        if ($withfiles) {
            $mform->addElement('filemanager', 'attachments', get_string('resume_files', 'block_exaport'), null,
                    array('subdirs' => false, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'maxfiles' => 5));
            $mform->addExaportHelpButton('attachments', 'forms.resume.attachments');
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
        $mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');

        if (isset($inputs) && is_array($inputs) && count($inputs) > 0) {
            foreach ($inputs as $fieldname => $fieldtype) {
                list ($type, $required) = explode(':', $fieldtype.":");
                switch ($type) {
                    case 'text' :
                        $mform->addElement('text', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'), $attributestext);
                        $mform->setType($fieldname, PARAM_RAW);
                        $mform->addExaportHelpButton($fieldname, 'forms.resume.'.$fieldname);
                        break;
                    case 'textarea' :
                        $mform->addElement('textarea', $fieldname, get_string('resume_'.$fieldname, 'block_exaport'),
                                $attributestextarea);
                        $mform->setType($fieldname, PARAM_RAW);
                        $mform->addExaportHelpButton($fieldname, 'forms.resume.'.$fieldname);
                        break;
                    case 'filearea' :
                        $mform->addElement('filemanager', 'attachments', get_string('resume_'.$fieldname, 'block_exaport'), null,
                                array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
                        $mform->addExaportHelpButton('attachments', 'forms.resume.attachments_'.$fieldname);
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
        $mform->addElement('html', '<div class="block_eportfolio_center">'.$this->_customdata['formheader'].'</div>');

        if (isset($records) && is_array($records) && count($records) > 0) {
            foreach ($records as $record) {
                $mform->addElement('checkbox', 'check['.$record['id'].']', $record['title'], $record['description']);
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
                $records[$badge->id]['title'] = $badgeimage.$badge->name;
                $dateformat = get_string('strftimedate', 'langconfig');
                $records[$badge->id]['description'] = userdate($badge->dateissued, $dateformat).': '.$badge->description;
            };
            $defaultvalues = $DB->get_records('block_exaportresume_'.$edit, array('resumeid' => $resume->id), null, 'badgeid');
            break;
    }

    $formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$edit, "block_exaport");
    $workform = new block_exaport_resume_checkboxlist_form($_SERVER['REQUEST_URI'].'#'.$edit,
            array('formheader' => $formheader, 'records' => $records));
    $data->check = $defaultvalues;
    $data->resume_id = $resume->id;
    $workform->set_data($data);
    if ($workform->is_cancelled()) {
        $showiinformation = true;
    } else if ($fromform = $workform->get_data()) {
        $DB->delete_records('block_exaportresume_'.$edit, array('resumeid' => $resume->id));
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
                    $DB->insert_record('block_exaportresume_'.$edit, $dataobject);
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
    $formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$typeblock, "block_exaport");
    $workform = new block_exaport_resume_multifields_form($_SERVER['REQUEST_URI'].'#'.$typeblock,
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
            file_save_draft_area_files($fromform->attachments, $context->id, 'block_exaport', 'resume_'.$typeblock, $itemid,
                    array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        };
        echo "<div class='block_eportfolio_center'>".
                $OUTPUT->box(get_string('resume_'.$typeblock."saved", "block_exaport"), 'center')."</div>";
        $showinformation = true;
    } else {
        if ($id > 0) {
            // Edit existing record.
            // Files.
            $draftitemid = file_get_submitted_draft_itemid('attachments');
            $context = context_user::instance($USER->id);
            file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'resume_'.$typeblock, $id,
                    array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
            // All data to form.
            $data = $DB->get_record("block_exaportresume_".$typeblock, array('id' => $id, 'resume_id' => $resume->id));
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

    // add related parameters of resume
    if ($full && $resumeparams) {
        // TODO: add images?
        $fs = get_file_storage();
        $context = context_user::instance($userid);
        $importAttachments = function($type, $recordid) use ($fs, $context, $CFG) {
            $result = null;
            $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type, $recordid, 'filename', false);
            if (count($files) > 0) {
                $result = array();
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $url = $CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/block_exaport/resume_'.$type.'/'.$file->get_itemid().
                            '/'.$filename;
                    $result[] = array('filename' => $filename, 'fileurl' => $url);
                };
            }
            return $result;
        };

        // educations
        $educations = block_exaport_resume_get_educations(@$resumeparams->id);
        if ($educations) {
            foreach ($educations as $education) {
                $education->attachments = $importAttachments('edu', $education->id);
            }
            $resumeparams->educations = $educations;
        }
        // employments
        $employments = block_exaport_resume_get_employments(@$resumeparams->id);
        if ($employments) {
            foreach ($employments as $employment) {
                $employment->attachments = $importAttachments('employ', $employment->id);
            }
            $resumeparams->employments = $employments;
        }
        // certifications
        $certifications = block_exaport_resume_get_certificates(@$resumeparams->id);
        if ($certifications) {
            foreach ($certifications as $certification) {
                $certification->attachments = $importAttachments('certif', $certification->id);
            }
            $resumeparams->certifications = $certifications;
        }
        // publications
        $publications = block_exaport_resume_get_publications(@$resumeparams->id);
        if ($publications) {
            foreach ($publications as $publication) {
                $publication->attachments = $importAttachments('public', $publication->id);
            }
            $resumeparams->publications = $publications;
        }
        // Professional memberships
        $profmembershipments = block_exaport_resume_get_profmembershipments(@$resumeparams->id);
        if ($profmembershipments) {
            foreach ($profmembershipments as $profmembershipment) {
                $profmembershipment->attachments = $importAttachments('mbrship', $profmembershipment->id);
            }
            $resumeparams->profmembershipments = $profmembershipments;
        }
        // add files to skills and goals
        $elements = array('personal', 'academic', 'careers');
        foreach ($elements as $element) {
            $resumeparams->{'goals'.$element.'_attachments'} = $importAttachments('goals'.$element, $resumeparams->id);
            $resumeparams->{'skills'.$element.'_attachments'} = $importAttachments('skills'.$element, $resumeparams->id);
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
        $newresumeparams = (object) $params;
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
        $id = $DB->insert_record('block_exaportresume_'.$table, $fromform);
    } else if ($fromform->id > 0) {
        $DB->update_record('block_exaportresume_'.$table, $fromform);
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
        $wherearr[] = $field.' = ? ';
        $params[] = $value;
    }
    $where = implode(' AND ', $wherearr);
    $records = $DB->get_records_sql('SELECT * FROM {block_exaportresume_'.$table.'} WHERE '.$where.' ORDER BY sorting', $params);
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
    $table->head['title'] = get_string('resume_'.$headertitle, 'block_exaport');
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
                    $position .= ' ('.block_exaport_html_secure($record->qualtype, FORMAT_PLAIN).')';
                } else {
                    $position .= block_exaport_html_secure($record->qualtype, FORMAT_PLAIN);
                };
                if ($position) {
                    $position .= ' '.get_string('in', 'block_exaport').' ';
                }
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->qualdescription) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= $position.block_exaport_html_secure($record->institution, FORMAT_PLAIN).'</strong>';
                if ($record->qualdescription) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>'.block_exaport_html_secure($record->startdate, FORMAT_PLAIN).
                        (isset($record->enddate) && $record->enddate <> '' ? ' - '.block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '').'</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($record->qualdescription).'</div>';
                break;
            case 'employ':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->positiondescription) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->jobtitle, FORMAT_PLAIN).': '.block_exaport_html_secure($record->employer, FORMAT_PLAIN).'</strong>';
                if ($record->positiondescription) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>'.block_exaport_html_secure($record->startdate, FORMAT_PLAIN).
                        (isset($record->enddate) && $record->enddate <> '' ? ' - '.block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '').'</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($record->positiondescription).'</div>';
                break;
            case 'certif':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN).'</strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>'.block_exaport_html_secure($record->date, FORMAT_PLAIN).'</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($record->description).'</div>';
                break;
            case 'public':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->contributiondetails) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN).' ('.block_exaport_html_secure($record->contribution, FORMAT_PLAIN).')</strong>';
                if ($record->contributiondetails) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>'.block_exaport_html_secure($record->date, FORMAT_PLAIN).'</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($record->contributiondetails);
                if ($record->url) {
                    $table->data[$itemindex]['title'] .= '<br><a href="'.s($record->url).'">'.s($record->url).'</a>';
                };
                $table->data[$itemindex]['title'] .= '</div>';
                break;
            case 'mbrship':
                $table->data[$itemindex]['title'] = '<strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($record->title, FORMAT_PLAIN).'</strong>';
                if ($record->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $table->data[$itemindex]['title'] .= '<div>'.block_exaport_html_secure($record->startdate, FORMAT_PLAIN).
                        (isset($record->enddate) && $record->enddate <> '' ? ' - '.block_exaport_html_secure($record->enddate, FORMAT_PLAIN) : '').'</div>';
                $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($record->description).'</div>';
                break;
            case 'badges':
                $badge = $DB->get_record_sql('SELECT b.*, bi.dateissued, bi.uniquehash '.
                                ' FROM {badge} b LEFT JOIN {badge_issued} bi ON b.id=bi.badgeid AND bi.userid='.$USER->id.
                                ' WHERE b.id=? ',
                                array('id' => $record->badgeid));
                $table->data[$itemindex]['title'] = '<strong>';
                if ($badge->description) {
                    $table->data[$itemindex]['title'] .= '<a href="#" class="expandable-head">';
                };
                $table->data[$itemindex]['title'] .= block_exaport_html_secure($badge->name, FORMAT_PLAIN).'</strong>';
                if ($badge->description) {
                    $table->data[$itemindex]['title'] .= '</a>';
                };
                $dateformat = get_string('strftimedate', 'langconfig');
                $badgeimage = block_exaport_get_user_badge_image($badge);
                $table->data[$itemindex]['title'] .= '<div>'.userdate($badge->dateissued, $dateformat).'</div>'.
                                                '<div class="expandable-text hidden">'.block_exaport_html_secure($badge->description).$badgeimage.'</div>';
                break;
            default:
                break;
        }
        // Count of files.
        if ($filescolumn) {
            $fs = get_file_storage();
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type, $record->id, 'filename', false);
            $countfiles = count($files);
            if ($countfiles > 0) {
                $table->data[$itemindex]['files'] = '<a href="#" class="expandable-head">'.$countfiles.'</a>'.
                                '<div class="expandable-text hidden">'.block_exaport_resume_list_files($type, $files).'</div>';
            } else {
                $table->data[$itemindex]['files'] = '0';
            };
        };
        // Links to up/down.
        if ($updowncolumn) {
            if ($itemindex < count($records) - 1) {
                $idnext = $keys[$itemindex + 1];
            };
            $linktoup = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=sortchange&type='.$type.
                    '&id1='.$record->id.'&id2='.$idnext.'&sesskey='.sesskey().'"><img src="pix/down_16.png" alt="'.
                    get_string("down").'" /></a>';
            $linktodown = '<a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.
                    '&action=sortchange&type='.$type.'&id1='.$record->id.'&id2='.$idprev.'&sesskey='.sesskey().'">'.
                    '<img src="pix/up_16.png" alt="'.get_string("up").'" /></a>';
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
            $table->data[$itemindex]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.
                    '&action=edit&type='.$type.'&id='.$record->id.'&sesskey='.sesskey().'">'.
                    '<img src="pix/edit.png" alt="'.get_string("edit").'" /></a>'.
                    ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&action=delete&type='.$type.'&id='.
                    $record->id.'"><img src="pix/del.png" alt="'.get_string("delete").'"/></a>';
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
    $table->head['title'] = get_string('resume_'.$type, 'block_exaport');
    $table->head['files'] = get_string('resume_files', 'block_exaport');
    $table->head['icons'] = '';
    $table->size['files'] = '40px';
    $table->size['icons'] = '40px';

    $itemindex = 0;
    // Competencies.
    if (block_exaport_check_competence_interaction()) {
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(BLOCK_EXACOMP_DB_DESCRIPTORS)) {
            $table->data[$itemindex]['title'] = get_string('resume_'.$type.'comp', 'block_exaport').
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
                    $comptitles .= $competencesdb->title.'<br>';
                };
            };
            if ($comptitles <> '') {
                $table->data[$itemindex]['title'] = '<a name="'.$type.'comp"></a><a href="#" class="expandable-head">'.
                        get_string('resume_'.$type.'comp', 'block_exaport').'</a>';
            } else {
                $table->data[$itemindex]['title'] = '<a name="'.$type.'comp"></a>'.
                                                    get_string('resume_'.$type.'comp', 'block_exaport');
            }
            $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.$comptitles.'</div>';
            $table->data[$itemindex]['files'] = '';
            // Links to edit / delete.
            if (file_exists($CFG->dirroot.'/blocks/exacomp/lib/lib.php')) {
                $table->data[$itemindex]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.
                        $courseid.'&action=edit&type='.$type.'comp&id='.$resume->id.'&sesskey='.sesskey().'">'.
                        '<img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
            } else {
                $table->data[$itemindex]['icons'] = '';
            }
        };

    };

    foreach ($elements as $element) {
        $itemindex++;
        // Title and Description.
        $description = '';
        $description = $resume->{$type.$element};
        $description = file_rewrite_pluginfile_urls($description, 'pluginfile.php',
                            context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_'.$type.$element, $resume->id);
        $description = trim($description);
        if (preg_replace('/\<br(\s*)?\/?\>/i', "", $description) == '') {
            // If text is only <br> (html-editor can return this).
            $description = '';
        }
        $table->data[$itemindex]['title'] = '';
        $fs = get_file_storage();
        $context = context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'block_exaport', 'resume_'.$type.$element, $resume->id, 'filename', false);
        // Count of files.
        $countfiles = count($files);
        if ($countfiles > 0) {
            $table->data[$itemindex]['files'] = '<a href="#" class="expandable-head">'.$countfiles.'</a>'.
                        '<div class="expandable-text hidden">'.block_exaport_resume_list_files($type.$element, $files).'</div>';
        } else {
            $table->data[$itemindex]['files'] = '0';
        };
        if ($description <> '') {
            $table->data[$itemindex]['title'] = '<a name="'.$type.$element.'"></a><a href="#" class="expandable-head">'.
                    get_string('resume_'.$type.$element, 'block_exaport').'</a>';
            $table->data[$itemindex]['title'] .= '<div class="expandable-text hidden">'.block_exaport_html_secure($description).'</div>';
        } else {
            $table->data[$itemindex]['title'] = '<a name="'.$type.$element.'"></a>'.
                                                    get_string('resume_'.$type.$element, 'block_exaport');
        };
        // Links to edit / delete.
        $table->data[$itemindex]['icons'] = ' <a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.
                '&action=edit&type='.$type.$element.'&id='.$resume->id.'&sesskey='.sesskey().'">'.
                '<img src="pix/edit.png" alt="'.get_string("edit").'" /></a>';
    };

    $tablecontent = html_writer::table($table);
    return $tablecontent;
}

function block_exaport_resume_list_files($filearea, $files) {
    global $CFG;
    $listfiles = '<ul class="resume_listfiles">';
    foreach ($files as $file) {
        $filename = $file->get_filename();
        $url = $CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/block_exaport/resume_'.$filearea.'/'.$file->get_itemid().
                '/'.$filename;
        $listfiles .= '<li>'.html_writer::link($url, $filename).'</li>';
    };
    $listfiles .= '<ul>';

    return $listfiles;
}

function block_exaport_resume_mm_delete($table, $conditions) {
    global $DB, $USER;
    $DB->delete_records('block_exaportresume_'.$table, $conditions);
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_'.$table, $conditions['id']);
    foreach ($files as $file) {
        $file->delete();
    };
}

function block_exaport_get_max_sorting($table, $resumeid) {
    global $DB;
    return $DB->get_field_sql('SELECT MAX(sorting) FROM {block_exaportresume_'.$table.'} WHERE resume_id=?', array($resumeid));
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
    $content .= '<div class="block_eportfolio_center">'.get_string('edit', "block_exaport").': '.
            get_string('resume_'.$typeblock, "block_exaport").'</div>';
    $content .= block_exaport_build_comp_tree($typeblock, $resume);
    echo $content;
    return false;
}

function block_exaport_get_user_badge_image($badge) {
    global $USER;
    $src = '/pluginfile.php/'.context_user::instance($badge->usercreated)->id.'/badges/userbadge/'.$badge->id.'/'.
            $badge->uniquehash;
    $img = '<img src="'.$src.'" style="float: left; margin: 0px 10px;">';
    return $img;
}

// Get XML for europass.
function europass_xml($resumeid = 0) {
    global $USER, $DB;
    global $attachedfilenames, $attachedfiledatas, $attachedfilemimetypes;
    $xml = '';
    $resume = $DB->get_record('block_exaportresume', array("id" => $resumeid, 'user_id' => $USER->id));

    $dom = new DOMDocument('1.0', 'utf-8');
    $root = $dom->createElement('SkillsPassport');
    $root->setAttribute('xmlns', 'http://europass.cedefop.europa.eu/Europass');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttribute('xsi:schemaLocation',
            'http://europass.cedefop.europa.eu/Europass http://europass.cedefop.europa.eu/xml/v3.2.0/EuropassSchema.xsd');
    $root->setAttribute('locale', 'en');
    // DocumentInfo.
    $documentinfo = $dom->createElement('DocumentInfo');
    $documenttype = $dom->createElement('DocumentType');
    $text = $dom->createTextNode('ECV');
    $documenttype->appendChild($text);
    $documentinfo->appendChild($documenttype);
    $bundle = $dom->createElement('Bundle');
    $documentinfo->appendChild($bundle);
    $creationdate = $dom->createElement('CreationDate');
    $text = $dom->createTextNode(date("Y-m-d").'T'.date("H:i:s.000").'Z');
    $creationdate->appendChild($text);
    $documentinfo->appendChild($creationdate);
    $lastupdatedate = $dom->createElement('LastUpdateDate');
    $text = $dom->createTextNode(date("Y-m-d").'T'.date("H:i:s.000").'Z');
    $lastupdatedate->appendChild($text);
    $documentinfo->appendChild($lastupdatedate);
    $xsdversion = $dom->createElement('XSDVersion');
    $text = $dom->createTextNode('V3.2');
    $xsdversion->appendChild($text);
    $documentinfo->appendChild($xsdversion);
    $generator = $dom->createElement('Generator');
    $text = $dom->createTextNode('Moodle exaport resume');
    $generator->appendChild($text);
    $documentinfo->appendChild($generator);
    $comment = $dom->createElement('Comment');
    $text = $dom->createTextNode('Europass CV from Moodle exaport');
    $comment->appendChild($text);
    $documentinfo->appendChild($comment);
    $root->appendChild($documentinfo);

    // LearnerInfo.
    $learnerinfo = $dom->createElement('LearnerInfo');
    $identification = $dom->createElement('Identification');
    $personname = $dom->createElement('PersonName');
    $title = $dom->createElement('Title');
    $text = $dom->createTextNode('');
    $title->appendChild($text);
    $personname->appendChild($title);
    $firstname = $dom->createElement('FirstName');
    $text = $dom->createTextNode($USER->firstname);
    $firstname->appendChild($text);
    $personname->appendChild($firstname);
    $surname = $dom->createElement('Surname');
    $text = $dom->createTextNode($USER->lastname);
    $surname->appendChild($text);
    $personname->appendChild($surname);
    $identification->appendChild($personname);

    $contactinfo = $dom->createElement('ContactInfo');
    // Address info.
    $address = $dom->createElement('Address');
    $contact = $dom->createElement('Contact');
    $addressline = $dom->createElement('AddressLine');
    $text = $dom->createTextNode($USER->address);
    $addressline->appendChild($text);
    $contact->appendChild($addressline);
    $postalcode = $dom->createElement('PostalCode');
    $text = $dom->createTextNode('');
    $postalcode->appendChild($text);
    $contact->appendChild($postalcode);
    $municipality = $dom->createElement('Municipality');
    $text = $dom->createTextNode($USER->city);
    $municipality->appendChild($text);
    $contact->appendChild($municipality);
    $country = $dom->createElement('Country');
    $code = $dom->createElement('Code');
    $text = $dom->createTextNode($USER->country);
    $code->appendChild($text);
    $country->appendChild($code);
    $label = $dom->createElement('Label');
    $text = $dom->createTextNode('');
    $label->appendChild($text);
    $country->appendChild($label);
    $contact->appendChild($country);
    $address->appendChild($contact);
    $contactinfo->appendChild($address);
    // Email.
    $email = $dom->createElement('Email');
    $contact = $dom->createElement('Contact');
    $text = $dom->createTextNode($USER->email);
    $contact->appendChild($text);
    $email->appendChild($contact);
    $contactinfo->appendChild($email);
    // Phones.
    $telephonelist = $dom->createElement('TelephoneList');
    $phones = array('1' => 'home', '2' => 'mobile');
    foreach ($phones as $index => $label) {
        $telephone = $dom->createElement('Telephone');
        $contact = $dom->createElement('Contact');
        $text = $dom->createTextNode($USER->{'phone'.$index});
        $contact->appendChild($text);
        $telephone->appendChild($contact);
        $use = $dom->createElement('Use');
        $code = $dom->createElement('Code');
        $text = $dom->createTextNode($label);
        $code->appendChild($text);
        $use->appendChild($code);
        $telephone->appendChild($use);
        $telephonelist->appendChild($telephone);
    };
    $contactinfo->appendChild($telephonelist);
    // Www.
    $websitelist = $dom->createElement('WebsiteList');
    $website = $dom->createElement('Website');
    $contact = $dom->createElement('Contact');
    $text = $dom->createTextNode($USER->url);
    $contact->appendChild($text);
    $website->appendChild($contact);
    $use = $dom->createElement('Use');
    $code = $dom->createElement('Code');
    $text = $dom->createTextNode('personal');
    $code->appendChild($text);
    $use->appendChild($code);
    $website->appendChild($use);
    $websitelist->appendChild($website);
    $contactinfo->appendChild($websitelist);
    // Messengers.
    $instantmessaginglist = $dom->createElement('InstantMessagingList');
    $messangers = array('skype', 'icq', 'aim', 'msn', 'yahoo');
    foreach ($messangers as $messanger) {
        $instantmessaging = $dom->createElement('InstantMessaging');
        $contact = $dom->createElement('Contact');
        $text = $dom->createTextNode($USER->{$messanger});
        $contact->appendChild($text);
        $instantmessaging->appendChild($contact);
        $use = $dom->createElement('Use');
        $code = $dom->createElement('Code');
        $text = $dom->createTextNode($messanger);
        $code->appendChild($text);
        $use->appendChild($code);
        $instantmessaging->appendChild($use);
        $instantmessaginglist->appendChild($instantmessaging);
    };
    $contactinfo->appendChild($instantmessaginglist);
    $identification->appendChild($contactinfo);

    // PHOTO.
    $fs = get_file_storage();
    $imgTypes = array('png', 'jpg', 'jpeg');
    $i = 0;
    do {
        $file = $fs->get_file(context_user::instance($USER->id)->id, 'user', 'icon', 0, '/', 'f3.'.$imgTypes[$i]);
        $i++;
    } while (!$file && $i < count($imgTypes));
    if ($file) {
        $photo = $dom->createElement('Photo');
        $mimetype = $dom->createElement('MimeType');
        $text = $dom->createTextNode($file->get_mimetype());
        $mimetype->appendChild($text);
        $photo->appendChild($mimetype);
        $data = $dom->createElement('Data');
        $userpicturefilecontent = base64_encode($file->get_content());
        $text = $dom->createTextNode($userpicturefilecontent);
        $data->appendChild($text);
        $photo->appendChild($data);
        $identification->appendChild($photo);
    };

    $learnerinfo->appendChild($identification);
    // Headline - insert of the cover of exaport resume.
    $headline = $dom->createElement('Headline');
    $type = $dom->createElement('Type');
    $code = $dom->createElement('Code');
    $text = $dom->createTextNode('personal_statement');
    $code->appendChild($text);
    $type->appendChild($code);
    $label = $dom->createElement('Label');
    $text = $dom->createTextNode('PERSONAL STATEMENT');
    $label->appendChild($text);
    $type->appendChild($label);
    $headline->appendChild($type);
    $description = $dom->createElement('Description');
    $label = $dom->createElement('Label');
    $text = $dom->createTextNode(clean_for_external_xml($resume->cover));
    $label->appendChild($text);
    $description->appendChild($label);
    $headline->appendChild($description);
    $learnerinfo->appendChild($headline);

    // WorkExperienceList / Employment history.
    $resume->employments = $DB->get_records('block_exaportresume_employ', array("resume_id" => $resume->id), 'sorting');
    $workexperiencelist = europass_xml_employers_educations($dom, 'WorkExperience', $resume->employments);
    $learnerinfo->appendChild($workexperiencelist);    /**/

    // EducationList / Education history.
    $resume->educations = $DB->get_records('block_exaportresume_edu', array("resume_id" => $resume->id), 'sorting');
    $workeducationslist = europass_xml_employers_educations($dom, 'Education', $resume->educations);
    $learnerinfo->appendChild($workeducationslist);    /**/

    // Skills. Carrer skills to Job-related skills.
    $skills = $dom->createElement('Skills');
    $other = $dom->createElement('Other');
    $description = $dom->createElement('Description');
    $skillscontent = $resume->skillscareers.$resume->skillsacademic.$resume->skillspersonal;
    $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'skills'));
    foreach ($competences as $competence) {
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*',
                $strictness = IGNORE_MISSING);
        if ($competencesdb != null) {
            $skillscontent .= '<p>'.$competencesdb->title.'</p>';
        };
    };
    $text = $dom->createTextNode(clean_for_external_xml($skillscontent));
    $description->appendChild($text);
    $other->appendChild($description);
    // Skill's files.
    $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'resume_skillscareers', $resume->id,
            'filename', false);
    $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                            'block_exaport', 'resume_skillspersonal', $resume->id, 'filename', false);
    $files = $files + $fs->get_area_files(context_user::instance($USER->id)->id,
                            'block_exaport', 'resume_skillsacademic', $resume->id, 'filename', false);
    if (count($files) > 0) {
        $filearray = europass_xml_get_attached_file_contents($files);
        $documentation = europass_xml_documentation_list($dom, $filearray);
        $other->appendChild($documentation);
    };

    $skills->appendChild($other);
    $learnerinfo->appendChild($skills);

    // AchievementList.
    $achievementlist = $dom->createElement('AchievementList');
    // Sertifications, awards.
    list($sertificationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_certif');
    $sertifications = europass_xml_achievement($dom, 'certif', $elementids, get_string('resume_certif', 'block_exaport'),
            clean_for_external_xml($sertificationsstring));
    $achievementlist->appendChild($sertifications);

    // Books, publications.
    list($publicationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_public');
    $publications = europass_xml_achievement($dom, 'public', $elementids, get_string('resume_public', 'block_exaport'),
            clean_for_external_xml($publicationsstring));
    $achievementlist->appendChild($publications);

    // Memberships.
    list($mbrshipstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_mbrship');
    $memberships = europass_xml_achievement($dom, 'membership', $elementids, get_string('resume_mbrship', 'block_exaport'),
            clean_for_external_xml($mbrshipstring));
    $achievementlist->appendChild($memberships);

    // Goals.
    $goalsstring = $resume->goalspersonal.'<br>'.$resume->goalsacademic.'<br>'.$resume->goalscareers;
    $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'goals'));
    foreach ($competences as $competence) {
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*',
                $strictness = IGNORE_MISSING);
        if ($competencesdb != null) {
            $goalsstring .= $competencesdb->title.'<br>';
        };
    };
    $goals = europass_xml_achievement($dom, 'goals', array($resume->id), get_string('resume_mygoals', 'block_exaport'),
            clean_for_external_xml($goalsstring));
    $achievementlist->appendChild($goals);

    // Interests.
    $interests = europass_xml_achievement($dom, 'intersts', array($resume->id), get_string('resume_interests', 'block_exaport'),
            clean_for_external_xml($resume->interests));
    $achievementlist->appendChild($interests);

    $learnerinfo->appendChild($achievementlist);

    // All attached files IDs.
    $filearray = array_keys($attachedfilenames);
    $documentation = europass_xml_documentation_list($dom, $filearray);
    if ($documentation) {
        $learnerinfo->appendChild($documentation);
    };

    $root->appendChild($learnerinfo);

    // Attachment files.
    if (count($attachedfilenames) > 0) {
        $attachmentlist = $dom->createElement('AttachmentList');
        foreach ($attachedfilenames as $id => $filename) {
            $attachment = $dom->createElement('Attachment');
            $attachment->setAttribute('id', $id);
            // Name.
            $name = $dom->createElement('Name');
            $text = $dom->createTextNode($filename);
            $name->appendChild($text);
            $attachment->appendChild($name);
            // Mimetype.
            $mimetype = $dom->createElement('MimeType');
            $text = $dom->createTextNode($attachedfilemimetypes[$id]);
            $mimetype->appendChild($text);
            $attachment->appendChild($mimetype);
            // Data.
            $data = $dom->createElement('Data');
            $text = $dom->createTextNode($attachedfiledatas[$id]);
            $data->appendChild($text);
            $attachment->appendChild($data);
            // Description.
            $description = $dom->createElement('Description');
            $text = $dom->createTextNode($filename);
            $description->appendChild($text);
            $attachment->appendChild($description);
            $attachmentlist->appendChild($attachment);
        };
        $root->appendChild($attachmentlist);
    }

    // Cover. Insert the Cover letter from the exaport to the main content of the europass cover.
    $coverletter = $dom->createElement('CoverLetter');
    $letter = $dom->createElement('Letter');
    $body = $dom->createElement('Body');
    $mainbody = $dom->createElement('MainBody');
    $text = $dom->createTextNode(clean_for_external_xml($resume->cover));
    $mainbody->appendChild($text);
    $body->appendChild($mainbody);
    $letter->appendChild($body);
    $coverletter->appendChild($letter);
    $root->appendChild($coverletter);

    $dom->appendChild($root);
    $dom->formatOutput = true;
    $xml .= $dom->saveXML();

    // Save to file for development.
    /* $strXML = $xml; file_put_contents('d:/incom/testXML.xml', $strXML); */
    return $xml;
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

// Xml for educations and employers.
function europass_xml_employers_educations($dom, $type, $data) {
    global $USER;
    switch ($type) {
        case 'WorkExperience':
            $orgname = 'employer';
            $orgaddress = 'employeraddress';
            $activitiesfield = 'positiondescription';
            break;
        case 'Education':
            $orgname = 'institution';
            $orgaddress = 'institutionaddress';
            $activitiesfield = 'qualdescription';
            break;
    }

    $experiencelist = $dom->createElement($type.'List');
    foreach ($data as $id => $dataitem) {
        $experienceitem = $dom->createElement($type);
        // Period.
        $period = $dom->createElement('Period');
        $from = $dom->createElement('From');
        $datearr = get_date_params_from_string($dataitem->startdate);
        foreach ($datearr as $param => $value) {
            $from->setAttribute($param, $value);
        }
        $period->appendChild($from);
        $textcurrent = $dom->createTextNode(clean_for_external_xml('true'));
        if ($dataitem->enddate <> '') {
            $to = $dom->createElement('To');
            $datearr = get_date_params_from_string($dataitem->enddate);
            foreach ($datearr as $param => $value) {
                $to->setAttribute($param, $value);
            }
            $period->appendChild($to);
            $textcurrent = $dom->createTextNode(clean_for_external_xml('false'));
        };
        $current = $dom->createElement('Current');
        $current->appendChild($textcurrent);
        $period->appendChild($current);
        $experienceitem->appendChild($period);
        // Position.
        if ($type == 'WorkExperience') {
            $position = $dom->createElement('Position');
            $label = $dom->createElement('Label');
            $text = $dom->createTextNode($dataitem->jobtitle);
            $label->appendChild($text);
            $position->appendChild($label);
            $position->appendChild($label);
            $experienceitem->appendChild($position);
        } else if ($type == 'Education') {
            $title = $dom->createElement('Title');
            $text = $dom->createTextNode($dataitem->qualname);
            $title->appendChild($text);
            $experienceitem->appendChild($title);
        };
        // Activities.
        if ($dataitem->{$activitiesfield} <> '') {
            $activities = $dom->createElement('Activities');
            $text = $dom->createTextNode($dataitem->{$activitiesfield});
            $activities->appendChild($text);
            $experienceitem->appendChild($activities);
        };
        // Employer.
        if ($type == 'WorkExperience') {
            $organisationxml = $dom->createElement('Employer');
        } else if ($type == 'Education') {
            $organisationxml = $dom->createElement('Organisation');
        }
        // Organisation name.
        $name = $dom->createElement('Name');
        $text = $dom->createTextNode($dataitem->{$orgname});
        $name->appendChild($text);
        $organisationxml->appendChild($name);
        // Employer contacts.
        if ($dataitem->{$orgaddress} <> '') {
            $contactinfo = $dom->createElement('ContactInfo');
            // Address info.
            $address = $dom->createElement('Address');
            $contact = $dom->createElement('Contact');
            $addressline = $dom->createElement('AddressLine');
            $text = $dom->createTextNode($dataitem->{$orgaddress});
            $addressline->appendChild($text);
            $contact->appendChild($addressline);
            $address->appendChild($contact);
            $contactinfo->appendChild($address);
            $organisationxml->appendChild($contactinfo);
        };
        $experienceitem->appendChild($organisationxml);
        // Attached files.
        switch ($type) {
            case 'WorkExperience':
                $filearea = 'resume_employ';
                break;
            case 'Education':
                $filearea = 'resume_edu';
                break;
        };
        $fs = get_file_storage();
        $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', $filearea, $dataitem->id, 'filename',
                false);
        if (count($files) > 0) {
            $filearray = europass_xml_get_attached_file_contents($files);
            if (count($filearray) > 0) {
                $documentation = europass_xml_documentation_list($dom, $filearray);
                if ($documentation) {
                    $experienceitem->appendChild($documentation);
                }
            }
        };
        $experiencelist->appendChild($experienceitem);
    };
    return $experiencelist;
}

// Single Achievement for achievementlist.
function europass_xml_achievement($dom, $type, $ids = array(), $atitle, $content) {
    global $USER;
    $files = array();
    $fs = get_file_storage();
    $achievement = $dom->createElement('Achievement');
    $title = $dom->createElement('Title');
    $label = $dom->createElement('Label');
    $text = $dom->createTextNode($atitle);
    $label->appendChild($text);
    $title->appendChild($label);
    $achievement->appendChild($title);
    $description = $dom->createElement('Description');
    $text = $dom->createTextNode($content);
    $description->appendChild($text);
    $achievement->appendChild($description);
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
        $filearray = europass_xml_get_attached_file_contents($files);
        if (count($filearray)) {
            $documentation = europass_xml_documentation_list($dom, $filearray);
            if ($documentation) {
                $achievement->appendChild($documentation);
            }
        }
    };
    return $achievement;
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
                $itemsstring .= ' ('.$item->date.')';
                $itemsstring .= ($item->description ? ". " : "").$item->description;
                break;
            case 'block_exaportresume_public':
                $itemsstring .= $item->title;
                $itemsstring .= ' ('.$item->date.'). ';
                $itemsstring .= $item->contribution;
                $itemsstring .= ($item->contributiondetails ? ": " : "").$item->contributiondetails;
                break;
            case 'block_exaportresume_mbrship':
                $itemsstring .= $item->title;
                $itemsstring .= ' ('.$item->startdate.($item->enddate ? "-".$item->enddate : "").')';
                $itemsstring .= ($item->description ? ". " : "").$item->description;
                break;
            default:
                $itemsstring .= '';
        };

        $itemsstring .= '</li>';
    }
    $itemsstring .= '</ul>';
    return array($itemsstring, $itemsids);
}

// Fill global arrays with
// fileid => filecontent
// fileid => mimetype
// fileid => filename.
function europass_xml_get_attached_file_contents($files) {
    global $attachedfilenames, $attachedfiledatas, $attachedfilemimetypes;
    $arrayids = array();
    $chars = '123456789';
    $numchars = strlen($chars);
    foreach ($files as $file) {
        $fmimetype = $file->get_mimetype();
        if (($fmimetype == 'application/pdf' || $fmimetype == 'image/jpeg' || $fmimetype == 'image/png') &&
                $file->get_filesize() <= 2560000) {
            // Random ID.
            $id = 'ATT_';
            for ($i = 0; $i < 13; $i++) {
                $id .= substr($chars, rand(1, $numchars) - 1, 1);
            };
            $attachedfilemimetypes[$id] = $fmimetype;
            $attachedfiledatas[$id] = base64_encode($file->get_content());
            $attachedfilenames[$id] = $file->get_filename();
            $arrayids[] = $id;
        };
    };
    return $arrayids;
}

// Get XML for documentations (attached to item).
function europass_xml_documentation_list($dom, $filearray) {
    if (count($filearray) > 0) {
        $documentation = $dom->createElement('Documentation');
        foreach ($filearray as $fileid) {
            $referenceto = $dom->createElement('ReferenceTo');
            $referenceto->setAttribute('idref', $fileid);
            $documentation->appendChild($referenceto);
        };
        return $documentation;
    };
    return null;
};
