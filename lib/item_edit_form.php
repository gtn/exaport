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

require_once($CFG->libdir . '/formslib.php');

class block_exaport_comment_edit_form extends block_exaport_moodleform {

    public function definition() {
        global $CFG, $USER, $DB;
        $mform = &$this->_form;

        $this->_form->_attributes['action'] = $_SERVER['REQUEST_URI'];

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', 'add');

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->setDefault('itemid', 0);

        $mform->addElement('hidden', 'commentid');
        $mform->setType('commentid', PARAM_INT);
        $mform->setDefault('commentid', 0);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setDefault('userid', 0);

        $isediting = !empty($this->_customdata['is_editing']);

        $mform->addElement('header', 'comment',
            get_string($isediting ? 'editcomment' : 'addcomment', 'block_exaport'));

        $mform->addElement('editor', 'entry', get_string("comment", "block_exaport"), null,
            array('rows' => 10, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        $mform->setType('entry', PARAM_TEXT);
        $mform->addRule('entry', get_string("commentshouldnotbeempty", "block_exaport"), 'required', null, 'client');
        $mform->add_exaport_help_button('entry', 'forms.items_comment.entry');

        $mform->addElement('filemanager', 'file', get_string('file', 'block_exaport'), null,
            array('subdirs' => 0, 'maxfiles' => 1));
        $mform->add_exaport_help_button('file', 'forms.items_comment.file');

        /*
        fjungwirth: hide grading at this stage (meeting LS 4.7.16)

        if ($this->_customdata['gradingpermission']) {
            $mform->addElement('header', 'itemgrading', get_string("itemgrading", "block_exaport"));
            $itemgrade = $this->_customdata['itemgrade'];
            $mform->addElement('select', 'itemgrade', get_string('gradeitem', 'block_exaport'), range(0, 100));
            $mform->setDefault('itemgrade', $itemgrade);

            $slider = '<div id="slider"></div>';
            $mform->addElement('html',$slider);
        }
        */

        $submitlabel = $isediting
            ? get_string('savecommentchanges', 'block_exaport')
            : get_string('add');
        $this->add_action_buttons(false, $submitlabel);

    }

}

class block_exaport_item_edit_form extends block_exaport_moodleform {

    public function definition() {
        global $CFG, $USER, $DB, $PAGE;

        $type = $this->_customdata['type'];

        $mform = &$this->_form;

        $pendingeditor = !empty($this->_customdata['pendingeditor']);
        // Assignment import keeps its own submission flow; the normal editor owns pending blocks.
        $sections = ['mainsettings' => true, 'content' => false, 'competences' => false];
        foreach ($this->_customdata['itemeditsections'] ?? [] as $section => $visible) {
            if ($section !== 'mainsettings' && array_key_exists($section, $sections)) {
                $sections[$section] = (bool)$visible;
            }
        }

        $mform->addElement('header', 'mainsettings', get_string('mainsettings', 'block_exaport'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', '');

        $mform->addElement('text', 'name', get_string("title", "block_exaport"), 'maxlength="255" size="60"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string("titlenotemtpy", "block_exaport"), 'required', null, 'client');
        $mform->add_exaport_help_button('name', 'forms.item.title');

        // Category selection uses multi-value, synced to block_exaportitemcate relation table.
        $mform->addElement('autocomplete', 'categoryids', get_string("category", "block_exaport"), array(), ['multiple' => true]);
        $mform->setType('categoryids', PARAM_SEQUENCE);
        $mform->setDefault('categoryids', []);
        $this->category_select_setup($this->_customdata['cattype'], $this->_customdata['catid']);
        $mform->add_exaport_help_button('categoryids', 'forms.item.categoryid');


        // Assignment import still needs its source identifiers; its files are saved as structured content.
        if ($this->_customdata['action'] == 'assignment_import') {
            foreach (['submissionid' => PARAM_INT, 'fileid' => PARAM_TEXT, 'nosubmission' => PARAM_INT,
                    'onlinetext' => PARAM_INT, 'aid' => PARAM_INT] as $field => $paramtype) {
                $mform->addElement('hidden', $field);
                $mform->setType($field, $paramtype);
            }
        } else if (empty($this->_customdata['current']) && !$pendingeditor) {
            $mform->addElement('static', 'contentaftersave', '', get_string('contentaftersave', 'block_exaport'));
        }

        if (block_exaport_course_has_desp()) {
            $langcode = get_string("langcode", "block_desp");

            $sql = "SELECT lang.id,lang." . $langcode .
                " as name FROM {block_desp_lang} lang WHERE id IN(SELECT langid FROM {block_desp_check_lang} " .
                " WHERE userid=?) OR id IN (SELECT langid FROM {block_desp_lanhistories} WHERE userid=?) ORDER BY lang." .
                $langcode;
            $languages = $DB->get_records_sql_menu($sql, array($USER->id, $USER->id));

            $languages[0] = '';

            asort($languages);
            $mform->addElement('select', 'langid', get_string("desp_language", "block_exaport"), $languages);
            $mform->setType('langid', PARAM_INT);
            $mform->add_exaport_help_button('langid', 'forms.item.langid');
        }

        $textareafields = [ // Field name => string marker.
            'intro' => 'shortdescription',
        ];
        $usetextareas = @$this->_customdata['useTextareas'] ?: [];
        foreach ($textareafields as $textareafield => $stringmarker) {
            if (isset($usetextareas[$textareafield]) && $usetextareas[$textareafield]) {
                // It has iframe, show textfield, no editor.
                $mform->addElement('textarea', $textareafield, get_string($stringmarker, 'block_exaport'), 'rows="20" cols="50" style="width: 95%"');
                $mform->setType($textareafield, PARAM_RAW);
                // Required for all item types
                //                if ($type == 'note' && $textareafield == 'intro') {
                // Intro field is now optional - validation rule removed
                //                }
                $mform->add_exaport_help_button($textareafield, 'forms.item.' . $textareafield);
            } else {
                if (!isset($this->_customdata['textfieldoptions'])) {
                    $this->_customdata['textfieldoptions'] = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99,
                        'context' => context_user::instance($USER->id));
                }
                $mform->addElement('editor', $textareafield . '_editor', get_string($stringmarker, 'block_exaport'), null,
                    $this->_customdata['textfieldoptions']);
                $mform->setType($textareafield . '_editor', PARAM_RAW);
                // Intro field is now optional - validation rule removed
                $mform->add_exaport_help_button($textareafield . '_editor', 'forms.item.' . $textareafield . '_editor');
            }
        }

        $mform->addElement('filemanager', 'iconfile', get_string('iconfile', 'block_exaport'), null,
            array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
                'accepted_types' => array('image', 'web_image')));
        $mform->add_exaport_help_button('iconfile', 'forms.item.iconfile');

        if ($sections['content']) {
            $mform->addElement('header', 'itemcontent', get_string('viewcontent', 'block_exaport'));
            $mform->setExpanded('itemcontent', true);

            if ($pendingeditor) {
                $initialstate = $this->_customdata['editorstate'];
                $statejson = $this->optional_param('contentblocks', json_encode($initialstate), PARAM_RAW);
                foreach (['pendingeditor' => 1, 'competencesmodified' => 0] as $name => $default) {
                    $mform->addElement('hidden', $name, $default);
                    $mform->setType($name, PARAM_BOOL);
                }
                foreach (['contentblocks' => json_encode($initialstate),
                        'competenceids' => implode(',', $this->initial_competenceids())] as $name => $default) {
                    $mform->addElement('hidden', $name, $default);
                    $mform->setType($name, PARAM_RAW);
                }
                try {
                    $state = \block_exaport\local\item_editor::display_state($statejson,
                        (int)($this->_customdata['current']->id ?? 0), (int)$this->_customdata['course']->id,
                        empty($this->_customdata['allowedit']));
                } catch (\moodle_exception $exception) {
                    // Keep invalid submitted values for validation, but never render untrusted block data.
                    $state = $initialstate;
                }
                $html = \block_exaport\output\pending_item_content_blocks::render_section($state,
                    $this->_customdata['current'] ?: null, (int)$this->_customdata['course']->id,
                    !empty($this->_customdata['allowedit']));
                $mform->addElement('static', 'editorcontent', '', $html);
                $mform->getElement('editorcontent')->updateAttributes(['class' => 'w-100']);
                $mform->addElement('static', 'editorpendinghint', '', get_string('itemeditorpendinghint', 'block_exaport'));
            } else {
                $contentblocks = $this->_customdata['itemcontentblocks'];
                $contentaddurl = $this->_customdata['itemcontentaddurl'] ?? null;
                $contentownerid = (int)($this->_customdata['itemcontentownerid'] ?? $USER->id);
                $contentsection = new \block_exaport\output\item_content_blocks(
                    $contentblocks,
                    $contentaddurl,
                    $contentownerid,
                    true,
                    false
                );
                $mform->addElement('html', $PAGE->get_renderer('block_exaport')->render($contentsection));
            }
        }

        $mform->addElement('header', 'itemmetadata', get_string('metadata', 'block_exaport'));

        $textareafields = [
            'project_description' => 'project_description',
            'project_process' => 'project_process',
            'project_result' => 'project_result',
        ];
        foreach ($textareafields as $textareafield => $stringmarker) {
            if (isset($usetextareas[$textareafield]) && $usetextareas[$textareafield]) {
                // It has iframe, show textfield, no editor.
                $mform->addElement('textarea', $textareafield, get_string($stringmarker, 'block_exaport'),
                    'rows="20" cols="50" style="width: 95%"');
                $mform->setType($textareafield, PARAM_RAW);
                $mform->add_exaport_help_button($textareafield, 'forms.item.' . $textareafield);
            } else {
                if (!isset($this->_customdata['textfieldoptions'])) {
                    $this->_customdata['textfieldoptions'] = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99,
                        'context' => context_user::instance($USER->id));
                }
                $mform->addElement('editor', $textareafield . '_editor', get_string($stringmarker, 'block_exaport'), null,
                    $this->_customdata['textfieldoptions']);
                $mform->setType($textareafield . '_editor', PARAM_RAW);
                $mform->add_exaport_help_button($textareafield . '_editor', 'forms.item.' . $textareafield . '_editor');
            }
        }

        // Sharing (direct item share: users and groups/cohorts).
        if (has_capability('block/exaport:shareintern', context_system::instance())) {
            global $OUTPUT;
            $mform->addElement('header', 'itemsharing', get_string('share', 'block_exaport'));
            $itemid = (int)($this->_customdata['current']->id ?? 0);
            $item = $itemid > 0 ? $DB->get_record('block_exaportitem', ['id' => $itemid, 'userid' => $USER->id]) : null;
            $itemshareall = $item ? (int)$item->shareall : 0;
            $hasusers = $itemid > 0 && $DB->record_exists('block_exaportitemshar', ['itemid' => $itemid]);
            $hasgroups = $itemid > 0 && $DB->record_exists('block_exaportitemgroupshar', ['itemid' => $itemid]);
            $shareenabled = $itemshareall > 0 || $hasusers || $hasgroups;
            $shareconfigured = $shareenabled;
            $submittedsharing = null;
            // Start with saved settings. After a rejected submit, redisplay the user's
            // submitted choices instead; this changes the form, not the stored shares.
            $sharing = $this->submitted_sharing();
            if ($sharing !== null) {
                $shareenabled = $sharing['enabled'];
                $itemshareall = $sharing['mode'];
                $submittedsharing = json_encode([
                    'users' => $sharing['users'], 'notify' => $sharing['notify'], 'groups' => $sharing['groups'],
                    'courses' => $sharing['users'] === null ? null : block_exaport_get_sharing_userlist(
                        'item', $itemid, $sharing['users'], $sharing['notify']),
                ], JSON_THROW_ON_ERROR);
            }
            $sharedusers = $itemid > 0 ? $DB->get_records_menu('block_exaportitemshar',
                ['itemid' => $itemid], null, 'userid, userid AS tmp') : [];
            $mform->addElement('html', '<script>var sharedusersarr = ' . json_encode(array_values($sharedusers)) . ';</script>');
            $sharingform = new \block_exaport\output\sharing_form([
                'componentid' => 'item-sharing',
                'enabled' => $shareenabled,
                'configured' => $shareconfigured,
                'showinternal' => true,
                'showeveryone' => block_exaport_shareall_enabled(),
                'mode' => $itemshareall,
                'alwaysnotify' => (bool)get_config('block_exaport', 'alwaysnotifywhenshare'),
                'submittedsharing' => $submittedsharing,
                'submittedgroupsknown' => $sharing !== null && $sharing['groups'] !== null,
                'submittedgroups' => $sharing['groups'] ?? [],
            ]);
            $sharinghtml = $OUTPUT->render_from_template(
                'block_exaport/sharing_form',
                $sharingform->export_for_template($OUTPUT)
            );
            $mform->addElement('html', $sharinghtml);
            $PAGE->requires->js_call_amd('block_exaport/sharing_form', 'init', ['cat_mod']);
        }

        // Tags.
        if (!empty($CFG->usetags) && $CFG->usetags) {
            $mform->addElement('header', 'itemtags', get_string('tags'));
            $tags = \core_tag_tag::get_tags_by_area_in_contexts('block_exaport', 'block_exaportitem', [context_user::instance($USER->id)]);
            $tagstrings = [];
            foreach ($tags as $tag) {
                $tagstrings[$tag->name] = $tag->name;
            }
            $showstandard = core_tag_area::get_showstandard('block_exaport', 'block_exaportitem');
            if ($showstandard != core_tag_tag::HIDE_STANDARD) {
                $namefield = empty($CFG->keeptagnamecase) ? 'name' : 'rawname';
                $standardtags = $DB->get_records('tag',
                    array(
                        'isstandard' => 1,
                        'tagcollid' => core_tag_area::get_collection('block_exaport', 'block_exaportitem'),
                    ),
                    $namefield,
                    'id,' . $namefield
                );
                foreach ($standardtags as $standardtag) {
                    $tagstrings[$standardtag->$namefield] = $standardtag->$namefield;
                }
            }
            $options = [
                'tags' => true,
                'multiple' => true,
            ];
            $mform->addElement('autocomplete', 'tags', get_string('tags'), $tagstrings, $options);
            $mform->add_exaport_help_button('tags', 'forms.item.tags');
        }

        // The modal edits a selection; the normal editor submits that selection with all other fields.
        if ($sections['competences']) {
            $mform->addElement('header', 'itemcompetences', get_string('competencessection', 'block_exaport'));
            $competenceitem = $this->_customdata['current'] ? clone $this->_customdata['current'] : (object)['id' => 0];
            if ($pendingeditor) {
                try {
                    $competenceitem->compids_array = block_exaport_parse_competenceids(
                        $this->optional_param('competenceids', implode(',', $this->initial_competenceids()), PARAM_RAW));
                } catch (\moodle_exception $exception) {
                    $competenceitem->compids_array = $this->initial_competenceids();
                }
            }
            $competences = new \block_exaport\output\item_competences(
                $competenceitem,
                !empty($this->_customdata['allowedit'])
            );
            $mform->addElement(
                'static',
                'competencesummary',
                get_string('selectcomps', 'block_exaport'),
                $PAGE->get_renderer('block_exaport')->render($competences)
            );
        }

        if (!empty($this->_customdata['allowedit']) || empty($this->_customdata['current'])) {
            $buttonarray = [];
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('saveitem', 'block_exaport'));
            $buttonarray[] = $mform->createElement(
                'submit',
                'saveandkeepediting',
                get_string('saveandkeepediting', 'block_exaport')
            );
            $buttonarray[] = $mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            $exampleid = $DB->get_field(BLOCK_EXACOMP_DB_ITEM_MM,
                'exacomp_record_id',
                ['itemid' => $this->_customdata['current']->id, 'competence_type' => BLOCK_EXACOMP_TYPE_EXAMPLE]);

            $mform->addElement('hidden', 'allowedit');
            $mform->setType('allowedit', PARAM_INT);
            $mform->setDefault('allowedit', 0);

            $mform->disabledIf('name', 'allowedit', 'neq', 1);
            $mform->disabledIf('categoryids', 'allowedit', 'neq', 1);
            $mform->disabledIf('intro', 'allowedit', 'neq', 1);
            $mform->disabledIf('intro_editor', 'allowedit', 'neq', 1);
            $mform->disabledIf('iconfile', 'allowedit', 'neq', 1);

            if ($exampleid && !empty($this->_customdata['allowresubmission'])) {
                $url = new moodle_url('/blocks/exacomp/example_submission.php',
                    ['courseid' => $this->_customdata['course']->id, 'newsubmission' => true, 'exampleid' => $exampleid]);
                $mform->addElement('button', 'newsubmission', get_string("newsubmission", "block_exacomp"),
                    array('onclick' => 'location.href = " ' . str_replace("&amp;", "&", $url) . '"'));
            } else {
                $mform->addElement('html', get_string("isgraded", "block_exacomp"));
            }
        }
    }

    /** Restore only this submitted form; unloaded pickers are unknown, not empty audiences. */
    private function submitted_sharing(): ?array {
        $source = $this->_ajaxformdata ?: $_POST;
        if (($source['_qf__' . $this->_formname] ?? 0) != 1 ||
                !confirm_sesskey($source['sesskey'] ?? '')) {
            return null;
        }
        $ids = static function(string $name) use ($source): array {
            $values = \block_exaport\param::clean_array($source[$name] ?? [], PARAM_INT);
            return array_values(array_unique(array_filter($values, static function(int $id): bool { return $id > 0; })));
        };
        $mode = clean_param($source['shareall'] ?? 0, PARAM_INT);
        $users = clean_param($source['exaport_userlist_ready'] ?? 0, PARAM_INT) === 1 ? $ids('shareusers') : null;
        return ['enabled' => (bool)clean_param($source['shareenabled'] ?? 0, PARAM_BOOL),
            'mode' => in_array($mode, [0, 1, 2], true) ? $mode : 0, 'users' => $users,
            'notify' => $users === null ? null : array_values(array_intersect($ids('notifyusers'), $users)),
            'groups' => clean_param($source['exaport_grouplist_ready'] ?? 0, PARAM_INT) === 1 ? $ids('sharegroups') : null];
    }

    /** Return trusted persisted selections without creating an item for the new-item form. */
    private function initial_competenceids(): array {
        global $DB;
        if (empty($this->_customdata['current']) || empty($this->_customdata['itemeditsections']['competences'])) {
            return [];
        }
        return block_exaport_normalize_competenceids($DB->get_fieldset_select(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            'compid', 'activityid = ? AND eportfolioitem = 1', [(int)$this->_customdata['current']->id]));
    }

    /** Share the modal validators, while treating the final POST as a new trust boundary. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['pendingeditor'])) {
            return $errors;
        }
        try {
            // Read the actual POST: a missing collection must not fall back to an empty/default collection.
            if (!empty($this->_customdata['itemeditsections']['competences']) &&
                    $this->optional_param('competenceids', null, PARAM_RAW) === null) {
                throw new \moodle_exception('itemeditorinvalid', 'block_exaport');
            }
            \block_exaport\local\item_editor::validate_request(
                (int)($this->_customdata['current']->id ?? 0), (int)$this->_customdata['course']->id,
                $this->optional_param('contentblocks', '', PARAM_RAW),
                $this->optional_param('competenceids', '', PARAM_RAW), $data,
                !empty($this->_customdata['itemeditsections']['competences']));
        } catch (\moodle_exception $exception) {
            $errors['editorcontent'] = $exception->getMessage();
        }
        return $errors;
    }

    /** Display a save error without losing any submitted fields. */
    public function set_editor_error(string $message): void {
        $this->_form->setElementError('editorcontent', $message);
    }

    public function category_select_setup($categorytype = '', $selectedcat = 0) {
        global $CFG, $USER, $DB;
        $mform = &$this->_form;
        $categorysselect = &$mform->getElement('categoryids');
        $categorysselect->removeOptions();

        if ($categorytype == 'shared') {
            // only shared categories
            $sharedcatids = [];
            $sharedcategories = \block_exaport\get_categories_shared_to_user($USER->id);
            if ($sharedcategories) {
                foreach ($sharedcategories as $shcat) {
                    $sharedcatids = array_merge($sharedcatids, array_keys($shcat->categories));
                }
            }
            if (in_array($selectedcat, $sharedcatids)) {
                $conditions = array($selectedcat);
                $outercategories = $DB->get_records_select("block_exaportcate", "id = ?", $conditions, "name asc");
            } else {
                $outercategories = null;
            }
        } else {
            // only MY categories
            $conditions = array("userid" => $USER->id, "pid" => 0);
            $outercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
        }
        $categories = array(
            0 => block_exaport_get_root_category()->name,
        );
        if ($outercategories) {
            $categories = $categories + rek_category_select_setup($outercategories, " ", $categories);
        }
        $categorysselect->loadArray($categories);
    }

}

function rek_category_select_setup($outercategories, $entryname, $categories) {
    global $DB, $USER;
    foreach ($outercategories as $curcategory) {
        $categories[$curcategory->id] = $entryname . format_string($curcategory->name);
        $name = $entryname . format_string($curcategory->name);

        $conditions = array("userid" => $USER->id, "pid" => $curcategory->id);
        $innercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
        if ($innercategories) {
            $categories = rek_category_select_setup($innercategories, $name . ' &rarr; ', $categories);
        }
    }
    return $categories;
}
