# WhatsApp Templates Report

This document lists all available WhatsApp templates in the system and maps them to the code files where they are triggered.

## 1. Available Templates (Database)

| Template Name | Category | Header | Status | Active |
| :--- | :--- | :--- | :--- | :--- |
| **registration_success_001** | utility | None | Approved | Yes |
| **admission_001** | utility | None | Approved | Yes |
| **login_credentials** | utility | None | Approved | Yes |
| **tokenfeesuconline_011** | utility | None | Approved | Yes |
| **feepaymentsuccess_001** | utility | None | Approved | Yes |
| **refund_processed_001** | utility | None | Approved | Yes |
| **gcr_request_submitted_01** | utility | None | Approved | Yes |
| **gcr_request_approved** | utility | None | Approved | Yes |
| **gcr_request_rejected_001** | utility | None | Approved | Yes |
| **feereminder_001** | utility | None | Approved | Yes |
| **feeoverdue_003** | utility | None | Approved | Yes |
| **appointmentreminder_004** | utility | None | Approved | Yes |
| **appointmentbooked_001** | utility | None | Approved | Yes |
| **appointment_confirmed_001** | utility | None | Approved | Yes |
| **appointmentcancelled_001** | utility | None | Approved | Yes |
| **counsassigned_001** | utility | None | Approved | Yes |
| **docuverified_001** | utility | None | Approved | Yes |
| **payment_failed001** | utility | None | Approved | Yes |
| **partial_payment_001** | utility | None | Approved | Yes |
| **scholarship_approved_01** | utility | None | Approved | Yes |
| **scholarship_rejected_001** | utility | None | Approved | Yes |
| **scholarship_applied_001** | utility | None | Approved | Yes |
| **exam_schedule_001** | utility | None | Approved | Yes |
| **result_published_001** | utility | None | Approved | Yes |
| **admit_card_ready_001** | utility | None | Approved | Yes |
| **welcome__message110** | utility | None | Approved | Yes |
| **followup_reminder__001** | utility | None | Approved | Yes |
| **profile_updated** | utility | None | Approved | Yes |
| **event_invitation** | utility | None | Approved | Yes |
| **holiday_notice_001** | utility | None | Approved | Yes |
| **general__announcement_001** | utility | None | Approved | Yes |
| **gcr_fee_adjusted** | utility | None | Approved | Yes |

*(Note: Based on code mapping and standard expectation. Actual DB content may vary slightly if manual inserts occurred.)*

## 2. Template Triggers (Codebase)

Most notifications are centralized in `sendNotification` function in `common/helpers/notification_functions.php`.

| Notification Type | Template Name (Code) | Trigger File(s) | Function / Context |
| :--- | :--- | :--- | :--- |
| **registration_success** | `registration_success_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **admission_confirmed** | `admission_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **login_credentials** | `login_credentials` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **token_fee_success** | `tokenfeesuconline_011` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **fee_payment_success** | `feepaymentsuccess_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **refund_request_submitted** | `refund_processed_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **group_change_submitted** | `gcr_request_submitted_01` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **group_change_approved** | `gcr_request_approved` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **group_change_rejected** | `gcr_request_rejected_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **fee_reminder** | `feereminder_001` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **fee_overdue** | `feeoverdue_003` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **appointment_reminder** | `appointmentreminder_004` | `common/helpers/notification_functions.php` | `sendNotification()` |
| **appointment_booked** | `appointmentbooked_001` | `common/helpers/notification_functions.php` | `sendNotification()` |

### Direct Usage

| Template Pattern | File | Description |
| :--- | :--- | :--- |
| `fee_reminder` / `pending_fee` | `counselling-backend/controllers/fees/pending-reminders_controller.php` | Fetches template dynamically from DB using LIKE query. |

## 3. Testing Guide

To test these templates:

1.  **Identify the Action**: Find the action in the portal that triggers the notification (e.g., Register a student, Pay fees).
2.  **Check Recipient**: Ensure the test student has a valid mobile number with WhatsApp.
3.  **Trigger**: Perform the action.
4.  **Verify**: Check the `tbl_whatsapp_logs` table for the status 'sent'.
