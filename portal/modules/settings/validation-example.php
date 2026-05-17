<?php
/**
 * 3-Layer Validation Example
 * 
 * This file demonstrates how to implement all 3 validation layers:
 * 1. HTML5 Validation (First Layer - Basic)
 * 2. JavaScript Validation (Second Layer - Interaction)
 * 3. PHP Validation (Third Layer - Security)
 * 
 * Copy this pattern for any form in your application.
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/InputValidator.php';

$errors = [];
$success = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'confirm_password' => '',
    'age' => '',
    'aadhaar' => '',
    'pincode' => ''
];

// ==========================================
// LAYER 3: PHP VALIDATION (Final & Most Important)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize validator with POST data
    $validator = new InputValidator($_POST);

    // Apply validation rules
    $validator
        // Name: Required, letters only, 3-50 chars
        ->required('name', 'Name is required')
        ->alpha('name', 'Name must contain only letters')
        ->minLength('name', 3, 'Name must be at least 3 characters')
        ->maxLength('name', 50, 'Name must not exceed 50 characters')

        // Email: Required, valid email format
        ->required('email', 'Email is required')
        ->email('email', 'Please enter a valid email address')

        // Phone: Required, valid Indian phone
        ->required('phone', 'Phone number is required')
        ->phone('phone', 'Please enter a valid 10-digit phone number')

        // Password: Required, min 8 chars, strength check
        ->required('password', 'Password is required')
        ->minLength('password', 8, 'Password must be at least 8 characters')
        ->passwordStrength('password', 'Password must have uppercase, lowercase, and numbers')

        // Confirm Password: Must match password
        ->required('confirm_password', 'Please confirm your password')
        ->match('password', 'confirm_password', 'Passwords do not match')

        // Age: Optional but if provided, must be 18-100
        ->numeric('age', 'Age must be a number')
        ->min('age', 18, 'You must be at least 18 years old')
        ->max('age', 100, 'Please enter a valid age')

        // Aadhaar: Optional, valid 12-digit
        ->aadhaar('aadhaar', 'Please enter a valid 12-digit Aadhaar number')

        // Pincode: Optional, valid 6-digit
        ->pincode('pincode', 'Please enter a valid 6-digit pincode');

    // Custom validation: Check if email exists
    global $conn;
    $validator->custom('email', function ($email) use ($conn) {
        if (empty($email))
            return true; // Skip if empty (required rule handles it)
        $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() === 0; // Return true if email doesn't exist
    }, 'This email is already registered');

    // Check validation result
    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Get sanitized data
        $cleanData = $validator->getSanitizedData();

        // Process the form (save to database, etc.)
        // ... your logic here ...

        $success = 'Form submitted successfully! All validations passed.';
    }

    // Preserve form data for re-display
    $formData = $validator->getSanitizedData();
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt me-2"></i>
                                3-Layer Validation Example
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- Validation Info -->
                            <div class="alert alert-info mb-4">
                                <h5><i class="fas fa-info-circle me-2"></i>How It Works:</h5>
                                <ul class="mb-0">
                                    <li><strong>Layer 1 (HTML5):</strong> Browser-level validation using
                                        <code>required</code>, <code>type</code>, <code>pattern</code> attributes
                                    </li>
                                    <li><strong>Layer 2 (JavaScript):</strong> Real-time validation with error messages
                                        using <code>data-rules</code> attribute</li>
                                    <li><strong>Layer 3 (PHP):</strong> Server-side validation - the final security
                                        layer that cannot be bypassed</li>
                                </ul>
                            </div>

                            <!-- Success Message -->
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= e($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Error Summary -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:
                                    </h5>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $field => $fieldErrors): ?>
                                            <?php foreach ($fieldErrors as $error): ?>
                                                <li><?= e($error) ?></li>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- The Form with all 3 layers -->
                            <form method="POST" action="" data-validate novalidate>
                                <div class="row">
                                    <!-- Name Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name <span
                                                class="text-danger">*</span></label>
                                        <!--
                                            LAYER 1 (HTML5): required, minlength, maxlength, pattern
                                            LAYER 2 (JS): data-rules="required|alpha|min:3|max:50"
                                        -->
                                        <input type="text"
                                            class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                            id="name" name="name" value="<?= e($formData['name']) ?>" required
                                            minlength="3" maxlength="50" pattern="[a-zA-Z\s]+"
                                            data-rules="required|alpha|min:3|max:50" data-label="Full Name"
                                            placeholder="Enter your full name">
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Email Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address <span
                                                class="text-danger">*</span></label>
                                        <input type="email"
                                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                            id="email" name="email" value="<?= e($formData['email']) ?>" required
                                            data-rules="required|email" data-label="Email"
                                            placeholder="Enter your email">
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['email'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Phone Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number <span
                                                class="text-danger">*</span></label>
                                        <input type="tel"
                                            class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                            id="phone" name="phone" value="<?= e($formData['phone']) ?>" required
                                            pattern="[6-9][0-9]{9}" maxlength="10" data-rules="required|phone"
                                            data-label="Phone Number" placeholder="Enter 10-digit phone number">
                                        <?php if (isset($errors['phone'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['phone'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Age Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="age" class="form-label">Age</label>
                                        <input type="number"
                                            class="form-control <?= isset($errors['age']) ? 'is-invalid' : '' ?>"
                                            id="age" name="age" value="<?= e($formData['age']) ?>" min="18" max="100"
                                            data-rules="numeric|minValue:18|maxValue:100" data-label="Age"
                                            placeholder="Enter your age">
                                        <?php if (isset($errors['age'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['age'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Aadhaar Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="aadhaar" class="form-label">Aadhaar Number</label>
                                        <input type="text"
                                            class="form-control <?= isset($errors['aadhaar']) ? 'is-invalid' : '' ?>"
                                            id="aadhaar" name="aadhaar" value="<?= e($formData['aadhaar']) ?>"
                                            maxlength="12" pattern="[2-9][0-9]{11}" data-rules="aadhaar"
                                            data-label="Aadhaar" placeholder="Enter 12-digit Aadhaar">
                                        <?php if (isset($errors['aadhaar'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['aadhaar'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Pincode Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="pincode" class="form-label">Pincode</label>
                                        <input type="text"
                                            class="form-control <?= isset($errors['pincode']) ? 'is-invalid' : '' ?>"
                                            id="pincode" name="pincode" value="<?= e($formData['pincode']) ?>"
                                            maxlength="6" pattern="[1-9][0-9]{5}" data-rules="pincode"
                                            data-label="Pincode" placeholder="Enter 6-digit pincode">
                                        <?php if (isset($errors['pincode'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['pincode'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Password Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password <span
                                                class="text-danger">*</span></label>
                                        <input type="password"
                                            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                            id="password" name="password" required minlength="8"
                                            data-rules="required|min:8|password" data-label="Password"
                                            data-password-meter placeholder="Enter password">
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['password'][0]) ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Min 8 chars with uppercase, lowercase, and
                                            numbers</small>
                                    </div>

                                    <!-- Confirm Password Field -->
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password <span
                                                class="text-danger">*</span></label>
                                        <input type="password"
                                            class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                            id="confirm_password" name="confirm_password" required
                                            data-rules="required|match:password" data-label="Confirm Password"
                                            placeholder="Confirm your password">
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['confirm_password'][0]) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-1"></i> Reset
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check me-1"></i> Submit Form
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Code Reference -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-code me-2"></i>Quick Reference
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Available Validation Rules:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Rule</th>
                                            <th>HTML5</th>
                                            <th>JavaScript (data-rules)</th>
                                            <th>PHP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Required</td>
                                            <td><code>required</code></td>
                                            <td><code>required</code></td>
                                            <td><code>->required()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Email</td>
                                            <td><code>type="email"</code></td>
                                            <td><code>email</code></td>
                                            <td><code>->email()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Min Length</td>
                                            <td><code>minlength="5"</code></td>
                                            <td><code>min:5</code></td>
                                            <td><code>->minLength(5)</code></td>
                                        </tr>
                                        <tr>
                                            <td>Max Length</td>
                                            <td><code>maxlength="50"</code></td>
                                            <td><code>max:50</code></td>
                                            <td><code>->maxLength(50)</code></td>
                                        </tr>
                                        <tr>
                                            <td>Pattern</td>
                                            <td><code>pattern="[A-Z]+"</code></td>
                                            <td><code>pattern:[A-Z]+</code></td>
                                            <td><code>->pattern('/^[A-Z]+$/')</code></td>
                                        </tr>
                                        <tr>
                                            <td>Numeric</td>
                                            <td><code>type="number"</code></td>
                                            <td><code>numeric</code></td>
                                            <td><code>->numeric()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Phone</td>
                                            <td><code>type="tel"</code></td>
                                            <td><code>phone</code></td>
                                            <td><code>->phone()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Password</td>
                                            <td>-</td>
                                            <td><code>password</code></td>
                                            <td><code>->passwordStrength()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Match</td>
                                            <td>-</td>
                                            <td><code>match:field</code></td>
                                            <td><code>->match()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Aadhaar</td>
                                            <td>-</td>
                                            <td><code>aadhaar</code></td>
                                            <td><code>->aadhaar()</code></td>
                                        </tr>
                                        <tr>
                                            <td>Pincode</td>
                                            <td>-</td>
                                            <td><code>pincode</code></td>
                                            <td><code>->pincode()</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include JavaScript Validation Library -->
<script src="<?= BASE_URL ?>/portal/assets/js/form-validator.js"></script>

<?php include '../../include/footer.php'; ?>