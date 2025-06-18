<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Form Submission Processor
 * Handles validation and processing of webform submissions
 */
class DT_Webform_Submission_Processor {

    /**
     * Validate form submission data
     *
     * @param array $form Form configuration
     * @param array $submission_data Submitted form data
     * @return true|WP_Error True on success, WP_Error on validation failure
     */
    public function validate_submission( $form, $submission_data ) {
        $errors = [];

        if ( empty( $submission_data ) || ! is_array( $submission_data ) ) {
            return new WP_Error( 'empty_submission', __( 'No submission data provided.', 'dt-webform' ) );
        }

        // Validate against form fields
        foreach ( $form['fields'] as $field ) {
            $field_key = $field['key'];
            $field_value = $submission_data[ $field_key ] ?? null;

            // Check required fields
            if ( ! empty( $field['required'] ) && empty( $field_value ) ) {
                $errors[] = sprintf( 
                    __( 'Field "%s" is required.', 'dt-webform' ), 
                    $field['label'] ?? $field_key 
                );
                continue;
            }

            // Skip validation if field is empty and not required
            if ( empty( $field_value ) ) {
                continue;
            }

            // Type-specific validation
            $validation_error = $this->validate_field_value( $field, $field_value );
            if ( $validation_error ) {
                $errors[] = $validation_error;
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', __( 'Validation failed.', 'dt-webform' ), $errors );
        }

        return true;
    }

    /**
     * Validate individual field value
     *
     * @param array $field Field configuration
     * @param mixed $value Field value
     * @return string|null Error message or null if valid
     */
    private function validate_field_value( $field, $value ) {
        $field_label = $field['label'] ?? $field['key'];

        switch ( $field['type'] ) {
            case 'email':
                if ( ! is_email( $value ) ) {
                    return sprintf( __( '"%s" must be a valid email address.', 'dt-webform' ), $field_label );
                }
                break;

            case 'url':
                if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return sprintf( __( '"%s" must be a valid URL.', 'dt-webform' ), $field_label );
                }
                break;

            case 'number':
                if ( ! is_numeric( $value ) ) {
                    return sprintf( __( '"%s" must be a number.', 'dt-webform' ), $field_label );
                }
                
                // Check min/max if specified
                if ( isset( $field['validation']['min'] ) && $value < $field['validation']['min'] ) {
                    return sprintf( 
                        __( '"%s" must be at least %s.', 'dt-webform' ), 
                        $field_label, 
                        $field['validation']['min'] 
                    );
                }
                if ( isset( $field['validation']['max'] ) && $value > $field['validation']['max'] ) {
                    return sprintf( 
                        __( '"%s" must be no more than %s.', 'dt-webform' ), 
                        $field_label, 
                        $field['validation']['max'] 
                    );
                }
                break;

            case 'tel':
                // Basic phone validation
                $cleaned = preg_replace( '/[^0-9+\-\(\)\s]/', '', $value );
                if ( strlen( $cleaned ) < 7 ) {
                    return sprintf( __( '"%s" must be a valid phone number.', 'dt-webform' ), $field_label );
                }
                break;

            case 'select':
            case 'radio':
                // Validate against allowed options
                if ( ! empty( $field['options'] ) ) {
                    $valid_values = array_column( $field['options'], 'value' );
                    if ( ! in_array( $value, $valid_values ) ) {
                        return sprintf( __( '"%s" contains an invalid selection.', 'dt-webform' ), $field_label );
                    }
                }
                break;

            case 'multi_select':
                if ( is_array( $value ) && ! empty( $field['options'] ) ) {
                    $valid_values = array_column( $field['options'], 'value' );
                    foreach ( $value as $selected_value ) {
                        if ( ! in_array( $selected_value, $valid_values ) ) {
                            return sprintf( __( '"%s" contains invalid selections.', 'dt-webform' ), $field_label );
                        }
                    }
                }
                break;

            case 'date':
                // Validate date format
                $date = DateTime::createFromFormat( 'Y-m-d', $value );
                if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
                    return sprintf( __( '"%s" must be a valid date (YYYY-MM-DD).', 'dt-webform' ), $field_label );
                }
                break;
        }

        return null;
    }

    /**
     * Process form submission and create DT record
     *
     * @param array $form Form configuration
     * @param array $submission_data Submitted form data
     * @return array|WP_Error Created record data or WP_Error on failure
     */
    public function process_submission( $form, $submission_data ) {
        // Sanitize submission data
        $sanitized_data = $this->sanitize_submission_data( $form, $submission_data );

        // Map form fields to DT post fields
        $dt_fields = $this->map_fields_to_dt_format( $form, $sanitized_data );

        // Add submission metadata
        $dt_fields = $this->add_submission_metadata( $form, $dt_fields, $sanitized_data );

        try {
            // Create DT record
            $result = DT_Posts::create_post( $form['post_type'], $dt_fields, true, false );

            if ( is_wp_error( $result ) ) {
                error_log( 'DT Webform: Failed to create post - ' . $result->get_error_message() );
                return $result;
            }

            // Log successful submission
            $this->log_submission( $form['id'], $result['ID'], $sanitized_data );

            return $result;

        } catch ( Exception $e ) {
            error_log( 'DT Webform: Exception during submission processing - ' . $e->getMessage() );
            return new WP_Error( 'submission_failed', __( 'Failed to process submission.', 'dt-webform' ) );
        }
    }

    /**
     * Sanitize submission data
     *
     * @param array $form Form configuration
     * @param array $submission_data Raw submission data
     * @return array Sanitized data
     */
    private function sanitize_submission_data( $form, $submission_data ) {
        $sanitized = [];

        foreach ( $form['fields'] as $field ) {
            $field_key = $field['key'];
            $field_value = $submission_data[ $field_key ] ?? '';

            if ( empty( $field_value ) ) {
                continue;
            }

            switch ( $field['type'] ) {
                case 'textarea':
                    $sanitized[ $field_key ] = sanitize_textarea_field( $field_value );
                    break;

                case 'email':
                    $sanitized[ $field_key ] = sanitize_email( $field_value );
                    break;

                case 'url':
                    $sanitized[ $field_key ] = esc_url_raw( $field_value );
                    break;

                case 'number':
                    $sanitized[ $field_key ] = floatval( $field_value );
                    break;

                case 'checkbox':
                    $sanitized[ $field_key ] = (bool) $field_value;
                    break;

                case 'multi_select':
                    if ( is_array( $field_value ) ) {
                        $sanitized[ $field_key ] = array_map( 'sanitize_text_field', $field_value );
                    }
                    break;

                default:
                    $sanitized[ $field_key ] = sanitize_text_field( $field_value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Map form fields to DT post format
     *
     * @param array $form Form configuration
     * @param array $sanitized_data Sanitized submission data
     * @return array DT formatted fields
     */
    private function map_fields_to_dt_format( $form, $sanitized_data ) {
        $dt_fields = [];
        $notes = [];

        foreach ( $form['fields'] as $field ) {
            $field_key = $field['key'];
            $field_value = $sanitized_data[ $field_key ] ?? null;

            if ( $field_value === null || $field_value === '' ) {
                continue;
            }

            $field_label = $field['label'] ?? $field_key;

            // Handle DT fields
            if ( ! empty( $field['is_dt_field'] ) ) {
                $dt_fields[ $field_key ] = $this->format_dt_field_value( $field, $field_value );
            } else {
                // Handle custom fields as notes
                if ( is_array( $field_value ) ) {
                    $value_text = implode( ', ', $field_value );
                } else {
                    $value_text = (string) $field_value;
                }
                $notes[] = $field_label . ': ' . $value_text;
            }
        }

        // Add notes if any custom fields were submitted
        if ( ! empty( $notes ) ) {
            $dt_fields['notes'] = [ implode( "\n", $notes ) ];
        }

        return $dt_fields;
    }

    /**
     * Format field value for DT field types
     *
     * @param array $field Field configuration
     * @param mixed $value Field value
     * @return mixed Formatted value
     */
    private function format_dt_field_value( $field, $value ) {
        switch ( $field['type'] ) {
            case 'communication_channel':
                return [ [ 'value' => $value ] ];

            case 'multi_select':
                if ( is_array( $value ) ) {
                    return [ 'values' => array_map( function( $v ) {
                        return [ 'value' => $v ];
                    }, $value ) ];
                }
                return $value;

            case 'checkbox':
            case 'boolean':
                return (bool) $value;

            case 'number':
                return is_numeric( $value ) ? floatval( $value ) : $value;

            default:
                return $value;
        }
    }

    /**
     * Add submission metadata to DT fields
     *
     * @param array $form Form configuration
     * @param array $dt_fields DT fields array
     * @param array $sanitized_data Sanitized submission data
     * @return array Enhanced DT fields
     */
    private function add_submission_metadata( $form, $dt_fields, $sanitized_data ) {
        // Add form source information
        $metadata_notes = [
            sprintf( __( 'Submitted via webform: %s (ID: %d)', 'dt-webform' ), $form['title'], $form['id'] ),
            sprintf( __( 'Submission time: %s', 'dt-webform' ), current_time( 'mysql' ) ),
        ];

        // Add form description if available
        if ( ! empty( $form['settings']['description'] ) ) {
            $metadata_notes[] = sprintf( __( 'Form description: %s', 'dt-webform' ), $form['settings']['description'] );
        }

        // Add referrer information if available
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if ( ! empty( $referrer ) ) {
            $metadata_notes[] = sprintf( __( 'Referrer: %s', 'dt-webform' ), esc_url( $referrer ) );
        }

        // Combine with existing notes
        if ( isset( $dt_fields['notes'] ) ) {
            array_unshift( $dt_fields['notes'], implode( "\n", $metadata_notes ) );
        } else {
            $dt_fields['notes'] = [ implode( "\n", $metadata_notes ) ];
        }

        // Set default title if not provided
        if ( empty( $dt_fields['title'] ) ) {
            $dt_fields['title'] = sprintf( 
                __( 'Webform Submission - %s', 'dt-webform' ), 
                current_time( 'Y-m-d H:i:s' ) 
            );
        }

        return $dt_fields;
    }

    /**
     * Log submission for analytics (simple logging for Phase 3)
     *
     * @param int $form_id Form ID
     * @param int $record_id Created record ID
     * @param array $sanitized_data Submission data
     */
    private function log_submission( $form_id, $record_id, $sanitized_data ) {
        // Simple logging - can be expanded for analytics in future phases
        error_log( sprintf( 
            'DT Webform: Successful submission - Form ID: %d, Record ID: %d, Fields: %d',
            $form_id,
            $record_id,
            count( $sanitized_data )
        ) );

        // Store basic submission count
        $submission_count = get_post_meta( $form_id, '_dt_webform_submission_count', true );
        $submission_count = intval( $submission_count ) + 1;
        update_post_meta( $form_id, '_dt_webform_submission_count', $submission_count );
        update_post_meta( $form_id, '_dt_webform_last_submission', current_time( 'mysql' ) );
    }
} 