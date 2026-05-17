# PDF Analysis: FEE COLLECTION DETAILS GM-SGM-MST.pdf

This specific file, **FEE COLLECTION DETAILS GM-SGM-MST.pdf**, is a highly granular transaction log that provides a student-by-student breakdown of payments, showing exactly how each receipt is split across different fee heads (Tuition, Vehicle, Facilities, etc.) and educational trusts.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Grouping**: Grouped by **Date** and **Payment Mode** (Cash, Cheque, etc.).
*   **Granularity**: Individual student transactions with receipt-level detail.

### 📊 Columns & Multi-Trust Mapping
This is a complex matrix report that GCA handles through its unified ledger system:

| PDF Data Point | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Rec. No** | `receipt_no` | The unique receipt identifier. |
| **Name** | `student_name` | The full name of the student. |
| **Class / Division** | `current_class` / `division` | Academic placement of the student. |
| **Tuition Fee (GM/SGM)** | `component: Tuition` | Revenue for the specific school units. |
| **Trust Facilities Fees** | `component: Facilities` | Specific head under Mahatma Seva Trust. |
| **Vehicle Fee (Annual/Monthly)**| `component: Transport` | Transport-related revenue categorization. |
| **Total** | `grand_total` | The total value of that specific receipt. |

### ✅ Implementation Note
In the GCA system, this level of detail is exactly what the **[Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php)** provides when accessed in its "Detailed View".

**How to replicate this in GCA:**
1.  Navigate to the **Day Book**.
2.  Select your **Date Range**.
3.  The main table provides the chronological list (Date, Time, Student, Mode, Amount).
4.  To see the specific "Heads" (Tuition vs. Vehicle), you can click on any **Receipt No** to view/print the receipt breakdown, or refer to the **Receipt Breakdown** report which provides a horizontal view similar to this PDF.

This report is essential for accountants to verify that payments are being credited to the correct departments and trusts.
