# PDF Analysis: FEE COLLECTION SUMMERY - GCA.pdf

This specific file, **FEE COLLECTION SUMMERY - GCA.pdf**, is a targeted financial summary focusing on the daily collection performance of the **Gyanmanjari Career Academy (GCA)** unit, detailing total inflows by payment mode.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-02-2026 to 21-02-2026.
*   **Entity Focused**: Gyanmanjari Career Academy (GCA).
*   **Type**: Monthly Detailed Summary (aggregated by day).

### 📊 Data Points & GCA Integration
This report breaks down GCA-specific collections into four primary categories:

| PDF Column Header | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Cash** | `payment_mode: Cash` | Liquid currency collected on that specific date. |
| **Cheque/DD** | `payment_mode: Cheque` | Total value of bank instruments processed. |
| **Deduction** | `discount/waiver` | Any amount subtracted from the student's dues. |
| **E-Transfer** | `payment_mode: Online` | Funds received via UPI, Bank Transfer, or Cards. |
| **Total** | `daily_total` | The net revenue recognized for the GCA unit. |

### ✅ Implementation Note
In the GCA system, this unit-specific report is generated via the **[Payment Type Breakdown](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/payment-type-breakdown.php)** report.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Payment Type Breakdown** module.
2.  Select the **Institution Filter**: `GCA`.
3.  Set your requested **Date Range**.
4.  The top **Summary Cards** will provide the total Cash, Cheque, and E-Transfer amounts specifically for the GCA entity.
5.  The **Deduction** column in this PDF corresponds to the "Scholarships & Discounts" tracking within our individual student ledgers.

This report is vital for GCA-specific financial monitoring to ensure the unit is meeting its specific collection targets independently of other trusts.
