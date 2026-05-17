# GCA Production — Sidebar Navigation Reference

> **Source file:** `layouts/sidebar.php`  
> **Last updated:** 2026-04-24  
> **Framework:** AdminLTE 4 (Bootstrap 5)

---

## Common (All Roles)

| Item | Icon | Link |
|---|---|---|
| **Dashboard** | `fa-home` | Role-specific dashboard (see below) |
| **Logout** | `fa-sign-out-alt` | `logout.php` |

### Dashboard routing by role

| Role | Dashboard URL |
|---|---|
| Super Admin | `modules/dashboard/admin_dashboard.php` |
| Principle | `modules/dashboard/principle_dashboard.php` |
| Counsellor | `modules/dashboard/counsellor_dashboard.php` |
| Student | `modules/dashboard/student_dashboard.php` |
| Accountant | `modules/dashboard/accountant_dashboard.php` |
| Website Admin | `modules/dashboard/website_admin_dashboard.php` |

---

## 🔑 Super Admin Menu (`ROLE_SUPER_ADMIN`)

### User Management
| Sub-item | File |
|---|---|
| Users | `modules/settings/users.php` |
| Roles | `modules/settings/roles.php` |

### Master Data
| Sub-item | File |
|---|---|
| Academic Years | `modules/academics/academic-years.php` |
| Boards | `modules/academics/boards.php` |
| Courses | `modules/academics/courses.php` |
| Schools | `modules/academics/schools.php` |
| Medium | `modules/academics/medium.php` |
| Groups | `modules/academics/group.php` |
| Terms | `modules/academics/term.php` |

### Division Management
| Sub-item | File |
|---|---|
| Divisions | `modules/academics/divisions.php` |
| Course Division | `modules/academics/course-division.php` |
| Assign Division | `modules/students/division-assignment.php` |
| Division Requests | `modules/students/pending-division-requests.php` |
| Division Shuffle | `modules/students/division-shuffle.php` |

### System Config
| Sub-item | File |
|---|---|
| API Config | `modules/settings/api-config.php` |
| Receipt Config | `modules/payments/receipt-config.php` |
| Payment Gateways | `modules/settings/payment-gateways.php` |

### WhatsApp
| Sub-item | File |
|---|---|
| API Management | `modules/settings/whatsapp-api-management.php` |
| Templates | `modules/settings/whatsapp-templates.php` |
| Message Logs | `modules/settings/whatsapp-message-logs.php` |

### Email
| Sub-item | File |
|---|---|
| SMTP Config | `modules/settings/smtp-config.php` |
| Templates | `modules/settings/email-templates.php` |
| Email Logs | `modules/settings/email-logs.php` |

### Fee Management
| Sub-item | File |
|---|---|
| Fee Config | `modules/fees/fee-config.php` |
| Hostel Fee | `modules/hostel/hostel-fee-config.php` |
| Assign Fees | `modules/fees/assign-fees.php` |
| Installment Requests | `modules/payments/installment-requests.php` |

### Scholarship
| Sub-item | File |
|---|---|
| Types | `modules/scholarships/scholarship-types.php` |
| Rules | `modules/scholarships/scholarship-rules.php` |

### Account Section
| Item | File |
|---|---|
| Profile | `modules/profile/profile.php` |
| Settings | `modules/profile/settings.php` |

---

## 🏫 Principle Menu (`ROLE_PRINCIPLE`)

### Management
| Sub-item | File |
|---|---|
| Counsellors | `modules/counsellors/list.php` |
| Students | `modules/students/list.php` |
| Student Assignment | `modules/students/student-assignment.php` |
| Division Assignment | `modules/students/division-assignment.php` |
| Group Changes | `modules/group-change/pending-requests.php` |
| Division Requests | `modules/students/pending-division-requests.php` |
| Division Shuffle | `modules/students/division-shuffle.php` |

### Paper Sets
| Sub-item | File |
|---|---|
| Paper Sets | `modules/test-management/paper-sets.php` |
| Blueprint Upload | `modules/test-management/blueprint-upload.php` |
| Answer Keys | `modules/test-management/answer-keys.php` |

### OMR Management
| Sub-item | File |
|---|---|
| OMR Sheets | `modules/omr/omr-sheets.php` |
| Single Upload | `modules/omr/single-upload.php` |
| Bulk Upload | `modules/omr/bulk-upload.php?role=principle` |

### Direct links
| Item | File |
|---|---|
| Test Results | `modules/results/results.php` |
| Reports | `modules/reports/reports.php` |

### Fee Management
| Sub-item | File |
|---|---|
| Post-Admission Discount | `modules/students/post-admission-discount.php` |
| Installment Requests | `modules/payments/installment-requests.php` |

### Account Section
| Item | File |
|---|---|
| Profile | `modules/profile/profile.php` |
| Settings | `modules/profile/settings.php` |

---

## 🧑‍💼 Counsellor Menu (`ROLE_COUNSELLOR`)

### Students
| Sub-item | File |
|---|---|
| All Students | `modules/students/list.php` |
| Appointments | `modules/students/appointments.php` |
| Sessions | `modules/students/sessions.php` |
| Admission Confirm | `modules/students/admission-confirm-list.php` |
| Division Requests | `modules/students/pending-division-requests.php` |

### OMR & Results
| Sub-item | File |
|---|---|
| OMR Sheets | `modules/omr/omr-sheets.php` |
| Results | `modules/results/results.php` |

### Direct links
| Item | File |
|---|---|
| Reports | `modules/reports/reports.php` |

### Account Section
| Item | File |
|---|---|
| Profile | `modules/profile/profile.php` |

---

## 💰 Accountant Menu (`role === 'accountant'`)

### Student Management
| Sub-item | File |
|---|---|
| Enrolled Students | `modules/students/enrolled-students.php` |
| Pending Token Fee | `modules/students/registered-students.php` |
| All Students | `modules/students/list.php` |

### Financial
| Sub-item | File |
|---|---|
| Token Fee | `modules/payments/token-fee-collection.php` |
| Payments | `modules/payments/payments.php` |
| Pending | `modules/payments/pending-payments.php` |
| Post-Admission Discount *(Principle/Admin only)* | `modules/students/post-admission-discount.php` |
| Installment Requests | `modules/payments/installment-requests.php` |

### Direct links
| Item | File |
|---|---|
| Financial Reports | `modules/payments/financial-reports.php` |

### Account Section
| Item | File |
|---|---|
| Profile | `modules/profile/profile.php` |
| Settings | `modules/profile/settings.php` |

---

## 🎓 Student Portal Menu (`is_student_login === true`)

### Appointments
| Sub-item | File |
|---|---|
| Book Appointment | `modules/student-portal/appointments.php` |
| My Appointments | `modules/student-portal/my-appointments.php` |

### My Area
| Sub-item | File |
|---|---|
| My Results | `modules/student-portal/my-results.php` |
| My Fees | `modules/student-portal/my-fees.php` |
| Records | `modules/student-portal/records.php` |

### Division Change
| Sub-item | File |
|---|---|
| Request Change | `modules/student-portal/request-division-change.php` |
| My Requests | `modules/student-portal/my-division-change-requests.php` |

> **Note:** Student portal accounts do **not** show Profile/Settings or Logout section in the Account panel.

---

## Notes

- **`ACCOUNT` section header** is shown for all roles except `ROLE_STUDENT` and student portal sessions.
- **Counsellors** see a stripped Account section (Profile only, no Settings).
- **Post-Admission Discount** inside the Accountant > Financial menu is conditionally shown only when the user also holds `ROLE_PRINCIPLE` or `ROLE_SUPER_ADMIN`.
