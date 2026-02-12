<?php
// Test script to understand Moodle's assign API
// This helps us understand how to properly use the API to respect blindmarking

require_once(__DIR__ . '/inc.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

echo "=== Testing Moodle Assign API ===\n\n";

// Get an assignment to test with
$assignment = $DB->get_record('assign', array(), '*', IGNORE_MULTIPLE);
if (!$assignment) {
    echo "No assignments found in database\n";
    exit;
}

echo "Testing with assignment: {$assignment->name} (ID: {$assignment->id})\n";
echo "Blindmarking setting: " . ($assignment->blindmarking ? 'ENABLED' : 'DISABLED') . "\n\n";

// Get the course module
$cm = get_coursemodule_from_instance('assign', $assignment->id);
if (!$cm) {
    echo "Could not find course module\n";
    exit;
}

// Get course
$course = $DB->get_record('course', array('id' => $assignment->course));
$context = context_module::instance($cm->id);

// Create assign object
$assign = new assign($context, $cm, $course);

echo "=== Available assign object methods ===\n";
$methods = get_class_methods($assign);
$relevant_methods = array_filter($methods, function($method) {
    return stripos($method, 'blind') !== false || 
           stripos($method, 'grader') !== false ||
           stripos($method, 'feedback') !== false ||
           stripos($method, 'view') !== false;
});
foreach ($relevant_methods as $method) {
    echo "- $method\n";
}

echo "\n=== Checking blindmarking methods ===\n";
if (method_exists($assign, 'is_blind_marking')) {
    echo "is_blind_marking() exists: " . ($assign->is_blind_marking() ? 'TRUE' : 'FALSE') . "\n";
}
if (method_exists($assign, 'is_hidden_grader')) {
    echo "is_hidden_grader() exists\n";
}

echo "\n=== Assignment properties ===\n";
echo "blindmarking: " . (isset($assignment->blindmarking) ? $assignment->blindmarking : 'not set') . "\n";
echo "revealidentities: " . (isset($assignment->revealidentities) ? $assignment->revealidentities : 'not set') . "\n";
echo "markingworkflow: " . (isset($assignment->markingworkflow) ? $assignment->markingworkflow : 'not set') . "\n";

echo "\n=== Testing complete ===\n";
