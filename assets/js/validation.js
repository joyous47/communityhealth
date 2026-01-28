function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePasswordStrength(password) {
    const feedback = [];
    let strength = 0;

    if (password.length < 8) {
        feedback.push('At least 8 characters required');
    } else {
        strength++;
    }

    if (/[A-Z]/.test(password)) {
        strength++;
    } else {
        feedback.push('At least one uppercase letter required');
    }

    if (/[a-z]/.test(password)) {
        strength++;
    } else {
        feedback.push('At least one lowercase letter required');
    }

    if (/\d/.test(password)) {
        strength++;
    } else {
        feedback.push('At least one number required');
    }

    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        strength++;
    } else {
        feedback.push('At least one special character required');
    }

    let strengthLabel = 'Weak';
    if (strength === 4) strengthLabel = 'Good';
    if (strength === 5) strengthLabel = 'Strong';

    return {
        isValid: strength >= 4,
        strength: strengthLabel,
        feedback: feedback
    };
}

function isValidUsername(username) {
    const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
    return usernameRegex.test(username);
}

function isEmpty(value) {
    return value.trim() === '';
}

function hasMinLength(value, minLength) {
    return value.trim().length >= minLength;
}

function valuesMatch(value1, value2) {
    return value1 === value2;
}

function showFieldError(fieldElement, errorMessage) {
    const existingError = fieldElement.parentElement.querySelector('.error-text');
    if (existingError) {
        existingError.remove();
    }

    fieldElement.classList.add('input-error');

    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-text';
    errorDiv.textContent = errorMessage;
    errorDiv.style.cssText = `
        color: #d32f2f;
        font-size: 0.85rem;
        margin-top: 5px;
        display: block;
        font-weight: 500;
    `;
    fieldElement.parentElement.appendChild(errorDiv);
}

function clearFieldError(fieldElement) {
    fieldElement.classList.remove('input-error');
    const errorDiv = fieldElement.parentElement.querySelector('.error-text');
    if (errorDiv) {
        errorDiv.remove();
    }
}

function showFieldSuccess(fieldElement) {
    fieldElement.classList.remove('input-error');
    fieldElement.classList.add('input-success');
}

function validateRegistrationForm() {
    const form = document.getElementById('registrationForm');
    if (!form) return true;

    let isValid = true;

    const usernameField = document.getElementById('username');
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirmPassword');
    const roleField = document.getElementById('role');
    const agreeField = document.getElementById('agree');

    if (usernameField) {
        if (isEmpty(usernameField.value)) {
            showFieldError(usernameField, 'Username is required');
            isValid = false;
        } else if (!isValidUsername(usernameField.value)) {
            showFieldError(usernameField, 'Username must be 3-50 characters (alphanumeric and underscore only)');
            isValid = false;
        } else {
            clearFieldError(usernameField);
        }
    }

    if (emailField) {
        if (isEmpty(emailField.value)) {
            showFieldError(emailField, 'Email is required');
            isValid = false;
        } else if (!isValidEmail(emailField.value)) {
            showFieldError(emailField, 'Please enter a valid email address');
            isValid = false;
        } else {
            clearFieldError(emailField);
        }
    }

    if (passwordField) {
        if (isEmpty(passwordField.value)) {
            showFieldError(passwordField, 'Password is required');
            isValid = false;
        } else {
            const strength = validatePasswordStrength(passwordField.value);
            if (!strength.isValid) {
                showFieldError(passwordField, strength.feedback.join(', '));
                isValid = false;
            } else {
                clearFieldError(passwordField);
            }
        }
    }

    if (confirmPasswordField && passwordField) {
        if (isEmpty(confirmPasswordField.value)) {
            showFieldError(confirmPasswordField, 'Password confirmation is required');
            isValid = false;
        } else if (!valuesMatch(passwordField.value, confirmPasswordField.value)) {
            showFieldError(confirmPasswordField, 'Passwords do not match');
            isValid = false;
        } else {
            clearFieldError(confirmPasswordField);
        }
    }

    if (roleField) {
        if (isEmpty(roleField.value)) {
            showFieldError(roleField, 'Please select a role');
            isValid = false;
        } else {
            clearFieldError(roleField);
        }
    }

    if (agreeField) {
        if (!agreeField.checked) {
            showFieldError(agreeField, 'You must agree to the terms and conditions');
            isValid = false;
        } else {
            clearFieldError(agreeField);
        }
    }

    return isValid;
}

function setupRegistrationValidation() {
    const usernameField = document.getElementById('username');
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirmPassword');

    if (usernameField) {
        usernameField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !isValidUsername(this.value)) {
                showFieldError(this, 'Username must be 3-50 characters (alphanumeric and underscore only)');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }

    if (emailField) {
        emailField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !isValidEmail(this.value)) {
                showFieldError(this, 'Please enter a valid email address');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }

    if (passwordField) {
        passwordField.addEventListener('change', function() {
            if (!isEmpty(this.value)) {
                const strength = validatePasswordStrength(this.value);
                if (strength.isValid) {
                    clearFieldError(this);
                    showFieldSuccess(this);
                } else {
                    showFieldError(this, strength.feedback[0]);
                }
            }
        });
    }

    if (confirmPasswordField && passwordField) {
        confirmPasswordField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !valuesMatch(passwordField.value, this.value)) {
                showFieldError(this, 'Passwords do not match');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }
}

function validateLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return true;

    let isValid = true;

    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');

    if (emailField) {
        if (isEmpty(emailField.value)) {
            showFieldError(emailField, 'Email is required');
            isValid = false;
        } else if (!isValidEmail(emailField.value)) {
            showFieldError(emailField, 'Please enter a valid email address');
            isValid = false;
        } else {
            clearFieldError(emailField);
        }
    }

    if (passwordField) {
        if (isEmpty(passwordField.value)) {
            showFieldError(passwordField, 'Password is required');
            isValid = false;
        } else if (!hasMinLength(passwordField.value, 6)) {
            showFieldError(passwordField, 'Password must be at least 6 characters');
            isValid = false;
        } else {
            clearFieldError(passwordField);
        }
    }

    return isValid;
}

function setupLoginValidation() {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');

    if (emailField) {
        emailField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !isValidEmail(this.value)) {
                showFieldError(this, 'Please enter a valid email address');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }

    if (passwordField) {
        passwordField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 6)) {
                showFieldError(this, 'Password must be at least 6 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }
}

function validateReportForm() {
    const form = document.getElementById('reportForm');
    if (!form) return true;

    let isValid = true;

    const diseaseField = document.getElementById('diseaseType');
    const symptomsField = document.getElementById('symptoms');
    const locationField = document.getElementById('location');

    if (diseaseField) {
        if (isEmpty(diseaseField.value)) {
            showFieldError(diseaseField, 'Disease type is required');
            isValid = false;
        } else if (!hasMinLength(diseaseField.value, 2)) {
            showFieldError(diseaseField, 'Disease name must be at least 2 characters');
            isValid = false;
        } else {
            clearFieldError(diseaseField);
        }
    }

    if (symptomsField) {
        if (isEmpty(symptomsField.value)) {
            showFieldError(symptomsField, 'Symptoms description is required');
            isValid = false;
        } else if (!hasMinLength(symptomsField.value, 10)) {
            showFieldError(symptomsField, 'Symptoms must be at least 10 characters');
            isValid = false;
        } else if (symptomsField.value.length > 5000) {
            showFieldError(symptomsField, 'Symptoms must not exceed 5000 characters');
            isValid = false;
        } else {
            clearFieldError(symptomsField);
        }
    }

    if (locationField) {
        if (isEmpty(locationField.value)) {
            showFieldError(locationField, 'Location is required');
            isValid = false;
        } else if (!hasMinLength(locationField.value, 2)) {
            showFieldError(locationField, 'Location must be at least 2 characters');
            isValid = false;
        } else {
            clearFieldError(locationField);
        }
    }

    return isValid;
}

function setupReportValidation() {
    const diseaseField = document.getElementById('diseaseType');
    const symptomsField = document.getElementById('symptoms');
    const locationField = document.getElementById('location');

    if (diseaseField) {
        diseaseField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 2)) {
                showFieldError(this, 'Disease name must be at least 2 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }

    if (symptomsField) {
        symptomsField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 10)) {
                showFieldError(this, 'Symptoms must be at least 10 characters');
            } else if (this.value.length > 5000) {
                showFieldError(this, 'Symptoms must not exceed 5000 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });

        symptomsField.addEventListener('input', function() {
            const counter = this.parentElement.querySelector('.char-count');
            if (counter) {
                counter.textContent = this.value.length + ' / 5000 characters';
            }
        });
    }

    if (locationField) {
        locationField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 2)) {
                showFieldError(this, 'Location must be at least 2 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });
    }
}

function validateAnalysisForm() {
    const form = document.getElementById('analysisForm');
    if (!form) return true;

    let isValid = true;

    const detailsField = document.getElementById('analysisDetails');
    const severityField = document.getElementById('severity');

    if (detailsField) {
        if (isEmpty(detailsField.value)) {
            showFieldError(detailsField, 'Analysis details are required');
            isValid = false;
        } else if (!hasMinLength(detailsField.value, 10)) {
            showFieldError(detailsField, 'Analysis must be at least 10 characters');
            isValid = false;
        } else if (detailsField.value.length > 10000) {
            showFieldError(detailsField, 'Analysis must not exceed 10000 characters');
            isValid = false;
        } else {
            clearFieldError(detailsField);
        }
    }

    if (severityField) {
        if (isEmpty(severityField.value)) {
            showFieldError(severityField, 'Severity level is required');
            isValid = false;
        } else {
            clearFieldError(severityField);
        }
    }

    return isValid;
}

function setupAnalysisValidation() {
    const detailsField = document.getElementById('analysisDetails');

    if (detailsField) {
        detailsField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 10)) {
                showFieldError(this, 'Analysis must be at least 10 characters');
            } else if (this.value.length > 10000) {
                showFieldError(this, 'Analysis must not exceed 10000 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });

        detailsField.addEventListener('input', function() {
            const counter = this.parentElement.querySelector('.char-count');
            if (counter) {
                counter.textContent = this.value.length + ' / 10000 characters';
            }
        });
    }
}

function validateRecommendationForm() {
    const form = document.getElementById('recommendationForm');
    if (!form) return true;

    let isValid = true;

    const textField = document.getElementById('recommendationText');

    if (textField) {
        if (isEmpty(textField.value)) {
            showFieldError(textField, 'Recommendation text is required');
            isValid = false;
        } else if (!hasMinLength(textField.value, 10)) {
            showFieldError(textField, 'Recommendation must be at least 10 characters');
            isValid = false;
        } else if (textField.value.length > 5000) {
            showFieldError(textField, 'Recommendation must not exceed 5000 characters');
            isValid = false;
        } else {
            clearFieldError(textField);
        }
    }

    return isValid;
}

function setupRecommendationValidation() {
    const textField = document.getElementById('recommendationText');

    if (textField) {
        textField.addEventListener('blur', function() {
            if (!isEmpty(this.value) && !hasMinLength(this.value, 10)) {
                showFieldError(this, 'Recommendation must be at least 10 characters');
            } else if (this.value.length > 5000) {
                showFieldError(this, 'Recommendation must not exceed 5000 characters');
            } else if (!isEmpty(this.value)) {
                clearFieldError(this);
            }
        });

        textField.addEventListener('input', function() {
            const counter = this.parentElement.querySelector('.char-count');
            if (counter) {
                counter.textContent = this.value.length + ' / 5000 characters';
            }
        });
    }
}

function validateDateRange() {
    const startDateField = document.getElementById('startDate') || document.getElementById('start_date');
    const endDateField = document.getElementById('endDate') || document.getElementById('end_date');

    if (startDateField && endDateField) {
        const startDate = new Date(startDateField.value);
        const endDate = new Date(endDateField.value);

        if (startDate > endDate) {
            showFieldError(endDateField, 'End date must be after start date');
            return false;
        } else {
            clearFieldError(endDateField);
            return true;
        }
    }

    return true;
}

function setupDateRangeValidation() {
    const startDateField = document.getElementById('startDate') || document.getElementById('start_date');
    const endDateField = document.getElementById('endDate') || document.getElementById('end_date');

    if (endDateField) {
        endDateField.addEventListener('change', validateDateRange);
    }
}

function setupFormValidation() {
    const registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
        setupRegistrationValidation();
        registrationForm.addEventListener('submit', function(e) {
            if (!validateRegistrationForm()) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        setupLoginValidation();
        loginForm.addEventListener('submit', function(e) {
            if (!validateLoginForm()) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        setupReportValidation();
        reportForm.addEventListener('submit', function(e) {
            if (!validateReportForm()) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    const analysisForm = document.getElementById('analysisForm');
    if (analysisForm) {
        setupAnalysisValidation();
        analysisForm.addEventListener('submit', function(e) {
            if (!validateAnalysisForm()) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    const recommendationForm = document.getElementById('recommendationForm');
    if (recommendationForm) {
        setupRecommendationValidation();
        recommendationForm.addEventListener('submit', function(e) {
            if (!validateRecommendationForm()) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    setupDateRangeValidation();
}

function scrollToFirstError() {
    const firstError = document.querySelector('.input-error');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setupFormValidation();
});