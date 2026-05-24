/**
 * Form Validator - JavaScript Validation Library
 * Second Layer - Real-time client-side validation
 * 
 * Features:
 * - Real-time validation on blur/input
 * - Visual feedback (success/error states)
 * - Error message display
 * - Form submission prevention
 * - Password strength meter
 * 
 * Usage:
 *   <form id="myForm" data-validate>
 *     <input type="text" name="name" data-rules="required|min:3|max:50" data-label="Name">
 *     <input type="email" name="email" data-rules="required|email" data-label="Email">
 *   </form>
 * 
 *   // OR programmatically:
 *   const validator = new FormValidator('#myForm');
 *   validator.validate(); // Returns true/false
 */

class FormValidator {
    constructor(formSelector, options = {}) {
        this.form = typeof formSelector === 'string' 
            ? document.querySelector(formSelector) 
            : formSelector;
        
        if (!this.form) {
            console.error('FormValidator: Form not found');
            return;
        }

        this.options = {
            validateOnBlur: true,
            validateOnInput: false,
            showSuccessState: true,
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorMessageClass: 'invalid-feedback',
            ...options
        };

        this.errors = {};
        this.init();
    }

    init() {
        // Add novalidate to prevent browser validation (we handle it)
        this.form.setAttribute('novalidate', 'true');

        // Get all inputs with validation rules
        this.inputs = this.form.querySelectorAll('[data-rules]');

        // Bind events
        this.inputs.forEach(input => {
            if (this.options.validateOnBlur) {
                input.addEventListener('blur', () => this.validateField(input));
            }
            if (this.options.validateOnInput) {
                input.addEventListener('input', () => this.validateField(input));
            }
        });

        // Form submit handler
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                e.stopPropagation();
                this.focusFirstError();
            }
        });
    }

    /**
     * Validate entire form
     */
    validate() {
        this.errors = {};
        let isValid = true;

        this.inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Validate single field
     */
    validateField(input) {
        const rules = input.dataset.rules.split('|');
        const label = input.dataset.label || input.name;
        const value = input.value.trim();
        
        // Clear previous errors
        this.clearFieldError(input);
        delete this.errors[input.name];

        for (const rule of rules) {
            const [ruleName, ruleValue] = rule.split(':');
            const error = this.checkRule(ruleName, ruleValue, value, label, input);
            
            if (error) {
                this.setFieldError(input, error);
                this.errors[input.name] = error;
                return false;
            }
        }

        if (this.options.showSuccessState && value) {
            this.setFieldSuccess(input);
        }
        
        return true;
    }

    /**
     * Check individual rule
     */
    checkRule(ruleName, ruleValue, value, label, input) {
        switch (ruleName) {
            case 'required':
                if (!value) return `${label} is required.`;
                break;

            case 'email':
                if (value && !this.isValidEmail(value)) {
                    return `Please enter a valid email address.`;
                }
                break;

            case 'min':
                if (value && value.length < parseInt(ruleValue)) {
                    return `${label} must be at least ${ruleValue} characters.`;
                }
                break;

            case 'max':
                if (value && value.length > parseInt(ruleValue)) {
                    return `${label} must not exceed ${ruleValue} characters.`;
                }
                break;

            case 'minValue':
                if (value && parseFloat(value) < parseFloat(ruleValue)) {
                    return `${label} must be at least ${ruleValue}.`;
                }
                break;

            case 'maxValue':
                if (value && parseFloat(value) > parseFloat(ruleValue)) {
                    return `${label} must not exceed ${ruleValue}.`;
                }
                break;

            case 'numeric':
                if (value && !/^\d+$/.test(value)) {
                    return `${label} must be a number.`;
                }
                break;

            case 'alpha':
                if (value && !/^[a-zA-Z\s]+$/.test(value)) {
                    return `${label} must contain only letters.`;
                }
                break;

            case 'alphanumeric':
                if (value && !/^[a-zA-Z0-9\s]+$/.test(value)) {
                    return `${label} must contain only letters and numbers.`;
                }
                break;

            case 'phone':
                if (value && !this.isValidPhone(value)) {
                    return `Please enter a valid phone number.`;
                }
                break;

            case 'aadhaar':
                if (value && !this.isValidAadhaar(value)) {
                    return `Please enter a valid 12-digit Aadhaar number.`;
                }
                break;

            case 'pan':
                if (value && !/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(value.toUpperCase())) {
                    return `Please enter a valid PAN number.`;
                }
                break;

            case 'pincode':
                if (value && !/^[1-9][0-9]{5}$/.test(value)) {
                    return `Please enter a valid 6-digit pincode.`;
                }
                break;

            case 'date':
                if (value && !this.isValidDate(value)) {
                    return `Please enter a valid date.`;
                }
                break;

            case 'url':
                if (value && !this.isValidUrl(value)) {
                    return `Please enter a valid URL.`;
                }
                break;

            case 'match':
                const matchInput = this.form.querySelector(`[name="${ruleValue}"]`);
                if (matchInput && value !== matchInput.value) {
                    return `${label} must match.`;
                }
                break;

            case 'password':
                if (value && !this.isStrongPassword(value)) {
                    return `Password must be at least 8 characters with uppercase, lowercase, and numbers.`;
                }
                break;

            case 'pattern':
                if (value && !new RegExp(ruleValue).test(value)) {
                    return `${label} format is invalid.`;
                }
                break;

            case 'file':
                if (input.files && input.files.length > 0) {
                    const file = input.files[0];
                    const allowedTypes = ruleValue ? ruleValue.split(',') : [];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    
                    if (allowedTypes.length && !allowedTypes.includes(fileExt)) {
                        return `Allowed file types: ${allowedTypes.join(', ')}`;
                    }
                }
                break;

            case 'filesize':
                if (input.files && input.files.length > 0) {
                    const maxSize = parseInt(ruleValue) * 1024 * 1024; // MB to bytes
                    if (input.files[0].size > maxSize) {
                        return `File size must not exceed ${ruleValue}MB.`;
                    }
                }
                break;
        }

        return null;
    }

    // Validation helpers
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    isValidPhone(phone) {
        const cleaned = phone.replace(/[\s\-]/g, '');
        return /^(\+91|0)?[6-9]\d{9}$/.test(cleaned);
    }

    isValidAadhaar(aadhaar) {
        const cleaned = aadhaar.replace(/[\s\-]/g, '');
        return /^[2-9]\d{11}$/.test(cleaned);
    }

    isValidDate(date) {
        const parsed = Date.parse(date);
        return !isNaN(parsed);
    }

    isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    isStrongPassword(password) {
        return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(password);
    }

    // UI Methods
    setFieldError(input, message) {
        input.classList.remove(this.options.successClass);
        input.classList.add(this.options.errorClass);

        // Remove existing error message
        this.removeErrorMessage(input);

        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = this.options.errorMessageClass;
        errorDiv.textContent = message;
        
        // Insert after input (or after input-group if exists)
        const parent = input.closest('.input-group') || input;
        parent.insertAdjacentElement('afterend', errorDiv);
    }

    setFieldSuccess(input) {
        input.classList.remove(this.options.errorClass);
        input.classList.add(this.options.successClass);
        this.removeErrorMessage(input);
    }

    clearFieldError(input) {
        input.classList.remove(this.options.errorClass, this.options.successClass);
        this.removeErrorMessage(input);
    }

    removeErrorMessage(input) {
        const parent = input.closest('.input-group') || input;
        const next = parent.nextElementSibling;
        if (next && next.classList.contains(this.options.errorMessageClass)) {
            next.remove();
        }
    }

    focusFirstError() {
        const firstErrorField = this.form.querySelector(`.${this.options.errorClass}`);
        if (firstErrorField) {
            firstErrorField.focus();
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    getErrors() {
        return this.errors;
    }

    hasErrors() {
        return Object.keys(this.errors).length > 0;
    }
}

/**
 * Password Strength Meter
 */
class PasswordStrengthMeter {
    constructor(inputSelector, meterSelector = null) {
        this.input = document.querySelector(inputSelector);
        this.meterContainer = meterSelector ? document.querySelector(meterSelector) : null;
        
        if (!this.input) return;
        
        if (!this.meterContainer) {
            this.createMeter();
        }
        
        this.init();
    }

    createMeter() {
        this.meterContainer = document.createElement('div');
        this.meterContainer.className = 'password-strength-meter mt-2';
        this.meterContainer.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill" style="width: 0%"></div>
            </div>
            <small class="strength-text text-muted">Password strength</small>
        `;
        this.input.insertAdjacentElement('afterend', this.meterContainer);

        // Add styles if not exists
        if (!document.getElementById('password-meter-styles')) {
            const style = document.createElement('style');
            style.id = 'password-meter-styles';
            style.textContent = `
                .password-strength-meter { margin-top: 5px; }
                .strength-bar { height: 4px; background: #e0e0e0; border-radius: 2px; overflow: hidden; }
                .strength-fill { height: 100%; transition: width 0.3s, background 0.3s; }
                .strength-weak { background: #dc3545; }
                .strength-fair { background: #ffc107; }
                .strength-good { background: #17a2b8; }
                .strength-strong { background: #28a745; }
            `;
            document.head.appendChild(style);
        }
    }

    init() {
        this.input.addEventListener('input', () => this.checkStrength());
    }

    checkStrength() {
        const password = this.input.value;
        const score = this.calculateScore(password);
        this.updateMeter(score);
    }

    calculateScore(password) {
        let score = 0;
        
        if (password.length >= 8) score += 25;
        if (password.length >= 12) score += 15;
        if (/[a-z]/.test(password)) score += 15;
        if (/[A-Z]/.test(password)) score += 15;
        if (/\d/.test(password)) score += 15;
        if (/[^a-zA-Z0-9]/.test(password)) score += 15;
        
        return Math.min(100, score);
    }

    updateMeter(score) {
        const fill = this.meterContainer.querySelector('.strength-fill');
        const text = this.meterContainer.querySelector('.strength-text');
        
        fill.style.width = score + '%';
        fill.className = 'strength-fill';
        
        if (score < 25) {
            fill.classList.add('strength-weak');
            text.textContent = 'Very weak';
            text.className = 'strength-text text-danger';
        } else if (score < 50) {
            fill.classList.add('strength-fair');
            text.textContent = 'Weak';
            text.className = 'strength-text text-warning';
        } else if (score < 75) {
            fill.classList.add('strength-good');
            text.textContent = 'Good';
            text.className = 'strength-text text-info';
        } else {
            fill.classList.add('strength-strong');
            text.textContent = 'Strong';
            text.className = 'strength-text text-success';
        }
    }
}

/**
 * Auto-initialize forms with data-validate attribute
 */
document.addEventListener('DOMContentLoaded', function() {
    // Auto-init validation on forms with data-validate
    document.querySelectorAll('form[data-validate]').forEach(form => {
        new FormValidator(form);
    });
    
    // Auto-init password strength meters
    document.querySelectorAll('[data-password-meter]').forEach(input => {
        new PasswordStrengthMeter(input);
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FormValidator, PasswordStrengthMeter };
}
