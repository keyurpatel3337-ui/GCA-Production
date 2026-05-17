# Data Dictionary - Counselling Management System

**Database Name**: `counselling`  
**Generated From**: counselling.sql  
**Last Updated**: January 10, 2026  
**Total Tables**: 75+

---

## Table Index

| # | Table Name | Category | Description |
|---|------------|----------|-------------|
| 1 | [smtp_config](#smtp_config) | Configuration | SMTP server configuration |
| 2 | [tbl_academic_years](#tbl_academic_years) | Master | Academic years master |
| 3 | [tbl_answer_keys](#tbl_answer_keys) | Test | Test answer keys |
| 4 | [tbl_api_configurations](#tbl_api_configurations) | Configuration | API and service configurations |
| 5 | [tbl_appointments](#tbl_appointments) | Counselling | Student appointments |
| 6 | [tbl_audit_logs](#tbl_audit_logs) | System | System audit trail |
| 7 | [tbl_blueprint_questions](#tbl_blueprint_questions) | Test | Test blueprint questions |
| 8 | [tbl_blueprint_topics](#tbl_blueprint_topics) | Test | Test blueprint topics |
| 9 | [tbl_boards](#tbl_boards) | Master | Education boards master |
| 10 | [tbl_counselling_sessions](#tbl_counselling_sessions) | Counselling | Counselling sessions |
| 11 | [tbl_courses](#tbl_courses) | Master | Courses/classes master |
| 12 | [tbl_course_division](#tbl_course_division) | Master | Course-division mapping |
| 13 | [tbl_division](#tbl_division) | Master | Division master |
| 14 | [tbl_division_change_requests](#tbl_division_change_requests) | Student | Division change requests |
| 15 | [tbl_email_logs](#tbl_email_logs) | Communication | Email sending logs |
| 16 | [tbl_email_queue](#tbl_email_queue) | Communication | Email queue |
| 17 | [tbl_email_templates](#tbl_email_templates) | Communication | Email templates |
| 18 | [tbl_enrolled_students](#tbl_enrolled_students) | Student | Enrolled students (current) |
| 19 | [tbl_fee_config](#tbl_fee_config) | Fee | Fee configuration |
| 20 | [tbl_fee_installments](#tbl_fee_installments) | Fee | Fee installments |
| 21 | [tbl_fee_receipt_mapping](#tbl_fee_receipt_mapping) | Fee | Fee-receipt mapping |
| 22 | [tbl_fee_reminders](#tbl_fee_reminders) | Fee | Fee payment reminders |
| 23 | [tbl_fee_split_configuration](#tbl_fee_split_configuration) | Fee | Fee split configuration |
| 24 | [tbl_fee_structure](#tbl_fee_structure) | Fee | Fee structure |
| 25 | [tbl_gm_std_registration](#tbl_gm_std_registration) | Student | Student registration |
| 26 | [tbl_group](#tbl_group) | Master | Subject groups |
| 27 | [tbl_group_change_requests](#tbl_group_change_requests) | Student | Group change requests |
| 28 | [tbl_group_change_fee_adjustments](#tbl_group_change_fee_adjustments) | Fee | Group change fee adjustments |
| 29 | [tbl_hostel_fee_settings](#tbl_hostel_fee_settings) | Fee | Hostel fee settings |
| 30 | [tbl_hostel_installments](#tbl_hostel_installments) | Fee | Hostel fee installments |

---

## Table Definitions

### smtp_config

**Purpose**: SMTP server configuration for email sending

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| smtp_host | VARCHAR(255) | NOT NULL | SMTP server hostname | - |
| smtp_port | INT | NOT NULL | SMTP port number | 587 |
| smtp_username | VARCHAR(255) | NOT NULL | SMTP username | - |
| smtp_password | VARCHAR(255) | NOT NULL | SMTP password (encrypted) | - |
| smtp_encryption | ENUM | NOT NULL | Encryption type | 'tls' |
| smtp_from_email | VARCHAR(255) | NOT NULL | From email address | - |
| smtp_from_name | VARCHAR(255) | NOT NULL | From name | - |
| is_active | TINYINT(1) | NOT NULL | Active status | 1 |
| created_at | DATETIME | NOT NULL | Creation timestamp | - |
| updated_at | DATETIME | NULL | Last update timestamp | NULL |

**Enum Values**:
- smtp_encryption: 'tls', 'ssl', 'none'

---

### tbl_academic_years

**Purpose**: Academic years master table

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| year_name | VARCHAR(20) | NOT NULL, UNIQUE | Academic year (e.g., 2024-2025) | - |
| start_date | DATE | NULL | Year start date | NULL |
| end_date | DATE | NULL | Year end date | NULL |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Indexes**:
- UNIQUE KEY (year_name)
- KEY (created_by)

---

### tbl_answer_keys

**Purpose**: Test answer keys storage

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| paper_set_id | INT | NOT NULL, FK | Reference to paper set | - |
| test_name | VARCHAR(255) | NOT NULL | Test name | - |
| test_date | DATE | NULL | Test date | NULL |
| total_questions | INT | NOT NULL | Total questions | 100 |
| answer_key_file | VARCHAR(255) | NULL | File path | NULL |
| answers_json | TEXT | NULL | JSON format answers | NULL |
| uploaded_by | INT | NOT NULL, FK | User who uploaded | - |
| status | ENUM | NOT NULL | Answer key status | 'active' |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL | Last update timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- status: 'active', 'inactive'

**Indexes**:
- KEY (paper_set_id)
- KEY (uploaded_by)
- KEY (status)

---

### tbl_api_configurations

**Purpose**: API and SMTP configuration management

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| config_type | ENUM | NOT NULL | Configuration type | - |
| config_name | VARCHAR(100) | NOT NULL, UNIQUE | Configuration name | - |
| provider_name | VARCHAR(100) | NULL | Provider name | NULL |
| api_key | VARCHAR(255) | NULL | API key/username | NULL |
| api_secret | VARCHAR(255) | NULL | API secret/password | NULL |
| api_url | VARCHAR(500) | NULL | API base URL | NULL |
| additional_params | TEXT | NULL | JSON additional parameters | NULL |
| is_active | TINYINT(1) | NULL | Active status | 0 |
| is_primary | TINYINT(1) | NULL | Primary configuration flag | 0 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Enum Values**:
- config_type: 'whatsapp', 'sms', 'payment_gateway', 'smtp', 'other'

---

### tbl_appointments

**Purpose**: Student appointment management

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| student_id | INT | NOT NULL, FK | Reference to student | - |
| counsellor_id | INT | NOT NULL, FK | Reference to counsellor | - |
| appointment_date | DATE | NOT NULL | Appointment date | - |
| appointment_time | TIME | NOT NULL | Appointment time | - |
| purpose | TEXT | NULL | Purpose of appointment | NULL |
| status | ENUM | NOT NULL | Appointment status | 'pending' |
| notes | TEXT | NULL | Additional notes | NULL |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL | Last update timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- status: 'pending', 'confirmed', 'completed', 'cancelled'

**Indexes**:
- KEY (student_id)
- KEY (counsellor_id)
- KEY (status)
- KEY idx_appointment_date (appointment_date, status)

---

### tbl_audit_logs

**Purpose**: System audit trail for critical operations

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| user_id | INT | NULL, FK | User who performed action | NULL |
| action | VARCHAR(100) | NOT NULL | Action performed | - |
| module | VARCHAR(50) | NULL | Module/section affected | NULL |
| details | TEXT | NULL | Action details | NULL |
| ip_address | VARCHAR(45) | NULL | User IP address | NULL |
| created_at | TIMESTAMP | NULL | Log timestamp | CURRENT_TIMESTAMP |

**Storage Engine**: MyISAM

---

### tbl_blueprint_questions

**Purpose**: Test blueprint question mapping

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| blueprint_topic_id | INT | NOT NULL, FK | Reference to blueprint topic | - |
| paper_set_id | INT | NOT NULL, FK | Reference to paper set | - |
| question_number | INT | NOT NULL | Question number (1-100) | - |
| difficulty_level | ENUM | NOT NULL | Difficulty level | - |
| marks | DECIMAL(5,2) | NULL | Marks for question | 1.00 |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- difficulty_level: 'low', 'medium', 'high'

**Indexes**:
- UNIQUE KEY (paper_set_id, question_number)
- KEY (blueprint_topic_id)
- KEY (difficulty_level)

---

### tbl_blueprint_topics

**Purpose**: Test blueprint topics configuration

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| paper_set_id | INT | NOT NULL, FK | Reference to paper set | - |
| sr_no | INT | NOT NULL | Serial number | - |
| subject_id | INT | NULL, FK | Reference to subject | NULL |
| topic_id | INT | NULL, FK | Reference to topic | NULL |
| total_questions | INT | NOT NULL | Total questions | 0 |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL | Last update timestamp | CURRENT_TIMESTAMP |

---

### tbl_boards

**Purpose**: Education boards master (GSEB, CBSE, ICSE, etc.)

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| board_name | VARCHAR(100) | NOT NULL, UNIQUE | Board name | - |
| board_code | VARCHAR(20) | NULL | Short code | NULL |
| description | TEXT | NULL | Board description | NULL |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

---

### tbl_counselling_sessions

**Purpose**: Counselling sessions with test marks reference

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| session_number | VARCHAR(50) | NULL, UNIQUE | Auto-generated session number | NULL |
| student_id | INT | NOT NULL, FK | FK to tbl_gm_std_registration.id | - |
| enrollment_id | INT | NULL, FK | FK to tbl_enrolled_students | NULL |
| counsellor_id | INT | NOT NULL, FK | FK to tbl_users.id (counsellor) | - |
| session_type | ENUM | NOT NULL | Type of session | 'academic' |
| session_date | DATE | NOT NULL | Session date | - |
| session_time | TIME | NOT NULL | Session time | - |
| duration_minutes | INT | NULL | Duration in minutes | 30 |
| parent_attended | TINYINT(1) | NULL | Parent attendance flag | 0 |
| parent_name | VARCHAR(100) | NULL | Parent name | NULL |
| parent_relation | ENUM | NULL | Parent relation | NULL |
| parent_mobile | VARCHAR(15) | NULL | Parent mobile | NULL |
| omr_test_id | INT | NULL, FK | FK to OMR test | NULL |
| descriptive_test_id | INT | NULL, FK | FK to descriptive test | NULL |
| academic_strengths | TEXT | NULL | Student strengths | NULL |
| improvement_areas | TEXT | NULL | Areas for improvement | NULL |
| recommendations | TEXT | NULL | Counsellor recommendations | NULL |
| action_items | TEXT | NULL | Action items | NULL |
| parent_feedback | TEXT | NULL | Parent feedback | NULL |
| session_summary | TEXT | NULL | Session summary | NULL |
| status | ENUM | NULL | Session status | 'scheduled' |
| completed_at | DATETIME | NULL | Completion timestamp | NULL |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- session_type: 'admission', 'academic', 'career', 'parent_meeting', 'follow_up', 'test_review'
- parent_relation: 'father', 'mother', 'guardian', 'other'
- status: 'scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'

---

### tbl_courses

**Purpose**: Courses/Classes master

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| course_name | VARCHAR(100) | NOT NULL | Course/Class name | - |
| board_id | INT | NULL, FK | Reference to board | NULL |
| course_code | VARCHAR(20) | NULL | Short code | NULL |
| standard | VARCHAR(10) | NULL | Standard/Class level | NULL |
| description | TEXT | NULL | Course description | NULL |
| display_order | INT | NULL | Display order | 0 |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Indexes**:
- UNIQUE KEY (course_name, board_id)

---

### tbl_course_division

**Purpose**: Course-Division mapping with roll number tracking

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| course_id | INT | NOT NULL, FK | Reference to tbl_courses.id | - |
| group_id | INT | NOT NULL, FK | Reference to tbl_group.id | - |
| division_id | INT | NOT NULL, FK | Reference to tbl_division.id | - |
| start_roll_no | INT | NOT NULL | Starting roll number | 1 |
| current_roll_no | INT | NOT NULL | Current roll number counter | 0 |
| total_students | INT | NOT NULL | Total students count | 0 |
| max_capacity | INT | NULL | Maximum capacity | NULL |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Indexes**:
- UNIQUE KEY (course_id, group_id, division_id)

---

### tbl_division

**Purpose**: Division master table (A, B, C, D, E)

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| division_name | VARCHAR(50) | NOT NULL, UNIQUE | Division name | - |
| description | TEXT | NULL | Optional description | NULL |
| display_order | INT | NULL | Display order | 0 |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

---

### tbl_division_change_requests

**Purpose**: Student division change requests with approval workflow

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| enrollment_id | INT | NOT NULL, FK | Reference to enrollment | - |
| student_id | INT | NOT NULL, FK | Reference to student | - |
| current_division_id | INT | NOT NULL, FK | Current division | - |
| requested_division_id | INT | NOT NULL, FK | Requested division | - |
| current_roll_no | INT | NULL | Current roll number | NULL |
| reason | TEXT | NOT NULL | Reason for change | - |
| request_date | DATETIME | NULL | Request date | CURRENT_TIMESTAMP |
| status | ENUM | NULL | Request status | 'pending' |
| reviewed_by | INT | NULL, FK | Reviewer user ID | NULL |
| review_date | DATETIME | NULL | Review date | NULL |
| review_remarks | TEXT | NULL | Review remarks | NULL |
| new_roll_no | INT | NULL | New roll number | NULL |
| counsellor_id | INT | NULL, FK | Assigned counsellor | NULL |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- status: 'pending', 'under_review', 'approved', 'rejected'

---

### tbl_email_logs

**Purpose**: Email sending logs and tracking

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| template_id | INT | NULL, FK | Template used | NULL |
| template_code | VARCHAR(100) | NULL | Template code | NULL |
| config_id | INT | NULL, FK | SMTP config used | NULL |
| recipient_email | VARCHAR(255) | NOT NULL | Recipient email | - |
| recipient_name | VARCHAR(200) | NULL | Recipient name | NULL |
| recipient_type | ENUM | NULL | Recipient type | NULL |
| reference_type | ENUM | NULL | Reference type | NULL |
| reference_id | INT | NULL | Related record ID | NULL |
| subject | VARCHAR(500) | NOT NULL | Email subject | - |
| html_body | TEXT | NULL | HTML body | NULL |
| text_body | TEXT | NULL | Plain text body | NULL |
| variables_used | JSON | NULL | Variables replaced | NULL |
| cc_emails | TEXT | NULL | CC recipients | NULL |
| bcc_emails | TEXT | NULL | BCC recipients | NULL |
| attachments | JSON | NULL | Attached files | NULL |
| status | ENUM | NOT NULL | Email status | 'queued' |
| error_message | TEXT | NULL | Error message | NULL |
| smtp_response | TEXT | NULL | SMTP response | NULL |
| sent_at | DATETIME | NULL | Sent timestamp | NULL |
| delivered_at | DATETIME | NULL | Delivery timestamp | NULL |
| opened_at | DATETIME | NULL | Opened timestamp | NULL |
| clicked_at | DATETIME | NULL | Clicked timestamp | NULL |
| retry_count | INT | NULL | Retry attempts | 0 |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Enum Values**:
- recipient_type: 'student', 'parent', 'staff', 'admin', 'counsellor', 'accountant', 'principal', 'system'
- reference_type: 'student', 'admission', 'payment', 'appointment', 'fee', 'scholarship', 'result', 'other'
- status: 'queued', 'sent', 'delivered', 'failed', 'bounced', 'complained'

---

### tbl_email_queue

**Purpose**: Email sending queue for asynchronous processing

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| template_id | INT | NOT NULL, FK | Template to use | - |
| recipient_email | VARCHAR(255) | NOT NULL | Recipient email | - |
| recipient_name | VARCHAR(200) | NULL | Recipient name | NULL |
| recipient_type | ENUM | NULL | Recipient type | NULL |
| reference_type | ENUM | NULL | Reference type | NULL |
| reference_id | INT | NULL | Related record ID | NULL |
| variables | JSON | NOT NULL | Template variables | - |
| cc_emails | TEXT | NULL | CC recipients | NULL |
| bcc_emails | TEXT | NULL | BCC recipients | NULL |
| attachments | JSON | NULL | Attachments | NULL |
| priority | ENUM | NULL | Email priority | 'normal' |
| scheduled_at | DATETIME | NULL | Scheduled send time | NULL |
| status | ENUM | NULL | Queue status | 'pending' |
| retry_count | INT | NULL | Retry count | 0 |
| max_retries | INT | NULL | Max retries | 3 |
| error_message | TEXT | NULL | Error message | NULL |
| log_id | INT | NULL, FK | Reference to email log | NULL |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| processed_at | DATETIME | NULL | Processing timestamp | NULL |

**Enum Values**:
- priority: 'low', 'normal', 'high'
- status: 'pending', 'processing', 'sent', 'failed', 'cancelled'

---

### tbl_email_templates

**Purpose**: Email notification templates

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| template_code | VARCHAR(100) | NOT NULL, UNIQUE | Unique template code | - |
| template_name | VARCHAR(200) | NOT NULL | Display name | - |
| template_category | ENUM | NOT NULL | Template category | 'STAFF_NOTIFICATION' |
| recipient_type | ENUM | NOT NULL | Recipient type | - |
| subject | VARCHAR(300) | NOT NULL | Email subject with variables | - |
| html_content | TEXT | NOT NULL | HTML template | - |
| text_content | TEXT | NULL | Plain text fallback | NULL |
| variables | JSON | NULL | Available variables | NULL |
| cc_emails | TEXT | NULL | CC emails | NULL |
| bcc_emails | TEXT | NULL | BCC emails | NULL |
| priority | ENUM | NULL | Email priority | 'normal' |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Enum Values**:
- template_category: 'STAFF_NOTIFICATION', 'STUDENT_NOTIFICATION', 'ADMIN_NOTIFICATION', 'SYSTEM_ALERT', 'DAILY_REPORT'
- recipient_type: 'student', 'parent', 'staff', 'admin', 'counsellor', 'accountant', 'principal', 'system'
- priority: 'low', 'normal', 'high'

---

### tbl_enrolled_students

**Purpose**: Core enrolled students - normalized structure

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| enrollment_id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| enrollment_no | VARCHAR(50) | NOT NULL, UNIQUE | Unique enrollment number | - |
| registration_id | INT | NULL, FK | FK to tbl_student_registration | NULL |
| course_id | INT | NOT NULL, FK | FK to tbl_courses | - |
| school_id | INT | NOT NULL, FK | FK to tbl_schools | - |
| board_id | INT | NOT NULL, FK | FK to tbl_boards | - |
| standard | INT | NOT NULL | Class/Standard | - |
| medium_id | INT | NOT NULL, FK | FK to tbl_medium | - |
| group_id | INT | NULL, FK | FK to tbl_group | NULL |
| division_id | INT | NULL, FK | FK to tbl_division | NULL |
| roll_no | INT | NULL | Roll number in division | NULL |
| academic_year | INT | NOT NULL | Year of admission | - |
| enrollment_date | DATE | NOT NULL | Date of enrollment | - |
| enrollment_status | ENUM | NOT NULL | Student enrollment status | 'active' |
| is_active | TINYINT(1) | NOT NULL | Active/Inactive flag | 1 |
| enrolled_by | INT | NULL, FK | Admin who created enrollment | NULL |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL | Last update timestamp | CURRENT_TIMESTAMP |

**Enum Values**:
- enrollment_status: 'active', 'inactive', 'transferred', 'withdrawn', 'completed', 'suspended', 'dropped'

**Indexes**:
- UNIQUE KEY (enrollment_no)
- UNIQUE KEY (course_id, academic_year, division_id)
- Multiple composite indexes for performance

---

### tbl_fee_config

**Purpose**: Fee configuration based on course, medium, and group

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| academic_year | VARCHAR(20) | NOT NULL | Academic year | - |
| term | VARCHAR(50) | NULL | Term/Semester name | NULL |
| course_id | INT | NULL, FK | Course ID | NULL |
| course_name | VARCHAR(100) | NOT NULL | Course name | - |
| medium_id | INT | NULL, FK | Medium ID | NULL |
| group_id | INT | NULL, FK | Group ID | NULL |
| school_fee | DECIMAL(10,2) | NULL | School fee | 15000.00 |
| trust_facilities_fee | DECIMAL(10,2) | NULL | Trust facilities fee | 15000.00 |
| hostel_fee | DECIMAL(10,2) | NULL | Hostel fee | 0.00 |
| hostel_fee_gst_applicable | TINYINT(1) | NULL | GST applicable flag | 0 |
| hostel_fee_gst_rate | DECIMAL(5,2) | NULL | GST rate | 0.00 |
| tuition_fee_part1 | DECIMAL(10,2) | NULL | Tuition fee Part-1 | 0.00 |
| tuition_fee_part2 | DECIMAL(10,2) | NULL | Tuition fee Part-2 | 0.00 |
| token_fee | DECIMAL(10,2) | NOT NULL | Initial payment | 0.00 |
| total_fees | DECIMAL(10,2) | NOT NULL | Total fees including GST | - |
| number_of_installments | INT | NOT NULL | Number of installments | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| school_id | INT | NULL, FK | School ID | NULL |
| school_fee_label | VARCHAR(50) | NULL | Label for school fee | 'Split Label 1' |
| trust_fee_label | VARCHAR(50) | NULL | Label for trust fee | 'Split Label 2' |
| tuition_fee_label | VARCHAR(50) | NULL | Label for tuition fee | 'Split Label 2' |
| school_fee_gst | TINYINT(1) | NULL | GST applicable | 0 |
| token_fee_gst | TINYINT(1) | NULL | GST applicable | 0 |
| token_fee_label | VARCHAR(50) | NULL | Token fee label | 'GCA' |
| trust_fee_gst | TINYINT(1) | NULL | GST applicable | 0 |
| tuition_fee_gst | TINYINT(1) | NULL | GST applicable | 0 |

**Indexes**:
- UNIQUE KEY (academic_year, term, course_id, medium_id, group_id)

---

### tbl_fee_installments

**Purpose**: Individual installment records for each student

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| allocation_id | INT | NOT NULL, FK | References tbl_student_fee_allocation | - |
| student_id | INT | NOT NULL, FK | References tbl_users.id | - |
| fee_config_id | INT | NOT NULL, FK | References tbl_fee_config.id | - |
| installment_number | INT | NOT NULL | Installment sequence | - |
| due_amount | DECIMAL(10,2) | NOT NULL | Amount due | - |
| due_date | DATE | NULL | Due date | NULL |
| paid_amount | DECIMAL(10,2) | NOT NULL | Amount paid | 0.00 |
| payment_status | ENUM | NOT NULL | Payment status | 'pending' |
| payment_date | DATETIME | NULL | Payment date | NULL |
| payment_method | VARCHAR(50) | NULL | Payment method | NULL |
| payment_id | VARCHAR(200) | NULL | Gateway payment ID | NULL |
| gateway_name | VARCHAR(50) | NULL | Payment gateway | NULL |
| bank_ref_num | VARCHAR(100) | NULL | Bank reference | NULL |
| payment_response | TEXT | NULL | Gateway response JSON | NULL |
| webhook_received | TINYINT(1) | NULL | Webhook received flag | 0 |
| webhook_received_at | DATETIME | NULL | Webhook timestamp | NULL |
| payment_source | ENUM | NULL | Payment source | 'manual' |
| transaction_id | VARCHAR(100) | NULL | Transaction ID | NULL |
| receipt_number | VARCHAR(50) | NULL | Receipt number | NULL |
| remarks | TEXT | NULL | Additional remarks | NULL |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

**Enum Values**:
- payment_status: 'pending', 'partial', 'paid'
- payment_source: 'callback', 'webhook', 'manual', 'offline'

---

### tbl_gm_std_registration

**Purpose**: Student registration (main student table)

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| surname | VARCHAR(100) | NOT NULL | Student surname | - |
| student_name | VARCHAR(100) | NOT NULL | Student first name | - |
| fathers_name | VARCHAR(100) | NOT NULL | Father's name | - |
| dob | DATE | NOT NULL | Date of birth | - |
| gender | VARCHAR(10) | NOT NULL | Gender | - |
| board_id | INT | NULL, FK | Board ID | NULL |
| medium_id | INT | NULL, FK | Medium ID | NULL |
| group_id | INT | NULL, FK | Group ID | NULL |
| standard | INT | NULL | Class/Standard | NULL |
| school_id | INT | NULL, FK | Assigned school ID | NULL |
| course_id | INT | NULL, FK | Course selected | NULL |
| mob | VARCHAR(15) | NOT NULL | Mobile number | - |
| amob | VARCHAR(15) | NULL | Alternate mobile | NULL |
| aadhaar | VARCHAR(12) | NULL | Aadhaar number | NULL |
| schoolname | VARCHAR(255) | NOT NULL | Previous school name | - |
| schaddr | TEXT | NOT NULL | Previous school address | - |
| addr | TEXT | NOT NULL | Residential address | - |
| district | VARCHAR(100) | NOT NULL | District | - |
| fathername | VARCHAR(100) | NOT NULL | Father name | - |
| fatheredu | VARCHAR(100) | NOT NULL | Father education | - |
| ocupation | VARCHAR(100) | NOT NULL | Father occupation | - |
| ofcaddr | TEXT | NOT NULL | Office address | - |
| hostel_required | VARCHAR(10) | NOT NULL | Hostel required | 'No' |
| hash_password | VARCHAR(255) | NOT NULL | Hashed password | - |
| password | VARCHAR(100) | NOT NULL | Plain password (deprecated) | - |
| declaration_agreed | TINYINT(1) | NOT NULL | Declaration agreement | 0 |
| created_at | TIMESTAMP | NOT NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL | Last update timestamp | CURRENT_TIMESTAMP |
| last_login | DATETIME | NULL | Last login timestamp | NULL |
| status | TINYINT(1) | NOT NULL | Active status | 1 |
| token_fees_paid | TINYINT(1) | NULL | Token fee paid flag | 0 |
| token_payment_id | VARCHAR(200) | NULL | Payment gateway ID | NULL |
| token_transaction_id | VARCHAR(200) | NULL | Transaction ID | NULL |
| token_amount | DECIMAL(10,2) | NULL | Token fee amount | 0.00 |
| token_payment_date | DATETIME | NULL | Payment date | NULL |
| scholarship_amount | DECIMAL(10,2) | NULL | Scholarship amount | 0.00 |
| scholarship_percentage | DECIMAL(5,2) | NULL | Scholarship percentage | 0.00 |
| scholarship_rule_id | INT | NULL, FK | Scholarship rule applied | NULL |
| additional_scholarship_type | ENUM | NULL | Additional scholarship type | NULL |
| additional_scholarship_value | DECIMAL(10,2) | NULL | Additional value | NULL |
| additional_scholarship_amount | DECIMAL(10,2) | NULL | Calculated amount | NULL |
| additional_scholarship_remarks | TEXT | NULL | Scholarship remarks | NULL |
| admission_confirmed | TINYINT(1) | NULL | Admission confirmed flag | 0 |
| admission_confirmed_by | INT | NULL, FK | User who confirmed | NULL |
| admission_confirmed_date | DATETIME | NULL | Confirmation date | NULL |
| admission_letter_generated | TINYINT(1) | NULL | Letter generated flag | 0 |
| admission_letter_number | VARCHAR(50) | NULL | Letter number | NULL |
| token_payment_mode | ENUM | NULL | Payment mode | 'pending' |
| is_enrolled | TINYINT(1) | NULL | Enrollment status | 0 |
| enrollment_id | INT | NULL, FK | Reference to enrollment | NULL |
| enrollment_date | DATETIME | NULL | Enrollment date | NULL |
| counsellor_id | INT | NULL, FK | Assigned counsellor | NULL |
| academic_year_id | INT | NULL, FK | Academic year | NULL |
| email | VARCHAR(255) | NULL | Email address | NULL |

**Enum Values**:
- additional_scholarship_type: 'percentage', 'amount'
- token_payment_mode: 'online', 'offline', 'pending'

**Indexes**:
- Multiple indexes for performance optimization
- KEY (token_fees_paid)
- KEY (is_enrolled)
- KEY (admission_confirmed)

---

### tbl_group

**Purpose**: Subject groups master (e.g., A Group Board, B Group Board/NEET)

| Column | Type | Constraints | Description | Default |
|--------|------|-------------|-------------|---------|
| id | INT | PK, AUTO_INCREMENT | Unique identifier | - |
| group_name | VARCHAR(100) | NOT NULL, UNIQUE | Group name | - |
| description | TEXT | NULL | Group description | NULL |
| is_active | TINYINT(1) | NULL | Active status | 1 |
| created_by | INT | NOT NULL, FK | User who created | - |
| created_at | TIMESTAMP | NULL | Creation timestamp | CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NULL | Last update timestamp | NULL |

---

## Database Conventions

### Naming Standards
- **Table Prefix**: `tbl_` for application tables
- **Primary Keys**: `id` or `[table_name]_id` with AUTO_INCREMENT
- **Foreign Keys**: `[referenced_table]_id` format
- **Timestamps**: `created_at`, `updated_at` using TIMESTAMP
- **Boolean Fields**: TINYINT(1) with 0/1 values
- **Status Fields**: ENUM for predefined values

### Data Types
- **IDs**: INT
- **Strings**: VARCHAR with appropriate length
- **Large Text**: TEXT
- **Money**: DECIMAL(10,2)
- **Dates**: DATE for dates only
- **Date/Time**: DATETIME for specific timestamps
- **Timestamps**: TIMESTAMP with timezone support
- **Boolean**: TINYINT(1)
- **JSON**: JSON type for complex data

### Storage Engines
- **InnoDB**: Primary engine (supports transactions, foreign keys)
- **MyISAM**: Used for audit_logs (higher performance for logging)

### Character Set
- **Charset**: utf8mb4
- **Collation**: utf8mb4_unicode_ci or utf8mb4_0900_ai_ci

---

## Notes

1. **Token Fee**: Fixed at ₹11,800 (configurable in tbl_fee_config)
2. **GST Rates**: 18% on tuition fees, configurable for hostel fees
3. **Enrollment Number Format**: Unique per student per year
4. **Roll Number Management**: Auto-assigned through tbl_course_division
5. **Payment Gateway**: Integrated with Easebuzz (configurable in tbl_api_configurations)
6. **Email System**: Queue-based asynchronous email processing
7. **Academic Year**: Format YYYY-YYYY (e.g., 2024-2025)

---

*This data dictionary is generated from counselling.sql database dump dated January 10, 2026. For complete schema details, refer to the SQL file.*
