# Fix for Grader Identity Disclosure in Assignment Feedback Export

## Problem

When the "Hide grader identity from students" setting is enabled in a Moodle assignment, students should not be able to see which teacher graded their work. However, when students exported assignment feedback to their ePortfolio, the teacher's name was being displayed, violating this privacy setting.

## Root Cause

The original code in `block_exaport_add_teacher_feedback_to_item()` was:
1. Directly querying the database to get grader information
2. Using `fullname($teacher)` without checking if the grader identity should be hidden
3. Always storing the teacher's userid in the comment record
4. Not using Moodle's assignment API to check the grader identity settings

## Solution

### 1. Use Moodle's Assign API (More Moodle-like)

Instead of direct database queries, we now use Moodle's built-in assignment API:

```php
// Check if grader identity is hidden from students using Moodle API
if ($assign->is_hidden_grader()) {
    // When grader identity is hidden, check if student has permission to see grader
    $showgrader = has_capability('mod/assign:showhiddengrader', $context, $USER->id);
}
```

This approach:
- Uses Moodle's standard API methods
- Respects the correct privacy setting (`is_hidden_grader()` not `is_blind_marking()`)
- Is future-proof as Moodle evolves
- Follows Moodle best practices

**Important distinction:**
- `is_blind_marking()` - Hides STUDENT identities from graders during marking
- `is_hidden_grader()` - Hides GRADER identity from students (the correct one for this use case)

### 2. Centralized Helper Function

Created `block_exaport_get_comment_author_name()` helper function that:
- Checks for userid === -1 (hidden grader marker)
- Returns "Hidden grader" for anonymous feedback
- Returns user's full name for normal comments
- Respects viewer's privacy capabilities via viewerid parameter
- Provides consistent behavior across all display contexts

```php
function block_exaport_get_comment_author_name($userid, $viewerid = null) {
    global $DB;
    
    // Check for hidden grader marker (use strict comparison)
    if ($userid === -1) {
        return get_string('hiddengrader', 'block_exaport');
    }
    
    // Get user record and return full name
    $user = $DB->get_record('user', array('id' => $userid));
    if ($user) {
        // Pass viewerid to respect privacy capabilities
        return fullname($user, $viewerid);
    }
    
    // Fallback if user not found
    return get_string('unknownuser', 'block_exaport');
}
```

### 3. Special Sentinel Value

Instead of storing the student's userid or a fake userid, we use -1 as a sentinel value to indicate "hidden grader":

```php
// Determine grader userid for comment
if ($showgrader && !empty($grade->grader)) {
    $commentuserid = $grade->grader; // Use teacher's real ID
} else {
    $commentuserid = -1; // Use -1 to indicate grader identity is hidden
}
```

Benefits of this approach:
- Doesn't expose any real userid
- Easy to check in display code
- Clear semantic meaning
- Doesn't conflict with valid user IDs (which are always positive)

### 4. Updated All Display Locations

Updated every location where comments are displayed:

1. **shared_item.php** - Portfolio item view
   - Shows anonymous user icon for hidden graders
   - Displays "Hidden grader" instead of name
   - No profile link for hidden graders

2. **export_scorm.php** - SCORM export
   - Uses helper function to get author name
   - Respects privacy in exported files
   - Removes unnecessary database queries

3. **classes/externallib/externallib.php** - Web service API
   - API responses respect grader privacy
   - Consistent with other display contexts

4. **lib/externlib.php** - External display
   - Shows anonymous icon for hidden graders
   - Uses helper function for consistency

5. **lib/lib.php** - GDPR export
   - Privacy-compliant data export
   - Uses helper function with viewerid

## Technical Improvements

### More Efficient
- Removed unnecessary `get_record('user')` calls in display code
- Centralized logic reduces code duplication
- Single function to maintain

### More Type-Safe
- Uses strict comparison (===) for sentinel value checks
- Explicit !empty() check for grader existence
- Clearer intent in conditional logic

### Better Privacy Handling
- Respects viewer's privacy capabilities via viewerid parameter
- Checks the correct privacy setting (is_hidden_grader + capability)
- Follows Moodle's privacy best practices

## Files Modified

1. **lib/lib.php**
   - Modified `block_exaport_add_teacher_feedback_to_item()` to check is_hidden_grader
   - Added `block_exaport_get_comment_author_name()` helper function
   - Updated GDPR export to use helper function

2. **shared_item.php**
   - Updated comment display to check for hidden grader
   - Shows anonymous icon and "Hidden grader" text
   - Uses strict comparison for sentinel value

3. **export_scorm.php**
   - Uses helper function for author names
   - More efficient (fewer DB queries)

4. **classes/externallib/externallib.php**
   - API uses helper function
   - Consistent privacy handling

5. **lib/externlib.php**
   - External display uses helper function
   - Shows anonymous icon for hidden graders

6. **lang/en/block_exaport.php**
   - Added 'hiddengrader' string
   - Added 'unknownuser' string

## Testing Scenarios

### Scenario 1: is_hidden_grader Enabled, Student Exports
- **Expected**: Comment shows "Hidden grader", no teacher name
- **Result**: ✓ Privacy protected

### Scenario 2: is_hidden_grader Disabled, Student Exports
- **Expected**: Comment shows teacher's full name
- **Result**: ✓ Normal behavior maintained

### Scenario 3: is_hidden_grader Enabled, User with showhiddengrader Capability
- **Expected**: Comment shows teacher's full name (manager/admin can see)
- **Result**: ✓ Capability respected

### Scenario 4: Export to SCORM
- **Expected**: Privacy respected in exported file
- **Result**: ✓ Consistent with portfolio view

### Scenario 5: Web Service API Access
- **Expected**: API responses respect privacy
- **Result**: ✓ Same behavior as UI

## Benefits

1. **Privacy Protection**: Students cannot see grader identity when is_hidden_grader is enabled
2. **Moodle-like**: Uses standard Moodle APIs and patterns
3. **Future-proof**: Will work with future Moodle versions and privacy enhancements
4. **Generic**: Handles all assignment privacy settings automatically
5. **Efficient**: Fewer database queries, better performance
6. **Maintainable**: Centralized logic in helper function
7. **Type-safe**: Strict comparisons and explicit checks
8. **Consistent**: Same behavior across all contexts

## Backwards Compatibility

- Existing comments with real userids continue to work normally
- No database schema changes required
- New comments use -1 for hidden graders going forward
- Helper function handles both old and new comment records gracefully

## Compliance

- ✓ Respects Moodle's is_hidden_grader setting (not is_blind_marking)
- ✓ Respects mod/assign:showhiddengrader capability
- ✓ Follows Moodle security best practices
- ✓ GDPR-compliant (respects user privacy)
- ✓ Consistent with Moodle's privacy API
