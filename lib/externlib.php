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

defined('MOODLE_INTERNAL') || die();

function block_exaport_get_user_from_hash($hash) {
    trigger_error('deprecated');
    $conditions = array("user_hash" => $hash);
    if (!$hashrecord = $DB->get_record("block_exaportuser", $conditions)) {
        return false;
    } else {
        $conditions = array("id" => $hashrecord->user_id);
        return $DB->get_record("user", $conditions);
    }
}

function block_exaport_print_extern_item($item, $access) {
    global $CFG, $OUTPUT;
    echo $OUTPUT->heading(format_string($item->name));
    $tags = \core_tag_tag::get_item_tags('block_exaport', 'block_exaportitem', $item->id);
    echo $OUTPUT->tag_list($tags, null, 'exaport-artifact-tags', 0, null, false);

    $boxcontent = '';
    $filescontent = '';
    if ($files = block_exaport_get_item_files($item)) {
        foreach ($files as $fileindex => $file) {
            if (!$file) {
                continue; // Is here possible that $file is null?
            }
            $ffurl = s("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=" . $access . "&itemid=" . $item->id . '&inst=' . $fileindex);
            if ($file->is_valid_image()) { // Image attachments don't get printed as links.
                $filescontent .= "<div class=\"item-detail-image\"><img src=\"$ffurl\" alt=\"" . s($item->name) . "\" /></div>";
            } else {
                $icon = $OUTPUT->pix_icon(file_file_icon($file), '');
                $filescontent .= "<p class=\"filelink\">" . $icon . ' ' .
                    $OUTPUT->action_link($ffurl, format_string($item->name), new popup_action ('click', $ffurl)) . "</p>";
                if (block_exaport_is_valid_media_by_filename($file->get_filename())) {
                    // Videoblock.
                    $filescontent .= '
                    <div id="video_block">
                        <div id="video_content">
                            <video id="video_file" class="video-js vjs-default-skin vjs-big-play-centered"
                                        controls preload="auto" width="640" height="480"
                                        data-setup=\'{}\'>
                                <source src="' . $ffurl . '" type="video/mp4" />
                                <p class="vjs-no-js">To view this video please enable JavaScript, and consider upgrading
                                        to a web browser that
                                        <a href="http://videojs.com/html5-video-support/" target="_blank">
                                            supports HTML5 video</a></p>
                            </video>
                        </div>
                        <div id="video_error" style="display: none;" class="incompatible_video">';
                    $a = new stdClass ();
                    $a->link = $OUTPUT->action_link($ffurl, format_string($item->name), new popup_action ('click', $ffurl));
                    $filescontent .= get_string('incompatible_video', 'block_exaport', $a);
                    $filescontent .= '</div>
                                    </div>';
                    $filescontent .= "
                    <script src=\"" . $CFG->wwwroot . "/blocks/exaport/javascript/vedeo-js/exaport_video.js\"></script>";
                };
            }
        }
    }

    if (!$filescontent && !$item->url) {
        if ($item->type != 'note') { // notes can be without files
            $boxcontent = block_exaport_get_string('filenotfound');
        }
    }

    // Display files/attachments with heading if they exist
    if ($filescontent) {
        $boxcontent .= '<div class="item-project-section">';
        $boxcontent .= '<h4>' . get_string('file', 'block_exaport') . '</h4>';
        $boxcontent .= $filescontent;
        $boxcontent .= '</div>';
    }

    $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id,
        'block_exaport', 'item_content', $access . '/itemid/' . $item->id);
    $intro = format_text($intro);
    $template_text_to_html = text_to_html('');
    $intro = trim($intro);
    if ($intro && $intro == $template_text_to_html && strpos($item->intro, '<iframe') !== false) {
        // TODO: test - if the intro is empty - it will have wrapper template (Moodle api)
        // in this case it is possible that it is cleaned media link. Get it again
        $intro = $item->intro;
    }

    // Display URL with heading if it exists
    if ($item->url && $item->url != "false") {
        $boxcontent .= '<div class="item-project-section">';
        $boxcontent .= '<h4>' . get_string('url', 'block_exaport') . '</h4>';
        $boxcontent .= '<p><a target="_blank" href="' . s($item->url) . '">' . str_replace('http://', '', $item->url) . '</a></p>';
        $boxcontent .= '</div>';
    }

    // Display short description (intro field) with heading
    if ($intro) {
        $boxcontent .= '<div class="item-project-section">';
        $boxcontent .= '<h4>' . get_string('shortdescription', 'block_exaport') . '</h4>';
        $boxcontent .= $intro;
        $boxcontent .= '</div>';
    }

    // Display project information fields if they exist
    if (@$item->project_description || @$item->project_process || @$item->project_result) {
        // The why behind this project
        if (@$item->project_description) {
            $boxcontent .= '<div class="item-project-section">';
            $boxcontent .= '<h4>' . get_string('project_description', 'block_exaport') . '</h4>';
            $content = file_rewrite_pluginfile_urls($item->project_description, 'pluginfile. php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content_project_description',
                $access . '/itemid/' . $item->id);
            $boxcontent .= format_text($content);
            $boxcontent .= '</div>';
        }

        // Making it happen
        if (@$item->project_process) {
            $boxcontent .= '<div class="item-project-section">';
            $boxcontent .= '<h4>' . get_string('project_process', 'block_exaport') . '</h4>';
            $content = file_rewrite_pluginfile_urls($item->project_process, 'pluginfile.php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content_project_process',
                $access . '/itemid/' . $item->id);
            $boxcontent .= format_text($content);
            $boxcontent .= '</div>';
        }

        // Results and learnings
        if (@$item->project_result) {
            $boxcontent .= '<div class="item-project-section">';
            $boxcontent .= '<h4>' . get_string('project_result', 'block_exaport') . '</h4>';
            $content = file_rewrite_pluginfile_urls($item->project_result, 'pluginfile.php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content_project_result',
                $access . '/itemid/' . $item->id);
            $boxcontent .= format_text($content);
            $boxcontent .= '</div>';
        }
    }

    echo $OUTPUT->box($boxcontent);
}

function block_exaport_print_extcomments($itemid) {

    global $DB, $OUTPUT;

    $stredit = get_string('edit');
    $strdelete = get_string('delete');

    $conditions = array("itemid" => $itemid);
    $comments = $DB->get_records("block_exaportitemcomm", $conditions, 'timemodified DESC');
    if (!$comments) {
        return;
    }

    foreach ($comments as $comment) {
        $conditions = array("id" => $comment->userid);
        $user = $DB->get_record('user', $conditions);

        echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

        echo '<tr class="header"><td class="picture left">';
        echo $OUTPUT->user_picture($user);
        echo '</td>';

        echo '<td class="topic starter"><div class="author">';
        $fullname = fullname($user, $comment->userid);
        $by = new object();
        $by->name = $fullname;
        $by->date = userdate($comment->timemodified);
        print_string('bynameondate', 'forum', $by);

        echo '</div></td></tr>';

        echo '<tr><td class="left side">';

        echo '</td><td class="content">' . "\n";

        echo format_text($comment->entry);

        echo '</td></tr></table>' . "\n\n";
    }
}
