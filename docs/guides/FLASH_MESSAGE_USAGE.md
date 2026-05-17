# Flash Message System - Usage Guide

## Problem Solved
पहले session messages सभी pages पर दिखाई देते थे। अब ये **सिर्फ एक बार** दिखेंगे और automatically clear हो जाएंगे।

## Features
✅ Messages automatically clear after display  
✅ Support for multiple message types (success, error, warning, info)  
✅ Prevents duplicate messages  
✅ Auto-expires after 5 minutes  
✅ Backward compatible with old `$_SESSION['error_msg']` and `$_SESSION['success_msg']`  
✅ Automatic migration from old session messages

## Installation
Already installed! Flash message system is auto-loaded in `session_config.php`.

## Usage Examples

### 1. Set Success Message (सफलता संदेश)
```php
// Old way (still works but converts to flash)
$_SESSION['success_msg'] = "Student added successfully!";

// New way (recommended)
set_flash_message('success', "Student added successfully!");

// After redirect
header('Location: list.php');
exit;
```

### 2. Set Error Message (त्रुटि संदेश)
```php
// Old way
$_SESSION['error_msg'] = "Failed to delete student!";

// New way
set_flash_message('error', "Failed to delete student!");
```

### 3. Set Warning Message (चेतावनी संदेश)
```php
set_flash_message('warning', "Please complete profile details!");
```

### 4. Set Info Message (सूचना संदेश)
```php
set_flash_message('info', "Your session will expire in 5 minutes");
```

### 5. Preserve Message (संदेश को बनाए रखें)
```php
// This message will NOT auto-clear (useful for persistent warnings)
set_flash_message('warning', "Complete KYC verification", true);

// Clear it manually when needed
clear_flash_messages('warning');
```

## Display Messages

### Automatic Display (recommended)
Messages are automatically displayed by `footer.php` using SweetAlert.  
**No extra code needed!**

### Manual Display (for custom pages)
```php
<?php display_flash_messages(); ?>
```

This will render Bootstrap alert boxes:
```html
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> Student added successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

## Check if Messages Exist

```php
// Check any message
if (has_flash_messages()) {
    echo "There are pending messages";
}

// Check specific type
if (has_flash_messages('error')) {
    echo "There are error messages";
}
```

## Clear Messages Manually

```php
// Clear all messages
clear_flash_messages();

// Clear only errors
clear_flash_messages('error');

// Clear only success
clear_flash_messages('success');
```

## Real-World Examples

### Example 1: Student Form Submission
```php
// add-student.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student->add($_POST);
        set_flash_message('success', "Student {$_POST['name']} added successfully!");
        header('Location: list.php');
        exit;
    } catch (Exception $e) {
        set_flash_message('error', "Failed to add student: " . $e->getMessage());
    }
}
```

### Example 2: Delete Operation
```php
// delete-student.php
try {
    $student->delete($_GET['id']);
    set_flash_message('success', "Student deleted successfully!");
} catch (Exception $e) {
    set_flash_message('error', "Cannot delete: Student has active enrollments");
}
header('Location: list.php');
exit;
```

### Example 3: Login Validation
```php
// login.php
if (!$user->verify($username, $password)) {
    set_flash_message('error', "Invalid username or password!");
    header('Location: login.php');
    exit;
}

set_flash_message('success', "Welcome back, {$user->name}!");
header('Location: dashboard.php');
exit;
```

### Example 4: API Response
```php
// api/students/add.php
if ($result) {
    set_flash_message('success', "Student registered successfully!");
    jsonResponse(['success' => true, 'redirect' => 'list.php']);
} else {
    set_flash_message('error', "Registration failed. Please try again.");
    jsonResponse(['success' => false]);
}
```

## Migration from Old System

### Old Code:
```php
$_SESSION['success_msg'] = "Operation completed";
$_SESSION['error_msg'] = "Something went wrong";
```

### Works automatically!
The system automatically converts old-style messages to flash messages.

### New Code (recommended):
```php
set_flash_message('success', "Operation completed");
set_flash_message('error', "Something went wrong");
```

## Benefits

### Before (Old System)
❌ Message दिखता रहता था multiple pages पर  
❌ Manual `unset()` करना पड़ता था  
❌ Race conditions थे  
❌ Duplicate messages दिखते थे

### After (Flash System)
✅ Message **सिर्फ एक बार** दिखता है  
✅ Automatic cleanup  
✅ Thread-safe  
✅ Duplicate prevention  
✅ Auto-expiry (5 minutes)

## Technical Details

### Message Structure
```php
$_SESSION['flash_messages'] = [
    'abc123' => [
        'type' => 'success',
        'message' => 'Operation completed',
        'preserve' => false,
        'created_at' => 1234567890
    ]
];
```

### Auto-Cleanup
- Messages are cleared after being fetched once
- Expired messages (>5 minutes old) are auto-deleted
- Preserved messages remain until manually cleared

### Thread Safety
Each message has a unique ID (MD5 hash) to prevent duplicates and race conditions.

## Troubleshooting

### Messages not showing?
1. Check if `session_config.php` is included
2. Verify `footer.php` is included in your page
3. Check browser console for JavaScript errors
4. Ensure SweetAlert2 is loaded

### Messages showing multiple times?
- This should NOT happen with the new system
- Old code might still be using `$_SESSION['error_msg']` directly
- Convert to `set_flash_message()` function

### Messages disappearing before seeing them?
- Messages expire after 5 minutes
- Check if multiple tabs are open (message shows in first tab only)
- Use `preserve` parameter if message should persist

## API Functions

| Function | Purpose |
|----------|---------|
| `set_flash_message($type, $message, $preserve)` | Set a message |
| `get_flash_messages($clear)` | Get messages (and optionally clear) |
| `has_flash_messages($type)` | Check if messages exist |
| `clear_flash_messages($type)` | Clear messages |
| `display_flash_messages()` | Display as HTML alerts |
| `migrate_old_session_messages()` | Auto-migrate old messages |

## Support
For issues or questions, check the error log or contact the development team.
