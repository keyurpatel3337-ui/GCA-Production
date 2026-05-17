# GET Request Usage Audit Report

This file contains a list of files that use HTTP GET requests within the Counselling Portal.

## 📥 1. Backend: Files Receiving GET Data (`$_GET`)
These files extract parameters directly from the URL.

| File Name | Purpose |
| :--- | :--- |
| `portal/session_config.php` | Global request sanitization. |
| `portal/scripts/generate_pdf.php` | Fetches record ID from URL for PDF generation. |
| `portal/modules/students/appointment-delete.php` | Deletes a record using ID from URL. |
| `portal/modules/scholarships/scholarship-type-toggle.php` | Toggles status using ID from URL. |
| `portal/modules/payments/token-fee-collection.php` | Handles search filters via GET parameters. |
| `portal/modules/student-portal/my-fees.php` | Checks payment statuses using GET flags. |

---

## 🚀 2. API Communication: Files Sending GET Requests (`$api->get`)
Internal PHP modules that fetch data using the API layer.

| File Name | Route Targeted |
| :--- | :--- |
| `portal/modules/students/list.php` | `students/list` |
| `portal/modules/payments/payments.php` | `payments/list` |
| `portal/modules/settings/users.php` | `settings/users` |
| `portal/modules/fees/fee-config.php` | `fees/config` |
| `portal/modules/student-portal/profile.php` | `student-portal/profile` |

---

## 🌐 3. AJAX & JavaScript GET Calls
Frontend files that periodically or dynamically fetch data.

| File Name | Technology | Logic |
| :--- | :--- | :--- |
| `portal/modules/payments/create-installment.php` | Select2 AJAX | Remote search for students. |
| `portal/modules/test-management/blueprint.php` | JS `fetch()` | Dependent dropdowns fetching topics. |
| `portal/modules/scholarships/scholarship-rules.php` | jQuery `$.get()` | Populating edit modals with rule data. |

---

## 📝 4. Search & Filter Forms (`method="GET"`)
HTML forms that append search queries to the URL.

| File Name | Functionality |
| :--- | :--- |
| `portal/modules/students/enrolled-students.php` | Student list filtering. |
| `portal/modules/payments/token-fee-collection.php` | Payment search interface. |
| `portal/modules/students/admission-confirm-list.php` | Admission record search. |

---

**Report Generated On:** 08-Jan-2026
**Scope:** `c:\wamp64\www\counselling`
