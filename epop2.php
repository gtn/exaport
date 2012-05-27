 <?php
 require_once dirname(__FILE__) . '/inc.php';
 require_once dirname(__FILE__) . '/lib/lib.php';
 global $DB;

$filepath="/";
$dateiu = optional_param('dateiu', 0, PARAM_INT);
$userhash = optional_param('key', 0, PARAM_ALPHANUM);
$title = addslashes(optional_param('title', 0, PARAM_TEXT));
$description = addslashes(optional_param('description', 0, PARAM_TEXT));
//print_r($_FILES);


	$sql="SELECT * FROM {user} WHERE md5(id)='".$userhash."'";					 
	if (!$user=$DB->get_record_sql($sql)){
		return "error1";
	}
	

if($dateiu>0){
	$fs = get_file_storage();
	$totalsize = 0;
	$context = get_context_instance(CONTEXT_USER);
	foreach ($_FILES as $fieldname=>$uploaded_file) {
    // check upload errors
    if (!empty($_FILES[$fieldname]['error'])) {
        switch ($_FILES[$fieldname]['error']) {
        case UPLOAD_ERR_INI_SIZE:
            throw new moodle_exception('upload_error_ini_size', 'repository_upload');
            break;
        case UPLOAD_ERR_FORM_SIZE:
            throw new moodle_exception('upload_error_form_size', 'repository_upload');
            break;
        case UPLOAD_ERR_PARTIAL:
            throw new moodle_exception('upload_error_partial', 'repository_upload');
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new moodle_exception('upload_error_no_file', 'repository_upload');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new moodle_exception('upload_error_no_tmp_dir', 'repository_upload');
            break;
        case UPLOAD_ERR_CANT_WRITE:
            throw new moodle_exception('upload_error_cant_write', 'repository_upload');
            break;
        case UPLOAD_ERR_EXTENSION:
            throw new moodle_exception('upload_error_extension', 'repository_upload');
            break;
        default:
            throw new moodle_exception('nofile');
        }
    }
    $file = new stdClass();
    $file->filename = clean_param($_FILES[$fieldname]['name'], PARAM_FILE);
    // check system maxbytes setting
    if (($_FILES[$fieldname]['size'] > get_max_upload_file_size($CFG->maxbytes))) {
        // oversize file will be ignored, error added to array to notify
        // web service client
        $file->errortype = 'fileoversized';
        $file->error = get_string('maxbytes', 'error');
    } else {
        $file->filepath = $_FILES[$fieldname]['tmp_name'];
        // calculate total size of upload
        $totalsize += $_FILES[$fieldname]['size'];
    }
    $files[] = $file;
	}

	//$fs = get_file_storage();
	
	$usedspace = 0;
	$privatefiles = $fs->get_area_files($context->id, 'user', 'draft', false, 'id', false);
	foreach ($privatefiles as $file) {
	    $usedspace += $file->get_filesize();
	}
	if ($totalsize > ($CFG->userquota - $usedspace)) {
    throw new file_exception('userquotalimit');
	}
	$results = array();
	
foreach ($files as $file) {
    if (!empty($file->error)) {
        // including error and filename
        $results[] = $file;
        continue;
    }
    $file_record = new stdClass;
    $file_record->component = 'user';
    $file_record->contextid = $context->id;
    $file_record->userid    = $user->id;
    $file_record->filearea  = 'private';
    $file_record->filename = $file->filename;
    $file_record->filepath  = $filepath;
    $file_record->itemid    = 0;
    $file_record->license   = $CFG->sitedefaultlicense;
    $file_record->author    = $user->lastname." ".$user->firstname;
    $file_record->source    = '';

    //Check if the file already exist
    $existingfile = $fs->file_exists($file_record->contextid, $file_record->component, $file_record->filearea,
                $file_record->itemid, $file_record->filepath, $file_record->filename);
    if ($existingfile) {
        $file->errortype = 'filenameexist';
        $file->error = "die datei existiert bereits";
        $results[] = $file;
    } else {
    	
        if ($newfile = $fs->create_file_from_pathname($file_record, $file->filepath)){
        	 $DB->set_field("files","itemid",$newfile->get_id(),array("id"=>$newfile->get_id()));

        	 $new = new stdClass();
           $new->userid = $user->id;
           //$new->categoryid = $category;
           $new->name = $title;
           $new->intro = $description;
           $new->timemodified = time();
           $new->type = 'file';
           $new->course = $COURSE->id;
           $new->attachment = $newfile->get_id();     
           if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
           	echo "item gespeichert";
           }else{
           	echo "item NICHT gespeichert";
           }

           echo "OK";
        }else{
        	echo "falsch";
        };
                      
        $results[] = $file_record;
    }
}
print_r ($results);
}


 
 ?>