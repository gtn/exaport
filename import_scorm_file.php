<?php
require_once dirname(__FILE__) . '/inc.php';
require_once dirname(__FILE__) . '/lib/sharelib.php';
require_once dirname(__FILE__).'/lib/import_scorm_form.php';
require_once dirname(__FILE__).'/lib/minixml.inc.php';
require_once dirname(__FILE__).'/lib/class.scormparser.php';

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('block/exaport:use', $context);
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

$url = '/blocks/exaport/import_scorm_file.php';
$PAGE->set_url($url);
block_exaport_print_header("exportimportimport");

$exteditform = new block_exaport_import_scorm_form();
$exteditform->display();

echo $OUTPUT->footer($course);

function import_files($unzip_dir, $structures, $i = 0, $previd=NULL) {
	// this function is for future use.
}

function portfolio_file_area_name() {
    global $CFG, $USER;
	return "exaport/temp/import/{$USER->id}";
}

function import_user_description($file) {
	global $USER;
	$content = file_get_contents($file);

	if(($startDesc = strpos($content,  '<!--###BOOKMARK_PERSONAL_DESC###-->')) !== false) {
	   	$startDesc+=strlen('<!--###BOOKMARK_PERSONAL_DESC###-->');
	   	if(($endDesc = strpos($content, '<!--###BOOKMARK_PERSONAL_DESC###-->', $startDesc)) !== false) {
	        if(record_exists('block_exaportuser', 'user_id', $USER->id)) {
				$conditions = array("user_id" => $USER->id);
	        	$record = $DB->get_record('block_exaportuser', $conditions);
	        	$record->description = block_exaport_clean_text(substr($content, $startDesc, $endDesc-$startDesc));
		        $record->persinfo_timemodified = time();
		        if (! $DB->update_record('block_exaportuser', $record)) {
	                error(get_string("couldntupdatedesc", "block_exaport"));
	            }
	        }
	        else {
                $newentry = new stdClass();
		        $newentry->description =  addslashes(substr($content, $startDesc, $endDesc-$startDesc));
		        $newentry->persinfo_timemodified = time();
		        $newentry->id = $USER->id;
		        if (! $DB->insert_record('block_exaportuser', $newentry)) {
	                error(get_string("couldntinsertdesc", "block_exaport"));
	            }
	        }
		}
	}
}

function import_structure($unzip_dir, $structures,$course, $i = 0, $previd=NULL) {
	global $USER, $COURSE;
	foreach($structures as $structure) {
		if(isset($structure["data"])) {
			if(isset($structure["data"]["title"]) &&
			   isset($structure["data"]["url"]) &&
			   ($previd != NULL)) {
				insert_entry($unzip_dir, $structure["data"]["url"], $structure["data"]["title"], $previd,$course);
			}
			else if(isset($structure["data"]["title"])) {
				if(is_null($previd)) {
	    			if(count_records_select("block_exaportcate","name='".block_exaport_clean_title($structure["data"]["title"])."' AND userid='$USER->id' AND pid=0") == 0) {
	    				$newentry = new stdClass();
						$newentry->name = block_exaport_clean_title($structure["data"]["title"]);
						$newentry->timemodified = time();
						$newentry->course = $COURSE->id;
	                    $newentry->userid = $USER->id;
	                    //$newentry->pid = $previd;

	                    if (! $entryid = $DB->insert_record("block_exaportcate", $newentry)) {
	                		notify("Could not insert category!");
	                    }
	    			}
	    			else {
	    				$entry = $DB->get_record_select("block_exaportcate","name='".block_exaport_clean_title($structure["data"]["title"])."' AND userid='$USER->id' AND pid=0");
	    				$entryid = $entry->id;
	    			}
	    		}
	    		else {
	    			if(count_records_select("block_exaportcate","name='".block_exaport_clean_title($structure["data"]["title"])."' AND userid='$USER->id' AND pid='$previd'") == 0) {
						$newentry->name = block_exaport_clean_title($structure["data"]["title"]);
						$newentry->timemodified = time();
						$newentry->course = $COURSE->id;
	                    $newentry->userid = $USER->id;
	                    $newentry->pid = $previd;

	                    if (! $entryid = $DB->insert_record("block_exaportcate", $newentry)) {
	                		notify("Could not insert category!");
	                    }
	    			}
	    			else {
	    				$entry = $DB->get_record_select("block_exaportcate","name='".block_exaport_clean_title($structure["data"]["title"])."' AND userid='$USER->id' AND pid='$previd'");
	    				$entryid = $entry->id;
	    			}
	    		}
			}
		}
		if(isset($structure["items"]) && isset($entryid)) {
			import_structure($unzip_dir, $structure["items"],$course, $i+1,$entryid);
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


function insert_entry($unzip_dir, $url, $title, $category,$course) {
	global $USER, $CFG, $COURSE;
	$filePath = $unzip_dir . '/' . $url;

	$content = file_get_contents($filePath);
	if((($startUrl = strpos($content,  '<!--###BOOKMARK_EXT_URL###-->')) !== false)&&
	   (($startDesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->')) !== false)) {
	   	$startUrl+=strlen('<!--###BOOKMARK_EXT_URL###-->');
	   	$startDesc+=strlen('<!--###BOOKMARK_EXT_DESC###-->');
	   	if((($endUrl = strpos($content,  '<!--###BOOKMARK_EXT_URL###-->', $startUrl)) !== false)&&
		   (($endDesc = strpos($content, '<!--###BOOKMARK_EXT_DESC###-->', $startDesc)) !== false)) {
            $new = new stdClass();
	   	    $new->userid       = $USER->id;
		    $new->categoryid     = $category;
		    $new->name         = block_exaport_clean_title($title);
		    $new->url          = block_exaport_clean_url(substr($content, $startUrl, $endUrl-$startUrl));
		    $new->intro        = block_exaport_clean_text(substr($content, $startDesc, $endDesc-$startDesc));
		    $new->timemodified = time();
			$new->type = 'link';
		    $new->course = $COURSE->id;

		    if($new->id = $DB->insert_record('block_exaportitem', $new)) {
			    get_comments($content, $new->id, 'block_exaportitemcomm');
			}
	   		else {
				notify(get_string("couldntinsert", "block_exaport", $title));
	   		}
		}
		else {
			notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
		}
	}
	else if((($startUrl = strpos($content, '<!--###BOOKMARK_FILE_URL###-->')) !== false)&&
	   (($startDesc = strpos($content,     '<!--###BOOKMARK_FILE_DESC###-->')) !== false)) {
	   	$startUrl+=strlen('<!--###BOOKMARK_FILE_URL###-->');
	   	$startDesc+=strlen('<!--###BOOKMARK_FILE_DESC###-->');
	   	if((($endUrl = strpos($content,  '<!--###BOOKMARK_FILE_URL###-->', $startUrl)) !== false)&&
		   (($endDesc = strpos($content, '<!--###BOOKMARK_FILE_DESC###-->', $startDesc)) !== false)) {
		   	$linkedFileName = block_exaport_clean_path(substr($content, $startUrl, $endUrl-$startUrl));
		   	$linkedFilePath = dirname($filePath) . '/' . $linkedFileName;
		   	if(is_file($linkedFilePath)) {
                $new = new stdClass();
		   	    $new->userid       = $USER->id;
			    $new->categoryid     = $category;
			    $new->name         = block_exaport_clean_title($title);
			    $new->intro        = block_exaport_clean_text(substr($content, $startDesc, $endDesc-$startDesc));
			    $new->timemodified = time();
				$new->type = 'file';
		    	$new->course       = $COURSE->id;
		    	// not necessary
		    	//$new->url          = str_replace($CFG->wwwroot, "", $_SERVER["HTTP_REFERER"]);

		   		if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
		   			$destination = block_exaport_file_area_name($new);
		   			if(make_upload_directory($destination, false)) {
						$destination = $CFG->dataroot . '/' . $destination;
						$destination_name = handle_filename_collision($destination, $linkedFileName);
						if(copy($linkedFilePath, $destination . '/' . $destination_name)) {
							set_field("block_exaportitem", "attachment", $destination_name, "id", $new->id);
						}
						else {
							notify(get_string("couldntcopyfile", "block_exaport", $title));
						}
		   			}
		   			else {
						notify(get_string("couldntcreatedirectory", "block_exaport", $title));
		   			}
			    	get_comments($content, $new->id, 'block_exaportitemcomm');
		   		}
		   		else {
					notify(get_string("couldntinsert", "block_exaport", $title));
		   		}
		   	}
			else {
				notify(get_string("linkedfilenotfound", "block_exaport", array("filename" => $linkedFileName, "url" => $url, "title" => $title)));
			}
		}
		else {
			notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
		}
	}
	else if( (($startDesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->')) !== false) ) {
	   	$startDesc+=strlen('<!--###BOOKMARK_NOTE_DESC###-->');
	   	if((($endDesc = strpos($content, '<!--###BOOKMARK_NOTE_DESC###-->', $startDesc)) !== false) ) {
	   	    $new = new stdClass();
	   	    $new->userid       = $USER->id;
		    $new->categoryid     = $category;
		    $new->name         = block_exaport_clean_title($title);
		    $new->intro        = block_exaport_clean_text(substr($content, $startDesc, $endDesc-$startDesc));
		    $new->timemodified = time();
			$new->type = 'note';
		    $new->course = $COURSE->id;

		    if($new->id = $DB->insert_record('block_exaportitem', $new)) {
			    get_comments($content, $new->id, 'block_exaportitemcomm');
			}
	   		else {
				notify(get_string("couldntinsert", "block_exaport", $title));
	   		}
		}
		else {
			notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
		}
	}
	else {
		notify(get_string("filetypenotdetected", "block_exaport", array("filename" => $url, "title" => $title)));
	}
}

function get_comments($content, $bookmarkid, $table) {
	global $USER;
	$i = 1;
	$comment = "";
	while((($startAuthor  = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_AUTHOR###-->' )) !== false) &&
	      (($startTime    = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_TIME###-->'   )) !== false) &&
	      (($startContent = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_CONTENT###-->')) !== false)) {
	   	$startAuthor+=strlen('<!--###BOOKMARK_COMMENT('.$i.')_AUTHOR###-->');
	   	$startTime+=strlen('<!--###BOOKMARK_COMMENT('.$i.')_TIME###-->');
	   	$startContent+=strlen('<!--###BOOKMARK_COMMENT('.$i.')_CONTENT###-->');

	   	if((($endAuthor  = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_AUTHOR###-->', $startAuthor  )) !== false) &&
	      (($endTime    = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_TIME###-->', $startTime       )) !== false) &&
	      (($endContent = strpos($content, '<!--###BOOKMARK_COMMENT('.$i.')_CONTENT###-->', $startContent )) !== false)) {

		    $commentAuthor =  block_exaport_clean_text(substr($content, $startAuthor, $endAuthor-$startAuthor));
		    $commentTime =  block_exaport_clean_text(substr($content, $startTime, $endTime-$startTime));
		    $commentContent =  block_exaport_clean_text(substr($content, $startContent, $endContent-$startContent));

		    $comment .= '<span class="block_eportfolio_commentauthor">'.$commentAuthor.'</span> '.$commentTime.'<br />'.$commentContent.'<br /><br />';
	    }
	    else {
			notify(get_string("couldninsertcomment","block_exaport"));
	    }
	   	$i++;
	}
	if($comment != "") {
	    $new = new stdClass();
	    $new->userid       = $USER->id;
	    $new->timemodified = time();
		$new->bookmarkid   = $bookmarkid;
		$new->entry        = get_string("importedcommentsstart","block_exaport") . $comment . get_string("importedcommentsend","block_exaport");
		if (!insert_record($table, $new)) {
			notify(get_string("couldninsertcomment","block_exaport"));
		}
	}
}

function handle_filename_collision($destination, $filename) {
    if (file_exists($destination .'/'. $filename)) {
        $parts = explode('.', $filename);
        $lastPart = array_pop($parts);
        $firstPart = implode('.', $parts);
    	$i = 0;
    	do {
    		$i++;
			$filename = $firstPart . '_' . $i . '.' . $lastPart;
    	} while(file_exists($destination .'/'. $filename));
    }
    return $filename;
}

function import_file_area_name() {
	global $USER, $CFG, $COURSE;

	return "exaport/temp/import/{$USER->id}";
}
?>
