# BhashSMS WhatsApp API Integration Guide

## API Endpoint

```
http://bhashsms.com/api/sendmsgutil.php
```

## Credentials (Static Configuration)

- **Username**: `GYANMANJARI_CAREER`
- **Password**: `098765`
- **Sender ID**: `BUZWAP`

## Configuration Location

File: `common/config/app-config.php`
Function: `getWhatsAppConfig()`

---

## API Formats

### 1. Normal Template Message

**Use Case**: Send approved WhatsApp templates without variables

```
http://bhashsms.com/api/sendmsgutil.php?
  user=GYANMANJARI_CAREER
  &pass=098765
  &sender=BUZWAP
  &phone=7436044629
  &text=TEMPLATENAME
  &priority=wa
  &stype=normal
```

**Parameters**:

- `user`: Username (GYANMANJARI_CAREER)
- `pass`: Password (098765)
- `sender`: Sender ID (BUZWAP)
- `phone`: Mobile number **WITHOUT 91** (for multiple: comma-separated)
- `text`: Template name registered with WhatsApp
- `priority`: `wa` (WhatsApp)
- `stype`: `normal` (template message)

**Note**: Do NOT include `htype` parameter for normal templates

---

### 2. Template Message with Variables/Parameters

**Use Case**: Send templates with dynamic content (e.g., student name, registration ID)

```
http://bhashsms.com/api/sendmsgutil.php?
  user=GYANMANJARI_CAREER
  &pass=098765
  &sender=BUZWAP
  &phone=7436044629
  &text=gci_0007
  &priority=wa
  &stype=normal
  &Params=Rahul Kumar,11th Science,Gyanmanjari Career,REG001,0141-1234567
```

**Additional Parameter**:

- `Params`: Comma-separated values matching template variables order

**Example**: Template `gci_0007` with 5 variables:

1. student_name → `Rahul Kumar`
2. course_name → `11th Science`
3. school_name → `Gyanmanjari Career`
4. registration_id → `REG001`
5. contact_info → `0141-1234567`

---

### 3. Template with Media (Image/Video/Document)

**Use Case**: Send template with header image, video, or document

```
http://bhashsms.com/api/sendmsgutil.php?
  user=GYANMANJARI_CAREER
  &pass=098765
  &sender=BUZWAP
  &phone=7436044629
  &text=TEMPLATENAME
  &priority=wa
  &stype=normal
  &Params=param1,param2
  &htype=image
  &url=https://example.com/image.jpg
```

**Additional Parameters**:

- `htype`: Media type (`image`, `video`, or `document`)
- `url`: Public URL of the media file

**Media Requirements**:

- URL must be publicly accessible
- Supported formats:
  - Images: JPG, PNG
  - Videos: MP4
  - Documents: PDF, DOC, DOCX

---

### 4. OTP/Authentication Messages

**Use Case**: Send one-time passwords for authentication

```
http://bhashsms.com/api/sendmsgutil.php?
  user=GYANMANJARI_CAREER
  &pass=098765
  &sender=BUZWAP
  &phone=7436044629
  &text=OTP_TEMPLATE
  &priority=wa
  &stype=auth
  &Params=123456
```

**Key Differences**:

- `stype`: `auth` (authentication type)
- `Params`: Single OTP code

**Function**: `sendWhatsAppOTP_BhashSMS($config, $recipient, $template_name, $otp_code)`

---

### 5. Non-Template Text (After Customer Replies)

**Use Case**: Send plain text messages in existing conversations

```
http://bhashsms.com/api/sendmsgutil.php?
  user=GYANMANJARI_CAREER
  &pass=098765
  &sender=BUZWAP
  &phone=7436044629
  &text=Your custom text message here
  &priority=wa
  &stype=normal
  &htype=normal
```

**Key Parameters**:

- `text`: Plain text message (NOT a template name)
- `htype`: `normal` (required for non-template text)

**Function**: `sendWhatsAppText_BhashSMS($config, $recipient, $text)`

**Note**: Can only be sent after customer initiates conversation or replies

---

## Implementation in Code

### Main Template Function

**File**: `common/helpers/whatsapp_functions.php`

```php
// Send template with variables
sendWhatsAppTemplate($conn, $recipient, $template_id, $variables);

// Example:
sendWhatsAppTemplate(
    $conn,
    '7436044629',
    45,  // template_id for gci_0007
    ['Rahul Kumar', '11th Science', 'Gyanmanjari Career', 'REG001', '0141-1234567']
);
```

### Provider-Specific Functions

#### 1. BhashSMS Template

```php
sendWhatsAppBhashSMS($config, $recipient, $template_name, $variables = [], $media_url = null, $media_type = null)
```

#### 2. BhashSMS OTP

```php
sendWhatsAppOTP_BhashSMS($config, $recipient, $template_name, $otp_code)
```

#### 3. BhashSMS Text (Non-Template)

```php
sendWhatsAppText_BhashSMS($config, $recipient, $text)
```

---

## Database Tables

### tbl_whatsapp_templates

Stores approved WhatsApp templates

- `template_name`: Template identifier (e.g., gci_0007)
- `template_category`: utility, marketing, authentication
- `body_text`: Template content with {{1}}, {{2}} placeholders
- `variables`: JSON array of variable names
- `approval_status`: approved, pending, rejected
- `is_active`: 1 or 0

### tbl_whatsapp_logs

Tracks all sent messages

- `template_id`: Foreign key to tbl_whatsapp_templates
- `recipient_number`: Phone number
- `status`: sent, failed
- `message_id`: Unique identifier
- `variables`: JSON array of values sent
- `api_response`: Raw API response
- `error_message`: Error details if failed

---

## Testing

### Test Script

**File**: `test-whatsapp-gci-0007.php`

**URL**: `http://localhost/test-whatsapp-gci-0007.php`

**Test Numbers**:

- 7436044629
- 7990965567

**Test Template**: gci_0007 (Online Registration Welcome)

---

## Important Notes

### Phone Number Format

- **Always remove country code 91**
- Single: `7436044629`
- Multiple: `7436044629,7990965567` (comma-separated)

### Parameter Order

Variables must match the exact order defined in the template

### Parameter Case Sensitivity

- `Params` (capital P) - used for template variables
- `priority`, `stype`, `htype` (lowercase)

### htype Usage

- **DO NOT include** for normal template messages
- **Include `htype=image/video/document`** when sending media
- **Include `htype=normal`** for non-template text after customer replies

### Error Handling

API returns plain text responses. Success if:

- HTTP 200
- Response doesn't contain "error" (case-insensitive)

---

## Example Use Cases

### 1. Registration Confirmation

```php
sendWhatsAppTemplate($conn, '7436044629', 45, [
    'Priya Sharma',
    '12th Science (NEET)',
    'Gyanmanjari Career',
    'REG2026001',
    '0141-2345678'
]);
```

### 2. Fee Payment Receipt

```php
sendWhatsAppTemplate($conn, '7990965567', 12, [
    'Amit Verma',
    '50000',
    '2026-01-19',
    'RCPT001234',
    'January 2026'
]);
```

### 3. OTP for Login

```php
$config = getWhatsAppConfig();
sendWhatsAppOTP_BhashSMS($config, '7436044629', 'otp_template', '458392');
```

### 4. Follow-up Message (After Reply)

```php
$config = getWhatsAppConfig();
sendWhatsAppText_BhashSMS($config, '7436044629', 'Thank you for your interest. Our counsellor will contact you shortly.');
```

---

## Troubleshooting

### Message Not Sent

1. Check template approval status (must be `approved`)
2. Verify template is active (`is_active = 1`)
3. Ensure phone number is without 91
4. Verify parameter count matches template variables
5. Check `tbl_whatsapp_logs` for error messages

### Template Not Found

1. Check template name spelling (case-sensitive)
2. Verify template exists in `tbl_whatsapp_templates`
3. Check `is_active` status

### Variable Mismatch

- Count template variables: `SELECT variables FROM tbl_whatsapp_templates WHERE id = ?`
- Ensure array length matches variable count
- Variables are zero-indexed in code but 1-indexed in template ({{1}}, {{2}})

---

## Support & Documentation

**Provider**: BhashSMS
**Website**: http://bhashsms.com
**Support**: Contact BhashSMS support for:

- Template approval
- API rate limits
- Delivery reports
- Technical issues

**Internal Files**:

- Config: `common/config/app-config.php`
- Functions: `common/helpers/whatsapp_functions.php`
- Controller: `counselling-backend/controllers/settings/whatsapp_controller.php`
- UI: `portal/modules/settings/whatsapp-templates.php`
