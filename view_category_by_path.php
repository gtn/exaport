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
// if referrer is empty get courseid from $user->lastcourseaccess
if (!$courseid) {
        $courseid=getNewestCourse($USER->lastcourseaccess);
}

// If still no courseid, use current course
// (will be SITEID if accessed outside course context)
if (!$courseid) {
    $courseid = $COURSE->id;
}

// Login check
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
$foundCategory = null;

$courseid_temp=$courseid;
foreach ($pathParts as $categoryName) {
    // Additional validation on each segment
    if (strlen($categoryName) > 100) {
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    // Build base query conditions (without courseid filter)
    $conditions = [
        'userid' => $currentUserId,
        'pid' => $categoryid,
        'name' => $categoryName,
    ];

    // Get ALL matching categories
    $categories = $DB->get_records('block_exaportcate', $conditions, 'timemodified DESC');

    if (empty($categories)) {
        // No category found at all
        throw new moodle_exception('category_not_found', 'block_exaport');
    }

    // Priority 1: Try to find category with matching courseid (if not SITEID)
    $category = null;
    if ($courseid != SITEID) {
        foreach ($categories as $cat) {
            if ($cat->courseid == $courseid) {
                $category = $cat;
                break; // Found exact match, use it (already sorted by timemodified DESC)
            }
        }
    }

    // Priority 2: If no courseid match, use the first one (most recent due to sort)
    if (!$category) {
        $category = reset($categories);
    }

    $categoryid = $category->id;
    $foundCategory = $category;
    if ($foundCategory->courseid !== null && $foundCategory->courseid !=0) $courseid_temp=$foundCategory->courseid;
  
}

// Use the courseid from the found category for the redirect
// This ensures we always use the correct course context
$finalCourseid = $foundCategory->courseid;

if ($finalCourseid==0) $finalCourseid=$courseid_temp;
  
// Redirect to the actual view_items.php with resolved category ID
$redirectUrl = new moodle_url('/blocks/exaport/view_items.php', [
    'courseid' => $finalCourseid,
    'categoryid' => $categoryid,
]);

function getNewestCourse(array $lastcourseaccess) {
    $newestOver69 = null;
    $newestOver69Time = 0;

    $newestAny = null;
    $newestAnyTime = 0;

    foreach ($lastcourseaccess as $courseid => $timestamp) {

        // Newest Course
        if ($timestamp > $newestAnyTime) {
            $newestAnyTime = $timestamp;
            $newestAny = $courseid;
        }

        // Lastcourseaccess is not reliably up-to-date. Therefore, prefer the newer courses (ID > 69).
        if ($courseid > 69 && $timestamp > $newestOver69Time) {
            $newestOver69Time = $timestamp;
            $newestOver69 = $courseid;
        }
    }

    //  prefer the newer courses (ID > 69)
    if ($newestOver69 !== null) {
        return $newestOver69;
    }

    return $newestAny;
}

redirect($redirectUrl);
