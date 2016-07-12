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
require_once __DIR__.'/lib/edit_form.php';
require_once __DIR__.'/lib/minixml.inc.php';
require_once __DIR__.'/lib/class.scormparser.php';
require_once __DIR__.'/lib/information_edit_form.php';

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

$context = context_system::instance();

require_capability('block/exaport:use', $context);
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
	error("That's an invalid course id");
}//require_capability('block/exaport:import', $context);

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
	//no_submit_button_actions($exteditform, $sitecontext);
}

block_exaport_print_header("importexport", "exportimportimport");

////////
if ($fromform = $exteditform->get_data()) {
	$imported = true;
	$dir = make_upload_directory(import_file_area_name());
	$zipcontent = $exteditform->get_file_content('attachment');
	if (file_put_contents($dir."/".$exteditform->get_new_filename('attachment'), $zipcontent) && $newfilename = $exteditform->get_new_filename('attachment')) {
		if (preg_match('/^(.*).zip$/', $newfilename, $regs)) {
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
						if(is_file($unzip_dir."/itemscomp.xml")){
							$xml = simplexml_load_file($unzip_dir."/itemscomp.xml");
						}
						
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
												import_user_description($filepath, $unzip_dir);
											}
										}
										break;
									case "PORTFOLIO": 
										if(isset($organization["items"])){
											if(isset($xml)){
												import_structure($unzip_dir, $organization["items"], $course, 0, $xml, 0);
											} else import_structure($unzip_dir, $organization["items"], $course);
															}
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
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function import_files($unzip_dir, $structures, $i = 0, $previd=NULL) {
	// this function is for future use.
}

function portfolio_file_area_name() {
	global $CFG, $USER;
	return "exaport/temp/import/{$USER->id}";
}

function create_image($content){
	$newcontent = str_replace ("personal" , "@@PLUGINFILE@@" , $content );
	return $newcontent;
}

function get_image_url($content){
	$urls = array();
	
	while(($pos = strpos($content, '@@PLUGINFILE@@/')) !== false){
		$content = substr ( $content , $pos+15);
		$url = explode("\"", $content);
		$url = current($url);
		array_push ($urls, $url);
	}
	
	return $urls;
}

function import_user_image($unzip_dir, $url){
	global $USER, $DB, $OUTPUT;
	
	$path = $unzip_dir."/data/personal/".$url;
   
	$linkedFileName = block_exaport_clean_path($url);
	$linkedFilePath = dirname($path) . '/' . $linkedFileName;
	
	 $content = file_get_contents($linkedFilePath);
		
	if (is_file($linkedFilePath)) {
	
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

			// Prepare file record object
			$fileinfo = array(
				//'contextid' => get_context_instance(CONTEXT_USER, $USER->id)->id,	// ID of context
				'contextid' => context_user::instance($USER->id)->id,
				'component' => 'block_exaport', // usually = table name
				'filearea' => 'personal_information',	 // usually = table name
				'itemid' => $new->id,		  // usually = ID of row in table
				'filepath' => '/',			  // any path beginning and ending in /
				'filename' => $linkedFileName,
				'userid' => $USER->id);

			//eindeutige itemid generieren
			$fs->create_file_from_pathname($fileinfo, $linkedFilePath);
		
				
			$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id));
			$userpreferences = block_exaport_get_user_preferences($USER->id);
			$description = $userpreferences->description;
			$informationform = new block_exaport_personal_information_form();
			
			$data = new stdClass();
			$data->courseid = '2';
			$data->description = $description;
			$data->descriptionformat = FORMAT_HTML;
			$data->cataction = 'save';
			$data->edit = 1;
		
			$data = file_prepare_standard_editor($data, 'description', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'personal_information', $USER->id);
			
			$array = $data->description_editor;
			//file_prepare_draft_area($array["itemid"], get_context_instance(CONTEXT_USER, $USER->id)->id, 'block_exaport', 'personal_information', $new->id);
			file_prepare_draft_area($array["itemid"], context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', $new->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
			//var_dump(file_get_draft_area_info($array["itemid"]));
			
			//file_prepare_draft_area($data->itemid, get_context_instance(CONTEXT_USER, $USER->id)->id, 'block_exaport', 'personal_information', $new->id);
			//var_dump(file_get_draft_area_info($data->itemid));
			
			
			$informationform->set_data($data);
			$informationform->display();
			
			$DB->delete_records("block_exaportitem",array("id"=>$new->id));
		} else {
			notify(get_string("linkedfilenotfound", "block_exaport", array("url" => $url, "title" => "test")));
		}
	}
}
function import_user_description($file, $unzip_dir) {
	global $USER, $DB;
	
	$content = file_get_contents($file);
	
	//$content = create_image($content);
	//$images = get_image_url($content);
	
	//foreach($images as $image){
		//import_user_image($unzip_dir, $image);
	//}
	
	if (($startDesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->')) !== false) {
		$startDesc+=strlen('<!--###BOOKMARK_PERSONAL_DESC###-->');
		
		if (($endDesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->', $startDesc)) !== false) {
			if ($DB->record_exists('block_exaportuser', array('user_id'=>$USER->id))) {
				$conditions = array("user_id" => $USER->id);
				$record = $DB->get_record('block_exaportuser', $conditions);
//				$record->description = block_exaport_clean_text(substr($content, $startDesc, $endDesc - $startDesc));
				$record->description = substr($content, $startDesc, $endDesc - $startDesc);
				$record->persinfo_timemodified = time();
				if (!$DB->update_record('block_exaportuser', $record)) {
					error(get_string("couldntupdatedesc", "block_exaport"));
				}
			} else {
				$newentry = new stdClass();
//				$newentry->description = addslashes(substr($content, $startDesc, $endDesc - $startDesc));
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

function import_structure($unzip_dir, $structures, $course, $i = 0, &$xml=NULL, $previd=NULL) {
	global $USER, $COURSE, $DB;
	foreach ($structures as $structure) {		
		if (isset($structure["data"])) {
			if (isset($structure["data"]["title"]) 
					&& isset($structure["data"]["url"]) 
					&& !isset($structure["items"])
					//&& ($previd != NULL)) 
					) {
				if(isset($structure["data"]["id"])) {
					insert_entry($unzip_dir, $structure["data"]["url"], $structure["data"]["title"], $previd, $course, $xml, $structure["data"]["id"]);
				} else {
					insert_entry($unzip_dir, $structure["data"]["url"], $structure["data"]["title"], $previd, $course);
				};
		   } else if (isset($structure["data"]["title"])) {
				if (is_null($previd)) {
					if ($DB->count_records_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid=0") == 0) {
						$newentry = new stdClass();
						$newentry->name = block_exaport_clean_title($structure["data"]["title"]);
						$newentry->timemodified = time();
						$newentry->course = $COURSE->id;
						$newentry->userid = $USER->id;

						if (!$entryid = $DB->insert_record("block_exaportcate", $newentry)) {
							notify("Could not insert category!");
						}
					} else {
						$entry = $DB->get_record_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid=0");
						$entryid = $entry->id;
					}
				} else {
					if ($DB->count_records_select("block_exaportcate", "name='" . block_exaport_clean_title($structure["data"]["title"]) . "' AND userid='$USER->id' AND pid='$previd'") == 0) {
						$newentry = new stdClass();
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
			import_structure($unzip_dir, $structure["items"], $course, $i + 1, $xml, $entryid);
		}
	}
}
function import_item_competences($newid, $oldid, &$xml, $dir, $title){
global $USER, $DB, $COURSE;

foreach($xml->items->item as $item){
	$id = (int)$item->attributes()->identifier[0];
	if($oldid == $id){
		foreach($item->comp as $comp){
			$compid = (int) $comp->attributes()->identifier[0];
			$desc = $DB->get_record('block_exacompdescriptors', array("sourceid"=>$compid));
			$newentry = new stdClass();
			$newentry->activityid = $newid;
			$newentry->compid = $desc->id;
			$newentry->userid = $USER->id;
			$newentry->reviewerid = $USER->id;
			$newentry->role = 0;
			$newentry->eportfolioitem = 1;
			$newentry->wert = 0;
			$DB->insert_record("block_exacompcompuser_mm", $newentry);
			
			$newentry2 = new stdClass();
			$newentry2->compid = $desc->id;
			$newentry2->activityid = $newid;
			$newentry2->eportfolioitem = 1;
			$newentry2->activitytitle = $title;
			$newentry2->coursetitle = $COURSE->shortname;
			$DB->insert_record("block_exacompcompactiv_mm", $newentry2);
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
	   
function insert_entry($unzip_dir, $url, $title, $category, $course, &$xml=NULL, $id=NULL) {
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
				if(isset($xml) && isset($id)){	
					import_item_competences($new->id, $id, $xml, $unzip_dir, $new->name);
				}
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
				//$new->url		  = str_replace($CFG->wwwroot, "", $_SERVER["HTTP_REFERER"]);

				if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
					if(isset($xml) && isset($id)){
						import_item_competences($new->id, $id, $xml, $unzip_dir, $new->name);
					}
					$fs = get_file_storage();

					// Prepare file record object
					$fileinfo = array(
						//'contextid' => get_context_instance(CONTEXT_USER, $USER->id)->id,	// ID of context
						'contextid' => context_user::instance($USER->id)->id,
						'component' => 'block_exaport', // usually = table name
						'filearea' => 'item_file',	 // usually = table name
						'itemid' => $new->id,		  // usually = ID of row in table
						'filepath' => '/',			  // any path beginning and ending in /
						'filename' => $linkedFileName,
						'userid' => $USER->id);

					//eindeutige itemid generieren
					if (!$ret = $fs->create_file_from_pathname($fileinfo, $linkedFilePath)) {
						$DB->delete_records("block_exaportitem",array("id"=>$new->id));
						notify(get_string("couldntcopyfile", "block_exaport", $title));
					} else {
						get_comments($content, $new->id, 'block_exaportitemcomm');
					}
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
				if(isset($xml) && isset($id)){	
					import_item_competences($new->id, $id, $xml, $unzip_dir, $new->name);
				}
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
