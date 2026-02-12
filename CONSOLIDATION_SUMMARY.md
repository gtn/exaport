# Assignment Import Consolidation Summary

## Overview
This consolidation effort unified the two assignment import paths into a single, consistent implementation that eliminates code duplication and provides enhanced functionality.

## Problem Statement
Previously, there were two separate ways to import assignments from Moodle to Exaport:
1. **Direct Import**: `import_moodle.php` + `import_moodle_add_file.php`
2. **Portfolio Export**: `lib/portfolio_plugin/lib.php`

These had **duplicate code**, **inconsistent behavior**, and **missing features**:
- Used filename instead of assignment name for artifacts
- Feedback attribution was incorrect (student instead of teacher)
- No support for assignments with feedback but no submission
- ~400 lines of duplicated logic

## Solution Implemented

### 1. Shared Library Functions
Created two new shared functions in `lib/lib.php`:

#### `block_exaport_create_item_from_assignment($assignment, $file, $categoryid, $courseid)`
**Purpose**: Unified function to create portfolio artifacts from assignments

**Features**:
- Uses assignment name (not filename) for artifact name
- Handles both file submissions and feedback-only cases
- Creates 'file' type for submissions, 'note' type for feedback-only
- Automatically saves submission file if provided
- Integrates with competence system
- Calls feedback function to add teacher feedback

**Parameters**:
- `$assignment`: Object with assignment data (aid, assignment, name, coursename)
- `$file`: Optional stored_file object for submission
- `$categoryid`: Category ID for the artifact (default: 0)
- `$courseid`: Course ID

**Returns**: Item ID of created artifact

#### `block_exaport_add_teacher_feedback_to_item($itemid, $cm, $assignmentid)`
**Purpose**: Add teacher feedback (text and files) to a portfolio artifact

**Features**:
- Retrieves grade record for the student
- Gets feedback comment text from `assignfeedback_comments` table
- Gets feedback files from `assignfeedback_file` component
- Creates comment with **teacher as author** (uses `$grade->grader`)
- Includes teacher's name in comment
- Attaches all feedback files to the comment

**Parameters**:
- `$itemid`: Portfolio item ID
- `$cm`: Course module object
- `$assignmentid`: Assignment ID

### 2. Enhanced Assignment Query
Modified `block_exaport_get_assignments_for_import()` to support feedback-only assignments:

```sql
SELECT DISTINCT 
    COALESCE(s.id, ag.id * -1) AS submissionid,
    a.id AS aid, 
    a.id AS assignment,
    COALESCE(s.timemodified, ag.timemodified) AS timemodified,
    a.name, 
    a.course, 
    c.fullname AS coursename,
    CASE WHEN s.id IS NULL THEN 0 ELSE 1 END AS has_submission
FROM {assign} a
LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = ?
LEFT JOIN {assignsubmission_file} sf ON sf.submission = s.id
LEFT JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.userid = ?
LEFT JOIN {course} c ON a.course = c.id
WHERE (sf.id IS NOT NULL OR ag.id IS NOT NULL)
```

**Key Changes**:
- Uses LEFT JOIN instead of INNER JOIN
- Includes assignments with grades even without submissions
- Adds `has_submission` flag to identify feedback-only cases
- Uses negative submission ID for feedback-only cases

### 3. Direct Import Path Updates

#### `import_moodle.php`
- Updated display logic to handle feedback-only assignments
- Shows "No submission file" for assignments without submissions
- Provides import button for feedback-only cases
- Passes `nosubmission=1` parameter for these cases

#### `import_moodle_add_file.php`
- Added support for `aid` and `nosubmission` parameters
- Handles both normal and feedback-only imports
- Simplified `do_add()` function to just call shared function
- Removed duplicate feedback handling code (~80 lines)
- Updated display to show message when no file exists

### 4. Portfolio Plugin Updates

#### `lib/portfolio_plugin/lib.php`
- Completely refactored `send_package()` method
- Extracts assignment context from portfolio caller
- Uses shared function for assignment imports
- Falls back to old behavior for non-assignment files
- Removed duplicate feedback handling code (~150 lines)

**New helper method**: `get_assignment_from_caller($caller)`
- Extracts course module from various caller types
- Validates it's an assignment
- Builds assignment object for shared function

## Technical Details

### Database Tables Used

#### For Submissions
- `assign` - Assignment definition
- `assign_submission` - Student submissions
- `assignsubmission_file` - Submission file records

#### For Feedback
- `assign_grades` - Grade records with grader (teacher) ID
- `assignfeedback_comments` - Teacher feedback text
- `assignfeedback_file` - Teacher feedback file records

#### For Portfolio
- `block_exaportitem` - Portfolio artifacts
- `block_exaportitemcomm` - Comments (used for feedback)
- Files stored in `item_comment_file` area

### Feedback Attribution
**Before**: Comment used `$USER->id` (student)
**After**: Comment uses `$grade->grader` (teacher)

This ensures:
- Correct author attribution
- Teacher's name displayed
- Proper permission handling

### Edge Cases Handled

1. **No submission, has feedback**: Creates note-type artifact with feedback
2. **Has submission, no feedback**: Creates file-type artifact, no comment
3. **Has both**: Creates file-type artifact with feedback comment
4. **Multiple feedback files**: All attached to single comment
5. **Legacy assignments**: Gracefully skipped for feedback features

## Code Metrics

### Lines of Code
- **Before**: ~400 lines of duplicated logic
- **After**: ~150 lines in shared functions
- **Net Reduction**: ~250 lines

### Files Modified
1. `lib/lib.php` - Added 172 lines (shared functions)
2. `import_moodle_add_file.php` - Reduced by ~80 lines
3. `lib/portfolio_plugin/lib.php` - Reduced by ~150 lines
4. `import_moodle.php` - Enhanced display logic
5. `lang/en/block_exaport.php` - Added language strings

## Testing Scenarios

### Direct Import
1. ✅ Import assignment with submission file
2. ✅ Import assignment without submission (feedback only)
3. ✅ Import with feedback comment text
4. ✅ Import with feedback files
5. ✅ Import with both text and files

### Portfolio Export
1. ✅ Export from assignment view with submission
2. ✅ Export with feedback
3. ✅ Non-assignment file export (fallback)

### Consistency
1. ✅ Both paths use assignment name
2. ✅ Both paths include feedback
3. ✅ Both paths attribute to teacher
4. ✅ Both paths handle no-submission case

## Benefits

### For Users
- Consistent experience across both import methods
- See meaningful assignment names instead of filenames
- Complete feedback preserved (text + files)
- Can import assignments even without submitting files

### For Developers
- Single source of truth for assignment import logic
- Easier to maintain and enhance
- Clear separation of concerns
- Better code reusability

### For Maintenance
- Bug fixes in one place affect both paths
- New features automatically available to both
- Reduced testing surface area
- Better documentation

## Language Strings Added
- `add_this_assignment` - Button text for feedback-only imports
- `nosubmissionfile` - Display text when no submission exists
- `teacher` - Fallback teacher name

## Future Enhancements

### Potential Improvements
1. Support for other feedback types (rubrics, inline comments)
2. Display feedback preview on import list
3. Bulk import with feedback
4. Feedback summary statistics
5. Legacy assignment support for feedback

### Known Limitations
1. Modern assignments only (Moodle 2.3+)
2. File-based feedback only (not text-only)
3. Single feedback comment per import
4. Requires assignfeedback_file plugin

## Migration Notes
- No database changes required
- Backward compatible with existing artifacts
- Existing import functionality unchanged
- New features automatically available

## Conclusion
This consolidation successfully:
✅ Unified two import paths into consistent behavior
✅ Eliminated ~250 lines of duplicate code
✅ Added support for feedback-only assignments
✅ Fixed teacher attribution for feedback
✅ Used assignment names for artifacts
✅ Included feedback comment text

The codebase is now cleaner, more maintainable, and provides better functionality for users.
