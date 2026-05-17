# Audit Log Quick Reference

## 🚀 Quick Start

### 1. Log Any Action
```php
require_once __DIR__ . '/common/helpers/audit_log_helper.php';

logAuditAction($conn, 'action_type', 'category', 'Description', [
    'student_id' => $id,
    'action_data' => ['key' => 'value'],
    'status' => 'success' // success, failed, warning, info, pending
]);
```

### 2. Common Actions

#### Login/Logout
```php
logLoginAttempt($conn, $username, 'success');  // or 'failed'
logLogout($conn, $user_id, $username);
```

#### Receipt & Payment
```php
logReceiptGeneration($conn, $student_id, $receipt_no, $fee_type, $amount, $payment_id, 'success');
logPaymentProcessing($conn, $student_id, $payment_id, $amount, $mode, $fee_types, $txn_id, 'success');
```

#### Student Operations
```php
logStudentAction($conn, 'student_created', $student_id, ['name' => $name]);
logStudentAction($conn, 'student_updated', $student_id, $changes, $old, $new);
logStudentEnrollment($conn, $student_id, $enrollment_id, 'success');
```

#### Search & Export
```php
logSearch($conn, 'student_search', $query, $result_count);
logExport($conn, 'payment_list', 'CSV', $record_count, 'payment');
```

#### Notifications
```php
logNotificationSent($conn, $student_id, 'email', $recipient, 'success');
```

### 3. Query Logs

#### Get Student Activity
```php
$logs = getStudentAuditLogs($conn, $student_id, 50);
```

#### Get Failed Actions
```php
$failed = getFailedAuditLogs($conn, 100);
```

#### Get User Activity
```php
$summary = getUserActivitySummary($conn, $user_id, '2026-01-01', '2026-01-31');
```

#### Get Session Activity
```php
$session_logs = getAuditLogsBySession($conn, session_id(), 100);
```

## 📋 Action Categories

- `authentication` - Login, logout, passwords
- `session` - Session management
- `navigation` - Page views
- `data_access` - Views, searches
- `payment` - Payments
- `receipt` - Receipts
- `enrollment` - Enrollments
- `student` - Student CRUD
- `notification` - Emails, SMS
- `sequence` - Receipt sequences
- `crud` - CRUD operations
- `report` - Reports
- `export` - Exports
- `system` - System events

## 🔍 SQL Queries

### Today's Activity
```sql
SELECT action_category, COUNT(*) as count
FROM tbl_audit_logs
WHERE DATE(created_at) = CURDATE()
GROUP BY action_category;
```

### Failed Actions
```sql
SELECT * FROM tbl_audit_logs
WHERE status = 'failed'
ORDER BY created_at DESC
LIMIT 50;
```

### User Activity
```sql
SELECT * FROM tbl_audit_logs
WHERE performed_by = ?
ORDER BY created_at DESC;
```

### Session Timeline
```sql
SELECT * FROM tbl_audit_logs
WHERE session_id = ?
ORDER BY created_at ASC;
```

## 📝 Status Values

- `success` - Action completed successfully
- `failed` - Action failed
- `warning` - Warning or security event
- `info` - Informational log
- `pending` - Action pending completion

## 🎯 Best Practices

1. **Always log after success:** Log successful operations after they complete
2. **Log failures with context:** Include error messages for failed actions
3. **Use appropriate categories:** Choose the right category for filtering
4. **Include relevant IDs:** Add student_id, payment_id, etc. for linking
5. **Don't log passwords:** System automatically filters sensitive data

## 🔧 Automatic Tracking

These are tracked automatically (no code needed):
- ✅ Page views (all GET requests)
- ✅ Login attempts
- ✅ Logout events
- ✅ Session timeouts
- ✅ Failed authentication

## 📊 Dashboard Queries

### Activity Summary
```sql
SELECT 
    DATE(created_at) as date,
    action_category,
    COUNT(*) as count,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM tbl_audit_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), action_category;
```

### Most Active Users
```sql
SELECT 
    performed_by,
    user_name,
    COUNT(*) as action_count
FROM tbl_audit_logs
WHERE DATE(created_at) = CURDATE()
    AND performed_by IS NOT NULL
GROUP BY performed_by, user_name
ORDER BY action_count DESC
LIMIT 10;
```

### Security Alerts
```sql
SELECT * FROM tbl_audit_logs
WHERE (action_type = 'login_failed' OR action_type = 'unauthorized_access')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;
```

---

**File Location:** `c:\xampp\htdocs\docs\AUDIT_LOG_QUICK_REFERENCE.md`  
**Last Updated:** January 20, 2026
