# Correction: is_hidden_grader() vs is_blind_marking()

## The Issue

In the initial implementation, we used the wrong Moodle API method to check if grader identity should be hidden from students.

## The Mistake

**What was used:** `is_blind_marking()`
**What should be used:** `is_hidden_grader()`

## Important Distinction

These are two **completely different** privacy settings in Moodle assignments:

### `is_blind_marking()`
- **Purpose:** Hides STUDENT identities from graders during the marking process
- **When enabled:** Graders see anonymous participant numbers instead of student names
- **Direction:** Protects students from graders
- **Use case:** Ensuring unbiased grading without knowing which student submitted what

### `is_hidden_grader()` ✓ CORRECT
- **Purpose:** Hides GRADER identity from students when viewing feedback
- **When enabled:** Students don't see which teacher/grader marked their work
- **Direction:** Protects graders from students
- **Use case:** Privacy for teaching staff, preventing favoritism concerns

## Why This Matters

When exporting assignment feedback to the ePortfolio:
- We need to respect whether the **grader's name** should be hidden from the **student**
- This is controlled by `is_hidden_grader()`, NOT `is_blind_marking()`
- Using the wrong method would check the wrong privacy setting

## The Fix

### Before (Incorrect)
```php
// Check if blindmarking is enabled and student can't see hidden grader
if ($assign->is_blind_marking()) {
    // When blindmarking is enabled, check if student has permission to see grader
    $showgrader = has_capability('mod/assign:showhiddengrader', $context, $USER->id);
}
```

### After (Correct)
```php
// Check if grader identity is hidden from students (is_hidden_grader setting)
if ($assign->is_hidden_grader()) {
    // When grader identity is hidden, check if student has permission to see grader
    $showgrader = has_capability('mod/assign:showhiddengrader', $context, $USER->id);
}
```

## Impact

With the correct method:
- ✅ The ePortfolio export now properly respects the "Hide grader identity from students" setting
- ✅ When is_hidden_grader is enabled, students see "Hidden grader" instead of teacher name
- ✅ The `mod/assign:showhiddengrader` capability is checked against the correct setting
- ✅ Follows Moodle's intended privacy model

## Related Moodle Settings

In the Moodle Assignment activity settings:
- **Anonymous submissions** → Controls `is_blind_marking()` (student anonymity)
- **Hide grader identity from students** → Controls `is_hidden_grader()` (grader anonymity)

These are independent settings and can be configured separately.

## Lesson Learned

When working with Moodle's privacy features:
1. Always verify which direction the privacy protection goes (who is hidden from whom)
2. Check Moodle documentation for the exact purpose of each method
3. Use the method that matches the specific privacy requirement
4. Don't assume methods with similar names serve the same purpose
