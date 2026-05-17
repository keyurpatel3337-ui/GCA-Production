Gyan Manjari Counselling System - Complete Notification Templates
📋 Overview
This document contains all WhatsApp and Email notification templates for the complete counselling system, organized by module.
Version: 1.0.0
Last Updated: 2025-12-25
Total Templates: 45+ (WhatsApp) + 30+ (Email)
________________________________________
📊 Template Index by Module
Module	WhatsApp Templates	Email Templates
1. Admission & Registration
5	4
2. Payment & Fees
8	5
3. Appointments
4	3
4. Group Change Request
5	5
5. Scholarships
3	2
6. Examinations & Results
4	3
7. Counsellor Notifications
2	4
8. Student Portal
4	3
9. Administrative
3	4
________________________________________
📱 WhatsApp Templates (Students/Parents)
________________________________________
1. Admission & Registration
WA-REG-001: Registration Success
Field	Value
Template Code	registration_success
Category	UTILITY
Parameters	5
Dear {{1}},

Your registration for {{2}} ({{3}}) at Gyan Manjari has been received successfully on {{4}}.

📋 Registration ID: {{5}}

Our counsellor will contact you shortly to guide you through the admission process.

For queries: 079-12345678
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	course_name	11th Science
{{3}}	group_name	PCM (Physics, Chemistry, Math)
{{4}}	registration_date	25-Dec-2024
{{5}}	registration_id	GM2024001234
________________________________________
WA-REG-002: Admission Confirmed
Field	Value
Template Code	admission_confirmed
Category	UTILITY
Parameters	6
🎓 Congratulations {{1}}!

Your admission to {{2}} {{3}} at Gyan Manjari is CONFIRMED.

📄 Admission Letter No: {{4}}
💰 Token Fee Amount: ₹{{5}}
🎁 Scholarship: {{6}}

Please complete token fee payment to activate your student portal.

Portal: https://gyanmanjari.com/
Param	Variable	Example
{{1}}	student_name	Priya Patel
{{2}}	course_name	12th Commerce
{{3}}	group_name	Accountancy Group
{{4}}	admission_letter_no	ADM-2024-000123
{{5}}	token_amount	25,000.00
{{6}}	scholarship	10% Merit
________________________________________
WA-REG-003: Login Credentials
Field	Value
Template Code	login_credentials
Category	UTILITY
Parameters	4
Dear {{1}},

Your Student Portal login credentials:

🔐 Login ID: {{2}} (Aadhaar)
🔑 Password: {{3}} (Mobile)

Portal URL: {{4}}

Complete your token fee payment online to fully activate your account.

⚠️ Do not share these credentials with anyone.
Param	Variable	Example
{{1}}	student_name	Amit Kumar
{{2}}	aadhaar_masked	XXXX-XXXX-1234
{{3}}	password	9876543210
{{4}}	portal_url	https://gyanmanjari.com/
________________________________________
WA-REG-004: Counsellor Assigned
Field	Value
Template Code	counsellor_assigned
Category	UTILITY
Parameters	4
Dear {{1}},

A counsellor has been assigned to guide you through the admission process.

👤 Counsellor: {{2}}
📞 Contact: {{3}}

They will reach out to you within {{4}} hours.

For immediate queries, call our helpline: 079-12345678
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	counsellor_name	Ms. Priya Shah
{{3}}	counsellor_mobile	9876543210
{{4}}	hours	24
________________________________________
WA-REG-005: Document Verification Complete
Field	Value
Template Code	documents_verified
Category	UTILITY
Parameters	3
Dear {{1}},

✅ Your documents have been successfully verified!

📋 Status: All documents approved
📅 Verified On: {{2}}
🎓 Course: {{3}}

You can now proceed with the fee payment to complete your admission.

Login: https://gyanmanjari.com/
Param	Variable	Example
{{1}}	student_name	Sneha Gupta
{{2}}	verification_date	25-Dec-2024
{{3}}	course_name	11th Science
________________________________________
2. Payment & Fees
WA-PAY-001: Token Fee Success (Online)
Field	Value
Template Code	token_fee_success_online
Category	UTILITY
Parameters	4
Dear {{1}},

✅ Payment Successful!

Your token fee of ₹{{2}} has been received.

📄 Receipt No: {{3}}
🔢 Transaction ID: {{4}}

Your student portal is now FULLY ACTIVATED.

Login: https://gyanmanjari.com/

Thank you for choosing Gyan Manjari!
Param	Variable	Example
{{1}}	student_name	Sneha Gupta
{{2}}	amount	25,000.00
{{3}}	receipt_no	GM-20251225-000001
{{4}}	transaction_id	EZPAY123456789
________________________________________
WA-PAY-002: Token Fee Success (Offline)
Field	Value
Template Code	token_fee_success_offline
Category	UTILITY
Parameters	4
Dear {{1}},

✅ Token Fee Received!

Amount: ₹{{2}}

📄 3 Receipts Generated:
• GM Receipt (School Fee)
• MST Receipt (Trust Fee)
• GCA Receipt (Tuition Fee)

🔐 Portal Login Credentials:
Login ID: {{3}}
Password: {{4}}

Portal: https://gyanmanjari.com/
Param	Variable	Example
{{1}}	student_name	Karan Joshi
{{2}}	total_amount	25,000.00
{{3}}	aadhaar_masked	XXXX-XXXX-5678
{{4}}	password	9988776655
________________________________________
WA-PAY-003: Fee Payment Success
Field	Value
Template Code	fee_payment_success
Category	UTILITY
Parameters	5
Dear {{1}},

✅ Payment Received!

Amount: ₹{{2}}
Mode: {{3}}
Receipt No: {{4}}
Date: {{5}}

For receipts, login to student portal.

Thank you!
- Gyan Manjari Accounts
Param	Variable	Example
{{1}}	student_name	Neha Singh
{{2}}	amount	15,000.00
{{3}}	payment_mode	CASH
{{4}}	receipt_no	RCP-202512-0001
{{5}}	payment_date	25-Dec-2024
________________________________________
WA-PAY-004: Fee Reminder
Field	Value
Template Code	fee_reminder
Category	UTILITY
Parameters	4
Dear {{1}},

⏰ Fee Payment Reminder

Amount Due: ₹{{2}}
Due Date: {{3}}
Days Remaining: {{4}}

Pay online to avoid late fee:
https://gyanmanjari.com/

For queries: 079-12345678
Param	Variable	Example
{{1}}	student_name	Vikram Rao
{{2}}	amount_due	50,000.00
{{3}}	due_date	31-Dec-2024
{{4}}	days_remaining	6
________________________________________
WA-PAY-005: Fee Overdue
Field	Value
Template Code	fee_overdue
Category	UTILITY
Parameters	4
Dear {{1}},

⚠️ Fee Payment Overdue!

Overdue Amount: ₹{{2}}
Days Overdue: {{3}}
Late Fee: ₹{{4}}

Please pay immediately to avoid further penalties.

Contact: 079-12345678
Param	Variable	Example
{{1}}	student_name	Arjun Patil
{{2}}	overdue_amount	50,000.00
{{3}}	days_overdue	15
{{4}}	late_fee	500.00
________________________________________
WA-PAY-006: Refund Processed
Field	Value
Template Code	refund_processed
Category	UTILITY
Parameters	4
Dear {{1}},

💰 Refund Processed!

Refund Amount: ₹{{2}}
Reference No: {{3}}
Mode: {{4}}

Amount will be credited within 5-7 working days.

For queries, contact accounts department.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	refund_amount	10,000.00
{{3}}	reference_no	REF-2024-000123
{{4}}	refund_mode	Bank Transfer
________________________________________
WA-PAY-007: Payment Failed
Field	Value
Template Code	payment_failed
Category	UTILITY
Parameters	4
Dear {{1}},

❌ Payment Failed

Amount: ₹{{2}}
Transaction ID: {{3}}
Reason: {{4}}

If amount was deducted, it will be refunded within 5-7 days.

Please try again or contact support.
Param	Variable	Example
{{1}}	student_name	Priya Patel
{{2}}	amount	25,000.00
{{3}}	transaction_id	EZPAY123456789
{{4}}	failure_reason	Bank server timeout
________________________________________
WA-PAY-008: Partial Payment Received
Field	Value
Template Code	partial_payment
Category	UTILITY
Parameters	4
Dear {{1}},

✅ Partial Payment Received

Amount Paid: ₹{{2}}
Remaining: ₹{{3}}
Receipt No: {{4}}

Please complete the remaining payment before due date.

Portal: https://gyanmanjari.com/
Param	Variable	Example
{{1}}	student_name	Amit Kumar
{{2}}	amount_paid	10,000.00
{{3}}	remaining_amount	15,000.00
{{4}}	receipt_no	RCP-202512-0002
________________________________________
3. Appointments
WA-APT-001: Appointment Booked
Field	Value
Template Code	appointment_booked
Category	UTILITY
Parameters	5
Dear {{1}},

📅 Appointment Request Submitted

Counsellor: {{2}}
Date: {{3}}
Time: {{4}}
Purpose: {{5}}

Status: PENDING CONFIRMATION

You will be notified once confirmed.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	counsellor_name	Ms. Priya Shah
{{3}}	date	26-Dec-2024
{{4}}	time	10:30 AM
{{5}}	purpose	Course Selection
________________________________________
WA-APT-002: Appointment Confirmed
Field	Value
Template Code	appointment_confirmed
Category	UTILITY
Parameters	4
Dear {{1}},

✅ Appointment CONFIRMED!

Counsellor: {{2}}
Date: {{3}}
Time: {{4}}

Please arrive 10 minutes early.
Bring all required documents.

Location: Gyan Manjari Campus
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	counsellor_name	Ms. Priya Shah
{{3}}	date	26-Dec-2024
{{4}}	time	10:30 AM
________________________________________
WA-APT-003: Appointment Cancelled
Field	Value
Template Code	appointment_cancelled
Category	UTILITY
Parameters	3
Dear {{1}},

❌ Appointment Cancelled

Your appointment on {{2}} has been cancelled.

Reason: {{3}}

Please book a new appointment through the student portal.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	date_time	26-Dec-2024, 10:30 AM
{{3}}	reason	Counsellor unavailable
________________________________________
WA-APT-004: Appointment Reminder
Field	Value
Template Code	appointment_reminder
Category	UTILITY
Parameters	4
⏰ Appointment Reminder

Dear {{1}},

Your appointment is {{2}}.

Counsellor: {{3}}
Time: {{4}}

Please be on time!
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	when	Tomorrow
{{3}}	counsellor_name	Ms. Priya Shah
{{4}}	time	10:30 AM
________________________________________
4. Group Change Request
WA-GCR-001: Request Submitted
Field	Value
Template Code	gcr_request_submitted
Category	UTILITY
Parameters	6
Hello {{1}},

Your group change request has been submitted successfully! 🎓

📋 Request Details:
• Request No: {{2}}
• Current Group: {{3}}
• Requested Group: {{4}}

Your request is now pending approval from the Principal. You will be notified once it is reviewed.

Track your request at: {{5}}

Thank you,
{{6}}
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	request_number	GCR-2025-0001
{{3}}	current_group	Science
{{4}}	requested_group	Commerce
{{5}}	tracking_url	https://portal.edu/track/GCR-2025-0001
{{6}}	institution_name	Gyan Manjari
________________________________________
WA-GCR-002: Request Approved
Field	Value
Template Code	gcr_request_approved
Category	UTILITY
Parameters	7
Congratulations {{1}}! 🎉

Your group change request has been APPROVED!

✅ Request No: {{2}}
📚 New Group: {{3}}
💰 Fee Impact: {{4}}
📅 Effective Date: {{5}}

{{6}}

Please visit the student portal to view your updated fee structure.

Thank you,
{{7}}
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	request_number	GCR-2025-0001
{{3}}	new_group	Commerce
{{4}}	fee_impact	-₹10,000 (Decreased)
{{5}}	effective_date	26 Dec 2025
{{6}}	principal_remarks	Approved as requested
{{7}}	institution_name	Gyan Manjari
________________________________________
WA-GCR-003: Request Rejected
Field	Value
Template Code	gcr_request_rejected
Category	UTILITY
Parameters	6
Dear {{1}},

We regret to inform you that your group change request has been REJECTED.

❌ Request No: {{2}}
📋 Requested Change: {{3}} → {{4}}

📝 Reason: {{5}}

If you have any questions, please contact the administration office.

Thank you,
{{6}}
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	request_number	GCR-2025-0001
{{3}}	current_group	Science
{{4}}	requested_group	Commerce
{{5}}	rejection_reason	Request submitted after deadline
{{6}}	institution_name	Gyan Manjari
________________________________________
WA-GCR-004: Fee Adjusted
Field	Value
Template Code	gcr_fee_adjusted
Category	UTILITY
Parameters	9
Dear {{1}},

Your fee structure has been updated following your group change! 💰

📋 Request No: {{2}}
📚 New Group: {{3}}

💵 Fee Summary:
• Previous Total: ₹{{4}}
• New Total: ₹{{5}}
• Already Paid: ₹{{6}}
• Pending Amount: ₹{{7}}

{{8}}

View your updated fee details in the student portal.

Thank you,
{{9}}
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	request_number	GCR-2025-0001
{{3}}	new_group	Commerce
{{4}}	previous_total	60,000
{{5}}	new_total	50,000
{{6}}	already_paid	30,000
{{7}}	pending_amount	20,000
{{8}}	additional_note	Credit of ₹10,000 applied
{{9}}	institution_name	Gyan Manjari
________________________________________
WA-GCR-005: Request Cancelled
Field	Value
Template Code	gcr_request_cancelled
Category	UTILITY
Parameters	5
Dear {{1}},

Your group change request has been cancelled as per your request.

🚫 Request No: {{2}}
📋 Requested Change: {{3}} → {{4}}

If you wish to submit a new request, please visit the student portal.

Thank you,
{{5}}
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	request_number	GCR-2025-0001
{{3}}	current_group	Science
{{4}}	requested_group	Commerce
{{5}}	institution_name	Gyan Manjari
________________________________________
5. Scholarships
WA-SCH-001: Scholarship Approved
Field	Value
Template Code	scholarship_approved
Category	UTILITY
Parameters	3
🎉 Congratulations {{1}}!

You have been awarded:
{{2}}

Benefit: {{3}}

This will be applied to your fee automatically.

Keep up the good work!
Param	Variable	Example
{{1}}	student_name	Priya Patel
{{2}}	scholarship_name	Merit Scholarship
{{3}}	benefit	15% Fee Waiver
________________________________________
WA-SCH-002: Scholarship Application Received
Field	Value
Template Code	scholarship_applied
Category	UTILITY
Parameters	3
Dear {{1}},

Your scholarship application has been received.

📋 Scholarship: {{2}}
📅 Applied On: {{3}}

You will be notified once the review is complete.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	scholarship_name	Need-Based Scholarship
{{3}}	application_date	25-Dec-2024
________________________________________
WA-SCH-003: Scholarship Rejected
Field	Value
Template Code	scholarship_rejected
Category	UTILITY
Parameters	3
Dear {{1}},

We regret to inform you that your scholarship application was not approved.

📋 Scholarship: {{2}}
📝 Reason: {{3}}

You may apply for other available scholarships through the student portal.
Param	Variable	Example
{{1}}	student_name	Vikram Rao
{{2}}	scholarship_name	Merit Scholarship
{{3}}	rejection_reason	Eligibility criteria not met
________________________________________
6. Examinations & Results
WA-EXAM-001: Exam Schedule
Field	Value
Template Code	exam_schedule
Category	UTILITY
Parameters	4
Dear {{1}},

📝 Exam Schedule

Exam: {{2}}
Date: {{3}}
Time: {{4}}

Please reach the exam center 30 minutes before start time.

All the best!
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	exam_name	GMSAT 2024
{{3}}	date	28-Dec-2024
{{4}}	time	10:00 AM - 1:00 PM
________________________________________
WA-EXAM-002: Result Published
Field	Value
Template Code	result_published
Category	UTILITY
Parameters	5
Dear {{1}},

📊 Results Published!

Exam: {{2}}
Your Score: {{3}}/{{4}}
Percentage: {{5}}%

View detailed report:
https://gyanmanjari.com/

Best wishes!
Param	Variable	Example
{{1}}	student_name	Arjun Patil
{{2}}	exam_name	GMSAT 2024
{{3}}	score	156
{{4}}	total_marks	200
{{5}}	percentage	78
________________________________________
WA-EXAM-003: Admit Card Ready
Field	Value
Template Code	admit_card_ready
Category	UTILITY
Parameters	3
Dear {{1}},

📄 Your admit card is ready for download!

Exam: {{2}}
Roll Number: {{3}}

Download from student portal:
https://gyanmanjari.com/

Carry printed admit card to exam center.
Param	Variable	Example
{{1}}	student_name	Sneha Gupta
{{2}}	exam_name	GMSAT 2024
{{3}}	roll_number	GM-2024-0001234
________________________________________
WA-EXAM-004: Exam Reminder
Field	Value
Template Code	exam_reminder
Category	UTILITY
Parameters	4
⏰ Exam Reminder

Dear {{1}},

Your exam is {{2}}!

📝 {{3}}
⏰ {{4}}

Don't forget:
• Admit card
• Photo ID
• Stationery

All the best!
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	when	Tomorrow
{{3}}	exam_name	GMSAT 2024
{{4}}	time	10:00 AM
________________________________________
7. Counsellor Notifications
WA-CNS-001: Follow-up Reminder
Field	Value
Template Code	followup_reminder
Category	UTILITY
Parameters	3
Dear {{1}},

This is a reminder from Gyan Manjari regarding your admission inquiry.

Our counsellor {{2}} will contact you today at {{3}}.

Please keep your documents ready for discussion.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	counsellor_name	Ms. Priya Shah
{{3}}	time	2:00 PM
________________________________________
WA-CNS-002: Welcome Message
Field	Value
Template Code	welcome_message
Category	UTILITY
Parameters	2
Welcome to Gyan Manjari! 🎓

Dear {{1}},

Thank you for your interest in our institution.

Your counsellor {{2}} has been assigned to assist you with the admission process.

We look forward to guiding you on your educational journey!
Param	Variable	Example
{{1}}	student_name	Priya Patel
{{2}}	counsellor_name	Ms. Neha Sharma
________________________________________
8. Student Portal
WA-PORTAL-001: Password Reset OTP
Field	Value
Template Code	password_reset_otp
Category	AUTHENTICATION
Parameters	3
Dear {{1}},

Your OTP for password reset is: {{2}}

Valid for {{3}} minutes.

⚠️ Do not share this OTP with anyone.

If you didn't request this, please contact admin.
Param	Variable	Example
{{1}}	student_name	Amit Kumar
{{2}}	otp_code	123456
{{3}}	validity	10
________________________________________
WA-PORTAL-002: Password Changed
Field	Value
Template Code	password_changed
Category	UTILITY
Parameters	2
Dear {{1}},

Your password has been successfully changed on {{2}}.

If you didn't make this change, please contact admin immediately.

- Gyan Manjari IT Team
Param	Variable	Example
{{1}}	student_name	Priya Patel
{{2}}	date_time	25-Dec-2024, 3:45 PM
________________________________________
WA-PORTAL-003: Profile Updated
Field	Value
Template Code	profile_updated
Category	UTILITY
Parameters	2
Dear {{1}},

Your profile has been updated on {{2}}.

If you didn't make these changes, please contact admin.
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	date_time	25-Dec-2024
________________________________________
WA-PORTAL-004: Login Alert
Field	Value
Template Code	login_alert
Category	UTILITY
Parameters	3
Dear {{1}},

New login detected on your account.

📅 Time: {{2}}
📍 Device: {{3}}

If this wasn't you, change your password immediately.
Param	Variable	Example
{{1}}	student_name	Amit Kumar
{{2}}	date_time	25-Dec-2024, 4:30 PM
{{3}}	device_info	Chrome on Windows
________________________________________
9. Administrative
WA-ADMIN-001: General Announcement
Field	Value
Template Code	general_announcement
Category	UTILITY
Parameters	3
📢 Important Notice

Dear {{1}},

{{2}}

Date: {{3}}

For more details, visit the student portal.

- Gyan Manjari Administration
Param	Variable	Example
{{1}}	student_name	All Students
{{2}}	announcement	Institute will remain closed on 26th Dec for Christmas
{{3}}	date	25-Dec-2024
________________________________________
WA-ADMIN-002: Holiday Notice
Field	Value
Template Code	holiday_notice
Category	UTILITY
Parameters	3
📅 Holiday Notice

Dear {{1}},

The institute will remain closed on {{2}} for {{3}}.

Regular classes will resume from the next working day.

- Gyan Manjari Administration
Param	Variable	Example
{{1}}	student_name	Students
{{2}}	date	26-Dec-2024
{{3}}	occasion	Christmas
________________________________________
WA-ADMIN-003: Event Invitation
Field	Value
Template Code	event_invitation
Category	UTILITY
Parameters	4
🎉 You're Invited!

Dear {{1}},

Event: {{2}}
Date: {{3}}
Venue: {{4}}

We look forward to your participation!

- Gyan Manjari
Param	Variable	Example
{{1}}	student_name	Rahul Sharma
{{2}}	event_name	Annual Day Celebration
{{3}}	date_time	28-Dec-2024, 5:00 PM
{{4}}	venue	Gyan Manjari Auditorium

📊 Template Summary
WhatsApp Templates Count
Category	Count	Priority
Admission & Registration	5	HIGH
Payment & Fees	8	HIGH
Appointments	4	MEDIUM
Group Change Request	5	MEDIUM
Scholarships	3	MEDIUM
Examinations & Results	4	MEDIUM
Counsellor Notifications	2	MEDIUM
Student Portal	4	LOW
Administrative	3	LOW
TOTAL	38	-
________________________________________
🔧 Integration Quick Reference
PHP Usage
require_once 'common/whatsapp_functions.php';

// Send template
$result = sendWhatsAppTemplate($conn, $mobile, $template_id, [
    'Rahul Sharma',
    '25,000.00',
    'RCP-2024-0001'
]);

if ($result['success']) {
    echo "Sent! Message ID: " . $result['message_id'];
}
________________________________________
📅 Version History
Version	Date	Changes
1.0.0	2025-12-25	Initial comprehensive documentation

