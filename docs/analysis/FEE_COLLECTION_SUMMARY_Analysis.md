# PDF Analysis: FEE COLLECTION SUMMARY GM-SGM-MST.pdf

This specific file, **FEE COLLECTION SUMMARY GM-SGM-MST.pdf**, provides a concise high-level view of fee collections grouped by institution and summary statistics, designed for quick management review.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Entities Included**: Gyanmanjari School (GM), Mahatma Seva Trust (MST).
*   **Type**: Summary Report (Aggregated totals).

### 📊 Data Aggregation & Mapping
This report consolidates daily activities into institution-wide totals:

| PDF Summary Point | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Gyanmanjari Seconda** | `School Filter: GM` | The total revenue collected for the specific educational unit. |
| **Mahatma Seva Trust** | `Trust Filter: MST` | Total funds processed specifically for the Trust account. |
| **Cash / Cheque/DD** | `payment_mode` | Breakdown of collection by physical and bank instruments. |
| **Deduction** | `deduction` | Total value of credits or waivers applied within the period. |
| **Total** | `grand_total` | The final net collection amount across all selected entities. |

### ✅ Implementation Note
In the GCA system, this summary is the primary output of the **[Day Book](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/day-book.php)** and **Receipt Register** dashboard components.

**How to replicate this in GCA:**
1.  Navigate to **financial reports**.
2.  Use the **Trust/School Filter** to select the specific institutions (GM, SGM, MST).
3.  The **"Summary Dashboard"** at the top will automatically aggregate these totals for the selected date range.
4.  Unlike the "Detailed" reports, this view is designed for **Reconciliation**, allowing you to see if the ledger balances match the institutional bank statements.

This report is particularly useful for end-of-period closures (Monthly or Quarterly) to see the performance of each trust at a glance.
