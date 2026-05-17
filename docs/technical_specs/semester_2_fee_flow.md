# Semester 2 Fee Collection & Allocation Flow

This document outlines the technical and operational flow for collecting Semester 2 fees (Tuition Fee Part 2), including allocation, payment methods (online vs. offline), and student experience.

## 1. Fee Allocation Process

Fee allocation for Semester 2 is handled through the existing **Fee Configuration** and **Allocation System**.

### How Fees are Allocated
Allocation for Semester 2 includes **all major fee components** except for the one-time Token Fee (paid at admission) and Security Deposits.

-   **Components Allocated**:
    -   **Tuition Fee Part 2**: The second installment of tuition fees.
    -   **School Fee**: Recurring academic fee.
    -   **Trust Facilities Fee**: Recurring facility fee.
    -   **Hostel Fee**: If the student is a hosteller (excluding the one-time Security Deposit).
-   **Configuration**: The `tbl_fee_config` table defines the amounts for each of these components.
-   **Trigger**: When Semester 2 fees are released, the system generates new records in `tbl_student_fee_allocation` that include amounts for all these applicable heads.

### Fee Structure Breakdown

**1. Admission / Semester 1 (Already Collected)**
-   **Tuition Fee Part 1 (Token Fee)**: Paid to confirm admission.
-   **Hostel Security Deposit**: Paid if opting for hostel (Refundable).

**2. Semester 2 (To Be Collected)**
-   **Tuition Fee Part 2**: Remaining tuition balance.
-   **School Fee**: Standard academic charges.
-   **Trust Facilities Fee**: Campus facility charges.
-   **Hostel Fee**: Regular hostel charges (Rent/Mess).

## 2. Payment Methods

Students can pay via two modes: **Online** and **Offline**.

### A. Online Payment (Student Portal)
Students can log in to their portal and pay fees via the **EaseBuzz** payment gateway.

1.  **Student Action**:
    -   Navigates to "My Fees" section.
    -   Sees "Tuition Fee Part 2" as "Pending".
    -   Clicks "Pay Now".
2.  **System Process**:
    -   The system initiates a payment request to EaseBuzz.
    -   **Split Payments**: The system automatically detects `tuition_fee_part2` and routes the funds to the specific bank account linked to the "Tuition Label" (e.g., specific college account) defined in the backend.
    -   **GST**: GST is included in the total payable amount.
3.  **Completion**:
    -   On success, the student is redirected back.
    -   A receipt is automatically generated.
    -   The allocation status updates to "Paid".

### B. Offline Payment (Accountant Dashboard)
Parents/Students visit the college admin office to pay via Cash, Cheque, or DD.

1.  **Accountant Action**:
    -   Logs in to Admin Panel > "Collect Fees" (or "Add Payment").
    -   Searches for the student.
    -   Selects "Tuition Fee Part 2" from the fee components list.
    -   Enters payment details (Cash/Cheque/DD).
    -   Can apply **Discounts** (if needed) at this stage.
2.  **System Process**:
    -   Records the transaction in `tbl_payments`.
    -   Generates a receipt immediately.
    -   Updates the "Pending Balance" for the student.

## 3. Future Roadmap: Starting Semester 2

To "Turn On" Semester 2 collection in 2-3 months:

1.  **Verify Configuration**: Ensure `tuition_fee_part2` amounts are correct in "Master Settings > Fee Structure".
2.  **Release Fees**:
    -   We may need to run a "Bulk Allocate" script if not already done.
    -   *Current Status*: The system likely allocates all fees at the start but marks them with different due dates. We should verify if "Part 2" is already visible as "Future Due" or needs to be inserted.
    -   *Action Item*: When ready, we will ensure `tbl_student_fee_allocation` has entries for Part 2 for all active students.

## 4. Student Payment Experience

**Step-by-Step for Student:**
1.  **Login**: Student logs in with Mobile No & Password.
2.  **Dashboard**: Sees "Upcoming Dues" notification.
3.  **My Fees Page**:
    -   List of all Semester 2 fee heads:
        -   **Tuition Fee Part 2**
        -   **School Fee**
        -   **Trust Facilities Fee**
        -   **Hostel Fee** (if applicable)
    -   Shows Amount + GST for each (or total).
    -   Status: `Pending`.
4.  **Payment**:
    -   Selects "Pay Online".
    -   Enters Card/UPI details on Gateway.
    -   Success -> Download Receipt.

---
**Technical Note**: The backend code (`easebuzz-payment.php`) already monitors for `payment_type === 'tuition_fee_part2'` to handle specific routing and GST calculations, so the infrastructure is ready.
