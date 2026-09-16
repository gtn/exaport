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

require_once(__DIR__ . '/lib.php'); // needed for block_exaport_get_comment_author_name
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

/**
 * Fetch category badge HTML for a single item in the shared detail view.
 *
 * @param int $itemid
 * @param int $userid Owner of the item (used to build full path names).
 * @return string HTML string of category badges, or empty string when no categories.
 */
function block_exaport_extern_item_category_badges(int $itemid, int $userid): string {
    global $DB;

    // Load all categories belonging to this item's owner so we can build full path names.
    $allcategories = $DB->get_records('block_exaportcate', ['userid' => $userid], '', 'id, name, pid');

    // Resolve the full path name for a given category id.
    $fullpath = function(int $catid) use ($allcategories): string {
        $parts = [];
        $id = $catid;
        $visited = [];
        while ($id && isset($allcategories[$id])) {
            if (isset($visited[$id])) {
                break;
            }
            $visited[$id] = true;
            $parts[] = $allcategories[$id]->name;
            $id = (int)($allcategories[$id]->pid ?? 0);
        }
        return implode(' / ', array_reverse($parts));
    };

    $rows = $DB->get_records_sql(
        "SELECT ic.id AS icid, c.id, c.name
           FROM {block_exaportitemcate} ic
           JOIN {block_exaportcate} c ON c.id = ic.cateid
          WHERE ic.itemid = ?
          ORDER BY c.name ASC",
        [$itemid]
    );

    if (!$rows) {
        return '';
    }

    $badges = [];
    foreach ($rows as $row) {
        $label = $fullpath((int)$row->id) ?: format_string($row->name);
        $parts = explode(' / ', $label);
        $shortlabel = trim(end($parts));
        $attrs = [
            'class' => 'badge badge-secondary',
            'data-bs-toggle' => 'tooltip',
            'data-bs-placement' => 'top',
            'data-bs-title' => $label,
        ];
        $badges[] = html_writer::tag('span', $shortlabel, $attrs);
    }

    return html_writer::div(implode(' ', $badges), 'eportfolio-categories');
}

function block_exaport_print_extern_item($item, $access) {
    global $CFG, $OUTPUT, $PAGE, $DB;
    echo $OUTPUT->heading(format_string($item->name));
    $tags = \core_tag_tag::get_item_tags('block_exaport', 'block_exaportitem', $item->id);
    echo $OUTPUT->tag_list($tags, null, 'exaport-artifact-tags', 0, null, false);

    // Display category badges.
    $categorybadges = block_exaport_extern_item_category_badges($item->id, $item->userid);
    if ($categorybadges) {
        echo $categorybadges;
    }

    $boxcontent = '';
    $requiresvideojs = false;
    $blocks = \block_exaport\item_block::get_display_blocks($item, $access);
    foreach ($blocks as $blockindex => $block) {
        $boxcontent .= '<div class="item-project-section exaport-item-block exaport-item-block-' . s($block->type) . '">';
        if (!empty($block->title)) {
            $boxcontent .= '<h4>' . format_string($block->title) . '</h4>';
        }

        if ($block->type === \block_exaport\item_block::TYPE_FILE) {
            foreach ($block->files as $fileindex => $file) {
                $fileurl = !empty($block->fileurl) && count($block->files) === 1
                    ? $block->fileurl
                    : moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        'itemblock_file/' . trim($access, '/') . '/blockid',
                        $block->id,
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    )->out(false);

                if ($file->is_valid_image()) {
                    $boxcontent .= '<div class="item-detail-image"><img src="' . s($fileurl) . '" alt="' .
                        s($file->get_filename()) . '" /></div>';
                } else {
                    $icon = $OUTPUT->pix_icon(file_file_icon($file), '');
                    $boxcontent .= '<p class="filelink">' . $icon . ' ' .
                        $OUTPUT->action_link($fileurl, format_string($file->get_filename()), new popup_action('click', $fileurl)) .
                        '</p>';
                    if (block_exaport_is_valid_media_by_filename($file->get_filename())) {
                        $requiresvideojs = true;
                        $videoid = 'video-file-' . $block->id . '-' . $blockindex . '-' . $fileindex;
                        $boxcontent .= '
                        <div class="video_block">
                            <div class="video_content">
                                <video id="' . s($videoid) . '" class="video-js vjs-default-skin vjs-big-play-centered"
                                            controls preload="auto" width="640" height="480"
                                            data-setup=\'{}\'>
                                    <source src="' . s($fileurl) . '" type="video/mp4" />
                                </video>
                            </div>
                        </div>';
                    }
                }
            }
        } else {
            if ($block->type === \block_exaport\item_block::TYPE_LINK && !empty($block->url)) {
                $label = $block->title ?: preg_replace('!^https?://!i', '', $block->url);
                $boxcontent .= '<p><a target="_blank" rel="noopener noreferrer" href="' . s($block->url) . '">' .
                    s($label) . '</a></p>';
            }
            if (!empty($block->contenthtml)) {
                $boxcontent .= $block->contenthtml;
            }
        }

        $boxcontent .= '</div>';
    }

    if (!$boxcontent && !$blocks && $item->type != 'note') {
        $boxcontent = block_exaport_get_string('filenotfound');
    }

    if ($requiresvideojs) {
        $PAGE->requires->js(new moodle_url('/blocks/exaport/javascript/vedeo-js/exaport_video.js'));
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
        // Check if this is a hidden grader (userid = -1, use strict comparison)
        if ($comment->userid == -1) {
            // Show anonymous user icon for hidden grader
            // echo $OUTPUT->user_picture((object)['id' => 0, 'picture' => 0, 'firstname' => '', 'lastname' => '']);
            // since this above does not work: just show nothing for hidden grader
        } else {
            echo $OUTPUT->user_picture($user);
        }
        echo '</td>';

        echo '<td class="topic starter"><div class="author">';
        // Use helper function to get author name respecting privacy
        $fullname = block_exaport_get_comment_author_name($comment->userid);
        $by = new stdClass();
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
