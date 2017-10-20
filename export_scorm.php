<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';
require_once __DIR__.'/lib/minixml.inc.php';
global $DB, $CFG;

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

global $zip, $existingfilesArray;
$zip = block_exacomp_ZipArchive::create_temp_file();
$existingfilesArray = array();

$courseid = optional_param("courseid", 0, PARAM_INT);
$confirm = optional_param("confirm", 0, PARAM_INT);
$viewid = optional_param("viewid", 0, PARAM_INT);
$with_directory = optional_param("with_directory", 0, PARAM_INT);
$identifier = 1000000; // Item identifier
$ridentifier = 1000000; // Ressource identifier

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
if (!$confirm)
	block_exaport_print_header("importexport", "exportimportexport");

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

function &create_item(&$pitem, $identifier, $titletext, $residentifier = '', $id=null) {
	// at an external ressource no file is needed inside resource
	$item = & $pitem->createChild('item');
	$item->attribute('identifier', $identifier);
	$item->attribute('isvisible', 'true');
	if($id)$item->attribute('id', $id);
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
	return "exaport/temp/exportdataDir/{$USER->id}";
}

function add_comments($table, $bookmarkid) {
	global $DB;

	$commentsContent = '';
	$conditions = array("itemid" => $bookmarkid);
	$comments = $DB->get_records($table, $conditions);
	$i = 1;
	if ($comments) {
		foreach ($comments as $comment) {
			$conditions = array("id" => $comment->userid);
			$user = $DB->get_record('user', $conditions);

			$commentsContent .= '
			<div id="comment">
				<div id="author"><!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###-->' . fullname($user, $comment->userid) . '<!--###BOOKMARK_COMMENT(' . $i . ')_AUTHOR###--></div>
				<div id="date"><!--###BOOKMARK_COMMENT(' . $i . ')_TIME###-->' . userdate($comment->timemodified) . '<!--###BOOKMARK_COMMENT(' . $i . ')_TIME###--></div>
				<div id="content"><!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###-->' . spch_text($comment->entry) . '<!--###BOOKMARK_COMMENT(' . $i . ')_CONTENT###--></div>
			</div>';
			$i++;
		}
	}
	return $commentsContent;
}

function get_category_items($categoryid, $viewid=null, $type=null) {
	global $USER, $CFG, $DB;

	$conditions = array();
	if(strcmp($CFG->dbtype, "sqlsrv")==0){
		$itemQuery = "SELECT i.*" .
				" FROM {block_exaportitem} i" .
				($viewid ? " JOIN {block_exaportviewblock} vb ON cast(vb.type AS varchar(11))='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
				" WHERE i.userid = ?" .
				($type ? " AND i.type=?" : '') .
				" AND i.categoryid = ?" .
				" ORDER BY i.name desc";				
	}else{
		$itemQuery = "SELECT i.*" .
				" FROM {block_exaportitem} i" .
				($viewid ? " JOIN {block_exaportviewblock} vb ON vb.type='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
				" WHERE i.userid = ?" .
		 		($type ? " AND i.type=?" : '') .
				" AND i.categoryid =?" .
				" ORDER BY i.name desc";
	}
	if ($viewid)
		$conditions[] = $viewid;
	$conditions[] = $USER->id;
	if ($type)
		$conditions[] = $type;
	$conditions[] = $categoryid;	
	
	return $DB->get_records_sql($itemQuery, $conditions);
}

function get_category_files($categoryid, $viewid=null) {
	global $USER, $CFG, $DB;

	$conditions = array();
	if(strcmp($CFG->dbtype, "sqlsrv")==0){
		$itemQuery = "select ".($viewid ? " vb.id as vbid," : "")." i.*" .
				" FROM {block_exaportitem} i" .
				($viewid ? " JOIN {block_exaportviewblock} vb ON cast(vb.type AS varchar(11))='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
				" WHERE i.userid = ?" .
				" AND i.type='file'" .
				" AND i.categoryid = ?" .
				" ORDER BY i.name desc";
	}
	else{
		$itemQuery = "select ".($viewid ? " vb.id as vbid," : "")."i.*" .
			" FROM {block_exaportitem} i" .
			($viewid ? " JOIN {block_exaportviewblock} vb ON vb.type='item' AND vb.viewid=? AND vb.itemid=i.id" : '') .
			" WHERE i.userid = ?" .
			" AND i.type='file'" .
			" AND i.categoryid = ?" .
			" ORDER BY i.name desc";
	}
	if ($viewid)
		$conditions[] = $viewid;
	$conditions[] = $USER->id;
	$conditions[] = $categoryid;
	return $DB->get_records_sql($itemQuery, $conditions);
}

function get_category_content(&$xmlElement, &$resources, $id, $name, $exportpath, $export_dir, &$identifier, &$ridentifier, $viewid, &$itemscomp, $depth=0, $with_directory=false) {
	global $USER, $CFG, $COURSE, $DB, $zip, $existingfilesArray;
	$indexfileItems = '';

	// index file for category
	if ($with_directory) {
		$indexfilecontent = '';
		$indexfilecontent .= createHTMLHeader(spch($name), $depth+1);
		$indexfilecontent .= '<body>' . "\n";
		$indexfilecontent .= '<div id="exa_ex">' . "\n";			
		$indexfilecontent .= '<h1>'.get_string("current_category", "block_exaport").': '.spch($name).'</h1>' . "\n";
		// subcategory links
		$cats = $DB->get_records_select("block_exaportcate", "userid=$USER->id AND pid='$id'", null, "name ASC");
		if ($cats) {
			$indexfilecontent .= '<h2>'.get_string("categories", "block_exaport").'</h2>';
			$indexfilecontent .= '<ul>';
			foreach ($cats as $cat) {
				$subdirName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $cat->name);
				$subdirName = mb_ereg_replace("([\.]{2,})", '', $subdirName);
				$indexfilecontent .= '<li><a href="'.$subdirName.'/index.html">'.$cat->name.'</a></li>';
			}
			$indexfilecontent .= '</ul>';
		}
	}
	$bookmarks = get_category_items($id, $viewid, 'link');
	
	$hasItems = false;

	if ($bookmarks) {
		$hasItems = true;
		foreach ($bookmarks as $bookmark) {		
			if(block_exaport_check_competence_interaction()){
				//begin
				$compids = block_exaport_get_active_compids_for_item($bookmark);

				if($compids){									
					$competences = "";
					$competencesids = array();
					foreach($compids as $compid){

						$conditions = array("id" => $compid);
						$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING);
						if($competencesdb != null){
							$competences .= $competencesdb->title.'<br />';
							array_push($competencesids, $competencesdb->sourceid);
						}
					}
					$competences = str_replace("\r", "", $competences);
					$competences = str_replace("\n", "", $competences);
					$bookmark->competences = $competences;
					
					$itemscomp[$bookmark->id] = $competencesids;
					
				}
			}
			//end
			unset($filecontent);
			unset($filename);

			$filecontent = '';
			$filecontent = createHTMLHeader(spch(format_string($bookmark->name)), $depth+1);
			$filecontent .= '<body>' . "\n";
			$filecontent .= '<div id="exa_ex">' . "\n";			
			$filecontent .= '  <h1 id="header">' . spch(format_string($bookmark->name)) . '</h1>' . "\n";
			$filecontent .= '  <div id="url"><a href="' . spch($bookmark->url) . '"><!--###BOOKMARK_EXT_URL###-->' . spch($bookmark->url) . '<!--###BOOKMARK_EXT_URL###--></a></div>' . "\n";
			$filecontent .= '  <div id="description"><!--###BOOKMARK_EXT_DESC###-->' . spch_text($bookmark->intro) . '<!--###BOOKMARK_EXT_DESC###--></div>' . "\n";
			$filecontent .= add_comments('block_exaportitemcomm', $bookmark->id);
			if(isset($bookmark->competences)) $filecontent .= '<br /> <div id="competences">'.$bookmark->competences.'<div>';
			$filecontent .= '</div>' . "\n";
			$filecontent .= '</body>' . "\n";
			$filecontent .= '</html>' . "\n";

			list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $export_dir, $bookmark->name);

			$zip->addFromString($exportpath . $filepath, $filecontent);
			create_ressource($resources, 'RES-' . $ridentifier, $filepath);
			create_item($xmlElement, 'ITEM-' . $identifier, $bookmark->name, 'RES-' . $ridentifier, $bookmark->id);
			
			if ($with_directory) {
				$indexfileItems .= '<li><a href="'.$resfilename.'">'.$bookmark->name.'</a></li>';			
			}
		
			$identifier++;
			$ridentifier++;
		}
	}

	$files = get_category_files($id, $viewid);
	//!!
	if ($files) {
		$fs = get_file_storage();
		$hasItems = true;

		foreach ($files as $file) {
			if(block_exaport_check_competence_interaction()){
				$compids = block_exaport_get_active_compids_for_item($file);
			
				if($compids){
					$competences = "";
					$competencesids = array();
					foreach($compids as $compid){
				
						$conditions = array("id" => $compid);
						$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING); 
						if($competencesdb != null){
							$competences .= $competencesdb->title.'<br />';
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

			$fsFile = block_exaport_get_item_file($file);
			if (!$fsFile) continue;

			$i = 0;
			$content_filename = $fsFile->get_filename();
			while (in_array($exportpath . $export_dir . $content_filename, $existingfilesArray)) {
				$i++;
				$content_filename = $i . '-' . $fsFile->get_filename();
			}
			$existingfilesArray[] = $exportpath . $export_dir . $content_filename;

			$zip->addFromString($exportpath . $export_dir . $content_filename, $fsFile->get_content());

			$filecontent = '';
			$filecontent = createHTMLHeader(spch($file->name), $depth+1);
			$filecontent .= '<body>' . "\n";
			$filecontent .= '<div id="exa_ex">' . "\n";
			$filecontent .= '  <h1 id="header">' . spch($file->name) . '</h1>' . "\n";
			$filecontent .= '  <div id="url"><a href="' . spch($content_filename) . '"><!--###BOOKMARK_FILE_URL###-->' . spch($content_filename) . '<!--###BOOKMARK_FILE_URL###--></a></div>' . "\n";
			$filecontent .= '  <div id="description"><!--###BOOKMARK_FILE_DESC###-->' . spch_text($file->intro) . '<!--###BOOKMARK_FILE_DESC###--></div>' . "\n";
			$filecontent .= add_comments('block_exaportitemcomm', $file->id);
			if(isset($file->competences)) $filecontent .= '<br /> <div id="competences">'.$file->competences.'<div>';
			$filecontent .= '</div>' . "\n";
   			$filecontent .= '</body>' . "\n";
			$filecontent .= '</html>' . "\n";

			list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $export_dir, $file->name);
			$zip->addFromString($exportpath . $filepath, $filecontent);
			create_ressource($resources, 'RES-' . $ridentifier, $filepath);
			create_item($xmlElement, 'ITEM-' . $identifier, $file->name, 'RES-' . $ridentifier, $file->id);
			
			if ($with_directory) {
				$indexfileItems .= '<li><a href="'.$resfilename.'">'.$file->name.'</a></li>';			
			}
			
			$identifier++;
			$ridentifier++;
		}
	}

	$notes = get_category_items($id, $viewid, 'note');

	if ($notes) {
		$hasItems = true;
		foreach ($notes as $note) {
			if(block_exaport_check_competence_interaction()){
				$compids = block_exaport_get_active_compids_for_item($note);

				if($compids){
					$competences = "";
					$competencesids = array();
					foreach($compids as $compid){

						$conditions = array("id" => $compid);
						$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING);
						if($competencesdb != null){
							$competences .= $competencesdb->title.'<br />';
							array_push($competencesids, $competencesdb->sourceid);
						}
					}
					$competences = str_replace("\r", "", $competences);
					$competences = str_replace("\n", "", $competences);
					
					$note->competences = $competences;
					$itemscomp[$note->id]=$competencesids;
	
				}
			}
			unset($filecontent);
			unset($filename);

			$filecontent = '';
			$filecontent .= createHTMLHeader(spch($note->name), $depth+1);
			$filecontent .= '<body>' . "\n";
			$filecontent .= '<div id="exa_ex">' . "\n";			
			$filecontent .= '  <h1 id="header">' . spch($note->name) . '</h1>' . "\n";
			$filecontent .= '  <div id="description"><!--###BOOKMARK_NOTE_DESC###-->' . spch_text($note->intro) . '<!--###BOOKMARK_NOTE_DESC###--></div>' . "\n";
			$filecontent .= add_comments('block_exaportitemcomm', $note->id);
			if(isset($note->competences)) $filecontent .= '<br /> <div id="competences">'.$note->competences.'<div>';
			$filecontent .= '</div>' . "\n";
			$filecontent .= '</body>' . "\n";
			$filecontent .= '</html>' . "\n";

			list ($resfilename, $filepath) = get_htmlfile_name_path($exportpath, $export_dir, $note->name);
			$zip->addFromString($exportpath . $filepath, $filecontent);
			create_ressource($resources, 'RES-' . $ridentifier, $filepath);
			create_item($xmlElement, 'ITEM-' . $identifier, $note->name, 'RES-' . $ridentifier, $note->id);
			
			if ($with_directory) {
				$indexfileItems .= '<li><a href="'.$resfilename.'">'.$note->name.'</a></li>';			
			}
			
			$identifier++;
			$ridentifier++;
		}
	}
	if ($hasItems && $with_directory) {
		$indexfilecontent .= '<h2>'.get_string("listofartefacts", "block_exaport").'</h2>';
		$indexfilecontent .= '<ul>';
		$indexfilecontent .= $indexfileItems;
		$indexfilecontent .= '</ul>';
	}
	if ($with_directory) {
		$indexfilecontent .= '</div>' . "\n";
		$indexfilecontent .= '</body>' . "\n";
		$indexfilecontent .= '</html>' . "\n";
		$zip->addFromString($exportpath . $export_dir . 'index.html', $indexfilecontent);
	}

	return $hasItems;
}

function rekcat($owncats, $parsedDoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $organization, $i, &$itemscomp, $subdirname, $depth, $with_directory=false) {	
	global $DB, $USER, $zip;
	$return = false;
	
	foreach ($owncats as $owncat) {
		// directory for category
		if (!$with_directory) {
			$newSubdir = '';
		} else if ($owncat->id == 0 && $owncat->name == 'Root') {
			// root category
			$newSubdir = '';	
		} else {
			$newSubdir = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $owncat->name);
			$newSubdir = mb_ereg_replace("([\.]{2,})", '', $newSubdir);
			$zip->addEmptyDir($exportdir.$subdirname.$newSubdir);
			if (substr($newSubdir, -1) != "/")
				$newSubdir .= "/";
		}
		if ($owncat->id == 0) {
			// ignore root virtual category
			$item = $organization;
		} else {
			$i++;
			$item = & $parsedDoc->createElement('item');
			$item->attribute('identifier', sprintf('B%04d', $i));
			$item->attribute('isvisible', 'true');
			$itemtitle = & $item->createChild('title');
			$itemtitle->text($owncat->name);
		}
		// get everything inside this category:
		$mainNotEmpty = get_category_content($item, $resources, $owncat->id, $owncat->name, $exportdir, $subdirname.$newSubdir, $identifier, $ridentifier, $viewid, $itemscomp, $depth, $with_directory);

		$innerowncats = $DB->get_records_select("block_exaportcate", "userid=$USER->id AND pid='$owncat->id'", null, "name ASC");
		if ($innerowncats) {
			$value = rekcat($innerowncats, $parsedDoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $item, $i, $itemscomp, $subdirname.$newSubdir, $depth+1, $with_directory);
			if ($value) 
				$mainNotEmpty = $value;
		}

		if ($mainNotEmpty) {
			// if the main category is not empty, append it to the xml-file
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

function createXMLcomps($itemscomp, $exportdir){
global $USER, $zip;
	$parsedDoc = new MiniXMLDoc();

	$xmlRoot = & $parsedDoc->getRoot();

	// Root-Element MANIFEST
	$manifest = & $xmlRoot->createChild('manifest');
	$manifest->attribute('identifier', $USER->username . 'Export');
	$manifest->attribute('version', '1.1');
	$manifest->attribute('xmlns', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2');
	$manifest->attribute('xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
	$manifest->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
	$manifest->attribute('xsi:schemaLocation', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
					  http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
					  http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd');

	$items = & $manifest->createChild('items');
	$items->attribute('default', 'DATA');

	foreach($itemscomp as $key => $values){
	
		$item= & $items->createChild('item');
		$item->attribute('identifier',$key);

		foreach($values as $value){
			$comp = & $item->createChild('comp');
			$comp->attribute('identifier',$value);
		}
	}
	
	$zip->addFromString('itemscomp.xml', $parsedDoc->toString(MINIXML_NOWHITESPACES));
}

if ($confirm) {
	if (!confirm_sesskey()) {
		error('Bad Session Key');
	}

	$exportdir = '';

	// Put a / on the end
	if (substr($exportdir, -1) != "/")
		$exportdir .= "/";

	// Create directory for data files:
	$export_data_dir = $exportdir . "data";
	$zip->addEmptyDir($export_data_dir);	
	if (substr($export_data_dir, -1) != "/")
		$export_data_dir .= "/";
	
	// Create directory for categories
	if ($with_directory) {
		$categoriesSubdirName = "categories";
		$export_categories_dir = $exportdir . $categoriesSubdirName;
		$zip->addEmptyDir($export_categories_dir);	
		if (substr($export_categories_dir, -1) != "/") {
			$export_categories_dir .= "/";
			$categoriesSubdirName .= "/";
		};
	} else {
		$categoriesSubdirName = $export_data_dir;
	}

	// copy all necessary files:
	$zip->addFromString('adlcp_rootv1p2.xsd', file_get_contents('files/adlcp_rootv1p2.xsd'));
	$zip->addFromString('ims_xml.xsd', file_get_contents('files/ims_xml.xsd'));
	$zip->addFromString('imscp_rootv1p1p2.xsd', file_get_contents('files/imscp_rootv1p1p2.xsd'));
	$zip->addFromString('imsmd_rootv1p2p1.xsd', file_get_contents('files/imsmd_rootv1p2p1.xsd'));
	$zip->addFromString('export_style.css', file_get_contents('files/export_style.css'));

	$parsedDoc = new MiniXMLDoc();

	$xmlRoot = & $parsedDoc->getRoot();

	// Root-Element MANIFEST
	$manifest = & $xmlRoot->createChild('manifest');
	$manifest->attribute('identifier', $USER->username . 'Export');
	$manifest->attribute('version', '1.1');
	$manifest->attribute('xmlns', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2');
	$manifest->attribute('xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
	$manifest->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
	$manifest->attribute('xsi:schemaLocation', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
					  http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
					  http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd');

	// Our Organizations
	$organizations = & $manifest->createChild('organizations');
	$organizations->attribute('default', 'DATA');

	// Our organization for the export structure
	$desc_organization = & $organizations->createChild('organization');
	$desc_organization->attribute('identifier', 'DATA');

	$title = & $desc_organization->createChild('title');
	$title->text(get_string("personal", "block_exaport"));

	// Our organization for the export structure
	$organization = & $organizations->createChild('organization');
	$organization->attribute('identifier', 'PORTFOLIO');

	// Our resources
	$resources = & $manifest->createChild('resources');

	// Root entry in organization
	$title = & $organization->createChild('title');
	$title->text(get_string("myportfolio", "block_exaport"));

	$userdescriptions = $DB->get_records_select("block_exaportuser", "user_id = '$USER->id'");

	$description = '';
	if ($userdescriptions) {
		foreach ($userdescriptions as $userdescription) {
			$description = $userdescription->description;
			if(strncmp($description, "<img", strlen("<img"))){
				$description=str_replace("@@PLUGINFILE@@/", "personal/", $description);
			}
		}
	}

	$filecontent = '';
	$filecontent .= createHTMLHeader(spch(fullname($USER, $USER->id)), 1);
	$filecontent .= '<body>' . "\n";
	$filecontent .= '	<div id="exa_ex">' . "\n";
	$filecontent .= '  <h1 id="header">' . spch(fullname($USER, $USER->id)) . '</h1>' . "\n";
	$filecontent .= '  <div id="description"><!--###BOOKMARK_PERSONAL_DESC###-->' . spch_text($description) . '<!--###BOOKMARK_PERSONAL_DESC###--></div>' . "\n";
	$filecontent .= '</div>' . "\n";
	$filecontent .= '</body>' . "\n";
	$filecontent .= '</html>' . "\n";
	
	list ($profilefilename, $filepath) = get_htmlfile_name_path($exportdir, 'data/', fullname($USER, $USER->id));
	$filepath_to_personal = $filepath;

	$zip->addFromString($exportdir . $filepath, $filecontent);
	
	create_ressource($resources, 'RES-' . $ridentifier, $filepath);
	create_item($desc_organization, 'ITEM-' . $identifier, fullname($USER, $USER->id), 'RES-' . $ridentifier);

	$identifier++;
	$ridentifier++;

	//categories
	// virtual root category
	$owncat = new stdClass();
	$owncat->id = 0;
	$owncat->name = 'Root';
	$owncats = array();
	$owncats[] = $owncat;
	
	$i = 0;
	
	$itemscomp = array();
	
	if ($owncats) {
		rekcat($owncats, $parsedDoc, $resources, $exportdir, $identifier, $ridentifier, $viewid, $organization, $i, $itemscomp, $categoriesSubdirName, 0, $with_directory);
	}
	
	//save files, from personal information
	$fs = get_file_storage();
	$areafiles = $fs->get_area_files(context_user::instance($USER->id)->id,'block_exaport', 'personal_information');
	$areafiles_exist = false;
	foreach ($areafiles as $areafile){
		if (!$areafile) continue;
		
		if(strcmp($areafile->get_filename(),".")!=0){	
			$zip->addEmptyDir($exportdir."data/personal/");	
			
			$i = 0;
			$content_filename = $areafile->get_filename();
			while (in_array($exportdir ."data/personal/". $content_filename, $existingfilesArray)) {
				$i++;
				$content_filename = $i . '-' . $areafile->get_filename();
			}
			$existingfilesArray[] = $exportdir . $content_filename;
			
			$zip->addFromString($exportdir ."data/personal/". $content_filename, $areafile->get_content());
			$areafiles_exist = true;
		}
	
	}
	
	// main index.html
	if ($with_directory) {
		$filecontent = '';
		$filecontent .= createHTMLHeader(spch(fullname($USER, $USER->id)), 0);
		$filecontent .= '<body>' . "\n";
		$filecontent .= '	<div id="exa_ex">' . "\n";
		$filecontent .= '  <h1 id="header">' . spch(fullname($USER, $USER->id)) . '</h1>' . "\n";
		$filecontent .= '  <div id="description"><!--###BOOKMARK_PERSONAL_DESC###-->' . spch_text($description) . '<!--###BOOKMARK_PERSONAL_DESC###--></div>' . "\n";
		$filecontent .= '  <ul>' . "\n";
		$filecontent .= '  <li><a href="'.$filepath_to_personal.'">' . get_string("explainpersonal", "block_exaport") . '</a></li>' . "\n";
		$filecontent .= '  <li><a href="'.$categoriesSubdirName.'index.html">' . get_string("myportfolio", "block_exaport") . '</a></li>' . "\n";
		if ($areafiles_exist)
			$filecontent .= '  <li><a href="data/personal/">' . get_string("myfilearea", "block_exaport") . '</a></li>' . "\n";	
		$filecontent .= '  </ul>' . "\n";
		$filecontent .= '</div>' . "\n";
		$filecontent .= '</body>' . "\n";
		$filecontent .= '</html>' . "\n";
	}
	$zip->addFromString($exportdir . 'index.html', $filecontent);
	
	//begin
	createXMLcomps($itemscomp, $exportdir);
	//end
	
	// if there's need for metadata, put it in:
	//$metadata =& $organization->createChild('metadata');
	//$schema =& $metadata->createChild('schema');
	//$schema->text('ADL SCORM');
	//$schemaversion =& $metadata->createChild('schemaversion');
	//$schemaversion->text('1.2');
	// echo $parsedDoc->toString(); exit;

	$zip->addFromString($exportdir . 'imsmanifest.xml', $parsedDoc->toString(MINIXML_NOWHITESPACES));

	$zipname = clean_param($USER->username, PARAM_ALPHANUM) . strftime("_%Y_%m_%d_%H%M") . ".zip";

/**/
    // return zip
	$zipfile = $zip->filename;
	$zip->close();
	header('Content-Type: application/zip');
	header('Content-Length: ' . filesize($zipfile));
	header('Content-Disposition: attachment; filename="'.$zipname.'"');
	readfile($zipfile);
	unlink($zipfile);
	exit;	
}

echo "<br />";
echo '<div class="block_eportfolio_center">';


if (strcmp($CFG->dbtype, "sqlsrv")==0) 
	$views = $DB->get_records('block_exaportview', array('userid' => $USER->id), 'cast(name AS varchar(max))');
else 
	$views = $DB->get_records('block_exaportview', array('userid' => $USER->id), 'name');

echo $OUTPUT->box_start();

echo '<p>' . get_string("explainexport", "block_exaport") . '</p>';
echo '<form method="post" class="block_eportfolio_center" action="' . $_SERVER['PHP_SELF'] . '" >';
echo '<fieldset>';

echo '<div style="padding-bottom: 15px;">';
// views
if (block_exaport_feature_enabled('views')) {
	echo get_string("exportviewselect", "block_exaport") . ': ';
	echo '<select name="viewid">';
	echo '<option></option>';
	foreach ($views as $view) {
		echo '<option value="' . $view->id . '">' . $view->name . '</option>';
	}
	echo '</select>';
}

echo '<label><input type="checkbox" name="with_directory" value="1" />'.get_string("add_directory_structure", "block_exaport").'</label>';
echo ' </div>';

echo '<input type="hidden" name="confirm" value="1" />';
echo '<input type="submit" name="export" value="' . get_string("createexport", "block_exaport") . '" class="btn btn-default"/>';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<input type="hidden" name="courseid" value="' . $courseid . '" />';
echo '</fieldset>';
echo '</form>';
echo '</div>';

echo $OUTPUT->box_end();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function get_htmlfile_name_path($exportpath, $export_dir, $itemname) {
	global $existingfilesArray;
	$filename = clean_param($itemname, PARAM_ALPHANUM);
	$ext = ".html";
	$i = 0;
	if ($filename == "") {
		$filepath = $export_dir . $filename . $i . $ext;
		$resfilename = $filename . $i . $ext;
	} else {
		$filepath = $export_dir . $filename . $ext;
		$resfilename = $filename . $ext;
	}
	if (in_array($exportpath . $filepath, $existingfilesArray)) {
		do {
			$i++;
			$filepath = $export_dir . $filename . $i . $ext;
			$resfilename = $filename . $i . $ext;
		} while (in_array($exportpath . $filepath, $existingfilesArray));
	}
	$existingfilesArray[] = $exportpath . $filepath;
	return array($resfilename, $filepath);
}

function createHTMLHeader($title, $depthPath = 0) {
	$filecontent = '';
	$depth = '';
	for($i = 1; $i<=$depthPath; $i++) 
		$depth .= '../';
	$filecontent .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n";
	$filecontent .= '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
	$filecontent .= '<head>' . "\n";
	$filecontent .= '  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n";
	$filecontent .= '  <title>' . $title . '</title>' . "\n";
	$filecontent .= '  <link href="'.$depth.'export_style.css" rel="stylesheet">' . "\n";	
	$filecontent .= '<!-- ' . get_string("exportcomment", "block_exaport") . ' -->';
	$filecontent .= '</head>' . "\n";
	return $filecontent;
}