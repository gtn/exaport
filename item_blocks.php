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

defined('BLOCK_EXAPORT_INTERNAL_ITEM_BLOCKS') || die();

require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

$textfieldoptions = \block_exaport\item_block::get_editor_options((object)['userid' => $USER->id]);
$structuredreturnurl = $returnurl;
$baseeditparams = [
    'courseid' => $courseid,
    'categoryid' => $categoryid,
    'action' => 'edit',
];
if ($cattype) {
    $baseeditparams['cattype'] = $cattype;
}

$blockaction = optional_param('blockaction', '', PARAM_ALPHA);
$blocktype = optional_param('blocktype', '', PARAM_ALPHA);
$blockid = optional_param('blockid', 0, PARAM_INT);
$deleteblock = optional_param('deleteblock', 0, PARAM_INT);
$ajax = optional_param('ajax', 0, PARAM_BOOL);
$ajaxaction = optional_param('ajaxaction', '', PARAM_ALPHA);

if ($existing && !$allowedit && ($deleteblock || in_array($blockaction, ['add', 'edit'], true))) {
    print_error('nopermissions', 'error', '', get_string('edit'));
}

if ($ajax && $existing) {
    require_sesskey();
    try {
        if (!$allowedit) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('edit'));
        }

        if ($ajaxaction === 'saveblock') {
            $blockaction = optional_param('blockaction', '', PARAM_ALPHA);
            $blocktype = optional_param('blocktype', '', PARAM_ALPHA);
            $blockid = optional_param('blockid', 0, PARAM_INT);
            $submitteddata = block_exaport_get_submitted_item_block_data($blocktype);

            if ($blockaction === 'edit') {
                $block = \block_exaport\item_block::get_block($blockid, $existing->id);
                if (!$block) {
                    throw new moodle_exception('invalidblockid', 'block_exaport');
                }
                $blocktype = $block->type;
                \block_exaport\item_block::update_block($existing, $block, $submitteddata);
                $savedblockid = $block->id;
            } else if ($blockaction === 'add') {
                \block_exaport\item_block::validate_type($blocktype);
                $savedblockid = \block_exaport\item_block::create_block($existing, $blocktype, $submitteddata);
            } else {
                throw new moodle_exception('invalidblockid', 'block_exaport');
            }

            $displayblock = block_exaport_get_editor_display_block($existing, $savedblockid);
            block_exaport_send_json([
                'success' => true,
                'blockid' => $savedblockid,
                'rowhtml' => block_exaport_render_item_block_editor_row($displayblock, $existing, $courseid, $categoryid, $cattype),
                'hasblocks' => true,
            ]);
        }

        if ($ajaxaction === 'deleteblock') {
            $deleteblockid = optional_param('blockid', 0, PARAM_INT);
            $block = \block_exaport\item_block::get_block($deleteblockid, $existing->id);
            if (!$block) {
                throw new moodle_exception('invalidblockid', 'block_exaport');
            }
            \block_exaport\item_block::delete_block($existing, $block);
            block_exaport_send_json([
                'success' => true,
                'hasblocks' => !empty(\block_exaport\item_block::get_blocks((int)$existing->id)),
            ]);
        }

        if ($ajaxaction === 'saveblockorder') {
            $submittedorder = json_decode((string)optional_param('blockorder', '', PARAM_RAW_TRIMMED), true);
            \block_exaport\item_block::save_order($existing->id, is_array($submittedorder) ? $submittedorder : []);
            block_exaport_send_json(['success' => true]);
        }

        throw new moodle_exception('invalidblockid', 'block_exaport');
    } catch (moodle_exception $exception) {
        block_exaport_send_json([
            'success' => false,
            'message' => $exception->getMessage(),
            'fielderrors' => block_exaport_get_item_block_field_errors($exception->errorcode, $blocktype),
        ]);
    }
}

if ($deleteblock && $existing && $allowedit) {
    require_sesskey();
    if ($block = \block_exaport\item_block::get_block($deleteblock, $existing->id)) {
        \block_exaport\item_block::delete_block($existing, $block);
    }
    redirect(new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id]));
}

$editform = new block_exaport_item_edit_form($_SERVER['REQUEST_URI'], [
    'current' => $existing,
    'textfieldoptions' => $textfieldoptions,
    'course' => $course,
    'type' => 'mixed',
    'action' => $action,
    'allowedit' => $allowedit,
    'allowresubmission' => $allowresubmission,
    'cattype' => $cattype,
    'catid' => $categoryid,
    'structuredmode' => true,
]);

$edititemurl = null;
if ($existing) {
    $edititemurl = new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id]);
}
$ajaxurl = $edititemurl ? new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id, 'ajax' => 1]) : null;

$blockform = null;
if ($existing && in_array($blockaction, ['add', 'edit'], true)) {
    $blockformstate = block_exaport_build_item_block_edit_form($existing, $blockaction, $blockid, $blocktype);
    $blockform = $blockformstate['form'];
    $block = $blockformstate['block'];
    $blocktype = $blockformstate['blocktype'];
}

if ($blockform && $blockform->is_cancelled()) {
    redirect($edititemurl);
}

if ($blockform && ($blockdata = $blockform->get_data()) && $allowedit) {
    require_sesskey();
    if ($blockaction === 'edit') {
        \block_exaport\item_block::update_block($existing, $block, $blockdata);
    } else {
        \block_exaport\item_block::create_block($existing, $blocktype, $blockdata);
    }
    redirect($edititemurl);
}

if ($editform->is_cancelled()) {
    if ($existing) {
        redirect($structuredreturnurl);
    }
    redirect($returnurl);
} else if (($fromform = $editform->get_data()) && $allowedit) {
    require_sesskey();
    $fromform->categoryids = block_exaport_normalize_item_categoryids($fromform->categoryids ?? []);
    if ($existing) {
        block_exaport_do_edit_structured($existing, $fromform, $courseid);
        if (!empty($fromform->blockorder)) {
            \block_exaport\item_block::save_order($existing->id, json_decode($fromform->blockorder, true) ?: []);
        }
        redirect($edititemurl);
    } else {
        $newitemid = block_exaport_do_add_structured($fromform, $courseid);
        redirect(new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $newitemid]));
    }
}

$post = new stdClass();
$post->allowedit = $allowedit;
$post->langid = 0;
$post->blockorder = '';
if ($existing) {
    $post->id = $existing->id;
    $post->name = $existing->name;
    $post->courseid = $courseid;
    $post->action = 'edit';
    $post->categoryids = array_map('intval', $DB->get_fieldset_select('block_exaportitemcate', 'cateid', 'itemid = ?', [$existing->id]));
    $post->userid = $existing->userid;
    $post->compids = isset($existing->compids) ? $existing->compids : '';
    $post->langid = $existing->langid;
    if (!empty($CFG->usetags)) {
        if ($CFG->branch < 31) {
            $post->tags = tag_get_tags_array('block_exaportitem', $existing->id);
        } else {
            $post->tags = core_tag_tag::get_item_tags_array('block_exaport', 'block_exaportitem', $existing->id,
                core_tag_tag::BOTH_STANDARD_AND_NOT);
        }
    }

    $draftitemid = file_get_submitted_draft_itemid('iconfile');
    file_prepare_draft_area($draftitemid, context_user::instance($USER->id)->id, 'block_exaport', 'item_iconfile', $existing->id,
        array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
    $post->iconfile = $draftitemid;
} else {
    $post->action = 'add';
    $post->courseid = $courseid;
    $post->categoryids = $categoryid > 0 ? [$categoryid] : [];
}
$editform->set_data($post);

$exacompactive = block_exaport_check_competence_interaction() && $descriptorselection;
$PAGE->requires->js('/blocks/exaport/javascript/item.js', true);
$PAGE->requires->js_call_amd('block_exaport/item_blocks', 'init', [[
    'hasitem' => (bool)$existing,
    'itemid' => $existing ? (int)$existing->id : 0,
    'courseid' => (int)$courseid,
    'categoryid' => (int)$categoryid,
    'cattype' => (string)$cattype,
    'ajaxurl' => $ajaxurl ? $ajaxurl->out(false) : '',
    'fragmentcontextid' => context_system::instance()->id,
    'editurl' => $edititemurl ? $edititemurl->out(false) : '',
    'strings' => [
        'choosertitle' => get_string('itemblock_addcontent', 'block_exaport'),
        'text' => get_string('text', 'block_exaport'),
        'file' => get_string('file', 'block_exaport'),
        'link' => get_string('link', 'block_exaport'),
        'add' => get_string('add'),
        'edit' => get_string('edit'),
        'delete' => get_string('delete'),
        'cancel' => get_string('cancel'),
        'savefirst' => get_string('itemblock_savefirst', 'block_exaport'),
        'deleteconfirm' => get_string('itemblock_deleteconfirm', 'block_exaport'),
        'empty' => get_string('itemblock_empty', 'block_exaport'),
    ],
]]);

if ($exacompactive) {
    $PAGE->requires->jquery();
    $PAGE->requires->js('/blocks/exaport/javascript/simpletreemenu.js', true);
    $PAGE->requires->css('/blocks/exaport/javascript/simpletree.css');
    $PAGE->requires->js('/blocks/exaport/javascript/jquery.colorbox.js', true);
    $PAGE->requires->css('/blocks/exaport/css/colorbox.css');
}

block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), $action);
echo block_exaport_wrapperdivstart();
echo html_writer::start_div('exaport-structured-item-editor');
echo html_writer::tag('p', get_string('itemblock_editor_intro', 'block_exaport'), ['class' => 'alert alert-info']);

if ($exacompactive) {
    echo '<fieldset id="general" style="border: 1px solid #ddd; margin: 10px;">';
    echo '<legend class="ftoggler"><b>' . get_string("competences", "block_exaport") . '</b></legend>';
    if (file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php')) {
        echo "<p style='margin-left: 5px;'><a class='competences' href='#'>" . get_string("selectcomps", "block_exaport") . "</a>";
    } else {
        echo "<p style='margin-left: 5px;'" . get_string("competences_old_version", "block_exaport");
    }
    echo "<div style='margin-left: 5px;' id='comptitles'></div></p>";
    echo '</fieldset>';
    ?>
    <div style="display: none">
        <div id='inline_comp_tree' style='padding: 10px; background: #fff;'>
            <h4><?php echo get_string("opencomps", "block_exaport") ?></h4>
            <a href="javascript:ddtreemenu.flatten('comptree', 'expand')"><?php echo get_string("expandcomps", "block_exaport") ?></a> |
            <a href="javascript:ddtreemenu.flatten('comptree', 'contact')"><?php echo get_string("contactcomps", "block_exaport") ?></a>
            <?php echo block_exaport_build_comp_tree('item', $existing, $allowedit); ?>
        </div>
    </div>
    <script type="text/javascript">
        jQueryExaport(function ($) {
            $('#treeform :checkbox').click(function (e) {
                e.stopPropagation();
            });
            var $compids = $('input[name=compids]');
            var $descriptors = $('#treeform :checkbox');
            $(".competences").colorbox({
                width: "75%", height: "75%", inline: true, href: "#inline_comp_tree", onClosed: function () {
                    var compids = '';
                    $descriptors.filter(':checked').each(function () {
                        compids += this.value + ',';
                    });
                    $compids.val(compids);
                    build_competence_output();
                }
            });
            ddtreemenu.createTree("comptree", true);
            function build_competence_output() {
                var $tree = $('#comptree').clone();
                $tree.attr('id', 'comptree-selected');
                $tree.find('li').each(function () {
                    if (!$(this).find(':checked').length) {
                        $(this).remove();
                    }
                });
                $tree.find(':checkbox').remove();
                $("#comptitles").empty().append($tree);
                ddtreemenu.createTree("comptree-selected", false);
                ddtreemenu.flatten('comptree-selected', 'expand');
            }
            build_competence_output();
        });
    </script>
    <?php
}
$editform->display();

echo html_writer::start_div('exaport-item-blocks-editor mt-4');
echo html_writer::tag('h3', get_string('itemblock_contentblocks', 'block_exaport'));
echo html_writer::tag('button',
    block_exaport_fontawesome_icon('plus', 'solid', 1) . ' ' . get_string('itemblock_addcontent', 'block_exaport'),
    ['type' => 'button', 'class' => 'btn btn-secondary exaport-add-content', 'data-action' => 'choose-content',
        'aria-haspopup' => 'dialog']
);

if (!$existing) {
    echo html_writer::tag('p', get_string('itemblock_savefirst', 'block_exaport'), ['class' => 'mt-3 text-muted']);
} else {
    $blocks = \block_exaport\item_block::get_display_blocks($existing, 'portfolio/id/' . $existing->userid);
    echo html_writer::start_tag('ul', ['class' => 'list-group mt-3 exaport-item-block-list' . ($blocks ? '' : ' d-none'),
        'data-itemid' => $existing->id]);
    if ($blocks) {
        foreach ($blocks as $displayblock) {
            echo block_exaport_render_item_block_editor_row($displayblock, $existing, $courseid, $categoryid, $cattype);
        }
    }
    echo html_writer::end_tag('ul');
    echo html_writer::tag('p', get_string('itemblock_empty', 'block_exaport'), [
        'class' => 'mt-3 text-muted exaport-item-block-empty' . ($blocks ? ' d-none' : ''),
    ]);
    echo html_writer::tag('p', get_string('itemblock_dragdrophelp', 'block_exaport'),
        ['class' => 'text-muted mt-2 mb-0', 'id' => 'exaport-item-block-help']);
    echo '<noscript>';
    echo html_writer::tag('div', html_writer::link(
        (new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id, 'blockaction' => 'add',
            'blocktype' => 'text']))->out(false),
        get_string('itemblock_addcontent', 'block_exaport') . ': ' . get_string('text', 'block_exaport')
    ) . ' | ' . html_writer::link(
        (new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id, 'blockaction' => 'add',
            'blocktype' => 'link']))->out(false),
        get_string('itemblock_addcontent', 'block_exaport') . ': ' . get_string('link', 'block_exaport')
    ) . ' | ' . html_writer::link(
        (new moodle_url('/blocks/exaport/item.php', $baseeditparams + ['id' => $existing->id, 'blockaction' => 'add',
            'blocktype' => 'file']))->out(false),
        get_string('itemblock_addcontent', 'block_exaport') . ': ' . get_string('file', 'block_exaport')
    ), '', ['class' => 'mt-2']);
    echo '</noscript>';
}
echo html_writer::end_div();

if ($blockform) {
    echo html_writer::start_div('mt-4');
    echo html_writer::tag('h3', $blockaction === 'edit' ? get_string('edit') : get_string('add'));
    $blockform->display();
    echo html_writer::end_div();
}

if (has_capability('block/exaport:shareintern', context_system::instance())) {
    $translations = array(
        'name', 'role', 'nousersfound',
        'internalaccessgroups', 'grouptitle', 'membercount', 'nogroupsfound',
        'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs',
        'notify', 'checkall', 'viewmustbesafed',
    );

    $translations = array_flip($translations);
    foreach ($translations as $key => &$value) {
        $value = block_exaport_get_string($key);
    }
    unset($value);
    ?>
    <script type="text/javascript">
        ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
    </script>
    <?php
}

echo html_writer::end_div();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);

function block_exaport_render_item_block_editor_row($block, $item, $courseid, $categoryid, $cattype) {
    $params = [
        'courseid' => $courseid,
        'id' => $item->id,
        'action' => 'edit',
        'categoryid' => $categoryid,
    ];
    if ($cattype) {
        $params['cattype'] = $cattype;
    }
    $editurl = new moodle_url('/blocks/exaport/item.php', $params + ['blockaction' => 'edit', 'blockid' => $block->id]);
    $deleteurl = new moodle_url('/blocks/exaport/item.php', $params + ['deleteblock' => $block->id, 'sesskey' => sesskey()]);

    $content = html_writer::start_div('d-flex justify-content-between align-items-start');
    $content .= html_writer::start_div('me-3');
    $content .= html_writer::tag('div',
        block_exaport_fontawesome_icon('grip-vertical', 'solid', 1) . ' ' . format_string($block->title ?: get_string($block->type, 'block_exaport')),
        ['class' => 'fw-bold exaport-item-block-handle', 'aria-label' => get_string('itemblock_reorderlabel', 'block_exaport'),
            'title' => get_string('itemblock_reorderlabel', 'block_exaport'), 'tabindex' => 0]);

    if ($block->type === \block_exaport\item_block::TYPE_FILE) {
        foreach ($block->files as $file) {
            $fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                'itemblock_file/portfolio/id/' . $item->userid . '/blockid',
                $block->id,
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            $content .= html_writer::tag('div', html_writer::link($fileurl, format_string($file->get_filename()),
                ['target' => '_blank', 'rel' => 'noopener noreferrer']), ['class' => 'text-muted']);
        }
    } else {
        if (!empty($block->url)) {
            $content .= html_writer::tag('div', s($block->url), ['class' => 'text-muted']);
        }
        if (!empty($block->contenthtml)) {
            $content .= html_writer::div($block->contenthtml, 'mt-2');
        }
    }
    $content .= html_writer::end_div();
    $content .= html_writer::start_div('btn-group');
    $content .= html_writer::tag('button', get_string('itemblock_moveup', 'block_exaport'), [
        'type' => 'button',
        'class' => 'btn btn-sm btn-outline-secondary exaport-item-block-move',
        'data-direction' => 'up',
    ]);
    $content .= html_writer::tag('button', get_string('itemblock_movedown', 'block_exaport'), [
        'type' => 'button',
        'class' => 'btn btn-sm btn-outline-secondary exaport-item-block-move',
        'data-direction' => 'down',
    ]);
    $content .= html_writer::link($editurl, get_string('edit'), [
        'class' => 'btn btn-sm btn-outline-primary exaport-item-block-edit',
        'data-blockid' => $block->id,
        'data-blocktype' => $block->type,
    ]);
    $content .= html_writer::link($deleteurl, get_string('delete'), [
        'class' => 'btn btn-sm btn-outline-danger exaport-item-block-delete',
        'data-blockid' => $block->id,
        'data-blocktype' => $block->type,
    ]);
    $content .= html_writer::end_div();
    $content .= html_writer::end_div();

    return html_writer::tag('li', $content, [
        'class' => 'list-group-item',
        'data-blockid' => $block->id,
        'aria-describedby' => 'exaport-item-block-help',
    ]);
}

function block_exaport_do_add_structured($post, $courseid) {
    global $CFG, $USER, $DB;

    $post->userid = $USER->id;
    $post->type = 'mixed';
    $post->categoryid = 0;
    $post->url = '';
    $post->intro = '';
    $post->attachment = '';
    $post->project_description = '';
    $post->project_process = '';
    $post->project_result = '';
    $post->timecreated = time();
    $post->timemodified = time();
    $post->courseid = $courseid;
    $post->shareall = 0;
    $post->externaccess = 0;
    $post->externcomment = 0;
    $post->sortorder = 0;
    $post->isoez = 0;
    $post->fileurl = '';
    $post->beispiel_url = '';
    $post->exampid = 0;
    $post->source = 0;
    $post->sourceid = 0;
    $post->iseditable = 1;
    $post->example_url = '';
    $post->parentid = 0;
    $post->beispiel_angabe = '';

    $itemid = (int)$DB->insert_record('block_exaportitem', $post);
    \block_exaport\item_category_helper::sync_item_categories($itemid, $post->categoryids ?? []);
    block_exaport_save_item_shares($itemid);

    if (!empty($post->iconfile)) {
        $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->iconfile);
        if (block_exaport_file_userquotecheck($uploadfilesizes, $itemid)
            && block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)) {
            file_save_draft_area_files($post->iconfile, context_user::instance($USER->id)->id, 'block_exaport', 'item_iconfile', $itemid,
                array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        }
    }

    if ($CFG->branch < 31) {
        tag_set('block_exaportitem', $itemid, $post->tags ?? [], 'block_exaport', context_user::instance($USER->id)->id);
    } else {
        core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $itemid, context_user::instance($USER->id), $post->tags ?? []);
    }

    if (block_exaport_check_competence_interaction()) {
        $comps = $post->compids ?? '';
        if ($comps) {
            $comps = explode(",", $comps);
            $course = $DB->get_record('course', array("id" => $courseid));
            foreach ($comps as $comp) {
                if ($comp != 0) {
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("compid" => $comp, "activityid" => $itemid, "eportfolioitem" => 1,
                            "activitytitle" => $post->name, "coursetitle" => $course->shortname));
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                        array("compid" => $comp, "activityid" => $itemid, "eportfolioitem" => 1, "reviewerid" => $USER->id,
                            "userid" => $USER->id, "role" => 0));
                }
            }
        }
    }

    block_exaport_add_to_log(SITEID, 'bookmark', 'add', 'item.php?courseid=' . $courseid . '&id=' . $itemid . '&action=add', $post->name);

    return $itemid;
}

function block_exaport_do_edit_structured($existing, $post, $courseid) {
    global $CFG, $USER, $DB;

    $record = (object)[
        'id' => $existing->id,
        'name' => $post->name,
        'langid' => $post->langid ?? 0,
        'timemodified' => time(),
    ];
    $DB->update_record('block_exaportitem', $record);
    \block_exaport\item_category_helper::sync_item_categories($existing->id, $post->categoryids ?? []);
    block_exaport_save_item_shares($existing->id);

    if (isset($post->iconfile)) {
        $uploadfilesizes = block_exaport_get_filessize_by_draftid($post->iconfile);
        if (block_exaport_file_userquotecheck($uploadfilesizes, $existing->id)
            && block_exaport_get_maxfilesize_by_draftid_check($post->iconfile)) {
            file_save_draft_area_files($post->iconfile, context_user::instance($USER->id)->id, 'block_exaport', 'item_iconfile',
                $existing->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        }
    }

    if ($CFG->branch < 31) {
        tag_set('block_exaportitem', $existing->id, $post->tags ?? [], 'block_exaport', context_user::instance($USER->id)->id);
    } else {
        core_tag_tag::set_item_tags('block_exaport', 'block_exaportitem', $existing->id, context_user::instance($USER->id), $post->tags ?? []);
    }

    if (block_exaport_check_competence_interaction()) {
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $existing->id, "eportfolioitem" => 1));
        $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
            array("activityid" => $existing->id, "eportfolioitem" => 1, "reviewerid" => $USER->id));
        $comps = $post->compids ?? '';
        if ($comps) {
            $comps = explode(",", $comps);
            $course = $DB->get_record('course', array("id" => $courseid));
            foreach ($comps as $comp) {
                if ($comp != 0) {
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("compid" => $comp, "activityid" => $existing->id, "eportfolioitem" => 1,
                            "activitytitle" => $post->name, "coursetitle" => $course->shortname));
                    $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                        array("compid" => $comp, "activityid" => $existing->id, "eportfolioitem" => 1, "reviewerid" => $USER->id,
                            "userid" => $USER->id, "role" => 0));
                }
            }

        }
    }

    block_exaport_add_to_log(SITEID, 'bookmark', 'update', 'item.php?courseid=' . $courseid . '&id=' . $existing->id . '&action=edit',
        $post->name);
}

function block_exaport_get_submitted_item_block_data(string $blocktype): stdClass {
    $data = (object)[
        'title' => optional_param('title', '', PARAM_TEXT),
        'url' => optional_param('url', '', PARAM_RAW_TRIMMED),
    ];

    if (in_array($blocktype, [\block_exaport\item_block::TYPE_TEXT, \block_exaport\item_block::TYPE_LINK], true)) {
        $contenteditor = optional_param_array('content_editor', [], PARAM_RAW);
        $data->content_editor = [
            'text' => (string)($contenteditor['text'] ?? ''),
            'format' => clean_param($contenteditor['format'] ?? FORMAT_HTML, PARAM_INT),
            'itemid' => clean_param($contenteditor['itemid'] ?? 0, PARAM_INT),
        ];
    }

    if ($blocktype === \block_exaport\item_block::TYPE_FILE) {
        $data->file = optional_param('file', 0, PARAM_INT);
    }

    return $data;
}

function block_exaport_get_item_block_field_errors(string $errorcode, string $blocktype): array {
    switch ($errorcode) {
        case 'invalidblockurl':
            return ['url' => get_string('invalidblockurl', 'block_exaport')];
        case 'uploadfailed':
            return ['file' => get_string('uploadfailed', 'block_exaport')];
        case 'invalidblockcontent':
            if ($blocktype === \block_exaport\item_block::TYPE_FILE) {
                return ['file' => get_string('invalidblockcontent', 'block_exaport')];
            }
            return ['content_editor' => get_string('invalidblockcontent', 'block_exaport')];
        default:
            return [];
    }
}

function block_exaport_get_editor_display_block(stdClass $item, int $blockid): stdClass {
    foreach (\block_exaport\item_block::get_display_blocks($item, 'portfolio/id/' . $item->userid) as $displayblock) {
        if ((int)$displayblock->id === $blockid) {
            return $displayblock;
        }
    }

    throw new moodle_exception('invalidblockid', 'block_exaport');
}

function block_exaport_send_json(array $payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
