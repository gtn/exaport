<?php
require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';
// for ajax - stop errors/notices reporting
error_reporting (0);

require_once $CFG->libdir.'/editor/tinymce/lib.php';
$tinymce = new tinymce_texteditor();

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param("action", "add", PARAM_ALPHA);

define('SUBMIT_BUTTON_TEXT', get_string($action == 'add' ? 'addButton' : 'saveButton', 'block_exaport'));

$url = '/blocks/exabis_competences/blocks.json.php';
$PAGE->set_url($url);
$context = context_system::instance();
$PAGE->set_context($context);

require_login($courseid);


$type = $_POST['type_block'];
$id = optional_param('item_id', -1, PARAM_INT);

$block_data = new stdClass();
if ($id != -1) {
	$query = "select type, itemid, text, block_title, firstname, lastname, email, picture, contentmedia, width, height ".
		" from {block_exaportviewblock}".
		" where id=?";
	$block_data = $DB->get_record_sql($query, array($id));	
	$type = $block_data->type;
};
	
$message = array();

switch($type) {
	case 'item': $message["html"] = get_form_items($id, $block_data);
		break;
	case 'personal_information': $message["html"] = get_form_personalinfo($id, $block_data);
		break;
	case 'text': $message["html"] = get_form_text($id, $block_data);
		break;		
	case 'headline': $message["html"] = get_form_headline($id, $block_data);
		break;		
	case 'media': $message["html"] = get_form_media($id, $block_data);
		break;		
	case 'badge': $message["html"] = get_form_badge($id, $block_data);
		break;		
	default: break;
}

echo json_encode($message);
exit;

//echo $message["html"];

/* $data = json_decode($_POST['json_data']);
$response = 'Params'.count($data).'\n';
foreach ($data as $key=>$value) {
    $response .= $key.' = '.$value.'\n';
}

/**/
function get_form_items($id, $block_data=array()) {
	global $DB, $USER;
	
	// read all categories
	$categories = $DB->get_records_sql('
		SELECT c.id, c.name, c.pid, COUNT(i.id) AS item_cnt
		FROM {block_exaportcate} c
		LEFT JOIN {block_exaportitem} i ON i.categoryid=c.id AND '.block_exaport_get_item_where().'
		WHERE c.userid = ?
		GROUP BY c.id
		ORDER BY c.name ASC
	', array($USER->id));

	// build a tree according to parent
	$categoriesByParent = array();
	foreach ($categories as $category) {
		if (!isset($categoriesByParent[$category->pid])) $categoriesByParent[$category->pid] = array();
		$categoriesByParent[$category->pid][] = $category;
	}

	// the main root category
	$rootCategory = block_exaport_get_root_category();
	$categories[0] = $rootCategory;

	$items = $DB->get_records_sql("
			SELECT i.id, i.name, i.type, i.categoryid, COUNT(com.id) As comments
			FROM {block_exaportitem} i
			LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
			WHERE i.userid = ? AND ".block_exaport_get_item_where()."
			GROUP BY i.id, i.name, i.type, i.categoryid
			ORDER BY i.name
		", array($USER->id));
	
	$itemsByCategory = array();
	// save items to category
	foreach ($items as $item) {
		if (empty($itemsByCategory[$item->categoryid]))
			$itemsByCategory[$item->categoryid] = array();
		$itemsByCategory[$item->categoryid][] = $item;
	}
	
	$content  = "";
    $content .= '<form enctype="multipart/form-data" id="blockform" method="post" class="pieform" onsubmit="exaportViewEdit.addItem('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';	
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';
	$content .= '<label for="list">'.get_string('listofartefacts','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	

	function block_exaport_blocks_json_print_categories_recursive($category, $categoriesByParent, $itemsByCategory) {
	
		$subContent = '';
		
		foreach ($categoriesByParent[$category->id] as $subCategory) {
			$subContent .= block_exaport_blocks_json_print_categories_recursive($subCategory, $categoriesByParent, $itemsByCategory);
		}

		if (!$subContent && empty($itemsByCategory[$category->id])) {
			// no subcontent and no items
			return '';
		}
		
		$content = '';
		
		if (($category->id > 0) && ($category->pid > 0)) $content .= '<div class="add-item-sub">';
		
		$content .= '<div class="add-item-category">'.$category->name.'</div>';
	
		if (!empty($itemsByCategory[$category->id])) {
			foreach ($itemsByCategory[$category->id] as $item) {
				$content .= '<div class="add-item">';
				$content .= '<input type="checkbox" name="add_items[]" value="'.$item->id.'" /> ';
				$content .= $item->name;
				$content .= '</div>';
			}
		}
		
		$content .= $subContent;
		
		if (($category->id > 0) && ($category->pid > 0)) $content .= '</div>';

		return $content;
	}
	
	ob_start();
	?>
	<div id="add-items-list">
		<?php
			echo block_exaport_blocks_json_print_categories_recursive($rootCategory, $categoriesByParent, $itemsByCategory);
		?>
	</div>
	<script type="text/javascript">
	//<![CDATA[
		exaportViewEdit.setPopupTitle(<?php echo json_encode(get_string('cofigureblock_item','block_exaport')); ?>);
		exaportViewEdit.initAddItems();
	//]]>
	</script>
	<?php
	$content .= ob_get_clean();


	$content .= '</td></tr>';		
	$content .= '<tr><td>';	
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_text" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';	
	$content .= '</form>';

	return $content;
};

function get_form_text($id, $block_data=array()) {
	global $CFG, $tinymce, $PAGE, $USER;
	
    $draftid_editor = file_get_submitted_draft_itemid('text');
	if ($block_data->text) {
		$text = $block_data->text;
		$text = file_rewrite_pluginfile_urls($text, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $draftid_editor, array('subdirs'=>true));
	} else {
		$text = '';
	}

	$content  = "";
    $content .= '<form enctype="multipart/form-data" id="blockform" action="#json" method="post" class="pieform" onsubmit="exaportViewEdit.addText('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';		
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';
	$content .= '<label for="block_title">'.get_string('blocktitle2','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';
	$content .= '<input type="text" name="block_title" value="'.s($block_data->block_title?$block_data->block_title:"").'" id="block_title">';
	$content .= '</td></tr>';	
	$content .= '<tr><th>';
	$content .= '<label for="text">'.get_string('blockcontent','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	$content .= '<textarea tabindex="1" style="height: 150px; width: 100%;" name="text" id="block_text" class="mceEditor" cols="10" rows="15" aria-hidden="true">'.$text.'</textarea>';
	$content .= '</td></tr>';		
	$content .= '<tr><td>';
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_text" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';
	$content .= '</form>';
	// change the title of block
	$content .= '<script type="text/javascript">jQueryExaport("#block_form_title").html("'.get_string('cofigureblock_text','block_exaport').'")</script>';

	$content .= tinyMCE_enable_script('block_text');

	return $content;
}

function get_form_headline($id, $block_data=array()) {
	$content  = "";
    $content .= '<form enctype="multipart/form-data" id="blockform" action="#json" method="post" class="pieform" onsubmit="exaportViewEdit.addHeadline('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';			
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';	
	$content .= '<label for="headline">'.get_string('view_specialitem_headline', 'block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	$content .= '<input name="headline" id="headline" type="text" value="'.s($block_data->text?$block_data->text:"").'" default-text="'.get_string('view_specialitem_headline_defaulttext', 'block_exaport').'" /></div>';
	$content .= '<div for="headline" class="not-empty-check">'.block_exaport_get_string('titlenotemtpy').'</div>';
	$content .= '</td></tr>';		
	$content .= '<tr><td>';	
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_text" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';
	$content .= '</form>';
	// change the title of block
	$content .= '<script type="text/javascript">jQueryExaport("#block_form_title").html("'.get_string('cofigureblock_header','block_exaport').'")</script>';

	return $content;
}

function get_form_personalinfo($id, $block_data=array()) {
global $OUTPUT, $DB, $USER, $PAGE;

if ($USER->picture) {
	$user_picture = new user_picture($USER);
	$user_picture->size = 1;
	$picture_src = $user_picture->get_url($PAGE);
	$user_picture->size = 2;
	$picture_src_small = $user_picture->get_url($PAGE);
};

    $draftid_editor = file_get_submitted_draft_itemid('text');
	
	if ($block_data->text) {
		$text = $block_data->text;

		$text = file_prepare_draft_area($draftid_editor, context_user::instance($USER->id)->id, 'block_exaport', 'view_content',
                                       required_param('viewid', PARAM_INT), array('subdirs'=>true), $text);
	} else {
		$text = block_exaport_get_user_preferences()->description;

		$text = file_prepare_draft_area($draftid_editor, context_user::instance($USER->id)->id, 'block_exaport', 'personal_information',
                                       $USER->id, array('subdirs'=>true), $text);
	}
	$content  = "";

    $content .= '<form enctype="multipart/form-data" id="blockform" action="#json" method="post" class="pieform" onsubmit="exaportViewEdit.addPersonalInfo('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';			
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';
	$content .= '<label for="block_title">'.get_string('blocktitle2', 'block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';
	$content .= '<input type="text" name="block_title" value="'.s($block_data->block_title).'" id="block_title">';
	$content .= '</td></tr>';
	$content .= '<tr><th>';
	$content .= '<label>'.get_string('fieldstoshow','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	$content .= '<input type="checkbox" name="fields[firstname]" id="firstname" value="'.$USER->firstname.'" '.($block_data->firstname==$USER->firstname?'checked="checked"':'').'> '.get_string('firstname','block_exaport').'</input><br>';
	$content .= '<input type="checkbox" name="fields[lastname]" id="lastname" value="'.$USER->lastname.'" '.($block_data->lastname==$USER->lastname?'checked="checked"':'').'> '.get_string('lastname','block_exaport').'</input>';
	$content .= '</td></tr>';		
	$content .= '<tr><th>';
	$content .= '<label>'.get_string('profilepicture','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';		
	if ($USER->picture) {	
		$content .= '<input type="radio" name="picture" value="" '.($block_data->picture==$picture_src?'':'checked="checked"').'> '.get_string('nopicture','block_exaport').'</input><br>';
		$content .= '<input type="radio" name="picture" value="'.$picture_src.'" '.($block_data->picture==$picture_src?'checked="checked"':'').'> <img src="'.$user_picture->get_url($PAGE).'"></input>';
	}
	else 
		$content .= get_string('noprofilepicture','block_exaport');
	$content .= '</td></tr>';
	$content .= '<tr><th>';
	$content .= '<label>'.get_string('mailadress','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	if ($USER->email) {		
		$content .= '<input type="radio" name="email" value="" '.($block_data->email==$USER->email?'':'checked="checked"').'> '.get_string('nomail','block_exaport').'</input><br>';
		$content .= '<input type="radio" name="email" value="'.$USER->email.'" '.($block_data->email==$USER->email?'checked="checked"':'').'> '.$USER->email.'</input>';
	}
	else
		$content .= get_string('noemails','block_exaport');
	$content .= '</td></tr>';
	
	$content .= '<tr><th>';	
	$content .= '<label for="text">'.get_string('aboutme','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	$content .= '<textarea tabindex="1" style="height: 150px; width: 100%;" name="text" id="block_intro" class="mceEditor" cols="10" rows="15" aria-hidden="true">'.s($text).'</textarea>';
	$content .= '</td></tr>';		
	$content .= '<tr><td>';
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_text" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';
	// change the title of block
	$content .= '<script type="text/javascript">jQueryExaport("#block_form_title").html("'.get_string('cofigureblock_personalinfo','block_exaport').'")</script>';

	$content .= tinyMCE_enable_script('block_intro');
	
	return $content;
}


function get_form_media($id, $block_data=array()) {
	global $CFG, $PAGE, $USER, $action;
	
	$content  = "";
    $content .= '<form enctype="multipart/form-data" id="blockform" action="#json" method="post" class="pieform" onsubmit="exaportViewEdit.addMedia('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';		
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';
	$content .= '<label for="block_title">'.get_string('blocktitle2','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';
	$content .= '<input tabindex="1" type="text" name="block_title" value="'.s($block_data->block_title?$block_data->block_title:"").'" id="block_title">';
	$content .= '<div for="block_title" class="not-empty-check">'.block_exaport_get_string('titlenotemtpy').'</div>';
	$content .= '</td></tr>';	
	$content .= '<tr><th>';
	$content .= '<label for="mediacontent">'.get_string('mediacontent','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	
	$content .= '<textarea tabindex="1" style="height: 100px; width: 100%;" name="mediacontent" id="block_media" cols="10" rows="15" aria-hidden="true">'.$block_data->contentmedia.'</textarea>';
	$content .= '</td></tr>';		
	$content .= '<tr><th>';
	$content .= get_string('media_allowed_notes','block_exaport');
	$content .= '<br><ul class="inlinelist" style="list-style-type: none;">
  <li><a target="_blank" href="http://www.glogster.com/"><img title="Glogster" alt="Glogster" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/glogster.png"></a></li>
  <li><a target="_blank" href="http://video.google.com/"><img title="Google Video" alt="Google Video" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/googlevideo.png"></a></li>
  <li><a target="_blank" href="http://www.prezi.com/"><img title="Prezi" alt="Prezi" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/prezi.png"></a></li>
  <li><a target="_blank" href="http://scivee.tv/"><img title="SciVee" alt="SciVee" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/scivee.png"></a></li>
  <li><a target="_blank" href="http://slideshare.net/"><img title="SlideShare" alt="SlideShare" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/slideshare.png"></a></li>
  <li><a target="_blank" href="http://www.teachertube.com/"><img title="TeacherTube" alt="TeacherTube" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/teachertube.png"></a></li>
  <li><a target="_blank" href="http://vimeo.com/"><img title="Vimeo" alt="Vimeo" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/vimeo.png"></a></li>
  <li><a target="_blank" href="http://www.voicethread.com/"><img title="VoiceThread" alt="VoiceThread" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/voicethread.png"></a></li>
  <li><a target="_blank" href="http://www.voki.com/"><img title="Voki" alt="Voki" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/voki.png"></a></li>
  <li><a target="_blank" href="http://wikieducator.org/"><img title="WikiEducator" alt="WikiEducator" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/wikieducator.png"></a></li>
  <li><a target="_blank" href="http://youtube.com/"><img title="YouTube" alt="YouTube" src="'.$CFG->wwwroot.'/blocks/exaport/pix/media_sources/youtube.png"></a></li>

	</ul>	';
	$content .= '</th></tr>';	
	$content .= '<tr><th>';
	$content .= '<input type="checkbox" tabindex="1" name="create_as_note" id="create_as_note" value="1"'.($action=='add'?' checked="checked"':'').' /> ';
	$content .= '<label for="create_as_note">'.block_exaport_get_string('create_as_note').'</label>';
	$content .= '</td></tr>';
	$content .= '<tr><th>';
	$content .= '<label for="width">'.get_string('width','block_exaport').'</label>';	
	$content .= ' <input type="text" tabindex="1" name="width" value="'.s($block_data->width?$block_data->width:"").'" id="block_width">';		
	$content .= '&nbsp;&nbsp;&nbsp;<label for="height">'.get_string('height','block_exaport').'</label>';	
	$content .= ' <input type="text" tabindex="1" name="height" value="'.s($block_data->height?$block_data->height:"").'" id="block_height">';			
	$content .= '</td></tr>';			
	$content .= '<tr><td>';
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_media" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';
	$content .= '</form>';
	// change the title of block
	$content .= '<script type="text/javascript">jQueryExaport("#block_form_title").html("'.get_string('cofigureblock_media','block_exaport').'")</script>';	
	return $content;
}

function get_form_badge($id, $block_data=array()) {
	global $DB, $USER;
	
	$badges = block_exaport_get_all_user_badges();
	
	$content  = "";
    $content .= '<form enctype="multipart/form-data" id="blockform" method="post" class="pieform" onsubmit="exaportViewEdit.addBadge('.$id.'); return false;">';
	$content .= '<input type="hidden" name="item_id" value="'.$id.'">';	
	$content .= '<table style="width: 100%;">';
	$content .= '<tr><th>';
	$content .= '<label for="list">'.get_string('listofbadges','block_exaport').'</label>';
	$content .= '</th></tr>';
	$content .= '<tr><td>';	

	ob_start();
	?>
	<div id="add-items-list">
		<?php
			if (!empty($badges)) {
				foreach ($badges as $badge) {
					echo '<div class="add-item">';
					echo '<input type="checkbox" name="add_badges[]" value="'.$badge->id.'" /> ';
					echo $badge->name;
					echo '</div>';
				}
			}
		?>
	</div>
	<script type="text/javascript">
	//<![CDATA[
		exaportViewEdit.setPopupTitle(<?php echo json_encode(get_string('cofigureblock_badge','block_exaport')); ?>);
		exaportViewEdit.initAddItems();
	//]]>
	</script>
	<?php
	$content .= ob_get_clean();


	$content .= '</td></tr>';		
	$content .= '<tr><td>';	
	$content .= '<input type="submit" value="'.SUBMIT_BUTTON_TEXT.'" id="add_text" name="submit_block" class="submit" />';
	$content .= '<input type="button" value="'.get_string('cancelButton', 'block_exaport').'" name="cancel" class="submit" id="cancel_list" onclick="exaportViewEdit.cancelAddEdit()" />';
	$content .= '</td></tr>';
	$content .= '</table>';	
	$content .= '</form>';

	return $content;
};

function tinyMCE_enable_script($element_id) {
	global $CFG, $PAGE;
	
	$draft_itemid = (int)file_get_submitted_draft_itemid('text');

		
$directionality = get_string('thisdirection', 'langconfig');
//$strtime        = get_string('strftimetime');
//$strdate        = get_string('strftimedaydate');
$lang           = current_language();
$rev = theme_get_revision();
//$contentcss     = $PAGE->theme->editor_css_url()->out(false);
if ($CFG->version >= 2012120300) {
	$content .= '
	<script type="text/javascript">
	//<![CDATA[
	YUI().use(\'node\', function(Y) {
	M.util.load_flowplayer();
	Y.on(\'domready\', function() { Y.use(\'core_filepicker\', function(Y) { M.core_filepicker.set_templates(Y, {"generallayout":"\n<div class=\"file-picker fp-generallayout\">\n <div class=\"fp-repo-area\">\n <ul class=\"fp-list\">\n <li class=\"fp-repo\"><a href=\"#\"><img class=\"fp-repo-icon\" width=\"16\" height=\"16\" \/>&nbsp;<span class=\"fp-repo-name\"><\/span><\/a><\/li>\n <\/ul>\n <\/div>\n <div class=\"fp-repo-items\">\n <div class=\"fp-navbar\">\n <div>\n <div class=\"fp-toolbar\">\n <div class=\"fp-tb-back\"><a href=\"#\">&laquo; Back<\/a><\/div>\n <div class=\"fp-tb-search\"><form><\/form><\/div>\n <div class=\"fp-tb-refresh\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/a\/refresh\" \/><\/a><\/div>\n <div class=\"fp-tb-logout\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/a\/logout\" \/><a href=\"#\"><\/a><\/div>\n <div class=\"fp-tb-manage\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/a\/setting\" \/> Manage<\/a><\/div>\n <div class=\"fp-tb-help\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/a\/help\" \/> Help<\/a><\/div>\n <div class=\"fp-tb-message\"><\/div>\n <\/div>\n <div class=\"fp-viewbar\">\n <a class=\"fp-vb-icons\" href=\"#\"><\/a>\n <a class=\"fp-vb-details\" href=\"#\"><\/a>\n <a class=\"fp-vb-tree\" href=\"#\"><\/a>\n <\/div>\n <div class=\"fp-clear-left\"><\/div>\n <\/div>\n <div class=\"fp-pathbar\">\n <span class=\"fp-path-folder\"><a class=\"fp-path-folder-name\" href=\"#\"><\/a><\/span>\n <\/div>\n <\/div>\n <div class=\"fp-content\"><\/div>\n <\/div>\n<\/div>","iconfilename":"\n<a class=\"fp-file\" href=\"#\" >\n <div style=\"position:relative;\">\n <div class=\"fp-thumbnail\"><\/div>\n <div class=\"fp-reficons1\"><\/div>\n <div class=\"fp-reficons2\"><\/div>\n <\/div>\n <div class=\"fp-filename-field\">\n <p class=\"fp-filename\"><\/p>\n <\/div>\n<\/a>","listfilename":"\n<span class=\"fp-filename-icon\">\n <a href=\"#\">\n <span class=\"fp-icon\"><\/span>\n <span class=\"fp-filename\"><\/span>\n <\/a>\n<\/span>","nextpage":"\n<div class=\"fp-nextpage\">\n <div class=\"fp-nextpage-link\"><a href=\"#\">more<\/a><\/div>\n <div class=\"fp-nextpage-loading\">\n <img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/i\/loading_small\" \/>\n <\/div>\n<\/div>","selectlayout":"\n<div class=\"file-picker fp-select\">\n <div class=\"fp-select-loading\">\n <img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/i\/loading_small\" \/>\n <\/div>\n <form>\n <table>\n <tr class=\"fp-linktype-2\">\n <td class=\"mdl-right\"><\/td>\n <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Make a copy of the file<\/label><\/td><\/tr>\n <tr class=\"fp-linktype-1\">\n <td class=\"mdl-right\"><\/td>\n <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Link to the file directly<\/label><\/td><\/tr>\n <tr class=\"fp-linktype-4\">\n <td class=\"mdl-right\"><\/td>\n <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Create an alias\/shortcut to the file<\/label><\/td><\/tr>\n <tr class=\"fp-saveas\">\n <td class=\"mdl-right\"><label>Save as<\/label>:<\/td>\n <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n <tr class=\"fp-setauthor\">\n <td class=\"mdl-right\"><label>Author<\/label>:<\/td>\n <td class=\"mdl-left\"><input type=\"text\" \/><\/td><\/tr>\n <tr class=\"fp-setlicense\">\n <td class=\"mdl-right\"><label>Choose license<\/label>:<\/td>\n <td class=\"mdl-left\"><select><\/select><\/td><\/tr>\n <\/table>\n <div class=\"fp-select-buttons\">\n <button class=\"fp-select-confirm\">Select this file<\/button>\n <button class=\"fp-select-cancel\">Cancel<\/button>\n <\/div>\n <\/form>\n <div class=\"fp-info\">\n <div class=\"fp-hr\"><\/div>\n <p class=\"fp-thumbnail\"><\/p>\n <div class=\"fp-fileinfo\">\n <div class=\"fp-datemodified\">Last modified: <span class=\"fp-value\"><\/span><\/div>\n <div class=\"fp-datecreated\">Created: <span class=\"fp-value\"><\/span><\/div>\n <div class=\"fp-size\">Size: <span class=\"fp-value\"><\/span><\/div>\n <div class=\"fp-license\">Licence: <span class=\"fp-value\"><\/span><\/div>\n <div class=\"fp-author\">Author: <span class=\"fp-value\"><\/span><\/div>\n <div class=\"fp-dimensions\">Dimensions: <span class=\"fp-value\"><\/span><\/div>\n <\/div>\n <div>\n<\/div>","uploadform":"\n<div class=\"fp-upload-form mdl-align\">\n <div class=\"fp-content-center\">\n <form enctype=\"multipart\/form-data\" method=\"POST\">\n <table >\n <tr class=\"fp-file\">\n <td class=\"mdl-right\"><label>Attachment<\/label>:<\/td>\n <td class=\"mdl-left\"><input type=\"file\"\/><\/td><\/tr>\n <tr class=\"fp-saveas\">\n <td class=\"mdl-right\"><label>Save as<\/label>:<\/td>\n <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n <tr class=\"fp-setauthor\">\n <td class=\"mdl-right\"><label>Author<\/label>:<\/td>\n <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n <tr class=\"fp-setlicense\">\n <td class=\"mdl-right\"><label>Choose license<\/label>:<\/td>\n <td class=\"mdl-left\"><select><\/select><\/td><\/tr>\n <\/table>\n <\/form>\n <div><button class=\"fp-upload-btn\">Upload this file<\/button><\/div>\n <\/div>\n<\/div> ","loading":"\n<div class=\"fp-content-loading\">\n <div class=\"fp-content-center\">\n <img src=\"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/core\/'.$rev.'\/i\/loading_small\" \/>\n <\/div>\n<\/div>","error":"\n<div class=\"fp-content-error\" ><div class=\"fp-error\"><\/div><\/div>","message":"\n<div class=\"file-picker fp-msg\">\n <p class=\"fp-msg-text\"><\/p>\n <button class=\"fp-msg-butok\">OK<\/button>\n<\/div>","processexistingfile":"\n<div class=\"file-picker fp-dlg\">\n <p class=\"fp-dlg-text\"><\/p>\n <div class=\"fp-dlg-buttons\">\n <button class=\"fp-dlg-butoverwrite\">Overwrite<\/button>\n <button class=\"fp-dlg-butrename\"><\/button>\n <button class=\"fp-dlg-butcancel\">Cancel<\/button>\n <\/div>\n<\/div>","processexistingfilemultiple":"\n<div class=\"file-picker fp-dlg\">\n <p class=\"fp-dlg-text\"><\/p>\n <a class=\"fp-dlg-butoverwrite fp-panel-button\" href=\"#\">Overwrite<\/a>\n <a class=\"fp-dlg-butcancel fp-panel-button\" href=\"#\">Cancel<\/a>\n <a class=\"fp-dlg-butrename fp-panel-button\" href=\"#\"><\/a>\n <br\/>\n <a class=\"fp-dlg-butoverwriteall fp-panel-button\" href=\"#\">Overwrite all<\/a>\n <a class=\"fp-dlg-butrenameall fp-panel-button\" href=\"#\">Rename all<\/a>\n<\/div>","loginform":"\n<div class=\"fp-login-form\">\n <div class=\"fp-content-center\">\n <form>\n <table >\n <tr class=\"fp-login-popup\">\n <td colspan=\"2\">\n <label>Click \"Login\" button to login<\/label>\n <p class=\"fp-popup\"><button class=\"fp-login-popup-but\">Login<\/button><\/p><\/td><\/tr>\n <tr class=\"fp-login-textarea\">\n <td colspan=\"2\"><p><textarea><\/textarea><\/p><\/td><\/tr>\n <tr class=\"fp-login-select\">\n <td align=\"right\"><label><\/label><\/td>\n <td align=\"left\"><select><\/select><\/td><\/tr>\n <tr class=\"fp-login-input\">\n <td class=\"label\"><label><\/label><\/td>\n <td class=\"input\"><input\/><\/td><\/tr>\n <tr class=\"fp-login-radiogroup\">\n <td align=\"right\" width=\"30%\" valign=\"top\"><label><\/label><\/td>\n <td align=\"left\" valign=\"top\"><p class=\"fp-login-radio\"><input \/> <label><\/label><\/p><\/td><\/tr>\n <\/table>\n <p><button class=\"fp-login-submit\">Submit<\/button><\/p>\n <\/form>\n <\/div>\n<\/div>"}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_editor(Y, "'.$element_id.'", {"mode":"exact","elements":"'.$element_id.'","relative_urls":false,"document_base_url":"'.$CFG->wwwroot.'","moodle_plugin_base":"'.$CFG->wwwroot.'\/lib\/editor\/tinymce\/plugins\/","content_css":"'.$CFG->wwwroot.'\/theme\/styles.php\/standard\/'.$rev.'\/editor","language":"'.$lang.'","directionality":"'.$directionality.'","plugin_insertdate_dateFormat ":"%A, %d %B %Y","plugin_insertdate_timeFormat ":"%I:%M %p","theme":"advanced","skin":"o2k7","skin_variant":"silver","apply_source_formatting":true,"remove_script_host":false,"entity_encoding":"raw","plugins":"safari,table,style,layer,advhr,advlink,emotions,inlinepopups,searchreplace,paste,directionality,fullscreen,nonbreaking,contextmenu,insertdatetime,save,iespell,preview,print,noneditable,visualchars,xhtmlxtras,template,pagebreak,-moodlenolink,-spellchecker,-moodleimage,-moodlemedia","theme_advanced_font_sizes":"1,2,3,4,5,6,7","theme_advanced_layout_manager":"SimpleLayout","theme_advanced_toolbar_align":"left","theme_advanced_fonts":"Trebuchet=Trebuchet MS,Verdana,Arial,Helvetica,sans-serif;Arial=arial,helvetica,sans-serif;Courier New=courier new,courier,monospace;Georgia=georgia,times new roman,times,serif;Tahoma=tahoma,arial,helvetica,sans-serif;Times New Roman=times new roman,times,serif;Verdana=verdana,arial,helvetica,sans-serif;Impact=impact;Wingdings=wingdings","theme_advanced_resize_horizontal":true,"theme_advanced_resizing":true,"theme_advanced_resizing_min_height":30,"min_height":30,"theme_advanced_toolbar_location":"top","theme_advanced_statusbar_location":"bottom","language_load":false,"langrev":-1,"theme_advanced_buttons1":"fontselect,fontsizeselect,formatselect,|,undo,redo,|,search,replace,|,fullscreen","theme_advanced_buttons2":"bold,italic,underline,strikethrough,sub,sup,|,justifyleft,justifycenter,justifyright,|,cleanup,removeformat,pastetext,pasteword,|,forecolor,backcolor,|,ltr,rtl","theme_advanced_buttons3":"bullist,numlist,outdent,indent,|,link,unlink,moodlenolink,|,image,moodlemedia,nonbreaking,charmap,table,|,code,spellchecker","extended_valid_elements":"nolink,tex,algebra,lang[lang]","custom_elements":"nolink,~tex,~algebra,lang","init_instance_callback":"M.editor_tinymce.onblur_event","moodle_init_plugins":"moodlenolink:loader.php\/moodlenolink\/2012112900\/editor_plugin.js,spellchecker:loader.php\/spellchecker\/2012112900\/editor_plugin.js,moodleimage:loader.php\/moodleimage\/2012112900\/editor_plugin.js,moodlemedia:loader.php\/moodlemedia\/2012112900\/editor_plugin.js","spellchecker_rpc_url":"'.$CFG->wwwroot.'\/lib\/editor\/tinymce\/plugins\/spellchecker\/rpc.php","spellchecker_languages":"+English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr,German=de,Italian=it,Polish=pl,Portuguese=pt,Spanish=es,Swedish=sv","file_browser_callback":"M.editor_tinymce.filepicker"}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_filepicker(Y, "'.$element_id.'", {"image":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Ras Razawa","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_local\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_recent\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_upload\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"URL downloader","type":"url","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_url\/'.$rev.'\/icon","supported_types":[".gif",".jpe",".jpeg",".jpg",".png",".svg",".svgz"],"return_types":3,"sortorder":4},"5":{"id":"5","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_user\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":5},"6":{"id":"6","name":"Wikimedia","type":"wikimedia","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_wikimedia\/'.$rev.'\/icon","supported_types":[],"return_types":3,"sortorder":6}},"externallink":true,"userprefs":{"recentrepository":"3","recentlicense":"public","recentviewmode":""},"accepted_types":[".gif",".jpe",".jpeg",".jpg",".png",".svg",".svgz"],"return_types":7,"context":{"id":"5","contextlevel":30,"instanceid":"2","path":"\/1\/5","depth":"2"},"client_id":"512105b0bb58c","maxbytes":0,"areamaxbytes":-1,"env":"editor","itemid":'.$draft_itemid.'},"media":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Ras Razawa","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_local\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_recent\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_upload\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":3},"5":{"id":"5","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_user\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":5},"6":{"id":"6","name":"Wikimedia","type":"wikimedia","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_wikimedia\/'.$rev.'\/icon","supported_types":[],"return_types":3,"sortorder":6},"7":{"id":"7","name":"Youtube videos","type":"youtube","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_youtube\/'.$rev.'\/icon","supported_types":[".3gp",".avi",".dv",".dif",".flv",".f4v",".mov",".movie",".mp4",".m4v",".mpeg",".mpe",".mpg",".ogv",".qt",".rmvb",".rv",".swf",".swfl",".webm",".wmv",".asf"],"return_types":1,"sortorder":7}},"externallink":true,"userprefs":{"recentrepository":"3","recentlicense":"public","recentviewmode":""},"accepted_types":[".3gp",".avi",".dv",".dif",".flv",".f4v",".mov",".movie",".mp4",".m4v",".mpeg",".mpe",".mpg",".ogv",".qt",".rmvb",".rv",".swf",".swfl",".webm",".wmv",".asf",".aac",".aif",".aiff",".aifc",".au",".m3u",".mp3",".m4a",".oga",".ogg",".ra",".ram",".rm",".wav"],"return_types":7,"context":{"id":"5","contextlevel":30,"instanceid":"2","path":"\/1\/5","depth":"2"},"client_id":"512105b0c60f3","maxbytes":0,"areamaxbytes":-1,"env":"editor","itemid":'.$draft_itemid.'},"link":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Ras Razawa","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_local\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_recent\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_upload\/'.$rev.'\/icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"URL downloader","type":"url","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_url\/'.$rev.'\/icon","supported_types":[".gif",".jpe",".jpeg",".jpg",".png",".svg",".svgz"],"return_types":3,"sortorder":4},"5":{"id":"5","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_user\/'.$rev.'\/icon","supported_types":[],"return_types":6,"sortorder":5},"6":{"id":"6","name":"Wikimedia","type":"wikimedia","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_wikimedia\/'.$rev.'\/icon","supported_types":[],"return_types":3,"sortorder":6},"7":{"id":"7","name":"Youtube videos","type":"youtube","icon":"'.$CFG->wwwroot.'\/theme\/image.php\/standard\/repository_youtube\/'.$rev.'\/icon","supported_types":[".3gp",".avi",".dv",".dif",".flv",".f4v",".mov",".movie",".mp4",".m4v",".mpeg",".mpe",".mpg",".ogv",".qt",".rmvb",".rv",".swf",".swfl",".webm",".wmv",".asf"],"return_types":1,"sortorder":7}},"externallink":true,"userprefs":{"recentrepository":"3","recentlicense":"public","recentviewmode":""},"accepted_types":[],"return_types":7,"context":{"id":"5","contextlevel":30,"instanceid":"2","path":"\/1\/5","depth":"2"},"client_id":"512105b0ceec7","maxbytes":0,"areamaxbytes":-1,"env":"editor","itemid":'.$draft_itemid.'}}); }); });
	});
	//]]>
	</script>'; /**/
	}
else {
 $content .= '
	<script type="text/javascript">
	//<![CDATA[
	YUI().use(\'node\', function(Y) {
	M.util.load_flowplayer();
	Y.on(\'domready\', function() { Y.use(\'core_filepicker\', function(Y) { M.core_filepicker.set_templates(Y, {"generallayout":"\n<div class=\"file-picker fp-generallayout\">\n    <div class=\"fp-repo-area\">\n        <ul class=\"fp-list\">\n            <li class=\"fp-repo\"><a href=\"#\"><img class=\"fp-repo-icon\" width=\"16\" height=\"16\" \/>&nbsp;<span class=\"fp-repo-name\"><\/span><\/a><\/li>\n        <\/ul>\n    <\/div>\n    <div class=\"fp-repo-items\">\n        <div class=\"fp-navbar\">\n            <div>\n                <div class=\"fp-toolbar\">\n                    <div class=\"fp-tb-back\"><a href=\"#\">&laquo; Back<\/a><\/div>\n                    <div class=\"fp-tb-search\"><form><\/form><\/div>\n                    <div class=\"fp-tb-refresh\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=a%2Frefresh\" \/><\/a><\/div>\n                    <div class=\"fp-tb-logout\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=a%2Flogout\" \/><a href=\"#\"><\/a><\/div>\n                    <div class=\"fp-tb-manage\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=a%2Fsetting\" \/> Manage<\/a><\/div>\n                    <div class=\"fp-tb-help\"><a href=\"#\"><img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=a%2Fhelp\" \/> Help<\/a><\/div>\n                <\/div>\n                <div class=\"fp-viewbar\">\n                    <a class=\"fp-vb-icons\" href=\"#\"><\/a>\n                    <a class=\"fp-vb-details\" href=\"#\"><\/a>\n                    <a class=\"fp-vb-tree\" href=\"#\"><\/a>\n                <\/div>\n                <div class=\"fp-clear-left\"><\/div>\n            <\/div>\n            <div class=\"fp-pathbar\">\n                 <span class=\"fp-path-folder\"><a class=\"fp-path-folder-name\" href=\"#\"><\/a><\/span>\n            <\/div>\n        <\/div>\n        <div class=\"fp-content\"><\/div>\n    <\/div>\n<\/div>","iconfilename":"\n<a class=\"fp-file\" href=\"#\" >\n    <div style=\"position:relative;\">\n        <div class=\"fp-thumbnail\"><\/div>\n        <div class=\"fp-reficons1\"><\/div>\n        <div class=\"fp-reficons2\"><\/div>\n    <\/div>\n    <div class=\"fp-filename-field\">\n        <p class=\"fp-filename\"><\/p>\n    <\/div>\n<\/a>","listfilename":"\n<span class=\"fp-filename-icon\">\n    <a href=\"#\">\n        <span class=\"fp-icon\"><\/span>\n        <span class=\"fp-filename\"><\/span>\n    <\/a>\n<\/span>","nextpage":"\n<div class=\"fp-nextpage\">\n    <div class=\"fp-nextpage-link\"><a href=\"#\">more<\/a><\/div>\n    <div class=\"fp-nextpage-loading\">\n        <img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=i%2Floading_small\" \/>\n    <\/div>\n<\/div>","selectlayout":"\n<div class=\"file-picker fp-select\">\n    <div class=\"fp-select-loading\">\n        <img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=i%2Floading_small\" \/>\n    <\/div>\n    <form>\n        <table>\n            <tr class=\"fp-linktype-2\">\n                <td class=\"mdl-right\"><\/td>\n                <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Make a copy of the file<\/label><\/td><\/tr>\n            <tr class=\"fp-linktype-1\">\n                <td class=\"mdl-right\"><\/td>\n                <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Link to the file directly<\/label><\/td><\/tr>\n            <tr class=\"fp-linktype-4\">\n                <td class=\"mdl-right\"><\/td>\n                <td class=\"mdl-left\"><input type=\"radio\"\/><label>&nbsp;Create an alias\/shortcut to the file<\/label><\/td><\/tr>\n            <tr class=\"fp-saveas\">\n                <td class=\"mdl-right\"><label>Save as<\/label>:<\/td>\n                <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n            <tr class=\"fp-setauthor\">\n                <td class=\"mdl-right\"><label>Author<\/label>:<\/td>\n                <td class=\"mdl-left\"><input type=\"text\" \/><\/td><\/tr>\n            <tr class=\"fp-setlicense\">\n                <td class=\"mdl-right\"><label>Choose license<\/label>:<\/td>\n                <td class=\"mdl-left\"><select><\/select><\/td><\/tr>\n        <\/table>\n        <div class=\"fp-select-buttons\">\n            <button class=\"fp-select-confirm\">Select this file<\/button>\n            <button class=\"fp-select-cancel\">Cancel<\/button>\n        <\/div>\n    <\/form>\n    <div class=\"fp-info\">\n        <div class=\"fp-hr\"><\/div>\n        <p class=\"fp-thumbnail\"><\/p>\n        <div class=\"fp-fileinfo\">\n            <div class=\"fp-datemodified\">Last modified: <span class=\"fp-value\"><\/span><\/div>\n            <div class=\"fp-datecreated\">Created: <span class=\"fp-value\"><\/span><\/div>\n            <div class=\"fp-size\">Size: <span class=\"fp-value\"><\/span><\/div>\n            <div class=\"fp-license\">Licence: <span class=\"fp-value\"><\/span><\/div>\n            <div class=\"fp-author\">Author: <span class=\"fp-value\"><\/span><\/div>\n            <div class=\"fp-dimensions\">Dimensions: <span class=\"fp-value\"><\/span><\/div>\n        <\/div>\n    <div>\n<\/div>","uploadform":"\n<div class=\"fp-upload-form mdl-align\">\n    <div class=\"fp-content-center\">\n        <form enctype=\"multipart\/form-data\" method=\"POST\">\n            <table >\n                <tr class=\"fp-file\">\n                    <td class=\"mdl-right\"><label>Attachment<\/label>:<\/td>\n                    <td class=\"mdl-left\"><input type=\"file\"\/><\/td><\/tr>\n                <tr class=\"fp-saveas\">\n                    <td class=\"mdl-right\"><label>Save as<\/label>:<\/td>\n                    <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n                <tr class=\"fp-setauthor\">\n                    <td class=\"mdl-right\"><label>Author<\/label>:<\/td>\n                    <td class=\"mdl-left\"><input type=\"text\"\/><\/td><\/tr>\n                <tr class=\"fp-setlicense\">\n                    <td class=\"mdl-right\"><label>Choose license<\/label>:<\/td>\n                    <td class=\"mdl-left\"><select><\/select><\/td><\/tr>\n            <\/table>\n        <\/form>\n        <div><button class=\"fp-upload-btn\">Upload this file<\/button><\/div>\n    <\/div>\n<\/div> ","loading":"\n<div class=\"fp-content-loading\">\n    <div class=\"fp-content-center\">\n        <img src=\"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&amp;component=core&amp;image=i%2Floading_small\" \/>\n    <\/div>\n<\/div>","error":"\n<div class=\"fp-content-error\" ><div class=\"fp-error\"><\/div><\/div>","message":"\n<div class=\"file-picker fp-msg\">\n    <p class=\"fp-msg-text\"><\/p>\n    <button class=\"fp-msg-butok\">OK<\/button>\n<\/div>","processexistingfile":"\n<div class=\"file-picker fp-dlg\">\n    <p class=\"fp-dlg-text\"><\/p>\n    <div class=\"fp-dlg-buttons\">\n        <button class=\"fp-dlg-butoverwrite\">Overwrite<\/button>\n        <button class=\"fp-dlg-butrename\"><\/button>\n        <button class=\"fp-dlg-butcancel\">Cancel<\/button>\n    <\/div>\n<\/div>","processexistingfilemultiple":"\n<div class=\"file-picker fp-dlg\">\n    <p class=\"fp-dlg-text\"><\/p>\n    <a class=\"fp-dlg-butoverwrite fp-panel-button\" href=\"#\">Overwrite<\/a>\n    <a class=\"fp-dlg-butcancel fp-panel-button\" href=\"#\">Cancel<\/a>\n    <a class=\"fp-dlg-butrename fp-panel-button\" href=\"#\"><\/a>\n    <br\/>\n    <a class=\"fp-dlg-butoverwriteall fp-panel-button\" href=\"#\">Overwrite all<\/a>\n    <a class=\"fp-dlg-butrenameall fp-panel-button\" href=\"#\">Rename all<\/a>\n<\/div>","loginform":"\n<div class=\"fp-login-form\">\n    <div class=\"fp-content-center\">\n        <form>\n            <table >\n                <tr class=\"fp-login-popup\">\n                    <td colspan=\"2\">\n                        <label>Click \"Login\" button to login<\/label>\n                        <p class=\"fp-popup\"><button class=\"fp-login-popup-but\">Login<\/button><\/p><\/td><\/tr>\n                <tr class=\"fp-login-textarea\">\n                    <td colspan=\"2\"><p><textarea><\/textarea><\/p><\/td><\/tr>\n                <tr class=\"fp-login-select\">\n                    <td align=\"right\"><label><\/label><\/td>\n                    <td align=\"left\"><select><\/select><\/td><\/tr>\n                <tr class=\"fp-login-input\">\n                    <td class=\"label\"><label><\/label><\/td>\n                    <td class=\"input\"><input\/><\/td><\/tr>\n                <tr class=\"fp-login-radiogroup\">\n                    <td align=\"right\" width=\"30%\" valign=\"top\"><label><\/label><\/td>\n                    <td align=\"left\" valign=\"top\"><p class=\"fp-login-radio\"><input \/> <label><\/label><\/p><\/td><\/tr>\n            <\/table>\n            <p><button class=\"fp-login-submit\">Submit<\/button><\/p>\n        <\/form>\n    <\/div>\n<\/div>"}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_editor(Y, null, {"mode":"exact","elements":null,"relative_urls":false,"document_base_url":"'.$CFG->wwwroot.'","content_css":"'.$CFG->wwwroot.'\/theme\/styles_debug.php?theme=arialist&type=editor","language":"'.$lang.'","directionality":"'.$directionality.'","plugin_insertdate_dateFormat ":"%A, %d %B %Y","plugin_insertdate_timeFormat ":"%I:%M %p","theme":"advanced","skin":"o2k7","skin_variant":"silver","apply_source_formatting":true,"remove_script_host":false,"entity_encoding":"raw","plugins":"moodlemedia,advimage,safari,table,style,layer,advhr,advlink,emotions,inlinepopups,searchreplace,paste,directionality,fullscreen,moodlenolink,nonbreaking,contextmenu,insertdatetime,save,iespell,preview,print,noneditable,visualchars,xhtmlxtras,template,pagebreak,spellchecker","theme_advanced_font_sizes":"1,2,3,4,5,6,7","theme_advanced_layout_manager":"SimpleLayout","theme_advanced_toolbar_align":"left","theme_advanced_buttons1":"fontselect,fontsizeselect,formatselect","theme_advanced_buttons1_add":"|,undo,redo,|,search,replace,|,fullscreen","theme_advanced_buttons2":"bold,italic,underline,strikethrough,sub,sup,|,justifyleft,justifycenter,justifyright","theme_advanced_buttons2_add":"|,cleanup,removeformat,pastetext,pasteword,|,forecolor,backcolor,|,ltr,rtl","theme_advanced_buttons3":"bullist,numlist,outdent,indent,|,link,unlink,moodlenolink,|,image,moodlemedia,nonbreaking,charmap","theme_advanced_buttons3_add":"table,|,code,spellchecker","theme_advanced_fonts":"Trebuchet=Trebuchet MS,Verdana,Arial,Helvetica,sans-serif;Arial=arial,helvetica,sans-serif;Courier New=courier new,courier,monospace;Georgia=georgia,times new roman,times,serif;Tahoma=tahoma,arial,helvetica,sans-serif;Times New Roman=times new roman,times,serif;Verdana=verdana,arial,helvetica,sans-serif;Impact=impact;Wingdings=wingdings","theme_advanced_resize_horizontal":true,"theme_advanced_resizing":true,"theme_advanced_resizing_min_height":30,"theme_advanced_toolbar_location":"top","theme_advanced_statusbar_location":"bottom","spellchecker_rpc_url":"'.$CFG->wwwroot.'\/lib\/editor\/tinymce\/tiny_mce\/3.5.1.1\/plugins\/spellchecker\/rpc.php","spellchecker_languages":"+English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr,German=de,Italian=it,Polish=pl,Portuguese=pt,Spanish=es,Swedish=sv","extended_valid_elements":"nolink,tex,algebra,lang[lang]","custom_elements":"nolink,~tex,~algebra,lang","file_browser_callback":"M.editor_tinymce.filepicker"}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_filepicker(Y, null, {"image":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[".gif",".jpe",".jpeg",".jpg",".png",".svg",".svgz"],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"5121286324358","maxbytes":0,"env":"editor","itemid":'.$draft_itemid.'},"media":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[".3gp",".avi",".dv",".dif",".flv",".f4v",".mov",".movie",".mp4",".m4v",".mpeg",".mpe",".mpg",".ogv",".qt",".rmvb",".rv",".swf",".swfl",".webm",".wmv",".asf",".aac",".aif",".aiff",".aifc",".au",".m3u",".mp3",".m4a",".oga",".ogg",".ra",".ram",".rm",".wav"],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"5121286326185","maxbytes":0,"env":"editor","itemid":'.$draft_itemid.'},"link":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"5121286327e18","maxbytes":0,"env":"editor","itemid":'.$draft_itemid.'}}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_editor(Y, "'.$element_id.'", {"mode":"exact","elements":"'.$element_id.'","relative_urls":false,"document_base_url":"'.$CFG->wwwroot.'","content_css":"'.$CFG->wwwroot.'\/theme\/styles_debug.php?theme=arialist&type=editor","language":"'.$lang.'","directionality":"'.$directionality.'","plugin_insertdate_dateFormat ":"%A, %d %B %Y","plugin_insertdate_timeFormat ":"%I:%M %p","theme":"advanced","skin":"o2k7","skin_variant":"silver","apply_source_formatting":true,"remove_script_host":false,"entity_encoding":"raw","plugins":"moodlemedia,advimage,safari,table,style,layer,advhr,advlink,emotions,inlinepopups,searchreplace,paste,directionality,fullscreen,moodlenolink,nonbreaking,contextmenu,insertdatetime,save,iespell,preview,print,noneditable,visualchars,xhtmlxtras,template,pagebreak,spellchecker","theme_advanced_font_sizes":"1,2,3,4,5,6,7","theme_advanced_layout_manager":"SimpleLayout","theme_advanced_toolbar_align":"left","theme_advanced_buttons1":"fontselect,fontsizeselect,formatselect","theme_advanced_buttons1_add":"|,undo,redo,|,search,replace,|,fullscreen","theme_advanced_buttons2":"bold,italic,underline,strikethrough,sub,sup,|,justifyleft,justifycenter,justifyright","theme_advanced_buttons2_add":"|,cleanup,removeformat,pastetext,pasteword,|,forecolor,backcolor,|,ltr,rtl","theme_advanced_buttons3":"bullist,numlist,outdent,indent,|,link,unlink,moodlenolink,|,image,moodlemedia,nonbreaking,charmap","theme_advanced_buttons3_add":"table,|,code,spellchecker","theme_advanced_fonts":"Trebuchet=Trebuchet MS,Verdana,Arial,Helvetica,sans-serif;Arial=arial,helvetica,sans-serif;Courier New=courier new,courier,monospace;Georgia=georgia,times new roman,times,serif;Tahoma=tahoma,arial,helvetica,sans-serif;Times New Roman=times new roman,times,serif;Verdana=verdana,arial,helvetica,sans-serif;Impact=impact;Wingdings=wingdings","theme_advanced_resize_horizontal":true,"theme_advanced_resizing":true,"theme_advanced_resizing_min_height":30,"theme_advanced_toolbar_location":"top","theme_advanced_statusbar_location":"bottom","spellchecker_rpc_url":"'.$CFG->wwwroot.'\/lib\/editor\/tinymce\/tiny_mce\/3.5.1.1\/plugins\/spellchecker\/rpc.php","spellchecker_languages":"+English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr,German=de,Italian=it,Polish=pl,Portuguese=pt,Spanish=es,Swedish=sv","extended_valid_elements":"nolink,tex,algebra,lang[lang]","custom_elements":"nolink,~tex,~algebra,lang","file_browser_callback":"M.editor_tinymce.filepicker"}); }); });
	Y.on(\'domready\', function() { Y.use(\'editor_tinymce\', function(Y) { M.editor_tinymce.init_filepicker(Y, "'.$element_id.'", {"image":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[".gif",".jpe",".jpeg",".jpg",".png",".svg",".svgz"],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"512128632afb3","maxbytes":0,"env":"editor","itemid":369611589},"media":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[".3gp",".avi",".dv",".dif",".flv",".f4v",".mov",".movie",".mp4",".m4v",".mpeg",".mpe",".mpg",".ogv",".qt",".rmvb",".rv",".swf",".swfl",".webm",".wmv",".asf",".aac",".aif",".aiff",".aifc",".au",".m3u",".mp3",".m4a",".oga",".ogg",".ra",".ram",".rm",".wav"],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"512128632cc9a","maxbytes":0,"env":"editor","itemid":369611589},"link":{"defaultlicense":"allrightsreserved","licenses":[{"shortname":"unknown","fullname":"Other"},{"shortname":"allrightsreserved","fullname":"All rights reserved"},{"shortname":"public","fullname":"Public domain"},{"shortname":"cc","fullname":"Creative Commons"},{"shortname":"cc-nd","fullname":"Creative Commons - NoDerivs"},{"shortname":"cc-nc-nd","fullname":"Creative Commons - No Commercial NoDerivs"},{"shortname":"cc-nc","fullname":"Creative Commons - No Commercial"},{"shortname":"cc-nc-sa","fullname":"Creative Commons - No Commercial ShareAlike"},{"shortname":"cc-sa","fullname":"Creative Commons - ShareAlike"}],"author":"Sergey Zavarzin","repositories":{"1":{"id":"1","name":"Server files","type":"local","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_local&image=icon","supported_types":[],"return_types":6,"sortorder":1},"2":{"id":"2","name":"Recent files","type":"recent","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_recent&image=icon","supported_types":[],"return_types":2,"sortorder":2},"3":{"id":"3","name":"Upload a file","type":"upload","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_upload&image=icon","supported_types":[],"return_types":2,"sortorder":3},"4":{"id":"4","name":"Private files","type":"user","icon":"'.$CFG->wwwroot.'\/theme\/image.php?theme=arialist&component=repository_user&image=icon","supported_types":[],"return_types":6,"sortorder":4}},"externallink":true,"userprefs":{"recentrepository":"6","recentlicense":"allrightsreserved","recentviewmode":""},"accepted_types":[],"return_types":7,"context":{"id":"2","contextlevel":50,"instanceid":"1","path":"\/1\/2","depth":"2"},"client_id":"512128632e9b0","maxbytes":0,"env":"editor","itemid":369611589}}); }); });
	Y.on(\'click\', openpopup, "#action_link51212862ea22a1", null, {"url":"'.$CFG->wwwroot.'\/report\/loglive\/index.php?id=1&inpopup=1","name":"popup","options":"height=400,width=500,top=0,left=0,menubar=0,location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent"});
	
	});
	//]]>
	</script>'; /**/
};
return $content;
}
