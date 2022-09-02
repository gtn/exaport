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
//    $src = '/pluginfile.php/'.context_user::instance($badge->usercreated)->id.'/badges/userbadge/'.$badge->id.'/'.
//            $badge->uniquehash;
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
    $img = '<img src="'.$src.'" style="float: left; margin: 0px 10px;">';
    return $img;
}

function europass_xml($resumeid = 0) {
    global $USER, $DB, $SITE, $CFG;
    global $attachedfilenames, $attachedfiledatas, $attachedfilemimetypes;
    $xml = '';
    $resume = $DB->get_record('block_exaportresume', array("id" => $resumeid, 'user_id' => $USER->id));

    $languageCode = 'en';
    $schemeID = 'exaportTest-0001';
    $schemeName = 'DocumentIdentifier';
    $schemeAgencyName = 'EUROPASS';
    $schemeVersionID = '4.0';

    $dom = new DOMDocument('1.0', 'utf-8');
    $root = $dom->createElement('Candidate');
    $root->setAttribute('xsi:schemaLocation', 'http://www.europass.eu/1.0 Candidate.xsd');
    $root->setAttribute('xmlns', 'http://www.europass.eu/1.0');


    $root->setAttribute('xmlns:oa', 'http://www.openapplications.org/oagis/9');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttribute('xmlns:eures', 'http://www.europass_eures.eu/1.0');
    $root->setAttribute('xmlns:hr', 'http://www.hr-xml.org/3');
//    $root->setAttribute('majorVersionID', '3');
//    $root->setAttribute('minorVersionID', '2');

    // document ID
    $documentID = $dom->createElement('hr:DocumentID');
    $documentID->setAttribute('schemeID', $schemeID);
    $documentID->setAttribute('schemeName', $schemeName);
    $documentID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $documentID->setAttribute('schemeVersionID', $schemeVersionID);

    // supplier
    $CandidateSupplier = $dom->createElement('CandidateSupplier');
    $PartyID = $dom->createElement('hr:PartyID');
    $PartyID->setAttribute('schemeID', $schemeID);
    $PartyID->setAttribute('schemeName', 'PartyID');
    $PartyID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $PartyID->setAttribute('schemeVersionID', '1.0');
    $CandidateSupplier->appendChild($PartyID);
    $PartyName = $dom->createElement('hr:PartyName');
    $text = $dom->createTextNode($SITE->fullname.': Exabis ePortfolio CV');
    $PartyName->appendChild($text);
    $CandidateSupplier->appendChild($PartyName);
    $root->appendChild($CandidateSupplier);

    // candidate
    $CandidatePerson = $dom->createElement('CandidatePerson');
    // name
    $PersonName = $dom->createElement('PersonName');
    $GivenName = $dom->createElement('oa:GivenName');
    $text = $dom->createTextNode($USER->firstname);
    $GivenName->appendChild($text);
    $FamilyName = $dom->createElement('hr:FamilyName');
    $text = $dom->createTextNode($USER->lastname);
    $FamilyName->appendChild($text);
    $PersonName->appendChild($GivenName);
    $PersonName->appendChild($FamilyName);
    $CandidatePerson->appendChild($PersonName);
    // contact data
    $Communication = $dom->createElement('Communication');
    $UseCode = $dom->createElement('UseCode');
    $text = $dom->createTextNode('home');
    $UseCode->appendChild($text);
    $Communication->appendChild($UseCode);
    $Address = $dom->createElement('Address');
    $Address->setAttribute('type', 'home');
    $Communication->appendChild($Address);
//    echo "<pre>debug:<strong>resumelib.php:827</strong>\r\n"; print_r($USER); echo '</pre>'; exit; // !!!!!!!!!! delete it
    europassFillSubElementText($dom, $Address, 'oa:AddressLine', $USER->address);
    europassFillSubElementText($dom, $Address, 'oa:CityName', $USER->city);
    europassFillSubElementText($dom, $Address, 'CountryCode', strtolower($USER->country));
    $CandidatePerson->appendChild($Communication);

    // for phone numbers we need to know country code and phone number. So, use this code
    $phoneTypes = ['home', 'mobile'];
    $numb = [1, 2];
    $i = 0;
    foreach ($numb as $n) {
        if ($USER->{'phone'.$n}) {
            preg_match("~^(?:(?:\(?(?:00|\+)([1-4]\d\d|[1-9]\d?)\)?)?[\-\.\ \\\/]?)?((?:\(?\d{1,}\)?[\-\.\ \\\/]?){0,})(?:[\-\.\ \\\/]?(?:#|ext\.?|extension|x)[\-\.\ \\\/]?(\d+))?$~i", $USER->{'phone'.$n}, $matches);
            $countryCode = @$matches[1];
            $phoneNumber = @$matches[2];
            if ($phoneNumber) {
                if ($countryCode && $countryCode > 0) { // get default country code from Moodle settings
                    europassFillCommunicationItem($dom, $CandidatePerson, 'Telephone', ['oa:DialNumber' => $phoneNumber, 'UseCode' => $phoneTypes[$i++], 'CountryDialing' => $countryCode]);
                } else if ($CFG->country) {
                    // todo: find phone code by country code?
//                    europassFillCommunicationItem($dom, $CandidatePerson, 'Telephone', ['oa:DialNumber' => $phoneNumber, 'UseCode' => $phoneTypes[$i++], 'CountryCode' => strtolower($CFG->country)]);
                }

            }
        }
    }
    europassFillCommunicationItem($dom, $CandidatePerson, 'Email', ['oa:URI' => $USER->email]);
    europassFillCommunicationItem($dom, $CandidatePerson, 'Web', ['oa:URI' => $USER->url]);
    europassFillCommunicationItem($dom, $CandidatePerson, 'InstantMessage', ['oa:URI' => $USER->icq, 'OtherTitle' => 'ICQ', 'UseCode' => 'other']);
    europassFillCommunicationItem($dom, $CandidatePerson, 'InstantMessage', ['oa:URI' => $USER->skype, 'OtherTitle' => 'Skype', 'UseCode' => 'other']);
    europassFillCommunicationItem($dom, $CandidatePerson, 'InstantMessage', ['oa:URI' => $USER->yahoo, 'OtherTitle' => 'Yahoo', 'UseCode' => 'other']);
    europassFillCommunicationItem($dom, $CandidatePerson, 'InstantMessage', ['oa:URI' => $USER->aim, 'OtherTitle' => 'AIM', 'UseCode' => 'other']);
    europassFillCommunicationItem($dom, $CandidatePerson, 'InstantMessage', ['oa:URI' => $USER->msn, 'OtherTitle' => 'MSN', 'UseCode' => 'other']);

    europassFillSubElementText($dom, $CandidatePerson, 'ResidenceCountryCode', strtolower($USER->country));
    $root->appendChild($CandidatePerson);

    // CandidateProfile
    $CandidateProfile = $dom->createElement('CandidateProfile');
    $CandidateProfile->setAttribute('languageCode', 'en');
    $ID = $dom->createElement('hr:ID');
    $ID->setAttribute('schemeID', $schemeID);
    $ID->setAttribute('schemeName', 'CandidateProfileID');
    $ID->setAttribute('schemeAgencyName', $schemeAgencyName);
    $ID->setAttribute('schemeVersionID', '1.0');
    $CandidateProfile->appendChild($ID);

    // user picture.
    $fs = get_file_storage();
    $imgTypes = array('png', 'jpg', 'jpeg');
    $i = 0;
    do {
        $file = $fs->get_file(context_user::instance($USER->id)->id, 'user', 'icon', 0, '/', 'f3.'.$imgTypes[$i]);
        $i++;
    } while (!$file && $i < count($imgTypes));
    if ($file) {
        $base64content = base64_encode('data:'.$file->get_mimetype().';base64,'.base64_encode($file->get_content())); // double encoding!!!!!
        europassAddAttachment($dom, $CandidateProfile, $base64content, 'photo', 'ProfilePicture');
    };

    // personal information
    $ExecutiveSummary = $dom->createElement('hr:ExecutiveSummary');
    $ExecutiveSummary->appendChild($dom->createTextNode(clean_html_to_plain_text($resume->cover)));
    $CandidateProfile->appendChild($ExecutiveSummary);

    // WorkExperienceList / Employment history.
    $employments = $DB->get_records('block_exaportresume_employ', array("resume_id" => $resume->id), 'sorting');
//    $workexperiencelist = europass_xml_employers_educations($dom, 'WorkExperience', $resume->employments);
    $EmploymentHistory = $dom->createElement('EmploymentHistory');
    foreach ($employments as $employment) {
        $EmployerHistory = $dom->createElement('EmployerHistory');
        // title
        $OrganizationName = $dom->createElement('hr:OrganizationName');
        $text = $dom->createTextNode(clean_for_external_xml($employment->employer));
        $OrganizationName->appendChild($text);
        $EmployerHistory->appendChild($OrganizationName);
        // address
        $address = clean_for_external_xml($employment->employeraddress);
        $OrganizationContact = $dom->createElement('OrganizationContact');
        $Communication = $dom->createElement('Communication');
        $Address = $dom->createElement('Address');
        $text = $dom->createTextNode($address);
        $Address->appendChild($text);
        $Communication->appendChild($Address);
        $OrganizationContact->appendChild($Communication);
        $EmployerHistory->appendChild($OrganizationContact);

        $PositionHistory = $dom->createElement('PositionHistory');
        $PositionTitle = $dom->createElement('PositionTitle');
        $PositionTitle->setAttribute('typeCode', 'FREETEXT');
        $text = $dom->createTextNode(clean_for_external_xml($employment->jobtitle));
        $PositionTitle->appendChild($text);
        $PositionHistory->appendChild($PositionTitle);
        $EmploymentPeriod = $dom->createElement('eures:EmploymentPeriod');
        // start date
        $date = getEuropassDate($employment->startdate);
        if ($date) {
            $StartDate = $dom->createElement('eures:StartDate');
            $FormattedDateTime = $dom->createElement('hr:FormattedDateTime');
            $text = $dom->createTextNode($date);
            $FormattedDateTime->appendChild($text);
            $StartDate->appendChild($FormattedDateTime);
            $EmploymentPeriod->appendChild($StartDate);
        }
        // end date
        if ($employment->enddate) {
            $date = getEuropassDate($employment->enddate);
            if ($date) {
                $EndDate = $dom->createElement('eures:EndDate');
                $FormattedDateTime = $dom->createElement('hr:FormattedDateTime');
                $text = $dom->createTextNode($date);
                $FormattedDateTime->appendChild($text);
                $EndDate->appendChild($FormattedDateTime);
                $EmploymentPeriod->appendChild($EndDate);
            }
            $current = 'false';
        } else {
            $current = 'true';
        }
        // current
        $CurrentIndicator = $dom->createElement('hr:CurrentIndicator');
        $text = $dom->createTextNode($current);
        $CurrentIndicator->appendChild($text);
        $EmploymentPeriod->appendChild($CurrentIndicator);
        $PositionHistory->appendChild($EmploymentPeriod);
        // description
        $Description = $dom->createElement('oa:Description');
        $text = $dom->createTextNode(clean_for_external_xml($employment->positiondescription));
        $Description->appendChild($text);
        $PositionHistory->appendChild($Description);

        $EmployerHistory->appendChild($PositionHistory);

        $EmploymentHistory->appendChild($EmployerHistory);
    }
    $CandidateProfile->appendChild($EmploymentHistory);

    // EducationList / Education history.
    $educations = $DB->get_records('block_exaportresume_edu', array("resume_id" => $resume->id), 'sorting');
//    [id] => 1
//            [resume_id] => 1
//            [startdate] => marth 2010
//            [enddate] => april 2010
//            [institution] => Education 1
//            [institutionaddress] => address1
//    [qualtype] => type1
//    [qualname] => my title name1
//    [qualdescription] => description of qualification 1
//            [sorting] => 10
    $EducationHistory = $dom->createElement('EducationHistory');
    foreach ($educations as $education) {
        $EducationOrganizationAttendance = $dom->createElement('EducationOrganizationAttendance');
        // title
        $OrganizationName = $dom->createElement('hr:OrganizationName');
        $text = $dom->createTextNode(clean_for_external_xml($education->institution));
        $OrganizationName->appendChild($text);
        $EducationOrganizationAttendance->appendChild($OrganizationName);
        // address
        $address = clean_for_external_xml($education->institutionaddress);
        $OrganizationContact = $dom->createElement('OrganizationContact');
        $Communication = $dom->createElement('Communication');
        $Address = $dom->createElement('Address');
        $text = $dom->createTextNode($address);
        $Address->appendChild($text);
        $Communication->appendChild($Address);
        $OrganizationContact->appendChild($Communication);
        $EducationOrganizationAttendance->appendChild($OrganizationContact);

        $EducationDegree = $dom->createElement('EducationDegree');
        $DegreeName = $dom->createElement('hr:DegreeName');
        $text = $dom->createTextNode(clean_for_external_xml($education->qualtype));
        $DegreeName->appendChild($text);
        $EducationDegree->appendChild($DegreeName);

        $FinalGrade = $dom->createElement('FinalGrade');
        $ScoreText = $dom->createElement('hr:ScoreText');
        $text = $dom->createTextNode(clean_for_external_xml($education->qualname));
        $ScoreText->appendChild($text);
        $FinalGrade->appendChild($ScoreText);
        $EducationDegree->appendChild($FinalGrade);

        $OccupationalSkillsCovered = $dom->createElement('OccupationalSkillsCovered');
        $text = $dom->createTextNode(clean_for_external_xml($education->qualdescription));
        $OccupationalSkillsCovered->appendChild($text);
        $EducationDegree->appendChild($OccupationalSkillsCovered);
        $EducationOrganizationAttendance->appendChild($EducationDegree);

        $AttendancePeriod = $dom->createElement('AttendancePeriod');
        // start date
        $date = getEuropassDate($education->startdate);
        if ($date) {
            $StartDate = $dom->createElement('StartDate');
            $FormattedDateTime = $dom->createElement('hr:FormattedDateTime');
            $text = $dom->createTextNode($date);
            $FormattedDateTime->appendChild($text);
            $StartDate->appendChild($FormattedDateTime);
            $AttendancePeriod->appendChild($StartDate);
        }
        // end date
        if ($education->enddate) {
            $date = getEuropassDate($education->enddate);
            if ($date) {
                $EndDate = $dom->createElement('EndDate');
                $FormattedDateTime = $dom->createElement('hr:FormattedDateTime');
                $text = $dom->createTextNode($date);
                $FormattedDateTime->appendChild($text);
                $EndDate->appendChild($FormattedDateTime);
                $AttendancePeriod->appendChild($EndDate);
            }
            $current = 'false';
        } else {
            $current = 'true';
        }
        // current
        $Ongoing = $dom->createElement('Ongoing');
        $text = $dom->createTextNode($current);
        $Ongoing->appendChild($text);
        $AttendancePeriod->appendChild($Ongoing);
        $EducationOrganizationAttendance->appendChild($AttendancePeriod);

        $EducationHistory->appendChild($EducationOrganizationAttendance);

    }
    $CandidateProfile->appendChild($EducationHistory);

    // Skills
    // skills - Career skills
    if ($resume->skillscareers) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillscareers'), $resume->skillscareers);
    }
    // skills - Academic skills
    if ($resume->skillsacademic) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillsacademic'), $resume->skillsacademic);
    }
    // skills - Personal skills
    if ($resume->skillspersonal) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillspersonal'), $resume->skillspersonal);
    }
    // skills - Educational standards
    $skillscontent = '';
    $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $resume->id, "comptype" => 'skills'));
    foreach ($competences as $competence) {
        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), $fields = '*', $strictness = IGNORE_MISSING);
        if ($competencesdb != null) {
            $skillscontent .= '<p>'.$competencesdb->title.'</p>';
        };
    };
    if ($skillscontent) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_skills'), block_exaport_get_string('resume_skillscomp'), $skillscontent);
    }

    // certificates
    $certifications = $DB->get_records('block_exaportresume_certif', array("resume_id" => $resume->id), 'sorting');
    if ($certifications && is_array($certifications)) {
//        list($sertificationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_certif');
//        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_certif'), '', $sertificationsstring);
        $Certifications = $dom->createElement('Certifications');
        foreach ($certifications as $certification) {
            $CertificationNode = $dom->createElement('Certification');
            $CertificationName = $dom->createElement('hr:CertificationName');
            $text = $dom->createTextNode($certification->title);
            $CertificationName->appendChild($text);
            $CertificationNode->appendChild($CertificationName);
            $date = getEuropassDate($certification->date);
            if ($date) {
                $FirstIssuedDate = $dom->createElement('eures:FirstIssuedDate');
                $FormattedDateTime = $dom->createElement('hr:FormattedDateTime');
                $text = $dom->createTextNode($date);
                $FormattedDateTime->appendChild($text);
                $FirstIssuedDate->appendChild($FormattedDateTime);
                $CertificationNode->appendChild($FirstIssuedDate);
            }
            $Description = $dom->createElement('oa:Description');
            $text = $dom->createTextNode($certification->description);
            $Description->appendChild($text);
            $CertificationNode->appendChild($Description);
            // attachment
            europass_xml_attachFile($dom, $CandidateProfile, $CertificationNode, 'certif', [$certification->id], 'DOC');
            $Certifications->appendChild($CertificationNode);
        }
        $CandidateProfile->appendChild($Certifications);
    }

    // Books, publications.
    $publications = $DB->get_records('block_exaportresume_public', array("resume_id" => $resume->id), 'sorting');
    if ($publications && is_array($publications)) {
//        list($publicationsstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_public');
//        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_public'), '', $publicationsstring);
        $PublicationHistory = $dom->createElement('PublicationHistory');
        foreach ($publications as $publication) {
            $PublicationNode = $dom->createElement('Publication');
            $FormattedPublicationDescription = $dom->createElement('hr:FormattedPublicationDescription');
            $text = $dom->createTextNode(clean_for_external_xml($publication->contributiondetails));
            $FormattedPublicationDescription->appendChild($text);
            $PublicationNode->appendChild($FormattedPublicationDescription);
            $Title = $dom->createElement('Title');
            $text = $dom->createTextNode(clean_for_external_xml($publication->title));
            $Title->appendChild($text);
            $PublicationNode->appendChild($Title);
            $Reference = $dom->createElement('Reference');
            $text = $dom->createTextNode(clean_for_external_xml($publication->contribution));
            $Reference->appendChild($text);
            $PublicationNode->appendChild($Reference);
            $date = getEuropassDate($publication->date, 'Y');
            if ($date) {
                $Year = $dom->createElement('Year');
                $text = $dom->createTextNode($date);
                $Year->appendChild($text);
                $PublicationNode->appendChild($Year);
            }
            if ($publication->url) {
                $DOI = $dom->createElement('DOI');
                $Link = $dom->createElement('Link');
                $text = $dom->createTextNode($publication->url);
                $Link->appendChild($text);
                $DOI->appendChild($Link);
                $PublicationNode->appendChild($DOI);
            }
            europass_xml_attachFile($dom, $CandidateProfile, $PublicationNode, 'public', [$publication->id], 'DOC');
            $PublicationHistory->appendChild($PublicationNode);
        }
        $CandidateProfile->appendChild($PublicationHistory);
    }

    // Memberships.
    list($mbrshipstring, $elementids) = list_for_resume_elements($resume->id, 'block_exaportresume_mbrship');
    if ($mbrshipstring) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_mbrship'), '', $mbrshipstring);
        // europass_xml_attachFile($dom, $CandidateProfile, $PublicationNode, 'public', [$publication->id], 'DOC'); files?
    }
    // Goals.
    // goals - Personal goals
    if ($resume->goalspersonal) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalspersonal'), $resume->goalspersonal);
    }
    // goals - Academic goals
    if ($resume->goalsacademic) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalsacademic'), $resume->goalsacademic);
    }
    // goals - Careers goals
    if ($resume->goalscareers) {
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalscareers'), $resume->goalscareers);
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
        europassAddOthersPartToCandiadateProfile($dom, $CandidateProfile, block_exaport_get_string('resume_goals'), block_exaport_get_string('resume_goalscomp'), $goalsstring);
    }

    // Interests.
    if ($resume->interests) {
        $HobbiesAndInterests = $dom->createElement('HobbiesAndInterests');
        $HobbyOrInterest = $dom->createElement('HobbyOrInterest');
        $Title = $dom->createElement('Title');
        $text = $dom->createTextNode(block_exaport_get_string('resume_interests'));
        $Title->appendChild($text);
        $HobbyOrInterest->appendChild($Title);
        $Description = $dom->createElement('Description');
        $text = $dom->createTextNode($resume->interests);
        $Description->appendChild($text);
        $HobbyOrInterest->appendChild($Description);
        $HobbiesAndInterests->appendChild($HobbyOrInterest);
        $CandidateProfile->appendChild($HobbiesAndInterests);
    }

    $root->appendChild($CandidateProfile);

    $dom->appendChild($root);
    $dom->formatOutput = true;
    $xml .= $dom->saveXML();

    // Save to file for development.
    /* $strXML = $xml; file_put_contents('d:/incom/testXML.xml', $strXML); */
    return $xml;
}

function europassAddOthersPartToCandiadateProfile(&$dom, &$candidateProfile, $sectionTitle, $title, $description) {
    $Others = $dom->createElement('Others');
    $Title = $dom->createElement('Title');
    $text = $dom->createTextNode($sectionTitle);
    $Title->appendChild($text);
    $Others->appendChild($Title);
    $Other = $dom->createElement('Other');
    if ($title) {
        $Title = $dom->createElement('Title');
        $text = $dom->createTextNode($title);
        $Title->appendChild($text);
        $Other->appendChild($Title);
    }
    $DescriptionNode = $dom->createElement('Description');
    $text = $dom->createTextNode($description);
    $DescriptionNode->appendChild($text);
    $Other->appendChild($DescriptionNode);

    $Others->appendChild($Other);

    $candidateProfile->appendChild($Others);
}

function getEuropassDate($stringDate, $format = 'Y-m-d') {
    try {
        $date = new \DateTime($stringDate);
        $date = $date->format($format);
    } catch (\Exception $e) {
        $date = '';
    }
    return $date;
}

function europassAddAttachment(&$dom, &$candidateProfile, $fileContent, $fileType, $instructions, $filename = '', $description = '', $documentTitle = '', $mimecode = '') {
    $attachment = $dom->createElement('eures:Attachment');

    $fileContentNode = $dom->createElement('oa:EmbeddedData');
    if ($mimecode) {
        $fileContentNode->setAttribute('mimeCode', $mimecode);
    }
    if ($filename) {
        $fileContentNode->setAttribute('filename', $filename);
    }
    $text = $dom->createTextNode($fileContent);
    $fileContentNode->appendChild($text);
    $attachment->appendChild($fileContentNode);

    $fileTypeNode = $dom->createElement('oa:FileType');
    $text = $dom->createTextNode($fileType);
    $fileTypeNode->appendChild($text);
    $attachment->appendChild($fileTypeNode);

    $instructionsNode = $dom->createElement('hr:Instructions');
    $text = $dom->createTextNode($instructions);
    $instructionsNode->appendChild($text);
    $attachment->appendChild($instructionsNode);

    $additionalParams = array(
        'filename' => 'oa:FileName',
        'description' => 'oa:Description',
        'documentTitle' => 'hr:DocumentTitle',
    );
    foreach ($additionalParams as $param => $nodeName) {
        if (${''.$param}) {
            $node = $dom->createElement($nodeName);
            $text = $dom->createTextNode(${''.$param});
            $node->appendChild($text);
            $attachment->appendChild($node);
        }
    }

    $candidateProfile->appendChild($attachment);
}

function europassFillCommunicationItem(&$dom, &$parenNode, $channelCode, $nodes) {
    $inserted = false;
    $Communication = $dom->createElement('Communication'); // new Communicate node!
    $i = 0;
    foreach ($nodes as $nodeName => $value) {
        if ($value) {
            $node = $dom->createElement($nodeName);
            $text = $dom->createTextNode($value);
            $node->appendChild($text);
            $Communication->appendChild($node);
            $i++;
        }
    }
    if ($i == count($nodes)) {
        $inserted = true; // all nodes must have values
    }
    if ($inserted) {
        $node = $dom->createElement('ChannelCode');
        $text = $dom->createTextNode($channelCode);
        $node->appendChild($text);
        $Communication->appendChild($node);
        $parenNode->appendChild($Communication);
    }
}

function europassFillSubElementText(&$dom, &$parenNode, $nodeName, $value) {
    if ($value) {
        $node = $dom->createElement($nodeName);
        $text = $dom->createTextNode($value);
        $node->appendChild($text);
        $parenNode->appendChild($node);
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

// Attachment files
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
function europass_xml_attachFile(&$dom, &$candidateProfile, &$parentNode, $type, $ids = array(), $instructions = 'ProfilePicture') {
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

/*        if ($candidateProfile->getElementsByTagName("eures:Attachment")->length == 0) {
            $Attachment = $dom->createElement('eures:Attachment');
            $candidateProfile->appendChild($Attachment);
        } else {
            $Attachment = $candidateProfile->getElementsByTagName('eures:Attachment')[0];
        }*/

        foreach ($files as $file) {
            $fileNameID = '';

            if ($parentNode) {
                // Insert reference to the Parent node
                $AttachmentReference = $dom->createElement('eures:AttachmentReference');
                $Description = $dom->createElement('oa:Description');
                $text = $dom->createTextNode('1111111111');
                $Description->appendChild($text);
                $AttachmentReference->appendChild($Description);

                $XPath = $dom->createElement('hr:XPath');
                $randStr = substr(str_shuffle(MD5(microtime())), 0, 5);
                $fileNameID = $randStr . '_' . $file->get_filename();
                $xpathFull = '/Candidate/CandidateProfile/Attachment/oa:FileName[text()=\'' . $fileNameID . '\']';
                $text = $dom->createTextNode($xpathFull);
                $XPath->appendChild($text);
                $AttachmentReference->appendChild($XPath);
                $parentNode->appendChild($AttachmentReference);
            }

            // insert attachment main data
            $Attachment = $dom->createElement('eures:Attachment');

            $EmbeddedData = $dom->createElement('oa:EmbeddedData');
            $EmbeddedDataContent = base64_encode($file->get_content());
            $EmbeddedDataContentNode = $dom->createTextNode($EmbeddedDataContent);
            $EmbeddedDataContentNode->appendChild($EmbeddedDataContentNode);
            $EmbeddedData->appendChild($EmbeddedDataContentNode);
            $Attachment->appendChild($EmbeddedData);

            $FileType = $dom->createElement('oa:FileType');
            $text = $dom->createTextNode('DOC');
            $FileType->appendChild($text);
            $Attachment->appendChild($FileType);

            $Instructions = $dom->createElement('hr:Instructions');
            $text = $dom->createTextNode($instructions);
            $Instructions->appendChild($text);
            $Attachment->appendChild($Instructions);

            if ($fileNameID) {
                $FileName = $dom->createElement('oa:FileName');
                $text = $dom->createTextNode($fileNameID);
                $FileName->appendChild($text);
                $Attachment->appendChild($FileName);
            }

            $candidateProfile->appendChild($Attachment);
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