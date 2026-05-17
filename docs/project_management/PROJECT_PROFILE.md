# Project Profile: Counselling Management System

## Brief Project Description

### Overview
The **Counselling Management System** is a comprehensive web-based application developed for **Gyanmanjari Vidyapith** to streamline and automate the complete student counselling, admission, and fee management lifecycle. The system manages end-to-end processes from student registration through counselling sessions, payment processing, admission confirmation, and academic tracking.

### Project Type
**Enterprise Web Application** - Educational Institution Management System

### Target Organization
**Gyanmanjari Vidyapith** - Educational institution offering multiple courses with integrated counselling and admission services.

---

## Project Objectives

1. **Automate Student Registration** - Digital registration process with document upload and validation
2. **Streamline Counselling Services** - Systematic counsellor-student assignment and session management
3. **Simplify Fee Management** - Automated fee calculation, payment processing, and installment handling
4. **Enable Online Payments** - Integration with Easebuzz payment gateway for secure online transactions
5. **Facilitate Admission Process** - Digital admission confirmation with automated enrollment and letter generation
6. **Improve Communication** - Automated email/SMS notifications for stakeholders
7. **Generate Reports** - Real-time dashboards and comprehensive reporting for management decisions
8. **Ensure Data Security** - Role-based access control and secure data management

---

## Key Features

### Student Portal
- Online registration with document upload
- Fee payment (online/offline) with receipt generation
- View admission status and counsellor details
- Book counselling appointments
- Request fee installments
- Download admission letter and receipts
- Track payment history

### Counsellor Portal
- View assigned students dashboard
- Conduct and record counselling sessions (Initial, Follow-up, Career, Academic)
- Schedule appointments and manage calendar
- Confirm student admissions
- Request installments on behalf of students
- Generate student progress reports

### Principal Portal
- Assign students to counsellors (manual/auto with load balancing)
- Approve installment requests (dual approval workflow)
- Manage scholarship allocations
- View comprehensive reports and analytics
- Monitor system performance and activities
- Oversee admission processes

### Accountant Portal
- Process offline payments (Cash, Cheque, DD, NEFT/RTGS)
- Configure fee structures by course, school, medium, and group
- Create and manage installments
- Approve installment requests (first-level approval)
- Generate financial reports (daily, weekly, monthly)
- Track collections and outstanding dues
- Issue and manage receipts

### Super Admin Portal
- User management (create, edit, delete users)
- Role and permissions management
- System configuration and settings
- Database backup and maintenance
- View audit logs and system logs
- Manage courses, schools, boards, mediums, groups
- Monitor login activities and security

### Website Admin Portal (CMS)
- Manage gallery (photos, albums)
- Update website content (about, facilities, academics)
- Manage announcements and notifications
- Update contact information
- Maintain facility descriptions

---

## System Modules

### 1. Authentication & Authorization Module
- Secure login with session management
- Role-based access control (6 user roles)
- Failed login tracking and account locking
- Password reset functionality

### 2. Student Registration Module
- Multi-step registration form with validation
- Document upload (Photo, ID Proof, Address Proof, Certificates)
- Duplicate student detection
- Auto-counsellor assignment based on course/medium/load
- Email/SMS welcome notifications

### 3. Payment Processing Module
- Token fee payment (₹11,800) - triggers enrollment eligibility
- Multiple fee components (School, Trust, Tuition Part 1 & 2, Hostel)
- Online payment via Easebuzz gateway
- Offline payment recording (Cash, Cheque, DD, NEFT)
- Automatic receipt generation
- Scholarship application and deduction

### 4. Admission Management Module
- Token fee verification
- Document verification
- Fee calculation based on course configuration
- Scholarship rule application
- Enrollment record creation with unique enrollment number
- Admission letter generation (PDF)
- Automated notifications to all stakeholders

### 5. Fee Management Module
- Configurable fee structure by academic parameters
- Fee allocation to enrolled students
- Installment request and approval workflow
- Dual approval (Accountant → Principal)
- Installment schedule generation with due dates
- Payment reminder system (7 days before, on due, overdue)
- Fee adjustment and manual correction

### 6. Counselling Management Module
- Student-counsellor assignment (manual/automatic)
- Appointment booking and scheduling
- Session types: Initial, Follow-up, Career Guidance, Academic Support
- Session notes and documentation
- Progress tracking and follow-up scheduling
- Session history and reports

### 7. Scholarship Module
- Scholarship type and rule configuration
- Percentage-based or fixed amount scholarships
- Automatic calculation and application
- Course-specific scholarship rules
- Eligibility criteria validation

### 8. Test Management Module
- Test creation and scheduling
- OMR sheet management
- Answer key configuration
- Result generation and publishing
- Rank calculation
- Performance analytics

### 9. Reporting & Analytics Module
- **Daily Reports**: Registrations, payments, sessions, appointments
- **Weekly Reports**: Collection summary, overdue payments, counsellor performance
- **Monthly Reports**: Financial analysis, enrollment trends, comprehensive statistics
- **Custom Reports**: Flexible filtering and data export
- **Dashboards**: Real-time metrics and KPIs
- **Export Formats**: PDF, Excel, CSV

### 10. Notification Module
- Email notifications (25+ templates)
- SMS alerts for critical actions
- Automated daily/weekly summaries
- Event-triggered notifications (payment, admission, appointment)

---

## Technology Stack

### Backend
- **Language**: PHP 7.4+
- **Architecture**: MVC Pattern
- **Database**: MySQL 8.0 with InnoDB engine
- **Database Access**: PDO (PHP Data Objects)
- **Session Management**: PHP Sessions / File-based

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Responsive styling
- **JavaScript** - Client-side interactivity
- **Bootstrap** (if applicable) - UI framework

### Server Environment
- **Web Server**: Apache HTTP Server
- **Operating System**: Windows/Linux
- **Local Development**: WAMP64 (Windows Apache MySQL PHP)

### External Integrations
- **Payment Gateway**: Easebuzz API (HTTPS)
- **Email Service**: SMTP Server (Port 587)
- **SMS Gateway**: REST API integration

### Database Features
- InnoDB Storage Engine with foreign key constraints
- Transaction support for data integrity
- Indexed columns for performance optimization
- Automated backup system

---

## User Roles & Access Levels

| Role | Count | Primary Functions |
|------|-------|-------------------|
| **Student** | Multiple | Register, Pay Fees, Book Appointments, View Status |
| **Counsellor** | Multiple | Conduct Sessions, Confirm Admissions, View Assigned Students |
| **Principal** | Single/Few | Assign Students, Approve Requests, View Reports |
| **Accountant** | Few | Process Payments, Manage Fees, Financial Reports |
| **Super Admin** | Few | System Configuration, User Management, Maintenance |
| **Website Admin** | Few | Content Management, Gallery, Website Updates |

---

## System Highlights

### Security Features
- Password encryption (hashing)
- SQL injection prevention (prepared statements)
- XSS protection
- Session hijacking prevention
- Role-based access control
- Audit logging for critical operations
- Failed login attempt tracking

### Performance Optimizations
- Database query optimization with indexes
- Connection pooling
- Session caching
- Optimized file storage structure
- Query result caching where applicable

### Scalability
- Modular architecture for easy feature additions
- Database normalization (3NF)
- Separate modules for different functionalities
- Configurable system parameters
- Support for multiple academic years

### User Experience
- Intuitive navigation and clean UI
- Responsive design for multiple devices
- Real-time form validation
- Informative error messages
- Progress indicators for multi-step processes
- Confirmation dialogs for critical actions

### Data Integrity
- Foreign key constraints
- Transaction management for critical operations
- Data validation at multiple levels (client, server, database)
- Referential integrity maintenance
- Automated backup system

---

## Fee Structure

### Token Fee
- **Amount**: ₹11,800 (fixed)
- **Purpose**: Enrollment eligibility
- **Trigger**: Enables admission confirmation process

### Fee Components
1. **School Fee** - Institutional charges
2. **Trust & Facilities Fee** - Infrastructure and amenities
3. **Tuition Fee Part 1** - First semester/term tuition
4. **Tuition Fee Part 2** - Second semester/term tuition
5. **Hostel Fee** - Accommodation charges (if applicable)

### Installment Options
- **2 Installments**: Split into two equal payments
- **3 Installments**: Split into three equal payments
- **4 Installments**: Split into four equal payments
- **Custom**: As per institutional policy

### Payment Modes
- **Online**: Easebuzz Payment Gateway (Credit/Debit Card, Net Banking, UPI, Wallets)
- **Offline**: Cash, Cheque, DD, NEFT, RTGS

---

## Workflow Highlights

### Registration to Enrollment Flow
1. Student registers online with personal and academic details
2. Student uploads required documents
3. System auto-assigns counsellor based on load balancing
4. Student pays token fee (₹11,800)
5. Payment confirmation triggers enrollment eligibility
6. Counsellor/Principal verifies documents
7. System calculates total fees and applies scholarship
8. Admission letter generated with enrollment number
9. Fee allocation created for enrolled student
10. Automated notifications sent to all stakeholders

### Installment Request Flow
1. Student/Counsellor submits installment request
2. System checks eligibility (pending amount, payment history)
3. Accountant reviews financial viability (first approval)
4. Principal reviews and provides final approval (second approval)
5. System generates installment schedule with due dates
6. Payment reminders sent automatically (7 days before, on due date, after due date)
7. Student makes installment payments
8. System updates fee allocation and payment status

---

## Project Benefits

### For Institution
- **Operational Efficiency**: 60-70% reduction in manual processing time
- **Financial Transparency**: Real-time collection tracking and reporting
- **Better Resource Management**: Optimized counsellor workload distribution
- **Data-Driven Decisions**: Comprehensive analytics and insights
- **Reduced Errors**: Automated calculations and validations
- **Improved Communication**: Automated notifications reduce follow-up efforts

### For Students
- **24/7 Accessibility**: Register and pay fees anytime, anywhere
- **Transparency**: Real-time visibility of fee status and payment history
- **Convenience**: Online payments and document submission
- **Quick Processing**: Faster admission confirmation
- **Better Support**: Structured counselling sessions and follow-ups

### For Staff
- **Reduced Workload**: Automation of repetitive tasks
- **Easy Access**: Centralized student information
- **Quick Reports**: Automated report generation
- **Better Tracking**: Organized session and appointment management
- **Improved Accountability**: Audit trails and activity logs

---

## Project Status

- **Development Environment**: WAMP64 on Windows
- **Project Location**: `c:\wamp64\www\counselling`
- **Database**: MySQL (counselling_db)
- **Version Control**: Git (GitHub Repository: keyurpatel3337-ui/counselling)
- **Current Branch**: main

---

## Documentation Available

- **Diagrams** (27 files in `/diagrams` directory):
  - 7 Use Case Diagrams (System + 6 Actor-specific)
  - 7 Activity Diagrams (Key processes)
  - 8 Data Flow Diagrams (Context, Level 1, 6 Level 2)
  - 5 Architecture Diagrams (Class, Component, System, Deployment, ER)
- **API Documentation**: Available in `/counselling-backend/docs`
- **Database Schema**: Available in `/counselling-backend/counselling.sql`
- **Email Templates**: 25+ templates in `/email_templates`

---

## Future Enhancements

1. **Mobile Application**: Native Android/iOS apps for students and counsellors
2. **AI-Powered Recommendations**: Smart counsellor-student matching
3. **Video Counselling**: Integration with video conferencing platforms
4. **Advanced Analytics**: Predictive analytics for student performance
5. **Parent Portal**: Separate portal for parent access and monitoring
6. **Biometric Integration**: Attendance tracking with biometric devices
7. **Cloud Deployment**: Migration to cloud infrastructure for better scalability
8. **Multi-Language Support**: Regional language support for wider accessibility

---

## Contact & Support

**Project Owner**: Gyanmanjari Vidyapith  
**Development Team**: Internal Development / External Vendor  
**Repository**: https://github.com/keyurpatel3337-ui/counselling  
**Documentation**: Available in project directory

---

*This project profile provides a comprehensive overview of the Counselling Management System. For detailed technical documentation, please refer to the diagrams and code documentation in the project repository.*
