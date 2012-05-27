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
require_once dirname(__FILE__) . '/lib/minixml.inc.php';

global $DB;

$courseid = optional_param("courseid", 0, PARAM_INT);
$confirm = optional_param("confirm", 0, PARAM_INT);
$viewid = optional_param("viewid", 0, PARAM_INT);
$identifier = 1000000; // Item identifier
$ridentifier = 1000000; // Ressource identifier

$context = get_context_instance(CONTEXT_SYSTEM);

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:export', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

$url = '/blocks/exabis_competences/export_epx.php';
$PAGE->set_url($url);
block_exaport_print_header("exportimportexportepx");

if (!defined('FILE_APPEND')) {
    define('FILE_APPEND', 1);
}
if (!function_exists('file_put_contents')) {

    function file_put_contents($n, $d, $flag = false) {
        $mode = ($flag == FILE_APPEND || strtoupper($flag) == 'FILE_APPEND') ? 'a' : 'w';
        $f = @fopen($n, $mode);
        if ($f === false) {
            return 0;
        } else {
            if (is_array($d))
                $d = implode($d);
            $bytes_written = fwrite($f, $d);
            fclose($f);
            return $bytes_written;
        }
    }

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
    return format_text($text);
}

function titlespch($text) {
    return clean_param($text, PARAM_ALPHANUM);
}

function create_ressource(&$resources, $ridentifier, $filename) {
    // at an external ressource no file is needed inside resource
    $resource = & $resources->createChild('resource');
    $resource->attribute('identifier', $ridentifier);
    $resource->attribute('type', 'webcontent');
    $resource->attribute('adlcp:scormtype', 'asset');
    $resource->attribute('href', $filename);
    $file = & $resource->createChild('file');
    $file->attribute('href', $filename);
    return true;
}

function &create_item(&$pitem, $identifier, $titletext, $residentifier = '') {
    // at an external ressource no file is needed inside resource
    $item = & $pitem->createChild('item');
    $item->attribute('identifier', $identifier);
    $item->attribute('isvisible', 'true');
    if ($residentifier != '') {
        $item->attribute('identifierref', $residentifier);
    }
    $title = & $item->createChild('title');
    $title->text($titletext);
    return $item;
}

function export_file_area_name() {
    global $USER;
    return "exaport/temp/export/{$USER->id}";
}

function export_data_file_area_name() {
    global $USER;
    return "exaport/temp/exportdata/{$USER->id}";
}

function block_exaport_get_comments_for_item(&$item, $table, $bookmarkid, &$ridentifier) {
    global $DB;
    $conditions = array("itemid" => $bookmarkid);
    $comments = $DB->get_records($table, $conditions);

    if ($comments) {
        foreach ($comments as $comment) {
            unset($thisitem);
            unset($thisitemtitle);
            unset($thisitemtext);
            unset($thisitemauthor);

            $itemidentifier = $ridentifier;
            $ridentifier++;

            $conditions = array("id" => $comment->userid);
            $user = $DB->get_record('user', $conditions);

            $thisitem = & $item->createChild('epx:item');
            $thisitem->attribute('id', 'comment' . $itemidentifier);
            $thisitem->attribute('created', date("c", $comment->timemodified));
            $thisitem->attribute('lastedited', date("c", $comment->timemodified));
            $thisitem->attribute('editable', 'false');
            $thisitem->attribute('visible', 'true');
            $thisitem->attribute('xsi:type', 'epx:commentType');
            $thisitem->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

            $thisitemauthor = & $thisitem->createChild('epx:author');
            $thisitemauthor->cdata(spch(fullname($user, $comment->userid)));
            $thisitemtitle = & $thisitem->createChild('epx:title');
            $thisitemtitle->cdata('(no title)');
            $thisitemtext = & $thisitem->createChild('epx:text');
            $thisitemtext->cdata(spch($comment->entry));
        }
    }
}

function get_category_items($categoryid, $viewid=null, $type=null) {
    global $USER, $CFG, $DB;
    //$conditions = array("id" => $courseid);

    $itemQuery = "select i.*" .
            " FROM {block_exaportitem} AS i" .
            ($viewid ? " JOIN {block_exaportviewblock} AS vb ON vb.type='item' AND vb.viewid=" . $viewid . " AND vb.itemid=i.id" : '') .
            " where i.userid = $USER->id" .
            ($type ? " and i.type='$type'" : '') .
            " and i.categoryid = $categoryid" .
            " order by i.name desc";

    return $DB->get_records_sql($itemQuery);
}

function block_exaport_get_epx_category_content(&$xmlElement, &$resources, $id, $exportpath, $export_dir, &$ridentifier, $viewid) {
    global $USER, $CFG, $COURSE;

    $bookmarks = get_category_items($id, $viewid, 'link');

    if ($bookmarks) {
        foreach ($bookmarks as $bookmark) {
            unset($thisitem);
            unset($thisitemtitle);
            unset($thisitemdescription);
            unset($thisitemurl);
            unset($itemref);

            $itemidentifier = $ridentifier;
            $ridentifier++;

            $thisitem = & $resources->createChild('epx:item');
            $thisitem->attribute('id', 'link' . $itemidentifier);
            $thisitem->attribute('created', date("c", $bookmark->timemodified));
            $thisitem->attribute('lastedited', date("c", $bookmark->timemodified));
            $thisitem->attribute('editable', 'true');
            $thisitem->attribute('visible', 'true');
            $thisitem->attribute('xsi:type', 'epx:linkType');
            $thisitem->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

            block_exaport_get_comments_for_item($thisitem, 'block_exaportitemcomm', $bookmark->id, $ridentifier);

            $thisitemtitle = & $thisitem->createChild('epx:title');
            $thisitemtitle->cdata(spch($bookmark->name));
            $thisitemdescription = & $thisitem->createChild('epx:description');
            $thisitemdescription->cdata(spch($bookmark->intro));
            $thisitemurl = & $thisitem->createChild('epx:url');
            $thisitemurl->cdata(spch($bookmark->url));


            $itemref = & $xmlElement->createChild('epx:itemref');
            $itemref->attribute('id', 'link' . $itemidentifier);
        }
    }

    $files = get_category_items($id, $viewid, 'file');

    if ($files) {
        foreach ($files as $file) {
            unset($thisitem);
            unset($thisitemtitle);
            unset($thisitemdescription);
            unset($thisitemuri);
            unset($itemref);

            $itemidentifier = $ridentifier;
            $ridentifier++;

            $i = 0;
            $content_filename = $file->attachment;
            if (is_file($exportpath . $export_dir . $content_filename) || is_dir($exportpath . $export_dir . $content_filename) || is_link($exportpath . $export_dir . $content_filename)) {
                do {
                    $i++;
                    $content_filename = $i . $file->attachment;
                } while (is_file($exportpath . $export_dir . $content_filename) || is_dir($exportpath . $export_dir . $content_filename) || is_link($exportpath . $export_dir . $content_filename));
            }

            if ($file->attachment != '') {
                copy($CFG->dataroot . "/" . block_exaport_file_area_name($file) . "/" . $file->attachment, $exportpath . $export_dir . $content_filename);
            }

            $thisitem = & $resources->createChild('epx:item');
            $thisitem->attribute('id', 'file' . $itemidentifier);
            $thisitem->attribute('created', date("c", $file->timemodified));
            $thisitem->attribute('lastedited', date("c", $file->timemodified));
            $thisitem->attribute('editable', 'true');
            $thisitem->attribute('visible', 'true');
            $thisitem->attribute('xsi:type', 'epx:fileType');
            $thisitem->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

            block_exaport_get_comments_for_item($thisitem, 'block_exaportitemcomm', $file->id, $ridentifier);

            $thisitemtitle = & $thisitem->createChild('epx:title');
            $thisitemtitle->cdata(spch($file->name));
            $thisitemdescription = & $thisitem->createChild('epx:text');
            $thisitemdescription->cdata(spch($file->intro));
            $thisitemuri = & $thisitem->createChild('epx:uri');
            $thisitemuri->cdata(spch('epx:' . $export_dir . $content_filename));


            $itemref = & $xmlElement->createChild('epx:itemref');
            $itemref->attribute('id', 'file' . $itemidentifier);
        }
    }

    $notes = get_category_items($id, $viewid, 'note');

    if ($notes) {
        foreach ($notes as $note) {
            unset($thisitem);
            unset($thisitemtitle);
            unset($thisitemdescription);
            unset($itemref);

            $itemidentifier = $ridentifier;
            $ridentifier++;

            $thisitem = & $resources->createChild('epx:item');
            $thisitem->attribute('id', 'note' . $itemidentifier);
            $thisitem->attribute('created', date("c", $note->timemodified));
            $thisitem->attribute('lastedited', date("c", $note->timemodified));
            $thisitem->attribute('editable', 'true');
            $thisitem->attribute('visible', 'true');
            $thisitem->attribute('xsi:type', 'epx:noteType');
            $thisitem->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

            block_exaport_get_comments_for_item($thisitem, 'block_exaportitemcomm', $note->id, $ridentifier);

            $thisitemtitle = & $thisitem->createChild('epx:title');
            $thisitemtitle->cdata(spch($note->name));
            $thisitemdescription = & $thisitem->createChild('epx:text');
            $thisitemdescription->cdata(spch($note->intro));


            $itemref = & $xmlElement->createChild('epx:itemref');
            $itemref->attribute('id', 'note' . $itemidentifier);
        }
    }

    return true;
}

if ($confirm) {
    if (!confirm_sesskey()) {
        error('Bad Session Key');
    }

    if (!($exportdir = make_upload_directory(export_data_file_area_name()))) {
        error("Could not create temporary folder!");
    }

    // Delete everything inside
    remove_dir($exportdir, true);

    // Put a / on the end
    if (substr($exportdir, -1) != "/")
        $exportdir .= "/";

    // Create directory for data files
    $export_data_dir = $exportdir . "data";
    // Create directory for data files:
    mkdir($export_data_dir);
    if (substr($export_data_dir, -1) != "/")
        $export_data_dir .= "/";

    $sourcefiles = array();
    $sourcefiles[] = $export_data_dir;

    // copy all necessary files:
    copy("epxfiles/comment.xsd", $exportdir . "comment.xsd");
    $sourcefiles[] = $exportdir . "comment.xsd";
    copy("epxfiles/epx.xsd", $exportdir . "epx.xsd");
    $sourcefiles[] = $exportdir . "epx.xsd";
    copy("epxfiles/file.xsd", $exportdir . "file.xsd");
    $sourcefiles[] = $exportdir . "file.xsd";
    copy("epxfiles/link.xsd", $exportdir . "link.xsd");
    $sourcefiles[] = $exportdir . "link.xsd";
    copy("epxfiles/note.xsd", $exportdir . "note.xsd");
    $sourcefiles[] = $exportdir . "note.xsd";
    $parsedDoc = new MiniXMLDoc();

    $xmlRoot = & $parsedDoc->getRoot();

    $epxroot = & $xmlRoot->createChild('epx:epx');
    $epxroot->attribute('version', '1.0');
    $epxroot->attribute('xmlns:epx', 'https://opensvn.csie.org/epoman/EPX');

    $epxportfolio = & $epxroot->createChild('epx:portfolio');
    $epxportfolio->attribute('created', date("c"));
    $epxportfolio->attribute('name', 'Portfolio exported from Moodle');

    // epx: personal information
    $epxinformation = & $epxportfolio->createChild('epx:information');
    // TODO: this date is just the current date. Set it to the date of the last edit of the description!!!
    $epxinformation->attribute('lastedited', date("c"));

    $epxusername = & $epxinformation->createChild('epx:username');
    $epxusername->cdata($USER->username);
    $epxforename = & $epxinformation->createChild('epx:forename');
    $epxforename->cdata($USER->firstname);
    $epxsurname = & $epxinformation->createChild('epx:surename');
    $epxsurname->cdata($USER->lastname);
    $epxdescription = & $epxinformation->createChild('epx:description');
    $epxsurname->cdata('');
    $epxcontacts = & $epxinformation->createChild('epx:contacts');

    $epxcategories = & $epxportfolio->createChild('epx:categories');
    $epxitems = & $epxportfolio->createChild('epx:items');

    //echo '<div class="block_exaport_export">';
    //echo "<h3>" . get_string("categories","block_exaport") . "</h3>";


    $owncats = $DB->get_records("block_exaportcate", array("userid" => $USER->id, "pid" => 0), "name ASC");
    $i = 0;


    if ($owncats) {
        foreach ($owncats as $owncat) {
            unset($thiscategory);
            unset($thiscategorytitle);
            $i++;

            $thiscategory = & $parsedDoc->createElement('epx:category');
            $thiscategorytitle = & $thiscategory->createChild('epx:title');
            $thiscategorytitle->cdata(spch($owncat->name));

            $mainNotEmpty = block_exaport_get_epx_category_content($thiscategory, $epxitems, $owncat->id, $exportdir, 'data/', $ridentifier, $viewid);

            $innerowncats = $DB->get_records("block_exaportcate", array("userid"=>$USER->id,"pid"=>$owncat->id), "name ASC");
            if ($innerowncats) {
                foreach ($innerowncats as $innerowncat) {
                    unset($thissubcategory);
                    unset($thissubcategorytitle);
                    $i++;

                    $thissubcategory = & $parsedDoc->createElement('epx:subcategory');
                    $thissubcategorytitle = & $thissubcategory->createChild('epx:title');
                    $thissubcategorytitle->cdata(spch($innerowncat->name));

                    $subNotEmpty = block_exaport_get_epx_category_content($thissubcategory, $epxitems, $innerowncat->id, $exportdir, 'data/', $ridentifier, $viewid);

                    if ($subNotEmpty) {
                        // if the subcategory is not empty:
                        //	-> append it to the maincategory
                        //  -> set the main category as not empty
                        $thiscategory->appendChild($thissubcategory);
                        $mainNotEmpty = true;
                    }
                }
            }

            if ($mainNotEmpty) {
                // if the main category is not empty, append it to the xml-file
                $epxcategories->appendChild($thiscategory);
            }
        }
    }

    if (file_put_contents($exportdir . 'portfolio.xml', $parsedDoc->toString(MINIXML_NOWHITESPACES)) === false) {
        error("Writing portfolio.xml failed!");
        exit();
    }
    $sourcefiles[] = $exportdir . "portfolio.xml";

    // create directory for the zip-file:
    if (!($zipdir = make_upload_directory(export_file_area_name()))) {
        error(get_string("couldntcreatetempdir", "block_exaport"));
        exit();
    }

    // Delete everything inside
    remove_dir($zipdir, true);

    // Put a / on the end
    if (substr($zipdir, -1) != "/")
        $zipdir .= "/";

    $zipname = clean_param($USER->username, PARAM_ALPHANUM) . strftime("_%Y_%m_%d_%H%M") . ".epx";

    // zip all the files:
    zip_files($sourcefiles, $zipdir . $zipname);

    remove_dir($exportdir);

    echo '<div class="block_eportfolio_center">';
    print_simple_box_start("center", "40%", "#ccffbb");

    echo '<input type="submit" name="export" value="' . get_string("download", "block_exaport") . '"';
    echo ' onclick="window.open(\'' . $CFG->wwwroot . '/blocks/exaport/portfoliofile.php/' . export_file_area_name() . '/' . $zipname . '\');"/>';
    echo '</div>';

    print_simple_box_end();
    print_footer($course);
    exit;
}

echo "<br />";
echo '<div class="block_eportfolio_center">';

$views = $DB->get_records('block_exaportview', array('userid' => $USER->id), 'name');

$OUTPUT->box_start();

echo '<p>' . get_string("explainexport", "block_exaport") . '</p>';
echo '<form method="post" class="block_eportfolio_center" action="' . $_SERVER['PHP_SELF'] . '" >';
echo '<fieldset>';

// views
if (block_exaport_feature_enabled('views')) {
    echo '<div style="padding-bottom: 15px;">' . get_string("exportviewselect", "block_exaport") . ': ';
    echo '<select name="viewid">';
    echo '<option></option>';
    foreach ($views as $view) {
        echo '<option value="' . $view->id . '">' . $view->name . '</option>';
    }
    echo '</select>';
    echo ' </div>';
}

echo '<input type="hidden" name="confirm" value="1" />';
echo '<input type="submit" name="export" value="' . get_string("createepxexport", "block_exaport") . '" />';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
echo '</fieldset>';
echo '</form>';
echo '</div>';

$OUTPUT->box_end();
$OUTPUT->footer($course);
