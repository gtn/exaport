<?php
global $DB, $USER, $PAGE, $COURSE;
require_once(__DIR__ . '/inc.php');

$path = required_param('path', PARAM_TEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Auto-detect course if not provided
if (!$courseid) {
    // Try to get from current page context
    if (isset($COURSE) && $COURSE->id > 1) {
        $courseid = $COURSE->id;
    } else if ($PAGE->course && $PAGE->course->id > 1) {
        $courseid = $PAGE->course->id;
    } else {
        // Fallback: try to get from HTTP referer
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (preg_match('/[?&]id=(\d+)/', $referer, $matches)) {
            $courseid = intval($matches[1]);
        }
    }

    // Last resort: use site course
    if (!$courseid) {
        $courseid = 1;
    }
}

block_exaport_require_login($courseid);

// Split the path into parts
$pathParts = array_filter(explode('/', trim($path, '/')));

// Find the category by traversing the path
$categoryid = 0; // Start at root
$currentUserId = $USER->id;

foreach ($pathParts as $categoryName) {
    $category = $DB->get_record('block_exaportcate', [
        'userid' => $currentUserId,
        'pid' => $categoryid,
        'name' => $categoryName,
    ]);

    if (!$category) {
        // Category not found in user's portfolio
        throw new moodle_exception('category_not_found', 'block_exaport', '', $categoryName);
    }

    $categoryid = $category->id;
}

// Redirect to the actual view_items.php with resolved category ID
$redirectUrl = new moodle_url('/blocks/exaport/view_items.php', [
    'courseid' => $courseid,
    'categoryid' => $categoryid,
]);

redirect($redirectUrl);
