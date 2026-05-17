# PDF Analysis: FEE COLLECTION TOTAL ONLY GM-SGM-MST.pdf

This specific file, **FEE COLLECTION TOTAL ONLY GM-SGM-MST.pdf**, is a high-level consolidated revenue report that provides final collection totals for all major educational units and specific trust-level fee heads.

Here is a breakdown of its content and how it correlates with your current GCA system:

### 📄 Report Metadata
*   **Period Covered**: 01-01-2026 to 21-02-2026.
*   **Entities Included**: Gyanmanjari School (GM), Mahatma Seva Trust (MST), Shree Gyanmanjari (SGM).
*   **Type**: Consolidated Executive Summary.

### 📊 Multi-Entity Revenue Mapping
This report provides the "Bottom Line" for each division of the institution:

| PDF Revenue Head | GCA System Mapping | Description |
| :--- | :--- | :--- |
| **Tuition Fee (GM)** | `Head: Tuition | School: GM` | Total standard tuition collected for the main school. |
| **Trust Facilities Fees** | `Head: Facilities | Trust: MST` | Specialized facility revenue under Mahatma Seva Trust. |
| **Annual/Vehicle Fee** | `Head: Transport | Trust: MST` | Consolidated transport revenue. |
| **Tuition Fee (SGM)** | `Head: Tuition | School: SGM` | Total standard tuition for the SGM unit. |
| **Total** | `system_grand_total` | The absolute cumulative collection across all institutions. |

### ✅ Implementation Note
In the GCA system, this consolidated executive view is the primary function of the **[Financial Dashboard](file:///c:/xampp/htdocs/GCA-Development/portal/modules/reports/financial/index.php)**.

**How to generate this word-for-word in GCA:**
1.  Navigate to the **Financial Reports Index**.
2.  The landing page displays real-time **Revenue Gauges** and **Trust Breakdown charts**.
3.  By selecting multiple institutional filters (GM + SGM + MST), the system generates a unified total that matches the "Total" line in this PDF.
4.  This view is specifically engineered for **Auditors** and **Trustees** who need to see the institutional performance without student-level noise.

This report confirms the institutional health by showing the balanced distribution of revenue across all schools and trusts.
