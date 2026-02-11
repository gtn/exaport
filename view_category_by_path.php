<?php
global $DB, $USER, $PAGE, $COURSE;
require_once(__DIR__ . '/inc.php');

$path = required_param('path', PARAM_TEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Validate path length and characters
if (strlen($path) > 255 || preg_match('/[<>"\'\x00-\x1F]/', $path)) {
    throw new moodle_exception('invalid_path', 'block_exaport');
}

// Auto-detect course if not provided
if (!$courseid) {
    // get it from course, or page-course or default to 1, which is the site course. Works for the globally functioning exaport
    $courseid = $COURSE->id ?? $PAGE->course->id ?? 1;
}

block_exaport_require_login($courseid);

// Split the path into parts
$pathParts = array_filter(explode('/', trim($path, '/')));

// Limit number of path segments to prevent abuse
if (count($pathParts) > 20) {
    throw new moodle_exception('path_too_deep', 'block_exaport');
}

// Find the category by traversing the path
$categoryid = 0; // Start at root
$currentUserId = $USER->id;

foreach ($pathParts as $categoryName) {
    // Additional validation on each segment
    if (strlen($categoryName) > 100) {
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    $category = $DB->get_record('block_exaportcate', [
        'userid' => $currentUserId,
        'pid' => $categoryid,
        'name' => $categoryName,
    ]);

    if (!$category) {
        // Use generic error message to prevent enumeration
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    $categoryid = $category->id;
}

// Redirect to the actual view_items.php with resolved category ID
$redirectUrl = new moodle_url('/blocks/exaport/view_items.php', [
    'courseid' => $courseid,
    'categoryid' => $categoryid,
]);

redirect($redirectUrl);
