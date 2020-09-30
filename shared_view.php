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
use Dompdf\Exception;

require_once(__DIR__.'/inc.php');
require_once(__DIR__.'/blockmediafunc.php');

$access = optional_param('access', 0, PARAM_TEXT);

require_login(0, true);

// main content:
$generalContent = '';

$url = '/blocks/exaport/shared_view.php';
$PAGE->set_url($url);
$context = context_system::instance();
$PAGE->set_context($context);

if (!$view = block_exaport_get_view_from_access($access)) {
    print_error("viewnotfound", "block_exaport");
}

$isPdf = optional_param('ispdf', 0, PARAM_INT);

$conditions = array("id" => $view->userid);
if (!$user = $DB->get_record("user", $conditions)) {
    print_error("nouserforid", "block_exaport");
}

$portfoliouser = block_exaport_get_user_preferences($user->id);

// Read blocks.
$query = "select b.*". // , i.*, i.id as itemid".
        " FROM {block_exaportviewblock} b".
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

if (!$isPdf) {
    if ($view->access->request == 'intern') {
        block_exaport_print_header("shared_views");
    } else {
        $PAGE->requires->css('/blocks/exaport/css/shared_view.css');
        $PAGE->set_title(get_string("externaccess", "block_exaport"));
        $PAGE->set_heading(get_string("externaccess", "block_exaport")." ".fullname($user, $user->id));

        $generalContent .= $OUTPUT->header();
        $generalContent .= block_exaport_wrapperdivstart();
    }
}

if (!$isPdf) {
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

require_once(__DIR__.'/lib/resumelib.php');
$resume = block_exaport_get_resume_params($view->userid, true);

$colslayout = array(
        "1" => 1, "2" => 2, "3" => 2, "4" => 2, "5" => 3, "6" => 3, "7" => 3, "8" => 4, "9" => 4, "10" => 5,
);
if (!isset($view->layout) || $view->layout == 0) {
    $view->layout = 2;
}
$generalContent .= '<div id="view">';
$generalContent .= '<table class="table_layout layout'.$view->layout.'""><tr>';
$dataForPdf = array();
for ($i = 1; $i <= $colslayout[$view->layout]; $i++) {
    $dataForPdf[$i] = array();
    $generalContent .= '<td class="view-column td'.$i.'">';
    if (isset($columns[$i])) {
        foreach ($columns[$i] as $block) {
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
                        $competencies = block_exaport_get_active_comps_for_item($item);

                        if ($competencies) {
                            $competenciesoutput = "";
                            foreach ($competencies as $competence) {
                                $competenciesoutput .= $competence->title.'<br>';
                            }

                            // TODO: still needed?
                            $competenciesoutput = str_replace("\r", "", $competenciesoutput);
                            $competenciesoutput = str_replace("\n", "", $competenciesoutput);
                            $competenciesoutput = str_replace("\"", "&quot;", $competenciesoutput);
                            $competenciesoutput = str_replace("'", "&prime;", $competenciesoutput);

                            $item->competences = $competenciesoutput;
                        }

                    }

                    $href = 'shared_item.php?access=view/'.$access.'&itemid='.$item->id.'&att='.$item->attachment;

                    $generalContent .= '<div class="view-item view-item-type-'.$item->type.'">';
                    // Thumbnail of item.
                    $fileparams = '';
                    if ($item->type == "file") {
                        $select = "contextid='".context_user::instance($item->userid)->id."' ".
                                " AND component='block_exaport' AND filearea='item_file' AND itemid='".$item->id."' AND filesize>0 ";
                        if ($files = $DB->get_records_select('files', $select, null, 'id, filename, mimetype, filesize')) {
                            if (is_array($files)) {
                                $width = '';
                                if (count($files) > 5) {
                                    $width = 's35';
                                } elseif (count($files) > 3) {
                                    $width = 's40';
                                } elseif (count($files) > 2) {
                                    $width = 's50';
                                } elseif (count($files) > 1) {
                                    $width = 's75';
                                }

                                foreach ($files as $file) {
                                    if (strpos($file->mimetype, "image") !== false) {
                                        $imgsrc = $CFG->wwwroot."/pluginfile.php/".context_user::instance($item->userid)->id.
                                                "/".'block_exaport'."/".'item_file'."/view/".$access."/itemid/".$item->id."/".
                                                $file->filename;
                                        $generalContent .= '<div class="view-item-image"><img src="'.$imgsrc.'" class="'.$width.'" alt=""/></div>';
                                        if ($isPdf) {
                                            $imgsrc .= '/forPdf/'.$view->hash.'/'.$view->id.'/'.$USER->id;
                                        }
                                        $blockForPdf .= '<div class="view-item-image">
                                                            <img align = "right"
                                                                border = "0"
                                                                src = "'.$imgsrc.'" 
                                                                width = "'.((int)filter_var($width, FILTER_SANITIZE_NUMBER_INT) ?: '100').'" 
                                                                alt = ""/>
                                                         </div>';
                                    } else {
                                        // Link to file.
                                        $ffurl = s("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=view/".$access.
                                                "&itemid=".$item->id."&inst=".$file->pathnamehash);
                                        // Human filesize.
                                        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
                                        $power = $file->filesize > 0 ? floor(log($file->filesize, 1024)) : 0;
                                        $filesize = number_format($file->filesize / pow(1024, $power), 2, '.', ',').' '.$units[$power];
                                        // Fileinfo block.
                                        $fileparams = '<div class="view-item-file"><a href="'.$ffurl.'" >'.$file->filename.'</a> '.
                                                '<span class="filedescription">('.$filesize.')</span></div>';
                                        if (block_exaport_is_valid_media_by_filename($file->filename)) {
                                            $generalContent .= '<div class="view-item-image"><img height="60" src="'.$CFG->wwwroot.
                                                    '/blocks/exaport/pix/media.png" alt=""/></div>';
                                            $blockForPdf .= '<img height="60" src="'.$CFG->wwwroot. '/blocks/exaport/pix/media.png" align="right"/>';
                                        }
                                    };
                                }
                            }
                        };
                    } else if ($item->type == "link") {
                        $generalContent .= '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;"><a href="'.
                                $href.'"><img style="max-width: 100%; max-height: 100%;" src="'.$CFG->wwwroot.
                                '/blocks/exaport/item_thumb.php?item_id='.$item->id.'&access='.$access.'" alt=""/></a></div>';
                        $blockForPdf .= '<img align="right" 
                                                style="" height="100"
                                                src="'.$CFG->wwwroot.'/blocks/exaport/item_thumb.php?item_id='.$item->id.'&access='.$access.'&ispdf=1&vhash='.$view->hash.'&vid='.$view->id.'&uid='.$USER->id.'" 
                                                alt=""/>';
                    };
                    $generalContent .= '<div class="view-item-header" title="'.$item->type.'">'.$item->name;
                    // Falls Interaktion ePortfolio - competences aktiv und User ist Lehrer.
                    if ($comp && has_capability('block/exaport:competences', $context)) {
                        if ($competencies) {
                            $generalContent .= '<img align="right" src="'.$CFG->wwwroot.
                                    '/blocks/exaport/pix/application_view_tile.png" alt="competences"/>';
                        }
                    }
                    $generalContent .= '</div>';
                    $blockForPdf .= '<h4>'.$item->name.'</h4>';
                    $generalContent .= $fileparams;
                    $blockForPdf .= $fileparams;
                    $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
                            'block_exaport', 'item_content', 'view/'.$access.'/itemid/'.$item->id);
                    $intro = format_text($intro, FORMAT_HTML, ['noclean' => true]);
                    $generalContent .= '<div class="view-item-text">';
                    $blockForPdf .= '<div class="view-item-text">';
                    if ($item->url && $item->url != "false") {
                        // Link.
                        $generalContent .= '<a href="'.s($item->url).'" target="_blank">'.str_replace('http://', '', $item->url).'</a><br />';
                        $blockForPdf .= '<a href="'.s($item->url).'" target="_blank">'.str_replace('http://', '', $item->url).'</a><br />';
                    }
                    $generalContent .= $intro.'</div>';
                    $blockForPdf .= $intro.'</div>';
                    if ($competencies) {
                        $generalContent .= '<div class="view-item-competences">'.
                                '<script type="text/javascript" src="javascript/wz_tooltip.js"></script>'.
                                '<a onmouseover="Tip(\''.$item->competences.'\')" onmouseout="UnTip()">'.
                                '<img src="'.$CFG->wwwroot.'/blocks/exaport/pix/comp.png" class="iconsmall" alt="'.'competences'.'" />'.
                                '</a></div>';
                    }
                    $generalContent .= '<div class="view-item-link"><a href="'.s($href).'">'.block_exaport_get_string('show').'</a></div>';
                    $generalContent .= '</div>';
                    break;
                case 'personal_information':
                    $generalContent .= '<div class="header">'.$block->block_title.'</div>';
                    if ($block->block_title) {
                        $blockForPdf .= '<h4>'.$block->block_title.'</h4>';
                    }
                    $generalContent .= '<div class="view-personal-information">';
                    $blockForPdf .= '<div class="view-personal-information">';
                    if (isset($block->picture)) {
                        $generalContent .= '<div class="picture" style="float:right; position: relative;"><img src="'.$block->picture.
                                '" alt=""/></div>';
                        $blockForPdf .= '<img src="'.$block->picture.'" align="right" />';
                    }
                    $personInfo = '';
                    if (isset($block->firstname) or isset($block->lastname)) {
                        $personInfo .= '<div class="name">';
                        if (isset($block->firstname)) {
                            $personInfo .= $block->firstname;
                        }
                        if (isset($block->lastname)) {
                            $personInfo .= ' '.$block->lastname;
                        }
                        $personInfo .= '</div>';
                    };
                    if (isset($block->email)) {
                        $personInfo .= '<div class="email">'.$block->email.'</div>';
                    }
                    if (isset($block->text)) {
                        $personInfo .= '<div class="body">'.$block->text.'</div>';
                    }
                    $generalContent .= $personInfo;
                    $generalContent .= '</div>';
                    $blockForPdf .= $personInfo;
                    $blockForPdf .= '</div>';
                    break;
                case 'headline':
                    $generalContent .= '<div class="header view-header">'.nl2br($block->text).'</div>';
                    $blockForPdf .= '<h4>'.nl2br($block->text).'</h4>';
                    break;
                case 'media':
                    $generalContent .= '<div class="header view-header">'.nl2br($block->block_title).'</div>';
                    if ($block->block_title) {
                        $blockForPdf .= '<h4>'.nl2br($block->block_title).'</h4>';
                    }
                    $generalContent .= '<div class="view-media">';
                    if (!empty($block->contentmedia)) {
                        $generalContent .= $block->contentmedia;
                    }
                    $generalContent .= '</div>';
                    $blockForPdf .= '----media----';
                    $blockForPdf .= '</div>';
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
                    $generalContent .= '<div class="header">'.nl2br($badge->name).'</div>';
                    $blockForPdf .= '<h4>'.nl2br($badge->name).'</h4>';
                    $generalContent .= '<div class="view-text">';
                    $generalContent .= '<div style="float:right; position: relative; height: 100px; width: 100px;" class="picture">';
                    if (!$badge->courseid) { // For badges with courseid = NULL.
                        $badge->imageUrl = (string) moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage',
                                                                                    $badge->id, '/', 'f1', false);
                    } else {
                        $context = context_course::instance($badge->courseid);
                        $badge->imageUrl = (string) moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage',
                                                                                    $badge->id, '/', 'f1', false);
                    }
                    $generalContent .= '<img src="'.$badge->imageUrl.'">';
                    $generalContent .= '</div>';
                    $generalContent .= '<div class="badge-description">';
                    $generalContent .= format_text($badge->description, FORMAT_HTML);
                    $generalContent .= '</div>';
                    $generalContent .= '</div>';
                    $blockForPdf .= '<p>'.format_text($badge->description, FORMAT_HTML).'</p>';
                    $blockForPdf .= '<img align="right" src="'.$badge->imageUrl.'">';
                    $blockForPdf .= '</div>';
                    break;
                case 'cv_information':
                    $bodyContent = '';
                    switch ($block->resume_itemtype) {
                        case 'edu':
                            if ($block->itemid && $resume && $resume->educations[$block->itemid]) {
                                $itemData = $resume->educations[$block->itemid];
                                $attachments = $itemData->attachments;
                                $description = '';
                                $description .= '<span class="edu_institution">'.$itemData->institution.':</span> ';
                                $description .= '<span class="edu_qualname">'.$itemData->qualname.'</span>';
                                if ($itemData->startdate != '' || $itemData->enddate != '') {
                                    $description .= ' (';
                                    if ($itemData->startdate != '') {
                                        $description .= '<span class="edu_startdate">'.$itemData->startdate.'</span>';
                                    }
                                    if ($itemData->enddate != '') {
                                        $description .= '<span class="edu_enddate"> - '.$itemData->enddate.'</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($itemData->qualdescription != '') {
                                    $description .= '<span class="edu_qualdescription">'.$itemData->qualdescription.'</span>';
                                }
                                $bodyContent .= $description;
                            }
                            break;
                        case 'employ':
                            if ($block->itemid && $resume && $resume->employments[$block->itemid]) {
                                $itemData = $resume->employments[$block->itemid];
                                $attachments = $itemData->attachments;
                                $description = '';
                                $description .= '<span class="employ_jobtitle">'.$itemData->jobtitle.':</span> ';
                                $description .= '<span class="employ_employer">'.$itemData->employer.'</span>';
                                if ($itemData->startdate != '' || $itemData->enddate != '') {
                                    $description .= ' (';
                                    if ($itemData->startdate != '') {
                                        $description .= '<span class="employ_startdate">'.$itemData->startdate.'</span>';
                                    }
                                    if ($itemData->enddate != '') {
                                        $description .= '<span class="employ_enddate"> - '.$itemData->enddate.'</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($itemData->positiondescription != '') {
                                    $description .= '<span class="employ_positiondescription">'.$itemData->positiondescription.'</span>';
                                }
                                $bodyContent .= $description;
                            }
                            break;
                        case 'certif':
                            if ($block->itemid && $resume && $resume->certifications[$block->itemid]) {
                                $itemData = $resume->certifications[$block->itemid];
                                $attachments = $itemData->attachments;
                                $description = '';
                                $description .= '<span class="certif_title">'.$itemData->title.'</span> ';
                                if ($itemData->date != '') {
                                    $description .= '<span class="certif_date">('.$itemData->date.')</span>';
                                }
                                if ($itemData->description != '') {
                                    $description .= '<span class="certif_description">'.$itemData->description.'</span>';
                                }
                                $bodyContent = $description;
                            }
                            break;
                        case 'public':
                            if ($block->itemid && $resume && $resume->publications[$block->itemid]) {
                                $itemData = $resume->publications[$block->itemid];
                                $attachments = $itemData->attachments;
                                $description = '';
                                $description .= '<span class="public_title">'.$itemData->title;
                                if ($itemData->contribution != '') {
                                    $description .= ' ('.$itemData->contribution.')';
                                }
                                $description .= '</span> ';
                                if ($itemData->date != '') {
                                    $description .= '<span class="public_date">('.$itemData->date.')</span>';
                                }
                                if ($itemData->contributiondetails != '' || $itemData->url != '') {
                                    $description .= '<span class="public_description">';
                                    if ($itemData->contributiondetails != '') {
                                        $description .= $itemData->contributiondetails;
                                    }
                                    if ($itemData->url != '') {
                                        $description .= '<br /><a href="'.$itemData->url.'" class="public_url" target="_blank">'.$itemData->url.'</a>';
                                    }
                                    $description .= '</span>';
                                }
                                $bodyContent = $description;
                            }
                            break;
                        case 'mbrship':
                            if ($block->itemid && $resume && $resume->profmembershipments[$block->itemid]) {
                                $itemData = $resume->profmembershipments[$block->itemid];
                                $attachments = $itemData->attachments;
                                $description = '';
                                $description .= '<span class="mbrship_title">'.$itemData->title.'</span> ';
                                if ($itemData->startdate != '' || $itemData->enddate != '') {
                                    $description .= ' (';
                                    if ($itemData->startdate != '') {
                                        $description .= '<span class="mbrship_startdate">'.$itemData->startdate.'</span>';
                                    }
                                    if ($itemData->enddate != '') {
                                        $description .= '<span class="mbrship_enddate"> - '.$itemData->enddate.'</span>';
                                    }
                                    $description .= ')';
                                }
                                if ($itemData->description != '') {
                                    $description .= '<span class="mbrship_description">'.$itemData->description.'</span>';
                                }
                                $bodyContent = $description;
                            }
                            break;
                        case 'goalspersonal':
                        case 'goalsacademic':
                        case 'goalscareers':
                        case 'skillspersonal':
                        case 'skillsacademic':
                        case 'skillscareers':
                            $attachments = @$resume->{$block->resume_itemtype.'_attachments'};
                            $description = '';
                            if ($resume && $resume->{$block->resume_itemtype}) {
                                $description .= '<span class="'.$block->resume_itemtype.'_text">'.$resume->{$block->resume_itemtype}.'</span> ';
                            }
                            $bodyContent = $description;
                            break;
                        case 'interests':
                            $description = '';
                            if ($resume->interests != '') {
                                $description .= '<span class="interests">'.$resume->interests.'</span> ';
                            }
                            $bodyContent = $description;
                            break;
                        default:
                            $generalContent .= '!!! '.$block->resume_itemtype.' !!!';
                    }

                    if ($attachments && is_array($attachments) && count($attachments) > 0 && $block->resume_withfiles) {
                        $bodyContent .= '<ul class="resume_attachments '.$block->resume_itemtype.'_attachments">';
                        foreach($attachments as $attachm) {
                            $bodyContent .= '<li><a href="'.$attachm['fileurl'].'" target="_blank">'.$attachm['filename'].'</a></li>';
                        }
                        $bodyContent .= '</ul>';
                    }

                    // if the resume item is empty - do not show
                    if ($bodyContent != '') {
                        $generalContent .= '<div class="view-cv-information">';
                        /*if (isset($block->picture)) {
                            echo '<div class="picture" style="float:right; position: relative;"><img src="'.$block->picture.
                                    '" alt=""/></div>';
                        }*/
                        $generalContent .= $bodyContent;
                        $generalContent .= '</div>';
                        $blockForPdf .= $bodyContent;
                    }
                    break;
                default:
                    // Text.
                    $generalContent .= '<div class="header">'.$block->block_title.'</div>';
                    $generalContent .= '<div class="view-text">';
                    $generalContent .= format_text($block->text, FORMAT_HTML);
                    $generalContent .= '</div>';
                    if ($block->block_title) {
                        $blockForPdf = '<h4>'.$block->block_title.'</h4>';
                    }
                    $blockForPdf .= '<div>'.format_text($block->text, FORMAT_HTML).'</div>';
            }
            $blockForPdf .= '</div>';
            $dataForPdf[$i][] = $blockForPdf;
        }
    }
    $generalContent .= '</td>';
}

$generalContent .= '</tr></table>';
$generalContent .= '</div>';

$generalContent .= "<br />";
$generalContent .= "<div class=''>\n";
$pdflink = $PAGE->url;
$pdflink->params(array(
        'courseid' => optional_param('courseid', 1, PARAM_TEXT),
        'access' => optional_param('access', 0, PARAM_TEXT),
        'ispdf' => 1
));
$generalContent .= '<button class="btn btn-default" onclick="location.href=\''.$pdflink.'\'" type="button">'.block_exaport_get_string('download_pdf').' </button>';

$generalContent .= "</div>\n";

$generalContent .= "<div class='block_eportfolio_center'>\n";

$generalContent .= "</div>\n";
if (!$isPdf) {
    $generalContent .= block_exaport_wrapperdivend();
    $generalContent .= $OUTPUT->footer();
}

if ($isPdf) {
    require_once __DIR__.'/lib/classes/dompdf/autoload.inc.php';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'dejavu sans');
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'landscape');
    /*$context = stream_context_create([
            'ssl' => [
                    'verify_peer' => FALSE,
                    'verify_peer_name' => FALSE,
                    'allow_self_signed'=> TRUE
            ]
    ]);
    $dompdf->setHttpContext($context);*/
    $generalContent = pdfView($view, $colslayout, $dataForPdf);
//    echo $generalContent;exit;
    $dompdf->loadHtml($generalContent);
    $dompdf->render();
    $dompdf->stream('view.pdf'); //To popup pdf as download
    exit;
}

echo $generalContent;


function pdfView($view, $colslayout, $dataForPdf) {
    $pdfContent = '<html>';
    $pdfContent .= '<body>';
    $pdfContent .= '<style>
        body {
            font-family: "Open Sans", "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
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
            /*background-color: #fefefe;*/
            border-top: 1px solid #eeeeee;                       
        }        
        </style>';
    $pdfContent .= '<table border="0" width="100%" class="view-table" style="table-layout:fixed;">';
    $pdfContent .= '<tr>';
    $maxRows = 0;
    //echo "<pre>debug:<strong>shared_view.php:526</strong>\r\n"; print_r($dataForPdf); echo '</pre>'; exit; // !!!!!!!!!! delete it
    foreach ($dataForPdf as $col => $blocks) {
        $maxRows = max(count($blocks), $maxRows);
    }
    for ($colI = 1; $colI <= $colslayout[$view->layout]; $colI++) {
        $pdfContent .= '<td width="'.(round(100 / $colslayout[$view->layout]) - 1).'%" valign="top">';
        if (array_key_exists($colI, $dataForPdf)) {
            $pdfContent .= '<table width="100%" style="word-wrap: break-word !important;">';
            foreach ($dataForPdf[$colI] as $block) {
                $pdfContent .= '<tr><td>';
                $pdfContent .= $block;
                $pdfContent .= '</td></tr>';
            }
            $pdfContent .= '</table>';
        }

        $pdfContent .= '</td>';
    }
    /*for ($rowI = 0; $rowI < $maxRows; $rowI++) {
        $pdfContent .= '<tr>';

        for ($colI = 1; $colI <= $colslayout[$view->layout]; $colI++) {
            $pdfContent .= '<td width="'.(round(100 / $colslayout[$view->layout]) - 1).'%">';
            if (array_key_exists($colI, $dataForPdf)) {
                if (array_key_exists($rowI, $dataForPdf[$colI])) {
                    $pdfContent .= $dataForPdf[$colI][$rowI];
                }
            }
            $pdfContent .= '</td>';
        }
        $pdfContent .= '</tr>';
    }*/
    $pdfContent .= '</tr>';
    $pdfContent .= '</table>';
    $pdfContent .= '</body>';
    $pdfContent .= '</html>';
    //echo $pdfContent; exit;
    return $pdfContent;
}