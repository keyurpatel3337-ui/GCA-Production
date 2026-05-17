# Database & Files Backup Feature - Technical Documentation

**Date:** 28-Jan-2026  
**Module:** System Administration  
**Status:** Pending Approval

---

## 📋 Summary

Complete system backup solution - Database aur Portal Files ka regular backup D: Drive par. Manual aur Automatic dono options available.

---

## 🗄️ Backup Types

### 1. Database Backup

| Feature | Description |
|---------|-------------|
| **What** | Complete MySQL database (`.sql` file) |
| **Includes** | All tables, data, stored procedures |
| **Format** | `.sql` (plain) ya `.sql.gz` (compressed) |

### 2. Files Backup

| Feature | Description |
|---------|-------------|
| **What** | Portal files (uploads, documents, images) |
| **Includes** | Student photos, documents, attachments |
| **Format** | `.zip` (compressed archive) |

---

## 📁 Backup Location

```
D:/
└── portal_backups/
    ├── database/
    │   ├── daily/
    │   │   ├── DB_2026-01-28.sql
    │   │   └── ...
    │   ├── monthly/
    │   │   ├── DB_2026-01.sql.gz
    │   │   └── ...
    │   └── yearly/
    │       ├── DB_2026.sql.gz
    │       └── ...
    └── files/
        ├── daily/
        │   ├── Files_2026-01-28.zip
        │   └── ...
        ├── monthly/
        │   ├── Files_2026-01.zip
        │   └── ...
        └── yearly/
            ├── Files_2026.zip
            └── ...
```

---

## 🔧 Features

### Manual Backup (Admin Panel se)

| Action | Description |
|--------|-------------|
| **Backup Now** | Turant database/files ka backup lein |
| **Download** | Backup file download karein |
| **Restore** | Kisi backup se restore karein |
| **Delete Old** | Purane backups delete karein |

### Automatic Backup (Scheduled)

| Schedule | Database | Files |
|----------|----------|-------|
| **Daily** | Roz raat 2:00 AM | Roz raat 2:30 AM |
| **Weekly** | Har Sunday | Har Sunday |
| **Monthly** | Har mahine 1 tarikh | Har mahine 1 tarikh |

---

## 🛡️ Retention Policy (Kitne din rakhna hai)

| Backup Type | Keep For |
|-------------|----------|
| Daily backups | 7 days |
| Weekly backups | 4 weeks |
| Monthly backups | 12 months |
| Yearly backups | Forever |

*Purane backups automatically delete honge*

---

## 📊 Admin Panel UI

### Backup Dashboard mein dikhega:

- **Last Backup:** 28-Jan-2026, 02:00 AM ✅
- **Next Scheduled:** 29-Jan-2026, 02:00 AM
- **Total Backups:** 45 files (1.2 GB)
- **D: Drive Space:** 50 GB free

### Buttons:
- 🔵 **Backup Database Now**
- 🔵 **Backup Files Now**
- 🔵 **Backup All Now**
- 📥 **Download Latest**
- 🔄 **Restore from Backup**
- 📋 **View Backup History**

---

## 📊 Storage Estimate

| Item | Daily Size | Monthly Total |
|------|------------|---------------|
| Database (.sql) | ~5-20 MB | ~150-600 MB |
| Database (.sql.gz) | ~1-5 MB | ~30-150 MB |
| Files (.zip) | ~50-200 MB | ~1.5-6 GB |

---

## ⚠️ Requirements

1. **D: Drive** - Minimum 50 GB free space recommended
2. **mysqldump** - XAMPP mein included hai
3. **ZipArchive** - PHP extension (usually enabled)
4. **Windows Task Scheduler** - Automatic backups ke liye

---

## 🧪 Testing Steps

### Manual Backup Test:
1. Admin login karein
2. Settings → Backup & Restore jayein
3. "Backup Database Now" click karein
4. D: drive par file check karein

### Automatic Backup Test:
1. Task Scheduler mein task setup karein
2. 5 minutes baad run hone ke liye set karein
3. Verify backup created

---

## ⏰ Windows Task Scheduler Setup (Automatic Backup)

> [!TIP]
> **Recommended:** Refer to the consolidated [Cronjob Documentation](file:///c:/xampp/htdocs/portal/docs/cronjob_documentation.md) for a complete system-wide setup guide.

### Kaise kaam karega:

1. Hum ek PHP script banayenge: `portal/cron/backup_cron.php`
2. Windows Task Scheduler is script ko scheduled time par run karega
3. Script automatically D: drive par backup save karegi

---

### Step-by-Step Setup:

#### Step 1: Task Scheduler kholein
```
Start Menu → Search "Task Scheduler" → Open
```

#### Step 2: New Task banayein
```
Action → Create Basic Task
```

#### Step 3: Task Details bharein

| Field | Value |
|-------|-------|
| **Name** | Portal Daily Backup |
| **Description** | Automatic database and files backup |
| **Trigger** | Daily, 2:00 AM |
| **Action** | Start a program |

#### Step 4: Program Settings

| Field | Value |
|-------|-------|
| **Program/script** | `C:\xampp\php\php.exe` |
| **Arguments** | `C:\xampp\htdocs\portal\cron\backup_cron.php` |
| **Start in** | `C:\xampp\htdocs\portal\cron` |

#### Step 5: Additional Settings
- ✅ "Run whether user is logged on or not"
- ✅ "Run with highest privileges"

---

### Backup Script (`backup_cron.php`) kya karegi:

```
1. Check karegi kaun sa backup lena hai (daily/monthly/yearly)
2. mysqldump se database backup legi
3. Files ko zip karegi
4. D: drive par save karegi
5. Purane backups (retention policy ke hisaab se) delete karegi
6. Log file mein entry karegi
```

---

### Multiple Tasks Setup:

| Task Name | Schedule | Script Argument |
|-----------|----------|-----------------|
| Portal Daily Backup | Daily 2:00 AM | `--type=daily` |
| Portal Monthly Backup | 1st of month, 3:00 AM | `--type=monthly` |
| Portal Yearly Backup | 1st Jan, 4:00 AM | `--type=yearly` |

---

### Logs Location:
```
D:/portal_backups/logs/
├── backup_2026-01-28.log
├── backup_2026-01-29.log
└── ...
```

---

## 🔐 Security Considerations

- Backup files mein sensitive data hai
- D: drive par proper permissions hone chahiye
- Backup passwords se encrypt ho sakti hain (optional)
- Remote backup (FTP/Cloud) bhi available hai (optional)

---

## 👥 Team Contact

For queries, contact the development team.

---

*Document created for team review and approval.*
