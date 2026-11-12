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

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/item_content_helpers.php');

use block_exaport\blockedit;
use function block_exaport\common\print_error;

// Called from pluginfile.php
// to serve the file of a plugin
// urlformat:
// http://localhost/moodle20/pluginfile.php/17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// 17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// user context id (moodle standard)
// moudle name (moodle standard)
// file column name (moodle standard)
// access string according to exaport
// itemid (string)
// itemid
// file name.
function block_exaport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    \block_exaport\local\item_content_migration::require_ready();
    $file = block_exaport_get_authorized_file($context, $filearea, $args);
    if (!$file || $file->is_directory()) {
        return false;
    }

    $lifetime = in_array($filearea, ['category_icon', 'item_content_linked'], true) ? 0 : null;
    if (in_array($filearea, [
            'item_content_linked', 'item_content_file', 'item_content_text', 'item_file', 'item_iconfile',
    ], true)) {
        // Keep HTML sanitising and Moodle's download options at the delivery boundary.
        block_exaport_send_item_file($file, $lifetime, $forcedownload, $options);
    } else {
        send_stored_file($file, $lifetime, 0, $forcedownload, $options);
    }
}

/**
 * Resolve a file using the same permissions for HTTP delivery and PDF images.
 *
 * Returning the stored file lets PDF exports embed its bytes without creating
 * URLs that bypass login or accepting a viewer identity from the request.
 *
 * @param context $context Context from the pluginfile URL.
 * @param string $filearea Exaport file area.
 * @param array $args Decoded URL arguments following the file area.
 * @return stored_file|false Authorised file, or false when unavailable.
 */
function block_exaport_get_authorized_file(context $context, string $filearea, array $args) {
    \block_exaport\local\item_content_migration::require_ready();
    global $USER, $DB;

    // Reject obsolete PDF URLs, including those carrying another collection's hash.
    if (in_array('forPdf', $args, true)) {
        return false;
    }
    if ($filearea !== 'pdf_fontfamily') {
        require_login();
    }

    $viewuser = 0;
    switch ($filearea) {
        case 'item_content_linked':
            $parsed = block_exaport_parse_item_content_file_args($args);
            if (!$parsed || !preg_match('~^/(\d+)/$~', $parsed['filepath'], $matches)) {
                return false;
            }
            $block = $DB->get_record('block_exaportitemblock', [
                'id' => $parsed['blockid'], 'itemid' => $parsed['itemid'],
            ]);
            if (!$block || !in_array($block->type, \block_exaport\local\item_linked_content::TYPES, true)) {
                return false;
            }
            if (preg_match('~^webservice/(\d+)$~', $parsed['access'], $ownermatches)) {
                $item = block_exaport_get_item_for_webservice($parsed['itemid'], (int)$ownermatches[1], (int)$USER->id);
            } else if ($parsed['access'] !== '') {
                $item = block_exaport_get_item($parsed['itemid'], $parsed['access']);
            } else {
                $item = $DB->get_record('block_exaportitem', ['id' => $parsed['itemid']]);
                if (!$item || ((int)$item->userid !== (int)$USER->id &&
                        !block_exaport_can_user_access_shared_item($USER->id, $item->id))) {
                    return false;
                }
            }
            if (!$item || (int)$context->id !== (int)context_user::instance($item->userid)->id) {
                return false;
            }
            // Re-resolve on every request: removing a selection or revoking a badge also
            // revokes its files. The URL never grants access to the rest of the owner's CV.
            $linked = \block_exaport\local\item_linked_content::resolve($block, (int)$item->userid);
            $file = $linked['editorfiles'][(int)$matches[1]] ?? null;
            if (!$file || $file->get_filename() !== $parsed['filename']) {
                return false;
            }
            return $file;
        case 'item_content_file':
            $parsed = block_exaport_parse_item_content_file_args($args);
            if (!$parsed) {
                return false;
            }
            ['access' => $access, 'itemid' => $itemid, 'blockid' => $blockid,
                'filepath' => $filepath, 'filename' => $filename] = $parsed;

            $block = block_exaport_get_item_content_file_block((int)$itemid, (int)$blockid);
            if (!$block) {
                return false;
            }

            if (preg_match('~^webservice/(\d+)$~', $access, $matches)) {
                $item = block_exaport_get_item_for_webservice($itemid, (int)$matches[1], (int)$USER->id);
            } else if ($access !== '') {
                $item = block_exaport_get_item($itemid, $access);
            } else {
                $item = $DB->get_record('block_exaportitem', ['id' => $itemid]);
                $sharedownerid = $item ? block_exaport_can_user_access_shared_item($USER->id, $itemid) : false;
                if (!$item || ((int)$item->userid !== (int)$USER->id && !$sharedownerid)) {
                    $item = false;
                }
            }
            if (!$item) {
                return false;
            }

            $file = get_file_storage()->get_file(
                context_user::instance($item->userid)->id,
                'block_exaport',
                'item_content_file',
                $blockid,
                $filepath,
                $filename
            );
            if (!$file || $file->is_directory()) {
                return false;
            }
            return $file;
        case 'item_content_text':
            $itemmarker = array_search('itemid', $args, true);
            $blockmarker = array_search('blockid', $args, true);
            if ($itemmarker === false && $blockmarker === false) {
                $blockid = (int)array_shift($args);
                $block = $DB->get_record('block_exaportitemblock', ['id' => $blockid, 'type' => 'text']);
                $itemid = $block ? (int)$block->itemid : 0;
                $access = '';
            } else {
                if ($itemmarker === false || $blockmarker === false || $blockmarker !== $itemmarker + 2) {
                    return false;
                }
                $access = join('/', array_slice($args, 0, $itemmarker));
                $itemid = (int)$args[$itemmarker + 1];
                $blockid = (int)$args[$blockmarker + 1];
                $args = array_slice($args, $blockmarker + 2);
                $block = $DB->get_record('block_exaportitemblock', [
                    'id' => $blockid,
                    'itemid' => $itemid,
                    'type' => 'text',
                ]);
            }
            $filename = array_pop($args);
            $filepath = '/' . ($args ? join('/', $args) . '/' : '');

            if ($access !== '') {
                $item = $block ? block_exaport_get_item($itemid, $access) : false;
            } else {
                $item = $block ? $DB->get_record('block_exaportitem', ['id' => $itemid]) : false;
                $sharedownerid = $item ? block_exaport_can_user_access_shared_item($USER->id, $itemid) : false;
                if (!$item || ((int)$item->userid !== (int)$USER->id && !$sharedownerid)) {
                    $item = false;
                }
            }
            if (!$item) {
                return false;
            }
            $file = get_file_storage()->get_file(
                context_user::instance($item->userid)->id,
                'block_exaport',
                'item_content_text',
                $blockid,
                $filepath,
                $filename
            );
            if (!$file || $file->is_directory()) {
                return false;
            }
            return $file;
        case 'item_file':
        case 'item_iconfile':
            $filename = array_pop($args);
            $id = array_pop($args);
            if (array_pop($args) != 'itemid') {
                print_error('wrong params');
            }
            // Other params together are the access string.
            $access = join('/', $args);

            // Item exists?
            $item = block_exaport_get_item($id, $access);
            if (!$item) {
                print_error('Item not found');
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

            return $file;
        case 'item_content':
        case 'item_content_project_description':
        case 'item_content_project_process':
        case 'item_content_project_result':
            $filename = array_pop($args);
            $id = array_pop($args);
            if (array_pop($args) != 'itemid') {
                print_error('wrong params');
            }

            // Other params together are the access string.
            $access = join('/', $args);

            // Item exists?
            $item = block_exaport_get_item($id, $access);
            if (!$item) {
                print_error('Item not found');
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

            return $file;
        case 'view_content':
            $filename = array_pop($args);

            // Other params together are the access string.
            $access = join('/', $args);

            if (!$view = block_exaport_get_view_from_access($access)) {
                print_error("viewnotfound", "block_exaport");
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', $filearea, $view->id, '/', $filename);
            return $file;
        case 'personal_information_view':
            $filename = array_pop($args);

            // Other params together are the access string.
            $access = join('/', $args);

            if (!$view = block_exaport_get_view_from_access($access)) {
                print_error("viewnotfound", "block_exaport");
            }

            // View has personal information?
            $sql = "SELECT b.* FROM {block_exaportviewblock} b" .
                " WHERE b.viewid=? AND" .
                " b.type='personal_information'";
            if (!$DB->record_exists_sql($sql, array($view->id))) {
                return false;
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', 'personal_information', $view->userid,
                '/', $filename);

            return $file;
        case 'personal_information_self':
            $filename = join('/', $args);

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', $USER->id, '/',
                $filename);

            return $file;
        case 'category_icon':
            $filename = array_pop($args);
            $categoryid = (int)array_pop($args);
            // External previews carry the category page's hash, within Moodle's guest-login rules.
            $categoryaccess = optional_param('access', '', PARAM_PATH);
            $category = $DB->get_record('block_exaportcate', ['id' => $categoryid]);
            if ($args || !$category || $context->contextlevel !== CONTEXT_USER
                    || (int)$context->instanceid !== (int)$category->userid
                    || !\block_exaport\category_helper::can_access_thumbnail($category, $categoryaccess)) {
                return false;
            }

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file($context->id, 'block_exaport', 'category_icon', $categoryid, '/',
                $filename);

            // Recheck sharing on every request; do not serve arbitrary files from this image area.
            if ($file && !$file->is_directory() && $file->is_valid_image()) {
                return $file;
            }
            return false;
        case 'resume_editor_cover':
        case 'resume_editor_goalspersonal':
        case 'resume_editor_goalsacademic':
        case 'resume_editor_goalscareers':
        case 'resume_editor_skillspersonal':
        case 'resume_editor_skillsacademic':
        case 'resume_editor_skillscareers':
        case 'resume_editor_interests':
            $access = required_param('access', PARAM_RAW);
            if (preg_match('~^resume/(\d+)/(\d+)$~', $access, $matches)) {
                // The editing URL is not a sharing grant: only the CV owner may use it.
                $resume = $DB->get_record('block_exaportresume', ['id' => $matches[1], 'user_id' => $USER->id]);
                if (!$resume) {
                    return false;
                }
                $viewuser = $resume->user_id;
            } else {
                $view = block_exaport_get_view_from_access($access);
                // Sharing one CV section must not expose images in other sections.
                $section = substr($filearea, strlen('resume_editor_'));
                if (!$view || !$DB->record_exists_select('block_exaportviewblock',
                        "viewid = ? AND type IN ('cv_information', 'cv_group') AND resume_itemtype = ?",
                        [$view->id, $section])) {
                    return false;
                }
                $viewuser = $view->userid;
            }
        case 'resume_cover':
        case 'resume_interests':
        case 'resume_edu':
        case 'resume_employ':
        case 'resume_certif':
        case 'resume_public':
        case 'resume_mbrship':
        case 'resume_goalspersonal':
        case 'resume_goalsacademic':
        case 'resume_goalscareers':
        case 'resume_skillspersonal':
        case 'resume_skillsacademic':
        case 'resume_skillscareers':
            if (!$viewuser) {
                $viewuser = $USER->id;
            }
            $filename = array_pop($args);
            $id = array_pop($args);

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_user::instance($viewuser)->id, 'block_exaport', $filearea, $id, '/', $filename);
            return $file;
        case 'pdf_fontfamily':
            $filename = array_pop($args);
            $itemid = array_pop($args); // Always 0.

            // Get file.
            $fs = get_file_storage();
            $file = $fs->get_file(context_system::instance()->id, 'block_exaport', 'pdf_fontfamily', $itemid, '/', $filename);

            return $file;
        default:
            return false;
    }
}

/**
 * Return a list of all the user preferences used by block_exaport.
 *
 * @uses core_user::is_current_user
 *
 * @return array[]
 */
function block_exaport_user_preferences(): array {
    $preferences = [];
    $preferences['block_exaport_layout'] = [
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => block_exaport_get_default_layout(),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $preferences['block_exaport_folderlayout'] = [
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => block_exaport_get_default_folderlayout(),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $preferences['block_exaport_sort'] = [
        'type' => PARAM_ALPHANUMEXT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 'date-desc',
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $preferences['block_exaport_show_subcategories'] = [
        'type' => PARAM_INT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 0,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $preferences['block_exaport_show_otherusers'] = [
        'type' => PARAM_INT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 1,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $preferences['block_exaport_entrytype'] = [
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => 'all',
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    return $preferences;
}

/**
 * Fragment callback for editing the working copy of a course template block.
 *
 * @param array $args Fragment arguments including the validated course context
 * @return string Block edit form HTML
 */
function block_exaport_output_fragment_view_template_blockedit($args) {
    \block_exaport\local\item_content_migration::require_ready();
    global $CFG, $DB;
    $courseid = clean_param($args['courseid'] ?? 0, PARAM_INT);
    // Moodle's core_get_fragment has already authenticated this context and initialised the page theme.
    block_exaport_require_login(null);
    $context = $args['context'] ?? null;
    if (!$context instanceof context_course || (int)$context->instanceid !== $courseid) {
        throw new invalid_parameter_exception('Mismatched template course context.');
    }
    require_capability('block/exaport:distributecategories', $context);
    if (empty($CFG->block_exaport_enable_category_distribution)) {
        throw new moodle_exception('areaisdisabled', 'block_exaport');
    }
    $template = $DB->get_record(
        'block_exaport_view_templ',
        ['id' => clean_param($args['templateid'] ?? 0, PARAM_INT), 'courseid' => $courseid],
        '*',
        MUST_EXIST
    );
    $block = json_decode($args['block'] ?? '{}', true);
    if (!is_array($block)) {
        throw new invalid_parameter_exception('Invalid template block.');
    }
    $block['type'] = clean_param($args['type_block'] ?? '', PARAM_TEXT);
    $block = \block_exaport\view_template::normalise_blocks([$block], (int)($template->layout ?? 2))[0];
    if ($block->type === 'headline') {
        return blockedit::get_form_headline(0, $block, false);
    }
    $html = blockedit::get_form_text(0, $block, false);
    editors_get_preferred_editor(FORMAT_HTML)->use_editor(
        'id_block_text',
        ['maxfiles' => 0, 'context' => $context, 'autosave' => false]
    );
    return $html;
}

/**
 * Fragment callback for loading block edit forms via the Fragment API.
 * Moodle automatically captures $PAGE->requires JS (editor init) and delivers it alongside the HTML.
 */
function block_exaport_output_fragment_blockedit($args) {
    \block_exaport\local\item_content_migration::require_ready();
    global $CFG;

    require_login();

    $type = clean_param($args['type_block'] ?? '', PARAM_TEXT);
    $id = clean_param($args['item_id'] ?? 0, PARAM_INT);
    // Set draft_itemid in $_POST so file_get_submitted_draft_itemid() works.
    if (!empty($args['draft_itemid'])) {
        $_POST['text'] = ['itemid' => clean_param($args['draft_itemid'], PARAM_INT)];
    }

    $formdata = blockedit::load_form($id, $type);

    // Initialize the HTML editor for block types that have a textarea.
    // Calling use_editor() queues JS via $PAGE->requires, which the Fragment API captures automatically.
    if ($formdata->type == 'text' || $formdata->type == 'personal_information') {
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->use_editor('id_block_text', [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
            'autosave' => false,
        ]);
    }

    return $formdata->html;
}

/** Deleting users or courses also deletes contexts/files used by the paused plugins. */
function block_exaport_pre_user_delete($user) {
    \block_exaport\local\item_content_migration::require_ready();
}

function block_exaport_pre_course_delete($course) {
    \block_exaport\local\item_content_migration::require_ready();
}
