# PDF Analysis: FEE COLLECTION DETAIL SUMMARY GM-SGM-MST.pdf

This specific file, **FEE COLLECTION DETAIL SUMMARY GM-SGM-MST.pdf**, provides a high-level daily financial summary broken down by educational trusts and specific fee components.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Entities Included**: Gyanmanjari Seconda, Mahatma Seva Trust, Shree Gyanmanjari Se.
*   **Structure**: A matrix report showing daily totals across multiple financial heads.

### 📊 Data Columns & Trust Mapping
The report is a "Multi-Entity Summary" which our system handles via consolidated filtering:

| PDF Entity/Header | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Gyanmanjari Seconda** | `school_id` (GM) | Fees collected specifically for the Secondary unit. |
| **Mahatma Seva Trust** | `trust_id` (MST) | Fees collected under the Mahatma Seva Trust account. |
| **Shree Gyanmanjari Se** | `school_id` (SGM) | Fees collected for the Shree Gyanmanjari unit. |
| **Tuition Fee / Vehicle Fee** | `fee_component` | The specific "Head" or category of the fee. |
| **Cash / Cheque/DD / Deduction** | `payment_mode` | The method by which the fee was received. |
| **Total** | `grand_total` | The sum of all collections for that specific day. |

### ✅ Implementation Note
In the GCA system, you can replicate this summary by using the **[Payment Type Breakdown](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/payment-type-breakdown.php)** report.

**How to generate this view:**
1.  Navigate to the **Payment Type Breakdown** module.
2.  Set the **Date Range** (e.g., Month-to-date).
3.  The system will automatically generate the "Stat Cards" at the top which show the **Trust-wise totals** and **Mode-wise breakdowns** (Cash, Online, Cheque) matching the horizontal structure of this PDF.
4.  For the specific "Head-wise" (Tuition, Vehicle) totals, our system provides a nested breakdown in the data table below the charts.

This report is ideal for management-level reviews where individual student names are not required, only the daily institutional growth.
