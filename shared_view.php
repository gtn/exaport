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

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/blockmediafunc.php');

$access = optional_param('access', 0, PARAM_TEXT);

require_login(0, true);

// main content:
$general_content = '';

$url = '/blocks/exaport/shared_view.php';
$PAGE->set_url($url);
$context = context_system::instance();
$PAGE->set_context($context);

if (!$view = block_exaport_get_view_from_access($access)) {
    print_error("viewnotfound", "block_exaport");
}

// Get pdf settings
$pdf_settings = unserialize($view->pdf_settings);

$pdfsettingslink = $PAGE->url;
$pdfsettingslink->params(array(
    'courseid' => optional_param('courseid', 1, PARAM_TEXT),
    'access' => optional_param('access', 0, PARAM_TEXT),
));

// PDF form definition
require_once($CFG->libdir . '/formslib.php');

class pdfsettings_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $USER, $CFG;
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $mform->addElement('hidden', 'ispdf', '1');
        $mform->setType('ispdf', PARAM_INT);

        $view = $this->_customdata['view'];
        // All pdf settings are only for view owners
        if ($USER->id === $view->userid) {
            // Container: collapsible.
            $mform->addElement('html', '<fieldset class="clearfix view-group">');
            $mform->addElement('html', '<legend class="view-group-header">' . block_exaport_get_string('pdf_settings') . '</legend>');
            $mform->addElement('html', '<div class="view-group-content clearfix"><div>');
            // Description.
            $mform->addElement('html', '<div class="alert alert-info">' . block_exaport_get_string('pdf_settings_description') . '</div>');
            // Font family. Grouped by 'fixed' and 'Custom' files.
            $mform->addElement('selectgroups', 'fontfamily', block_exaport_get_string('pdf_settings_fontfamily'), block_export_getpdffontfamilies(true));

            // Upload custom font.
            // Toggler of file uploader
            $mform->addElement('static', 'fontuploader_toggler', '', 'Or upload custom font');
            // container of file uploader
            $mform->addElement('html', '<div class="uploadFont-container">');
            // Check permissions for pdf (dompdf creates own cache file in the own folder).
            // TODO: use moodle temp folder ('fontCache' option of dompdf)?
            $relatedpathtodompdffontcachefile = "/blocks/exaport/lib/classes/dompdf/vendor/dompdf/dompdf/lib/fonts/dompdf_font_family_cache.php";
            $pathtodompdffontcachefile = $CFG->dirroot . $relatedpathtodompdffontcachefile;
            // TODO:  Deprecated for new dompdf?
            /*if (!is_writable($pathtodompdffontcachefile)) {
				// Try to make it writeable.
                chmod($pathtodompdffontcachefile, 0766);
				// Check again
				if (!is_writable($pathtodompdffontcachefile)) {
                    $mform->addElement('html', '<div class="alert alert-danger">File "' . $relatedpathtodompdffontcachefile . '" must be writable. <br/>Otherwise we do not guarantee the operation of this function</div>');
                }
            }*/
            if (!is_writable(dirname($pathtodompdffontcachefile))) {
                // Try to make it writeable.
                chmod($pathtodompdffontcachefile, 0766);
                // Check again
                if (!is_writable(dirname($pathtodompdffontcachefile))) {
                    $mform->addElement('html', '<div class="alert alert-danger">Folder "' . dirname($relatedpathtodompdffontcachefile) . '" must be writable. <br/>Otherwise we do not guarantee the operation of this function</div>');
                }
            }

            $mform->addElement(
                'filepicker',
                'pdf_customfont',
                '',
                null,
                [
                    'accepted_types' => ['.ttf'],
                ]
            );
            $mform->addHelpButton('pdf_customfont', 'pdf_customfont', 'block_exaport');
            $mform->addElement('html', '</div>');
            // Font size.
            $mform->addElement('text', 'fontsize', block_exaport_get_string('pdf_settings_fontsize'));
            $mform->setType('fontsize', PARAM_INT);
            $mform->setDefault('fontsize', '14');
            // Page size.
            $pagesizes = ['A0', 'A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'B0', 'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'letter', 'legal'];
            $pagesizes = array_combine($pagesizes, $pagesizes);
            $mform->addElement('select', 'pagesize', block_exaport_get_string('pdf_settings_pagesize'), $pagesizes);
            $mform->setDefault('pagesize', 'A4');
            // Page size.
            $pageorients = [
                'portrait' => block_exaport_get_string('pdf_settings_pageorient.portrait'),
                'landscape' => block_exaport_get_string('pdf_settings_pageorient.landscape'),
            ];
            $mform->addElement('select', 'pageorient', block_exaport_get_string('pdf_settings_pageorient'), $pageorients);
            $mform->setDefault('pageorient', 'landscape');
            // Show view metadata.
            $mform->addElement('checkbox', 'showmetadata', '', block_exaport_get_string('pdf_settings_showmetadata'));
            $mform->setDefault('showmetadata', 1);
            // Show user info:
            // name
            $mform->addElement('checkbox', 'showusername', '', block_exaport_get_string('pdf_settings_showusername'));
            $mform->setDefault('showusername', 1);
            $mform->hideIf('showusername', 'showmetadata', 'notchecked');
            // user picture
            $mform->addElement('checkbox', 'showuserpicture', '', block_exaport_get_string('pdf_settings_showuserpicture'));
            $mform->setDefault('showuserpicture', 0);
            $mform->hideIf('showuserpicture', 'showmetadata', 'notchecked');
            // email
            $mform->addElement('checkbox', 'showuseremail', '', block_exaport_get_string('pdf_settings_showuseremail'));
            $mform->setDefault('showuseremail', 0);
            $mform->hideIf('showuseremail', 'showmetadata', 'notchecked');
            // email
            $mform->addElement('checkbox', 'showuserphone', '', block_exaport_get_string('pdf_settings_showuserphone'));
            $mform->setDefault('showuserphone', 0);
            $mform->hideIf('showuserphone', 'showmetadata', 'notchecked');

            // Close collapsible container.
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</fieldset>');
        }

        // Download pdf button.
        // 'submit' type is not suitable, because it is disabled after first pressing
        $mform->addElement('button', 'download', block_exaport_get_string('download_pdf'), ['onclick' => 'this.form.submit();']);
    }

}

$is_pdf = false;
// the PDF downloading also depends from url parameters: ispf  or as pdf
$is_pdf = optional_param('ispdf', 0, PARAM_INT);
if (!$is_pdf) {
    $is_pdf = optional_param('aspdf', 0, PARAM_INT);
}

$pdfForm = new pdfsettings_form($pdfsettingslink->raw_out(false), ['view' => $view]);

if ($fromPdform = $pdfForm->get_data()) {
    $is_pdf = true;

    // Save pdf settings into view settings - only for view owner
    if ($USER->id == $view->userid) {
        $customfontcfilecontent = $pdfForm->get_file_content('pdf_customfont');
        $newFontFile = null;
        if ($customfontcfilecontent) {
            // if it is an owner of the view - allow to upload file permanently
            $fs = get_file_storage();
            if ($view->userid == $USER->id) {
                // Dompdf library has wrong regular expression and incorrect works with filenames with ')', so:
                $filename = str_replace(['(', ')'], ['_', '_'], $pdfForm->get_new_filename('pdf_customfont'));
                $fileinfo = array(
                    'contextid' => context_system::instance()->id,
                    'component' => 'block_exaport',
                    'filearea' => 'pdf_fontfamily',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $filename,
                );
                $newFontFile = $fs->create_file_from_string($fileinfo, $customfontcfilecontent);
            } else {
                // only for temporary using!
                // Disabled. Not owners can not change any pdf setting!
                /*$draftid = file_get_submitted_draft_itemid('pdf_customfont');
                $newFontFile = $fs->get_file(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, '/', $pdfForm->get_new_filename('pdf_customfont'));*/
            }
        }

        if ($newFontFile !== null) {
            // uploaded file has more priority
            $fontfamily = $newFontFile->get_id();
        } else {
            $fontfamily = $fromPdform->fontfamily;
        }
        $allpossiblefonts = block_export_getpdffontfamilies();

        // Check default settings.
        // font family
        if (!trim($fontfamily) /*|| !in_array($fontfamily, array_keys($allpossiblefonts))*/) {
            $fontfamily = 'Dejavu sans';
        }
        $pdf_settings['fontfamily'] = $fontfamily;
        // fontsize
        $fontsize = $fromPdform->fontsize;
        if (!trim($fontsize)) {
            $fontsize = '14';
        }
        $pdf_settings['fontsize'] = $fontsize;
        // pagesize
        $pagesize = $fromPdform->pagesize;
        if (!trim($pagesize)) {
            $pagesize = 'A4';
        }
        $pdf_settings['pagesize'] = $pagesize;
        // page orientation
        $pageorient = $fromPdform->pageorient;
        if (!trim($pageorient)) {
            $pageorient = 'landscape';
        }
        $pdf_settings['pageorient'] = $pageorient;
        // show view metadata
        if (@$fromPdform->showmetadata) {
            $pdf_settings['showmetadata'] = $fromPdform->showmetadata;
        } else {
            $pdf_settings['showmetadata'] = 0;
        }
        // show user name
        $pdf_settings['showusername'] = @$fromPdform->showusername ? 1 : 0;
        // show user picture
        $pdf_settings['showuserpicture'] = @$fromPdform->showuserpicture ? 1 : 0;
        // show user email
        $pdf_settings['showuseremail'] = @$fromPdform->showuseremail ? 1 : 0;
        // show user phone
        $pdf_settings['showuserphone'] = @$fromPdform->showuserphone ? 1 : 0;

        // Save pdf settings into DB
        $pdf_settings_serialized = serialize($pdf_settings);
        $view->pdf_settings = $pdf_settings_serialized;
        $DB->update_record('block_exaportview', $view);

    }

}

// check default pdf settings (for example if the pdf is not from the form)
$pdf_settings['fontfamily'] = isset($pdf_settings['fontfamily']) ? $pdf_settings['fontfamily'] : 'Dejavu sans';
$pdf_settings['fontsize'] = isset($pdf_settings['fontsize']) ? $pdf_settings['fontsize'] : '14';
$pdf_settings['pagesize'] = isset($pdf_settings['pagesize']) ? $pdf_settings['pagesize'] : 'A4';
$pdf_settings['pageorient'] = isset($pdf_settings['pageorient']) ? $pdf_settings['pageorient'] : 'landscape';
$pdf_settings['showmetadata'] = isset($pdf_settings['showmetadata']) ? $pdf_settings['showmetadata'] : 0;
$pdf_settings['showusername'] = isset($pdf_settings['showusername']) ? $pdf_settings['showusername'] : 1;
$pdf_settings['showuserpicture'] = isset($pdf_settings['showuserpicture']) ? $pdf_settings['showuserpicture'] : 0;
$pdf_settings['showuseremail'] = isset($pdf_settings['showuseremail']) ? $pdf_settings['showuseremail'] : 0;
$pdf_settings['showuserphone'] = isset($pdf_settings['showuserphone']) ? $pdf_settings['showuserphone'] : 0;

$conditions = array("id" => $view->userid);
if (!$user = $DB->get_record("user", $conditions)) {
    print_error("nouserforid", "block_exaport");
}

$portfoliouser = block_exaport_get_user_preferences($user->id);

// Read blocks.
$query = "select b.*" . // , i.*, i.id as itemid".
    " FROM {block_exaportviewblock} b" .
    " WHERE b.viewid = ? ORDER BY b.positionx, b.positiony";

$blocks = $DB->get_records_sql($query, array($view->id));

$badges = block_exaport_get_all_user_badges($view->userid);

// Read columns.
$columns = array();
foreach ($blocks as $block) {
    if (!isset($columns[$block->positionx])) {
        $columns[$block->positionx] = array();
    }

    if ($block->type == 'item') {
        $conditions = array("id" => $block->itemid);
        if ($item = $DB->get_record("block_exaportitem", $conditions)) {
            if (!$block->width) {
                $block->width = 320;
            }
            if (!$block->height) {
                $block->height = 240;
            }
            $item->intro = process_media_url($item->intro, $block->width, $block->height);
            // Add checking on sharable item.
            if ($sharable = block_exaport_can_user_access_shared_item($view->userid, $item->id) || $view->userid == $item->userid) {
                $block->item = $item;
            } else {
                continue; // Hide unshared items.
            }
        } else {
            $block->type = 'text';
        }
    }
    $columns[$block->positionx][] = $block;
}

block_exaport_init_js_css();

if (!$is_pdf) {
    if ($view->access->request == 'intern') {
        block_exaport_print_header("shared_views");
    } else {
        $PAGE->requires->css('/blocks/exaport/css/shared_view.css');
        $PAGE->set_title(get_string("externaccess", "block_exaport"));
        $PAGE->set_heading(get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));

        $general_content .= $OUTPUT->header();
        $general_content .= block_exaport_wrapperdivstart();
    }
}

if (!$is_pdf) {
    ?>
    <script type="text/javascript">
        //<![CDATA[
        jQueryExaport(function ($) {
            $('.view-item').click(function (event) {
                if ($(event.target).is('a')) {
                    // ignore if link was clicked
                    return;
                }

                var link = $(this).find('.view-item-link a');
                if (link.length)
                    document.location.href = link.attr('href');
            });
        });
        //]]>
    </script>
    <?php
}

$comp = block_exaport_check_competence_interaction();

require_once(__DIR__ . '/lib/resumelib.php');
$resume = block_exaport_get_resume_params($view->userid, true);

if (!$is_pdf) {
    echo block_exaport_get_view_layout_style_from_settings($view, 'shared_view');
}

$colslayout = array(
    "1" => 1, "2" => 2, "3" => 2, "4" => 2, "5" => 3, "6" => 3, "7" => 3, "8" => 4, "9" => 4, "10" => 5,
);
if (!isset($view->layout) || $view->layout == 0) {
    $view->layout = 2;
}
$general_content .= '<div id="view">';
$general_content .= '<table class="table_layout layout' . $view->layout . '""><tr>';
$data_for_pdf = array(); // for old pdf view
$data_for_pdf_blocks = array(); // for new pdf view

$addAttachementsToBlockView = function($attachments, $block) {
    $body_content = '';
    if ($block->resume_withfiles && $attachments && is_array($attachments) && count($attachments) > 0) {
        $body_content .= '<ul class="resume_attachments ' . $block->resume_itemtype . '_attachments">';
        foreach ($attachments as $attachm) {
            $body_content .= '<li><a href="' . $attachm['fileurl'] . '" target="_blank">' . $attachm['filename'] . '</a></li>';
        }
        $body_content .= '</ul>';
    }
    return $body_content;
};

for ($i = 1; $i <= $colslayout[$view->layout]; $i++) {
    $data_for_pdf[$i] = array();
    $data_for_pdf_blocks[$i] = array();
    $general_content .= '<td class="view-column td' . $i . '">';
    if (isset($columns[$i])) {
        foreach ($columns[$i] as $block) {
            $body_content_forPdf = '';
            $blockForPdf = '<div class="view-block">';
            if ($block->text) {
                $block->text = file_rewrite_pluginfile_urls($block->text, 'pluginfile.php', context_user::instance($USER->id)->id,
                    'block_exaport', 'view_content', $access);
                $block->text = format_text($block->text, FORMAT_HTML);
            }
            $attachments = array();
            switch ($block->type) {
                case 'item':
                    $item = $block->item;
                    $competencies = null;

                    if ($comp) {
                        $comps = block_exaport_get_active_comps_for_item($item);
                        if ($comps && is_array($comps) && array_key_exists('descriptors', $comps)) {
                            $competencies = $comps['descriptors'];
                        } else {
                            $competencies = null;
                        }

                        if (is_array($competencies)) {
                            $competenciesoutput = "";
                            foreach ($competencies as $competence) {
                                $competenciesoutput .= $competence->title . '<br/>';
                            }

                            // TODO: still needed?
                            $competenciesoutput = str_replace("\r", "", $competenciesoutput);
                            $competenciesoutput = str_replace("\n", "", $competenciesoutput);
                            $competenciesoutput = str_replace("\"", "&quot;", $competenciesoutput);
                            $competenciesoutput = str_replace("'", "&prime;", $competenciesoutput);

                            $item->competences = $competenciesoutput;
                        }

                    }

                    $href = 'shared_item.php?access=view/' . $access . '&itemid=' . $item->id . '&att=' . $item->attachment;

                    $general_content .= '<div class="view-item view-item-type-' . $item->type . '">';
                    // Thumbnail of item.
                    $fileparams = '';
                    if ($item->type == "file") {
                        $select = "contextid='" . context_user::instance($item->userid)->id . "' " .
                            " AND component='block_exaport' AND filearea='item_file' AND itemid='" . $item->id . "' AND filesize>0 ";
                        if ($files = $DB->get_records_select('files', $select, null, 'id, filename, mimetype, filesize')) {
                            if (is_array($files)) {
                                $width = '';
                                if (count($files) > 5) {
                                    $width = 's35';
                                } else if (count($files) > 3) {
                                    $width = 's40';
                                } else if (count($files) > 2) {
                                    $width = 's50';
                                } else if (count($files) > 1) {
                                    $width = 's75';
                                }

                                foreach ($files as $file) {
                                    if (strpos($file->mimetype, "image") !== false) {
                                        $imgsrc = $CFG->wwwroot . "/pluginfile.php/" . context_user::instance($item->userid)->id .
                                            "/" . 'block_exaport' . "/" . 'item_file' . "/view/" . $access . "/itemid/" . $item->id . "/" .
                                            $file->filename;
                                        $general_content .= '<div class="view-item-image"><img src="' . $imgsrc . '" class="' . $width . '" alt=""/></div>';
                                        $blockForPdf .= '<div class="view-item-image">
                                                            <img align = "right"
                                                                border = "0"
                                                                src = "' . $imgsrc . '"
                                                                width = "' . ((int)filter_var($width, FILTER_SANITIZE_NUMBER_INT) ?: '100') . '"
                                                                alt = "" />
                                                         </div>';
                                    } else {
                                        // Link to file.
                                        $ffurl = s("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=view/" . $access .
                                            "&itemid=" . $item->id . "&inst=" . $file->pathnamehash);
                                        // Human filesize.
                                        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
                                        $power = $file->filesize > 0 ? floor(log($file->filesize, 1024)) : 0;
                                        $filesize = number_format($file->filesize / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
                                        // Fileinfo block.
                                        $fileparams = '<div class="view-item-file"><a href="' . $ffurl . '" >' . $file->filename . '</a> ' .
                                            '<span class="filedescription">(' . $filesize . ')</span></div>';
                                        if (block_exaport_is_valid_media_by_filename($file->filename)) {
                                            $general_content .= '<div class="view-item-image">
													<img height="60" src="' . $CFG->wwwroot . '/blocks/exaport/pix/media.png" alt="" />
												</div>';
                                            $blockForPdf .= '<img height="60" src="' . $CFG->wwwroot . '/blocks/exaport/pix/media.png" align="right" />';
                                        }
                                    };
                                }
                            }
                        };
                    } else if ($item->type == "link") {
                        $general_content .= '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;"><a href="' .
                            $href . '"><img style="max-width: 100%; max-height: 100%;" src="' . $CFG->wwwroot .
                            '/blocks/exaport/item_thumb.php?item_id=' . $item->id . '&access=' . $access . '" alt=""/></a></div>';
                        $blockForPdf .= '<img align="right"
                                                style="" height="100"
                                                src="' . $CFG->wwwroot . '/blocks/exaport/item_thumb.php?item_id=' . $item->id . '&access=' . $access . '&ispdf=1&vhash=' . $view->hash . '&vid=' . $view->id . '&uid=' . $USER->id . '"
                                                alt="" />';
                    };
                    $general_content .= '<div class="view-item-header" title="' . $item->type . '">' . $item->name;
                    // Falls Interaktion ePortfolio - competences aktiv und User ist Lehrer.
                    if ($comp && has_capability('block/exaport:competences', $context)) {
                        if (is_array($competencies) && count($competencies) > 0) {
                            $general_content .= '<img align="right" src="' . $CFG->wwwroot .
                                '/blocks/exaport/pix/application_view_tile.png" alt="competences"/>';
                        }
                    }
                    $general_content .= '</div>';
                    $blockForPdf .= '<h4>' . $item->name . '</h4>';
                    $general_content .= $fileparams;
                    $blockForPdf .= $fileparams;
                    $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
                        'block_exaport', 'item_content', 'view/' . $access . '/itemid/' . $item->id);
                    $intro = format_text($intro, FORMAT_HTML, ['noclean' => true]);
                    $general_content .= '<div class="view-item-text">';
                    $blockForPdf .= '<div class="view-item-text">';
                    if ($item->url && $item->url != "false") {
                        // Link.
                        $general_content .= '<a href="' . s($item->url) . '" target="_blank">' . str_replace('http://', '', $item->url) . '</a><br />';
                        $blockForPdf .= '<a href="' . s($item->url) . '" target="_blank">' . str_replace('http://', '', $item->url) . '</a><br />';
                    }
                    $general_content .= $intro . '</div>';
                    $blockForPdf .= $intro . '</div>';
                    if (is_array($competencies) && count($competencies) > 0) {
                        $general_content .= '<div class="view-item-competences">' .
                            '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>' .
                            '<a onmouseover="Tip(\'' . $item->competences . '\')" onmouseout="UnTip()">' .
                            '<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/comp.png" class="iconsmall" alt="' . 'competences' . '" />' .
                            '</a></div>';
                    }
                    $general_content .= '<div class="view-item-link"><a href="' . s($href) . '">' . block_exaport_get_string('show') . '</a></div>';
                    $general_content .= '</div>';
                    break;
                case 'personal_information':
                    $general_content .= '<div class="header">' . $block->block_title . '</div>';
                    if ($block->block_title) {
                        $blockForPdf .= '<h4>' . $block->block_title . '</h4>';
                    }
                    $general_content .= '<div class="view-personal-information">';
                    $blockForPdf .= '<div class="view-personal-information">';
                    if (isset($block->picture)) {
                        $general_content .= '<div class="picture" style="float:right; position: relative;"><img src="' . $block->picture .
                            '" alt=""/></div>';
                        $blockForPdf .= '<img src="' . $block->picture . '" align="right" />';
                    }
                    $person_info = '';
                    if (isset($block->firstname) or isset($block->lastname)) {
                        $person_info .= '<div class="name">';
                        if (isset($block->firstname)) {
                            $person_info .= $block->firstname;
                        }
                        if (isset($block->lastname)) {
                            $person_info .= ' ' . $block->lastname;
                        }
                        $person_info .= '</div>';
                    };
                    if (isset($block->email)) {
                        $person_info .= '<div class="email">' . $block->email . '</div>';
                    }
                    if (isset($block->text)) {
                        $text = $block->text;
                        $text = block_exaport_add_view_access_parameter_to_url($text, $access, ['src', 'href']);
                        $person_info .= '<div class="body">' . $text . '</div>';
                    }
                    $general_content .= $person_info;
                    $general_content .= '</div>';
                    $blockForPdf .= $person_info;
                    $blockForPdf .= '</div>';
                    break;
                case 'headline':
                    $general_content .= '<div class="header view-header">' . nl2br($block->text) . '</div>';
                    $blockForPdf .= '<h4>' . nl2br($block->text) . '</h4>';
                    break;
                case 'media':
                    $general_content .= '<div class="header view-header">' . nl2br($block->block_title) . '</div>';
                    if ($block->block_title) {
                        $blockForPdf .= '<h4>' . nl2br($block->block_title) . '</h4>';
                    }
                    $general_content .= '<div class="view-media">';
                    if (!empty($block->contentmedia)) {
                        $general_content .= $block->contentmedia;
                    }
                    $general_content .= '</div>';
                    $blockForPdf .= '<p><i>----media----</i></p>';
                    // $blockForPdf .= '</div>';
                    break;
                case 'badge':
                    if (count($badges) == 0) {
                        continue 2;
                    }
                    $badge = null;
                    foreach ($badges as $tmp) {
                        if ($tmp->id == $block->itemid) {
                            $badge = $tmp;
                            break;
                        };
                    };
                    if (!$badge) {
                        // Badge not found.
                        continue 2;
                    }
                    $general_content .= '<div class="header">' . nl2br($badge->name) . '</div>';
                    $blockForPdf .= '<h4>' . nl2br($badge->name) . '</h4>';
                    $general_content .= '<div class="view-text">';
                    $general_content .= '<div style="float:right; position: relative; height: 100px; width: 100px;" class="picture">';
                    if (!$badge->courseid) { // For badges with courseid = NULL.
                        $badge->imageUrl = (string)moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage',
                            $badge->id, '/', 'f1', false);
                    } else {
                        $context = context_course::instance($badge->courseid);
                        $badge->imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage',
                            $badge->id, '/', 'f1', false);
                    }
                    $general_content .= '<img src="' . $badge->imageUrl . '" />';
                    $general_content .= '</div>';
                    $general_content .= '<div class="badge-description">';
                    $general_content .= format_text($badge->description, FORMAT_HTML);
                    $general_content .= '</div>';
                    $general_content .= '</div>';
                    $blockForPdf .= '<p>' . format_text($badge->description, FORMAT_HTML) . '</p>';
                    $blockForPdf .= '<img align="right" src="' . $badge->imageUrl . '" />';
                    // $blockForPdf .= '</div>';
                    break;
                case 'cv_group':
                    $body_content = '';
                    switch ($block->resume_itemtype) {
                        case 'cover':
                            if ($resume && $resume->cover) {
                                $cover = $resume->cover;
                                $cover = file_rewrite_pluginfile_urls($cover, 'pluginfile.php',
                                    context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_cover', $resume->id);
                                // For shared views we need to have access argument to show attached files fo different cases:
                                $body_content_forPdf .= format_text($cover, FORMAT_HTML); // pdf does not need additional parameter
                                $cover = block_exaport_add_view_access_parameter_to_url($cover, $access, ['src', 'href']);
                                $cover = format_text($cover, FORMAT_HTML);
                                $body_content .= $cover;
                            }
                            break;
                        case 'edu':
                            $items = [];
                            if ($block->text && $resume && $resume->educations) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $itemid) {
                                    if ($resume->educations[$itemid]) {
                                        $item_data = $resume->educations[$itemid];
                                        $description = '';
                                        $description .= '<span class="edu_institution">' . $item_data->institution . ':</span> ';
                                        $description .= '<span class="edu_qualname">' . $item_data->qualname . '</span>';
                                        if ($item_data->startdate != '' || $item_data->enddate != '') {
                                            $description .= ' (';
                                            if ($item_data->startdate != '') {
                                                $description .= '<span class="edu_startdate">' . $item_data->startdate . '</span>';
                                            }
                                            if ($item_data->enddate != '') {
                                                $description .= '<span class="edu_enddate"> - ' . $item_data->enddate . '</span>';
                                            }
                                            $description .= ')';
                                        }
                                        if ($item_data->qualdescription != '') {
                                            $description .= '<span class="edu_qualdescription">' . $item_data->qualdescription . '</span>';
                                        }
                                        $attachments = $item_data->attachments;
                                        $description .= $addAttachementsToBlockView($attachments, $block);
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'employ':
                            $items = [];
                            if ($block->text && $resume && $resume->employments) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $itemid) {
                                    if ($resume->employments[$itemid]) {
                                        $item_data = $resume->employments[$itemid];
                                        $description = '';
                                        $description .= '<span class="employ_jobtitle">' . $item_data->jobtitle . ':</span> ';
                                        $description .= '<span class="employ_employer">' . $item_data->employer . '</span>';
                                        if ($item_data->startdate != '' || $item_data->enddate != '') {
                                            $description .= ' (';
                                            if ($item_data->startdate != '') {
                                                $description .= '<span class="employ_startdate">' . $item_data->startdate . '</span>';
                                            }
                                            if ($item_data->enddate != '') {
                                                $description .= '<span class="employ_enddate"> - ' . $item_data->enddate . '</span>';
                                            }
                                            $description .= ')';
                                        }
                                        if ($item_data->positiondescription != '') {
                                            $description .= '<span class="employ_positiondescription">' . $item_data->positiondescription . '</span>';
                                        }
                                        $attachments = $item_data->attachments;
                                        $description .= $addAttachementsToBlockView($attachments, $block);
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'certif':
                            $items = [];
                            if ($block->text && $resume && $resume->certifications) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $itemid) {
                                    if ($resume->certifications[$itemid]) {
                                        $item_data = $resume->certifications[$itemid];
                                        $attachments = $item_data->attachments;
                                        $description = '';
                                        $description .= '<span class="certif_title">' . $item_data->title . '</span> ';
                                        if ($item_data->date != '') {
                                            $description .= '<span class="certif_date">(' . $item_data->date . ')</span>';
                                        }
                                        if ($item_data->description != '') {
                                            $description .= '<span class="certif_description">' . $item_data->description . '</span>';
                                        }
                                        $attachments = $item_data->attachments;
                                        $description .= $addAttachementsToBlockView($attachments, $block);
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'public':
                            $items = [];
                            if ($block->text && $resume && $resume->publications) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $itemid) {
                                    if ($resume->publications[$itemid]) {
                                        $item_data = $resume->publications[$itemid];
                                        $description = '';
                                        $description .= '<span class="public_title">' . $item_data->title;
                                        if ($item_data->contribution != '') {
                                            $description .= ' (' . $item_data->contribution . ')';
                                        }
                                        $description .= '</span> ';
                                        if ($item_data->date != '') {
                                            $description .= '<span class="public_date">(' . $item_data->date . ')</span>';
                                        }
                                        if ($item_data->contributiondetails != '' || $item_data->url != '') {
                                            $description .= '<span class="public_description">';
                                            if ($item_data->contributiondetails != '') {
                                                $description .= $item_data->contributiondetails;
                                            }
                                            if ($item_data->url != '') {
                                                $description .= '<br /><a href="' . $item_data->url . '" class="public_url" target="_blank">' . $item_data->url . '</a>';
                                            }
                                            $description .= '</span>';
                                        }
                                        $attachments = $item_data->attachments;
                                        $description .= $addAttachementsToBlockView($attachments, $block);
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'mbrship':
                            $items = [];
                            if ($block->text && $resume && $resume->publications) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $itemid) {
                                    if ($resume->profmembershipments[$itemid]) {
                                        $item_data = $resume->profmembershipments[$itemid];
                                        $description = '';
                                        $description .= '<span class="mbrship_title">' . $item_data->title . '</span> ';
                                        if ($item_data->startdate != '' || $item_data->enddate != '') {
                                            $description .= ' (';
                                            if ($item_data->startdate != '') {
                                                $description .= '<span class="mbrship_startdate">' . $item_data->startdate . '</span>';
                                            }
                                            if ($item_data->enddate != '') {
                                                $description .= '<span class="mbrship_enddate"> - ' . $item_data->enddate . '</span>';
                                            }
                                            $description .= ')';
                                        }
                                        if ($item_data->description != '') {
                                            $description .= '<span class="mbrship_description">' . $item_data->description . '</span>';
                                        }
                                        $attachments = $item_data->attachments;
                                        $description .= $addAttachementsToBlockView($attachments, $block);
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'goals':
                        case 'skills':
                            $items = [];
                            if ($block->text && $resume) {
                                $itemIds = explode(',', $block->text);
                                foreach ($itemIds as $goalSkillType) {
                                    $description = '';
                                    if ($tempContent = $resume->{$goalSkillType}) {
                                        $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                                            context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_' . $goalSkillType, $resume->id);
                                        $body_content_forPdf .= format_text($tempContent, FORMAT_HTML); // pdf does not need additional parameter
                                        $tempContent = block_exaport_add_view_access_parameter_to_url($tempContent, $access, ['src']);
                                        $description .= '<span class="' . $goalSkillType . '_text">' . $tempContent . '</span> ';
                                    }
                                    $attachments = @$resume->{$goalSkillType . '_attachments'};
                                    $description .= $addAttachementsToBlockView($attachments, $block);
                                    $description = trim($description);
                                    if ($description) {
                                        $items[] = $description;
                                    }
                                }
                            }
                            $body_content .= implode('<br>', $items);
                            break;
                        case 'interests':
                            $description = '';
                            if ($tempContent = $resume->interests) {
                                $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                                    context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_interests', $resume->id);
                                $body_content_forPdf .= format_text($tempContent, FORMAT_HTML); // pdf does not need additional parameter
                                $tempContent = block_exaport_add_view_access_parameter_to_url($tempContent, $access, ['src']);
                                $description .= '<span class="interests">' . $tempContent . '</span> ';
                            }
                            $body_content = $description;
                            break;
                        default:
                            $general_content .= '!!! ' . $block->resume_itemtype . ' !!!';
                    }

                    // if the resume item is empty - do not show
                    if ($body_content != '') {
                        $general_content .= '<div class="view-cv-information">';
                        $general_content .= $body_content;
                        $general_content .= '</div>';
                        if ($body_content_forPdf) {
                            $blockForPdf .= $body_content_forPdf;
                        } else {
                            $blockForPdf .= $body_content;
                        }
                    }
                    break;
                case 'cv_information':
                    $body_content = '';
                    switch ($block->resume_itemtype) {
                        case 'cover':
                            if ($resume && $resume->cover) {
                                $cover = $resume->cover;
                                $cover = file_rewrite_pluginfile_urls($cover, 'pluginfile.php',
                                    context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_cover', $resume->id);
                                // For shared views we need to have access argument to show attached files fo different cases:
                                $body_content_forPdf .= format_text($cover, FORMAT_HTML); // pdf does not need additional parameter
                                $cover = block_exaport_add_view_access_parameter_to_url($cover, $access, ['src', 'href']);
                                $cover = format_text($cover, FORMAT_HTML);
                                $body_content .= $cover;
                            }
                            break;
                        case 'edu':
                            if ($block->itemid && $resume && $resume->educations[$block->itemid]) {
                                $item_data = $resume->educations[$block->itemid];
                                $attachments = $item_data->attachments;
                                $description = '';
                                $description .= '<span class="edu_institution">' . $item_data->institution . ':</span> ';
                                $description .= '<span class="edu_qualname">' . $item_data->qualname . '</span>';
                                if ($item_data->startdate != '' || $item_data->enddate != '') {
                                    $description .= ' (';
                                    if ($item_data->startdate != '') {
                                        $description .= '<span class="edu_startdate">' . $item_data->startdate . '</span>';
                                    }
                                    if ($item_data->enddate != '') {
                                        $description .= '<span class="edu_enddate"> - ' . $item_data->enddate . '</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($item_data->qualdescription != '') {
                                    $description .= '<span class="edu_qualdescription">' . $item_data->qualdescription . '</span>';
                                }
                                $body_content .= $description;
                            }
                            break;
                        case 'employ':
                            if ($block->itemid && $resume && $resume->employments[$block->itemid]) {
                                $item_data = $resume->employments[$block->itemid];
                                $attachments = $item_data->attachments;
                                $description = '';
                                $description .= '<span class="employ_jobtitle">' . $item_data->jobtitle . ':</span> ';
                                $description .= '<span class="employ_employer">' . $item_data->employer . '</span>';
                                if ($item_data->startdate != '' || $item_data->enddate != '') {
                                    $description .= ' (';
                                    if ($item_data->startdate != '') {
                                        $description .= '<span class="employ_startdate">' . $item_data->startdate . '</span>';
                                    }
                                    if ($item_data->enddate != '') {
                                        $description .= '<span class="employ_enddate"> - ' . $item_data->enddate . '</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($item_data->positiondescription != '') {
                                    $description .= '<span class="employ_positiondescription">' . $item_data->positiondescription . '</span>';
                                }
                                $body_content .= $description;
                            }
                            break;
                        case 'certif':
                            if ($block->itemid && $resume && $resume->certifications[$block->itemid]) {
                                $item_data = $resume->certifications[$block->itemid];
                                $attachments = $item_data->attachments;
                                $description = '';
                                $description .= '<span class="certif_title">' . $item_data->title . '</span> ';
                                if ($item_data->date != '') {
                                    $description .= '<span class="certif_date">(' . $item_data->date . ')</span>';
                                }
                                if ($item_data->description != '') {
                                    $description .= '<span class="certif_description">' . $item_data->description . '</span>';
                                }
                                $body_content = $description;
                            }
                            break;
                        case 'public':
                            if ($block->itemid && $resume && $resume->publications[$block->itemid]) {
                                $item_data = $resume->publications[$block->itemid];
                                $attachments = $item_data->attachments;
                                $description = '';
                                $description .= '<span class="public_title">' . $item_data->title;
                                if ($item_data->contribution != '') {
                                    $description .= ' (' . $item_data->contribution . ')';
                                }
                                $description .= '</span> ';
                                if ($item_data->date != '') {
                                    $description .= '<span class="public_date">(' . $item_data->date . ')</span>';
                                }
                                if ($item_data->contributiondetails != '' || $item_data->url != '') {
                                    $description .= '<span class="public_description">';
                                    if ($item_data->contributiondetails != '') {
                                        $description .= $item_data->contributiondetails;
                                    }
                                    if ($item_data->url != '') {
                                        $description .= '<br /><a href="' . $item_data->url . '" class="public_url" target="_blank">' . $item_data->url . '</a>';
                                    }
                                    $description .= '</span>';
                                }
                                $body_content = $description;
                            }
                            break;
                        case 'mbrship':
                            if ($block->itemid && $resume && $resume->profmembershipments[$block->itemid]) {
                                $item_data = $resume->profmembershipments[$block->itemid];
                                $attachments = $item_data->attachments;
                                $description = '';
                                $description .= '<span class="mbrship_title">' . $item_data->title . '</span> ';
                                if ($item_data->startdate != '' || $item_data->enddate != '') {
                                    $description .= ' (';
                                    if ($item_data->startdate != '') {
                                        $description .= '<span class="mbrship_startdate">' . $item_data->startdate . '</span>';
                                    }
                                    if ($item_data->enddate != '') {
                                        $description .= '<span class="mbrship_enddate"> - ' . $item_data->enddate . '</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($item_data->description != '') {
                                    $description .= '<span class="mbrship_description">' . $item_data->description . '</span>';
                                }
                                $body_content = $description;
                            }
                            break;
                        case 'goalspersonal':
                        case 'goalsacademic':
                        case 'goalscareers':
                        case 'skillspersonal':
                        case 'skillsacademic':
                        case 'skillscareers':
                            $attachments = @$resume->{$block->resume_itemtype . '_attachments'};
                            $description = '';
                            if ($resume && $tempContent = $resume->{$block->resume_itemtype}) {
                                $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                                    context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_' . $block->resume_itemtype, $resume->id);
                                $body_content_forPdf .= format_text($tempContent, FORMAT_HTML); // pdf does not need additional parameter
                                $tempContent = block_exaport_add_view_access_parameter_to_url($tempContent, $access, ['src']);
                                $description .= '<span class="' . $block->resume_itemtype . '_text">' . $tempContent . '</span> ';
                            }
                            $body_content = $description;
                            break;
                        case 'interests':
                            $description = '';
                            if ($tempContent = $resume->interests) {
                                $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                                    context_user::instance($resume->user_id)->id, 'block_exaport', 'resume_editor_interests', $resume->id);
                                $body_content_forPdf .= format_text($tempContent, FORMAT_HTML); // pdf does not need additional parameter
                                $tempContent = block_exaport_add_view_access_parameter_to_url($tempContent, $access, ['src']);
                                $description .= '<span class="interests">' . $tempContent . '</span> ';
                            }
                            $body_content = $description;
                            break;
                        default:
                            $general_content .= '!!! ' . $block->resume_itemtype . ' !!!';
                    }

                    if ($attachments && is_array($attachments) && count($attachments) > 0 && $block->resume_withfiles) {
                        $body_content .= '<ul class="resume_attachments ' . $block->resume_itemtype . '_attachments">';
                        foreach ($attachments as $attachm) {
                            $body_content .= '<li><a href="' . $attachm['fileurl'] . '" target="_blank">' . $attachm['filename'] . '</a></li>';
                        }
                        $body_content .= '</ul>';
                    }

                    // if the resume item is empty - do not show
                    if ($body_content != '') {
                        $general_content .= '<div class="view-cv-information">';
                        /*if (isset($block->picture)) {
                            echo '<div class="picture" style="float:right; position: relative;"><img src="'.$block->picture.
                                    '" alt=""/></div>';
                        }*/
                        $general_content .= $body_content;
                        $general_content .= '</div>';
                        if ($body_content_forPdf) {
                            $blockForPdf .= $body_content_forPdf;
                        } else {
                            $blockForPdf .= $body_content;
                        }
                    }
                    break;
                default:
                    // Text.
                    $general_content .= '<div class="header">' . $block->block_title . '</div>';
                    $general_content .= '<div class="view-text">';
                    $general_content .= format_text($block->text, FORMAT_HTML);
                    $general_content .= '</div>';
                    if ($block->block_title) {
                        $blockForPdf .= "\r\n" . '<h4>' . $block->block_title . '</h4>';
                    }
                    $pdf_text = format_text($block->text, FORMAT_HTML);
                    // If the text has HTML <img> - it can broke view template. Try to clean it
                    try {
                        $pdf_text = trim(mb_convert_encoding($pdf_text, 'HTML-ENTITIES', 'UTF-8'));
                        if ($pdf_text) {
                            $dom = new DOMDocument;
                            $dom->loadHTML($pdf_text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                            $xpath = new DOMXPath($dom);
                            $nodes = $xpath->query('//img');
                            /** @var DOMElement $node */
                            foreach ($nodes as $node) {
                                $node->removeAttribute('width');
                                $node->removeAttribute('height');
                                // $node->setAttribute('width', '200');
                                $style = $node->getAttribute('style');
                                $style .= ';width: 100%;';
                                $style = $node->setAttribute('style', $style);
                            }
                            $pdf_text = $dom->saveHTML();
                        }
                    } catch (\Exception $e) {
                        // just continue?
                    }
                    /* try {*/
                    $pdf_text = mb_convert_encoding($pdf_text, 'HTML-ENTITIES', 'UTF-8');
                    if ($pdf_text) {
                        $dom = new DOMDocument;
                        $dom->loadHTML($pdf_text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $xpath = new DOMXPath($dom);
                        $nodes = $xpath->query('//img');
                        /** @var DOMElement $node */
                        foreach ($nodes as $node) {
                            $node->removeAttribute('width');
                            $node->removeAttribute('height');
                            // $node->setAttribute('width', '200');
                            $style = $node->getAttribute('style');
                            $style .= ';width: 100%;';
                            $style = $node->setAttribute('style', $style);
                        }
                        $pdf_text = $dom->saveHTML();
                    }
                    /*}  finally {
                        // just wrapper
                    }*/
                    $blockForPdf .= "\r\n" . '<div>' . $pdf_text . '</div>';
            }
            $blockForPdf .= '</div>';
            $data_for_pdf[$i][] = $blockForPdf;
            $data_for_pdf_blocks[$i][] = $block;
        }
    }
    $general_content .= '</td>';
}

$general_content .= '</tr></table>';
$general_content .= '</div>';

$general_content .= "<br />";

// PDF form
// Get font families
function block_export_getpdffontfamilies($grouped = false) {
    // static fonts
    $defaultfamilies = [
        // css name => Name
        // must support dompdf by defualt
        'Dejavu' => 'DejaVu sans',
        'Arial' => 'Arial',
        'Times New Roman' => 'Times New Roman',
        'Helvetica' => 'Helvetica',
        'Symbol' => 'Symbol',
        'ZapfDingbats' => 'ZapfDingbats',
        // not sure that it is supported PDF
        //	    'Verdana, sans-serif' => 'Verdana (sans-serif)',
        //	    'Tahoma, sans-serif' => 'Tahoma (sans-serif)',
        //	    '"Trebuchet MS", sans-serif' => 'Trebuchet MS (sans-serif)',
        //	    '"Times New Roman", serif' => 'Times New Roman (serif)',
        //	    'Georgia, serif' => 'Georgia (serif)',
        //	    'Garamond, serif' => 'Garamond (serif)',
        //	    '"Courier New", monospace' => 'Courier New (monospace)',
        //	    '"Brush Script MT", cursive' => 'Brush Script MT (cursive)',
    ];
    // uploaded fonts
    $customfonts = [];
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_system::instance()->id, 'block_exaport', 'pdf_fontfamily', 0);
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $customfonts[$file->get_id()] = $file->get_filename();
        }
    }
    if ($grouped) {
        if ($customfonts) {
            // Add groups only if at least single custom font uploaded
            $all = [
                block_exaport_get_string('pdf_settings_fontfamily_fixedgroup') => $defaultfamilies,
                block_exaport_get_string('pdf_settings_fontfamily_customgroup') => $customfonts,
            ];
        } else {
            $all = [
                0 => $defaultfamilies, // No groups!
            ];
        }
    } else {
        $all = $defaultfamilies + $customfonts;
    }

    return $all;
}


$general_content .= "<div class='' id='pdfDownloader'>\n";

$pdfsettingslink = $PAGE->url;
$pdfsettingslink->params(array(
    'courseid' => optional_param('courseid', 1, PARAM_TEXT),
    'access' => optional_param('access', 0, PARAM_TEXT),
));

// Insert PDF form
$pdfFormData = [
    'fontfamily' => $pdf_settings['fontfamily'],
    'fontsize' => $pdf_settings['fontsize'],
    'pagesize' => $pdf_settings['pagesize'],
    'pageorient' => $pdf_settings['pageorient'],
    'showmetadata' => $pdf_settings['showmetadata'] ? 1 : 0,
    'showusername' => $pdf_settings['showusername'] ? 1 : 0,
    'showuserpicture' => $pdf_settings['showuserpicture'] ? 1 : 0,
    'showuseremail' => $pdf_settings['showuseremail'] ? 1 : 0,
    'showuserphone' => $pdf_settings['showuserphone'] ? 1 : 0,
];
$pdfForm->set_data($pdfFormData);
//displays the form
$general_content .= $pdfForm->render();


if (!$is_pdf) {
    $general_content .= block_exaport_wrapperdivend();
    $general_content .= $OUTPUT->footer();
}

if ($is_pdf) {
    // old pdf view
    require_once(__DIR__ . '/lib/classes/dompdf/autoload.inc.php');
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    //    $options->set('defaultFont', 'dejavu sans');
    //	$options->set('debugLayout', true);
    $dompdf = new Dompdf($options);
    $dompdf->setPaper($pdf_settings['pagesize'], $pdf_settings['pageorient']);
    $general_content = pdf_view($view, $colslayout, $data_for_pdf, $pdf_settings);
    $dompdf->loadHtml($general_content);
    try {
        error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
        $dompdf->render();
    } catch (\Dompdf\Exception $e) {
        throw new moodle_exception('Something wrong with PDF generator ');
        exit;
    }
    require_once($CFG->dirroot . '/user/lib.php');
    $user = \core_user::get_user($view->userid);
    $userData = user_get_user_details($user, null, array('fullname'));
    $pdfFileName = 'Portfolio-'.$userData['fullname'].'-'.$view->name.'.pdf';
    $pdfFileName = clean_filename($pdfFileName);
    $dompdf->stream($pdfFileName); //To popup pdf as download
    exit;
    /**/

    // new pdf view. not implemented yet

    // generate PDF directly. not as HTML. not done fully yet
    /* require_once __DIR__.'/lib/reportlib.php';
    // $pdf = new ExaportViewPdf();
    // $pdf->generatePDFview($view->layout, $data_for_pdf);
    $pdf = new ExaportVievPdf($view);
    $pdf->genarateView($view->layout, $data_for_pdf_blocks, $access);*/
    /**/
}

echo $general_content;

/**
 * Some servers app combinations PHP, OS e.t.c. can have different issues with pdf generation. Use this HTML cleaning
 * @param $content
 * @return mixed
 */
function prependHtmlContentToPdf($content, $view) {
    global $USER;

    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

    $doc = new DOMDocument();
    $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Replace all <p> to span. Sometimes <p> has wrong overlays in result PDF.
    // deprecated for new dompdf version?
    /*$xpath = new DOMXPath($doc);
    $pTags = $xpath->query('//p');
    foreach ($pTags as $p) {
        $span = $doc->createElement('p');
	    // keep attributes
//        foreach ($p->attributes as $attr) {
//            $span->setAttribute($attr->name, $attr->value);
//        }
//      Add class to see span like p.
        $span->setAttribute('class', $span->getAttribute('class').' pdf_paragraph');
        $children = $p->childNodes;
        foreach($children as $child) {
            $newDomElementChild = $doc->importNode($child, true);
            $span->appendChild($newDomElementChild);
        }

        $p->parentNode->replaceChild($span, $p);
    }*/

    // To see moodle images/files we need to have additional parameters in src url
    // without this parameters Moodle asks to be logged in, but PDF generator is not
    $xpath = new DOMXPath($doc);
    // Find all <img> tags with 'src' containing 'pluginfile.php' (only our Moodle urls)
    $imgTags = $xpath->query('//img[contains(@src, "pluginfile.php")][contains(@src, "block_exaport")]'); // check these conditions
    foreach ($imgTags as $img) {
        $src = $img->getAttribute('src');
        // Additional check if 'src' contains 'pluginfile.php'
        if (strpos($src, 'pluginfile.php') !== false) {
            $newSrc = $src . '/forPdf/' . $view->hash . '/' . $view->id . '/' . $USER->id;
            $img->setAttribute('src', $newSrc);
        }
    }

    // Save the modified HTML content
    $content = $doc->saveHTML();

    // Clean empty spaces between tags.
    //    $content = trim(preg_replace('/>\s+</', '><', $content));

    return $content;
}


function pdf_view($view, $colslayout, $data_for_pdf, $pdf_settings) {
    global $USER, $CFG;

    $fontfamily = $pdf_settings['fontfamily'];
    $fontfamilyUrl = '';
    if (is_numeric($fontfamily)) {
        // It is a custom uploaded file, so we need to get an url to this font.
        $fs = get_file_storage();
        $fontFile = $fs->get_file_by_id(intval($fontfamily));
        $fontfamilyUrl = moodle_url::make_pluginfile_url(
            $fontFile->get_contextid(),
            $fontFile->get_component(),
            $fontFile->get_filearea(),
            $fontFile->get_itemid(),
            $fontFile->get_filepath(),
            $fontFile->get_filename(),
            false                     // Do not force download of the file.
        );
        $fontfamilyUrl = $fontfamilyUrl->raw_out();
        // font family must be unique for the font. Because dompdf creates cached fonts for every family
        $fontfamily = 'customUploaded_' . $fontFile->get_filename();
    }

    $pdf_content = '';
    $pdf_content .= '<!DOCTYPE html>' . "\r\n";
    $pdf_content .= '<html>' . "\r\n";
    $pdf_content .= '<head>' . "\r\n";
    $pdf_content .= '<style>' . "\r\n";
    if ($fontfamilyUrl) {
        // Use the same font for different styles|weights (TODO: do we need to have a possibilitu to upload fonts for bold/italic?).
        $pdf_content .= '
			@font-face {
			  font-family: "' . $fontfamily . '";
			  font-weight: normal;
		      font-style: normal;
			  src:  url("' . $fontfamilyUrl . '") format("truetype");
			}
			@font-face {
			  font-family: "' . $fontfamily . '";
			  font-weight: normal;
		      font-style: italic;
			  src:  url("' . $fontfamilyUrl . '") format("truetype");
			}
			@font-face {
			  font-family: "' . $fontfamily . '";
			  font-weight: bold;
		      font-style: normal;
			  src:  url("' . $fontfamilyUrl . '") format("truetype");
			}
			@font-face {
			  font-family: "' . $fontfamily . '";
			  font-weight: bold;
		      font-style: italic;
			  src:  url("' . $fontfamilyUrl . '") format("truetype");
			}
			';
    }
    // Add CSS rules for metadata (header and footer)
    if (@$pdf_settings['showmetadata']) {
        $pdf_content .= '
            body {
                margin-top: 50px;
                margin-bottom: 50px;
            }
		    #header {
			    position: fixed;
			    top: 0px;
			    height: 50px;
		        margin: -25px -5px;
                padding: 5px 15px;
                width: 100%;
	        }
	        #header table {
	            width: 100%;
	        }
	        #header table td.viewtitle,
	        #header table td.userinfo {
	            width: 50%;
	        }
	        #header table td.viewtitle {
	            font-size: 1.25em;
	            line-height: 1em;
	        }
	        #header table td.userinfo {
	            text-align: right;
	            padding-right: 15px;
	            font-size:0.75em;
	            line-height: 1.1em;
	        }
	        #header table td.userpicture {
	            width: 50px;
	            text-align: right;
	        }
	        #header table td.userpicture img {
	            max-height: 40px;
	            height: 40px;
	        }
	        #footer {
	            position: fixed;
	            bottom: 0px;
			    height: 30px;
		        margin: -15px -5px;
		        margin-bottom: -15px;
                padding: 0px 15px;
                width: 100%;
                color: #cccccc;
                font-size: 0.75em;
                border-top: 1px solid #cccccc;
            }
            #footer table {
                width: 100%;
            }
            #footer table td {
                width: 33%;
                vertical-align: bottom;
            }
            #footer table td {
                text-align: center;
            }
            #footer table td:first-child {
				text-align: left
            }
            #footer table td:last-child  {
                text-align: right;
                padding-right: 15px;
            }
		';
    }

    $pdf_content .= '
	        body {
	            font-family: "' . $fontfamily . '";
	            font-size: ' . $pdf_settings['fontsize'] . 'px;
	            font-weight: normal !important;
	        }
	        .view-table td {
	            padding: 5px;
	        }
	        h4 {
	            margin: 15px 0 0;
	        }
	        div.view-block {
	            position: relative;
	            height: auto;
	            clear: both;
	            border-top: 1px solid #eeeeee;
	        }
	        /*span.pdf_paragraph {
	            clear: both;
	            color: red;
	            display: block;
	        }*/
        ';
    $pdf_content .= "\r\n";
    $pdf_content .= '</style>' . "\r\n";

    // add custom styles from view layouts
    // TODO: dow we need this?
    //    $pdf_settings .= block_exaport_get_view_layout_style_from_settings($view);

    $pdf_content .= '<title>' . $view->name . '</title>' . "\r\n";
    $pdf_content .= '</head>' . "\r\n";
    $pdf_content .= '<body>' . "\r\n";

    // Add header metadata
    if (@$pdf_settings['showmetadata']) {
        $pdf_content .= '<div id="header">';
        $pdf_content .= '<table><tr>';
        $pdf_content .= '<td class="viewtitle">' . $view->name . '</td>';
        $userlines = [];
        require_once($CFG->dirroot . '/user/lib.php');
        $user = \core_user::get_user($view->userid);
        $userData = user_get_user_details($user, null, array('email', 'fullname', 'profileimageurl', 'email', 'phone1', 'phone2'));
        if ($pdf_settings['showusername']) {
            $userlines[] = $userData['fullname'];
        }
        if ($pdf_settings['showuseremail']) {
            $userlines[] = $userData['email'];
        }
        if ($pdf_settings['showuserphone']) {
            $tel = trim($userData['phone1'] . ($userData['phone1'] ? ', ' . $userData['phone1'] : ''));
            $userlines[] = $tel ? 'tel: ' . $tel : '';
        }
        $userlines = array_filter($userlines);
        $userdatacontent = implode('<br>', $userlines);
        $pdf_content .= '<td class="userinfo">' . $userdatacontent . '</td>';
        if ($pdf_settings['showuserpicture']) {
            $pdf_content .= '<td class="userpicture">';
            $pdf_content .= '<img src="' . $userData['profileimageurl'] . '" />';
            $pdf_content .= '</td>';
        }
        $pdf_content .= '</tr></table>';
        $pdf_content .= '</div>';
    }

    $pdf_content .= '<table class="view-table" style="width: 100%; border: none; table-layout:fixed;">' . "\r\n";
    $pdf_content .= '<tr>';
    $max_rows = 0;
    foreach ($data_for_pdf as $col => $blocks) {
        $max_rows = max(count($blocks), $max_rows);
    }
    for ($coli = 1; $coli <= $colslayout[$view->layout]; $coli++) {
        $pdf_content .= '<td width="' . (round(100 / $colslayout[$view->layout]) - 1) . '%" valign="top">';
        if (array_key_exists($coli, $data_for_pdf)) {
            foreach ($data_for_pdf[$coli] as $block) {
                // Every block in own table - to keep width if some block has very big width.
                $pdf_content .= '<table width="100%" style="word-break:break-all !important; word-wrap: break-word !important; overflow-wrap: break-word !important;">';
                $pdf_content .= '<tr><td>';
                $pdf_content .= $block;
                $pdf_content .= '</td></tr>';
                $pdf_content .= '</table>';
            }
        } else {
            $pdf_content .= '&nbsp;';
        }
        $pdf_content .= '</td>';
    }
    $pdf_content .= '</tr>';
    $pdf_content .= '</table>' . "\r\n";

    // footer
    if (@$pdf_settings['showmetadata']) {
        $pdf_content .= '<div id="footer">';
        $pdf_content .= '<table><tr>';
        $pdf_content .= '<td class="">' . $view->name . '</td>';
        $pdf_content .= '<td class="">&copy; Exabis ePortfolio</td>';
        $pdf_content .= '<td class="">' . date('d.m.Y') . '</td>';
        $pdf_content .= '</body>' . "\r\n";
        $pdf_content .= '</html>';

    }
    $pdf_content = prependHtmlContentToPdf($pdf_content, $view);
    // Output for debugging.
    //    echo '<textarea>';
    //    print_r($pdf_content);
    //    echo '</textarea>';
    //    exit;
    //     echo $pdf_content; exit;
    return $pdf_content;
}

?>
