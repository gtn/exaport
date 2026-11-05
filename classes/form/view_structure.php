<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software under the GNU General Public License, version 3 or later.

namespace block_exaport\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/../../lib/item_content_helpers.php');

use block_exaport\view_structure as structure;

/** Fill a distributed view using normal Moodle draft areas and portfolio artefacts. */
class view_structure extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $blocks = $this->_customdata['blocks'];
        $mform->addElement('hidden', 'structuretoken', self::token($blocks));
        $mform->setType('structuretoken', PARAM_ALPHANUM);
        $add = function(string $parent, int $depth = 0) use (&$add, $mform, $blocks) {
            foreach (structure::children($blocks, $parent) as $block) {
                $config = $block->config;
                $key = $block->templatekey;
                if ($config['type'] === 'section') {
                    $mform->addElement('html', \html_writer::start_div('exaport-template-fill-section border rounded p-3 mb-4'));
                    $mform->addElement('html', \html_writer::tag($depth ? 'h4' : 'h3', s($config['label'])));
                } else if ($config['type'] === 'heading') {
                    $mform->addElement('html', \html_writer::tag('h4', s($config['label'])));
                }
                if ($config['instructions'] !== '') {
                    $mform->addElement('html', \html_writer::div(nl2br(s($config['instructions'])), 'text-muted mb-3'));
                }
                if ($config['type'] === 'section') {
                    $add($key, $depth + 1);
                    if ($config['repeatable']) {
                        $name = s($config['label']);
                        $siblings = array_filter(structure::children($blocks, $block->parentkey), static function($sibling) use ($config) {
                            return $sibling->config['key'] === $config['key'];
                        });
                        // One add action per set, shown after its last occurrence.
                        $last = end($siblings);
                        if ($last && $last->id === $block->id) {
                            $mform->addElement('html', \html_writer::tag('button',
                                get_string('template_add_another', 'block_exaport', $name), [
                                    'type' => 'submit', 'name' => 'structureoperation', 'value' => 'repeat:' . $key,
                                    'class' => 'btn btn-outline-primary me-2',
                                ]));
                        }
                        if (count($siblings) > $config['minimum']) {
                            $mform->addElement('html', \html_writer::tag('button',
                                get_string('template_remove_section', 'block_exaport', $name), [
                                    'type' => 'submit', 'name' => 'structureoperation', 'value' => 'remove:' . $key,
                                    'class' => 'btn btn-outline-danger',
                                ]));
                        }
                    }
                    $mform->addElement('html', \html_writer::end_div());
                } else if ($config['type'] === 'field') {
                    $name = 'value_' . $key;
                    $mform->addElement($config['multiline'] ? 'textarea' : 'text', $name, $config['label'],
                        $config['multiline'] ? ['rows' => 5, 'cols' => 70] : ['size' => 60]);
                    $mform->setType($name, PARAM_TEXT);
                } else if ($config['type'] === 'artefact') {
                    $mform->addElement('filemanager', 'file_' . $key, $config['label'], null, self::file_options($config));
                    if ($config['reflection'] !== '') {
                        $mform->addElement('textarea', 'reflection_' . $key, $config['reflection'], ['rows' => 6, 'cols' => 70]);
                        $mform->setType('reflection_' . $key, PARAM_TEXT);
                    }
                }
            }
        };
        $add('');
        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'previewbutton', get_string('template_save_preview', 'block_exaport')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttons', '', ' ', false);
    }

    /** Detect stale tabs without relying on timestamps with one-second resolution. */
    public static function token(array $blocks): string {
        global $DB;
        $items = array_values(array_filter(array_map(static function($block) { return (int)$block->itemid; }, $blocks)));
        $parts = $items ? $DB->get_records_list('block_exaportitemblock', 'itemid', $items, 'id') : [];
        $files = [];
        if ($parts) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($parts));
            $files = $DB->get_records_select('files', "itemid $insql AND component = ? AND filearea IN (?, ?) AND filename <> ?",
                array_merge($params, ['block_exaport', 'item_content_file', 'item_content_text', '.']),
                'id', 'id, itemid, filename, filepath, contenthash');
        }
        $responses = array_map(static function($block) {
            return [$block->id, $block->templatekey, $block->itemid, $block->text];
        }, $blocks);
        return hash('sha256', serialize([$responses, $parts, $files]));
    }

    public static function file_options(array $config): array {
        global $CFG;
        return ['subdirs' => 0, 'maxfiles' => 1,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size ?? 0,
            'accepted_types' => [$config['accept']]];
    }

    /** Validate drafts on the server; file-picker restrictions alone are insufficient. */
    public function validation($data, $files): array {
        global $USER;
        $errors = parent::validation($data, $files);
        $context = \context_user::instance((int)$USER->id);
        foreach ($this->_customdata['blocks'] as $block) {
            if ($block->config['type'] !== 'artefact') {
                continue;
            }
            $name = 'file_' . $block->templatekey;
            $draftid = (int)($data[$name] ?? 0);
            $draftfiles = $draftid ? get_file_storage()->get_area_files($context->id, 'user', 'draft', $draftid, 'id', false) : [];
            $accept = $block->config['accept'];
            if (count($draftfiles) > 1) {
                $errors[$name] = get_string('template_file_error', 'block_exaport');
            }
            foreach ($draftfiles as $draftfile) {
                if ($accept !== '*' && (!file_extension_in_typegroup($draftfile->get_filename(), $accept) ||
                        !file_mimetype_in_typegroup($draftfile->get_mimetype(), $accept))) {
                    $errors[$name] = get_string('template_file_error', 'block_exaport');
                }
            }
            [$item, $file] = self::artefact_parts($this->_customdata['view'], $block);
            if ($draftfiles || $file) {
                $item = $item ?: (object)['id' => 0, 'userid' => $USER->id];
                $error = \block_exaport_item_content_file_submission_error($item, $file, $draftid);
                if ($error) {
                    $errors[$name] = get_string($error[0], $error[1], $error[2]);
                }
            }
        }
        return $errors;
    }

    /** Load only an artefact owned by this view's learner. */
    public static function artefact_parts(\stdClass $view, \stdClass $block): array {
        global $DB;
        $item = $block->itemid ? $DB->get_record('block_exaportitem', ['id' => $block->itemid, 'userid' => $view->userid]) : false;
        $file = $text = null;
        if ($item) {
            foreach (\block_exaport_get_item_content_blocks((int)$item->id) as $part) {
                if ($part->type === 'file' && !$file) {
                    $file = $part;
                } else if ($part->type === 'text' && !$text) {
                    $text = $part;
                }
            }
        }
        return [$item, $file, $text];
    }

    /** Prepare independent drafts; no files are stored under template/instructor IDs. */
    public function prepare(\stdClass $view, array $blocks): void {
        $data = new \stdClass();
        $context = \context_user::instance((int)$view->userid);
        foreach ($blocks as $block) {
            $key = $block->templatekey;
            if ($block->config['type'] === 'field') {
                $data->{'value_' . $key} = $block->text;
            } else if ($block->config['type'] === 'artefact') {
                [$item, $file, $text] = self::artefact_parts($view, $block);
                $draftid = file_get_submitted_draft_itemid('file_' . $key);
                file_prepare_draft_area($draftid, $context->id, 'block_exaport', 'item_content_file',
                    $file ? (int)$file->id : null, self::file_options($block->config));
                $data->{'file_' . $key} = $draftid;
                $data->{'reflection_' . $key} = $text ? html_to_text(format_text($text->content, $text->contentformat,
                    ['filter' => false])) : '';
                if ($text && (int)$text->contentformat === FORMAT_PLAIN) {
                    $data->{'reflection_' . $key} = $text->content;
                }
            }
        }
        $this->set_data($data);
    }

    /** Save draft responses even when other slots are still empty. */
    public static function save_responses(\stdClass $view, array $blocks, \stdClass $data): void {
        global $DB;
        $context = \context_user::instance((int)$view->userid);
        foreach ($blocks as $block) {
            $key = $block->templatekey;
            if ($block->config['type'] === 'field') {
                $DB->set_field('block_exaportviewblock', 'text', $data->{'value_' . $key} ?? '', ['id' => $block->id]);
            } else if ($block->config['type'] === 'artefact') {
                [$item, $file, $text] = self::artefact_parts($view, $block);
                $draftid = (int)($data->{'file_' . $key} ?? 0);
                $reflection = $data->{'reflection_' . $key} ?? '';
                $files = $draftid ? get_file_storage()->get_area_files($context->id, 'user', 'draft', $draftid, 'id', false) : [];
                if (!$item && !$files && trim($reflection) === '') {
                    continue;
                }
                if (!$item) {
                    $item = (object)['userid' => $view->userid, 'name' => $block->config['label'],
                        'type' => 'mixed', 'intro' => '', 'courseid' => $view->templatecourseid ?: 0, 'timemodified' => time()];
                    $item->id = $DB->insert_record('block_exaportitem', $item);
                    $DB->update_record('block_exaportviewblock', (object)[
                        'id' => $block->id, 'itemid' => $item->id, 'type' => 'item',
                    ]);
                }
                if ($files || $file) {
                    \block_exaport_check_item_content_file_submission($item, $file, $draftid);
                    if (!$file) {
                        $file = \block_exaport_create_file_content_block((int)$item->id, $block->config['label']);
                    }
                    file_save_draft_area_files($draftid, $context->id, 'block_exaport', 'item_content_file',
                        (int)$file->id, self::file_options($block->config));
                }
                if ($block->config['reflection'] !== '') {
                    if (!$text) {
                        $text = \block_exaport_create_content_block((int)$item->id, 'text');
                    }
                    $DB->update_record('block_exaportitemblock', (object)[
                        'id' => $text->id, 'title' => \core_text::substr($block->config['reflection'], 0, 255),
                        'content' => $reflection, 'contentformat' => FORMAT_PLAIN, 'timemodified' => time(),
                    ]);
                }
                $DB->set_field('block_exaportitem', 'timemodified', time(), ['id' => $item->id]);
            }
        }
        $DB->set_field('block_exaportview', 'timemodified', time(), ['id' => $view->id]);
    }
}
