# Maintenance Admin Role - Complete Documentation

**Date:** 28-Jan-2026  
**Module:** System Administration  
**Status:** Pending Approval

---

## 📋 Overview

Ek naya **Maintenance Admin** role create karna hai jo portal ke technical aspects manage karega. Ye role specifically system maintenance, monitoring aur debugging ke liye hoga.

---

## 👤 Role Definition

| Property | Value |
|----------|-------|
| **Role Name** | Maintenance Admin |
| **Role Code** | `ROLE_MAINTENANCE` |
| **Access Level** | Technical/Backend |
| **Primary Users** | IT Team, System Admin |

---

## 🎯 Core Modules (User Requested)

### 1. 📦 Backup Management

| Feature | Description |
|---------|-------------|
| **Database Backup** | Manual aur automatic DB backup |
| **Files Backup** | Portal files ka backup (uploads, documents) |
| **Receipt Reports** | Daily/Monthly/Yearly receipt reports |
| **Restore** | Backup se restore karna |
| **History** | Backup history dekhna |
| **Scheduling** | Task Scheduler se automatic backup |

*Details: [database_files_backup_feature.md](file:///c:/xampp/htdocs/portal/docs/database_files_backup_feature.md) aur [receipt_backup_feature.md](file:///c:/xampp/htdocs/portal/docs/receipt_backup_feature.md)*

---

### 2. 🔌 API Debugger

| Feature | Description |
|---------|-------------|
| **API Logs** | Sabhi API calls ka log dekhna |
| **Request/Response** | Full request aur response data |
| **Error Tracking** | Failed API calls highlight |
| **Filter** | Date, endpoint, status se filter |
| **Test API** | Manually API endpoint test karna |
| **Response Time** | API performance metrics |

**Dashboard View:**
```
┌─────────────────────────────────────────────────────────┐
│ API Debugger                                            │
├─────────────────────────────────────────────────────────┤
│ Total Calls Today: 1,234  │ Success: 1,200 │ Failed: 34 │
├─────────────────────────────────────────────────────────┤
│ Recent API Calls:                                       │
│ ✅ POST /api/payments/create - 234ms - 200 OK          │
│ ✅ GET  /api/students/search - 45ms  - 200 OK          │
│ ❌ POST /api/sms/send       - 5000ms - 500 Error       │
│ ✅ GET  /api/fees/config    - 89ms  - 200 OK           │
└─────────────────────────────────────────────────────────┘
```

---

### 3. 📊 System Statistics

| Feature | Description |
|---------|-------------|
| **User Stats** | Active users, logins today |
| **Database Stats** | Table sizes, row counts |
| **Storage Stats** | Disk usage, uploads size |
| **Transaction Stats** | Payments processed today |
| **Growth Charts** | Weekly/Monthly trends |

**Dashboard View:**
```
┌──────────────────┬──────────────────┬──────────────────┐
│ Active Users     │ DB Size          │ Storage Used     │
│ 45 online        │ 2.5 GB           │ 15 GB / 100 GB   │
├──────────────────┴──────────────────┴──────────────────┤
│ Today's Activity:                                      │
│ • New Registrations: 25                                │
│ • Payments: 156 (₹4,50,000)                           │
│ • Receipts Generated: 156                              │
│ • SMS Sent: 89                                         │
└────────────────────────────────────────────────────────┘
```

---

### 4. 🏥 System Health

| Feature | Description |
|---------|-------------|
| **Server Status** | Apache, MySQL running check |
| **Database Connection** | DB connectivity test |
| **Disk Space** | C: aur D: drive free space |
| **Memory Usage** | PHP memory consumption |
| **API Health** | Third-party APIs status |
| **Cron Jobs** | Scheduled tasks status |

**Dashboard View:**
```
┌─────────────────────────────────────────────────────────┐
│ System Health                               Last: 2 min │
├─────────────────────────────────────────────────────────┤
│ ✅ Apache Server      Running                           │
│ ✅ MySQL Database     Connected (45ms)                  │
│ ✅ D: Drive           85 GB free                        │
│ ⚠️ C: Drive           5 GB free (Low!)                  │
│ ✅ Payment Gateway    Online                            │
│ ✅ SMS API            Online                            │
│ ❌ Email SMTP         Offline (Check config)            │
└─────────────────────────────────────────────────────────┘
```

---

## 💡 Suggested Additional Features

### 5. 📝 Error Logs Viewer

| Feature | Description |
|---------|-------------|
| **PHP Errors** | Portal ke PHP errors dekhna |
| **Application Logs** | Custom application logs |
| **Search** | Error message se search |
| **Severity Filter** | Error, Warning, Notice filter |
| **Auto Refresh** | Real-time log monitoring |

---

### 6. ⚙️ Configuration Manager

| Feature | Description |
|---------|-------------|
| **View Configs** | System configurations dekhna |
| **Edit Configs** | Safe configs edit karna |
| **Environment** | Dev/Staging/Production toggle |
| **Cache Clear** | Application cache clear |
| **Session Management** | Active sessions dekhna/clear |

---

### 7. 📧 Email/SMS Queue Manager

| Feature | Description |
|---------|-------------|
| **Pending Queue** | Pending emails/SMS dekhna |
| **Failed Items** | Failed emails/SMS retry |
| **Delivery Status** | Delivery confirmation |
| **Templates** | Email/SMS templates manage |

---

### 8. 🔐 Security Audit

| Feature | Description |
|---------|-------------|
| **Login Attempts** | Failed login attempts log |
| **User Activity** | Who did what, when |
| **IP Tracking** | Suspicious IP detection |
| **Permission Audit** | Role-wise access report |

---

### 9. 🔄 Database Tools

| Feature | Description |
|---------|-------------|
| **Table Browser** | Database tables browse |
| **Query Runner** | Safe SELECT queries run |
| **Optimize Tables** | Table optimization |
| **Repair Tables** | Corrupted tables repair |
| **Export Data** | Selected data export |

---

### 10. 📅 Cron Job Manager

| Feature | Description |
|---------|-------------|
| **View Jobs** | Scheduled jobs list |
| **Run Now** | Manually job trigger |
| **Job History** | Past executions log |
| **Enable/Disable** | Jobs on/off karna |

---

## 🖥️ Admin Panel Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  🔧 Maintenance Admin Panel                                     │
├────────────┬────────────────────────────────────────────────────┤
│            │                                                    │
│ Dashboard  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│            │  │ Health  │ │ Stats   │ │ Errors  │ │ Backups │  │
│ Backups    │  │  OK ✅  │ │ 1.2K    │ │ 5 new   │ │ 24hr ✅ │  │
│  └ DB      │  └─────────┘ └─────────┘ └─────────┘ └─────────┘  │
│  └ Files   │                                                    │
│  └ Reports │  Quick Actions:                                    │
│            │  [Backup Now] [Clear Cache] [Check Health]         │
│ API Debug  │                                                    │
│            │  Recent Errors:                                    │
│ System     │  • Payment API timeout - 10 min ago               │
│  └ Health  │  • SMS delivery failed - 1 hr ago                 │
│  └ Stats   │                                                    │
│            │  System Status:                                    │
│ Logs       │  CPU: 25% | RAM: 4.2 GB | Disk: 85 GB free        │
│            │                                                    │
│ Settings   │                                                    │
│            │                                                    │
└────────────┴────────────────────────────────────────────────────┘
```

---

## 📁 File Structure

```
portal/
└── modules/
    └── maintenance/
        ├── index.php                 # Dashboard
        ├── backup/
        │   ├── database.php          # DB backup
        │   ├── files.php             # Files backup
        │   ├── reports.php           # Receipt reports
        │   ├── restore.php           # Restore
        │   └── history.php           # Backup history
        ├── api-debug/
        │   ├── index.php             # API logs
        │   ├── test.php              # API tester
        │   └── details.php           # Single API detail
        ├── system/
        │   ├── health.php            # System health
        │   ├── statistics.php        # System stats
        │   └── info.php              # PHP info
        ├── logs/
        │   ├── errors.php            # Error logs
        │   └── activity.php          # Activity logs
        ├── tools/
        │   ├── cache.php             # Cache management
        │   ├── sessions.php          # Session management
        │   └── database.php          # DB tools
        └── settings/
            ├── cron.php              # Cron jobs
            └── config.php            # Configurations
```

---

## 🔐 Access Control

| Module | Maintenance Admin | Super Admin | Regular Admin |
|--------|-------------------|-------------|---------------|
| Backup Management | ✅ Full | ✅ Full | ❌ No |
| API Debugger | ✅ Full | ✅ View | ❌ No |
| System Health | ✅ Full | ✅ View | ❌ No |
| System Statistics | ✅ Full | ✅ View | ❌ No |
| Error Logs | ✅ Full | ✅ View | ❌ No |
| Database Tools | ✅ Full | ❌ No | ❌ No |
| Configuration | ✅ Full | ✅ View | ❌ No |

---

## ⚠️ Requirements

1. **New Role** - `ROLE_MAINTENANCE` database mein add karna
2. **Menu Items** - Sidebar mein Maintenance section
3. **Permission System** - Role-based access
4. **Logging** - All actions log honi chahiye

---

## 🧪 Testing Checklist

- [ ] Role create aur assign karna works
- [ ] Backup manually aur automatically works
- [ ] API logs capture ho rahe hain
- [ ] System health correct status show karta hai
- [ ] Error logs readable hain
- [ ] Restore function safe hai

---

## 📌 Priority Order (Suggested)

| Priority | Module | Reason |
|----------|--------|--------|
| 1 | Backup Management | Data security critical |
| 2 | System Health | Proactive monitoring |
| 3 | Error Logs | Debugging essential |
| 4 | API Debugger | Third-party integration |
| 5 | System Statistics | Analytics |
| 6 | Others | Nice to have |

---

## 👥 Team Contact

For queries, contact the development team.

---

*Document created for team review and approval.*
