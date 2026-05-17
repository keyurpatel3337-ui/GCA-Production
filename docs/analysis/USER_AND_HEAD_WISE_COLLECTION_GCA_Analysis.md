# PDF Analysis: USER AND HEAD WISE COLLECTION - GCA.pdf

This specific file, **USER AND HEAD WISE COLLECTION - GCA.pdf**, is a specialized financial log that tracks daily collections for the **Gyanmanjari Career Academy (GCA)** unit, categorized by both the **Collector** (Received By) and the **Payment Mode**.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-02-2026 to 21-02-2026.
*   **Entity Focused**: Gyanmanjari Career Academy (GCA).
*   **Grouping**: Grouped by **Date** and then by **Staff Member** (e.g., "main" collector).

### 📊 Data Points & Collector Mapping
This report provides accountability for who processed specific funds on behalf of the GCA unit:

| PDF Field | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Date** | `payment_date` | The business day the funds were received. |
| **Received By** | `collector_id / user_id` | The name or ID of the staff member who used the system (e.g., "main"). |
| **Payment Mode** | `payment_mode` | Breakdown per mode (Cash, Cheque/DD, E-Transfer, Deduction). |
| **GCA Amount** | `collector_total` | Total funds collected by that specific user for the GCA unit. |
| **Total** | `daily_total` | Cumulative collection for that day across all collectors. |

### ✅ Implementation Note
In the GCA system, this level of oversight is handled by the **[Collector-wise Report](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php)**.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Collector-wise Report**.
2.  Select **"GCA"** in the Institution Filter.
3.  Set your **Date Range**.
4.  The system will generate cards and tables showing exactly how much was collected by each staff member (e.g., Admin, Front Desk) for GCA, broken down by payment mode.
5.  This is the primary report used for **Internal Audit** to ensure that the physical cash handed over by staff matches the system record.

This report bridges the gap between individual student receipts and institutional revenue by introducing staff-level accountability.
