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

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/edit_form.php');
require_once(__DIR__ . '/lib/minixml.inc.php');
require_once(__DIR__ . '/lib/class.scormparser.php');
require_once(__DIR__ . '/lib/information_edit_form.php');

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

$context = context_system::instance();

require_capability('block/exaport:use', $context);
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

$url = '/blocks/exaport/import_file.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

$strimport = get_string("import", "block_exaport");
$imported = false;
$returnurl = $CFG->wwwroot . '/blocks/exaport/importexport.php?courseid=' . $courseid;

$exteditform = new block_exaport_scorm_upload_form(null, null);
if ($exteditform->is_cancelled()) {
    redirect($returnurl);
} else if ($exteditform->no_submit_button_pressed()) {
    die("nosubmitbutton");
}

block_exaport_print_header("importexport", "exportimportimport");

if ($fromform = $exteditform->get_data()) {
    $imported = true;
    $dir = make_upload_directory(import_file_area_name());
    $zipcontent = $exteditform->get_file_content('attachment');
    $fileput = file_put_contents($dir . "/" . $exteditform->get_new_filename('attachment'), $zipcontent);
    if ($fileput && $newfilename = $exteditform->get_new_filename('attachment')) {
        if (preg_match('/^(.*).zip$/', $newfilename, $regs)) {
            if ($scormdir = make_upload_directory(import_file_area_name())) {
                $unzipdir = $scormdir . '/' . $regs[1];

                if (is_dir($unzipdir)) {
                    $i = 0;
                    do {
                        $i++;
                        $unzipdir = $scormdir . '/' . $regs[1] . $i;
                    } while (is_dir($unzipdir));
                }

                if (mkdir($unzipdir)) {
                    $zip = new ZipArchive();
                    if ($zip->open($dir . '/' . $newfilename) == true) {
                        $zip->extractTo($unzipdir);
                        // if (unzip_file($dir.'/'.$newfilename, $unzipdir, false)) {
                        if (is_file($unzipdir . "/itemscomp.xml")) {
                            $xml = simplexml_load_file($unzipdir . "/itemscomp.xml");
                        }

                        // Parsing of file.
                        $scormparser = new SCORMParser();
                        $scormtree = $scormparser->parse($unzipdir . '/imsmanifest.xml');
                        // Write warnings and errors.
                        if ($scormparser->is_warning()) {
                            error($scormparser->get_warning());
                        } else if ($scormparser->is_error()) {
                            error($scormparser->get_error());
                        } else {
                            foreach ($scormtree as $organization) {
                                switch ($organization["data"]["identifier"]) {
                                    case "DATA":
                                        if (isset($organization["items"][0]["data"]["url"])) {
                                            $filepath = $unzipdir . '/' .
                                                clean_param($organization["items"][0]["data"]["url"], PARAM_PATH);
                                            if (is_file($filepath)) {
                                                import_user_description($filepath, $unzipdir);
                                            }
                                        }
                                        break;
                                    case "PORTFOLIO":
                                        if (isset($organization["items"])) {
                                            if (isset($xml)) {
                                                import_structure($unzipdir, $organization["items"], $course, 0, $xml, 0);
                                            } else {
                                                import_structure($unzipdir, $organization["items"], $course);
                                            }
                                        }
                                        break;
                                    default:
                                        import_files($unzipdir, $organization["items"]);
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
            print_error("scormhastobezip", "block_exaport");
        }
    } else {
        error(get_string("uploadfailed", "block_exaport"));
    }
}

$formdata = new stdClass();
$formdata->courseid = $courseid;
$exteditform->set_data($formdata);
if ($imported) {
    // notify(get_string("success", "block_exaport"));
    echo $OUTPUT->notification(get_string("success", "block_exaport"), 'success');
} else {
    $exteditform->display();
}
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function import_files($unzipdir, $structures, $i = 0, $previd = null) {
    // This function is for future use.
}

function portfolio_file_area_name() {
    global $CFG, $USER;
    return "exaport/temp/import/{$USER->id}";
}

function create_image($content) {
    $newcontent = str_replace("personal", "@@PLUGINFILE@@", $content);
    return $newcontent;
}

function get_image_url($content) {
    $urls = array();

    while (($pos = strpos($content, '@@PLUGINFILE@@/')) !== false) {
        $content = substr($content, $pos + 15);
        $url = explode("\"", $content);
        $url = current($url);
        array_push($urls, $url);
    }

    return $urls;
}

// deprecated?
function import_user_image($unzipdir, $url) {
    global $USER, $DB, $OUTPUT;

    $path = $unzipdir . "/data/personal/" . $url;

    $linkedfilename = block_exaport_clean_path($url);
    $linkedfilepath = dirname($path) . '/' . $linkedfilename;

    $content = file_get_contents($linkedfilepath);

    if (is_file($linkedfilepath)) {

        $new = new stdClass();
        $new->userid = $USER->id;
        $new->categoryid = 5;
        $new->name = $url;
        $new->intro = $path;
        $new->timemodified = time();
        $new->type = 'file';
        $new->course = null;

        if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
            $fs = get_file_storage();

            // Prepare file record object.
            $fileinfo = array(
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'block_exaport', // Usually = table name.
                'filearea' => 'personal_information',     // Usually = table name.
                'itemid' => $new->id,          // Usually = ID of row in table.
                'filepath' => '/',              // Any path beginning and ending in /.
                'filename' => $linkedfilename,
                'userid' => $USER->id);

            // Eindeutige itemid generieren.
            $fs->create_file_from_pathname($fileinfo, $linkedfilepath);

            $textfieldoptions = array('trusttext' => true,
                'subdirs' => true,
                'maxfiles' => 99,
                'context' => context_user::instance($USER->id));
            $userpreferences = block_exaport_get_user_preferences($USER->id);
            $description = $userpreferences->description;
            $informationform = new block_exaport_personal_information_form();

            $data = new stdClass();
            $data->courseid = '2';
            $data->description = $description;
            $data->descriptionformat = FORMAT_HTML;
            $data->cataction = 'save';
            $data->edit = 1;

            $data = file_prepare_standard_editor($data, 'description', $textfieldoptions, context_user::instance($USER->id),
                'block_exaport', 'personal_information', $USER->id);

            $array = $data->description_editor;
            file_prepare_draft_area($array["itemid"], context_user::instance($USER->id)->id, 'block_exaport',
                'personal_information', $new->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));

            $informationform->set_data($data);
            $informationform->display();

            $DB->delete_records("block_exaportitem", array("id" => $new->id));
        } else {
            $OUTPUT->notification(get_string("linkedfilenotfound", "block_exaport", array("url" => $url, "title" => "test")));
        }
    }
}

function import_user_description($file, $unzipdir) {
    global $USER, $DB;

    $content = file_get_contents($file);

    if (($startdesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->')) !== false) {
        $startdesc += strlen('<!--###BOOKMARK_PERSONAL_DESC###-->');

        if (($enddesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->', $startdesc)) !== false) {
            if ($DB->record_exists('block_exaportuser', array('user_id' => $USER->id))) {
                $conditions = array("user_id" => $USER->id);
                $record = $DB->get_record('block_exaportuser', $conditions);
                $record->description = substr($content, $startdesc, $enddesc - $startdesc);
                $record->persinfo_timemodified = time();
                if (!$DB->update_record('block_exaportuser', $record)) {
                    error(get_string("couldntupdatedesc", "block_exaport"));
                }
            } else {
                $newentry = new stdClass();
                $newentry->description = substr($content, $startdesc, $enddesc - $startdesc);
                $newentry->persinfo_timemodified = time();
                $newentry->user_id = $USER->id;
                if (!$DB->insert_record('block_exaportuser', $newentry)) {
                    error(get_string("couldntinsertdesc", "block_exaport"));
                }
            }
        }
    }
}

function import_structure($unzipdir, $structures, $course, $i = 0, &$xml = null, $previd = null) {
    global $USER, $COURSE, $DB, $OUTPUT;
    foreach ($structures as $structure) {
        if (isset($structure["data"])) {
            if (isset($structure["data"]["title"])
                && isset($structure["data"]["url"])
                && !isset($structure["items"])
            ) {
                if (isset($structure["data"]["id"])) {
                    insert_entry($unzipdir, $structure["data"]["url"], $structure["data"]["title"], $previd, $course, $xml,
                        $structure["data"]["id"]);
                } else {
                    insert_entry($unzipdir, $structure["data"]["url"], $structure["data"]["title"], $previd, $course);
                };
            } else if (isset($structure["data"]["title"])) {
                if (is_null($previd)) {
                    if ($DB->count_records_select("block_exaportcate",
                            "name = ? AND userid = ? AND pid = 0",
                            [$structure["data"]["title"], $USER->id]) == 0
                    ) {
                        $newentry = new stdClass();
                        $newentry->name = block_exaport_clean_title($structure["data"]["title"]);
                        $newentry->timemodified = time();
                        $newentry->course = $COURSE->id;
                        $newentry->userid = $USER->id;

                        if (!$entryid = $DB->insert_record("block_exaportcate", $newentry)) {
                            $OUTPUT->notification("Could not insert category!");
                        }
                    } else {
                        $entry = $DB->get_record_select("block_exaportcate",
                            "name = ? AND userid = ? AND pid = 0",
                            [$structure["data"]["title"], $USER->id]);
                        $entryid = $entry->id;
                    }
                } else {
                    if ($DB->count_records_select("block_exaportcate",
                            "name = ? AND userid = ? AND pid = ? ",
                            [$structure["data"]["title"], $USER->id, $previd]) == 0
                    ) {
                        $newentry = new stdClass();
                        $newentry->name = block_exaport_clean_title($structure["data"]["title"]);
                        $newentry->timemodified = time();
                        $newentry->course = $COURSE->id;
                        $newentry->userid = $USER->id;
                        $newentry->pid = $previd;

                        if (!$entryid = $DB->insert_record("block_exaportcate", $newentry)) {
                            $OUTPUT->notification("Could not insert category!");
                        }
                    } else {
                        $entry = $DB->get_record_select("block_exaportcate",
                            "name = ? AND userid = ? AND pid = ? ",
                            [$structure["data"]["title"], $USER->id, $previd]);
                        $entryid = $entry->id;
                    }
                }
            }
        }
        if (isset($structure["items"]) && isset($entryid)) {
            import_structure($unzipdir, $structure["items"], $course, $i + 1, $xml, $entryid);
        }
    }
}

function import_item_competences($newid, $oldid, &$xml, $dir, $title) {
    global $USER, $DB, $COURSE;

    foreach ($xml->items->item as $item) {
        $id = (int)$item->attributes()->identifier[0];
        if ($oldid == $id) {
            foreach ($item->comp as $comp) {
                $compid = (int)$comp->attributes()->identifier[0];
                $desc = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array("sourceid" => $compid));
                $newentry = new stdClass();
                $newentry->activityid = $newid;
                $newentry->compid = $desc->id;
                $newentry->userid = $USER->id;
                $newentry->reviewerid = $USER->id;
                $newentry->role = 0;
                $newentry->eportfolioitem = 1;
                $newentry->wert = 0;
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM, $newentry);

                $newentry2 = new stdClass();
                $newentry2->compid = $desc->id;
                $newentry2->activityid = $newid;
                $newentry2->eportfolioitem = 1;
                $newentry2->activitytitle = $title;
                $newentry2->coursetitle = $COURSE->shortname;
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, $newentry2);
            }
        }
    }
}

function block_exaport_clean_title($title) {
    return clean_param($title, PARAM_TEXT);
}

function block_exaport_clean_url($url) {
    return clean_param($url, PARAM_URL);
}

function block_exaport_clean_text($text) {
    return $text;
}

function block_exaport_clean_path($text) {
    $text = html_entity_decode($text);
    return clean_param($text, PARAM_PATH);
}

function insert_entry($unzipdir, $url, $title, $category, $course, &$xml = null, $id = null) {
    global $USER, $CFG, $COURSE, $DB, $OUTPUT;
    $filepath = $unzipdir . '/' . $url;
    $content = file_get_contents($filepath);

    if ((($starturl = strpos($content, '<!--###BOOKMARK_EXT_URL###-->')) !== false) &&
        (($startdesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->')) !== false)
    ) {
        $starturl += strlen('<!--###BOOKMARK_EXT_URL###-->');
        $startdesc += strlen('<!--###BOOKMARK_EXT_DESC###-->');
        if ((($endurl = strpos($content, '<!--###BOOKMARK_EXT_URL###-->', $starturl)) !== false) &&
            (($enddesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->', $startdesc)) !== false)
        ) {
            $new = new stdClass();
            $new->userid = $USER->id;
            $new->categoryid = $category;
            $new->name = block_exaport_clean_title($title);
            $new->url = block_exaport_clean_url(substr($content, $starturl, $endurl - $starturl));
            $new->intro = block_exaport_clean_text(substr($content, $startdesc, $enddesc - $startdesc));
            $new->timemodified = time();
            $new->type = 'link';
            $new->course = $COURSE->id;

            if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                if (isset($xml) && isset($id)) {
                    import_item_competences($new->id, $id, $xml, $unzipdir, $new->name);
                }
                get_comments($content, $new->id, 'block_exaportitemcomm');
            } else {
                $OUTPUT->notification(get_string("couldntinsert", "block_exaport", $title));
            }
        } else {
            $OUTPUT->notification(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
        }
    } else if ((($starturl = strpos($content, '<!--###BOOKMARK_FILE_URL###-->')) !== false) &&
        (($startdesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->')) !== false)
    ) {

        preg_match_all('/<!--###BOOKMARK_FILE_URL###-->(.*)<!--###BOOKMARK_FILE_URL###-->/m', $content, $matches);
        $allfiles = $matches[1];
        $enddesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->', $startdesc);
        if (is_file(dirname($filepath) . '/' . block_exaport_clean_path($allfiles[0]))) {
            $new = new stdClass();
            $new->userid = $USER->id;
            $new->categoryid = $category;
            $new->name = block_exaport_clean_title($title);
            $new->intro = block_exaport_clean_text(substr($content, $startdesc, $enddesc - $startdesc));
            $new->timemodified = time();
            $new->type = 'file';
            $new->course = $COURSE->id;

            if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                if (isset($xml) && isset($id)) {
                    import_item_competences($new->id, $id, $xml, $unzipdir, $new->name);
                }
                $fs = get_file_storage();

                // Prepare file record object.
                $fileinfo = array(
                    'contextid' => context_user::instance($USER->id)->id,
                    'component' => 'block_exaport', // Usually = table name.
                    'filearea' => 'item_file',     // Usually = table name.
                    'itemid' => $new->id,          // Usually = ID of row in table.
                    'filepath' => '/',              // Any path beginning and ending in /.
                    'filename' => null, // Setup later.
                    'userid' => $USER->id);
                // add file instances
                foreach ($allfiles as $filename) {
                    // $starturl += strlen('<!--###BOOKMARK_FILE_URL###-->');
                    // $startdesc += strlen('<!--###BOOKMARK_FILE_DESC###-->');
                    // if ((($endurl = strpos($content, '<!--###BOOKMARK_FILE_URL###-->', $starturl)) !== false) &&
                    // (($enddesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->', $startdesc)) !== false)
                    // ) {
                    // $linkedfilename = block_exaport_clean_path(substr($content, $starturl, $endurl - $starturl));
                    $linkedfilename = block_exaport_clean_path($filename);
                    $linkedfilepath = dirname($filepath) . '/' . $linkedfilename;
                    if (is_file($linkedfilepath)) {
                        $fileinfo['filename'] = $linkedfilename;
                        // Eindeutige itemid generieren.
                        if (!$ret = $fs->create_file_from_pathname($fileinfo, $linkedfilepath)) {
                            $DB->delete_records("block_exaportitem", array("id" => $new->id));
                            $OUTPUT->notification(get_string("couldntcopyfile", "block_exaport", $title));
                        } else {
                            get_comments($content, $new->id, 'block_exaportitemcomm');
                        }
                    } else {
                        $OUTPUT->notification(get_string("couldntinsert", "block_exaport", $title));
                    }
                }
            }
            // } else {
            // notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
            // }
        } else {
            $OUTPUT->notification(get_string("linkedfilenotfound", "block_exaport",
                array("filename" => dirname($filepath) . '/' . block_exaport_clean_path($allfiles[0]), "url" => $url, "title" => $title)));
        }
    } else if ((($startdesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->')) !== false)) {
        $startdesc += strlen('<!--###BOOKMARK_NOTE_DESC###-->');
        if ((($enddesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->', $startdesc)) !== false)) {
            $new = new stdClass();
            $new->userid = $USER->id;
            $new->categoryid = $category;
            $new->name = block_exaport_clean_title($title);
            $new->intro = block_exaport_clean_text(substr($content, $startdesc, $enddesc - $startdesc));
            $new->timemodified = time();
            $new->type = 'note';
            $new->course = $COURSE->id;

            if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                if (isset($xml) && isset($id)) {
                    import_item_competences($new->id, $id, $xml, $unzipdir, $new->name);
                }
                get_comments($content, $new->id, 'block_exaportitemcomm');
            } else {
                $OUTPUT->notification(get_string("couldntinsert", "block_exaport", $title));
            }
        } else {
            $OUTPUT->notification(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
        }
    } else {
        $OUTPUT->notification(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
    }
}

function get_comments($content, $bookmarkid, $table) {
    global $USER, $DB, $OUTPUT;
    $i = 1;
    $comment = "";
    while ((($startauthor = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->')) !== false) &&
        (($starttime = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->')) !== false) &&
        (($startcontent = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->')) !== false)) {
        $startauthor += strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->');
        $starttime += strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->');
        $startcontent += strlen('<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->');

        if ((($endauthor = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->', $startauthor)) !== false) &&
            (($endtime = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->', $starttime)) !== false) &&
            (($endcontent = strpos($content, '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->', $startcontent)) !== false)
        ) {

            $commentauthor = block_exaport_clean_text(substr($content, $startauthor, $endauthor - $startauthor));
            $commenttime = block_exaport_clean_text(substr($content, $starttime, $endtime - $starttime));
            $commentcontent = block_exaport_clean_text(substr($content, $startcontent, $endcontent - $startcontent));

            $comment .= '<span class="block_eportfolio_commentauthor">' . $commentauthor . '</span> ' . $commenttime . '<br />' .
                $commentcontent . '<br /><br />';
        } else {
            $OUTPUT->notification(get_string("couldninsertcomment", "block_exaport"));
        }
        $i++;
    }
    if ($comment != "") {
        $new = new stdClass();
        $new->userid = $USER->id;
        $new->timemodified = time();
        $new->itemid = $bookmarkid;
        $new->entry = get_string("importedcommentsstart", "block_exaport") .
            $comment . get_string("importedcommentsend", "block_exaport");
        if (!$DB->insert_record($table, $new)) {
            $OUTPUT->notification(get_string("couldninsertcomment", "block_exaport"));
        }
    }
}

function handle_filename_collision($destination, $filename) {
    if (file_exists($destination . '/' . $filename)) {
        $parts = explode('.', $filename);
        $lastpart = array_pop($parts);
        $firstpart = implode('.', $parts);
        $i = 0;
        do {
            $i++;
            $filename = $firstpart . '_' . $i . '.' . $lastpart;
        } while (file_exists($destination . '/' . $filename));
    }
    return $filename;
}

function import_file_area_name() {
    global $USER, $CFG, $COURSE;

    return "exaport/temp/import/{$USER->id}";
}
