/**
 * DT Webform Public JavaScript
 * Handles form submission, validation, and user interactions
 */
(function($) {
    'use strict';

    // Global form handler object
    let dtWebform = {
        forms: new Map(),
        initialized: false
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        dtWebform.init();
    });

    /**
     * Initialize the webform handler
     */
    dtWebform.init = function() {
        if (this.initialized) return;

        // Find all webforms on the page
        $('.dt-webform').each(function() {
            const $form = $(this);
            const formId = $form.data('form-id');
            
            if (formId) {
                dtWebform.initForm($form, formId);
            }
        });

        this.initialized = true;
        console.log('DT Webform: Initialized', this.forms.size, 'forms');
    };

    /**
     * Initialize individual form
     */
    dtWebform.initForm = function($formContainer, formId) {
        const $form = $formContainer.find('.dt-webform-form');
        
        if (!$form.length) {
            console.error('DT Webform: Form element not found for form ID', formId);
            return;
        }

        // Store form reference
        this.forms.set(formId, {
            container: $formContainer,
            form: $form,
            submitting: false
        });

        // Bind events
        this.bindFormEvents($form, formId);
        this.bindFieldEvents($form, formId);

        console.log('DT Webform: Initialized form', formId);
    };

    /**
     * Bind form-level events
     */
    dtWebform.bindFormEvents = function($form, formId) {
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            dtWebform.handleFormSubmit(formId);
        });
    };

    /**
     * Bind field-level events
     */
    dtWebform.bindFieldEvents = function($form, formId) {
        // Real-time validation on blur
        $form.find('input, textarea, select').on('blur', function() {
            const $field = $(this);
            const fieldKey = $field.closest('.dt-webform-field').data('field-key');
            dtWebform.validateField($field, fieldKey, formId);
        });

        // Clear validation on focus
        $form.find('input, textarea, select').on('focus', function() {
            const $field = $(this);
            dtWebform.clearFieldError($field);
        });

        // Handle checkbox/radio change events
        $form.find('input[type="checkbox"], input[type="radio"]').on('change', function() {
            const $field = $(this);
            const fieldKey = $field.closest('.dt-webform-field').data('field-key');
            dtWebform.validateField($field, fieldKey, formId);
        });
    };

    /**
     * Handle form submission
     */
    dtWebform.handleFormSubmit = function(formId) {
        const formData = this.forms.get(formId);
        
        if (!formData || formData.submitting) {
            return;
        }

        const $form = formData.form;
        const $container = formData.container;

        // Clear previous messages
        this.clearMessages($container);

        // Validate all fields
        if (!this.validateForm($form, formId)) {
            this.showError($container, 'Please correct the errors above and try again.');
            return;
        }

        // Set submitting state
        formData.submitting = true;
        this.setSubmittingState($form, true);

        // Collect form data
        const submissionData = this.collectFormData($form);

        // Submit form
        this.submitForm(formId, submissionData)
            .then(response => {
                this.handleSubmissionSuccess($container, response);
            })
            .catch(error => {
                this.handleSubmissionError($container, error);
            })
            .finally(() => {
                formData.submitting = false;
                this.setSubmittingState($form, false);
            });
    };

    /**
     * Validate entire form
     */
    dtWebform.validateForm = function($form, formId) {
        let isValid = true;

        $form.find('.dt-webform-field').each(function() {
            const $fieldContainer = $(this);
            const $field = $fieldContainer.find('input, textarea, select').first();
            const fieldKey = $fieldContainer.data('field-key');

            if (!dtWebform.validateField($field, fieldKey, formId)) {
                isValid = false;
            }
        });

        return isValid;
    };

    /**
     * Validate individual field
     */
    dtWebform.validateField = function($field, fieldKey, formId) {
        const $fieldContainer = $field.closest('.dt-webform-field');
        const fieldType = $fieldContainer.data('field-key');
        const isRequired = $field.prop('required');
        let value = this.getFieldValue($field, $fieldContainer);
        let isValid = true;
        let errorMessage = '';

        // Required field validation
        if (isRequired && this.isEmpty(value)) {
            isValid = false;
            errorMessage = dtWebformPublic.strings.required;
        }

        // Type-specific validation
        if (isValid && !this.isEmpty(value)) {
            const typeValidation = this.validateFieldType($field, value);
            if (!typeValidation.valid) {
                isValid = false;
                errorMessage = typeValidation.message;
            }
        }

        // Display validation result
        if (isValid) {
            this.clearFieldError($field);
        } else {
            this.showFieldError($field, errorMessage);
        }

        return isValid;
    };

    /**
     * Validate field type-specific rules
     */
    dtWebform.validateFieldType = function($field, value) {
        const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

        switch (fieldType) {
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    return { valid: false, message: 'Please enter a valid email address.' };
                }
                break;

            case 'url':
                try {
                    new URL(value);
                } catch {
                    return { valid: false, message: 'Please enter a valid URL.' };
                }
                break;

            case 'number':
                if (isNaN(value) || value === '') {
                    return { valid: false, message: 'Please enter a valid number.' };
                }
                
                // Check min/max if specified
                const min = $field.attr('min');
                const max = $field.attr('max');
                const numValue = parseFloat(value);
                
                if (min && numValue < parseFloat(min)) {
                    return { valid: false, message: `Value must be at least ${min}.` };
                }
                if (max && numValue > parseFloat(max)) {
                    return { valid: false, message: `Value must be no more than ${max}.` };
                }
                break;

            case 'tel':
                // Basic phone validation
                const phoneRegex = /^[\+]?[0-9\s\-\(\)]{7,}$/;
                if (!phoneRegex.test(value)) {
                    return { valid: false, message: 'Please enter a valid phone number.' };
                }
                break;

            case 'date':
                const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                if (!dateRegex.test(value)) {
                    return { valid: false, message: 'Please enter a valid date (YYYY-MM-DD).' };
                }
                break;
        }

        return { valid: true };
    };

    /**
     * Get field value (handles different input types)
     */
    dtWebform.getFieldValue = function($field, $fieldContainer) {
        const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

        switch (fieldType) {
            case 'checkbox':
                if ($fieldContainer.find('input[type="checkbox"]').length > 1) {
                    // Multi-select checkboxes
                    const values = [];
                    $fieldContainer.find('input[type="checkbox"]:checked').each(function() {
                        values.push($(this).val());
                    });
                    return values;
                } else {
                    // Single checkbox
                    return $field.is(':checked') ? $field.val() : '';
                }

            case 'radio':
                return $fieldContainer.find('input[type="radio"]:checked').val() || '';

            default:
                return $field.val() || '';
        }
    };

    /**
     * Check if value is empty
     */
    dtWebform.isEmpty = function(value) {
        if (Array.isArray(value)) {
            return value.length === 0;
        }
        return !value || value.trim() === '';
    };

    /**
     * Collect all form data
     */
    dtWebform.collectFormData = function($form) {
        const data = {};

        $form.find('.dt-webform-field').each(function() {
            const $fieldContainer = $(this);
            const fieldKey = $fieldContainer.data('field-key');
            const $field = $fieldContainer.find('input, textarea, select').first();
            
            const value = dtWebform.getFieldValue($field, $fieldContainer);
            if (!dtWebform.isEmpty(value)) {
                data[fieldKey] = value;
            }
        });

        return data;
    };

    /**
     * Submit form data via AJAX
     */
    dtWebform.submitForm = function(formId, data) {
        const submitUrl = dtWebformPublic.rest_url + 'submit/' + formId;

        return fetch(submitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': dtWebformPublic.nonce
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Network error');
                });
            }
            return response.json();
        });
    };

    /**
     * Handle successful submission
     */
    dtWebform.handleSubmissionSuccess = function($container, response) {
        console.log('DT Webform: Submission successful', response);

        // Hide form
        $container.find('.dt-webform-form').hide();

        // Show success message
        const message = response.message || dtWebformPublic.strings.success;
        this.showSuccess($container, message);

        // Trigger custom event
        $container.trigger('dt-webform:success', [response]);
    };

    /**
     * Handle submission error
     */
    dtWebform.handleSubmissionError = function($container, error) {
        console.error('DT Webform: Submission error', error);

        let errorMessage = dtWebformPublic.strings.error;

        if (error.message) {
            errorMessage = error.message;
        }

        this.showError($container, errorMessage);

        // Trigger custom event
        $container.trigger('dt-webform:error', [error]);
    };

    /**
     * Set form submitting state
     */
    dtWebform.setSubmittingState = function($form, submitting) {
        const $submitBtn = $form.find('.dt-webform-submit-btn');

        if (submitting) {
            $submitBtn.prop('disabled', true);
            $submitBtn.data('original-text', $submitBtn.text());
            $submitBtn.text(dtWebformPublic.strings.submitting);
            $form.addClass('dt-webform-submitting');
        } else {
            $submitBtn.prop('disabled', false);
            $submitBtn.text($submitBtn.data('original-text') || dtWebformPublic.strings.submit);
            $form.removeClass('dt-webform-submitting');
        }
    };

    /**
     * Show field error
     */
    dtWebform.showFieldError = function($field, message) {
        const $fieldContainer = $field.closest('.dt-webform-field');
        const $errorContainer = $fieldContainer.find('.dt-webform-field-error');

        $fieldContainer.addClass('dt-webform-field-error-state');
        $errorContainer.text(message).show();

        // Focus on first error field
        if (!$('.dt-webform-field-error-state').length) {
            $field.focus();
        }
    };

    /**
     * Clear field error
     */
    dtWebform.clearFieldError = function($field) {
        const $fieldContainer = $field.closest('.dt-webform-field');
        const $errorContainer = $fieldContainer.find('.dt-webform-field-error');

        $fieldContainer.removeClass('dt-webform-field-error-state');
        $errorContainer.hide().text('');
    };

    /**
     * Show success message
     */
    dtWebform.showSuccess = function($container, message) {
        const $messagesContainer = $container.find('.dt-webform-messages');
        const $successContainer = $messagesContainer.find('.dt-webform-success');

        $successContainer.text(message);
        $messagesContainer.show();
        $successContainer.show();

        // Scroll to message
        $('html, body').animate({
            scrollTop: $messagesContainer.offset().top - 50
        }, 500);
    };

    /**
     * Show error message
     */
    dtWebform.showError = function($container, message) {
        const $messagesContainer = $container.find('.dt-webform-messages');
        const $errorContainer = $messagesContainer.find('.dt-webform-error');

        $errorContainer.text(message);
        $messagesContainer.show();
        $errorContainer.show();

        // Scroll to message
        $('html, body').animate({
            scrollTop: $messagesContainer.offset().top - 50
        }, 500);
    };

    /**
     * Clear all messages
     */
    dtWebform.clearMessages = function($container) {
        const $messagesContainer = $container.find('.dt-webform-messages');
        $messagesContainer.hide();
        $messagesContainer.find('.dt-webform-message').hide().text('');
    };

    // Expose dtWebform to global scope for debugging and extensions
    window.dtWebform = dtWebform;

})(jQuery); 