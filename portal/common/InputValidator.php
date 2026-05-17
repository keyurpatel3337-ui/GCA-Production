<?php
/**
 * Input Validator & Sanitizer Class
 * 
 * Final Layer (PHP) - The Most Important Security Layer
 * 
 * This class provides:
 * - Validation: Check if data is in correct format
 * - Sanitization: Remove dangerous characters from data
 * - Error Handling: Collect and return validation errors
 * 
 * Usage:
 *   $validator = new InputValidator($_POST);
 *   $validator->required('name', 'Name is required')
 *             ->email('email', 'Invalid email format')
 *             ->minLength('password', 8, 'Password must be at least 8 characters')
 *             ->match('password', 'confirm_password', 'Passwords do not match');
 *   
 *   if ($validator->fails()) {
 *       $errors = $validator->getErrors();
 *   } else {
 *       $cleanData = $validator->getSanitizedData();
 *   }
 */

class InputValidator
{
    private $data = [];
    private $errors = [];
    private $sanitizedData = [];

    /**
     * Initialize with input data
     * 
     * @param array $data Input data to validate (usually $_POST or $_GET)
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->sanitizeAll();
    }

    /**
     * Sanitize all input data
     */
    private function sanitizeAll(): void
    {
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                $this->sanitizedData[$key] = array_map([$this, 'sanitizeValue'], $value);
            } else {
                $this->sanitizedData[$key] = $this->sanitizeValue($value);
            }
        }
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        // Remove null bytes
        $value = str_replace(chr(0), '', $value);
        // Trim whitespace
        $value = trim($value);
        // Remove HTML tags (basic sanitization)
        $value = strip_tags($value);
        return $value;
    }

    // ==========================================
    // VALIDATION RULES
    // ==========================================

    /**
     * Required field validation
     */
    public function required(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (empty($value) && $value !== '0') {
            $this->addError($field, $message ?? "The {$field} field is required.");
        }
        return $this;
    }

    /**
     * Email validation
     */
    public function email(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message ?? "Please enter a valid email address.");
        }
        return $this;
    }

    /**
     * Minimum length validation
     */
    public function minLength(string $field, int $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && mb_strlen($value) < $min) {
            $this->addError($field, $message ?? "The {$field} must be at least {$min} characters.");
        }
        return $this;
    }

    /**
     * Maximum length validation
     */
    public function maxLength(string $field, int $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && mb_strlen($value) > $max) {
            $this->addError($field, $message ?? "The {$field} must not exceed {$max} characters.");
        }
        return $this;
    }

    /**
     * Exact length validation
     */
    public function length(string $field, int $length, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && mb_strlen($value) !== $length) {
            $this->addError($field, $message ?? "The {$field} must be exactly {$length} characters.");
        }
        return $this;
    }

    /**
     * Numeric validation
     */
    public function numeric(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, $message ?? "The {$field} must be a number.");
        }
        return $this;
    }

    /**
     * Integer validation
     */
    public function integer(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $message ?? "The {$field} must be an integer.");
        }
        return $this;
    }

    /**
     * Minimum value validation
     */
    public function min(string $field, $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && is_numeric($value) && $value < $min) {
            $this->addError($field, $message ?? "The {$field} must be at least {$min}.");
        }
        return $this;
    }

    /**
     * Maximum value validation
     */
    public function max(string $field, $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && is_numeric($value) && $value > $max) {
            $this->addError($field, $message ?? "The {$field} must not exceed {$max}.");
        }
        return $this;
    }

    /**
     * Regex pattern validation
     */
    public function pattern(string $field, string $pattern, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && !preg_match($pattern, $value)) {
            $this->addError($field, $message ?? "The {$field} format is invalid.");
        }
        return $this;
    }

    /**
     * Alpha only validation (letters only)
     */
    public function alpha(string $field, string $message = null): self
    {
        return $this->pattern($field, '/^[a-zA-Z\s]+$/u', $message ?? "The {$field} must contain only letters.");
    }

    /**
     * Alphanumeric validation
     */
    public function alphanumeric(string $field, string $message = null): self
    {
        return $this->pattern($field, '/^[a-zA-Z0-9\s]+$/u', $message ?? "The {$field} must contain only letters and numbers.");
    }

    /**
     * Phone number validation (Indian format)
     */
    public function phone(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            // Remove spaces and dashes
            $phone = preg_replace('/[\s\-]/', '', $value);
            // Indian phone: 10 digits, may start with +91 or 0
            if (!preg_match('/^(\+91|0)?[6-9]\d{9}$/', $phone)) {
                $this->addError($field, $message ?? "Please enter a valid phone number.");
            }
        }
        return $this;
    }

    /**
     * Aadhaar number validation
     */
    public function aadhaar(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            $aadhaar = preg_replace('/[\s\-]/', '', $value);
            if (!preg_match('/^[2-9]\d{11}$/', $aadhaar)) {
                $this->addError($field, $message ?? "Please enter a valid 12-digit Aadhaar number.");
            }
        }
        return $this;
    }

    /**
     * PAN number validation
     */
    public function pan(string $field, string $message = null): self
    {
        return $this->pattern($field, '/^[A-Z]{5}[0-9]{4}[A-Z]$/', $message ?? "Please enter a valid PAN number (e.g., ABCDE1234F).");
    }

    /**
     * Pincode validation (Indian)
     */
    public function pincode(string $field, string $message = null): self
    {
        return $this->pattern($field, '/^[1-9][0-9]{5}$/', $message ?? "Please enter a valid 6-digit pincode.");
    }

    /**
     * Date validation
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->addError($field, $message ?? "Please enter a valid date.");
            }
        }
        return $this;
    }

    /**
     * Date before validation
     */
    public function dateBefore(string $field, string $beforeDate, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            $date = strtotime($value);
            $before = strtotime($beforeDate);
            if ($date >= $before) {
                $this->addError($field, $message ?? "The date must be before {$beforeDate}.");
            }
        }
        return $this;
    }

    /**
     * Date after validation
     */
    public function dateAfter(string $field, string $afterDate, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            $date = strtotime($value);
            $after = strtotime($afterDate);
            if ($date <= $after) {
                $this->addError($field, $message ?? "The date must be after {$afterDate}.");
            }
        }
        return $this;
    }

    /**
     * URL validation
     */
    public function url(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $message ?? "Please enter a valid URL.");
        }
        return $this;
    }

    /**
     * Match two fields (e.g., password confirmation)
     */
    public function match(string $field1, string $field2, string $message = null): self
    {
        $value1 = $this->getValue($field1);
        $value2 = $this->getValue($field2);
        if ($value1 !== $value2) {
            $this->addError($field2, $message ?? "The {$field2} must match {$field1}.");
        }
        return $this;
    }

    /**
     * In array validation
     */
    public function in(string $field, array $allowed, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value) && !in_array($value, $allowed)) {
            $this->addError($field, $message ?? "The selected {$field} is invalid.");
        }
        return $this;
    }

    /**
     * Password strength validation
     */
    public function passwordStrength(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if (!empty($value)) {
            // At least 8 chars, 1 uppercase, 1 lowercase, 1 number
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $value)) {
                $this->addError($field, $message ?? "Password must be at least 8 characters with uppercase, lowercase, and numbers.");
            }
        }
        return $this;
    }

    /**
     * File upload validation
     */
    public function file(string $field, array $allowedTypes = [], int $maxSize = 5242880, string $message = null): self
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return $this; // File is optional unless required() is also called
        }

        $file = $_FILES[$field];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, $message ?? "File upload failed.");
            return $this;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1048576, 2);
            $this->addError($field, "File size must not exceed {$maxMB}MB.");
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $this->addError($field, "Invalid file type. Allowed: " . implode(', ', $allowedTypes));
            }
        }

        return $this;
    }

    /**
     * Custom validation with callback
     */
    public function custom(string $field, callable $callback, string $message): self
    {
        $value = $this->getValue($field);
        if (!$callback($value, $this->sanitizedData)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    // ==========================================
    // SANITIZATION METHODS
    // ==========================================

    /**
     * Get sanitized string (HTML entities encoded)
     */
    public static function sanitizeString($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return htmlspecialchars(trim($value) ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get sanitized email
     */
    public static function sanitizeEmail($value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Get sanitized integer
     */
    public static function sanitizeInt($value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Get sanitized float
     */
    public static function sanitizeFloat($value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Get sanitized URL
     */
    public static function sanitizeUrl($value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize for SQL LIKE queries (escape wildcards)
     */
    public static function sanitizeForLike($value): string
    {
        return addcslashes(trim($value), '%_');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get value from sanitized data
     */
    private function getValue(string $field)
    {
        return $this->sanitizedData[$field] ?? '';
    }

    /**
     * Add an error
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get first error message (useful for simple forms)
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Get all sanitized data
     */
    public function getSanitizedData(): array
    {
        return $this->sanitizedData;
    }

    /**
     * Get specific sanitized field
     */
    public function get(string $field, $default = null)
    {
        return $this->sanitizedData[$field] ?? $default;
    }

    /**
     * Get only specified fields
     */
    public function only(array $fields): array
    {
        return array_intersect_key($this->sanitizedData, array_flip($fields));
    }

    /**
     * Get all except specified fields
     */
    public function except(array $fields): array
    {
        return array_diff_key($this->sanitizedData, array_flip($fields));
    }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Quick validation helper
 */
function validate(array $data): InputValidator
{
    return new InputValidator($data);
}

/**
 * Sanitize string for HTML output
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize for JavaScript output
 */
function js_escape($value): string
{
    return json_encode($value, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
}
