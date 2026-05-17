# Cronjob & Automated Tasks - Complete Documentation

This document serves as the central authoritative source for all scheduled tasks (cronjobs) within the portal system. These tasks are primarily managed through the **Windows Task Scheduler** on the server.

---

## 📅 Summary of Scheduled Tasks

| Task Name | Schedule | Script Path | Purpose |
|-----------|----------|-------------|---------|
| **Daily Receipt Report** | Daily 11:59 PM | `portal/cron/receipt_report_cron.php --type=daily` | Generates daily collection reports in Excel/PDF. |
| **Monthly Receipt Report** | 1st of month 12:30 AM | `portal/cron/receipt_report_cron.php --type=monthly` | Generates monthly collection reports. |
| **Yearly Receipt Report** | 1st Jan 1:00 AM | `portal/cron/receipt_report_cron.php --type=yearly` | Generates yearly collection reports. |
| **Daily DB Backup** | Every 8 Hours | `portal/cron/backup_cron.php --type=daily --target=database` | Full MySQL database backup. |
| **Daily Files Backup** | Daily 2:30 AM | `portal/cron/backup_cron.php --type=daily --target=files` | Backup of student uploads and documents. |

---

## 🛠️ Script Details

### 1. `receipt_report_cron.php`
- **Location:** `C:\xampp\htdocs\portal\cron\receipt_report_cron.php`
- **Functionality:** Fetches payment data for the specified period, creates Excel (using PHPSpreadsheet) and PDF (using TCPDF) files, and saves them to `D:/portal_backups/receipt_reports/`.
- **Logs:** Saved in `D:/portal_backups/receipt_reports/logs/`.

### 2. `backup_cron.php`
- **Location:** `C:\xampp\htdocs\portal\cron\backup_cron.php`
- **Functionality:** 
    - **Database:** Uses `mysqldump` to create SQL backups.
    - **Files:** Zips the `uploads/` directory into a date-stamped archive.
- **Backups Location:** `D:/portal_backups/database/` and `D:/portal_backups/files/`.
- **Logs:** Saved in `D:/portal_backups/logs/`.

---

## ⏰ Windows Task Scheduler Setup Guide

To set up or modify a scheduled task, follow these steps on the server:

### Step 1: Open Task Scheduler
- Start Menu → Search "**Task Scheduler**" → Open.

### Step 2: Create a Basic Task
- Click **Action** → **Create Basic Task**.
- Enter **Name** (e.g., `Portal Daily Backup`) and **Description**.

### Step 3: Set Trigger
- Choose Frequency (Daily/Weekly/Monthly) and set the **Time** according to the table above.

### Step 4: Configure Action
- Selection: **Start a program**.
- **Program/script:** `C:\xampp\php\php.exe`
- **Add arguments:** `C:\xampp\htdocs\portal\cron\filename.php --arguments`
- **Start in:** `C:\xampp\htdocs\portal\cron`

### Step 5: Security Settings
- Once created, right-click the task → **Properties**.
- Select "**Run whether user is logged on or not**".
- Select "**Run with highest privileges**".

---

## 📁 Storage

All backups and reports are stored on the **D: Drive** to ensure safety in case of C: drive failure.

---

## 🏥 Health Monitoring

The status of these jobs can be monitored through the **Maintenance Admin Panel** under the **Cron Job Manager** section.

- **Check Logs:** If a task fails, check the respective log files in `D:/portal_backups/`.
- **Manual Trigger:** If a cron fails, it can be triggered manually from the Maintenance Admin UI using the "Run Now" button.

---

*Last Updated: 15-Feb-2026*
