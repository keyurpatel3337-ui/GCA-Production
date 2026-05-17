# PDF Analysis: USER WISE TOTAL COLLECTION GM-SGM-MST.pdf

This specific file, **USER WISE TOTAL COLLECTION GM-SGM-MST.pdf**, is a concise summary report that provides the final total collections processed by each **Staff Member (User)**, categorized by payment mode, for a specific period.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Type**: User-wise Aggregated Summary.
*   **Focus**: Staff accountability and period-end totals.

### 📊 Data Summary & GCA Mapping
This report consolidates thousands of transactions into a single-page overview of staff performance:

| PDF Field | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Payment Mode** | `payment_mode` | Summarized by Cash, Cheque/DD, and Deduction. |
| **User** | `collector_id / user_name` | The identity of the staff member (e.g., "main"). |
| **Fees Amount** | `total_amount_per_user` | The cumulative sum of all transactions handled by that user. |
| **Remarks / Signature**| `manual_verification_space`| For physical audit and staff sign-off. |
| **Total** | `period_grand_total` | The net institutional collection handled by all users. |

### ✅ Implementation Note
In the GCA system, this summary page corresponds to the **Dashboard View** of the **[Collector-wise Report](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php)**.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Collector-wise Report**.
2.  Select the **"Summary Only"** toggle or view the **Stats Cards**.
3.  Set your **Date Range** (01-01-2026 to 21-02-2026).
4.  The system will generate a table showing each staff member's name and their total collections by mode (Cash, Online, Cheque).
5.  This report is used by **Management** during monthly reviews to evaluate individual staff productivity and to verify the total deposits in the office.

This report is the final point of reconciliation for staff-managed funds before they are finalized in the institutional ledger.
