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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

class blockedit {
    static function load_form(int $id, string $type = ''): object {
        global $DB, $USER;

        $blockdata = (object)[];
        if ($id > 0) {
            $blockdata = $DB->get_record_sql("SELECT b.*
            FROM {block_exaportviewblock} b
            JOIN {block_exaportview} v ON v.id = b.viewid
            WHERE b.id = ? AND v.userid = ?", [$id, $USER->id]);
            if (!$blockdata) {
                throw new \moodle_exception('viewnotfound', 'block_exaport');
            }
            $type = $blockdata->type;
        }

        $html = '';
        switch ($type) {
            case 'item':
                $html = self::get_form_items($id, $blockdata);
                break;
            case 'personal_information':
                $html = self::get_form_personalinfo($id, $blockdata);
                break;
            case 'cv_information':
                $html = self::get_form_cvinfo($id, $blockdata);
                break;
            case 'text':
                $html = self::get_form_text($id, $blockdata);
                break;
            case 'headline':
                $html = self::get_form_headline($id, $blockdata);
                break;
            case 'media':
                $html = self::get_form_media($id, $blockdata);
                break;
            case 'badge':
                $html = self::get_form_badge($id, $blockdata);
                break;
            default:
                throw new \moodle_exception('Unknown block type: ' . $type);
        }

        return (object)[
            'type' => $type,
            'title' => get_string('configureblock_' . $type, 'block_exaport'),
            'html' => $html,
        ];
    }

    static function action_buttons($add_type = 'add_text'): string {
        static $cssdone = false;
        $out = '';
        if (!$cssdone) {
            $out .= '<style>
            .exaport-block-form-actions .btn-toolbar{display:flex;flex-wrap:wrap;gap:.5rem}
            .exaport-block-form-actions .btn{min-width:140px}
        </style>';
            $cssdone = true;
        }
        $out .= '<tr><td class="exaport-block-form-actions">' .
            '<div class="btn-toolbar" role="group" aria-label="Actions">' .
            '<button type="submit" id="' . $add_type . '" name="submit_block" class="btn btn-primary">' . get_string('saveViewButton', 'block_exaport') . '</button>' .
            '<button type="submit" id="' . $add_type . '_notify" name="submit_block_and_notify" class="btn btn-primary">' . get_string('saveAndNotifyButton', 'block_exaport') . '</button>' .
            '<button type="button" id="cancel_list" name="cancel" class="btn btn-secondary">' . get_string('cancelButton', 'block_exaport') . '</button>' .
            '</div></td></tr>';
        return $out;
    }

    static function get_form_items($id, $blockdata = []) {
        global $DB, $USER, $CFG;

        // Read all categories.
        $categories = $DB->get_records_sql('
        SELECT c.id, c.name, c.pid, COUNT(DISTINCT i.id) AS item_cnt
        FROM {block_exaportcate} c
        LEFT JOIN {block_exaportitemcate} ic ON ic.cateid = c.id
        LEFT JOIN {block_exaportitem} i ON i.id = ic.itemid AND ' . block_exaport_get_item_where() . '
        WHERE c.userid = ?
        GROUP BY c.id
        ORDER BY c.name ASC
    ', array($USER->id));

        // Build a tree according to parent.
        $categoriesbyparent = array();
        foreach ($categories as $category) {
            if (!isset($categoriesbyparent[$category->pid])) {
                $categoriesbyparent[$category->pid] = array();
            }
            $categoriesbyparent[$category->pid][] = $category;
        }

        // The main root category.
        $rootcategory = block_exaport_get_root_category();
        $categories[0] = $rootcategory;

        $items = $DB->get_records_sql("
            SELECT i.id, i.name, i.type, COUNT(com.id) As comments
            FROM {block_exaportitem} i
            LEFT JOIN {block_exaportitemcomm} com on com.itemid = i.id
            WHERE i.userid = ? AND " . block_exaport_get_item_where() . "
            GROUP BY i.id, i.name, i.type
            ORDER BY i.name
        ", array($USER->id));

        $itemsbycategory = array();
        $itemidlist = array();
        // Build item-to-category mapping from itemcate table.
        $itemids = array_keys($items);
        $itemcatemapping = [];
        if ($itemids) {
            [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_QM);
            $itemcaterows = $DB->get_records_sql("SELECT id, itemid, cateid FROM {block_exaportitemcate} WHERE itemid $insql", $inparams);
            foreach ($itemcaterows as $row) {
                // Use the first category found for display purposes.
                if (!isset($itemcatemapping[$row->itemid])) {
                    $itemcatemapping[$row->itemid] = (int)$row->cateid;
                }
            }
        }
        // Save items to category.
        foreach ($items as $item) {
            $catid = $itemcatemapping[$item->id] ?? 0;
            if (empty($itemsbycategory[$catid])) {
                $itemsbycategory[$catid] = array();
            }
            $item->tags = block_exaport_get_item_tags($item->id);
            $itemsbycategory[$catid][] = $item;
            $itemidlist[] = $item->id;
        }

        $content = "";
        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        // Add new artefact button
        $content .= '<tr><td>';
        $content .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/item.php?courseid=1&action=add&type=mixed" target="_blank" class="exaport_add_artefact"><img src="pix/mixed_new_32.png" width="24"/>&nbsp;' . get_string("add_mixed", "block_exaport") . '</a><br>';
        $content .= 'To category: &nbsp;';
        $categories = block_exaport_get_all_categories_for_user_simpletree_selectbox($USER->id, 'categoryForNewItem', 'categoryForNewItem');
        $content .= $categories;
        $content .= '<hr width="95%" style="margin: 3px auto;">';
        $content .= '</td></tr>';
        // Filter by tag.
        $content .= '<tr><th>';
        $content .= '<label for="list">' . get_string('listofartefacts', 'block_exaport') . '</label>';
        $usertags = block_exaport_get_item_tags($itemidlist, 'rawname');
        if (count($usertags) > 0) {
            $content .= '<select class="tagfilter" onChange="exaportViewEdit.filterItemsByTag();">';
            $content .= '<option value="">' . get_string('filterByTag', 'block_exaport') . '</option>';
            foreach ($usertags as $tagname) {
                $content .= '<option value="' . $tagname . '">' . $tagname . '</option>';
            };
            $content .= '</select>';
        };

        // Search by title.
        $content .= ' <input type="text" id="filterByTitle" placeholder="' . get_string('searchByTitle', 'block_exaport') . '">';
        // Clear all filters.
        $content .= ' <img id="clearAllFilters" src="' . $CFG->wwwroot . '/blocks/exaport/pix/clearfilters.png" ' .
            ' title="' . get_string('clearAllFilers', 'block_exaport') . '" onClick="exaportViewEdit.clearItemFilters();">';
        $content .= '</th></tr>';
        $content .= '<tr><td>';

        ob_start();
        ?>
        <div id="add-items-list">
            <?php
            echo self::print_categories_recursive($rootcategory, $categoriesbyparent, $itemsbycategory);
            ?>
        </div>
        <script type="text/javascript">
            exaportViewEdit.initAddItems();
        </script>
        <?php
        $content .= ob_get_clean();

        $content .= '</td></tr>';
        // Shared artefacts for this user.
        $sharedartefacts = block_exaport_get_items_shared_to_user($USER->id);
        if (count($sharedartefacts) > 0) {
            $content .= '<tr class="sharedArtefacts"><td><hr width=95% style="margin: 3px auto;">';
            $content .= get_string('sharedArtefacts', 'block_exaport');
            $content .= '</td></tr>';
            $content .= '<tr class="sharedArtefacts"><td>';
            foreach ($sharedartefacts as $key => $user) {
                $content .= '<div class="add-item-category" data-category="sharedFromUser">' . $user['fullname'] . '</div>';
                if (isset($user['items']) && is_array($user['items']) && count($user['items']) > 0) {
                    foreach ($user['items'] as $itemid => $item) {
                        $item->tags = block_exaport_get_item_tags($item->id);
                        $tags = '';
                        // JSON_UNESCAPED_UNICODE --> PHP 5.4.
                        if (count($item->tags) > 0) {
                            $tags = 'data-tags=\'' . json_encode($item->tags, JSON_UNESCAPED_UNICODE) . '\'';
                        }
                        $content .= '<div class="add-item" ' . $tags . ' data-category="sharedFromUser">';
                        $content .= '<input class="add-item-checkbox" type="checkbox" name="add_items[]" ' .
                            ' value="' . $item->id . '" /> ';
                        $content .= $item->name;
                        $content .= '</div>';
                    }
                }
            };
            $content .= '</td></tr>';
        }
        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';
        $content .= '</form>';

        return $content;
    }

    private static function print_categories_recursive($category, $categoriesbyparent, $itemsbycategory) {

        $subcontent = '';
        if (isset($categoriesbyparent[$category->id])) {
            foreach ($categoriesbyparent[$category->id] as $subcategory) {
                $subcontent .= self::print_categories_recursive($subcategory,
                    $categoriesbyparent, $itemsbycategory);
            }
        }

        if (!$subcontent && empty($itemsbycategory[$category->id])) {
            // No subcontent and no items.
            return '';
        }

        $content = '';

        if (($category->id > 0) && ($category->pid > 0)) {
            $content .= '<div class="add-item-sub">';
        }

        $content .= '<div class="add-item-category" data-category="' . $category->id . '">' . $category->name . '</div>';

        if (!empty($itemsbycategory[$category->id])) {
            foreach ($itemsbycategory[$category->id] as $item) {
                $tags = '';
                // JSON_UNESCAPED_UNICODE --> PHP 5.4.
                if (count($item->tags) > 0) {
                    $tags = 'data-tags=\'' . json_encode($item->tags, JSON_UNESCAPED_UNICODE) . '\'';
                }
                $content .= '<div class="add-item" ' . $tags . ' data-category="' . $category->id . '">';
                $content .= '<input class="add-item-checkbox" type="checkbox" name="add_items[]" value="' . $item->id . '" /> ';
                $content .= $item->name;
                $content .= '</div>';
            }
        }

        $content .= $subcontent;

        if (($category->id > 0) && ($category->pid > 0)) {
            $content .= '</div>';
        }

        return $content;
    }

    static function get_form_text($id, $blockdata = []) {
        global $CFG, $PAGE, $USER;

        if ($id && isset($blockdata->text) && $blockdata->text) {
            $draftideditor = file_get_submitted_draft_itemid('text');
            $text = file_rewrite_pluginfile_urls($blockdata->text,
                'draftfile.php',
                \context_user::instance($USER->id)->id,
                'user',
                'draft',
                $draftideditor);
        } else {
            $text = '';
        }

        $content = "";
        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        $content .= '<tr><th>';
        $content .= '<label for="block_title">' . get_string('blocktitle2', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<input type="text" name="block_title" value="' .
            s(isset($blockdata->block_title) && $blockdata->block_title ? $blockdata->block_title : "") .
            '" id="block_title">';
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $content .= '<label for="text">' . get_string('blockcontent', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<textarea tabindex="1" style="height: 400px; width: 100%;" name="text" id="id_block_text" ' .
            ' cols="10" rows="20">' . $text . '</textarea>';
        $content .= '</td></tr>';
        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';
        $content .= '</form>';

        return $content;
    }

    static function get_form_headline($id, $blockdata = []) {
        $content = "";
        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        $content .= '<tr><th>';
        $content .= '<label for="headline">' . get_string('view_specialitem_headline', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<input name="headline" id="headline" type="text" value="' .
            s(isset($blockdata->text) && $blockdata->text ? $blockdata->text : "") .
            '" default-text="' . get_string('view_specialitem_headline_defaulttext', 'block_exaport') . '" /></div>';
        $content .= '<div for="headline" class="not-empty-check">' . block_exaport_get_string('titlenotemtpy') . '</div>';
        $content .= '</td></tr>';
        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';
        $content .= '</form>';

        return $content;
    }

    static function get_form_personalinfo($id, $blockdata = []) {
        global $OUTPUT, $DB, $USER, $PAGE, $CFG;

        if ($USER->picture) {
            $userpicture = new \user_picture($USER);
            $userpicture->size = 1;
            $picturesrc = $userpicture->get_url($PAGE);
            $userpicture->size = 2;
            $picturesrcsmall = $userpicture->get_url($PAGE);
        };

        //    $draftideditor = file_get_submitted_draft_itemid('text');
        $draftideditor = 0; // disable draft for this editor
        // attodraftid - we need to disable draft functionality for ATTO editor. It is not possible by settings (or?...)
        // so - generate random draftid (use as contextId in atto table) for simulate new records for every request
        $attoDraftContextId = random_int(10000, 99999999);

        if (isset($blockdata->text) && $blockdata->text) {
            // block data (text) can be changed manually for the block
            $text = $blockdata->text;

            /*        $text = file_prepare_draft_area(
                            $draftideditor,
                            context_user::instance($USER->id)->id,
                            'block_exaport',
                            'view_content',
                            required_param('viewid', PARAM_INT),
                            array('subdirs' => true, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size),
                            $text
                    );*/
        } else {
            // Or the block data can be a default value from 'About me' CV section
            require_once(__DIR__ . '/../lib/resumelib.php');
            $resumedata = block_exaport_get_resume_params_record();
            $text = @$resumedata->cover ?: '';

            $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php',
                \context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_cover', $resumedata->id);
            $text = block_exaport_add_view_access_parameter_to_url($text, 'resume/' . $resumedata->id . '/' . $USER->id, ['src']);
            $text = file_prepare_draft_area(
                $draftideditor,
                $attoDraftContextId, //context_user::instance($USER->id)->id,
                'block_exaport',
                'resume_editor_cover',
                $USER->id,
                array('subdirs' => true, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size), $text);
        }
        $content = "";

        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        $content .= '<tr><th>';
        $content .= '<label>' . get_string('fieldstoshow', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<input type="checkbox" name="fields[firstname]" id="firstname" value="' . $USER->firstname . '" ' .
            (isset($blockdata->firstname) && $blockdata->firstname == $USER->firstname ? 'checked="checked"' : '') .
            '> ' . get_string('firstname', 'block_exaport') . '</input><br>';
        $content .= '<input type="checkbox" name="fields[lastname]" id="lastname" value="' . $USER->lastname . '" ' .
            (isset($blockdata->lastname) && $blockdata->lastname == $USER->lastname ? 'checked="checked"' : '') . '> ' .
            get_string('lastname', 'block_exaport') . '</input>';
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $content .= '<label>' . get_string('profilepicture', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        if ($USER->picture) {
            $content .= '<input type="radio" name="picture" value="" ' .
                (isset($blockdata->picture) && $blockdata->picture == $picturesrc ? '' : 'checked="checked"') . '> ' .
                get_string('nopicture', 'block_exaport') . '</input><br>';
            $content .= '<input type="radio" name="picture" value="' . $picturesrc . '" ' .
                (isset($blockdata->picture) && $blockdata->picture == $picturesrc ? 'checked="checked"' : '') . '> ' .
                '<img src="' . $userpicture->get_url($PAGE) . '"></input>';
        } else {
            $content .= get_string('noprofilepicture', 'block_exaport');
        }
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $content .= '<label>' . get_string('mailadress', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        if ($USER->email) {
            $content .= '<input type="radio" name="email" value="" ' .
                (isset($blockdata->email) && $blockdata->email == $USER->email ? '' : 'checked="checked"') . '> ' .
                get_string('nomail', 'block_exaport') . '</input><br>';
            $content .= '<input type="radio" name="email" value="' . $USER->email . '" ' .
                (isset($blockdata->email) && $blockdata->email == $USER->email ? 'checked="checked"' : '') . '> ' .
                $USER->email . '</input>';
        } else {
            $content .= get_string('noemails', 'block_exaport');
        }
        $content .= '</td></tr>';

        $content .= '<tr><th>';
        $content .= '<label for="text">' . get_string('aboutme', 'block_exaport') . '<br>';
        $content .= '<small>' . get_string('aboutme_description', 'block_exaport') . '</small></label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<textarea tabindex="1" style="height: 400px; width: 100%;" name="text" id="id_block_text" ' .
            ' cols="10" rows="20">' . s($text) . '</textarea>';
        $content .= '</td></tr>';
        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';

        return $content;
    }

    static function get_form_cvinfo($id, $blockdata = []) {
        global $OUTPUT, $DB, $USER, $PAGE, $CFG;

        require_once(__DIR__ . '/../lib/resumelib.php');
        $resume = block_exaport_get_resume_params();

        $output = block_exaport_get_renderer();

        $content = "";

        $content .= '<p><img src="' . $output->image_url('help', 'block_exaport') . '" class="icon" alt="" />' . block_exaport_get_string('cofigureblock_cvinfo_help') . '</p>';

        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        // with files
        $content .= '<tr><th>';
        $content .= '<label>';
        $content .= '<input class="add-checkbox" type="checkbox" name="add_withfiles" value="1" /> ';
        $content .= get_string('cofigureblock_cvinfo_withfiles', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        // group by category
        $content .= '<tr><th>';
        $content .= '<label>';
        $content .= '<input class="add-checkbox" type="checkbox" name="category_grouping" value="1" /> ';
        $content .= get_string('configureblock_cvinfo_group_by_category', 'block_exaport') . '</label>';
        $content .= '</th></tr>';

        // educations
        $usereducaitons = block_exaport_resume_get_educations(@$resume->id);
        if ($usereducaitons) {
            $content .= '<tr><th>';
            $content .= '<label>' . get_string('cofigureblock_cvinfo_education_history', 'block_exaport') . '</label>';
            $content .= '</th></tr>';
            $content .= '<tr><td>';
            foreach ($usereducaitons as $edu) {
                $content .= '<div class="add-item">';
                $content .= '<label>';
                $content .= '<input class="add-cvitem-checkbox" data-cvtype="edu" type="checkbox" name="add_edu_items[]" value="' . $edu->id . '" /> ';
                $position = $edu->qualname;
                if ($position) {
                    $position .= ' (' . $edu->qualtype . ')';
                } else {
                    $position .= $edu->qualtype;
                }
                if ($position) {
                    $position .= ' ' . get_string('in', 'block_exaport') . ' ';
                }
                $content .= $position . $edu->institution . ($edu->startdate || $edu->enddate ? ' (' . $edu->startdate . ($edu->enddate ? ' - ' . $edu->enddate : '') . ')' : '');
                $content .= '</label>';
                $content .= '</div>';
            }
            $content .= '</td></tr>';
        }
        // employments
        $useremployments = block_exaport_resume_get_employments(@$resume->id);
        if ($useremployments) {
            $content .= '<tr><th>';
            $content .= '<label>' . get_string('cofigureblock_cvinfo_employment_history', 'block_exaport') . '</label>';
            $content .= '</th></tr>';
            $content .= '<tr><td>';
            foreach ($useremployments as $employ) {
                $content .= '<div class="add-item">';
                $content .= '<label>';
                $content .= '<input class="add-cvitem-checkbox" data-cvtype="employ" type="checkbox" name="add_employ_items[]" value="' . $employ->id . '" /> ';
                $content .= $employ->jobtitle . ':' . $employ->employer . ($employ->startdate || $employ->enddate ? ' (' . $employ->startdate . ($employ->enddate ? ' - ' . $employ->enddate : '') . ')' : '');
                $content .= '</label>';
                $content .= '</div>';
            }
            $content .= '</td></tr>';
        }
        // certifications, accreditations and awards
        $usercertifs = block_exaport_resume_get_certificates(@$resume->id);
        if ($usercertifs) {
            $content .= '<tr><th>';
            $content .= '<label>' . get_string('cofigureblock_cvinfo_certif', 'block_exaport') . '</label>';
            $content .= '</th></tr>';
            $content .= '<tr><td>';
            foreach ($usercertifs as $certif) {
                $content .= '<div class="add-item">';
                $content .= '<label>';
                $content .= '<input class="add-cvitem-checkbox" data-cvtype="certif" type="checkbox" name="add_certif_items[]" value="' . $certif->id . '" /> ';
                $content .= $certif->title . ($certif->date ? ' (' . $certif->date . ')' : '');
                $content .= '</label>';
                $content .= '</div>';
            }
            $content .= '</td></tr>';
        }
        // Books and publications
        $userpublics = block_exaport_resume_get_publications(@$resume->id);
        if ($userpublics) {
            $content .= '<tr><th>';
            $content .= '<label>' . get_string('cofigureblock_cvinfo_public', 'block_exaport') . '</label>';
            $content .= '</th></tr>';
            $content .= '<tr><td>';
            foreach ($userpublics as $public) {
                $content .= '<div class="add-item">';
                $content .= '<label>';
                $content .= '<input class="add-cvitem-checkbox" data-cvtype="public" type="checkbox" name="add_public_items[]" value="' . $public->id . '" /> ';
                $content .= $public->title . ($public->date ? ' (' . $public->date . ')' : '');
                $content .= '</label>';
                $content .= '</div>';
            }
            $content .= '</td></tr>';
        }
        // Professional memberships
        $usermbrships = block_exaport_resume_get_profmembershipments(@$resume->id);
        if ($usermbrships) {
            $content .= '<tr><th>';
            $content .= '<label>' . get_string('cofigureblock_cvinfo_mbrship', 'block_exaport') . '</label>';
            $content .= '</th></tr>';
            $content .= '<tr><td>';
            foreach ($usermbrships as $mbrship) {
                $content .= '<div class="add-item">';
                $content .= '<label>';
                $content .= '<input class="add-cvitem-checkbox" data-cvtype="mbrship" type="checkbox" name="add_mbrship_items[]" value="' . $mbrship->id . '" /> ';
                $content .= $mbrship->title . ($mbrship->startdate || $mbrship->enddate ? ' (' . $mbrship->startdate . ($mbrship->enddate ? ' - ' . $mbrship->enddate : '') . ')' : '');
                $content .= '</label>';
                $content .= '</div>';
            }
            $content .= '</td></tr>';
        }
        // My goals
        $content .= '<tr><th>';
        $content .= '<label>' . get_string('cofigureblock_cvinfo_goals', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $goal_types = array('personal', 'academic', 'careers');
        foreach ($goal_types as $goal) {
            $content .= '<div class="add-item">';
            $content .= '<label>';
            $content .= '<input class="add-cvitem-checkbox" data-cvtype="goals' . $goal . '" type="checkbox" name="add_goal_items[]" value="1" /> ';
            $content .= get_string('resume_goals' . $goal, 'block_exaport');
            $content .= '</label>';
            $content .= '</div>';
        }
        $content .= '</td></tr>';
        // My skills
        $content .= '<tr><th>';
        $content .= '<label>' . get_string('cofigureblock_cvinfo_skills', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $skill_types = array('personal', 'academic', 'careers');
        foreach ($skill_types as $skill) {
            $content .= '<div class="add-item">';
            $content .= '<label>';
            $content .= '<input class="add-cvitem-checkbox" data-cvtype="skills' . $skill . '" type="checkbox" name="add_skill_items[]" value="1" /> ';
            $content .= get_string('resume_skills' . $skill, 'block_exaport');
            $content .= '</label>';
            $content .= '</div>';
        }
        $content .= '</td></tr>';
        // Interests
        $content .= '<tr><th>';
        $content .= '<label>';
        $content .= '<input class="add-cvitem-checkbox" data-cvtype="interests" type="checkbox" name="add_interests" value="1" /> ';
        $content .= get_string('cofigureblock_cvinfo_interests', 'block_exaport') . '</label>';
        $content .= '</th></tr>';

        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';

        return $content;
    }

    static function get_form_media($id, $blockdata = []) {
        global $CFG, $PAGE, $USER, $OUTPUT;

        $content = "";
        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        $content .= '<tr><th>';
        $content .= '<label for="block_title">' . get_string('blocktitle2', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<input tabindex="1" type="text" name="block_title" value="' .
            s(isset($blockdata->block_title) && $blockdata->block_title ? $blockdata->block_title : "") .
            '" id="block_title">';
        $content .= '<div for="block_title" class="not-empty-check">' . block_exaport_get_string('titlenotemtpy') . '</div>';
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $helpicon = $OUTPUT->pix_icon('help', get_string('mediacontent', 'block_exaport'), 'moodle', array('class' => 'iconhelp'));
        $content .= '<label for="mediacontent">' . get_string('mediacontent', 'block_exaport') . '</label>
                    &nbsp;<span class="exaport-helpicon"
                            data-toggle="gtn-help-modal"
                            data-title="' . block_exaport_get_string('what_is_embed_code_title') . '"
                            data-content="' . block_exaport_get_string('what_is_embed_code_content') . '">' . $helpicon . '</span>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';
        $content .= '<textarea tabindex="1" style="height: 100px; width: 100%;" name="mediacontent" id="block_media" ' .
            ' cols="10" rows="15" aria-hidden="true">' .
            (isset($blockdata->contentmedia) ? $blockdata->contentmedia : '') .
            '</textarea>';
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $content .= get_string('media_allowed_notes', 'block_exaport');
        $content .= '<br><ul class="inline-list" style="list-style-type: none;">' .
            '<li><a target="_blank" href="http://www.glogster.com/"><img title="Glogster" alt="Glogster" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/glogster.png"></a></li>' .
            '<li><a target="_blank" href="http://video.google.com/"><img title="Google Video" alt="Google Video" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/googlevideo.png"></a></li>' .
            '<li><a target="_blank" href="http://www.prezi.com/"><img title="Prezi" alt="Prezi" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/prezi.png"></a></li>' .
            '<li><a target="_blank" href="http://scivee.tv/"><img title="SciVee" alt="SciVee" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/scivee.png"></a></li>' .
            '<li><a target="_blank" href="http://slideshare.net/"><img title="SlideShare" alt="SlideShare" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/slideshare.png"></a></li>' .
            '<li><a target="_blank" href="http://www.teachertube.com/"><img title="TeacherTube" alt="TeacherTube" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/teachertube.png"></a></li>' .
            '<li><a target="_blank" href="http://vimeo.com/"><img title="Vimeo" alt="Vimeo" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/vimeo.png"></a></li>' .
            '<li><a target="_blank" href="http://www.voicethread.com/"><img title="VoiceThread" alt="VoiceThread" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/voicethread.png"></a></li>' .
            '<li><a target="_blank" href="http://www.voki.com/"><img title="Voki" alt="Voki" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/voki.png"></a></li>' .
            '<li><a target="_blank" href="http://wikieducator.org/"><img title="WikiEducator" alt="WikiEducator" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/wikieducator.png"></a></li>' .
            '<li><a target="_blank" href="http://youtube.com/"><img title="YouTube" alt="YouTube" ' .
            '       src="' . $CFG->wwwroot . '/blocks/exaport/pix/media_sources/youtube.png"></a></li>' .
            '</ul>	';
        $content .= '</th></tr>';
        $content .= '<tr><th>';
        $content .= '<input type="checkbox" tabindex="1" name="create_as_note" id="create_as_note" value="1"' .
            (!$id ? ' checked="checked"' : '') . ' /> ';
        $content .= '<label for="create_as_note">' . block_exaport_get_string('create_as_note') . '</label>';
        $content .= '</td></tr>';
        $content .= '<tr><th>';
        $content .= '<label for="width">' . get_string('width', 'block_exaport') . '</label>';
        $content .= ' <input type="text" tabindex="1" name="width" value="' .
            s(isset($blockdata->width) && $blockdata->width ? $blockdata->width : "") .
            '" id="block_width">';
        $content .= '&nbsp;&nbsp;&nbsp;<label for="height">' . get_string('height', 'block_exaport') . '</label>';
        $content .= ' <input type="text" tabindex="1" name="height" value="' .
            s(isset($blockdata->height) && $blockdata->height ? $blockdata->height : "") .
            '" id="block_height">';
        $content .= '</td></tr>';
        $content .= '<tr><td>';
        $content .= self::action_buttons('add_media');
        $content .= '</td></tr>';
        $content .= '</table>';
        $content .= '</form>';
        return $content;
    }

    static function get_form_badge($id, $blockdata = []) {
        global $DB, $USER;

        $badges = block_exaport_get_all_user_badges();

        $content = "";
        $content .= '<form id="blockform">';
        $content .= '<input type="hidden" name="item_id" value="' . $id . '">';
        $content .= '<table style="width: 100%;">';
        $content .= '<tr><th>';
        $content .= '<label for="list">' . get_string('listofbadges', 'block_exaport') . '</label>';
        $content .= '</th></tr>';
        $content .= '<tr><td>';

        ob_start();
        ?>
        <div id="add-items-list">
            <?php
            if (!empty($badges)) {
                foreach ($badges as $badge) {
                    echo '<div class="add-item">';
                    echo '<input type="checkbox" name="add_badges[]" value="' . $badge->id . '" /> ';
                    echo $badge->name;
                    echo '</div>';
                }
            }
            ?>
        </div>
        <script type="text/javascript">
            exaportViewEdit.initAddItems();
        </script>
        <?php
        $content .= ob_get_clean();

        $content .= '</td></tr>';
        $content .= '<tr><td>';
        $content .= self::action_buttons();
        $content .= '</td></tr>';
        $content .= '</table>';
        $content .= '</form>';

        return $content;
    }
}
