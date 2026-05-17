# PDF Analysis: USER AND HEAD WISE COLLECTION GM-SGM-MST.pdf

This specific file, **USER AND HEAD WISE COLLECTION GM-SGM-MST.pdf**, is a consolidated financial log that tracks daily collections across multiple educational units (GM, SGM, MST), categorized by both the **Collector** (Received By) and the **Payment Mode**.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Entities Included**: Gyanmanjari School (GM), Shree Gyanmanjari (SGM), Mahatma Seva Trust (MST).
*   **Grouping**: Grouped by **Date** and then by **Staff Member** (e.g., "main" collector).

### 📊 Multi-Trust Data & Collector Mapping
This report provides accountability for who processed specific funds across the entire institution:

| PDF Field | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Date** | `payment_date` | The business day the funds were received. |
| **Received By** | `collector_id / user_id` | The name or ID of the staff member who used the system (e.g., "main"). |
| **Payment Mode** | `payment_mode` | Breakdown per mode (Cash, Cheque/DD, Deduction). |
| **Unit Columns (GM/MST/SGM)**| `unit_wise_total` | Revenue allocated to specific ledger accounts for each school/trust. |
| **Total** | `daily_cumulative_total`| Cumulative collection for that day across all units and modes. |

### ✅ Implementation Note
In the GCA system, this consolidated oversight is handled by the **[Collector-wise Report](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php)**.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Collector-wise Report**.
2.  Enable the **Institutional Multi-select** filter to include GM, SGM, and MST.
3.  Set your **Date Range**.
4.  The system will generate a table matching this PDF exactly, showing the split of funds per unit for each staff member.
5.  This is used by the **Chief Accountant** to verify that the total collections managed by each staff member are correctly distributed into the specific trust bank accounts.

This report serves as the final verification layer between the physical collection points and the institutional accounting ledgers.
