<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 exabis internet solutions <info@exabis.at>
 *  All rights reserved
 *
 *  You can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This module is based on the Collaborative Moodle Modules from
 *  NCSA Education Division (http://www.ncsa.uiuc.edu)
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

require_once dirname(__FILE__) . '/inc.php';
require_once dirname(__FILE__) . '/lib/sharelib.php';
require_once dirname(__FILE__) . '/lib/edit_form.php';
require_once dirname(__FILE__) . '/lib/minixml.inc.php';
require_once dirname(__FILE__) . '/lib/class.scormparser.php';

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('block/exaport:use', $context);
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}//require_capability('block/exaport:import', $context);

$url = '/blocks/exaport/import_file.php';
$PAGE->set_url($url);
block_exaport_print_header("exportimportimport");

//$exteditform = new block_exaport_import_scorm_form();

$strimport = get_string("import", "block_exaport");

$exteditform = new block_exaport_scorm_upload_form(null, null);


$imported = false;
$returnurl = $CFG->wwwroot . '/blocks/exaport/exportimport.php?courseid=' . $courseid;

////////
if ($exteditform->is_cancelled()) {
    redirect($returnurl);
} else if ($exteditform->no_submit_button_pressed()) {
    die("nosubmitbutton");
    //no_submit_button_actions($exteditform, $sitecontext);
} else if ($fromform = $exteditform->get_data()) {
    $imported = true;
    $dir = make_upload_directory(import_file_area_name());
    $zipcontent = $exteditform->get_file_content('attachment');
    if (file_put_contents($dir."/".$exteditform->get_new_filename('attachment'), $zipcontent) && $newfilename = $exteditform->get_new_filename('attachment')) {
        if (ereg("^(.*).zip$", $newfilename, $regs)) {
            if ($scormdir = make_upload_directory(import_file_area_name())) {
                $unzip_dir = $scormdir . '/' . $regs[1];

                if (is_dir($unzip_dir)) {
                    $i = 0;
                    do {
                        $i++;
                        $unzip_dir = $scormdir . '/' . $regs[1] . $i;
                    } while (is_dir($unzip_dir));
                }

                if (mkdir($unzip_dir)) {
                    if (unzip_file($dir . '/' . $newfilename, $unzip_dir, false)) {
                        // parsing of file
                        $scormparser = new SCORMParser();
                        $scormTree = $scormparser->parse($unzip_dir . '/imsmanifest.xml');

                        // write warnings and errors
                        if ($scormparser->isWarning()) {
                            error($scormparser->getWarning());
                        } else if ($scormparser->isError()) {
                            error($scormparser->getError());
                        } else {
                            foreach ($scormTree as $organization) {
                                switch ($organization["data"]["identifier"]) {
                                    case "DATA": if (isset($organization["items"][0]["data"]["url"])) {
                                            $filepath = $unzip_dir . '/' . clean_param($organization["items"][0]["data"]["url"], PARAM_PATH);
                                            if (is_file($filepath)) {
                                                import_user_description($filepath);
                                            }
                                        }
                                        break;
                                    case "PORTFOLIO": import_structure($unzip_dir, $organization["items"], $course);
                                        break;
                                    default: import_files($unzip_dir, $organization["items"]);
                                        break;
                                }
                            }
                        }
                    } else {
                        error(get_string("couldntextractscormfile", "block_exaport"));
                    }
                } else {
                    error(get_string("couldntcreatetempdir", "block_exaport"));
                }
            } else {
                error(get_string("couldntcreatetempdir", "block_exaport"));
            }
        } else {
            //print_error(get_string("scormhastobezip", "block_exaport"));
            print_error("scormhastobezip","block_exaport");
        }
    } else {
        error(get_string("uploadfailed", "block_exaport"));
    }
}


$form_data = new stdClass();
$form_data->courseid = $courseid;
$exteditform->set_data($form_data);
if ($imported) {
    notify(get_string("success", "block_exaport"));
} else {
    $exteditform->display();
}

echo $OUTPUT->footer($course);

function import_files($unzip_dir, $structures, $i = 0, $previd=NULL) {
    // this function is for future use.
}

function portfolio_file_area_name() {
    global $CFG, $USER;
    return "exaport/temp/import/{$USER->id}";
}

function import_user_description($file) {
    global $USER, $DB;
    $content = file_get_contents($file);

    if (($startDesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->')) !== false) {
        $startDesc+=strlen('<!--###BOOKMARK_PERSONAL_DESC###-->');
        if (($endDesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->', $startDesc)) !== false) {
            if ($DB->record_exists('block_exaportuser', array('user_id'=>$USER->id))) {
                $conditions = array("user_id" => $USER->id);
                $record = $DB->get_record('block_exaportuser', $conditions);
//                $record->description = block_exaport_clean_text(substr($content, $startDesc, $endDesc - $startDesc));
                $record->description = substr($content, $startDesc, $endDesc - $startDesc);
                $record->persinfo_timemodified = time();
                if (!$DB->update_record('block_exaportuser', $record)) {
                    error(get_string("couldntupdatedesc", "block_exaport"));
                }
            } else {
                $newentry = new stdClass();
//                $newentry->description = addslashes(substr($content, $startDesc, $endDesc - $startDesc));
                $newentry->description = substr($content, $startDesc, $endDesc - $startDesc);
                $newentry->persinfo_timemodified = time();
                $newentry->id = $USER->id;
                if (!$DB->insert_record('block_exaportuser', $newentry)) {
                    error(get_string("couldntinsertdesc", "block_exaport"));
                }
            }
        }
    }
}

function import_structure($unzip_dir, $structures, $course, $i = 0, $previd=NULL) {
    global $USER, $COURSE, $DB;
    foreach ($structures as $structure) {
        if (isset($structure["data"])) {
            if (isset($structure["data"]["title"]) &&
                    isset($structure["data"]["url"]) &&
                    ($previd != NULL)) {
                insert_entry($unzip_dir, $structure["data"]["url"], $structure["data"]["title"], $previd, $course);
            } else if (isset($structure["data"]["title"])) {
                if (is_null($previd)) {
                    if ($DB->count_records_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid=0") == 0) {
                        $newentry = new stdClass();
                        $newentry->name = block_exaport_clean_title($structure["data"]["title"]);
                        $newentry->timemodified = time();
                        $newentry->course = $COURSE->id;
                        $newentry->userid = $USER->id;
                        //$newentry->pid = $previd;

                        if (!$entryid = $DB->insert_record("block_exaportcate", $newentry)) {
                            notify("Could not insert category!");
                        }
                    } else {
                        $entry = $DB->get_record_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid=0");
                        $entryid = $entry->id;
                    }
                } else {
                    if ($DB->count_records_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid='$previd'") == 0) {
                        $newentry->name = block_exaport_clean_title($structure["data"]["title"]);
                        $newentry->timemodified = time();
                        $newentry->course = $COURSE->id;
                        $newentry->userid = $USER->id;
                        $newentry->pid = $previd;

                        if (!$entryid = $DB->insert_record("block_exaportcate", $newentry)) {
                            notify("Could not insert category!");
                        }
                    } else {
                        $entry = $DB->get_record_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid='$previd'");
                        $entryid = $entry->id;
                    }
                }
            }
        }
        if (isset($structure["items"]) && isset($entryid)) {
            import_structure($unzip_dir, $structure["items"], $course, $i + 1, $entryid);
        }
    }
}

function block_exaport_clean_title($title) {
    return clean_param(addslashes($title), PARAM_TEXT);
}

function block_exaport_clean_url($url) {
    return clean_param(addslashes($url), PARAM_URL);
}

function block_exaport_clean_text($text) {
    return addslashes($text);
}

function block_exaport_clean_path($text) {
    return clean_param($text, PARAM_PATH);
}

function insert_entry($unzip_dir, $url, $title, $category, $course) {
    global $USER, $CFG, $COURSE, $DB;
    $filePath = $unzip_dir . '/' . $url;

    $content = file_get_contents($filePath);
    if ((($startUrl = strpos($content, '<!--###BOOKMARK_EXT_URL###-->')) !== false) &&
            (($startDesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->')) !== false)) {
        $startUrl+=strlen('<!--###BOOKMARK_EXT_URL###-->');
        $startDesc+=strlen('<!--###BOOKMARK_EXT_DESC###-->');
        if ((($endUrl = strpos($content, '<!--###BOOKMARK_EXT_URL###-->', $startUrl)) !== false) &&
                (($endDesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->', $startDesc)) !== false)) {
            $new = new stdClass();
            $new->userid = $USER->id;
            $new->categoryid = $category;
            $new->name = block_exaport_clean_title($title);
            $new->url = block_exaport_clean_url(substr($content, $startUrl, $endUrl - $startUrl));
            $new->intro = block_exaport_clean_text(substr($content, $startDesc, $endDesc - $startDesc));
            $new->timemodified = time();
            $new->type = 'link';
            $new->course = $COURSE->id;

            if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                get_comments($content, $new->id, 'block_exaportitemcomm');
            } else {
                notify(get_string("couldntinsert", "block_exaport", $title));
            }
        } else {
            notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
        }
    } else if ((($startUrl = strpos($content, '<!--###BOOKMARK_FILE_URL###-->')) !== false) &&
            (($startDesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->')) !== false)) {
        $startUrl+=strlen('<!--###BOOKMARK_FILE_URL###-->');
        $startDesc+=strlen('<!--###BOOKMARK_FILE_DESC###-->');
        if ((($endUrl = strpos($content, '<!--###BOOKMARK_FILE_URL###-->', $startUrl)) !== false) &&
                (($endDesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->', $startDesc)) !== false)) {
            $linkedFileName = block_exaport_clean_path(substr($content, $startUrl, $endUrl - $startUrl));
            $linkedFilePath = dirname($filePath) . '/' . $linkedFileName;
            if (is_file($linkedFilePath)) {
                $new = new stdClass();
                $new->userid = $USER->id;
                $new->categoryid = $category;
                $new->name = block_exaport_clean_title($title);
                $new->intro = block_exaport_clean_text(substr($content, $startDesc, $endDesc - $startDesc));
                $new->timemodified = time();
                $new->type = 'file';
                $new->course = $COURSE->id;
                // not necessary
                //$new->url          = str_replace($CFG->wwwroot, "", $_SERVER["HTTP_REFERER"]);

                if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                    $destination = block_exaport_file_area_name($new);
                    if (make_upload_directory($destination, false)) {
                        $destination = $CFG->dataroot . '/' . $destination;
                        $destination_name = handle_filename_collision($destination, $linkedFileName);


                        $context = get_context_instance(CONTEXT_USER);
                        $fs = get_file_storage();
                        $file_record = array('contextid'=>$context->id, 'component'=>'user', 'filearea'=>'exaport_import',
                                'itemid'=>0, 'filepath'=>'/', 'filename'=>$destination_name,
                                'timecreated'=>time(), 'timemodified'=>time());

                        //eindeutige itemid generieren
                        try {
                            if ($newfile = $fs->create_file_from_pathname($file_record, $linkedFilePath)) {
                                $DB->set_field("files","itemid",$newfile->get_id(),array("id"=>$newfile->get_id()));
                                $DB->set_field("block_exaportitem", "attachment", $newfile->get_id(), array("id"=>$new->id));
                            } else {
                                $DB->delete_records("block_exaportitem",array("id"=>$new->id));
                                notify(get_string("couldntcopyfile", "block_exaport", $title));
                            }
                        } catch(stored_file_creation_exception $e) {
                            //File existiert bereits
                            $existing_file = $DB->get_records("files",array('filename'=>$destination_name));
                            $DB->set_field("block_exaportitem", "attachment", $existing_file[0]->itemid, array("id"=>$new->id));
                            //$DB->delete_records("block_exaportitem",array("id"=>$new->id));
                        }
                    } else {
                        notify(get_string("couldntcreatedirectory", "block_exaport", $title));
                    }
                    get_comments($content, $new->id, 'block_exaportitemcomm');
                } else {
                    notify(get_string("couldntinsert", "block_exaport", $title));
                }
            } else {
                notify(get_string("linkedfilenotfound", "block_exaport", array("filename" => $linkedFileName, "url" => $url, "title" => $title)));
            }
        } else {
            notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
        }
    } else if ((($startDesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->')) !== false)) {
        $startDesc+=strlen('<!--###BOOKMARK_NOTE_DESC###-->');
        if ((($endDesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->', $startDesc)) !== false)) {
            $new = new stdClass();
            $new->userid = $USER->id;
            $new->categoryid = $category;
            $new->name = block_exaport_clean_title($title);
            $new->intro = block_exaport_clean_text(substr($content, $startDesc, $endDesc - $startDesc));
            $new->timemodified = time();
            $new->type = 'note';
            $new->course = $COURSE->id;

            if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                get_comments($content, $new->id, 'block_exaportitemcomm');
            } else {
                notify(get_string("couldntinsert", "block_exaport", $title));
            }
        } else {
            notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
        }
    } else {
        notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
    }
}

function get_comments($content, $bookmarkid, $table) {
    global $USER,$DB;
    $i = 1;
    $comment = "";
    while ((($startAuthor = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->')) !== false) &&
    (($startTime = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->')) !== false) &&
    (($startContent = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->')) !== false)) {
        $startAuthor+=strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->');
        $startTime+=strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->');
        $startContent+=strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->');

        if ((($endAuthor = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->', $startAuthor)) !== false) &&
                (($endTime = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->', $startTime)) !== false) &&
                (($endContent = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->', $startContent)) !== false)) {

            $commentAuthor = block_exaport_clean_text(substr($content, $startAuthor, $endAuthor - $startAuthor));
            $commentTime = block_exaport_clean_text(substr($content, $startTime, $endTime - $startTime));
            $commentContent = block_exaport_clean_text(substr($content, $startContent, $endContent - $startContent));

            $comment .= '<span class="block_eportfolio_commentauthor">' . $commentAuthor . '</span> ' . $commentTime . '<br />' . $commentContent . '<br /><br />';
        } else {
            notify(get_string("couldninsertcomment", "block_exaport"));
        }
        $i++;
    }
    if ($comment != "") {
        $new = new stdClass();
        $new->userid = $USER->id;
        $new->timemodified = time();
        $new->itemid = $bookmarkid;
        $new->entry = get_string("importedcommentsstart", "block_exaport") . $comment . get_string("importedcommentsend", "block_exaport");
        if (!$DB->insert_record($table, $new)) {
            notify(get_string("couldninsertcomment", "block_exaport"));
        }
    }
}

function handle_filename_collision($destination, $filename) {
    if (file_exists($destination . '/' . $filename)) {
        $parts = explode('.', $filename);
        $lastPart = array_pop($parts);
        $firstPart = implode('.', $parts);
        $i = 0;
        do {
            $i++;
            $filename = $firstPart . '_' . $i . '.' . $lastPart;
        } while (file_exists($destination . '/' . $filename));
    }
    return $filename;
}

function import_file_area_name() {
    global $USER, $CFG, $COURSE;

    return "exaport/temp/import/{$USER->id}";
}
