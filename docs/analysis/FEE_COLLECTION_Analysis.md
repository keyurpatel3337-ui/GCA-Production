# PDF Analysis: FEE COLLECTION GM-SGM-MST.pdf

This specific file, **FEE COLLECTION GM-SGM-MST.pdf**, serves as a standard daily collection report that summarizes total cash and cheque inflows for specific educational units without individual student details.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Institutions Included**: Gyanmanjari School (GM), Mahatma Seva Trust (MST).
*   **Focus**: Daily totals per mode of payment (Cash/Cheque).

### 📊 Data Structure & Mapping
This report highlights the "Daily Ledger" summary which our system calculates in real-time:

| PDF Data Point | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Date** | `payment_date` | The business day the funds were received. |
| **Cash** | `cash_total` | Total liquid currency collected for the day. |
| **Cheque/DD** | `cheque_total` | Total bank instruments (Cheques/DDs) received. |
| **Deduction** | `deduction_total` | Any fee waivers or adjusted amounts. |
| **Total** | `grand_total` | The net collection for the day (Sum of all modes). |

### ✅ Implementation Note
In the GCA system, this overview is the primary purpose of the **[Day Book Summary](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php)**.

**How to generate this in GCA:**
1.  Navigate to the **Day Book**.
2.  At the top of the page, you will see the **"Summary Cards"** (Opening Balance, Today's Collection, Closing Balance).
3.  Each card provides the **Mode-wise breakdown** (Cash, Online, Cheque) exactly like the columns in this PDF.
4.  By using the **"Institution Filter"**, you can see these totals specifically for GM, SGM, or MST.

This report is the quickest way for management to check if the physical cash/cheques in the office match the system records for the day.
