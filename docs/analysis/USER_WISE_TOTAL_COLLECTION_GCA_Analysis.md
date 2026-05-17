# PDF Analysis: USER WISE TOTAL COLLECTION - GCA.pdf

This specific file, **USER WISE TOTAL COLLECTION - GCA.pdf**, is a targeted periodic summary that provides the total collections processed by each **Staff Member (User)** specifically for the **Gyanmanjari Career Academy (GCA)** unit, categorized by payment mode.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-02-2026 to 21-02-2026.
*   **Entity Focused**: Gyanmanjari Career Academy (GCA).
*   **Type**: GCA-specific User Aggregated Summary.

### 📊 Staff Revenue Alignment
This report provides a clear audit trail for funds collected by staff specifically for the GCA unit:

| PDF Data Header | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Payment Mode** | `payment_mode` | Breakdown per mode (Cash, Cheque/DD, E-Transfer, Deduction). |
| **User** | `collector_id / login_id` | The identity of the staff member (e.g., "main"). |
| **Fees Amount** | `total_amount_collected` | Cumulative total processed by the user for GCA. |
| **Remarks / Signature**| `audit_confirmation_block`| Space for manual verification of physical cash/receipts. |
| **Total** | `gca_period_total` | Net collection for GCA across all users. |

### ✅ Implementation Note
In the GCA system, this unit-specific staff summary is generated via the **[Collector-wise Report](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/collector-wise.php)**.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Collector-wise Report**.
2.  Select **"GCA"** in the Institution Filter.
3.  Set the **Date Range** (01-02-2026 to 21-02-2026).
4.  The system will output a summarized table showing exactly how much each user (Admin, Cashier, etc.) has collected for the GCA ledger, split by Cash, Online, and Cheque modes.
5.  This enables the **GCA Unit Manager** to verify staff collections independently of the main school trust.

This report is critical for maintaining a clean audit trail between front-line collection staff and the GCA financial ledger.
