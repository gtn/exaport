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
    if ($files = block_exaport_get_item_files($item)) {
        foreach ($files as $fileindex => $file) {
            if (!$file) {
                continue; // Is here possible that $file is null?
            }
            $ffurl = s("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=" . $access . "&itemid=" . $item->id . '&inst=' . $fileindex);
            if ($file->is_valid_image()) { // Image attachments don't get printed as links.
                $boxcontent .= "<div class=\"item-detail-image\"><img src=\"$ffurl\" alt=\"" . s($item->name) . "\" /></div>";
            } else {
                $icon = $OUTPUT->pix_icon(file_file_icon($file), '');
                $boxcontent .= "<p class=\"filelink\">" . $icon . ' ' .
                    $OUTPUT->action_link($ffurl, format_string($item->name), new popup_action ('click', $ffurl)) . "</p>";
                if (block_exaport_is_valid_media_by_filename($file->get_filename())) {
                    // Videoblock.
                    $boxcontent .= '
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
                    $boxcontent .= get_string('incompatible_video', 'block_exaport', $a);
                    $boxcontent .= '</div>
                                    </div>';
                    $boxcontent .= "
                    <script src=\"" . $CFG->wwwroot . "/blocks/exaport/javascript/vedeo-js/exaport_video.js\"></script>";
                };
            }
        }
    }

    if (!$boxcontent && !$item->url) {
        if ($item->type != 'note') { // notes can be without files
            $boxcontent = block_exaport_get_string('filenotfound');
        }
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
    if ($item->url && $item->url != "false") {
        $boxcontent .= '<p><a target="_blank" href="' . s($item->url) . '">' . str_replace('http://', '', $item->url) . '</a></p>';
    }
    $boxcontent .= $intro;
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
