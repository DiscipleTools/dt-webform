/**
 * DT Webform Form Builder JavaScript
 */
(function($) {
    'use strict';

    // Global variables
    let formBuilder = {
        form: null,
        fields: [],
        currentField: null,
        draggedField: null,
        fieldCounter: 0,
        dtFields: {},
        customFields: {},
        initialized: false
    };

    // Initialize the form builder
    function initFormBuilder() {
        if (formBuilder.initialized) return;

        // Check if form builder exists
        if (!$('#dt-webform-builder').length) return;

        // Initialize sortable for form fields
        initSortable();
        
        // Initialize drag and drop
        initDragDrop();
        
        // Initialize event handlers
        initEventHandlers();
        
        // Load existing form data
        loadExistingFormData();
        
        // Load custom field types
        loadCustomFieldTypes();

        // Load initial DT fields if we have them
        if (dtWebformAdmin.initialPostType && dtWebformAdmin.initialDTFields) {
            formBuilder.dtFields = dtWebformAdmin.initialDTFields;
            renderDTFields(dtWebformAdmin.initialDTFields);
        }

        formBuilder.initialized = true;
    }

    // Initialize sortable functionality
    function initSortable() {
        $('#dt-webform-form-fields').sortable({
            handle: '.dt-webform-field-handle',
            placeholder: 'dt-webform-drop-zone',
            tolerance: 'pointer',
            helper: 'clone',
            start: function(event, ui) {
                ui.item.addClass('dragging');
                ui.placeholder.text(dtWebformAdmin.strings.loading);
            },
            stop: function(event, ui) {
                ui.item.removeClass('dragging');
                updateFieldOrder();
            }
        });
    }

    // Initialize drag and drop from sidebar
    function initDragDrop() {
        // Make existing field items draggable (for initial page load)
        $('.dt-webform-field-item').draggable({
            helper: function() {
                return $('<div class="dt-webform-drag-helper">' + 
                    $(this).find('.field-label').text() + 
                    '</div>');
            },
            zIndex: 1000,
            appendTo: 'body',
            cursor: 'grabbing'
        });

        // Make form fields container droppable
        $('#dt-webform-form-fields').droppable({
            accept: '.dt-webform-field-item',
            tolerance: 'pointer',
            over: function(event, ui) {
                $(this).addClass('drag-over');
            },
            out: function(event, ui) {
                $(this).removeClass('drag-over');
            },
            drop: function(event, ui) {
                $(this).removeClass('drag-over');
                addFieldToForm(ui.draggable);
            }
        });
    }

    // Initialize event handlers
    function initEventHandlers() {
        // Post type change handler
        $('#form_post_type').on('change', function() {
            const postType = $(this).val();
            if (postType) {
                loadDTFields(postType);
            } else {
                clearDTFields();
            }
        });

        // Field editor buttons
        $(document).on('click', '.dt-webform-edit-field', function(e) {
            e.preventDefault();
            const fieldId = $(this).data('field-id');
            openFieldEditor(fieldId);
        });

        // Field delete buttons
        $(document).on('click', '.dt-webform-delete-field', function(e) {
            e.preventDefault();
            if (confirm(dtWebformAdmin.strings.confirm_delete)) {
                const fieldId = $(this).data('field-id');
                removeField(fieldId);
            }
        });

        // Preview button
        $('#dt-webform-preview-btn').on('click', function(e) {
            e.preventDefault();
            previewForm();
        });

        // Modal close buttons
        $(document).on('click', '.dt-webform-modal-close, .dt-webform-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Save field button
        $('#dt-webform-save-field').on('click', function(e) {
            e.preventDefault();
            saveFieldSettings();
        });

        // Cancel field button
        $('#dt-webform-cancel-field').on('click', function(e) {
            e.preventDefault();
            closeModal();
        });

        // Save button click
        $(document).on('click', '#dt-webform-save-btn', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            // Use REST API to create/update form
            submitFormViaAPI();
        });

        // Prevent modal content clicks from closing modal
        $(document).on('click', '.dt-webform-modal-content', function(e) {
            e.stopPropagation();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    // Load existing form data
    function loadExistingFormData() {
        const existingFields = [];
        $('.dt-webform-field-editor').each(function() {
            const fieldData = JSON.parse($(this).find('.dt-webform-field-data').text());
            existingFields.push(fieldData);
        });
        formBuilder.fields = existingFields;
        updateEmptyState();
    }

    // Load custom field types
    function loadCustomFieldTypes() {
        // Custom field types are loaded from PHP
        // This function can be expanded to handle dynamic loading
    }

    // Load DT fields for selected post type
    function loadDTFields(postType) {
        const $container = $('#dt-available-fields');
        $container.addClass('dt-webform-loading');

        $.post(dtWebformAdmin.ajaxurl, {
            action: 'dt_webform_get_dt_fields',
            post_type: postType,
            nonce: dtWebformAdmin.nonce
        }, function(response) {
            if (response.success) {
                formBuilder.dtFields = response.data;
                renderDTFields(response.data);
            } else {
                showError('Failed to load DT fields: ' + (response.data || 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            showError('Failed to load DT fields: ' + error);
        }).always(function() {
            $container.removeClass('dt-webform-loading');
        });
    }

    // Clear DT fields
    function clearDTFields() {
        const $container = $('#dt-available-fields');
        $container.html('<p class="description">' + 
            'Select a post type to see available DT fields.' + 
            '</p>');
        formBuilder.dtFields = {};
    }

    // Render DT fields in sidebar
    function renderDTFields(fields) {
        const $container = $('#dt-available-fields');
        $container.empty();

        if (Object.keys(fields).length === 0) {
            $container.html('<p class="description">No available fields for this post type.</p>');
            return;
        }

        Object.values(fields).forEach(function(field) {
            const $fieldItem = $('<div class="dt-webform-field-item" data-field-type="dt" data-field-key="' + field.key + '">' +
                '<span class="field-icon dashicons dashicons-admin-generic"></span>' +
                '<span class="field-label">' + field.label + '</span>' +
                '<span class="field-type">' + field.type + '</span>' +
                '</div>');
            
            // Make field draggable
            $fieldItem.draggable({
                helper: function() {
                    return $('<div class="dt-webform-drag-helper">' + field.label + '</div>');
                },
                zIndex: 1000,
                appendTo: 'body',
                cursor: 'grabbing'
            });

            $container.append($fieldItem);
        });
    }

    // Add field to form
    function addFieldToForm($draggedElement) {
        const fieldType = $draggedElement.data('field-type');
        const fieldKey = $draggedElement.data('field-key');
        let fieldData;

        if (fieldType === 'dt') {
            fieldData = formBuilder.dtFields[fieldKey];
            if (!fieldData) {
                showError('Field data not found for: ' + fieldKey + '. Please select the post type again.');
                return;
            }
        } else if (fieldType === 'custom') {
            fieldData = getCustomFieldData(fieldKey);
            if (!fieldData) {
                showError('Custom field data not found for: ' + fieldKey);
                return;
            }
        }

        if (!fieldData) {
            showError('Field data not found for: ' + fieldKey);
            return;
        }

        // Generate unique field ID
        const fieldId = 'field_' + (++formBuilder.fieldCounter);

        // Create field configuration
        const field = {
            id: fieldId,
            key: fieldData.key || fieldKey,
            label: fieldData.label,
            type: fieldData.type,
            required: fieldData.required || false,
            placeholder: fieldData.placeholder || '',
            description: fieldData.description || '',
            options: fieldData.options || [],
            validation: fieldData.validation || {},
            is_dt_field: fieldData.is_dt_field || false,
            is_custom_field: !fieldData.is_dt_field
        };

        // Add to fields array
        formBuilder.fields.push(field);

        // Render field in form
        renderFormField(field);

        // Update empty state
        updateEmptyState();

        // Open field editor
        setTimeout(function() {
            openFieldEditor(fieldId);
        }, 100);
    }

    // Get custom field data
    function getCustomFieldData(fieldKey) {
        const customTypes = {
            'custom_text': { key: 'custom_text', label: 'Text Input', type: 'text' },
            'custom_textarea': { key: 'custom_textarea', label: 'Textarea', type: 'textarea' },
            'custom_select': { key: 'custom_select', label: 'Dropdown', type: 'select', options: [] },
            'custom_radio': { key: 'custom_radio', label: 'Radio Buttons', type: 'radio', options: [] },
            'custom_checkbox': { key: 'custom_checkbox', label: 'Checkbox', type: 'checkbox' },
            'custom_email': { key: 'custom_email', label: 'Email', type: 'email' },
            'custom_phone': { key: 'custom_phone', label: 'Phone', type: 'tel' },
            'custom_number': { key: 'custom_number', label: 'Number', type: 'number' },
            'custom_date': { key: 'custom_date', label: 'Date', type: 'date' }
        };

        return customTypes[fieldKey] || null;
    }

    // Render form field
    function renderFormField(field) {
        const $container = $('#dt-webform-form-fields');
        const $emptyState = $container.find('.dt-webform-empty-state');
        
        if ($emptyState.length) {
            $emptyState.remove();
        }

        const $fieldEditor = $('<div class="dt-webform-field-editor" data-field-id="' + field.id + '">' +
            '<div class="dt-webform-field-header">' +
                '<div class="dt-webform-field-handle">' +
                    '<span class="dashicons dashicons-menu"></span>' +
                '</div>' +
                '<div class="dt-webform-field-info">' +
                    '<strong>' + field.label + '</strong>' +
                    '<span class="field-type">(' + field.type + ')</span>' +
                    (field.required ? '<span class="field-required">*</span>' : '') +
                '</div>' +
                '<div class="dt-webform-field-actions">' +
                    '<button type="button" class="button button-small dt-webform-edit-field" data-field-id="' + field.id + '">Edit</button>' +
                    '<button type="button" class="button button-small dt-webform-delete-field" data-field-id="' + field.id + '">Delete</button>' +
                '</div>' +
            '</div>' +
            '<div class="dt-webform-field-preview">' +
                renderFieldPreview(field) +
            '</div>' +
            '<script type="application/json" class="dt-webform-field-data">' +
                JSON.stringify(field) +
            '</script>' +
        '</div>');

        $container.append($fieldEditor);
    }

    // Render field preview
    function renderFieldPreview(field) {
        let html = '<div class="dt-webform-field-preview-content">';
        html += '<label class="dt-webform-preview-label">';
        html += field.label;
        if (field.required) {
            html += ' <span class="required">*</span>';
        }
        html += '</label>';

        switch (field.type) {
            case 'textarea':
                html += '<textarea class="dt-webform-preview-field" placeholder="' + 
                    (field.placeholder || '') + '" disabled></textarea>';
                break;
            case 'select':
                html += '<select class="dt-webform-preview-field" disabled>';
                html += '<option>Select an option...</option>';
                if (field.options && field.options.length > 0) {
                    field.options.forEach(function(option) {
                        const value = typeof option === 'object' ? option.value : option;
                        const label = typeof option === 'object' ? option.label : option;
                        html += '<option value="' + value + '">' + label + '</option>';
                    });
                }
                html += '</select>';
                break;
            case 'checkbox':
                html += '<label><input type="checkbox" disabled> ' + field.label + '</label>';
                break;
            case 'radio':
                if (field.options && field.options.length > 0) {
                    field.options.forEach(function(option) {
                        const label = typeof option === 'object' ? option.label : option;
                        html += '<label><input type="radio" name="preview_radio" disabled> ' + label + '</label><br>';
                    });
                }
                break;
            default:
                html += '<input type="' + field.type + '" class="dt-webform-preview-field" placeholder="' + 
                    (field.placeholder || '') + '" disabled>';
                break;
        }

        if (field.description) {
            html += '<p class="dt-webform-field-description">' + field.description + '</p>';
        }

        html += '</div>';
        return html;
    }

    // Update empty state
    function updateEmptyState() {
        const $container = $('#dt-webform-form-fields');
        const $fields = $container.find('.dt-webform-field-editor');
        
        if ($fields.length === 0) {
            $container.html('<div class="dt-webform-empty-state">' +
                '<p>Drag fields from the sidebar to build your form.</p>' +
                '</div>');
        }
    }

    // Open field editor
    function openFieldEditor(fieldId) {
        const field = getFieldById(fieldId);
        if (!field) return;

        formBuilder.currentField = field;
        const modalBody = generateFieldEditorForm(field);
        
        $('#dt-webform-field-editor-modal .dt-webform-modal-body').html(modalBody);
        $('#dt-webform-field-editor-modal').show();
    }

    // Generate field editor form
    function generateFieldEditorForm(field) {
        let html = '<form id="dt-webform-field-settings-form">';
        
        // Basic settings
        html += '<table class="form-table">';
        html += '<tr><th><label for="field_key">Field Key</label></th>';
        html += '<td><input type="text" id="field_key" name="key" value="' + field.key + '" class="regular-text" required></td></tr>';
        
        html += '<tr><th><label for="field_label">Label</label></th>';
        html += '<td><input type="text" id="field_label" name="label" value="' + field.label + '" class="regular-text" required></td></tr>';
        
        html += '<tr><th><label for="field_placeholder">Placeholder</label></th>';
        html += '<td><input type="text" id="field_placeholder" name="placeholder" value="' + (field.placeholder || '') + '" class="regular-text"></td></tr>';
        
        html += '<tr><th><label for="field_description">Description</label></th>';
        html += '<td><textarea id="field_description" name="description" class="large-text" rows="3">' + (field.description || '') + '</textarea></td></tr>';
        
        html += '<tr><th><label for="field_required">Required</label></th>';
        html += '<td><input type="checkbox" id="field_required" name="required" value="1" ' + (field.required ? 'checked' : '') + '> This field is required</td></tr>';

        // Options for select/radio fields
        if (['select', 'radio', 'multi_select'].includes(field.type)) {
            html += '<tr><th>Options</th><td>';
            html += '<div id="field-options-container">';
            
            if (field.options && field.options.length > 0) {
                field.options.forEach(function(option, index) {
                    const value = typeof option === 'object' ? option.value : option;
                    const label = typeof option === 'object' ? option.label : option;
                    html += '<div class="option-row">';
                    html += '<input type="text" name="option_value[]" value="' + value + '" placeholder="Value" class="regular-text">';
                    html += '<input type="text" name="option_label[]" value="' + label + '" placeholder="Label" class="regular-text">';
                    html += '<button type="button" class="button remove-option">Remove</button>';
                    html += '</div>';
                });
            }
            
            html += '</div>';
            html += '<button type="button" class="button add-option">Add Option</button>';
            html += '</td></tr>';
        }

        html += '</table>';
        html += '</form>';

        return html;
    }

    // Save field settings
    function saveFieldSettings() {
        const form = document.getElementById('dt-webform-field-settings-form');
        if (!form) return;

        const formData = new FormData(form);
        const field = formBuilder.currentField;

        // Update field data
        field.key = formData.get('key');
        field.label = formData.get('label');
        field.placeholder = formData.get('placeholder');
        field.description = formData.get('description');
        field.required = formData.get('required') === '1';

        // Handle options
        if (['select', 'radio', 'multi_select'].includes(field.type)) {
            const optionValues = formData.getAll('option_value[]');
            const optionLabels = formData.getAll('option_label[]');
            field.options = [];
            
            for (let i = 0; i < optionValues.length; i++) {
                if (optionValues[i] && optionLabels[i]) {
                    field.options.push({
                        value: optionValues[i],
                        label: optionLabels[i]
                    });
                }
            }
        }

        // Update field in fields array
        const fieldIndex = formBuilder.fields.findIndex(f => f.id === field.id);
        if (fieldIndex !== -1) {
            formBuilder.fields[fieldIndex] = field;
        }

        // Re-render the field
        const $fieldEditor = $('.dt-webform-field-editor[data-field-id="' + field.id + '"]');
        const $fieldHeader = $fieldEditor.find('.dt-webform-field-info');
        const $fieldPreview = $fieldEditor.find('.dt-webform-field-preview');
        const $fieldData = $fieldEditor.find('.dt-webform-field-data');

        // Update header
        $fieldHeader.html(
            '<strong>' + field.label + '</strong>' +
            '<span class="field-type">(' + field.type + ')</span>' +
            (field.required ? '<span class="field-required">*</span>' : '')
        );

        // Update preview
        $fieldPreview.html(renderFieldPreview(field));

        // Update data
        $fieldData.text(JSON.stringify(field));

        closeModal();
    }

    // Remove field
    function removeField(fieldId) {
        // Remove from fields array
        formBuilder.fields = formBuilder.fields.filter(field => field.id !== fieldId);
        
        // Remove from DOM
        $('.dt-webform-field-editor[data-field-id="' + fieldId + '"]').remove();
        
        // Update empty state
        updateEmptyState();
    }

    // Get field by ID
    function getFieldById(fieldId) {
        return formBuilder.fields.find(field => field.id === fieldId);
    }

    // Update field order
    function updateFieldOrder() {
        const orderedFields = [];
        $('#dt-webform-form-fields .dt-webform-field-editor').each(function() {
            const fieldId = $(this).data('field-id');
            const field = getFieldById(fieldId);
            if (field) {
                orderedFields.push(field);
            }
        });
        formBuilder.fields = orderedFields;
    }

    // Preview form
    function previewForm() {
        const formData = {
            title: $('#form_title').val(),
            settings: {
                description: $('#form_description').val(),
                success_message: $('#form_success_message').val()
            },
            fields: formBuilder.fields
        };

        const $previewContainer = $('#dt-webform-preview-container');
        $previewContainer.addClass('dt-webform-loading');

        $.post(dtWebformAdmin.ajaxurl, {
            action: 'dt_webform_preview_form',
            form_data: JSON.stringify(formData),
            nonce: dtWebformAdmin.nonce
        }, function(response) {
            if (response.success) {
                $previewContainer.html(response.data.html);
                $('#dt-webform-preview-modal').show();
            } else {
                showError('Failed to generate preview');
            }
        }).fail(function() {
            showError('Failed to generate preview');
        }).always(function() {
            $previewContainer.removeClass('dt-webform-loading');
        });
    }

    // Submit form via REST API
    function submitFormViaAPI() {
        const formData = {
            title: $('#form_title').val().trim(),
            post_type: $('#form_post_type').val(),
            is_active: $('#form_is_active').is(':checked'),
            fields: formBuilder.fields,
            settings: {
                title: $('#form_title').val().trim(),
                description: $('#form_description').val().trim(),
                success_message: $('#form_success_message').val().trim()
            }
        };

        // Determine if creating or updating
        const formId = $('input[name="form_id"]').val();
        const isUpdate = formId && formId !== '';
        
        // Show loading state
        const $submitBtn = $('#dt-webform-save-btn');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text(isUpdate ? 'Updating...' : 'Creating...');

        const url = isUpdate ? 
            dtWebformAdmin.restUrl + 'dt-webform/v1/forms/' + formId :
            dtWebformAdmin.restUrl + 'dt-webform/v1/forms';
        
        const method = isUpdate ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            method: method,
            contentType: 'application/json',
            data: JSON.stringify(formData),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dtWebformAdmin.restNonce);
            },
            success: function(response) {
                if (isUpdate) {
                    showSuccess('Form updated successfully!');
                } else {
                    showSuccess('Form created successfully!');
                    // Redirect to edit page for new forms
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' + response.id;
                    }, 1500);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to save form';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (error) {
                    errorMessage += ': ' + error;
                }
                showError(errorMessage);
            },
            complete: function() {
                // Restore button state
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    // Validate form
    function validateForm() {
        let isValid = true;
        const errors = [];

        // Check required fields
        if (!$('#form_title').val().trim()) {
            errors.push('Form title is required');
            isValid = false;
        }

        if (!$('#form_post_type').val()) {
            errors.push('Post type is required');
            isValid = false;
        }

        if (formBuilder.fields.length === 0) {
            errors.push('At least one field is required');
            isValid = false;
        }

        if (!isValid) {
            showError(errors.join(', '));
        }

        return isValid;
    }

    // Close modal
    function closeModal() {
        $('.dt-webform-modal').hide();
        formBuilder.currentField = null;
    }

    // Show error message
    function showError(message) {
        alert(message); // Simple alert for now, can be enhanced with better UI
    }

    // Show success message
    function showSuccess(message) {
        alert(message); // Simple alert for now, can be enhanced with better UI
    }

    // Add option row
    $(document).on('click', '.add-option', function(e) {
        e.preventDefault();
        const container = $('#field-options-container');
        const optionRow = $('<div class="option-row">' +
            '<input type="text" name="option_value[]" placeholder="Value" class="regular-text">' +
            '<input type="text" name="option_label[]" placeholder="Label" class="regular-text">' +
            '<button type="button" class="button remove-option">Remove</button>' +
            '</div>');
        container.append(optionRow);
    });

    // Remove option row
    $(document).on('click', '.remove-option', function(e) {
        e.preventDefault();
        $(this).closest('.option-row').remove();
    });

    // Embed code functionality
    function initEmbedCodeHandlers() {
        // Copy to clipboard functionality
        $(document).on('click', '.dt-webform-copy-btn', function(e) {
            e.preventDefault();
            const targetId = $(this).data('target');
            const $target = $('#' + targetId);
            
            if ($target.length) {
                $target.select();
                $target[0].setSelectionRange(0, 99999); // For mobile devices
                
                try {
                    document.execCommand('copy');
                    $(this).text('Copied!').addClass('copied');
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        $(this).text($(this).hasClass('dt-webform-copy-btn') ? 
                            (targetId.includes('url') ? 'Copy URL' : 'Copy Code') : 'Copy')
                            .removeClass('copied');
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy text: ', err);
                    alert('Failed to copy to clipboard. Please select and copy manually.');
                }
            }
        });

        // Generate custom embed code
        $(document).on('click', '#generate-custom-embed', function(e) {
            e.preventDefault();
            
            const width = $('#embed-width').val().trim() || '100%';
            const height = $('#embed-height').val().trim() || '600px';
            const formId = $('input[name="form_id"]').val();
            
            if (!formId) {
                alert('Form ID not found. Please save the form first.');
                return;
            }
            
            const formUrl = dtWebformAdmin.siteUrl + '/webform/' + formId;
            const customEmbedCode = '<iframe src="' + formUrl + '" width="' + width + '" height="' + height + '" frameborder="0" scrolling="auto"></iframe>';
            
            $('#dt-webform-custom-embed').val(customEmbedCode);
            $('.dt-webform-copy-btn[data-target="dt-webform-custom-embed"]').show();
        });

        // Auto-select text when clicking on readonly inputs/textareas
        $(document).on('click', '.dt-webform-code-textarea, .dt-webform-url-input', function() {
            $(this).select();
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initFormBuilder();
        initEmbedCodeHandlers();
    });

})(jQuery);