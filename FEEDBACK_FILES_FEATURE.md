# Teacher Feedback Files in Assignment Export

## Overview
This feature enables students to export assignment submissions from Moodle Assignments to Exaport while preserving teacher feedback files. When a student imports an assignment submission into their portfolio, any feedback files provided by the teacher are automatically included as comments on the portfolio item.

## Feature Details

### What's New
- **Automatic Feedback File Import**: When importing assignment submissions, teacher feedback files are now automatically retrieved and saved
- **Feedback as Comments**: Feedback files are stored as comments on the portfolio item with the label "Feedback from teacher"
- **Multiple Files Support**: Handles multiple feedback files from teachers
- **Graceful Handling**: Works seamlessly whether feedback files exist or not

### User Experience
1. Student submits assignment in Moodle
2. Teacher provides feedback including file attachments (e.g., annotated PDFs, marking rubrics)
3. Student navigates to Exaport and imports the assignment submission
4. The portfolio item includes:
   - Original submission files (existing behavior)
   - Teacher feedback files as a comment (new feature)

## Technical Implementation

### Modified Files
- `import_moodle_add_file.php`: Main import handler
  - Added `get_assignment_feedback_files()` function
  - Modified `do_add()` function to save feedback files
- `lang/en/block_exaport.php`: Language strings
  - Added `feedbackfromteacher` string

### How It Works

#### 1. Feedback File Retrieval (`get_assignment_feedback_files()`)
- Retrieves feedback files from Moodle's assignment grading system
- Uses the `assignfeedback_file` component with `feedback_files` filearea
- Accesses files via the grade record (not submission record)
- Only supports modern assign module (Moodle 2.3+)

#### 2. Feedback File Storage
- Creates a comment entry in `block_exaportitemcomm` table
- Saves files to the `item_comment_file` filearea
- Links files to the comment using Moodle's file API
- Uses system context for file storage

### Database Queries
The implementation queries the following Moodle tables:
- `assign_grades`: To get the grade record for the submission
- `assignfeedback_file`: To check if feedback files exist
- `files`: To retrieve the actual feedback file objects (via Moodle file API)

### Code Flow
```
User imports assignment submission
  ↓
do_add() creates portfolio item
  ↓
get_assignment_feedback_files() retrieves feedback files
  ↓
If feedback files exist:
  - Create comment with "Feedback from teacher" text
  - Copy each feedback file to comment's file area
  ↓
Portfolio item created with submission and feedback
```

## Edge Cases Handled

### No Feedback Files
- If no feedback files exist, the import proceeds normally without errors
- No comment is created when there are no feedback files

### Multiple Feedback Files
- All feedback files are included in a single comment
- Files are copied individually to maintain proper file metadata

### Multiple Grade Records
- Uses `IGNORE_MULTIPLE` flag to handle multiple grade attempts
- Takes the first grade record (typically the most recent or active)

### Legacy Assignments
- Legacy assignment module (pre-Moodle 2.3) is not supported for feedback files
- Only submission files are imported for legacy assignments
- This is acceptable as legacy assignments had different feedback structures

## Limitations

### Portfolio Plugin Export
The generic portfolio export mechanism (when clicking "Export to portfolio" from assignment view) does not currently support feedback files. This would require:
- Access to assignment context from the portfolio exporter
- Ability to identify the source module and retrieve associated data
- This could be a future enhancement

### Feedback Types
- Only file-based feedback is imported
- Text feedback, inline comments, and rubric grades are not imported
- These are stored differently in Moodle and would require separate implementation

## Security Considerations

### Permission Checks
- Feedback files are only accessible to the student who owns the submission
- Uses existing Moodle permission model via file API
- Files are stored in the student's context in Exaport

### File Handling
- Uses Moodle's `create_file_from_storedfile()` for safe file copying
- No direct file system operations
- Maintains file metadata and permissions

## Testing Recommendations

### Manual Testing Scenarios
1. **Basic Case**: Import assignment with feedback files
   - Verify files appear as comment on portfolio item
   - Verify files are downloadable

2. **No Feedback**: Import assignment without feedback
   - Verify import succeeds without errors
   - Verify no empty comment is created

3. **Multiple Files**: Import assignment with multiple feedback files
   - Verify all files are included
   - Verify each file is accessible

4. **Large Files**: Import assignment with large feedback files
   - Verify import completes successfully
   - Verify file integrity

### Automated Testing
Currently, no automated tests exist for this feature. Recommended test cases:
- Mock assignment submission with feedback files
- Test feedback file retrieval
- Test comment creation
- Test file storage
- Test error handling

## Future Enhancements

### Potential Improvements
1. **Portfolio Plugin Support**: Enable feedback files in generic portfolio export
2. **Feedback Text**: Include text feedback in the comment
3. **Inline Annotations**: Import inline comments if available
4. **Rubric Feedback**: Include rubric feedback in portfolio item
5. **Legacy Support**: Add feedback file support for legacy assignments

### Configuration Options
Future versions could include:
- Setting to enable/disable automatic feedback import
- Option to include/exclude certain feedback types
- User preference for feedback display format

## Compatibility

### Moodle Version Support
- **Modern Assignments (assign module)**: Fully supported (Moodle 2.3+)
- **Legacy Assignments (assignment module)**: Submission files only

### Exaport Version
- Requires Exaport 5.1 or later
- Uses existing comment file infrastructure
- No database schema changes required

## References

### Related Files
- `/import_moodle_add_file.php`: Main implementation
- `/lib/lib.php`: Helper functions
- `/classes/api.php`: Comment API
- `/db/install.xml`: Database schema

### Moodle Documentation
- Assignment feedback files: `mod/assign/feedback/file/`
- File API: `lib/filelib.php`
- Portfolio API: `lib/portfoliolib.php`

## Support

### Known Issues
None at this time.

### Troubleshooting
If feedback files are not appearing:
1. Verify the assignment has feedback files in Moodle
2. Check that the feedback files plugin is enabled for the assignment
3. Verify the student has permission to view the feedback
4. Check Moodle error logs for file retrieval issues

## Changelog

### Version 5.1+ (Current)
- Initial implementation of feedback file import
- Support for modern assignment module
- Automatic comment creation with feedback files
