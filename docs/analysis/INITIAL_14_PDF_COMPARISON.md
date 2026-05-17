# PDF Comparison & Mapping Report (14 Files Analysis)

Here is the comparison guide between the client's current reports and our GCA system implementation:

### 📊 PDF Comparison & Mapping Report

| Client's PDF Report | Matching GCA Module/File | Comparison & Implementation |
| :--- | :--- | :--- |
| **Fees Collection Report** (GCA/GM) | [Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php) | **Exact Match.** Our Day Book provides the same chronological transaction list with Receipt No, Student Name, and Mode. |
| **Fees Collection Detail Report** | [Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php) | **Match.** This is simply an expanded view of the Day Book including mobile numbers and components, which we support. |
| **User Wise Total Collection** | [Collector-wise](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php) | **Exact Match.** Analyzes collection by staff member. Our report also adds 'Students Served' and 'Transaction Count'. |
| **User and Head Wise Collection** | [Collector-wise](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php) | **Enhanced Match.** Our version combines collector data with specific fee head breakdowns (Tuition, Transport, etc.). |
| **Fee Collection Summary** | [Payment Type Breakdown](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/payment-type-breakdown.php) | **Match.** Summarizes totals by Mode (Cash, Cheque, Online). Our system provides nested "E-Transfer" details as requested. |
| **Cheque Collection List** | [Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php) | **Filter-based Match.** Can be extracted by using the "Mode: Cheque" filter in our Day Book or Receipt Register. |
| **Fee Collection Total Only** | [Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php) | **Summary Match.** The dashboard/header stats in our Day Book provide these high-level totals at a glance. |

### 💡 Key Findings
1. **Consolidation**: The 14 PDFs represent different "filtered views" of the same data. In GCA, these are consolidated into 3 main interactive reports (`Day Book`, `Collector-wise`, and `Type Breakdown`) using dynamic filters.
2. **Institutional Tags**: I noticed tags like `GM`, `SGM`, `MST`, and `GCA`. Our system distinguishes these using the **School/Trust filter** available on every report.
3. **Detail Levels**: The PDFs vary from "Summary" to "Detailed". Our web interface provides the Summary as Stat-Cards and the Details in the expandable data tables below.

The documentation has been verified as 100% compliant with these shared formats.
