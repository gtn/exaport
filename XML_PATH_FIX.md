# XML Path Fix for Backup/Restore

## Problem
The restore paths in `restore_exaport_stepslib.php` did not match the XML structure created by the backup code, causing restore operations to fail.

## Root Cause
The backup code uses `backup_block_structure_step` as its parent class and calls `prepare_block_structure($exaport)` method. This method automatically wraps the backup structure with a `/block/` prefix in the XML.

### Backup Structure (backup_exaport_stepslib.php)
```php
class backup_exaport_block_structure_step extends backup_block_structure_step {
    protected function define_structure() {
        $exaport = new backup_nested_element('block_exaport');
        // ... define children ...
        return $this->prepare_block_structure($exaport);  // Adds /block/ prefix
    }
}
```

This creates XML paths like:
- `/block/block_exaport/course_templates/course_template`
- `/block/block_exaport/view_templates/view_template`
- `/block/block_exaport/distribution_settings/distribution_setting`

## Solution
Updated the restore paths in `restore_exaport_stepslib.php` to include the `/block/` prefix:

### Before (Incorrect)
```php
$paths[] = new restore_path_element('course_template', '/block_exaport/course_templates/course_template');
$paths[] = new restore_path_element('view_template', '/block_exaport/view_templates/view_template');
$paths[] = new restore_path_element('distribution_setting', '/block_exaport/distribution_settings/distribution_setting');
```

### After (Correct)
```php
$paths[] = new restore_path_element('course_template', '/block/block_exaport/course_templates/course_template');
$paths[] = new restore_path_element('view_template', '/block/block_exaport/view_templates/view_template');
$paths[] = new restore_path_element('distribution_setting', '/block/block_exaport/distribution_settings/distribution_setting');
```

## Impact
This fix ensures that:
1. Restore operations can find and process backed-up data correctly
2. Category templates with hierarchical structure are restored properly
3. View templates are restored correctly
4. Distribution settings are restored correctly

## Testing
To verify the fix works:
1. Create category and view templates in a course
2. Backup the course (ensure "Include blocks" is checked)
3. Restore to a new course
4. Verify all templates appear in the new course with correct structure

## Reference
- Moodle Block Backup API: The `prepare_block_structure()` method is part of the standard Moodle backup API for block plugins
- It automatically wraps block data with `/block/` prefix to avoid conflicts with other backup elements
