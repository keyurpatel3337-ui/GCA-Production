# Receipt Reports & Backup Feature - Technical Documentation

**Date:** 28-Jan-2026  
**Module:** Payments / Reports  
**Status:** Pending Approval

---

## 📋 Summary

Sabhi receipts ka consolidated report generate karne ka feature - Daily, Monthly aur Yearly basis par. Reports **Excel** aur **PDF** dono format mein available hongi aur D: Drive par automatically save hongi.

---

## 📊 Report Types

| Report | Description | Format |
|--------|-------------|--------|
| **Daily Report** | Ek din ki saari receipts | Excel + PDF |
| **Monthly Report** | Poore mahine ki saari receipts | Excel + PDF |
| **Yearly Report** | Poore saal ki saari receipts | Excel + PDF |

---

## 📁 Backup Location

```
D:/
└── portal_backups/
    └── receipt_reports/
        ├── daily/
        │   ├── DailyReport_2026-01-28.xlsx
        │   ├── DailyReport_2026-01-28.pdf
        │   └── ...
        ├── monthly/
        │   ├── MonthlyReport_2026-01.xlsx
        │   ├── MonthlyReport_2026-01.pdf
        │   └── ...
        └── yearly/
            ├── YearlyReport_2026.xlsx
            ├── YearlyReport_2026.pdf
            └── ...
```

---

## 📄 Report Content (Fields)

| Column | Description |
|--------|-------------|
| Sr. No. | Serial number |
| Receipt No. | Receipt number |
| Date | Payment date |
| Student Name | Full name |
| Roll No. | Student roll number |
| Standard | Class/Standard |
| Fee Type | School Fee, Tuition Fee, etc. |
| Payment Mode | Cash/Online/Cheque |
| Amount | Payment amount |
| Transaction ID | For online payments |

---

## 🔧 Features

### Manual Generation
- Admin panel mein button se kisi bhi date range ki report generate karein
- Download ya server par save karne ka option

### Automatic Generation
- **Daily:** Har raat 11:59 PM par us din ki report auto-generate
- **Monthly:** Har mahine ki 1 tarikh ko previous month ki report
- **Yearly:** Har saal 1 January ko previous year ki report

---

## ⚠️ Requirements

1. **D: Drive** - Server par exist aur accessible
2. **PHPSpreadsheet** - Excel generation ke liye (composer package)
3. **TCPDF** - PDF generation ke liye (already installed)
4. **Windows Task Scheduler** - Automatic reports ke liye (optional)

---

## 📊 Storage Estimate

| Report Type | Per File Size | Yearly Total |
|-------------|---------------|--------------|
| Daily Excel | ~50-100 KB | ~18-36 MB |
| Daily PDF | ~100-200 KB | ~36-72 MB |
| Monthly | ~200-500 KB | ~2-6 MB |
| Yearly | ~1-2 MB | ~1-2 MB |

---

## 🧪 Testing Steps

1. Login to Admin panel
2. Go to Reports → Receipt Reports
3. Select date range
4. Click "Generate Daily/Monthly/Yearly Report"
5. Check D: drive for saved files

---

## ⏰ Windows Task Scheduler Setup (Automatic Reports)

> [!TIP]
> **Recommended:** Refer to the consolidated [Cronjob Documentation](file:///c:/xampp/htdocs/portal/docs/cronjob_documentation.md) for a complete system-wide setup guide.

### Kaise kaam karega:

1. Hum ek PHP script banayenge: `portal/cron/receipt_report_cron.php`
2. Windows Task Scheduler is script ko scheduled time par run karega
3. Script automatically Excel + PDF reports generate karke D: drive par save karegi

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
| **Name** | Portal Daily Receipt Report |
| **Description** | Automatic daily receipt report generation |
| **Trigger** | Daily, 11:59 PM |
| **Action** | Start a program |

#### Step 4: Program Settings

| Field | Value |
|-------|-------|
| **Program/script** | `C:\xampp\php\php.exe` |
| **Arguments** | `C:\xampp\htdocs\portal\cron\receipt_report_cron.php --type=daily` |
| **Start in** | `C:\xampp\htdocs\portal\cron` |

#### Step 5: Additional Settings
- ✅ "Run whether user is logged on or not"
- ✅ "Run with highest privileges"

---

### Multiple Tasks Setup:

| Task Name | Schedule | Script Argument |
|-----------|----------|-----------------|
| Daily Receipt Report | Daily 11:59 PM | `--type=daily` |
| Monthly Receipt Report | 1st of month, 12:30 AM | `--type=monthly` |
| Yearly Receipt Report | 1st Jan, 1:00 AM | `--type=yearly` |

---

### Report Script (`receipt_report_cron.php`) kya karegi:

```
1. Check karegi kaun si report banani hai (daily/monthly/yearly)
2. Database se us period ki saari receipts fetch karegi
3. Excel file generate karegi (PHPSpreadsheet)
4. PDF file generate karegi (TCPDF)
5. D: drive par save karegi
6. Log file mein entry karegi
```

---

### Logs Location:
```
D:/portal_backups/receipt_reports/logs/
├── report_2026-01-28.log
├── report_2026-01-29.log
└── ...
```

---

## 👥 Team Contact

For queries, contact the development team.

---

*Document updated for team review and approval.*
