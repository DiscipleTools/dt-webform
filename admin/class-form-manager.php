<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Form Manager class for admin operations
 */
class DT_Webform_Form_Manager {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        // Constructor intentionally left empty
    }

    /**
     * Validate form data
     *
     * @param array $form_data Form data to validate
     * @return array|WP_Error Valid form data or WP_Error on validation failure
     */
    public function validate_form_data( $form_data ) {
        $errors = [];

        // Validate title
        if ( empty( $form_data['title'] ) ) {
            $errors[] = __( 'Form title is required.', 'dt-webform' );
        }

        // Validate post type
        if ( empty( $form_data['post_type'] ) ) {
            $errors[] = __( 'Target post type is required.', 'dt-webform' );
        } else {
            $available_types = DT_Webform_Core::get_available_post_types();
            if ( ! isset( $available_types[ $form_data['post_type'] ] ) ) {
                $errors[] = __( 'Invalid post type selected.', 'dt-webform' );
            }
        }

        // Validate fields
        if ( ! empty( $form_data['fields'] ) ) {
            foreach ( $form_data['fields'] as $field ) {
                if ( empty( $field['key'] ) ) {
                    $errors[] = __( 'All fields must have a key.', 'dt-webform' );
                }
                if ( empty( $field['type'] ) ) {
                    $errors[] = __( 'All fields must have a type.', 'dt-webform' );
                }
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ' ', $errors ), $errors );
        }

        return $form_data;
    }

    /**
     * Create a new form from admin input
     *
     * @param array $form_data Form data from admin interface
     * @return int|WP_Error Form ID on success, WP_Error on failure
     */
    public function create_form_from_admin( $form_data ) {
        // Validate data
        $validated_data = $this->validate_form_data( $form_data );
        if ( is_wp_error( $validated_data ) ) {
            return $validated_data;
        }

        // Prepare form arguments
        $args = [
            'post_title' => sanitize_text_field( $validated_data['title'] ),
            'post_type' => sanitize_text_field( $validated_data['post_type'] ),
            'is_active' => ! empty( $validated_data['is_active'] ),
            'fields' => $this->sanitize_fields( $validated_data['fields'] ?? [] ),
            'settings' => [
                'title' => sanitize_text_field( $validated_data['settings']['title'] ?? '' ),
                'description' => sanitize_textarea_field( $validated_data['settings']['description'] ?? '' ),
                'success_message' => sanitize_textarea_field( $validated_data['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ) ),
            ]
        ];

        return DT_Webform_Core::create_form( $args );
    }

    /**
     * Update an existing form from admin input
     *
     * @param int $form_id Form ID
     * @param array $form_data Form data from admin interface
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_form_from_admin( $form_id, $form_data ) {
        // Check if form exists
        $existing_form = DT_Webform_Core::get_form( $form_id );
        if ( ! $existing_form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ) );
        }

        // Validate data
        $validated_data = $this->validate_form_data( $form_data );
        if ( is_wp_error( $validated_data ) ) {
            return $validated_data;
        }

        // Prepare update arguments
        $args = [
            'post_title' => sanitize_text_field( $validated_data['title'] ),
            'post_type' => sanitize_text_field( $validated_data['post_type'] ),
            'is_active' => ! empty( $validated_data['is_active'] ),
            'fields' => $this->sanitize_fields( $validated_data['fields'] ?? [] ),
            'settings' => [
                'title' => sanitize_text_field( $validated_data['settings']['title'] ?? '' ),
                'description' => sanitize_textarea_field( $validated_data['settings']['description'] ?? '' ),
                'success_message' => sanitize_textarea_field( $validated_data['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ) ),
            ]
        ];

        $success = DT_Webform_Core::update_form( $form_id, $args );
        
        return $success ? true : new WP_Error( 'update_failed', __( 'Failed to update form.', 'dt-webform' ) );
    }

    /**
     * Sanitize field configuration
     *
     * @param array $fields Raw field data
     * @return array Sanitized field data
     */
    private function sanitize_fields( $fields ) {
        $sanitized = [];

        foreach ( $fields as $field ) {
            $sanitized_field = [
                'key' => sanitize_key( $field['key'] ?? '' ),
                'type' => sanitize_text_field( $field['type'] ?? '' ),
                'label' => sanitize_text_field( $field['label'] ?? '' ),
                'required' => ! empty( $field['required'] ),
                'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
                'description' => sanitize_text_field( $field['description'] ?? '' ),
            ];

            // Handle field-specific options
            if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                $sanitized_field['options'] = [];
                foreach ( $field['options'] as $option ) {
                    if ( is_array( $option ) ) {
                        $sanitized_field['options'][] = [
                            'value' => sanitize_text_field( $option['value'] ?? '' ),
                            'label' => sanitize_text_field( $option['label'] ?? '' ),
                        ];
                    } else {
                        $sanitized_field['options'][] = sanitize_text_field( $option );
                    }
                }
            }

            // Handle validation rules
            if ( ! empty( $field['validation'] ) && is_array( $field['validation'] ) ) {
                $sanitized_field['validation'] = [];
                foreach ( $field['validation'] as $rule => $value ) {
                    $sanitized_field['validation'][ sanitize_key( $rule ) ] = sanitize_text_field( $value );
                }
            }

            $sanitized[] = $sanitized_field;
        }

        return $sanitized;
    }

    /**
     * Get form data formatted for admin display
     *
     * @param int $form_id Form ID
     * @return array|null Form data or null if not found
     */
    public function get_form_for_admin( $form_id ) {
        $form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $form ) {
            return null;
        }

        // Add additional admin-specific data
        $form['embed_code'] = $this->generate_embed_code( $form_id );
        $form['form_url'] = $this->get_form_url( $form_id );
        
        return $form;
    }

    /**
     * Generate embed code for a form
     *
     * @param int $form_id Form ID
     * @return string Embed code
     */
    public function generate_embed_code( $form_id ) {
        $form_url = $this->get_form_url( $form_id );
        
        return sprintf(
            '<iframe src="%s" width="100%%" height="600" frameborder="0"></iframe>',
            esc_url( $form_url )
        );
    }

    /**
     * Get the public URL for a form
     *
     * @param int $form_id Form ID
     * @return string Form URL
     */
    public function get_form_url( $form_id ) {
        return home_url( "/dt-webform/{$form_id}" );
    }

    /**
     * Get forms list for admin display
     *
     * @param array $args Query arguments
     * @return array Forms list with additional admin data
     */
    public function get_forms_for_admin( $args = [] ) {
        $forms = DT_Webform_Core::get_forms( $args );
        
        foreach ( $forms as &$form ) {
            $form['embed_code'] = $this->generate_embed_code( $form['id'] );
            $form['form_url'] = $this->get_form_url( $form['id'] );
            $form['field_count'] = count( $form['fields'] );
        }
        
        return $forms;
    }

    /**
     * Duplicate a form
     *
     * @param int $form_id Form ID to duplicate
     * @return int|WP_Error New form ID on success, WP_Error on failure
     */
    public function duplicate_form( $form_id ) {
        $original_form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $original_form ) {
            return new WP_Error( 'form_not_found', __( 'Original form not found.', 'dt-webform' ) );
        }

        // Prepare duplicate data
        $args = [
            'post_title' => sprintf( __( 'Copy of %s', 'dt-webform' ), $original_form['title'] ),
            'post_type' => $original_form['post_type'],
            'is_active' => false, // Duplicated forms start as inactive
            'config' => $original_form['config'],
            'fields' => $original_form['fields'],
            'settings' => $original_form['settings'],
        ];

        return DT_Webform_Core::create_form( $args );
    }

    /**
     * Get form statistics
     *
     * @param int $form_id Form ID
     * @return array Form statistics
     */
    public function get_form_stats( $form_id ) {
        // For Phase 1, return basic stats
        // This will be expanded in later phases with actual submission tracking
        return [
            'views' => 0,
            'submissions' => 0,
            'conversion_rate' => 0,
            'last_submission' => null,
        ];
    }

    /**
     * Export form configuration
     *
     * @param int $form_id Form ID
     * @return array|WP_Error Form configuration for export
     */
    public function export_form( $form_id ) {
        $form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ) );
        }

        // Remove ID and dates for clean export
        unset( $form['id'], $form['created_date'], $form['modified_date'], $form['author_id'] );
        
        return $form;
    }

    /**
     * Import form configuration
     *
     * @param array $form_config Form configuration data
     * @return int|WP_Error New form ID on success, WP_Error on failure
     */
    public function import_form( $form_config ) {
        // Validate imported data
        $validated_data = $this->validate_form_data( $form_config );
        if ( is_wp_error( $validated_data ) ) {
            return $validated_data;
        }

        // Create form from imported data
        return $this->create_form_from_admin( $validated_data );
    }
}

// Initialize the form manager
DT_Webform_Form_Manager::instance(); 