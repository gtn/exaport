# Manual Testing Guide for Exaport Backup/Restore and Navigation

## Testing Category Distribution Navigation

### Test 1: Verify Navigation Tab Appears
1. Log in as a teacher or manager with `block/exaport:distributecategories` capability
2. Navigate to a course
3. Add the Exaport block if not already present
4. Click on any Exaport link (e.g., "Import/Export")
5. **Expected**: You should see a "Preset structure" tab in the navigation tabs
6. Click on the "Preset structure" tab
7. **Expected**: The page loads with consistent layout matching other Exaport pages

### Test 2: Verify Page Layout Consistency
1. Navigate to "Import/Export" page
2. Note the layout, header, and navigation
3. Navigate to "Preset structure" (category_distribution.php)
4. **Expected**: The layout should match:
   - Same header style
   - Same tab navigation
   - Same page wrapper
   - Consistent breadcrumbs

### Test 3: Verify Capability Requirement
1. Log in as a student (without `block/exaport:distributecategories`)
2. Navigate to any Exaport page
3. **Expected**: The "Preset structure" tab should NOT appear
4. Try to access the page directly: `/blocks/exaport/category_distribution.php?courseid=X`
5. **Expected**: Access denied (requires capability)

## Testing Backup/Restore Functionality

### Prerequisites
- Teacher or manager role with course backup/restore capabilities
- Exaport block added to a test course

### Test 1: Basic Backup/Restore of Category Templates

#### Setup
1. Log in as a teacher
2. Navigate to a test course (Course A)
3. Go to "Preset structure" page
4. Create a category template structure:
   ```
   Main Category 1
   ├── Subcategory 1.1
   └── Subcategory 1.2
   Main Category 2
   ```
5. Set "share_to_teachers" flag on one of the categories
6. Save the structure

#### Backup
1. Go to Course Administration → Backup
2. Include blocks in the backup (ensure "Include blocks" is checked)
3. Complete the backup process
4. Download the backup file

#### Restore
1. Create a new course (Course B) or restore to another course
2. Go to Site Administration → Courses → Restore
3. Upload the backup file
4. Select "Restore into a new course" or select Course B
5. Ensure "Include blocks" is checked
6. Complete the restore process

#### Verification
1. Navigate to Course B → "Preset structure"
2. **Expected**: The category structure should be identical to Course A:
   - Same category names
   - Same hierarchy (parent-child relationships)
   - Same sortorder
   - Same share_to_teachers flags
3. Verify in database:
   ```sql
   SELECT * FROM mdl_block_exaport_course_templ WHERE courseid = [Course B ID];
   ```
4. **Expected**: Records should have new IDs but same structure
5. **Expected**: All `pid` (parent id) fields should be properly remapped to new IDs
6. **Expected**: `courseid` should be Course B's ID, not Course A's

### Test 2: Backup/Restore of View Templates

#### Setup
1. In Course A, go to "Preset structure"
2. Scroll to "View Distribution" section
3. Add 2-3 view templates:
   - View 1: "Student Portfolio" (with description)
   - View 2: "Project Showcase" (share to teachers enabled)
   - View 3: "Reflection Journal"

#### Backup and Restore
1. Follow the same backup procedure as Test 1
2. Restore to Course C

#### Verification
1. Navigate to Course C → "Preset structure"
2. Scroll to "View Distribution" section
3. **Expected**: All view templates should be present:
   - Same names
   - Same descriptions
   - Same share_to_teachers flags
   - Same sortorder
4. Verify in database:
   ```sql
   SELECT * FROM mdl_block_exaport_view_templ WHERE courseid = [Course C ID];
   ```

### Test 3: Backup/Restore of Distribution Settings

#### Setup
1. In Course A, go to "Preset structure"
2. Enable "Auto-distribute categories on enrolment" checkbox
3. Enable "Auto-distribute views on enrolment" checkbox
4. Save the settings

#### Backup and Restore
1. Backup Course A
2. Restore to Course D

#### Verification
1. Navigate to Course D → "Preset structure"
2. **Expected**: Both checkboxes should be checked
3. Verify in database:
   ```sql
   SELECT * FROM mdl_block_exaport_templ_dist WHERE courseid = [Course D ID];
   ```
4. **Expected**: `auto_distribute` = 1 and `auto_distribute_views` = 1

### Test 4: Complete Integration Test

#### Setup
1. In Course E, create a complete template:
   - Add 3-level category hierarchy
   - Add 3 view templates
   - Enable auto-distribution for both
   - Distribute to existing students

#### Backup and Restore
1. Backup Course E (with user data if desired)
2. Restore to Course F

#### Verification
1. Navigate to Course F → "Preset structure"
2. **Expected**: Complete template structure restored
3. Enroll a new student in Course F
4. **Expected**: If auto-distribution is enabled, categories and views should be created for the new student
5. Verify the newly created structures belong to the student and have correct courseid

### Test 5: Empty Course Backup/Restore

#### Test Case
1. Create Course G with no templates configured
2. Backup Course G
3. Restore to Course H
4. **Expected**: No errors, Course H has no templates (clean slate)

### Test 6: Partial Data Scenarios

#### Test 6a: Only Categories, No Views
1. Create only category templates (no view templates)
2. Backup and restore
3. **Expected**: Categories restored, no views

#### Test 6b: Only Views, No Categories
1. Create only view templates (no category templates)
2. Backup and restore
3. **Expected**: Views restored, no categories

#### Test 6c: No Distribution Settings
1. Create templates but never touch distribution settings
2. Backup and restore
3. **Expected**: Templates restored, default distribution settings (both disabled)

## Database Verification Queries

### Check Category Templates
```sql
-- Original course
SELECT id, courseid, pid, name, sortorder, share_to_teachers 
FROM mdl_block_exaport_course_templ 
WHERE courseid = [Original Course ID] 
ORDER BY sortorder;

-- Restored course
SELECT id, courseid, pid, name, sortorder, share_to_teachers 
FROM mdl_block_exaport_course_templ 
WHERE courseid = [Restored Course ID] 
ORDER BY sortorder;
```

### Check View Templates
```sql
-- Original course
SELECT id, courseid, name, description, sortorder, share_to_teachers 
FROM mdl_block_exaport_view_templ 
WHERE courseid = [Original Course ID] 
ORDER BY sortorder;

-- Restored course
SELECT id, courseid, name, description, sortorder, share_to_teachers 
FROM mdl_block_exaport_view_templ 
WHERE courseid = [Restored Course ID] 
ORDER BY sortorder;
```

### Check Distribution Settings
```sql
-- Original course
SELECT * FROM mdl_block_exaport_templ_dist WHERE courseid = [Original Course ID];

-- Restored course
SELECT * FROM mdl_block_exaport_templ_dist WHERE courseid = [Restored Course ID];
```

## Expected Results Summary

### Navigation Tests
- ✅ "Preset structure" tab appears for users with capability
- ✅ Tab does NOT appear for users without capability
- ✅ Page layout is consistent with other Exaport pages
- ✅ Breadcrumbs and navigation work correctly

### Backup/Restore Tests
- ✅ Category templates are backed up and restored with correct hierarchy
- ✅ Parent-child relationships (pid field) are properly remapped
- ✅ View templates are backed up and restored
- ✅ Distribution settings are backed up and restored
- ✅ All courseid fields are updated to new course
- ✅ No errors during backup or restore
- ✅ Auto-distribution works in restored course
- ✅ Empty courses backup/restore without errors

## Common Issues and Troubleshooting

### Issue: "Preset structure" tab not appearing
- Check user has `block/exaport:distributecategories` capability
- Check context is course context, not system context
- Clear cache and refresh page

### Issue: Backup fails
- Check PHP error logs
- Ensure backup includes blocks
- Verify tables exist and have correct structure

### Issue: Restore fails
- Check restore includes blocks
- Verify no database constraint violations
- Check that courseid exists in target system

### Issue: Category hierarchy broken after restore
- Check that parent categories are restored before child categories
- Verify `pid` mapping in restore logs
- Check sortorder is preserved

### Issue: Auto-distribution not working after restore
- Verify distribution settings were restored
- Check that new student enrollments trigger distribution
- Verify observer is properly registered

## Success Criteria

All tests pass when:
1. Navigation tab appears correctly based on capabilities
2. Page layout is consistent with existing pages
3. Templates backup without errors
4. Templates restore without errors
5. Restored data has correct courseid
6. Category hierarchy is preserved (parent-child relationships)
7. All attributes (names, descriptions, flags, sortorder) are preserved
8. Auto-distribution works in restored course
9. No database errors or warnings in logs
