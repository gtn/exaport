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

global $PAGE, $USER, $OUTPUT;
require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/minixml.inc.php');
global $DB, $CFG;

$itemsArray = array();

class block_exacomp_ZipArchive extends \ZipArchive {
    /**
     * @return ZipArchive
     */
    public static function create_temp_file() {
        global $CFG;
        $file = tempnam($CFG->tempdir, "zip");
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        return $zip;
    }
}

global $zip, $existingfilesarray;
$zip = block_exacomp_ZipArchive::create_temp_file();
$existingfilesarray = array();

$courseid = optional_param("courseid", 0, PARAM_INT);
$confirm = optional_param("confirm", 0, PARAM_INT);
$viewid = optional_param("viewid", 0, PARAM_INT);
$exportwpfile = optional_param("export-wp-file", '', PARAM_RAW);
$identifier = 1000000; // Item identifier.
$ridentifier = 1000000; // Ressource identifier.

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:export', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}
$url = '/blocks/exaport/export_scorm.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
if (!$confirm) {
    block_exaport_print_header("importexport", "exportimportexport");
}

if (!defined('FILE_APPEND')) {
    define('FILE_APPEND', 1);
}

function spch($text) {
    return htmlentities($text, ENT_QUOTES, "UTF-8");
}

function spch_text($text) {
    $text = htmlentities($text, ENT_QUOTES, "UTF-8");
    $text = str_replace('&amp;', '&', $text);
    $text = str_replace('&lt;', '<', $text);
    $text = str_replace('&gt;', '>', $text);
    $text = str_replace('&quot;', '"', $text);
    return $text;
}

function titlespch($text) {
    return clean_param($text, PARAM_ALPHANUM);
}

function create_ressource(&$resources, $ridentifier, $filename) {
    // At an external ressource no file is needed inside resource.
    $resource = &$resources->createChild('resource');
    $resource->attribute('identifier', $ridentifier);
    $resource->attribute('type', 'webcontent');
    $resource->attribute('adlcp:scormtype', 'asset');
    $resource->attribute('href', $filename);
    $file = &$resource->createChild('file');
    $file->attribute('href', $filename);
    return true;
}

function &create_item(&$pitem, $identifier, $titletext, $residentifier = '', $id = null) {
    // At an external ressource no file is needed inside resource.
    $item = &$pitem->createChild('item');
    $item->attribute('identifier', $identifier);
    $item->attribute('isvisible', 'true');
    if ($id) {
        $item->attribute('id', $id);
    }
    if ($residentifier != '') {
        $item->attribute('identifierref', $residentifier);
    }
    $title = &$item->createChild('title');
    $title->text($titletext);
    return $item;
}

function export_file_area_name() {
    global $USER;
    return "exaport/temp/export/{$USER->id}";
}

function export_data_file_area_name() {
    global $USER;
    return "exaport/temp/exportdataDir/{$USER->id}";
}

function add_comments($table, $bookmarkid) {
    global $DB, $exportwpfile;
    $commentscontent = '';
    $conditions = array("itemid" => $bookmarkid);
    $comments = $DB->get_records($table, $conditions);
    $i = 1;
    if ($comments) {
        foreach ($comments as $comment) {
            $conditions = array("id" => $comment->userid);
            $user = $DB->get_record('user', $conditions);
            if ($exportwpfile) {
                $commentscontent .= userdate($comment->timemodified) . " " . fullname($user, $comment->userid) . " " . $comment->entry . "\n";
            } else {
                $commentscontent .= '
            <div id="comment">
                <div id="author"><!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->' . fullname($user, $comment->userid) .
                    '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###--></div>
                <div id="date"><!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->' . userdate($comment->timemodified) .
                    '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###--></div>
                <div id="content"><!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->' . spch_text($comment->entry) .
                    '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###--></div>
            </div>';
            }
            $i++;
        }
    }
    return $commentscontent;
}

function get_category_items($categoryid, $viewid = null, $type = null) {
    global $USER, $CFG, $DB;
    $conditions = array();
    if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
        $itemquery = "SELECT i.*" .
            " FROM {block_exaportitem} i" .
            ($viewid ? " JOIN {block_exaportviewblock} vb ON cast(vb.type AS varchar(11))='item' " .
                " AND vb.viewid=? AND vb.itemid=i.id" : '') .
            " WHERE i.userid = ?" .
            ($type ? " AND i.type=?" : '') .
            " AND i.categoryid = ?" .
            " ORDER BY i.name desc";
    } else {
        $itemquery = "SELECT i.*" .
            " FROM {block_exaportitem} i" .
            ($viewid ? " JOIN {block_exaportviewblock} vb ON vb.type='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
            " WHERE i.userid = ?" .
            ($type ? " AND i.type=?" : '') .
            " AND i.categoryid =?" .
            " ORDER BY i.name desc";
    }
    if ($viewid) {
        $conditions[] = $viewid;
    }
    $conditions[] = $USER->id;
    if ($type) {
        $conditions[] = $type;
    }
    $conditions[] = $categoryid;

    return $DB->get_records_sql($itemquery, $conditions);
}

function get_category_files($categoryid, $viewid = null) {
    global $USER, $CFG, $DB;

    $conditions = array();
    if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
        $itemquery = "select " . ($viewid ? " vb.id as vbid," : "") . " i.*" .
            " FROM {block_exaportitem} i" .
            ($viewid ? " JOIN {block_exaportviewblock} vb ON cast(vb.type AS varchar(11))='item' " .
                " AND vb.viewid=? AND vb.itemid=i.id" : '') .
            " WHERE i.userid = ?" .
            " AND i.type='file'" .
            " AND i.categoryid = ?" .
            " ORDER BY i.name desc";
    } else {
        $itemquery = "select " . ($viewid ? " vb.id as vbid," : "") . "i.*" .
            " FROM {block_exaportitem} i" .
            ($viewid ? " JOIN {block_exaportviewblock} vb ON vb.type='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
            " WHERE i.userid = ?" .
            " AND i.type='file'" .
            " AND i.categoryid = ?" .
            " ORDER BY i.name desc";
    }
    if ($viewid) {
        $conditions[] = $viewid;
    }
    $conditions[] = $USER->id;
    $conditions[] = $categoryid;
    return $DB->get_records_sql($itemquery, $conditions);
}

function get_category_content(&$xmlelement, &$resources, $id, $name, $exportpath, $exportdir, &$identifier, &$ridentifier, $viewid,
    &$itemscomp, $depth = 0) {
    global $USER, $CFG, $COURSE, $DB, $zip, $existingfilesarray, $exportwpfile;
    // Index file for category.
    $indexfilecontent = '';
    $indexfilecontent .= create_html_header(spch($name), $depth + 1);
    $indexfilecontent .= '<body>' . "\n";
    $indexfilecontent .= '<div id="exa_ex">' . "\n";
    $indexfilecontent .= '<h1>' . get_string("current_category", "block_exaport") . ': ' . spch($name) . '</h1>' . "\n";
    if (!$exportwpfile) {
        $indexfileitems = '';
        // Subcategory links.
        $cats = $DB->get_records_select("block_exaportcate", "userid=$USER->id AND pid='$id'", null, "name ASC");
        if ($cats) {
            $indexfilecontent .= '<h2>' . get_string("categories", "block_exaport") . '</h2>';
            $indexfilecontent .= '<ul>';
            foreach ($cats as $cat) {
                $subdirname = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $cat->name);
                $subdirname = mb_ereg_replace("([\.]{2,})", '', $subdirname);
                //wichtig
                $indexfilecontent .= '<li><a href="' . $subdirname . '/index.html">' . $cat->name . '</a></li>';
            }
            $indexfilecontent .= '</ul>';
        }
    }

    $bookmarks = get_category_items($id, $viewid, 'link');

    $hasitems = false;
    if ($bookmarks) {
        $hasitems = true;
        foreach ($bookmarks as $bookmark) {
            if (block_exaport_check_competence_interaction()) {
                // Begin.
                $compids = block_exaport_get_active_compids_for_item($bookmark);

                if ($compids) {
                    $competences = "";
                    $competencesids = array();
                    foreach ($compids as $compid) {

                        $conditions = array("id" => $compid);
                        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, $conditions, $fields = '*',
                            $strictness = IGNORE_MISSING);
                        if ($competencesdb != null) {
                            $competences .= $competencesdb->title . '<br />';
                            array_push($competencesids, $competencesdb->sourceid);
                        }
                    }
                    $competences = str_replace("\r", "", $competences);
                    $competences = str_replace("\n", "", $competences);
                    $bookmark->competences = $competences;

                    $itemscomp[$bookmark->id] = $competencesids;

                }
            }
            // End.
            unset($filecontent);
            unset($filename);

            $filecontent = create_html_header(spch((fullname($USER, $USER->id))), $depth + 1);
            $filecontent .= '<body>' . "\n";
            $filecontent .= '<div id="exa_ex">' . "\n";
            $filecontent .= '  <h1 id="header">' . spch(format_string($bookmark->name)) . '</h1>' . "\n";
            $filecontent .= '  <div id="url"><a href="' . $bookmark->url . '"><!--###BOOKMARK_EXT_URL###-->' .
                spch($bookmark->url) . '<!--###BOOKMARK_EXT_URL###--></a></div>' . "\n";
            $filecontent .= '  <div id="description"><!--###BOOKMARK_EXT_DESC###-->' . spch_text($bookmark->intro) .
                '<!--###BOOKMARK_EXT_DESC###--></div>' . "\n";
            $filecontent .= add_comments('block_exaportitemcomm', $bookmark->id);
            if (isset($bookmark->competences)) {
                $filecontent .= '<br /> <div id="competences">' . $bookmark->competences . '<div>';
            }
            $filecontent .= '</div>' . "\n";
            $filecontent .= '</body>' . "\n";
            $filecontent .= '</html>' . "\n";

            list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $exportdir, $bookmark->name);
            if (!$exportwpfile) {
                $zip->addFromString($filepath, $filecontent);
            }
            create_ressource($resources, 'RES-' . $ridentifier, $filepath);
            create_item($xmlelement, 'ITEM-' . $identifier, $bookmark->name, 'RES-' . $ridentifier, $bookmark->id);
            $indexfileitems .= '<li><a href="' . $resfilename . '">' . $bookmark->name . '</a></li>';
            $identifier++;
            $ridentifier++;
        }

    }
    $files = get_category_files($id, $viewid);

    if ($files) {
        $fs = get_file_storage();
        $hasitems = true;
        foreach ($files as $file) {
            if (block_exaport_check_competence_interaction()) {
                $compids = block_exaport_get_active_compids_for_item($file);
                if ($compids) {
                    $competences = "";
                    $competencesids = array();
                    foreach ($compids as $compid) {
                        $conditions = array("id" => $compid);
                        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, $conditions, $fields = '*',
                            $strictness = IGNORE_MISSING);
                        if ($competencesdb != null) {
                            $competences .= $competencesdb->title . '<br />';
                            array_push($competencesids, $competencesdb->sourceid);
                        }
                    }
                    $competences = str_replace("\r", "", $competences);
                    $competences = str_replace("\n", "", $competences);

                    $file->competences = $competences;
                    $itemscomp[$file->id] = $competencesids;

                }
            }
            unset($filecontent);
            unset($filename);

            $fsfiles = block_exaport_get_item_files($file);

            if (!$fsfiles) {
                continue;
            }
            $filelinks = '';
            $j = 0;
            foreach ($fsfiles as $ind => $fsfile) {
                $i = 0;
                $contentfilename = $fsfile->get_filename();
                while (in_array($exportdir . $contentfilename, $existingfilesarray)) {
                    $i++;
                    $contentfilename = $i . '-' . $fsfile->get_filename();
                }
                $existingfilesarray[] = $exportdir . $contentfilename;
                if (!$exportwpfile) {
                    $zip->addFromString($contentfilename, $fsfile->get_content());
                }
                $filelinks .= '  <div id="url-' . $j . '"><a href="../' . spch($contentfilename) . '"><!--###BOOKMARK_FILE_URL###-->' .
                    spch($contentfilename) . '<!--###BOOKMARK_FILE_URL###--></a></div>' . "\n";
                $j++;
            }

            $filecontent = create_html_header(spch($file->name), $depth + 1);
            $filecontent .= '<body>' . "\n";
            $filecontent .= '<div id="exa_ex">' . "\n";
            $filecontent .= '  <h1 id="header">' . spch($file->name) . '</h1>' . "\n";
            $filecontent .= $filelinks;
            $filecontent .= '  <div id="description"><!--###BOOKMARK_FILE_DESC###-->' . spch_text($file->intro) .
                '<!--###BOOKMARK_FILE_DESC###--></div>' . "\n";
            $filecontent .= add_comments('block_exaportitemcomm', $file->id);
            if (isset($file->competences)) {
                $filecontent .= '<br /> <div id="competences">' . $file->competences . '<div>';
            }
            $filecontent .= '</div>' . "\n";
            $filecontent .= '</body>' . "\n";
            $filecontent .= '</html>' . "\n";

            list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $exportdir, $file->name);
            if (!$exportwpfile) {
                $zip->addFromString($filepath, $filecontent);
                create_ressource($resources, 'RES-' . $ridentifier, $filepath);
                create_item($xmlelement, 'ITEM-' . $identifier, $file->name, 'RES-' . $ridentifier, $file->id);
                $indexfileitems .= '<li><a href="' . $resfilename . '">' . $file->name . '</a></li>';

            }
            if ($exportwpfile) {
                $itemArray = array();
                $itemArray[] = get_category_items($id, $viewid, 'link');
                $itemArray[] = get_category_items($id, $viewid, 'file');
                $itemArray[] = get_category_items($id, $viewid, 'note');
                $filecontent = '';
                $filecontent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                $filecontent .= "<rss version=\"2.0\"\n";
                $filecontent .= "xmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"\n";
                $filecontent .= "xmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n";
                $filecontent .= "xmlns:wfw=\"http://wellformedweb.org/CommentAPI/\"\n";
                $filecontent .= "xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n";
                $filecontent .= "xmlns:wp=\"http://wordpress.org/export/1.2/\">\n";
                $filecontent .= "<channel>\n";
                $filecontent .= "<title>" . spch(fullname($USER, $USER->id)) . "</title>\n";
                // Author
                $filecontent .= "<wp:author>";
                $filecontent .= "<wp:author_id>" . $USER->id . "</wp:author_id>\n";
                $filecontent .= "<wp:author_login>" . "<![CDATA[" . fullname($USER, $USER->id) . "]]>" . "</wp:author_login>\n";
                $filecontent .= "<wp:author_email>" . "<![CDATA[" . $USER->email . "]]>" . "</wp:author_email>\n";
                $filecontent .= "<wp:author_display_name>" . "<![CDATA[" . fullname($USER, $USER->id) . "]]>" . "</wp:author_display_name>\n";
                $filecontent .= "<wp:author_first_name>" . "<![CDATA[" . $USER->firstname . "]]>" . "</wp:author_first_name>\n";
                $filecontent .= "<wp:author_last_name>" . "<![CDATA[" . $USER->lastname . "]]>" . "</wp:author_last_name>\n";
                $filecontent .= "</wp:author>\n";


                //items
                foreach ($itemArray as $subArray) {
                    foreach ($subArray as $blabla) {
                        $filecontent .= "<wp:category>\n";
                        $filecontent .= "<wp:term_id>" . $id . "</wp:term_id>\n";
                        $filecontent .= "<wp:category_nicename>" . "<![CDATA[" . spch($name) . "]]>" . "</wp:category_nicename>\n";
                        $filecontent .= "</wp:category>\n";
                        $filecontent .= "<item>\n";
                        $filecontent .= "<title>" . "<![CDATA[" . $blabla->name . "]]>" . "</title>\n";
                        if (add_comments('block_exaportitemcomm', $blabla->id) != '') {
                            $filecontent .= "<content:encoded>" . "<![CDATA[<!-- wp:peregraph --> <p>" . add_comments("block_exaportitemcomm", $blabla->id) . "</p> <!-- wp:peregraph -->]]> " . "</content:encoded>\n";
                        }
                        if ($blabla->intro != '') {

                            $filecontent .= "<description>" . "<![CDATA[" . spch_text($blabla->intro) . "]]>" . "</description>\n";
                        }
                        $filecontent .= "</item>\n";
                    }
                }
                $filecontent .= "</channel>\n";
                $filecontent .= "</rss>";
                $zip->addFromString('wordpress.xml', $filecontent);
                $zipname = clean_param($USER->username, PARAM_ALPHANUM) . strftime("_%Y_%m_%d_%H%M") . ".zip";
                $zipfile = $zip->filename;
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Length: ' . filesize($zipfile));
                header('Content-Disposition: attachment; filename="' . $zipname . '"');
                readfile($zipfile);
                unlink($zipfile);
                exit;
            }
            $identifier++;
            $ridentifier++;
        }
    }

    $notes = get_category_items($id, $viewid, 'note');

    if ($notes) {
        $hasitems = true;
        foreach ($notes as $note) {
            if (block_exaport_check_competence_interaction()) {
                $compids = block_exaport_get_active_compids_for_item($note);

                if ($compids) {
                    $competences = "";
                    $competencesids = array();
                    foreach ($compids as $compid) {

                        $conditions = array("id" => $compid);
                        $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, $conditions, $fields = '*',
                            $strictness = IGNORE_MISSING);
                        if ($competencesdb != null) {
                            $competences .= $competencesdb->title . '<br />';
                            array_push($competencesids, $competencesdb->sourceid);
                        }
                    }
                    $competences = str_replace("\r", "", $competences);
                    $competences = str_replace("\n", "", $competences);

                    $note->competences = $competences;
                    $itemscomp[$note->id] = $competencesids;

                }
            }
            unset($filecontent);
            unset($filename);
            $filecontent = '';
            $filecontent .= create_html_header(spch($note->name), $depth + 1);
            $filecontent .= '<body>' . "\n";
            $filecontent .= '<div id="exa_ex">' . "\n";
            $filecontent .= '  <h1 id="header">' . spch($note->name) . '</h1>' . "\n";
            $filecontent .= '  <div id="description"><!--###BOOKMARK_NOTE_DESC###-->' . spch_text($note->intro) .
                '<!--###BOOKMARK_NOTE_DESC###--></div>' . "\n";
            $filecontent .= add_comments('block_exaportitemcomm', $note->id);
            if (isset($note->competences)) {
                $filecontent .= '<br /> <div id="competences">' . $note->competences . '<div>';
            }
            $filecontent .= '</div>' . "\n";
            $filecontent .= '</body>' . "\n";
            $filecontent .= '</html>' . "\n";

            list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $exportdir, $note->name);
            $zip->addFromString($filepath, $filecontent);
            create_ressource($resources, 'RES-' . $ridentifier, $filepath);
            create_item($xmlelement, 'ITEM-' . $identifier, $note->name, 'RES-' . $ridentifier, $note->id);

            $indexfileitems .= '<li><a href="' . $resfilename . '">' . $note->name . '</a></li>';

            $identifier++;
            $ridentifier++;
        }
    }
    if ($hasitems) {
        $indexfilecontent .= '<h2>' . get_string("listofartefacts", "block_exaport") . '</h2>';
        $indexfilecontent .= '<ul>';
        $indexfilecontent .= $indexfileitems;
        $indexfilecontent .= '</ul>';
    }
    $indexfilecontent .= '</div>' . "\n";
    $indexfilecontent .= '</body>' . "\n";
    $indexfilecontent .= '</html>' . "\n";
    $zip->addFromString($exportdir . 'index.html', $indexfilecontent);


    return $hasitems;
}

function rekcat($owncats, $parseddoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $organization, $i, &$itemscomp,
    $subdirname, $depth): bool {
    global $DB, $USER, $zip;
    $return = false;

    foreach ($owncats as $owncat) {
        // Directory for category.

        $newsubdir = '';
        if ($owncat->id == 0 && $owncat->name == 'Root') {
            // Root category.
            $newsubdir = '';
        } else {
            $newsubdir = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $owncat->name);
            $newsubdir = mb_ereg_replace("([\.]{2,})", '', $newsubdir);
            $zip->addEmptyDir($subdirname . $newsubdir);
            if (substr($newsubdir, -1) != "/") {
                $newsubdir .= "/";
            }
        }
        if ($owncat->id == 0) {
            // Ignore root virtual category.
            $item = $organization;
        } else {
            $i++;
            $item = &$parseddoc->createElement('item');
            $item->attribute('identifier', sprintf('B%04d', $i));
            $item->attribute('isvisible', 'true');
            $itemtitle = &$item->createChild('title');
            $itemtitle->text($owncat->name);
        }
        // Get everything inside this category:.
        $mainnotempty = get_category_content($item, $resources, $owncat->id, $owncat->name, $exportdir, $subdirname . $newsubdir,
            $identifier, $ridentifier, $viewid, $itemscomp, $depth);

        $innerowncats = $DB->get_records_select("block_exaportcate", "userid=$USER->id AND pid='$owncat->id'", null, "name ASC");
        if ($innerowncats) {
            $value = rekcat($innerowncats, $parseddoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $item, $i,
                $itemscomp, $subdirname . $newsubdir, $depth + 1);
            if ($value) {
                $mainnotempty = $value;
            }
        }
        if ($mainnotempty) {
            // If the main category is not empty, append it to the xml-file.
            if ($owncat->id > 0) {
                $organization->appendChild($item);
                $ridentifier++;
                $identifier++;
                $i++;
            };
            $return = true;
        }
    }
    return $return;
}

function create_xml_comps($itemscomp, $exportdir) {
    global $USER, $zip;
    $parseddoc = new MiniXMLDoc();

    $xmlroot = &$parseddoc->getRoot();

    // Root-Element MANIFEST.
    $manifest = &$xmlroot->createChild('manifest');
    $manifest->attribute('identifier', $USER->username . 'Export');
    $manifest->attribute('version', '1.1');
    $manifest->attribute('xmlns', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2');
    $manifest->attribute('xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
    $manifest->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $manifest->attribute('xsi:schemaLocation', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
                      http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
                      http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd');

    $items = &$manifest->createChild('items');
    $items->attribute('default', 'DATA');

    foreach ($itemscomp as $key => $values) {

        $item = &$items->createChild('item');
        $item->attribute('identifier', $key);

        foreach ($values as $value) {
            $comp = &$item->createChild('comp');
            $comp->attribute('identifier', $value);
        }
    }

    $zip->addFromString('itemscomp.xml', $parseddoc->toString(MINIXML_NOWHITESPACES));
}

if ($confirm) {
    if (!confirm_sesskey()) {
        error('Bad Session Key');
    }

    $exportdir = '';

    // Put a / on the end.
    if (substr($exportdir, -1) != "/") {
        $exportdir .= "/";
    }

    // Create directory for data files.

    if (!$exportwpfile) {
        $exportdatadir = "data";
        $zip->addEmptyDir($exportdatadir);
        if (substr($exportdatadir, -1) != "/") {
            $exportdatadir .= "/";
        }
    }


    // Create directory for categories.

    $categoriessubdirname = "categories";
    $exportcategoriesdir = $exportdir . $categoriessubdirname;
    $exportcategoriesdir = rtrim($exportcategoriesdir, '/') . '/';
    $categoriessubdirname = rtrim($categoriessubdirname, '/') . '/';
    if (!$exportwpfile) {
        $zip->addEmptyDir($exportcategoriesdir);
    }


    // Copy all necessary files.
    if (!$exportwpfile) {
        $zip->addFromString('adlcp_rootv1p2.xsd', file_get_contents('files/adlcp_rootv1p2.xsd'));
        $zip->addFromString('ims_xml.xsd', file_get_contents('files/ims_xml.xsd'));
        $zip->addFromString('imscp_rootv1p1p2.xsd', file_get_contents('files/imscp_rootv1p1p2.xsd'));
        $zip->addFromString('imsmd_rootv1p2p1.xsd', file_get_contents('files/imsmd_rootv1p2p1.xsd'));
        $zip->addFromString('export_style.css', file_get_contents('files/export_style.css'));
    }


    $parseddoc = new MiniXMLDoc();

    $xmlroot = &$parseddoc->getRoot();

    // Root-Element MANIFEST.
    $manifest = &$xmlroot->createChild('manifest');
    $manifest->attribute('identifier', $USER->username . 'Export');
    $manifest->attribute('version', '1.1');
    $manifest->attribute('xmlns', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2');
    $manifest->attribute('xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
    $manifest->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $manifest->attribute('xsi:schemaLocation', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
                      http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
                      http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd');

    // Our Organizations.
    $organizations = &$manifest->createChild('organizations');
    $organizations->attribute('default', 'DATA');

    // Our organization for the export structure.
    $descorganization = &$organizations->createChild('organization');
    $descorganization->attribute('identifier', 'DATA');

    $title = &$descorganization->createChild('title');
    $title->text(get_string("personal", "block_exaport"));

    // Our organization for the export structure.
    $organization = &$organizations->createChild('organization');
    $organization->attribute('identifier', 'PORTFOLIO');

    // Our resources.
    $resources = &$manifest->createChild('resources');

    // Root entry in organization.
    $title = &$organization->createChild('title');
    $title->text(get_string("myportfolio", "block_exaport"));

    $userdescriptions = $DB->get_records_select("block_exaportuser", "user_id = '$USER->id'");

    $description = '';

    if ($userdescriptions) {
        foreach ($userdescriptions as $userdescription) {
            $description = $userdescription->description;
            if (strncmp($description, "<img", strlen("<img"))) {
                $description = str_replace("@@PLUGINFILE@@/", "personal/", $description);
            }
        }
    }
    $filecontent = '';
    $filecontent .= create_html_header(spch(fullname($USER, $USER->id)), 1);
    $filecontent .= '<body>' . "\n";
    $filecontent .= '	<div id="exa_ex">' . "\n";
    $filecontent .= '  <h1 id="header">' . spch(fullname($USER, $USER->id)) . '</h1>' . "\n";
    $filecontent .= '  <div id="description">' . $USER->country . '</div>' . "\n";
    $filecontent .= '  <div id="description">' . $USER->city . '</div>' . "\n";
    $filecontent .= '  <div id="description">' . $USER->email . '</div>' . "\n";
    $filecontent .= '</div>' . "\n";
    $filecontent .= '</body>' . "\n";
    $filecontent .= '</html>' . "\n";
    //wichtiggg
    list ($profilefilename, $filepath) = get_htmlfile_name_path($exportdir, 'data/', fullname($USER, $USER->id));
    $filepathtopersonal = $filepath;

    $zip->addFromString($filepath, $filecontent);

    create_ressource($resources, 'RES-' . $ridentifier, $filepath);
    create_item($descorganization, 'ITEM-' . $identifier, fullname($USER, $USER->id), 'RES-' . $ridentifier);

    $identifier++;
    $ridentifier++;

    // Categories.
    // Virtual root category.
    $owncat = new stdClass();
    $owncat->id = 0;
    $owncat->name = 'Root';
    $owncats = array();
    $owncats[] = $owncat;

    $i = 0;

    $itemscomp = array();
    if ($owncats) {
        rekcat($owncats, $parseddoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $organization, $i, $itemscomp,
            $categoriessubdirname, 0);
    }

    // Save files, from personal information.
    $fs = get_file_storage();

    $areafiles = $fs->get_area_files(context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', false, 'itemid, filepath, filename', false);
    $areafilesexist = false;
    foreach ($areafiles as $areafile) {
        if (!$areafile) {
            continue;
        }
        if (strcmp($areafile->get_filename(), ".") != 0) {
            $zip->addEmptyDir("data/personal/");
            $i = 0;
            $contentfilename = $areafile->get_filename();
            while (in_array("data/personal/" . $contentfilename, $existingfilesarray)) {
                $i++;
                $contentfilename = $i . '-' . $areafile->get_filename();
            }
            $existingfilesarray[] = $contentfilename;

            $zip->addFromString("data/personal/" . $contentfilename, $areafile->get_content());
            $areafilesexist = true;
        }

    }
    // Main index.html.
    //i think the if is not needed because withdirectory is not set anywhere
    $filecontent = '';
    $filecontent .= create_html_header(spch(fullname($USER, $USER->id)), 0);
    $filecontent .= '<body>' . "\n";
    $filecontent .= '	<div id="exa_ex">' . "\n";
    $filecontent .= '  <h1 id="header">' . spch(fullname($USER, $USER->id)) . '</h1>' . "\n";
    $filecontent .= '  <ul>' . "\n";
    $filecontent .= '  <li><a href="' . $filepathtopersonal . '">' . get_string("explainpersonal", "block_exaport") .
        '</a></li>' . "\n";
    $filecontent .= '  <li><a href="' . $categoriessubdirname . 'index.html">' . get_string("myportfolio", "block_exaport") .
        '</a></li>' . "\n";
    if ($areafilesexist) {
        $filecontent .= '  <li><a href="data/personal/">' . get_string("myfilearea", "block_exaport") . '</a></li>' . "\n";
    }
    $filecontent .= '  </ul>' . "\n";
    $filecontent .= '</div>' . "\n";
    $filecontent .= '</body>' . "\n";
    $filecontent .= '</html>' . "\n";
    // Save main index.html.
    $zip->addFromString('index.html', $filecontent);

    create_xml_comps($itemscomp, $exportdir);

    $zip->addFromString('imsmanifest.xml', $parseddoc->toString(MINIXML_NOWHITESPACES));

    $zipname = clean_param($USER->username, PARAM_ALPHANUM) . strftime("_%Y_%m_%d_%H%M") . ".zip";

    // Return zip.
    $zipfile = $zip->filename;
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipfile));
    header('Content-Disposition: attachment; filename="' . $zipname . '"');
    readfile($zipfile);
    unlink($zipfile);
    exit;
}

echo "<br />";
echo '<div class="block_eportfolio_center">';

if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
    $views = $DB->get_records('block_exaportview', array('userid' => $USER->id), 'cast(name AS varchar(max))');
} else {
    $views = $DB->get_records('block_exaportview', array('userid' => $USER->id), 'name');
}
global $OUTPUT, $views, $courseid, $course;
echo $OUTPUT->box_start();

echo '<p>' . get_string("explainexport", "block_exaport") . '</p>';
echo '<form method="post" class="block_eportfolio_center" action="' . $_SERVER['PHP_SELF'] . '" >';
echo '<fieldset>';

echo '<div style="padding-bottom: 15px;">';
// Views.
if (block_exaport_feature_enabled('views')) {
    echo get_string("exportviewselect", "block_exaport") . ': ';
    echo '<select name="viewid">';
    echo '<option></option>';
    foreach ($views as $view) {
        echo '<option value="' . $view->id . '">' . $view->name . '</option>';
    }
    echo '</select>';
}
echo ' </div>';
echo '<input type="hidden" name="confirm" value="1" />';
echo '<input type="submit" name="export" value="' . get_string("createexport", "block_exaport") . '" class="btn btn-primary"/> ';
//echo '<input type="submit" name="export-wp-file" value="' . "CREATE WP-File" . '" class="btn btn-primary"/>';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
echo '</fieldset>';
echo '</form>';
echo '</div>';

echo $OUTPUT->box_end();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function get_htmlfile_name_path($exportpath, $exportdir, $itemname) {
    global $existingfilesarray;
    $filename = clean_param($itemname, PARAM_ALPHANUM);
    $ext = ".html";
    $i = 0;
    if ($filename == "") {
        $filepath = $exportdir . $filename . $i . $ext;
        $resfilename = $filename . $i . $ext;
    } else {
        $filepath = $exportdir . $filename . $ext;
        $resfilename = $filename . $ext;
    }
    if (in_array($exportpath . $filepath, $existingfilesarray)) {
        do {
            $i++;
            $filepath = $exportdir . $filename . $i . $ext;
            $resfilename = $filename . $i . $ext;
        } while (in_array($exportpath . $filepath, $existingfilesarray));
    }
    $existingfilesarray[] = $exportpath . $filepath;
    return array($resfilename, $filepath);
}

function create_html_header($title, $depthpath = 0) {
    $filecontent = '';
    $depth = '';
    for ($i = 1; $i <= $depthpath; $i++) {
        $depth .= '../';
    }
    $filecontent .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"' .
        ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' .
        "\n";
    $filecontent .= '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
    $filecontent .= '<head>' . "\n";
    $filecontent .= '  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n";
    $filecontent .= '  <title>' . $title . '</title>' . "\n";
    $filecontent .= '  <link href="' . $depth . 'export_style.css" rel="stylesheet">' . "\n";
    $filecontent .= '<!-- ' . get_string("exportcomment", "block_exaport") . ' -->';
    $filecontent .= '</head>' . "\n";
    return $filecontent;
}
