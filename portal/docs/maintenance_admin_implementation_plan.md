# Maintenance Admin - Implementation Plan

**Date:** 28-Jan-2026  
**Version:** 1.0  
**Status:** Pending Approval

---

## 📑 Table of Contents

1. [Overview](#overview)
2. [Existing System Changes](#existing-system-changes)
3. [New Files to Create](#new-files-to-create)
4. [Module-wise Implementation](#module-wise-implementation)
5. [Database Changes](#database-changes)
6. [Task Scheduler Setup](#task-scheduler-setup)
7. [Testing Plan](#testing-plan)
8. [Timeline](#timeline)

---

## 📋 Overview

### Kya banana hai?
Ek naya **Maintenance Admin** role jo system ke technical aspects dekhega:
- Backups manage karega
- API debugging karega
- System health monitor karega
- Error logs dekhega
- And more...

### Kahan save hoga data?
- Backups: `D:/portal_backups/`
- Logs: Existing log system + new tables

---

# 🔧 EXISTING SYSTEM CHANGES

> Ye files already exist karti hain - inme modifications karni hain

---

## File 1: globalvariable.php

**Path:** `c:\xampp\htdocs\portal\common\globalvariable.php`

**Current Code (Line 123-124):**
```php
if (!defined('ROLE_WEBSITE_ADMIN')) {
    define('ROLE_WEBSITE_ADMIN', 6);
}
```

**Add After This (Line 126+):**
```php
// Maintenance Admin Role
if (!defined('ROLE_MAINTENANCE')) {
    define('ROLE_MAINTENANCE', 7);
}
```

**Explanation:** 
- Naya role constant add karna hai
- ID 7 dena hai (kyunki 6 already WEBSITE_ADMIN ke liye hai)

---

## File 2: sidebar.php

**Path:** `c:\xampp\htdocs\portal\include\sidebar.php`

**Current Code (approx Line 37):**
```php
} elseif (hasRole(ROLE_WEBSITE_ADMIN)) {
    $sidebar_type = 'website';
}
```

**Add After This:**
```php
} elseif (hasRole(ROLE_MAINTENANCE)) {
    $sidebar_type = 'maintenance';
}
```

**Also Add in Menu Section:**
```php
// Maintenance Admin Menu Items
if (hasRole(ROLE_MAINTENANCE) || hasRole(ROLE_SUPER_ADMIN)) {
    // Add Maintenance menu items here
}
```

---

## File 3: Database - tbl_roles

**Table:** `tbl_roles`

**Add New Row:**
```sql
INSERT INTO tbl_roles (id, role_name, role_slug, description, created_at) 
VALUES (7, 'Maintenance Admin', 'maintenance', 'System maintenance and monitoring', NOW());
```

---

# 📁 NEW FILES TO CREATE

> Ye nayi files aur folders create karni hain

---

## Folder Structure

```
c:\xampp\htdocs\portal\modules\maintenance\
├── index.php                      # Dashboard
├── controllers\
│   ├── backup_controller.php      # Backup operations
│   ├── api_log_controller.php     # API log operations  
│   ├── system_controller.php      # System operations
│   └── log_controller.php         # Error log operations
├── backup\
│   ├── database.php               # Database backup page
│   ├── files.php                  # Files backup page
│   ├── receipt-reports.php        # Receipt reports page
│   ├── history.php                # Backup history
│   └── restore.php                # Restore page
├── api-debug\
│   ├── index.php                  # API logs list
│   ├── details.php                # Single log details
│   └── test.php                   # API tester
├── system\
│   ├── health.php                 # System health check
│   ├── statistics.php             # System statistics
│   └── info.php                   # PHP/Server info
├── logs\
│   ├── errors.php                 # Error logs viewer
│   ├── activity.php               # User activity logs
│   └── security.php               # Security audit logs
├── tools\
│   ├── cache.php                  # Cache management
│   ├── sessions.php               # Session manager
│   ├── database.php               # DB tools
│   └── queue.php                  # Email/SMS queue
├── cron\
│   └── index.php                  # Cron job manager
└── settings\
    └── config.php                 # Configurations
```

---

## Cron Scripts (for Task Scheduler)

```
c:\xampp\htdocs\portal\cron\
├── backup_cron.php                # Database & Files backup
├── receipt_report_cron.php        # Receipt reports generation
├── cleanup_cron.php               # Old backups cleanup
└── health_check_cron.php          # System health monitoring
```

---

# 📦 MODULE-WISE IMPLEMENTATION

---

## Module 1: Backup Management

### 1.1 Database Backup

**File:** `modules/maintenance/backup/database.php`

**Features:**
| Feature | How it works |
|---------|--------------|
| Backup Now | `mysqldump` command se .sql file create |
| Compress | Optional .gz compression |
| Download | PHP force download |
| Schedule | Task Scheduler integration |

**Backend Logic:**
```php
// Example backup function
function backupDatabase() {
    $filename = 'DB_' . date('Y-m-d_His') . '.sql';
    $path = 'D:/portal_backups/database/daily/' . $filename;
    
    $command = "mysqldump -u root -p'' gca_counselling > {$path}";
    exec($command, $output, $return);
    
    return $return === 0 ? $path : false;
}
```

---

### 1.2 Files Backup

**File:** `modules/maintenance/backup/files.php`

**What to backup:**
- `uploads/` folder (student documents, photos)
- `counselling-backend/uploads/` folder
- Optional: Config files

**Backend Logic:**
```php
function backupFiles() {
    $zip = new ZipArchive();
    $filename = 'Files_' . date('Y-m-d') . '.zip';
    $path = 'D:/portal_backups/files/daily/' . $filename;
    
    $zip->open($path, ZipArchive::CREATE);
    addFolderToZip($zip, 'c:/xampp/htdocs/uploads');
    $zip->close();
    
    return $path;
}
```

---

### 1.3 Receipt Reports

**File:** `modules/maintenance/backup/receipt-reports.php`

**Features:**
| Feature | Description |
|---------|-------------|
| Daily Report | Ek din ki saari receipts |
| Monthly Report | Ek mahine ki saari receipts |
| Yearly Report | Ek saal ki saari receipts |
| Format | Excel (.xlsx) + PDF |

**Database Query:**
```sql
SELECT 
    p.receipt_no,
    p.payment_date,
    CONCAT(s.surname, ' ', s.student_name) as student_name,
    s.standard,
    p.payment_type,
    p.payment_mode,
    p.amount,
    p.transaction_id
FROM tbl_payments p
INNER JOIN tbl_gm_std_registration s ON p.student_id = s.id
WHERE DATE(p.payment_date) = CURDATE()
ORDER BY p.payment_date DESC
```

---

## Module 2: API Debugger

### What it needs:

**New Table:** `tbl_api_logs`
```sql
CREATE TABLE tbl_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255),
    method ENUM('GET', 'POST', 'PUT', 'DELETE'),
    request_headers TEXT,
    request_body TEXT,
    response_code INT,
    response_body TEXT,
    response_time_ms INT,
    ip_address VARCHAR(45),
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at),
    INDEX idx_response_code (response_code)
);
```

**Middleware File:** `common/api_logger.php`
```php
function logApiCall($endpoint, $method, $request, $response, $responseCode, $timeMs) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO tbl_api_logs 
        (endpoint, method, request_body, response_body, response_code, response_time_ms, ip_address, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $endpoint,
        $method,
        json_encode($request),
        json_encode($response),
        $responseCode,
        $timeMs,
        $_SERVER['REMOTE_ADDR'],
        $_SESSION['user_id'] ?? null
    ]);
}
```

**Where to add logging:**
- Modify existing API files in `portal/api/` folder
- Add logging call at end of each API

---

## Module 3: System Health

**File:** `modules/maintenance/system/health.php`

**Checks to perform:**
| Check | How to check | Good Status |
|-------|--------------|-------------|
| Apache | Port 80 check | Running |
| MySQL | PDO connect test | Connected |
| D: Drive Space | `disk_free_space('D:')` | > 10 GB |
| C: Drive Space | `disk_free_space('C:')` | > 5 GB |
| PHP Memory | `memory_get_usage()` | < 80% |
| Payment Gateway | API ping | Online |
| SMS API | API ping | Online |
| Last Backup | Check last backup file | < 24 hrs |

**Backend Logic:**
```php
function getSystemHealth() {
    $health = [];
    
    // MySQL Check
    try {
        $conn->query('SELECT 1');
        $health['mysql'] = ['status' => 'ok', 'message' => 'Connected'];
    } catch (Exception $e) {
        $health['mysql'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // Disk Space Check
    $d_drive_free = disk_free_space('D:') / 1024 / 1024 / 1024; // GB
    $health['d_drive'] = [
        'status' => $d_drive_free > 10 ? 'ok' : ($d_drive_free > 5 ? 'warning' : 'error'),
        'message' => round($d_drive_free, 2) . ' GB free'
    ];
    
    return $health;
}
```

---

## Module 4: System Statistics

**File:** `modules/maintenance/system/statistics.php`

**Statistics to show:**
| Stat | Query/Method |
|------|--------------|
| Total Students | `SELECT COUNT(*) FROM tbl_gm_std_registration` |
| Active Enrollments | `SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1` |
| Today's Payments | `SELECT COUNT(*), SUM(amount) FROM tbl_payments WHERE DATE(payment_date) = CURDATE()` |
| DB Size | `SELECT table_schema, SUM(data_length+index_length)/1024/1024 as size_mb FROM information_schema.tables` |
| Active Users | Count active sessions |
| API Calls Today | `SELECT COUNT(*) FROM tbl_api_logs WHERE DATE(created_at) = CURDATE()` |

---

## Module 5: Error Logs Viewer

**File:** `modules/maintenance/logs/errors.php`

**Log Files to Read:**
| Log | Path |
|-----|------|
| PHP Errors | `c:\xampp\php\logs\php_error_log` |
| Apache Errors | `c:\xampp\apache\logs\error.log` |
| Application Logs | `c:\xampp\htdocs\common\logs\*.log` |

**Features:**
- Real-time log viewing
- Search functionality
- Severity filter (Error, Warning, Notice)
- Date filter
- Clear logs option

---

## Module 6: Configuration Manager

**File:** `modules/maintenance/settings/config.php`

**Configs to manage:**
| Config | Source |
|--------|--------|
| Database | `env.config.php` (view only) |
| SMTP | `tbl_smtp_config` table |
| Payment Gateway | `tbl_payment_gateways` table |
| API Keys | `tbl_api_config` table |
| System Settings | `tbl_settings` table |

**Security:** 
- Sensitive data masked (passwords show as *****)
- Changes logged in audit trail

---

## Module 7: Email/SMS Queue

**File:** `modules/maintenance/tools/queue.php`

**New Table:** `tbl_notification_queue`
```sql
CREATE TABLE tbl_notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('email', 'sms', 'whatsapp'),
    recipient VARCHAR(255),
    subject VARCHAR(255),
    message TEXT,
    status ENUM('pending', 'sent', 'failed'),
    retry_count INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_type (type)
);
```

**Features:**
- View pending notifications
- Retry failed ones
- View delivery status
- Cancel pending

---

## Module 8: Security Audit

**File:** `modules/maintenance/logs/security.php`

**Track in existing `tbl_activity_log` or new table:**
| Event | What to log |
|-------|-------------|
| Login Success | User ID, IP, Time |
| Login Failed | Attempted email, IP, Time |
| Password Change | User ID, Time |
| Permission Change | Who changed, what changed |
| Data Export | Who exported, what data |
| Backup Created | Who, when |

---

## Module 9: Database Tools

**File:** `modules/maintenance/tools/database.php`

**Features:**
| Feature | Safety | Description |
|---------|--------|-------------|
| Table Browser | ✅ Safe | View table structure |
| Row Count | ✅ Safe | See row counts |
| Query Runner | ⚠️ SELECT only | Run read-only queries |
| Optimize | ✅ Safe | `OPTIMIZE TABLE` |
| Repair | ✅ Safe | `REPAIR TABLE` |

**Security:**
- Only SELECT queries allowed
- No DROP, DELETE, UPDATE, INSERT
- Query logging enabled

---

## Module 10: Cron Job Manager

**File:** `modules/maintenance/cron/index.php`

**Features:**
| Feature | Description |
|---------|-------------|
| View Jobs | List all scheduled tasks |
| Last Run | When job last ran |
| Next Run | When job will run next |
| Run Now | Manually trigger job |
| Enable/Disable | Toggle job status |
| View Logs | See job execution history |

---

# 🗃️ DATABASE CHANGES SUMMARY

## New Tables to Create:

| Table | Purpose |
|-------|---------|
| `tbl_api_logs` | API call logging |
| `tbl_notification_queue` | Email/SMS queue |
| `tbl_backup_history` | Backup records |
| `tbl_cron_jobs` | Scheduled jobs |
| `tbl_cron_logs` | Cron execution history |
| `tbl_system_health_log` | Health check history |

## Existing Tables to Modify:

| Table | Change |
|-------|--------|
| `tbl_roles` | Add Maintenance Admin role (ID: 7) |
| `tbl_activity_log` | Add more action types if needed |

---

# ⏰ TASK SCHEDULER SETUP

> [!IMPORTANT]
> All Task Scheduler setup instructions and scheduling tables have been consolidated into the [Cronjob & Automated Tasks Documentation](file:///c:/xampp/htdocs/portal/docs/cronjob_documentation.md). Please refer to that document for the most up-to-date setup guide.

---

# 🧪 TESTING PLAN

## Phase 1: Role Setup
- [ ] Role add ho gaya database mein
- [ ] User create ho gaya with new role
- [ ] Login work kar raha hai
- [ ] Sidebar sahi dikh raha hai

## Phase 2: Backup Module
- [ ] Database backup manual work karta hai
- [ ] Files backup work karta hai
- [ ] D: drive par save ho raha hai
- [ ] Download work karta hai
- [ ] Restore work karta hai (test environment mein)

## Phase 3: Monitoring Modules
- [ ] API logs capture ho rahe hain
- [ ] System health sahi status dikhata hai
- [ ] Error logs readable hain
- [ ] Statistics accurate hain

## Phase 4: Automation
- [ ] Task Scheduler tasks create
- [ ] Automatic backup work karta hai
- [ ] Cleanup purane files delete karta hai

---

# 📅 TIMELINE (Suggested)

| Phase | Duration | Modules |
|-------|----------|---------|
| Week 1 | 5 days | Role setup, Backup Management |
| Week 2 | 5 days | API Debugger, System Health, Statistics |
| Week 3 | 5 days | Error Logs, Config Manager, Queue |
| Week 4 | 5 days | Security Audit, DB Tools, Cron Manager |
| Week 5 | 3 days | Testing, Bug fixes, Documentation |

**Total: ~23 working days**

---

# ✅ APPROVAL CHECKLIST

Please confirm:
1. [ ] Overall approach theek hai?
2. [ ] D: drive backup location OK?
3. [ ] Sabhi 10 modules chahiye?
4. [ ] Timeline acceptable hai?
5. [ ] Kuch add/remove karna hai?

---

*Document ready for team review and approval.*
