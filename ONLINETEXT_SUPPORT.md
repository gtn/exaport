# Online Text Submission Support Implementation

## Overview
Added comprehensive support for online text submissions in Moodle assignments, allowing students to import their text-based assignment submissions into Exaport portfolio alongside file submissions.

## Problem Statement
Previously, Exaport only handled file submissions from Moodle assignments. When students submitted assignments using online text editor (no files), these submissions were not displayed or importable. The import list only showed:
- Assignments with file submissions
- Assignments with teacher feedback but no submission

Missing:
- Text-only submissions
- Combined file+text submissions

## Solution Overview

### 1. Database Query Enhancement
**File**: `lib/lib.php` - `block_exaport_get_assignments_for_import()`

Added LEFT JOIN for online text submissions:
```sql
LEFT JOIN {assignsubmission_onlinetext} sot
    ON sot.submission = s.id
```

Added flags to identify submission types:
```sql
CASE WHEN sf.id IS NOT NULL THEN 1 ELSE 0 END AS has_file,
CASE WHEN sot.id IS NOT NULL THEN 1 ELSE 0 END AS has_onlinetext
```

This allows the system to distinguish between:
- File submissions (`has_file = 1`)
- Text submissions (`has_onlinetext = 1`)
- Combined submissions (both flags = 1)
- Feedback-only (both flags = 0)

### 2. Import List Display
**File**: `import_moodle.php`

#### Changed Column Header
- **Before**: "File"
- **After**: "File/Text"

This better represents the content types that can be imported.

#### Display Logic
```php
if ($hasfile) {
    // Display file submissions with file icon and link
    foreach ($files as $file) {
        // Show file with icon and download link
    }
}

if ($hasonlinetext) {
    // Display online text with edit icon
    $onlinetext = $DB->get_record('assignsubmission_onlinetext', ...);
    // Show preview (first 100 chars)
    $textpreview = strip_tags(format_text($onlinetext->onlinetext, ...));
    // Truncate and add ellipsis if needed
}
```

#### Online Text Preview
- Formats text according to format (HTML, plain, etc.)
- Strips HTML tags for preview
- Shows first 100 characters
- Adds "..." if text is longer
- Displays with edit icon (i/edit)

### 3. Import Handler
**File**: `import_moodle_add_file.php`

#### New Parameter
Added `$onlinetext` parameter to indicate text submission:
```php
$onlinetext = optional_param('onlinetext', 0, PARAM_INT);
```

#### Query Logic
Three cases handled:
1. **No submission** (`$nosubmission && $aid`): Feedback-only
2. **Online text** (`$onlinetext && $submissionid`): Text submission
3. **File** (default): File submission

#### Online Text Retrieval
```php
if ($onlinetext && !$nosubmission) {
    $checkedonlinetext = $DB->get_record('assignsubmission_onlinetext', 
        array('submission' => $assignment->submissionid));
    if (!$checkedonlinetext || empty($checkedonlinetext->onlinetext)) {
        print_error("invalidonlinetextatthisassignment", "block_exaport");
    }
}
```

#### Form Preparation
- Sets artifact type to 'note' for online text (instead of 'file')
- Populates intro field with formatted text
- Displays text preview in confirmation box

### 4. Artifact Creation
**File**: `lib/lib.php` - `block_exaport_create_item_from_assignment()`

#### Function Signature Update
```php
function block_exaport_create_item_from_assignment(
    $assignment, 
    $file = null, 
    $categoryid = 0, 
    $courseid = 0, 
    $onlinetext = null  // NEW PARAMETER
)
```

#### Text Storage
```php
$item->intro = $onlinetext ? $onlinetext : '';
```

The online text is stored in the `intro` field of the portfolio item, allowing it to be displayed as the main content of the artifact.

### 5. Language Strings
**File**: `lang/en/block_exaport.php`

Added strings:
- `onlinetext` = "Online text"
- `fileortext` = "File/Text"
- `textpreview` = "Text preview"
- `invalidonlinetextatthisassignment` = "Invalid online text at this assignment!"

## User Experience

### Import List View
Students now see:

| Assignment | Time | File/Text | Course | Action |
|------------|------|-----------|--------|--------|
| Essay Assignment | 2026-02-10 | 📄 document.pdf | Course 101 | [add this file] |
| Essay Assignment | 2026-02-10 | ✏️ Online text: This is my essay... | Course 101 | [add this file] |
| Discussion Post | 2026-02-11 | ✏️ Online text: I believe that... | Course 101 | [add this file] |

### Import Process
1. Student clicks "add this file" for online text submission
2. Sees preview of text content
3. Can edit name and category
4. Imports as 'note' type artifact
5. Text content stored in artifact intro field
6. Teacher feedback added as comment

### Artifact Display
The imported artifact shows:
- **Name**: Assignment name
- **Type**: Note (for text submissions)
- **Content**: Full formatted text from submission
- **Comments**: Teacher feedback (if any)

## Technical Details

### Database Tables
- `assignsubmission_onlinetext`: Stores online text submissions
  - `submission`: FK to assign_submission.id
  - `onlinetext`: The text content (LONGTEXT)
  - `onlineformat`: Text format (0=Moodle, 1=HTML, 2=Plain, 4=Markdown)

### Text Formatting
Uses Moodle's `format_text()` function to properly handle:
- HTML formatting
- Plain text
- Markdown
- Links and media
- Filters and plugins

### Item Type Selection
- **File**: When file submission exists (`$file` parameter provided)
- **Note**: When online text, feedback-only, or no file

## Test Scenarios

### 1. File-Only Submission
- **Setup**: Student uploads PDF file
- **Expected**: Shows in list with file icon, imports as file type
- **Status**: ✅ Working (existing functionality)

### 2. Text-Only Submission
- **Setup**: Student submits text via editor
- **Expected**: Shows in list with edit icon, shows preview, imports as note
- **Status**: ✅ Implemented

### 3. Combined File+Text Submission
- **Setup**: Student uploads file AND adds text
- **Expected**: Both appear as separate rows in import list
- **Status**: ✅ Implemented

### 4. Feedback-Only (No Submission)
- **Setup**: Teacher grades without student submission
- **Expected**: Shows "No submission file", imports with feedback
- **Status**: ✅ Working (existing functionality)

### 5. Long Text Preview
- **Setup**: Student submits 500 words of text
- **Expected**: Preview shows first 100 chars + "..."
- **Status**: ✅ Implemented

### 6. HTML Formatted Text
- **Setup**: Student uses bold, italics, lists in text
- **Expected**: Formatting preserved in artifact, stripped in preview
- **Status**: ✅ Implemented

## Code Quality

### Error Handling
- Validates online text exists before import
- Checks for empty text content
- Proper error messages via language strings

### Backwards Compatibility
- No breaking changes to existing functionality
- File submissions work as before
- No database schema changes
- Optional parameters maintain compatibility

### Performance
- Single query retrieves all submission types
- No additional queries for simple file submissions
- Text preview generated on-demand (not stored)

## Limitations

### Current Scope
- Only modern assign module (Moodle 2.3+) supported
- Legacy assignment module not updated
- Text stored in intro field (single text field)

### Not Implemented
- Embedded images in text (would need file copying)
- Text formatting in preview (intentionally stripped)
- Combined file+text as single artifact (separate by design)

## Future Enhancements

### Potential Improvements
1. **Combined Artifacts**: Import file+text as single artifact
2. **Rich Preview**: Show formatted text in import list
3. **Text Search**: Allow searching text content
4. **Version History**: Track text changes if resubmitted
5. **Embedded Media**: Handle embedded images/videos in text

### Extension Points
The implementation provides hooks for:
- Additional submission types (e.g., comments, audio)
- Custom text processing
- Alternative storage methods
- Integration with other modules

## Documentation

### For Developers
- Function signatures updated with clear parameter documentation
- Inline comments explain logic flow
- Error messages use language strings
- Consistent naming conventions

### For Users
- Clear visual distinction (icons) between content types
- Helpful preview text
- Consistent import workflow
- No new permissions required

## Summary
This implementation successfully extends Exaport's assignment import functionality to handle online text submissions, providing a complete solution for importing all types of Moodle assignment submissions into student portfolios. The changes are minimal, focused, and maintain full backwards compatibility while adding significant new functionality.
