# Fix: Preserve Parameters Through Form Submission

## Issue Description

When creating an artifact from an assignment with feedback but no submission, the `$nosubmission` parameter (set to 1) was lost during form submission. This caused the following sequence:

1. User imports feedback-only assignment with `?nosubmission=1`
2. Form displays with assignment name prefilled
3. User edits title or other fields
4. User saves the form
5. **PROBLEM**: Form submits without `nosubmission` parameter
6. System defaults to `nosubmission=0`
7. System looks for non-existent submission
8. Error thrown: "invalidassignment" or similar

The same issue affected the `$onlinetext` parameter for text-only submissions.

## Root Cause

The form in `lib/item_edit_form.php` only had hidden fields for:
- `submissionid`
- `fileid`

But was missing hidden fields for:
- `nosubmission` (indicates feedback-only)
- `onlinetext` (indicates text-only submission)
- `aid` (assignment ID, needed for nosubmission case)

When the form was submitted, these parameters were not included in the POST data, causing them to default to 0 on the next page load.

## Solution

Added hidden fields to preserve all critical parameters through the form lifecycle.

### Changes Made

#### 1. Form Hidden Fields (lib/item_edit_form.php)

Added hidden fields in the assignment import section (lines 143-148):

```php
if ($this->_customdata['action'] == 'assignment_import') {
    // Assignment import.
    $mform->addElement('hidden', 'submissionid');
    $mform->setType('submissionid', PARAM_INT);
    $mform->addElement('hidden', 'fileid');
    $mform->setType('fileid', PARAM_TEXT);
    // NEW: Preserve nosubmission parameter
    $mform->addElement('hidden', 'nosubmission');
    $mform->setType('nosubmission', PARAM_INT);
    // NEW: Preserve onlinetext parameter
    $mform->addElement('hidden', 'onlinetext');
    $mform->setType('onlinetext', PARAM_INT);
    // NEW: Preserve assignment ID
    $mform->addElement('hidden', 'aid');
    $mform->setType('aid', PARAM_INT);
}
```

#### 2. Form Initialization (import_moodle_add_file.php)

Set values in `$existing` object (lines 181-185):

```php
$existing->submission = $submissionid;
$existing->submissionid = $submissionid;  // Explicit for form
$existing->fileid = $fileid;              // Explicit for form
$existing->nosubmission = $nosubmission;  // NEW: Preserve flag
$existing->onlinetext = $onlinetext;      // NEW: Preserve flag
$existing->aid = $aid;                     // NEW: Preserve aid
```

#### 3. Form Display Data (import_moodle_add_file.php)

Set values in `$post` object (lines 229-231):

```php
$post->submissionid = $submissionid;
$post->fileid = $fileid;
$post->name = $assignment->name;
$post->nosubmission = $nosubmission;  // NEW: Preserve flag
$post->onlinetext = $onlinetext;      // NEW: Preserve flag
$post->aid = $aid;                     // NEW: Preserve aid
```

## Technical Details

### Form Lifecycle

1. **Initial Request**: User clicks import button
   ```
   GET import_moodle_add_file.php?
       submissionid=123&
       nosubmission=1&
       aid=456&
       courseid=1
   ```

2. **Form Display**: Parameters captured via `optional_param()`
   ```php
   $nosubmission = optional_param('nosubmission', 0, PARAM_INT);
   $onlinetext = optional_param('onlinetext', 0, PARAM_INT);
   $aid = optional_param('aid', 0, PARAM_INT);
   ```

3. **Form HTML**: Hidden fields include parameters
   ```html
   <input type="hidden" name="nosubmission" value="1">
   <input type="hidden" name="onlinetext" value="0">
   <input type="hidden" name="aid" value="456">
   ```

4. **Form Submission**: All parameters included in POST
   ```
   POST import_moodle_add_file.php
   Data: {
       nosubmission: 1,
       onlinetext: 0,
       aid: 456,
       ...other fields
   }
   ```

5. **Processing**: Parameters correctly retrieved again
   ```php
   $nosubmission = optional_param('nosubmission', 0, PARAM_INT); // Gets 1
   ```

### Parameter Usage

#### nosubmission
- **Purpose**: Indicates assignment has feedback but no student submission
- **Used in**:
  - Line 33: Query assignment data without submission
  - Line 106: Skip submission validation
  - Line 111: Skip file/text checks
  - Line 171: Set artifact type to 'note'

#### onlinetext
- **Purpose**: Indicates text-only submission (no files)
- **Used in**:
  - Line 44: Query for online text submission
  - Line 111: Check online text content
  - Line 171: Set artifact type to 'note'

#### aid
- **Purpose**: Assignment ID (direct reference)
- **Used in**:
  - Line 33: When nosubmission=1, use aid to get assignment
  - Fallback when submission-based queries fail

## Test Scenarios

### Test Case 1: Feedback-Only Assignment
**Setup**: Teacher grades assignment without student submission

**Steps**:
1. Navigate to `import_moodle.php`
2. Click "Add this assignment" on feedback-only row
3. URL: `?nosubmission=1&aid=456`
4. Form displays with prefilled title
5. Edit title from "Essay Assignment" to "My Essay Assignment"
6. Click Save

**Before Fix**:
- ❌ Error: "invalidassignment"
- Reason: `nosubmission` lost, system looks for submission

**After Fix**:
- ✅ Artifact created successfully
- ✅ Title saved as "My Essay Assignment"
- ✅ Feedback attached to artifact

### Test Case 2: Text-Only Submission
**Setup**: Student submits text without files

**Steps**:
1. Navigate to `import_moodle.php`
2. Click import on online text row
3. URL: `?onlinetext=1&submissionid=123`
4. Form displays with text preview
5. Edit title
6. Click Save

**Before Fix**:
- ❌ Incorrect behavior (tries to find file)
- Reason: `onlinetext` lost

**After Fix**:
- ✅ Artifact created with text content
- ✅ No file checks performed
- ✅ Title saved correctly

### Test Case 3: File Submission
**Setup**: Student submits file

**Steps**:
1. Navigate to `import_moodle.php`
2. Click import on file row
3. URL: `?submissionid=123&fileid=abc`
4. Edit title
5. Click Save

**Before Fix**:
- ✅ Worked (no dependency on missing parameters)

**After Fix**:
- ✅ Still works
- ✅ No regression

## Benefits

### User Experience
1. **No More Errors**: Feedback-only artifacts can be edited and saved
2. **Consistent Behavior**: All submission types handle title editing
3. **Data Integrity**: Parameters preserved through entire workflow

### Code Quality
1. **Explicit Parameters**: All needed values explicitly passed
2. **Defensive Programming**: Parameters preserved even if not immediately needed
3. **Maintainability**: Clear what values need to be maintained

### Edge Cases Handled
1. ✅ Feedback-only with title edit
2. ✅ Text-only with title edit
3. ✅ File with title edit
4. ✅ Multiple form fields edited
5. ✅ Form cancelled and reopened
6. ✅ Back button behavior

## Comparison: Before vs After

### Before Fix
```
User Flow:
1. Click import (nosubmission=1)
2. Form shows ✓
3. Edit title ✓
4. Submit form
5. nosubmission lost → defaults to 0
6. System queries submission table
7. No submission found
8. ERROR ✗
```

### After Fix
```
User Flow:
1. Click import (nosubmission=1)
2. Form shows ✓
3. Hidden field: <input name="nosubmission" value="1">
4. Edit title ✓
5. Submit form
6. nosubmission=1 preserved in POST
7. System uses correct parameters
8. Artifact saved ✓
```

## Related Issues

This fix resolves similar issues that could occur with:
- Form validation failures (parameters lost on re-display)
- Multi-step wizards (parameters needed across steps)
- Any form that needs to preserve query parameters

## Maintenance Notes

When adding new parameters to assignment import:
1. Add to `optional_param()` calls at top of file
2. Add hidden field to form in `lib/item_edit_form.php`
3. Set value in `$existing` object
4. Set value in `$post` object
5. Use parameter in processing logic

## Summary

This fix ensures that critical parameters (`nosubmission`, `onlinetext`, `aid`) are preserved through the entire form lifecycle, preventing errors when users edit and save artifacts from various submission types.

The solution is minimal, focused, and follows existing patterns in the codebase for handling hidden form fields.
