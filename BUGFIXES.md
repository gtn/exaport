# Bug Fixes: Button Text and Query Error

## Issues Fixed

### 1. Inconsistent Button Text
**Problem**: Import buttons showed different text depending on submission type:
- File submissions: "add this file" (German: "diese Datei hinzufügen")
- Text submissions: "add this file"
- Feedback-only: "Add this assignment"

**Solution**: Standardized all buttons to use `get_string("add_this_assignment", "block_exaport")`

**Files Changed**: `import_moodle.php`
- Line 78: File submission button
- Line 100: Online text submission button

### 2. Query Error for Non-File Submissions
**Problem**: When importing submissions without files (text-only or feedback-only), the query in `import_moodle_add_file.php` would fail with "invalidassignment" error on line 77.

**Root Cause**: The else clause (lines 56-74) used an INNER JOIN with the `assignsubmission_file` table:
```sql
FROM {assignsubmission_file} sf
INNER JOIN {assign_submission} s ON sf.submission=s.id
```

This required a file submission record to exist, which doesn't exist for:
- Text-only submissions
- Submissions with no content but has grades/feedback

**Solution**: Changed to query `assign_submission` table directly:
```sql
FROM {assign_submission} s
INNER JOIN {assign} a ON s.assignment=a.id
```

**Files Changed**: `import_moodle_add_file.php`
- Lines 57-65: Simplified query to work with all submission types

## Technical Details

### Before Fix
```php
// import_moodle_add_file.php - Line 58-65 (OLD)
$assignment = $DB->get_record_sql("SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
    " a.name, a.course, c.fullname AS coursename " .
    " FROM {assignsubmission_file} sf " .
    " INNER JOIN {assign_submission} s ON sf.submission=s.id " .
    " INNER JOIN {assign} a ON s.assignment=a.id " .
    " LEFT JOIN {course} c on a.course = c.id " .
    " WHERE s.userid=? AND s.id=?", array($USER->id, $submissionid));
```

### After Fix
```php
// import_moodle_add_file.php - Line 57-65 (NEW)
// Normal case - get from submission (no specific flag set)
// This handles cases where we have a submission but don't know if it has files or text
$assignment = $DB->get_record_sql("SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
    " a.name, a.course, c.fullname AS coursename " .
    " FROM {assign_submission} s " .
    " INNER JOIN {assign} a ON s.assignment=a.id " .
    " LEFT JOIN {course} c on a.course = c.id " .
    " WHERE s.userid=? AND s.id=?", array($USER->id, $submissionid));
```

## Impact

### Positive Changes
1. ✅ Consistent user experience - all import buttons show same text
2. ✅ Text-only submissions can now be imported without errors
3. ✅ More robust query that works for all submission types
4. ✅ Cleaner code - removed unnecessary table join

### No Breaking Changes
- File submissions continue to work as before
- Feedback-only submissions continue to work as before
- Online text submissions continue to work as before
- All existing functionality preserved

## Testing Scenarios

### Test Case 1: File Submission
- **Setup**: Assignment with file uploaded
- **Expected**: Shows in list, imports successfully
- **Status**: ✅ Working

### Test Case 2: Text-Only Submission
- **Setup**: Assignment with online text, no file
- **Expected**: Shows in list, imports successfully
- **Status**: ✅ Fixed (was failing before)

### Test Case 3: Combined File+Text Submission
- **Setup**: Assignment with both file and text
- **Expected**: Both show in list, each imports successfully
- **Status**: ✅ Working

### Test Case 4: Feedback-Only (No Submission)
- **Setup**: Teacher grades without student submission
- **Expected**: Shows in list, imports successfully
- **Status**: ✅ Working

## Code Quality
- ✅ PHP syntax validated
- ✅ No SQL injection vulnerabilities (uses parameterized queries)
- ✅ Added clarifying comments
- ✅ Minimal changes as requested
- ✅ No drastic modifications

## Summary
These were small but important fixes that:
1. Improved consistency in the user interface
2. Fixed a critical bug preventing text-only submissions from being imported
3. Made the code more maintainable by removing unnecessary complexity

The changes align with the request to "clean up and fix small problems" without changing anything drastically.
