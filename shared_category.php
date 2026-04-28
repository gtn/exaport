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

/**
 * Read-only external view of a shared category.
 *
 * This page allows external (non-logged-in) users to view the contents of a
 * shared category via a hash-based URL. Strictly read-only: no editing,
 * uploading, deleting, or commenting is possible.
 */

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/blockmediafunc.php');

$access = optional_param('access', '', PARAM_TEXT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);

// Allow access without being logged in (external access via hash).
require_login(0, true);

$url = '/blocks/exaport/shared_category.php';
$PAGE->set_url($url, ['access' => $access]);

// Validate the access hash and get the root shared category.
$rootcategory = block_exaport_get_category_from_access($access);
if (!$rootcategory) {
    throw new moodle_exception('shared_category_notfound', 'block_exaport');
}

// Get the owner user.
$owner = $DB->get_record('user', ['id' => $rootcategory->userid]);
if (!$owner || $owner->deleted) {
    throw new moodle_exception('shared_category_notfound', 'block_exaport');
}

// Determine which category to display: root or a subcategory.
if ($subcategoryid > 0) {
    // Verify the subcategory belongs to the same owner and is a descendant of the root category.
    $currentcategory = $DB->get_record('block_exaportcate', [
        'id' => $subcategoryid,
        'userid' => $rootcategory->userid,
    ]);
    if (!$currentcategory) {
        throw new moodle_exception('shared_category_notfound', 'block_exaport');
    }

    // Walk up the parent chain to verify this subcategory is actually under the root category.
    $verified = false;
    $checkcat = $currentcategory;
    $maxdepth = 50; // Prevent infinite loops.
    while ($checkcat && $maxdepth-- > 0) {
        if ($checkcat->id == $rootcategory->id) {
            $verified = true;
            break;
        }
        if (empty($checkcat->pid)) {
            break;
        }
        $checkcat = $DB->get_record('block_exaportcate', [
            'id' => $checkcat->pid,
            'userid' => $rootcategory->userid,
        ]);
    }
    if (!$verified) {
        throw new moodle_exception('shared_category_notfound', 'block_exaport');
    }
} else {
    $currentcategory = $rootcategory;
}

// Page setup.
$PAGE->set_title(get_string('shared_category', 'block_exaport') . ': ' . format_string($currentcategory->name));
$PAGE->set_heading(get_string('shared_category', 'block_exaport'));

block_exaport_init_js_css();

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($currentcategory->name));

// Show owner info.
echo '<div class="shared-category-owner" style="margin-bottom: 15px;">';
echo $OUTPUT->user_picture($owner, ['link' => false, 'size' => 35]);
echo ' <span>' . fullname($owner) . '</span>';
echo '</div>';

// Read-only notice.
echo '<div class="alert alert-info">';
echo get_string('shared_category_readonly', 'block_exaport');
echo '</div>';

// Breadcrumb navigation.
echo '<div class="shared-category-breadcrumb" style="margin-bottom: 15px;">';
$breadcrumbs = [];
if ($currentcategory->id != $rootcategory->id) {
    // Build breadcrumb from root to current.
    $crumbchain = [];
    $cat = $currentcategory;
    $maxdepth = 50;
    while ($cat && $maxdepth-- > 0) {
        array_unshift($crumbchain, $cat);
        if ($cat->id == $rootcategory->id) {
            break;
        }
        if (empty($cat->pid)) {
            break;
        }
        $cat = $DB->get_record('block_exaportcate', [
            'id' => $cat->pid,
            'userid' => $rootcategory->userid,
        ]);
    }
    foreach ($crumbchain as $i => $crumb) {
        if ($i == count($crumbchain) - 1) {
            // Current (last) item - no link.
            $breadcrumbs[] = '<strong>' . format_string($crumb->name) . '</strong>';
        } else {
            $crumburl = new moodle_url('/blocks/exaport/shared_category.php', [
                'access' => $access,
                'subcategoryid' => ($crumb->id == $rootcategory->id) ? 0 : $crumb->id,
            ]);
            $breadcrumbs[] = '<a href="' . $crumburl->out() . '">' . format_string($crumb->name) . '</a>';
        }
    }
    echo implode(' &raquo; ', $breadcrumbs);
} else {
    echo '<strong>' . format_string($currentcategory->name) . '</strong>';
}
echo '</div>';

// Get subcategories.
$subcategories = $DB->get_records('block_exaportcate', [
    'pid' => $currentcategory->id,
    'userid' => $rootcategory->userid,
], 'name ASC');

// Get items in this category.
$items = $DB->get_records_sql("
    SELECT i.id, i.type, i.name, i.intro, i.url, i.timemodified, i.attachment
    FROM {block_exaportitem} i
    WHERE i.categoryid = ?
        AND i.userid = ?
    ORDER BY i.name ASC
", [$currentcategory->id, $rootcategory->userid]);

// Display subcategories.
if ($subcategories) {
    echo '<h3>' . get_string('category', 'block_exaport') . '</h3>';
    echo '<div class="shared-category-subcategories">';
    foreach ($subcategories as $subcat) {
        $subcaturl = new moodle_url('/blocks/exaport/shared_category.php', [
            'access' => $access,
            'subcategoryid' => $subcat->id,
        ]);
        echo '<div class="shared-category-item" style="padding: 5px 0;">';
        echo '<a href="' . $subcaturl->out() . '">';
        echo '<i class="fa fa-folder" style="color: #7a7a7a; margin-right: 5px;"></i>';
        echo format_string($subcat->name);
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
}

// Display items (read-only, no edit/delete/comment links).
if ($items) {
    echo '<h3>' . get_string('artefacts', 'block_exaport') . '</h3>';
    $table = new html_table();
    $table->width = "100%";
    $table->head = [
        get_string('name'),
        get_string('type', 'block_exaport'),
        get_string('date', 'block_exaport'),
    ];
    $table->data = [];

    foreach ($items as $item) {
        $itemname = format_string($item->name);
        // Show intro as tooltip/description but no link to edit or interact.
        if ($item->intro) {
            $itemname .= '<br /><small>' . format_text(
                    $item->intro,
                    FORMAT_HTML,
                    ['noclean' => false, 'filter' => true]
                ) . '</small>';
        }

        $typename = get_string($item->type, 'block_exaport');

        $table->data[] = [
            $itemname,
            $typename,
            userdate($item->timemodified),
        ];
    }

    echo html_writer::table($table);
}

if (empty($subcategories) && empty($items)) {
    echo '<p>' . get_string('nobookmarksall', 'block_exaport') . '</p>';
}

// Back link if we're in a subcategory.
if ($currentcategory->id != $rootcategory->id) {
    // Go up to parent.
    $parentid = ($currentcategory->pid == $rootcategory->id) ? 0 : $currentcategory->pid;
    $backurl = new moodle_url('/blocks/exaport/shared_category.php', [
        'access' => $access,
        'subcategoryid' => $parentid,
    ]);
    echo '<div style="margin-top: 15px;">';
    echo '<a href="' . $backurl->out() . '">&laquo; ' . get_string('back') . '</a>';
    echo '</div>';
}

echo $OUTPUT->footer();
