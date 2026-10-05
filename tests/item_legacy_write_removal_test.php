<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Guards the item editor boundary against restoration of legacy content writers.
 *
 * These source checks are deliberately narrow because loading item.php executes
 * a complete page controller and Moodle form construction needs a live request.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_legacy_write_removal_test extends \advanced_testcase {

    public function test_normal_item_form_excludes_legacy_controls_and_preserves_other_inputs(): void {
        $source = file_get_contents(__DIR__ . '/../lib/item_edit_form.php');
        $itemform = substr($source, strpos($source, 'class block_exaport_item_edit_form'));

        $this->assertStringNotContainsString("addElement('text', 'url'", $itemform);
        $this->assertStringNotContainsString("addElement('filemanager', 'file'", $itemform);
        $this->assertStringContainsString("addElement('filemanager', 'iconfile'", $itemform);
        $this->assertStringContainsString("class block_exaport_comment_edit_form", $source);
        $this->assertStringContainsString("addElement('filemanager', 'file'", $source);
        $this->assertStringContainsString("'submissionid' => PARAM_INT", $source);
        $this->assertStringContainsString("if (\$sections['content'])", $source);

        $structuredform = file_get_contents(__DIR__ . '/../lib/item_content_form.php');
        $this->assertStringContainsString("'files_filemanager'", $structuredform);
    }

    public function test_item_controller_has_no_legacy_write_or_edit_draft_preparation(): void {
        $source = file_get_contents(__DIR__ . '/../item.php');

        $this->assertStringNotContainsString("file_save_draft_area_files(\$post->file", $source);
        $this->assertStringNotContainsString("file_prepare_draft_area(\$draftitemid, \$context->id, 'block_exaport', 'item_file'", $source);
        $this->assertStringNotContainsString('block_exaport_convert_item_type', $source);
        $this->assertStringNotContainsString('file_get_submitted_draft_itemid(\'file\')', $source);
        $this->assertStringContainsString("\$record->url = '';", $source);
        $this->assertStringContainsString("\$record->attachment = '';", $source);
        $this->assertStringContainsString('$record->type = $existing ? $existing->type : $post->type;', $source);
    }

    public function test_disabled_external_writers_contain_no_legacy_implementation(): void {
        $source = file_get_contents(__DIR__ . '/../classes/externallib/externallib.php');
        preg_match('/public static function add_item\(.*?\n    }/s', $source, $addmethod);
        preg_match('/public static function update_item\(.*?\n    }/s', $source, $updatemethod);

        $this->assertStringContainsString('disableditemwriteapi', $addmethod[0]);
        $this->assertStringContainsString('disableditemwriteapi', $updatemethod[0]);
        $this->assertStringNotContainsString('insert_record', $addmethod[0]);
        $this->assertStringNotContainsString('update_record', $updatemethod[0]);
        $this->assertStringNotContainsString('item_file', $addmethod[0] . $updatemethod[0]);
    }

    public function test_external_and_report_structured_content_contracts_are_complete(): void {
        $external = file_get_contents(__DIR__ . '/../classes/externallib/externallib.php');
        $contenthelpers = file_get_contents(__DIR__ . '/../lib/item_content_helpers.php');
        $report = file_get_contents(__DIR__ . '/../lib/reportlib.php');
        $pluginfile = file_get_contents(__DIR__ . '/../lib.php');

        $this->assertStringContainsString("'contentblocks' => []", $external);
        $this->assertStringContainsString('$resultBlock->contentblocks = $item->contentblocks;', $external);
        $this->assertStringContainsString('block_exaport_get_item_content_webservice_data', $external);
        $this->assertStringContainsString("'/webservice/pluginfile.php'", $contenthelpers);
        $this->assertStringContainsString("'?token=' . rawurlencode(\$token)", $contenthelpers);
        $this->assertStringContainsString('@@PLUGINFILE@@', $report);
        $this->assertStringContainsString("case 'item_content_text':", $pluginfile);
        $this->assertStringContainsString('block_exaport_can_user_access_shared_item', $pluginfile);
    }

    public function test_residual_files_remain_at_compliance_and_authorized_serving_boundaries(): void {
        $privacy = file_get_contents(__DIR__ . '/../classes/privacy/provider.php');
        $pluginfile = file_get_contents(__DIR__ . '/../lib.php');

        $this->assertStringContainsString(
            "export_area_files([\$subcontext_name . \$add_tosubcontext_name], 'block_exaport', 'item_file', \$item_id)",
            $privacy
        );
        $this->assertStringContainsString("case 'item_file':", $pluginfile);
        $this->assertStringContainsString(
            'block_exaport_get_item($id, $access, false, $is_for_pdf, $pdfforuserid)',
            $pluginfile
        );
        $this->assertStringContainsString('send_stored_file($file)', $pluginfile);
    }
}
