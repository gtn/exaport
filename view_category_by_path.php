<?php
global $DB, $USER, $PAGE, $COURSE;
require_once(__DIR__ . '/inc.php');

$path = required_param('path', PARAM_TEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Validate path length and characters
if (strlen($path) > 255 || preg_match('/[<>"\'\x00-\x1F]/', $path)) {
    throw new moodle_exception('invalid_path', 'block_exaport');
}

// Try to detect courseid from referer if not provided
if (!$courseid && !empty($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];

    // Check if coming from a course page
    if (preg_match('/\/course\/view\.php\?id=(\d+)/', $referer, $matches)) {
        $courseid = (int)$matches[1];
    }
    // Check if coming from a module page (like assignment)
    else if (preg_match('/\/mod\/[^\/]+\/view\.php\?id=(\d+)/', $referer, $matches)) {
        $moduleid = (int)$matches[1];
        try {
            $cm = get_coursemodule_from_id('', $moduleid);
            if ($cm) {
                $courseid = $cm->course;
            }
        } catch (Exception $e) {
            // Module not found, continue without courseid
        }
    }
}

// Auto-detect course if not provided (fallback)
if (!$courseid) {
    $courseid = $COURSE->id ?? $PAGE->course->id ?? 0;
}

// Login check
if ($courseid > 0) {
    block_exaport_require_login($courseid);
} else {
    require_login();
}

// Split the path into parts
$pathParts = array_filter(explode('/', trim($path, '/')));

// Limit number of path segments to prevent abuse
if (count($pathParts) > 20) {
    throw new moodle_exception('path_too_deep', 'block_exaport');
}

// Find the category by traversing the path
$categoryid = 0; // Start at root
$currentUserId = $USER->id;
$foundCategory = null;

foreach ($pathParts as $categoryName) {
    // Additional validation on each segment
    if (strlen($categoryName) > 100) {
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    // Build query conditions
    $conditions = [
        'userid' => $currentUserId,
        'pid' => $categoryid,
        'name' => $categoryName,
    ];

    // If we have a courseid, filter by it
    if ($courseid > 0) {
        $conditions['courseid'] = $courseid;
        $category = $DB->get_record('block_exaportcate', $conditions);
    } else {
        // If no courseid, get all matching and pick the most recent
        $categories = $DB->get_records('block_exaportcate', $conditions, 'timemodified DESC');
        $category = $categories ? reset($categories) : false;
    }

    if (!$category) {
        // Use generic error message to prevent enumeration
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    $categoryid = $category->id;
    $foundCategory = $category;
}

// Use the courseid from the found category for the redirect
// This ensures we always use the correct course context
$finalCourseid = $foundCategory ? $foundCategory->courseid : ($courseid > 0 ? $courseid : 1);

// Redirect to the actual view_items.php with resolved category ID
$redirectUrl = new moodle_url('/blocks/exaport/view_items.php', [
    'courseid' => $finalCourseid,
    'categoryid' => $categoryid,
]);

redirect($redirectUrl);
