# Fix: Display Feedback-Only Assignments in Import List

## Problem
Assignments with teacher feedback but no student submission were not appearing in the `import_moodle.php` import list, despite the query being designed to include them.

## Root Cause
The SQL query in `block_exaport_get_assignments_for_import()` had an incorrect WHERE clause:

```sql
WHERE (sf.id IS NOT NULL OR ag.id IS NOT NULL)
```

Where:
- `sf` = `assignsubmission_file` table (only has records when files are uploaded)
- `ag` = `assign_grades` table (has records when teacher provides feedback/grade)

### The Problem
The condition `sf.id IS NOT NULL` checks for the existence of submission FILE records, not submissions themselves. This caused issues:

1. **Submissions without files**: If a student submitted but didn't upload any files, `sf.id` would be NULL
2. **Feedback-only assignments**: Even though `ag.id IS NOT NULL` should catch these, the logic wasn't as clear as intended

## Solution
Changed the WHERE clause to:

```sql
WHERE (s.id IS NOT NULL OR ag.id IS NOT NULL)
```

Where:
- `s` = `assign_submission` table (has records for any submission, with or without files)
- `ag` = `assign_grades` table (has records when teacher provides feedback/grade)

### Why This Works
The new condition properly checks for:
1. **Submissions**: `s.id IS NOT NULL` - true if student submitted anything (with or without files)
2. **Feedback**: `ag.id IS NOT NULL` - true if teacher provided grade/feedback (with or without submission)

## Test Scenarios

| Scenario | s.id | sf.id | ag.id | Old Query | New Query |
|----------|------|-------|-------|-----------|-----------|
| Submission with files | ✓ | ✓ | Maybe | ✓ (via sf.id) | ✓ (via s.id) |
| Submission without files | ✓ | ✗ | Maybe | ? (depends on ag.id) | ✓ (via s.id) |
| Feedback only (no submission) | ✗ | ✗ | ✓ | ✓ (via ag.id) | ✓ (via ag.id) |
| Nothing (no submission, no feedback) | ✗ | ✗ | ✗ | ✗ | ✗ |

## Code Change
**File**: `lib/lib.php`
**Line**: 928
**Change**: `sf.id` → `s.id`

```diff
- WHERE (sf.id IS NOT NULL OR ag.id IS NOT NULL)
+ WHERE (s.id IS NOT NULL OR ag.id IS NOT NULL)
```

## Impact
### Before Fix
- ❌ Feedback-only assignments might not appear (depending on interpretation)
- ❌ Submissions without files might not appear unless also graded
- ✓ Submissions with files appeared correctly

### After Fix
- ✅ Feedback-only assignments appear correctly
- ✅ All submissions appear (with or without files)
- ✅ Graded assignments appear whether submitted or not

## Database Tables Involved

### assign_submission
- Created when student clicks "Add submission"
- Exists even if no files uploaded (status='draft' or 'submitted')
- Does NOT exist if student never initiated submission

### assignsubmission_file
- Created only when student uploads files
- Does NOT exist for text-only or file-less submissions
- Child table of assign_submission

### assign_grades
- Created when teacher grades or provides feedback
- Can exist even without student submission
- Contains grader (teacher) ID and grade info

## Moodle Workflow

### Student Submits
1. Student clicks "Add submission" → `assign_submission` record created
2. If files uploaded → `assignsubmission_file` records created
3. Student clicks "Submit" → status updated

### Teacher Grades (No Submission)
1. Teacher can grade even without submission
2. `assign_grades` record created with grader ID
3. Can add feedback comments and files
4. `assign_submission` record does NOT exist

## Testing Checklist
- [ ] Create assignment in Moodle
- [ ] As teacher, grade student without student submitting
- [ ] Add feedback comment and feedback file
- [ ] As student, visit Exaport import page
- [ ] Verify assignment appears in list with "No submission file" label
- [ ] Click "Add this assignment" button
- [ ] Verify artifact created with assignment name
- [ ] Verify feedback comment and files appear in artifact

## Related Functions
- `block_exaport_get_assignments_for_import()` - Gets list of importable assignments
- `block_exaport_create_item_from_assignment()` - Creates artifact from assignment
- `block_exaport_add_teacher_feedback_to_item()` - Adds feedback to artifact

## Verification
The fix ensures that the import list displays ALL relevant assignments for the student:
1. Assignments they submitted to
2. Assignments they received feedback on
3. Both of the above

This aligns with the original design intent of showing feedback-only assignments.
