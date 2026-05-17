# Data Dictionary - Counselling Management System

## Database: counselling_db

---

## Table of Contents
1. [User Management Tables](#user-management-tables)
2. [Student Tables](#student-tables)
3. [Academic Master Tables](#academic-master-tables)
4. [Fee Management Tables](#fee-management-tables)
5. [Counselling Tables](#counselling-tables)
6. [Scholarship Tables](#scholarship-tables)
7. [Test Management Tables](#test-management-tables)
8. [Document Management Tables](#document-management-tables)

---

## User Management Tables

### tbl_users

**Description**: Stores all system users including counsellors, principals, accountants, super admins, and website admins.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier | - |
| name | VARCHAR(100) | NOT NULL | Full name of the user | - |
| email | VARCHAR(100) | NOT NULL, UNIQUE | Email address (used for login) | - |
| password | VARCHAR(255) | NOT NULL | Encrypted password hash | - |
| phone | VARCHAR(15) | NOT NULL, UNIQUE | Contact phone number | - |
| role_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_roles | - |
| status | ENUM('active', 'inactive', 'suspended') | NOT NULL | Account status | 'active' |
| last_login | DATETIME | NULL | Last successful login timestamp | NULL |
| failed_login_attempts | INT | NOT NULL | Count of consecutive failed logins | 0 |
| account_locked_until | DATETIME | NULL | Account lock expiry timestamp | NULL |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| updated_at | DATETIME | NULL | Last update timestamp | NULL |
| created_by | INT | NULL, FOREIGN KEY | User who created this record | NULL |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (email)
- UNIQUE KEY (phone)
- FOREIGN KEY (role_id) REFERENCES tbl_roles(id)
- FOREIGN KEY (created_by) REFERENCES tbl_users(id)

---

### tbl_roles

**Description**: Defines system roles and access levels for user authorization.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique role identifier | - |
| role_name | VARCHAR(50) | NOT NULL | Display name of the role | - |
| role_code | VARCHAR(20) | NOT NULL, UNIQUE | System code for the role | - |
| description | TEXT | NULL | Detailed role description | NULL |
| permissions | JSON | NULL | Role-specific permissions | NULL |
| is_active | BOOLEAN | NOT NULL | Role availability status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (role_code)

**Predefined Roles**:
- STUDENT
- COUNSELLOR
- PRINCIPAL
- ACCOUNTANT
- SUPER_ADMIN
- WEBSITE_ADMIN

---

## Student Tables

### tbl_gm_std_registration

**Description**: Main student registration table storing personal, academic, and contact information.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique student identifier | - |
| student_name | VARCHAR(100) | NOT NULL | Student first name | - |
| surname | VARCHAR(50) | NOT NULL | Student last name/surname | - |
| email | VARCHAR(100) | NOT NULL, UNIQUE | Student email address | - |
| mob | VARCHAR(15) | NOT NULL, UNIQUE | Student mobile number | - |
| dob | DATE | NOT NULL | Date of birth | - |
| gender | ENUM('Male', 'Female', 'Other') | NOT NULL | Gender | - |
| father_name | VARCHAR(100) | NOT NULL | Father's full name | - |
| mother_name | VARCHAR(100) | NOT NULL | Mother's full name | - |
| address | TEXT | NOT NULL | Residential address | - |
| city | VARCHAR(50) | NOT NULL | City name | - |
| state | VARCHAR(50) | NOT NULL | State name | - |
| pincode | VARCHAR(10) | NOT NULL | Postal code | - |
| course_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_courses | - |
| school_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_schools | - |
| board_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_boards | - |
| standard | INT | NOT NULL | Class/Standard (1-12) | - |
| medium_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_mediums | - |
| group_id | INT | NULL, FOREIGN KEY | Reference to tbl_groups | NULL |
| counsellor_id | INT | NULL, FOREIGN KEY | Assigned counsellor reference | NULL |
| enrollment_id | INT | NULL, FOREIGN KEY | Reference to tbl_enrolled_students | NULL |
| is_enrolled | BOOLEAN | NOT NULL | Enrollment completion status | FALSE |
| token_fees_paid | BOOLEAN | NOT NULL | Token fee payment status | FALSE |
| enrollment_date | DATETIME | NULL | Date of enrollment | NULL |
| status | ENUM('registered', 'enrolled', 'inactive') | NOT NULL | Student status | 'registered' |
| created_at | DATETIME | NOT NULL | Registration timestamp | CURRENT_TIMESTAMP |
| updated_at | DATETIME | NULL | Last update timestamp | NULL |
| created_by | INT | NULL, FOREIGN KEY | User who created record | NULL |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (email)
- UNIQUE KEY (mob)
- FOREIGN KEY (course_id) REFERENCES tbl_courses(id)
- FOREIGN KEY (school_id) REFERENCES tbl_schools(id)
- FOREIGN KEY (board_id) REFERENCES tbl_boards(id)
- FOREIGN KEY (medium_id) REFERENCES tbl_mediums(id)
- FOREIGN KEY (group_id) REFERENCES tbl_groups(id)
- FOREIGN KEY (counsellor_id) REFERENCES tbl_users(id)
- INDEX (is_enrolled)
- INDEX (token_fees_paid)

---

### tbl_enrolled_students

**Description**: Stores detailed enrollment information for students who have completed admission.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique enrollment identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| enrollment_no | VARCHAR(50) | NOT NULL, UNIQUE | Unique enrollment number | - |
| course_id | INT | NOT NULL, FOREIGN KEY | Course enrolled in | - |
| school_id | INT | NOT NULL, FOREIGN KEY | School attended | - |
| board_id | INT | NOT NULL, FOREIGN KEY | Board followed | - |
| standard | INT | NOT NULL | Class/Standard | - |
| medium_id | INT | NOT NULL, FOREIGN KEY | Medium of instruction | - |
| group_id | INT | NULL, FOREIGN KEY | Subject group (if applicable) | NULL |
| academic_year | VARCHAR(10) | NOT NULL | Academic year (e.g., 2024-25) | - |
| is_scholarship_student | BOOLEAN | NOT NULL | Scholarship recipient flag | FALSE |
| scholarship_amount | DECIMAL(10,2) | NOT NULL | Scholarship amount awarded | 0.00 |
| counsellor_id | INT | NOT NULL, FOREIGN KEY | Assigned counsellor | - |
| enrollment_date | DATETIME | NOT NULL | Date of enrollment | CURRENT_TIMESTAMP |
| status | ENUM('active', 'completed', 'withdrawn', 'suspended') | NOT NULL | Enrollment status | 'active' |
| completion_date | DATE | NULL | Course completion date | NULL |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (enrollment_no)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- INDEX (academic_year)
- INDEX (is_scholarship_student)

---

## Academic Master Tables

### tbl_courses

**Description**: Master table for courses/programs offered by the institution.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique course identifier | - |
| course_name | VARCHAR(100) | NOT NULL | Full course name | - |
| course_code | VARCHAR(20) | NOT NULL, UNIQUE | Short course code | - |
| description | TEXT | NULL | Course description | NULL |
| duration_years | INT | NOT NULL | Course duration in years | - |
| is_active | BOOLEAN | NOT NULL | Course availability status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NULL, FOREIGN KEY | User who created record | NULL |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (course_code)
- INDEX (is_active)

---

### tbl_schools

**Description**: Master table for schools/institutions partnered or managed.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique school identifier | - |
| school_name | VARCHAR(150) | NOT NULL | Full school name | - |
| school_code | VARCHAR(20) | NOT NULL, UNIQUE | Short school code | - |
| address | TEXT | NULL | School address | NULL |
| contact | VARCHAR(15) | NULL | School contact number | NULL |
| email | VARCHAR(100) | NULL | School email address | NULL |
| is_active | BOOLEAN | NOT NULL | School operational status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NULL, FOREIGN KEY | User who created record | NULL |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (school_code)
- INDEX (is_active)

---

### tbl_boards

**Description**: Master table for educational boards (CBSE, GSEB, ICSE, etc.).

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique board identifier | - |
| board_name | VARCHAR(100) | NOT NULL | Full board name | - |
| board_code | VARCHAR(20) | NOT NULL, UNIQUE | Short board code | - |
| description | TEXT | NULL | Board description | NULL |
| is_active | BOOLEAN | NOT NULL | Board availability status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (board_code)

---

### tbl_mediums

**Description**: Master table for mediums of instruction (Gujarati, English, Hindi).

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique medium identifier | - |
| medium_name | VARCHAR(50) | NOT NULL | Medium name | - |
| medium_code | VARCHAR(10) | NOT NULL, UNIQUE | Short medium code | - |
| is_active | BOOLEAN | NOT NULL | Medium availability status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (medium_code)

---

### tbl_groups

**Description**: Master table for subject groups (Science, Commerce, Arts).

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique group identifier | - |
| group_name | VARCHAR(50) | NOT NULL | Group name | - |
| group_code | VARCHAR(10) | NOT NULL, UNIQUE | Short group code | - |
| description | TEXT | NULL | Group description | NULL |
| is_active | BOOLEAN | NOT NULL | Group availability status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (group_code)

---

## Fee Management Tables

### tbl_fee_config

**Description**: Configuration table for fee structure based on course, school, medium, and group combinations.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique fee config identifier | - |
| course_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_courses | - |
| school_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_schools | - |
| medium_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_mediums | - |
| group_id | INT | NULL, FOREIGN KEY | Reference to tbl_groups | NULL |
| total_fees | DECIMAL(10,2) | NOT NULL | Total fee amount | - |
| school_fee | DECIMAL(10,2) | NOT NULL | School fee component | 0.00 |
| trust_facilities_fee | DECIMAL(10,2) | NOT NULL | Trust and facilities fee | 0.00 |
| tuition_fee_part1 | DECIMAL(10,2) | NOT NULL | First part tuition fee | 0.00 |
| tuition_fee_part2 | DECIMAL(10,2) | NOT NULL | Second part tuition fee | 0.00 |
| hostel_fee | DECIMAL(10,2) | NOT NULL | Hostel fee (if applicable) | 0.00 |
| number_of_installments | INT | NOT NULL | Max allowed installments | 1 |
| academic_year | VARCHAR(10) | NOT NULL | Academic year | - |
| is_active | BOOLEAN | NOT NULL | Configuration active status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (course_id, school_id, medium_id, group_id, academic_year)
- INDEX (academic_year)
- INDEX (is_active)

---

### tbl_student_fee_allocation

**Description**: Links students with their fee structure and tracks payment status.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique allocation identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| fee_config_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_fee_config | - |
| allocated_amount | DECIMAL(10,2) | NOT NULL | Total allocated fee amount | - |
| paid_amount | DECIMAL(10,2) | NOT NULL | Amount paid so far | 0.00 |
| pending_amount | DECIMAL(10,2) | NOT NULL | Amount pending | - |
| status | ENUM('pending', 'partial', 'paid', 'overdue') | NOT NULL | Payment status | 'pending' |
| academic_year | VARCHAR(10) | NOT NULL | Academic year | - |
| allocated_at | DATETIME | NOT NULL | Allocation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (fee_config_id) REFERENCES tbl_fee_config(id)
- INDEX (status)
- INDEX (academic_year)

---

### tbl_payments

**Description**: Records all payment transactions made by students.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique payment identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| receipt_no | VARCHAR(50) | NOT NULL, UNIQUE | Unique receipt number | - |
| amount | DECIMAL(10,2) | NOT NULL | Payment amount | - |
| payment_type | ENUM('token_fee', 'fee_payment', 'installment') | NOT NULL | Type of payment | - |
| fee_component | ENUM('token', 'school_fee', 'trust_fee', 'tuition_part1', 'tuition_part2', 'hostel_fee', 'full_fee') | NOT NULL | Specific fee component | - |
| payment_mode | ENUM('online', 'cash', 'cheque', 'dd', 'neft', 'rtgs', 'upi') | NOT NULL | Mode of payment | - |
| transaction_id | VARCHAR(100) | NULL | Gateway transaction ID | NULL |
| transaction_ref | VARCHAR(100) | NULL | Reference number (for offline) | NULL |
| status | ENUM('pending', 'success', 'failed', 'refunded') | NOT NULL | Payment status | 'pending' |
| remarks | TEXT | NULL | Additional remarks/notes | NULL |
| payment_date | DATETIME | NOT NULL | Payment timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (receipt_no)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- INDEX (payment_type)
- INDEX (status)
- INDEX (payment_date)

**Special Notes**:
- Token fee amount is fixed at ₹11,800

---

### tbl_installment_requests

**Description**: Stores student requests for fee installment plans with approval workflow.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique request identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| fee_component | ENUM('school_fee', 'trust_fee', 'tuition_part1', 'tuition_part2', 'hostel_fee', 'full_fee') | NOT NULL | Fee component for installment | - |
| total_amount | DECIMAL(10,2) | NOT NULL | Total amount to be paid | - |
| number_of_installments | INT | NOT NULL | Requested installments (2-4) | - |
| reason | TEXT | NOT NULL | Reason for installment request | - |
| status | ENUM('pending', 'accountant_approved', 'principal_approved', 'rejected', 'completed') | NOT NULL | Request status | 'pending' |
| accountant_remarks | TEXT | NULL | Accountant review comments | NULL |
| principal_remarks | TEXT | NULL | Principal review comments | NULL |
| requested_by | INT | NOT NULL, FOREIGN KEY | User who created request | - |
| approved_by_accountant | INT | NULL, FOREIGN KEY | Accountant who approved | NULL |
| approved_by_principal | INT | NULL, FOREIGN KEY | Principal who approved | NULL |
| requested_at | DATETIME | NOT NULL | Request timestamp | CURRENT_TIMESTAMP |
| accountant_approved_at | DATETIME | NULL | Accountant approval timestamp | NULL |
| principal_approved_at | DATETIME | NULL | Principal approval timestamp | NULL |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- INDEX (status)
- INDEX (requested_at)

---

### tbl_installments

**Description**: Stores individual installment details and payment tracking.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique installment identifier | - |
| request_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_installment_requests | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| installment_number | INT | NOT NULL | Installment sequence (1, 2, 3, 4) | - |
| amount | DECIMAL(10,2) | NOT NULL | Installment amount | - |
| due_date | DATE | NOT NULL | Payment due date | - |
| payment_status | ENUM('pending', 'paid', 'overdue', 'waived') | NOT NULL | Payment status | 'pending' |
| payment_id | INT | NULL, FOREIGN KEY | Reference to tbl_payments | NULL |
| paid_at | DATETIME | NULL | Payment completion timestamp | NULL |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (request_id) REFERENCES tbl_installment_requests(id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (payment_id) REFERENCES tbl_payments(id)
- INDEX (due_date)
- INDEX (payment_status)

---

## Counselling Tables

### tbl_counselling_sessions

**Description**: Records counselling sessions conducted with students.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique session identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| counsellor_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_users | - |
| session_type | ENUM('initial', 'followup', 'career', 'academic', 'personal', 'group') | NOT NULL | Type of counselling session | - |
| session_date | DATETIME | NOT NULL | Session date and time | - |
| duration_minutes | INT | NOT NULL | Session duration in minutes | - |
| session_notes | TEXT | NULL | Detailed session notes | NULL |
| issues_discussed | TEXT | NULL | Issues/topics discussed | NULL |
| objectives | TEXT | NULL | Session objectives | NULL |
| outcomes | TEXT | NULL | Session outcomes/results | NULL |
| recommendations | TEXT | NULL | Counsellor recommendations | NULL |
| next_followup_date | DATE | NULL | Scheduled follow-up date | NULL |
| status | ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') | NOT NULL | Session status | 'scheduled' |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (counsellor_id) REFERENCES tbl_users(id)
- INDEX (session_type)
- INDEX (session_date)
- INDEX (status)

---

### tbl_appointments

**Description**: Manages appointment bookings between students and counsellors.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique appointment identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| counsellor_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_users | - |
| appointment_date | DATE | NOT NULL | Appointment date | - |
| appointment_time | TIME | NOT NULL | Appointment time | - |
| purpose | TEXT | NULL | Purpose of appointment | NULL |
| status | ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'missed') | NOT NULL | Appointment status | 'pending' |
| notes | TEXT | NULL | Additional notes | NULL |
| created_at | DATETIME | NOT NULL | Booking timestamp | CURRENT_TIMESTAMP |
| updated_at | DATETIME | NULL | Last update timestamp | NULL |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (counsellor_id) REFERENCES tbl_users(id)
- INDEX (appointment_date)
- INDEX (status)

---

## Scholarship Tables

### tbl_scholarship_types

**Description**: Master table for different types of scholarships offered.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique scholarship type ID | - |
| type_name | VARCHAR(100) | NOT NULL | Scholarship type name | - |
| type_code | VARCHAR(20) | NOT NULL, UNIQUE | Short type code | - |
| description | TEXT | NULL | Detailed description | NULL |
| funding_source | VARCHAR(100) | NULL | Source of scholarship funds | NULL |
| is_active | BOOLEAN | NOT NULL | Scholarship availability | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (type_code)

---

### tbl_scholarship_rules

**Description**: Defines scholarship rules and calculation criteria.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique rule identifier | - |
| scholarship_type_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_scholarship_types | - |
| course_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_courses | - |
| percentage | DECIMAL(5,2) | NULL | Scholarship percentage (0-100) | NULL |
| fixed_amount | DECIMAL(10,2) | NULL | Fixed scholarship amount | NULL |
| min_percentage | DECIMAL(5,2) | NULL | Minimum academic percentage | NULL |
| max_percentage | DECIMAL(5,2) | NULL | Maximum academic percentage | NULL |
| criteria | TEXT | NULL | Additional eligibility criteria | NULL |
| is_active | BOOLEAN | NOT NULL | Rule active status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NULL, FOREIGN KEY | User who created record | NULL |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (scholarship_type_id) REFERENCES tbl_scholarship_types(id)
- FOREIGN KEY (course_id) REFERENCES tbl_courses(id)
- INDEX (is_active)

---

## Test Management Tables

### tbl_tests

**Description**: Stores test/examination information.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique test identifier | - |
| test_name | VARCHAR(150) | NOT NULL | Test name | - |
| test_code | VARCHAR(20) | NOT NULL, UNIQUE | Short test code | - |
| course_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_courses | - |
| test_date | DATE | NOT NULL | Scheduled test date | - |
| total_marks | INT | NOT NULL | Maximum marks | - |
| duration_minutes | INT | NOT NULL | Test duration in minutes | - |
| instructions | TEXT | NULL | Test instructions | NULL |
| is_active | BOOLEAN | NOT NULL | Test active status | TRUE |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (test_code)
- FOREIGN KEY (course_id) REFERENCES tbl_courses(id)
- INDEX (test_date)

---

### tbl_answer_keys

**Description**: Stores answer keys for tests.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique answer key identifier | - |
| test_id | INT | NOT NULL, FOREIGN KEY, UNIQUE | Reference to tbl_tests | - |
| answers | JSON | NOT NULL | Answer key in JSON format | - |
| marking_scheme | TEXT | NULL | Marking scheme details | NULL |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE FOREIGN KEY (test_id) REFERENCES tbl_tests(id)

---

### tbl_test_results

**Description**: Stores student test results and performance data.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique result identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| test_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_tests | - |
| marks_obtained | DECIMAL(6,2) | NOT NULL | Marks scored by student | - |
| percentage | DECIMAL(5,2) | NOT NULL | Calculated percentage | - |
| grade | VARCHAR(5) | NULL | Grade assigned | NULL |
| rank | INT | NULL | Rank in test | NULL |
| remarks | TEXT | NULL | Additional remarks | NULL |
| result_date | DATETIME | NOT NULL | Result declaration date | CURRENT_TIMESTAMP |
| created_at | DATETIME | NOT NULL | Record creation timestamp | CURRENT_TIMESTAMP |
| created_by | INT | NOT NULL, FOREIGN KEY | User who created record | - |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (student_id, test_id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (test_id) REFERENCES tbl_tests(id)
- INDEX (percentage)
- INDEX (rank)

---

## Document Management Tables

### tbl_documents

**Description**: Manages student document uploads and verification status.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique document identifier | - |
| student_id | INT | NOT NULL, FOREIGN KEY | Reference to tbl_gm_std_registration | - |
| document_type | ENUM('photo', 'id_proof', 'address_proof', 'birth_certificate', 'marks_card', 'transfer_certificate', 'caste_certificate', 'medical_certificate', 'other') | NOT NULL | Type of document | - |
| document_name | VARCHAR(200) | NOT NULL | Document display name | - |
| file_name | VARCHAR(255) | NOT NULL | Stored file name | - |
| file_path | VARCHAR(500) | NOT NULL | File storage path | - |
| file_size | INT | NOT NULL | File size in bytes | - |
| mime_type | VARCHAR(100) | NOT NULL | File MIME type | - |
| is_verified | BOOLEAN | NOT NULL | Document verification status | FALSE |
| uploaded_by | INT | NOT NULL, FOREIGN KEY | User who uploaded | - |
| verified_by | INT | NULL, FOREIGN KEY | User who verified | NULL |
| uploaded_at | DATETIME | NOT NULL | Upload timestamp | CURRENT_TIMESTAMP |
| verified_at | DATETIME | NULL | Verification timestamp | NULL |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (student_id) REFERENCES tbl_gm_std_registration(id)
- FOREIGN KEY (uploaded_by) REFERENCES tbl_users(id)
- FOREIGN KEY (verified_by) REFERENCES tbl_users(id)
- INDEX (document_type)
- INDEX (is_verified)

---

## Additional System Tables

### tbl_audit_logs

**Description**: System audit trail for tracking critical operations.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique log identifier | - |
| user_id | INT | NULL, FOREIGN KEY | Reference to tbl_users | NULL |
| action | VARCHAR(100) | NOT NULL | Action performed | - |
| table_name | VARCHAR(100) | NOT NULL | Affected table | - |
| record_id | INT | NULL | Affected record ID | NULL |
| old_values | JSON | NULL | Previous values | NULL |
| new_values | JSON | NULL | Updated values | NULL |
| ip_address | VARCHAR(45) | NULL | User IP address | NULL |
| user_agent | VARCHAR(500) | NULL | User browser/device info | NULL |
| created_at | DATETIME | NOT NULL | Log timestamp | CURRENT_TIMESTAMP |

**Indexes**:
- PRIMARY KEY (id)
- FOREIGN KEY (user_id) REFERENCES tbl_users(id)
- INDEX (action)
- INDEX (table_name)
- INDEX (created_at)

---

### tbl_system_settings

**Description**: Application configuration and system settings.

| Column Name | Data Type | Constraints | Description | Default |
|-------------|-----------|-------------|-------------|---------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique setting identifier | - |
| setting_key | VARCHAR(100) | NOT NULL, UNIQUE | Configuration key | - |
| setting_value | TEXT | NOT NULL | Configuration value | - |
| data_type | ENUM('string', 'integer', 'boolean', 'json') | NOT NULL | Value data type | 'string' |
| category | VARCHAR(50) | NULL | Setting category | NULL |
| description | TEXT | NULL | Setting description | NULL |
| is_editable | BOOLEAN | NOT NULL | Can be modified via UI | TRUE |
| updated_at | DATETIME | NULL | Last update timestamp | NULL |
| updated_by | INT | NULL, FOREIGN KEY | User who updated | NULL |

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE KEY (setting_key)
- INDEX (category)

---

## Database Conventions

### Naming Conventions
- **Tables**: Prefix `tbl_` followed by plural noun (e.g., tbl_users, tbl_payments)
- **Primary Keys**: Always named `id` with AUTO_INCREMENT
- **Foreign Keys**: Named as `<referenced_table>_id` (e.g., student_id, course_id)
- **Timestamp Columns**: `created_at`, `updated_at`, `deleted_at`
- **Boolean Columns**: Prefix `is_` or `has_` (e.g., is_active, is_enrolled)

### Data Type Standards
- **IDs**: INT
- **Strings**: VARCHAR with appropriate length
- **Text**: TEXT for large text content
- **Money**: DECIMAL(10,2)
- **Dates**: DATE for dates only, DATETIME for date and time
- **Boolean**: BOOLEAN (TINYINT(1) in MySQL)
- **Status/Type**: ENUM for predefined values

### Default Values
- **created_at**: CURRENT_TIMESTAMP
- **Boolean fields**: FALSE or TRUE as appropriate
- **Numeric fields**: 0 or 0.00
- **Status fields**: 'active' or 'pending' as appropriate

### Constraints
- All tables have PRIMARY KEY on `id`
- Foreign keys use ON DELETE RESTRICT and ON UPDATE CASCADE
- Unique constraints where business logic requires uniqueness
- NOT NULL constraints for mandatory fields

---

## Performance Optimization

### Indexed Columns
- Primary keys (automatic)
- Foreign keys
- Frequently queried columns (status, date fields)
- Unique identifiers (email, phone, enrollment_no)
- Columns used in WHERE clauses

### Storage Engine
- **InnoDB**: Used for all tables (supports transactions and foreign keys)

---

*Document Version: 1.0*  
*Last Updated: January 10, 2026*  
*Database Schema: counselling_db*
