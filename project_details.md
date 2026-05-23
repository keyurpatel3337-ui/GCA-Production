# CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
## Smt. Chandaben Mohanbhai Patel Institute of Computer Applications (CMPICA)
### Online Master of Computer Applications (MCA) — Semester IV
#### Final Project Report

---

# Enterprise Academy Management System (EAMS)

**A Project Report submitted in partial fulfillment of the requirements for the Degree of Master of Computer Applications (MCA)**

---

## 📁 Project Profile

| Profile Metric | Details / Specifications |
| :--- | :--- |
| **Project Title** | Enterprise Academy Management System (EAMS) |
| **Academic Program** | Online Master of Computer Applications (MCA) |
| **Semester / Term** | Semester IV (Final Semester Project) |
| **Affiliated Institution**| CMPICA, Charusat |
| **Academic Category** | Web-based Enterprise Resource Planning (ERP) & Database Management |
| **Target Audience** | Academy Administrators, Financial Accountants, Faculty Evaluators, Students, Parents |
| **Programming Languages** | PHP (Core backend logic & MVC architecture), JavaScript (Asynchronous AJAX/Fetch) |
| **Markup & Styling** | HTML5, Vanilla CSS (Semantic Tokens Design System), TailwindCSS (Responsive layout engine) |
| **Database Engine** | MySQL (Relational Database Service accessed via highly secure PHP PDO) |
| **Development IDE & Tools** | VS Code (Visual Studio Code), Git (Version Control), Mermaid.js (UML Modeler) |
| **Server & Deployment Platform** | Apache Web Server hosted on Amazon Web Services (AWS EC2 Cloud Instance) |
| **Network Infrastructure** | Static Elastic IP, SSL/TLS Encryption Protocols, REST API Gateway communications |
| **Local Testing Stack** | WAMP/XAMPP server suite on Windows 10/11 operating system environment |
| **Core Security Measures** | Secure password hashing (`bcrypt`), PHP Session validation, SQL Injection protection (Prepared Statements), CSRF validation, database transactions with rollbacks |
| **Specialized Integrations** | REST API Payment Gateway (Easebuzz), SMS/WhatsApp Messaging Gateway (BhashSMS), SMTP Mail Service, Custom XML-based Word (.docx) Parser, Optical Mark Recognition (OMR) grading system |

---

## 🏢 Company Profile

| Profile Metric | Details / Specifications |
| :--- | :--- |
| **Company Name** | Aksharraj Infotech |
| **Industry Domain** | Custom Software Engineering, Cloud Services, Enterprise IT Solutions & Consulting |
| **Core Services** | Web Application Development, Enterprise Resource Planning (ERP) Ecosystems, RESTful API Gateway Integrations, Relational Database Management Systems (RDBMS) |
| **Focus Areas** | Scalable Academic Portals, Secure Financial Transaction Gateways, Automated Data Parsing Systems (XML/OCR/OMR), Responsive UI Design Systems |
| **Development Methodologies**| Object-Oriented Analysis and Design (OOAD), Decoupled Agile Software Development, Test-Driven Quality Assurance |
| **Technologies Deployed** | LAMP Stack (Linux, Apache, MySQL, PHP), Modern Frontend Styling Engines, Multi-Gateway Integrations, Cloud Hosting Architectures |
| **Corporate Philosophy** | Delivering secure, performant, and scalable enterprise software solutions tailored to optimize administrative efficiency and digitize complex operational workflows. |

---

## 🗺️ Project Area
The **Enterprise Academy Management System (EAMS)** is an integrated enterprise-grade educational ecosystem. It is designed to manage end-to-end student lifecycles, administrative operations, academic workflows, automated examinations, and parent-student communications.

### 📋 Project Area Classifications
*   **[x] Web Application:** Built as a responsive, dynamic web-based dashboard and client-portal architecture using HTML5, CSS3, JavaScript (AJAX), and PHP.
*   **[x] Database:** Relies heavily on a relational MySQL database utilizing transaction rollbacks, prepared statements, and custom schemas.
*   **[x] Networking:** Incorporates REST API webhooks, SMTP protocols, and SMS/WhatsApp gateway communication over secured channels.
*   **[x] Security:** Employs standard secure password hashing algorithms (`bcrypt`), session validations, prepared statements, and role-based access management.
*   **[x] Commercial:** Processes financial transactions, category-based fee scheduling, custom scholarship calculations, and online payment gateway payments.
*   **[ ] Mobile Application:** *(Not Applicable)* System is mobile-responsive but does not contain native mobile client apps.
*   **[x] Other:** Incorporates specialized data processing algorithms like automated **Optical Mark Recognition (OMR)** grading and **XML-based Word Document (.docx) parsing** for question uploads.

### Core Functional Areas
*   **Student Lifecycle & Admissions:** Generic online registrations, multi-stage document verification, and automated enrollment pipelines for specialized batches (such as advanced entrance exam preparatory cohorts).
*   **Counselling & Onboarding:** Dynamic counselor allocation, pipeline tracking, interactive session logs, and automated appointment scheduling.
*   **Financial & Scholarship Operations:** Category-based dynamic fee schedules, automated installment extension workflows, custom scholarship rule configurations, and online payment processing.
*   **Academic & Examination Systems:** Online test engines, standardized question bank creation from Word documents (using automated XML/DOCX parsing), and offline test optical mark evaluation (OMR).
*   **Portals:** Secure, dedicated client portals for Administrative Staff, Students, and Parents.

---

## 🗄️ Database
*   **RDBMS System:** Relational Database Management System (MySQL).
*   **Architecture & Connection:** Implements the secure **PHP PDO (PHP Data Objects)** abstraction layer with prepared statements, ensuring protection against SQL injection attacks and enforcing strict transactional integrity.
*   **Key Schemas & Relations:**
    *   `tbl_student_registration`: Captures primary registration data, demographics, contacts, and verification states.
    *   `tbl_enrolled_students`: Maps verified registration entries to generated student records and enrollment IDs.
    *   `tbl_exam_questions` & `tbl_exams`: Stores test metadata, structured question sets, and user answer logs.
    *   `tbl_fees_transactions` & `tbl_receipts`: Log database audits for fee payments, transaction hashes, and payment states.

---

## 🎨 Front-end Tools
*   Vanilla CSS
*   TailwindCSS
*   AJAX (JavaScript Fetch API)
*   FontAwesome 6 Icons
*   Google Fonts (Outfit, Plus Jakarta Sans, Inter)

---

## 🛠️ Other Tools & Technologies
*   REST API-based Online Payment Gateway (Easebuzz integration)
*   Messaging API Gateway (SMS & WhatsApp notifications via BhashSMS)
*   Enterprise SMTP Mail Server
*   XML-based Word Document (.docx) Parser
*   Optical Mark Recognition (OMR) Sheet Parser
*   Daily Database SQL backup utilities

---

## 🌐 Web Technology
*   PHP (Core PHP & MVC backend architecture)
*   RESTful APIs (JSON payload communication)
*   Secure `bcrypt` hashing algorithms
*   Custom Session Tokens & CSRF protection
*   SQL Transaction-based rollbacks

---

## 💻 Platform
*   Amazon Web Services (AWS EC2 Cloud Hosting)
*   Apache HTTP Server
*   Static Elastic IP & SSL/TLS Encryption
*   Cross-platform Local Stack (Apache/PHP/MySQL on Windows WAMP/XAMPP)

---

## 📈 Project Approach

### 📋 Project Approach Classification
*   **[ ] Structured**
*   **[x] OOAD / UML (Object-Oriented Analysis and Design / Unified Modeling Language):** The project is designed using Object-Oriented principles, leveraging class abstractions (e.g., database operations, session tracking, certificate generators) and MVC modular patterns, documented with structural architecture and Mermaid UML sequence/activity diagrams.

### System Architecture Flow
```mermaid
graph TD
    subgraph Client Portals (Front-End)
        A[Admin Dashboard]
        B[Student Portal]
        C[Parent Portal]
    end

    subgraph Service Layer (Back-End Engine)
        D[EAMS REST API Core]
        E[Security & Auth Manager]
        F[Financial & Scholarship Engine]
        G[OMR & Exam Processor]
    end

    subgraph Infrastructure & Gateways
        H[(Relational MySQL Database)]
        I[Online Payment Gateway API]
        J[Messaging API Gateway]
        K[SMTP Mail Service]
    end

    A & B & C <-->|Asynchronous AJAX Requests| D
    D <--> E & F & G
    E & F & G <--> H
    F <--> I
    G <--> J & K
```

### Key Methodologies
1.  **Modular Isolation:** The codebase is compartmentalized. Modules (e.g., Financials, Academics, Counselling) are isolated from each other to ensure that updating one component does not impact the stability of another.
2.  **AJAX-First Interaction:** Core pages avoid complete standard page refreshes. Data submissions, input validations (such as Aadhaar or mobile check queries), and form actions occur asynchronously.
3.  **Strict Security Standards:** Sensitive database updates (e.g., student enrollment numbers, fee payments) are bound by SQL transactions with transaction-based rollback protection.
4.  **Environment Agnosticism:** Core configuration files automatically resolve physical host mappings, enabling instant portability between local workstations and live production environments.

---

## 📝 Project Description
The **Enterprise Academy Management System (EAMS)** is a production-grade, modular software solution that streamlines academic, administrative, and financial processes within educational academies. 

EAMS handles student workflows starting from duplicate-checked online registrations, registration processing, and counselor pipeline routing. It configures flexible fee installment schedules, computes academic scholarships based on custom rule engines, automates payment collections via payment gateways, generates secure PDF receipts, formats question structures using automated document parsing, evaluates physical OMR exam grids, and pushes real-time credentials, transaction receipts, and exam results to parents and students via integrated API messaging and email notifications.

---

## 🚨 Problem Definition
Before EAMS, educational academies faced operational challenges due to manual administrative procedures, disconnected software programs, and delayed communication channels:

> [!WARNING]
> **Key Operational Challenges Solved by EAMS:**
> 1. **Administrative Processing Overhead:** Manual checking of registrations and document uploads led to high human resources consumption and delays in student onboarding.
> 2. **Financial Management Complexity:** Handling varied fee plans, complex custom scholarship percentages, and flexible installment extensions manually led to errors, audit discrepancies, and collection gaps.
> 3. **Communication Friction:** Parents remained uninformed of student attendance, exam scores, or fee installment deadlines in a timely manner.
> 4. **Academic Grading Overhead:** Manually designing test questionnaires, formatting files, and grading paper-based grids by hand was highly time-consuming for teachers.
> 5. **Disconnected Information Pipelines:** Lack of synchronized communication between counselors, reception staff, accounting personnel, and academic heads hindered operational transparency.

EAMS overcomes these limitations by consolidating all student lifecycle, academic, financial, and messaging procedures into a secure, integrated web platform.

---

## 🔍 System Study

### 1. Existing System
In the existing manual and semi-automated system, the academy carried out its operations using disconnected Excel spreadsheets, local file storage, printed forms, and physical binders. 

*   **Admissions & Registrations:** Prospective students physically visited the administrative block to fill out printed application sheets. Administrative staff manually checked documents and hand-keyed the text into stand-alone computers, leading to errors, duplicate profiles, and processing bottlenecks.
*   **Counselor Pipeline & Tracking:** Counseling sessions were logged in physical diaries or individual local spreadsheets. There was no real-time way for administration to assign leads, monitor follow-up records, or coordinate between counselors.
*   **Accounts & Scholarship Management:** Student fee installment plans and dynamic discount allocations were computed manually in MS Excel. The collection of fees involved cash, physical checks, or bank transfers, and administrative clerks manually wrote out paper receipts, making audits complex and time-consuming.
*   **Exams & Grading:** Setting exam questionnaires required manually typing, formatting, and copying text from textbooks in MS Word. Graded evaluations relied either on hand-checking physical OMR paper bubbles or using isolated desktop optical scanners that output simple flat text files without syncing with a student portal.
*   **Communications:** Important operational notices, student test results, and outstanding fee invoices were conveyed by physically calling parents or manually copy-pasting contacts into free third-party SMS applications, limiting outreach capacity.

#### Drawbacks of the Existing System:
1.  **Redundant Data Entry & Processing Delays:** Hand-keying administrative records caused massive processing bottlenecks during peak admission periods.
2.  **High Computational Error Rate:** Calculating individual scholarship criteria and fee installment schedules in disconnected spreadsheets introduced human errors and bookkeeping disputes.
3.  **Fragmented Security & Backup Vulnerability:** Storing records in physical folders or local hard disks lacked security encryption, secure access controls, and off-site backup strategies, posing a major risk of permanent data loss.
4.  **Inefficient Grading Workflows:** Grading exams physically or through isolated OMR scanners required massive hours of labor, delaying score card analysis and updates.
5.  **Information Asymmetry:** Disconnected local sheets prevented rapid synchronization between reception counters, counseling tables, account desks, and academic coordinators.

### 2. Proposed System
The **Enterprise Academy Management System (EAMS)** is a modern, fully-integrated, web-based enterprise resource planning (ERP) platform designed to resolve the administrative bottlenecks and computational challenges of the manual workflow. By automating core processes, synchronizing database operations in real-time, and utilizing advanced data-parsing utilities, EAMS unifies administrative staff, financial accountants, faculty evaluators, students, and parents into a single synchronized portal ecosystem.

#### A. Core Functional Modules of EAMS:

*   **Student Lifecycle & Smart Admissions:** Prospective students submit admission data through a secure, responsive portal using asynchronous AJAX/Fetch API interactions. Data input fields (such as Aadhaar, phone, and email) are pre-validated on-the-fly to prevent duplicate entries. Verified registrations automatically feed into `tbl_student_registration`, triggering automated workflow routing to counselor buckets and, upon enrollment approval, generating auto-sequenced enrollment codes mapped into `tbl_enrolled_students`.
*   **Dynamic Counselling Pipeline Tracking:** Reception administrators can dynamically assign incoming leads to specific counselors. Counselors interact with a dedicated counselling backend dashboard to record session logs, track progression stages, schedule next-step appointments, and manage the student conversion funnel with complete transparency.
*   **Automated Fee Engine & Payment Gateways:** Hand-calculations are completely replaced by an automated dynamic fee engine. System administrators configure rules based on scholarship criteria, categories, or installments. Online collections are fully integrated with the Easebuzz REST API payment gateway. Once a payment is finalized, the system captures transaction hashes, writes to `tbl_fees_transactions`, updates payment ledgers, and auto-generates secure, cryptographically hashed PDF receipts logged in `tbl_receipts`.
*   **Dual-Channel Academic & Grading Systems:**
    *   *Question Bank Ingestion:* Faculty members upload standard Microsoft Word (`.docx`) test papers. EAMS leverages a custom XML-based parser to programmatically extract, structure, and categorize questions, options, and answers, populating `tbl_exam_questions` instantly.
    *   *Dual-Mode Testing:* EAMS supports online browser-based examinations logged in `tbl_exams` and automated processing of physical paper OMR (Optical Mark Recognition) sheets via an integrated high-precision scanner parser that translates visual bubbles into grade sheets, syncing results directly to student profiles in real-time.
*   **Integrated Multi-Channel Communication Service:** System events (such as admissions confirmations, successful payment receipts, upcoming fee milestones, test alerts, or attendance updates) trigger automated messaging pipelines. The system communicates directly with parents and students using SMS and WhatsApp templates via the BhashSMS gateway and high-priority SMTP mail delivery.

#### B. Architectural & Security Blueprint:

*   **Data Integrity & Security Audits:** Relational MySQL data manipulations are handled via PHP Data Objects (PDO) with strict prepared statements. Sensitive operations—such as allocating fee waivers or updating enrollment states—are strictly bound inside atomic SQL Database Transactions. If any part of a multi-table update fails, database engines execute automatic transaction rollbacks to prevent database corruption.
*   **Role-Based Access Control (RBAC):** Users are compartmentalized into distinct security classes (Super Admin, Counselor, Accountant, Evaluator, Student, and Parent). Dynamic session handling, secure cookie handshakes, and Cross-Site Request Forgery (CSRF) tokens protect backend gateways from unauthorized access or request tampering.
*   **Environment Agnostic Portability:** Core configuration parameters dynamically resolve physical pathways and server variables across local environments (WAMP/XAMPP) and live AWS EC2 Apache instances, assuring highly scalable, zero-downtime deployments.

#### Advantages of the Proposed System:

1.  **Elimination of Redundant Data Entry:** Centralized online capture with real-time field validation removes administrative data-entry bottlenecks, reducing registration-to-enrollment turnaround from days to minutes.
2.  **Absolute Financial Precision & Audit Compliance:** Automated scholarship discount rules and payment gateway callback hooks eliminate manual bookkeeping discrepancies, ensuring reliable financial ledgers.
3.  **Enterprise-Grade Security & Failure Recovery:** Robust `bcrypt` hashing, prepared statements, and atomic SQL transactions guarantee solid security parameters, while transaction rollbacks protect data from hardware/connection dropouts.
4.  **Drastic Turnaround Reduction in Grading:** Automated DOCX/XML question-bank generation and high-speed OMR optical sheet parsing reduce teachers' grading labor from hours to a few seconds per sheet.
5.  **Transparent Stakeholder Engagement:** Dedicated portals for students and parents, backed by auto-triggered SMS, WhatsApp, and SMTP alerts, resolve the classic information gaps of older manual systems.


