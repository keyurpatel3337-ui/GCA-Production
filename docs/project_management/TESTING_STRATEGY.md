# Testing Strategy & Test Cases
## Counselling Management System

### Document Information
- **Version:** 1.0
- **Date:** January 10, 2026
- **System:** Counselling Management System
- **Environment:** WAMP64 (Windows, Apache, MySQL, PHP)

---

## Table of Contents
1. [Testing Strategy Overview](#testing-strategy-overview)
2. [Testing Levels](#testing-levels)
3. [Testing Types](#testing-types)
4. [Test Environment](#test-environment)
5. [Test Cases by Module](#test-cases-by-module)
6. [Test Execution Guidelines](#test-execution-guidelines)
7. [Defect Management](#defect-management)
8. [Test Metrics](#test-metrics)

---

## 1. Testing Strategy Overview

### 1.1 Objectives
- Ensure all functional requirements are met
- Validate data integrity across all modules
- Verify payment gateway integrations
- Ensure security and access control
- Validate reporting accuracy
- Test system performance under load

### 1.2 Scope
**In Scope:**
- Student Registration Module
- Fee Management System
- Payment Processing (EaseBuzz Integration)
- Counselling Sessions Management
- Test/Exam Management
- Admission Process
- Receipt Generation
- Refund Processing
- Scholarship Management
- User Role Management
- Email Notifications
- WhatsApp Integration
- Academic Performance Tracking

**Out of Scope:**
- Third-party payment gateway internals
- Email server configuration
- Database server performance tuning

### 1.3 Test Approach
- **Manual Testing:** 70% (UI, Integration, UAT)
- **Automated Testing:** 30% (API, Database, Regression)
- **Exploratory Testing:** Ad-hoc for new features

---

## 2. Testing Levels

### 2.1 Unit Testing
- Individual PHP functions and methods
- Database stored procedures
- JavaScript functions
- Validation logic

### 2.2 Integration Testing
- Module-to-module integration
- Database operations
- Payment gateway integration
- Email/WhatsApp service integration
- Session management

### 2.3 System Testing
- End-to-end workflows
- Cross-module functionality
- Security testing
- Performance testing

### 2.4 User Acceptance Testing (UAT)
- Real-world scenarios
- User role-based testing
- Business process validation

---

## 3. Testing Types

### 3.1 Functional Testing
✓ Feature functionality
✓ Business rules validation
✓ Data validation
✓ Navigation flows

### 3.2 Non-Functional Testing
✓ **Performance:** Response time, load handling
✓ **Security:** Authentication, authorization, SQL injection, XSS
✓ **Usability:** UI/UX, accessibility
✓ **Compatibility:** Browser, device testing

### 3.3 Regression Testing
✓ Existing feature validation after changes
✓ Critical path testing
✓ Smoke testing for builds

---

## 4. Test Environment

### 4.1 Development Environment
- **URL:** http://localhost/counselling/
- **Database:** counselling (MySQL)
- **PHP Version:** 7.4+
- **Server:** WAMP64

### 4.2 Test Data Requirements
- 50+ student records with various statuses
- 10+ payment transactions (success, failed, pending)
- Multiple academic years
- Various fee structures
- Role-based users (admin, counsellor, accountant, student)

### 4.3 Test Tools
- **Browser Testing:** Chrome, Firefox, Edge, Safari
- **API Testing:** Postman, cURL
- **Database Testing:** phpMyAdmin, MySQL Workbench
- **Performance Testing:** JMeter, LoadRunner
- **Security Testing:** OWASP ZAP, Burp Suite

---

## 5. Test Cases by Module

## 5.1 Student Registration Module

### TC-REG-001: New Student Registration (Valid Data)
**Priority:** High | **Type:** Functional

**Preconditions:**
- User has counsellor or admin role
- Academic year is active

**Test Steps:**
1. Navigate to student registration page
2. Fill all mandatory fields:
   - Name, Father's name, Mother's name
   - Date of birth
   - Contact number, email
   - Address details
   - Board selection (NEET/JEE)
   - Medium selection
3. Upload required documents
4. Submit form

**Expected Result:**
- Student record created with unique enrollment number
- Success message displayed
- Student appears in student list
- Email notification sent to student
- Database entry verified in `tbl_gm_std_registration`

**Test Data:**
- Name: "Test Student ABC"
- Mobile: "9876543210"
- Email: "test@example.com"
- Board: "NEET"

---

### TC-REG-002: Duplicate Mobile Number Validation
**Priority:** High | **Type:** Negative

**Test Steps:**
1. Attempt to register student with existing mobile number
2. Submit form

**Expected Result:**
- Error message: "Mobile number already registered"
- Form not submitted
- No duplicate entry in database

---

### TC-REG-003: Email Validation
**Priority:** Medium | **Type:** Validation

**Test Data:**
- Invalid emails: "test", "test@", "@example.com", "test..test@example.com"

**Expected Result:**
- Client-side validation error
- Form not submitted until valid email provided

---

### TC-REG-004: Mandatory Field Validation
**Priority:** High | **Type:** Validation

**Test Steps:**
1. Leave mandatory fields empty
2. Attempt to submit form

**Expected Result:**
- Validation errors displayed for each empty mandatory field
- Form not submitted
- Focus on first error field

---

### TC-REG-005: Document Upload
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Upload valid documents (PDF, JPG, PNG)
2. Upload invalid file types (.exe, .bat)
3. Upload files exceeding size limit

**Expected Result:**
- Valid files uploaded successfully
- Invalid file types rejected with error
- Oversized files rejected with size limit message
- Files stored in `uploads/` directory with unique names

---

## 5.2 Fee Management Module

### TC-FEE-001: Fee Structure Creation
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Navigate to fee structure management
2. Create new fee structure for academic year
3. Define fee components:
   - Tuition fee
   - Admission fee
   - Exam fee
   - Hostel fee (optional)
4. Set installment plan
5. Save fee structure

**Expected Result:**
- Fee structure saved in `tbl_fee_split_configuration`
- Available for assignment to students
- Installment percentages total 100%

---

### TC-FEE-002: Fee Assignment to Student
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Select student without fee assigned
2. Assign fee structure based on:
   - Academic year
   - Board (NEET/JEE)
   - Hostel requirement
3. Apply scholarship if applicable
4. Generate fee schedule

**Expected Result:**
- Fee assigned and stored in `tbl_student_fees`
- Installment schedule generated in `tbl_fee_installments`
- Total fee calculated correctly
- Scholarship discount applied
- Student can view fee details in portal

---

### TC-FEE-003: Scholarship Calculation
**Priority:** High | **Type:** Business Logic

**Test Data:**
- Base fee: ₹100,000
- Scholarship: 20% (GMSAT)
- Expected: ₹80,000

**Test Steps:**
1. Assign scholarship to student
2. Calculate final fee

**Expected Result:**
- Scholarship percentage applied correctly
- Final fee = Base fee - (Base fee × Scholarship %)
- Stored in database with scholarship reference

---

### TC-FEE-004: Overdue Fee Calculation
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Create installment with past due date
2. System calculates overdue amount
3. Generate overdue report

**Expected Result:**
- Overdue installments identified
- Late fee calculated (if applicable)
- Email notification sent to student/parent
- Listed in overdue summary report

---

### TC-FEE-005: Fee Receipt Mapping
**Priority:** High | **Type:** Configuration

**Test Steps:**
1. Verify fee receipt mapping for different fee types
2. Check GST configuration for each school
3. Validate receipt templates

**Expected Result:**
- Correct receipt configuration selected based on school
- GST details populated correctly
- Receipt number generated sequentially

---

## 5.3 Payment Processing Module

### TC-PAY-001: Online Payment Success (EaseBuzz)
**Priority:** Critical | **Type:** Integration

**Test Steps:**
1. Student initiates payment from portal
2. Select installment to pay
3. Redirect to EaseBuzz payment gateway
4. Enter test card details (provided by EaseBuzz)
5. Complete payment
6. Return to application

**Expected Result:**
- Payment order created in `tbl_payment_orders` with status "created"
- Redirect to EaseBuzz gateway successful
- Payment processed successfully
- Status updated to "completed" in database
- Entry created in `tbl_payments`
- Installment status updated to "paid" in `tbl_fee_installments`
- Receipt generated in `tbl_receipts`
- Payment confirmation email sent
- Transaction visible in payment history

**Test Data:**
- Card: Test card provided by EaseBuzz
- Amount: ₹10,000

---

### TC-PAY-002: Payment Failure Handling
**Priority:** High | **Type:** Negative

**Test Steps:**
1. Initiate payment
2. Use invalid card details or trigger failure scenario
3. Payment fails at gateway

**Expected Result:**
- Payment order status updated to "failed"
- Error message displayed to user
- Student redirected back with failure message
- No payment entry created
- Installment remains unpaid
- Failure logged in `tbl_payment_orders`
- Optional: Email notification of failure

---

### TC-PAY-003: Webhook Processing
**Priority:** Critical | **Type:** Integration

**Test Steps:**
1. Simulate EaseBuzz webhook callback
2. Send webhook with payment success data
3. Verify system processing

**Expected Result:**
- Webhook received and validated
- Signature verification successful
- Payment status updated in database
- Receipt generated
- No duplicate processing for same transaction
- Webhook logged

---

### TC-PAY-004: Pending Payment Timeout
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Create payment order
2. Do not complete payment
3. Wait for timeout period (30 minutes)

**Expected Result:**
- Order status updated to "expired" or "failed"
- Payment not reflected in installments
- Entry moved to `tbl_pending_payments` with reason

---

### TC-PAY-005: Partial Payment
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Student has total fee of ₹50,000
2. Pay ₹10,000 (first installment)
3. Verify remaining balance

**Expected Result:**
- ₹10,000 marked as paid
- Remaining balance: ₹40,000
- Next installment becomes due
- Balance calculation accurate across all views

---

### TC-PAY-006: Multiple Installment Payment
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Student selects multiple installments to pay
2. Calculate total amount
3. Process payment
4. Verify all installments updated

**Expected Result:**
- Combined amount calculated correctly
- Single payment transaction created
- All selected installments marked paid
- Single receipt generated with breakdown

---

### TC-PAY-007: Payment Gateway Configuration
**Priority:** High | **Type:** Configuration

**Test Steps:**
1. Verify EaseBuzz credentials in `tbl_payment_gateway_config`
2. Check merchant ID, API key, salt
3. Validate production/test mode settings

**Expected Result:**
- Credentials configured correctly
- Environment setting matches (test/production)
- Split payment labels configured for schools

---

## 5.4 Receipt Generation Module

### TC-RCT-001: Receipt Generation on Payment
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Complete successful payment
2. System generates receipt automatically

**Expected Result:**
- Receipt created in `tbl_receipts`
- Unique receipt number assigned
- Correct template used based on school
- GST details included
- Amount breakdown correct
- PDF generated and stored
- Receipt downloadable from portal

---

### TC-RCT-002: Manual Receipt Generation
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Admin/Accountant selects payment
2. Generates receipt manually
3. Downloads PDF

**Expected Result:**
- Receipt generated with same details as auto-generated
- Manual generation logged
- Receipt number sequential and unique

---

### TC-RCT-003: Receipt Template Selection
**Priority:** High | **Type:** Configuration

**Test Steps:**
1. Verify receipt templates for different schools:
   - GM (Gyanmanjari)
   - SGM (Shree Gyanmanjari)
   - GCA
   - MST

**Expected Result:**
- Correct template selected based on school
- School-specific GST number populated
- Logo and branding correct
- Contact details accurate

---

### TC-RCT-004: Receipt Email Delivery
**Priority:** Medium | **Type:** Integration

**Test Steps:**
1. Receipt generated
2. System sends email with receipt attachment

**Expected Result:**
- Email sent to student's registered email
- Receipt PDF attached
- Email content clear and professional
- Email template: `email_fee_receipt.html` used

---

## 5.5 Refund Management Module

### TC-REF-001: Refund Request Submission
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Student submits refund request
2. Provides reason and bank details
3. Submits request

**Expected Result:**
- Request created in `tbl_refund_requests`
- Status: "pending"
- Notification sent to counsellor/accountant
- Request appears in admin panel

---

### TC-REF-002: Refund Request Approval
**Priority:** High | **Type:** Workflow

**Test Steps:**
1. Counsellor reviews refund request
2. Adds approval notes
3. Approves request
4. Forwards to accountant

**Expected Result:**
- Status updated to "approved_by_counsellor"
- Approval timestamp recorded
- Notification sent to accountant
- Notes saved in database

---

### TC-REF-003: Refund Request Rejection
**Priority:** Medium | **Type:** Workflow

**Test Steps:**
1. Counsellor reviews request
2. Adds rejection reason
3. Rejects request

**Expected Result:**
- Status updated to "rejected"
- Rejection reason saved
- Student notified via email
- No further workflow progression

---

### TC-REF-004: Refund Processing
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Accountant processes approved refund
2. Enters transaction details
3. Completes refund
4. Updates status

**Expected Result:**
- Entry created in `tbl_refunds`
- Original payment updated
- Refund amount deducted from fee balance
- Student notified
- Email template: `email_refund_processed.html` used
- Refund visible in student's payment history

---

### TC-REF-005: Partial Refund
**Priority:** Medium | **Type:** Functional

**Test Data:**
- Paid amount: ₹50,000
- Refund requested: ₹20,000
- Remaining: ₹30,000

**Expected Result:**
- Partial refund processed correctly
- Balance calculations accurate
- Refund entry shows partial amount

---

## 5.6 Admission Process Module

### TC-ADM-001: Admission Confirmation
**Priority:** High | **Type:** Workflow

**Test Steps:**
1. Student registered and token fee paid
2. Admin reviews application
3. Confirms admission
4. Generates admission letter

**Expected Result:**
- `admission_confirmed` flag set to 1
- Admission confirmation date recorded
- Admission letter generated (PDF)
- Email sent with admission letter
- Email template: `email_admission_confirmed_counsellor.html` used

---

### TC-ADM-002: Document Verification
**Priority:** High | **Type:** Workflow

**Test Steps:**
1. Admin reviews uploaded documents
2. Verifies authenticity
3. Marks as verified or requests re-upload

**Expected Result:**
- Document status updated
- If rejected, student notified to re-upload
- Email template: `email_document_verification.html` used
- Verification log maintained

---

### TC-ADM-003: Seat Allocation
**Priority:** High | **Type:** Business Logic

**Test Steps:**
1. Student admitted
2. System allocates seat based on:
   - Board (NEET/JEE)
   - Merit/Score
   - Availability

**Expected Result:**
- Seat allocated and recorded
- School assigned (GM/SGM)
- No over-allocation
- Seat count updated

---

## 5.7 Counselling Sessions Module

### TC-CNS-001: Session Appointment Booking
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Student requests counselling appointment
2. Selects date and time
3. Submits request

**Expected Result:**
- Appointment created in `tbl_sessions`
- Status: "requested"
- Counsellor notified
- Email template: `email_appointment_request.html` used

---

### TC-CNS-002: Session Scheduling
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Counsellor reviews appointment request
2. Confirms or suggests alternate time
3. Schedules session

**Expected Result:**
- Status updated to "scheduled"
- Both parties notified
- Session visible in calendar
- Reminder email sent before session

---

### TC-CNS-003: Session Notes Recording
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Counsellor conducts session
2. Records session notes
3. Marks session as completed

**Expected Result:**
- Notes saved in `tbl_student_counselling`
- Status: "completed"
- Session count updated
- Notes accessible only to authorized users

---

### TC-CNS-004: Missed Appointment Handling
**Priority:** Low | **Type:** Workflow

**Test Steps:**
1. Student doesn't attend scheduled session
2. Counsellor marks as missed

**Expected Result:**
- Status: "missed"
- Student notified
- Email template: `email_missed_appointment.html` used
- Rescheduling option provided

---

## 5.8 Test/Exam Management Module

### TC-TST-001: Paper Set Creation
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Navigate to test management
2. Create new paper set
3. Define:
   - Paper code
   - Total questions
   - Difficulty levels (Low, Medium, High)
   - Status (Active/Draft/Inactive)
4. Save paper set

**Expected Result:**
- Paper set saved in `tbl_paper_sets`
- Unique paper code generated
- Question count distribution validated
- Available for blueprint creation

---

### TC-TST-002: Blueprint Upload
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Select paper set
2. Upload blueprint Excel file
3. Map topics to questions
4. Define difficulty levels per question
5. Preview blueprint
6. Save blueprint

**Expected Result:**
- Blueprint parsed correctly
- Topics saved in `tbl_blueprint_topics`
- Questions mapped in `tbl_blueprint_questions`
- Question distribution matches paper set
- No duplicate question numbers

---

### TC-TST-003: Answer Key Upload
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Select paper set
2. Create answer key entry
3. Upload answer key file (PDF/DOCX) or enter JSON
4. Set test date
5. Activate answer key

**Expected Result:**
- Answer key saved in `tbl_answer_keys`
- JSON validated (if provided)
- File uploaded to server
- Answer key associated with paper set
- Status: "active"

---

### TC-TST-004: OMR Sheet Upload
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Student completes OMR test
2. Admin uploads OMR sheet (PDF/image)
3. System stores OMR data

**Expected Result:**
- OMR sheet saved in `tbl_omr_sheets`
- Linked to student and paper set
- File stored in uploads directory
- Status tracked

---

### TC-TST-005: Test Marks Entry
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Select student
2. Choose test/paper set
3. Enter marks for:
   - OMR section (if applicable)
   - Subject-wise marks
   - Total marks
4. Add remarks
5. Save marks

**Expected Result:**
- Marks saved in `tbl_question_answers`
- Total calculated correctly
- Marks visible in student academic performance
- Student can view marks in portal

---

### TC-TST-006: Test Result Analysis
**Priority:** Medium | **Type:** Reporting

**Test Steps:**
1. Generate test result report
2. View analysis:
   - Topic-wise performance
   - Difficulty-level performance
   - Comparison with peers
3. Export report

**Expected Result:**
- Accurate performance metrics
- Visual charts/graphs displayed
- Export to PDF/Excel successful
- Analysis helps identify weak areas

---

## 5.9 User Management & Security

### TC-USR-001: User Login (Valid Credentials)
**Priority:** Critical | **Type:** Security

**Test Steps:**
1. Navigate to login page
2. Enter valid username/email
3. Enter correct password
4. Submit

**Expected Result:**
- User authenticated successfully
- Session created
- Redirected to dashboard based on role
- Session data stored securely
- Last login timestamp updated

**Test Data:**
- Role-based users: admin, counsellor, accountant, student

---

### TC-USR-002: User Login (Invalid Credentials)
**Priority:** Critical | **Type:** Security

**Test Steps:**
1. Enter invalid username or wrong password
2. Submit

**Expected Result:**
- Login failed
- Error: "Invalid credentials"
- No session created
- Login attempt logged
- After 5 failed attempts, account locked (optional)
- Email alert sent: `email_login_failed_alert.html`

---

### TC-USR-003: Password Reset
**Priority:** High | **Type:** Functional

**Test Steps:**
1. Click "Forgot Password"
2. Enter registered email
3. Receive reset link
4. Click link and set new password
5. Login with new password

**Expected Result:**
- Reset link sent to email
- Email template: `email_password_reset.html` used
- Link valid for limited time (24 hours)
- New password saved securely (hashed)
- Successful login with new password

---

### TC-USR-004: Role-Based Access Control (Super Admin)
**Priority:** Critical | **Type:** Security

**Test Steps:**
1. Login as super_admin
2. Access all modules
3. Verify permissions

**Expected Result:**
- Access to all modules granted
- Can create/edit/delete all records
- Can manage users and roles
- Can view all reports
- Can configure system settings

---

### TC-USR-005: Role-Based Access Control (Student)
**Priority:** High | **Type:** Security

**Test Steps:**
1. Login as student
2. Attempt to access restricted pages (admin modules)

**Expected Result:**
- Access denied to admin modules
- Can only view own records
- Can make payments for own fees
- Can view own academic performance
- Cannot edit other students' data

---

### TC-USR-006: Session Timeout
**Priority:** Medium | **Type:** Security

**Test Steps:**
1. Login
2. Remain inactive for configured timeout period
3. Attempt to perform action

**Expected Result:**
- Session expired
- Redirected to login page
- Message: "Session expired. Please login again."
- Previous actions not executed

---

### TC-USR-007: SQL Injection Prevention
**Priority:** Critical | **Type:** Security

**Test Steps:**
1. Enter SQL injection payloads in input fields:
   - `' OR '1'='1`
   - `'; DROP TABLE users; --`
   - `admin'--`
2. Submit forms

**Expected Result:**
- Inputs sanitized
- No SQL executed from input
- Database not affected
- Invalid input error displayed

---

### TC-USR-008: XSS Prevention
**Priority:** Critical | **Type:** Security

**Test Steps:**
1. Enter XSS payloads:
   - `<script>alert('XSS')</script>`
   - `<img src=x onerror=alert('XSS')>`
2. Submit and view data

**Expected Result:**
- Scripts not executed
- HTML entities encoded
- Safe display of user input
- No JavaScript executed from user input

---

## 5.10 Reporting Module

### TC-RPT-001: Daily Registration Summary
**Priority:** Medium | **Type:** Reporting

**Test Steps:**
1. Navigate to reports
2. Generate daily registration summary
3. Select date
4. View report

**Expected Result:**
- Accurate count of registrations
- Breakdown by board (NEET/JEE)
- Breakdown by medium
- Export to PDF/Excel
- Email template: `email_daily_registration_summary.html`

---

### TC-RPT-002: Daily Collection Report
**Priority:** High | **Type:** Reporting

**Test Steps:**
1. Generate daily collection report
2. Select date
3. View report

**Expected Result:**
- Total collection for the day
- Breakdown by payment mode
- Breakdown by fee type
- School-wise collection
- GST breakdown
- Email template: `email_daily_collection.html`

---

### TC-RPT-003: Monthly Financial Report
**Priority:** High | **Type:** Reporting

**Test Steps:**
1. Generate monthly report
2. Select month and year
3. View report

**Expected Result:**
- Total revenue
- Outstanding fees
- Refunds processed
- Net collection
- Comparison with previous month
- Email template: `email_monthly_report.html`

---

### TC-RPT-004: Student Performance Report
**Priority:** Medium | **Type:** Reporting

**Test Steps:**
1. Select student
2. Generate performance report
3. View analysis

**Expected Result:**
- Test-wise marks
- Topic-wise performance
- Progress trend
- Attendance data
- Counselling session summary
- Export to PDF

---

### TC-RPT-005: Overdue Fee Report
**Priority:** High | **Type:** Reporting

**Test Steps:**
1. Generate overdue report
2. View list of students with overdue installments

**Expected Result:**
- Accurate overdue calculations
- Days overdue shown
- Contact details included
- Bulk email option available
- Email template: `email_fee_overdue_notice.html`
- Summary email: `email_overdue_summary.html`

---

## 5.11 Email Notification Module

### TC-EML-001: Welcome Email on Registration
**Priority:** Medium | **Type:** Integration

**Test Steps:**
1. Register new student
2. Verify email sent

**Expected Result:**
- Email sent to student's email
- Template: `email_student_welcome.html` used
- Contains enrollment number, login credentials
- Formatted correctly
- No broken images/links

---

### TC-EML-002: Payment Confirmation Email
**Priority:** High | **Type:** Integration

**Test Steps:**
1. Complete payment
2. Verify confirmation email

**Expected Result:**
- Email sent immediately after payment
- Template: `email_payment_received.html` used
- Contains payment details, receipt
- Receipt attached as PDF

---

### TC-EML-003: Bulk Email Sending
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Admin selects multiple students
2. Composes email
3. Sends bulk email

**Expected Result:**
- Emails sent to all selected recipients
- Email queue managed (not all sent simultaneously)
- Delivery status tracked
- Failed sends logged

---

### TC-EML-004: Email Template Rendering
**Priority:** Medium | **Type:** Functional

**Test Steps:**
1. Test all email templates with sample data
2. Verify variable substitution
3. Check formatting

**Expected Result:**
- All placeholders replaced with actual data
- HTML rendering correct in major email clients
- Images displayed (if hosted)
- Links functional

---

## 5.12 WhatsApp Integration Module

### TC-WHA-001: WhatsApp Notification Sending
**Priority:** Medium | **Type:** Integration

**Test Steps:**
1. Configure WhatsApp API credentials
2. Send test notification
3. Verify delivery

**Expected Result:**
- Message sent successfully
- Delivered to correct number
- Template variables replaced
- Delivery status tracked

---

### TC-WHA-002: Payment Confirmation WhatsApp
**Priority:** Medium | **Type:** Integration

**Test Steps:**
1. Complete payment
2. Verify WhatsApp message sent

**Expected Result:**
- Message sent to student's mobile
- Contains payment summary
- Professional template used
- Delivery confirmed

---

## 5.13 Performance & Load Testing

### TC-PRF-001: Page Load Time
**Priority:** Medium | **Type:** Performance

**Test Steps:**
1. Measure page load times for key pages:
   - Login page
   - Student dashboard
   - Payment page
   - Report generation

**Expected Result:**
- Login page: < 2 seconds
- Dashboard: < 3 seconds
- Payment page: < 2 seconds
- Reports: < 5 seconds
- No performance degradation with normal data load

---

### TC-PRF-002: Concurrent User Load
**Priority:** Medium | **Type:** Performance

**Test Steps:**
1. Simulate 50 concurrent users
2. Perform various operations
3. Monitor system response

**Expected Result:**
- System remains responsive
- No timeouts or crashes
- Response time degradation < 20%
- Database connections managed properly

---

### TC-PRF-003: Database Query Performance
**Priority:** Medium | **Type:** Performance

**Test Steps:**
1. Identify slow queries using MySQL slow query log
2. Analyze with EXPLAIN
3. Optimize with indexes

**Expected Result:**
- All queries < 1 second
- Complex reports < 5 seconds
- Proper indexes on foreign keys
- No full table scans on large tables

---

### TC-PRF-004: File Upload Performance
**Priority:** Low | **Type:** Performance

**Test Steps:**
1. Upload large files (10MB)
2. Upload multiple files simultaneously

**Expected Result:**
- Upload completes without timeout
- Progress indicator shown
- Server doesn't crash
- Files stored correctly

---

## 5.14 Browser & Device Compatibility

### TC-BRW-001: Chrome Browser Testing
**Priority:** High | **Type:** Compatibility

**Test Steps:**
1. Test all critical workflows on latest Chrome
2. Verify UI rendering
3. Test JavaScript functionality

**Expected Result:**
- All features work correctly
- UI renders properly
- No console errors
- Responsive design works

---

### TC-BRW-002: Firefox Browser Testing
**Priority:** Medium | **Type:** Compatibility

**Test Steps:**
1. Repeat all critical tests on Firefox

**Expected Result:**
- Consistent behavior with Chrome
- No browser-specific issues

---

### TC-BRW-003: Mobile Device Testing
**Priority:** Medium | **Type:** Compatibility

**Test Steps:**
1. Access portal on mobile devices
2. Test student portal functions:
   - Login
   - View fee details
   - Make payment
   - View reports

**Expected Result:**
- Responsive design adapts to screen size
- Touch interactions work
- Payment gateway mobile-friendly
- No horizontal scrolling (except tables)

---

## 6. Test Execution Guidelines

### 6.1 Test Execution Cycle
1. **Smoke Testing:** Run critical path tests first
2. **Functional Testing:** Execute all functional test cases
3. **Integration Testing:** Test module interactions
4. **Regression Testing:** Verify existing features
5. **UAT:** Business users validate workflows

### 6.2 Test Case Execution Order
**Priority-based:**
1. Critical/High priority cases first
2. Medium priority cases
3. Low priority cases
4. Exploratory testing

### 6.3 Entry Criteria
- Test environment ready
- Build deployed successfully
- Test data prepared
- Test cases reviewed and approved

### 6.4 Exit Criteria
- 100% critical test cases executed
- 95% test cases passed
- No high-severity defects open
- All regression tests passed
- Performance benchmarks met

---

## 7. Defect Management

### 7.1 Defect Severity Levels

**Critical (P1):**
- System crash or data loss
- Security vulnerabilities
- Payment processing failures
- Unable to login

**High (P2):**
- Major feature not working
- Incorrect calculations
- Data integrity issues
- Email notifications failing

**Medium (P3):**
- Minor feature issues
- UI rendering problems
- Non-critical validation errors

**Low (P4):**
- Cosmetic issues
- Documentation errors
- Enhancement requests

### 7.2 Defect Lifecycle
1. New
2. Assigned
3. In Progress
4. Fixed
5. Ready for Testing
6. Retest
7. Closed / Reopened

### 7.3 Defect Reporting Template
```
Defect ID: DEF-001
Title: [Concise description]
Module: [Module name]
Severity: [P1/P2/P3/P4]
Priority: [High/Medium/Low]
Steps to Reproduce:
1. [Step 1]
2. [Step 2]
3. [Step 3]
Expected Result: [What should happen]
Actual Result: [What actually happened]
Screenshots: [Attach if applicable]
Environment: [Browser, OS, etc.]
Reported By: [Name]
Date: [Date]
```

---

## 8. Test Metrics

### 8.1 Key Metrics to Track

**Test Coverage:**
- Total test cases: [Number]
- Executed: [Number]
- Coverage %: [Executed/Total × 100]

**Test Execution:**
- Passed: [Number]
- Failed: [Number]
- Blocked: [Number]
- Pass Rate %: [Passed/(Passed+Failed) × 100]

**Defect Metrics:**
- Total defects: [Number]
- Open defects: [Number]
- Fixed defects: [Number]
- Defect density: [Defects per module]
- Defect leakage: [Defects found in production]

**Efficiency Metrics:**
- Test execution time
- Defect fix time
- Test case effectiveness

### 8.2 Test Summary Report Template
```
Test Summary Report
===================
Project: Counselling Management System
Test Cycle: [Cycle name]
Date: [Date range]

Test Execution Summary:
- Total Test Cases: 250
- Executed: 245
- Passed: 230
- Failed: 10
- Blocked: 5
- Not Executed: 5
- Pass Rate: 94%

Defect Summary:
- Critical: 0
- High: 2
- Medium: 5
- Low: 3
- Total: 10

Module-wise Results:
- Student Registration: 98% pass
- Fee Management: 92% pass
- Payment Processing: 95% pass
- Reporting: 90% pass

Risks & Issues:
- [List any risks]

Recommendations:
- [List recommendations]

Sign-off:
QA Lead: [Name]
Project Manager: [Name]
Date: [Date]
```

---

## 9. Test Automation Strategy (Future Scope)

### 9.1 Automation Candidates
- Login/Logout flows
- CRUD operations
- Payment gateway integration (using test API)
- Report generation
- Regression test suite
- API testing (if REST APIs exist)

### 9.2 Suggested Tools
- **Selenium WebDriver:** For UI automation
- **PHPUnit:** For unit testing PHP code
- **Postman/Newman:** For API testing
- **JMeter:** For performance testing
- **OWASP ZAP:** For security testing

### 9.3 Automation Framework
- **Pattern:** Page Object Model (POM)
- **Language:** PHP (PHPUnit) or Python (Selenium)
- **CI/CD Integration:** Jenkins or GitHub Actions

---

## 10. Appendices

### Appendix A: Test Data Templates

**Student Registration Test Data:**
```json
{
  "name": "Test Student 001",
  "father_name": "Test Father 001",
  "mother_name": "Test Mother 001",
  "dob": "2005-05-15",
  "mobile": "9876543210",
  "email": "teststudent001@example.com",
  "board": "NEET",
  "medium_id": 1,
  "hostel_required": 0
}
```

**Payment Test Data:**
```json
{
  "student_id": 1,
  "amount": 10000.00,
  "installment_id": 1,
  "payment_method": "online"
}
```

### Appendix B: Database Tables Reference
- Total Tables: 97
- Key Tables: Listed in conversation summary
- Database: counselling.sql (4425 lines)

### Appendix C: Email Templates
Total email templates: 24
Located in: `/email_templates/`

### Appendix D: Glossary
- **UAT:** User Acceptance Testing
- **CRUD:** Create, Read, Update, Delete
- **OMR:** Optical Mark Recognition
- **GST:** Goods and Services Tax
- **EaseBuzz:** Payment gateway provider
- **NEET:** National Eligibility cum Entrance Test
- **JEE:** Joint Entrance Examination

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-10 | QA Team | Initial version |

---

**Prepared By:** QA Team  
**Approved By:** Project Manager  
**Date:** January 10, 2026

---

## 11. References

For a comprehensive bibliography including books, research papers, standards, and online resources related to software testing, quality assurance, and educational management systems, please refer to:

**📚 [BIBLIOGRAPHY.md](./BIBLIOGRAPHY.md)**

The bibliography includes 50+ references organized into categories:
- Books on Software Testing
- Web Resources & Documentation
- Payment Gateway Testing
- Research Papers & Articles
- Standards & Compliance (ISO, IEEE, Indian IT Laws)
- Testing Tools Documentation
- Educational Management System Testing
- And more...

---

*End of Testing Strategy & Test Cases Document*
