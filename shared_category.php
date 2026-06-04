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

use function block_exaport\common\print_error;

/**
 * Render one category node for external sharing.
 *
 * We keep this renderer strictly read-only by only generating plain HTML output.
 * No forms or write actions are emitted, so external guests cannot trigger changes.
 *
 * @param stdClass $category
 * @param int $ownerid
 * @param string $canonicalaccess
 * @param array $visited
 * @return string
 */
function block_exaport_render_shared_category_node($category, $ownerid, $canonicalaccess, &$visited) {
    global $DB, $CFG;

    if (isset($visited[$category->id])) {
        // Defensive cycle protection: malformed parent relations must never cause endless recursion on public pages.
        return '';
    }
    $visited[$category->id] = true;

    $content = '<li class="shared-category-node">';
    $content .= '<h3>' . format_string($category->name) . '</h3>';

    // Restrict to owner items only: this prevents accidentally exposing foreign items that might be linked elsewhere.
    $sql = "SELECT DISTINCT i.*
              FROM {block_exaportitem} i
              JOIN {block_exaportitemcate} ic ON ic.itemid = i.id
             WHERE ic.cateid = ?
               AND i.userid = ?
               AND " . block_exaport_get_item_where() . "
             ORDER BY i.name ASC";
    $items = $DB->get_records_sql($sql, [$category->id, $ownerid]);

    if ($items) {
        $content .= '<ul class="shared-category-items">';
        foreach ($items as $item) {
            $content .= '<li class="shared-category-item">';
            $content .= '<div><strong>' . format_string($item->name) . '</strong> <span>(' . s($item->type) . ')</span></div>';

            if (!empty($item->intro)) {
                // Rewriting through pluginfile keeps Moodle's standard file serving flow while enforcing our category hash checks.
                $intro = file_rewrite_pluginfile_urls(
                    $item->intro,
                    'pluginfile.php',
                    context_user::instance($item->userid)->id,
                    'block_exaport',
                    'item_content',
                    'category/' . $canonicalaccess . '/itemid/' . $item->id
                );
                // Keep Moodle's default text cleaning enabled for external pages to reduce stored-XSS risk.
                $content .= '<div>' . format_text($intro, FORMAT_HTML) . '</div>';
            }

            if ($item->type === 'file') {
                $fs = get_file_storage();
                $files = $fs->get_area_files(
                    context_user::instance($item->userid)->id,
                    'block_exaport',
                    'item_file',
                    $item->id,
                    'filename',
                    false
                );

                if ($files) {
                    $content .= '<ul class="shared-category-item-files">';
                    foreach ($files as $file) {
                        // Access path intentionally mirrors shared_view.php and is verified in block_exaport_get_item().
                        $downloadurl = $CFG->wwwroot . '/pluginfile.php/' . context_user::instance($item->userid)->id .
                            '/block_exaport/item_file/category/' . $canonicalaccess . '/itemid/' . $item->id . '/' .
                            rawurlencode($file->get_filename());
                        $content .= '<li><a href="' . s($downloadurl) . '">' . s($file->get_filename()) . '</a></li>';
                    }
                    $content .= '</ul>';
                }
            }

            $content .= '</li>';
        }
        $content .= '</ul>';
    }

    // Restrict recursion to categories of the same owner to avoid leaking cross-user data through orphaned relations.
    $children = $DB->get_records('block_exaportcate', ['pid' => $category->id, 'userid' => $ownerid], 'name ASC');
    if ($children) {
        $content .= '<ul class="shared-category-children">';
        foreach ($children as $child) {
            $content .= block_exaport_render_shared_category_node($child, $ownerid, $canonicalaccess, $visited);
        }
        $content .= '</ul>';
    }

    $content .= '</li>';
    return $content;
}

$rawaccess = optional_param('access', '', PARAM_TEXT);
$access = clean_param($rawaccess, PARAM_TEXT);
if (!preg_match('!^hash/[0-9]+-[a-zA-Z0-9]{8}$!', $access)) {
    // Fail fast on malformed input so we never construct file URLs from unexpected path fragments.
    print_error('category_not_found', 'block_exaport');
}

// Same login model as shared_view.php: allow guest context for public links while still using Moodle session controls.
require_login(0, true);

$url = new moodle_url('/blocks/exaport/shared_category.php', ['access' => $access]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

if (!block_exaport_externaccess_enabled()) {
    print_error('areaisdisabled', 'block_exaport');
}

if (!$category = block_exaport_get_category_from_access($access)) {
    print_error('category_not_found', 'block_exaport');
}
$canonicalaccess = clean_param('hash/' . $category->userid . '-' . $category->hash, PARAM_TEXT);
if (!preg_match('!^hash/[0-9]+-[a-zA-Z0-9]{8}$!', $canonicalaccess)) {
    print_error('category_not_found', 'block_exaport');
}

$owner = $DB->get_record('user', ['id' => $category->userid, 'deleted' => 0]);
if (!$owner) {
    print_error('category_not_found', 'block_exaport');
}

$PAGE->requires->css('/blocks/exaport/css/shared_view.css');
$PAGE->set_title(get_string('externaccess', 'block_exaport'));
$PAGE->set_heading(get_string('externaccess', 'block_exaport') . ' ' . fullname($owner));

echo $OUTPUT->header();
echo block_exaport_wrapperdivstart();

echo '<h2>' . format_string($category->name) . '</h2>';
echo '<ul class="shared-category-tree">';
$visited = [];
echo block_exaport_render_shared_category_node($category, $category->userid, $canonicalaccess, $visited);
echo '</ul>';

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
