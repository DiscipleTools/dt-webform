<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * REST API Controller for Forms CRUD operations
 */
class DT_Webform_Forms_Controller extends WP_REST_Controller {

    protected $namespace = 'dt-webform/v1';
    protected $rest_base = 'forms';

    public function __construct() {
        $this->register_routes();
    }

    /**
     * Register the routes for the forms controller
     */
    public function register_routes() {
        // GET /forms - List all forms
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_forms' ],
                'permission_callback' => [ $this, 'check_admin_permissions' ],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'create_form' ],
                'permission_callback' => [ $this, 'check_admin_permissions' ],
                'args' => $this->get_form_schema(),
            ],
        ] );

        // GET /forms/{id} - Get specific form
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_form' ],
                'permission_callback' => [ $this, 'check_form_permissions' ],
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the form.', 'dt-webform' ),
                        'type' => 'integer',
                        'required' => true,
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [ $this, 'update_form' ],
                'permission_callback' => [ $this, 'check_admin_permissions' ],
                'args' => $this->get_form_schema(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [ $this, 'delete_form' ],
                'permission_callback' => [ $this, 'check_admin_permissions' ],
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the form.', 'dt-webform' ),
                        'type' => 'integer',
                        'required' => true,
                    ],
                ],
            ],
        ] );

        // GET /forms/{id}/config - Get form configuration for rendering
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_form_config' ],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'id' => [
                    'description' => __( 'Unique identifier for the form.', 'dt-webform' ),
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ] );

        // Also register public config endpoint under dt-public namespace
        register_rest_route( 'dt-public/v1', '/webform/(?P<id>\d+)/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_form_config' ],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'id' => [
                    'description' => __( 'Unique identifier for the form.', 'dt-webform' ),
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ] );
    }

    /**
     * Get a collection of forms
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_forms( $request ) {
        $args = [
            'posts_per_page' => $request->get_param( 'per_page' ) ?: -1,
            'offset' => $request->get_param( 'offset' ) ?: 0,
        ];

        if ( $request->get_param( 'status' ) ) {
            // Filter by active status
            if ( $request->get_param( 'status' ) === 'active' ) {
                $args['meta_query'] = [
                    [
                        'key' => 'dt_webform_is_active',
                        'value' => '1',
                        'compare' => '=',
                    ],
                ];
            }
        }

        $forms = DT_Webform_Core::get_forms( $args );

        $response_data = [];
        foreach ( $forms as $form ) {
            $admin_form = $this->prepare_form_for_admin( $form );
            $response_data[] = $this->prepare_form_for_response( $admin_form );
        }

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Get a specific form
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_form( $request ) {
        $form_id = $request->get_param( 'id' );
        $form = DT_Webform_Core::get_form( $form_id );

        if ( ! $form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ), [ 'status' => 404 ] );
        }

        $admin_form = $this->prepare_form_for_admin( $form );
        return new WP_REST_Response( $this->prepare_form_for_response( $admin_form ), 200 );
    }

    /**
     * Create a new form
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_form( $request ) {
        $form_data = $this->prepare_form_from_request( $request );

        // Validate data
        $validation_result = $this->validate_form_data( $form_data );
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }

        // Prepare form arguments
        $args = [
            'post_title' => sanitize_text_field( $form_data['title'] ),
            'post_type' => sanitize_text_field( $form_data['post_type'] ),
            'is_active' => ! empty( $form_data['is_active'] ),
            'fields' => $this->sanitize_fields( $form_data['fields'] ?? [] ),
            'settings' => [
                'title' => sanitize_text_field( $form_data['settings']['title'] ?? '' ),
                'description' => sanitize_textarea_field( $form_data['settings']['description'] ?? '' ),
                'success_message' => sanitize_textarea_field( $form_data['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ) ),
            ]
        ];

        $result = DT_Webform_Core::create_form( $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $form = DT_Webform_Core::get_form( $result );
        $admin_form = $this->prepare_form_for_admin( $form );
        return new WP_REST_Response( $this->prepare_form_for_response( $admin_form ), 201 );
    }

    /**
     * Update an existing form
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_form( $request ) {
        $form_id = $request->get_param( 'id' );
        $form_data = $this->prepare_form_from_request( $request );

        // Check if form exists
        $existing_form = DT_Webform_Core::get_form( $form_id );
        if ( ! $existing_form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ), [ 'status' => 404 ] );
        }

        // Validate data
        $validation_result = $this->validate_form_data( $form_data );
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }

        // Prepare update arguments
        $args = [
            'post_title' => sanitize_text_field( $form_data['title'] ),
            'post_type' => sanitize_text_field( $form_data['post_type'] ),
            'is_active' => ! empty( $form_data['is_active'] ),
            'fields' => $this->sanitize_fields( $form_data['fields'] ?? [] ),
            'settings' => [
                'title' => sanitize_text_field( $form_data['settings']['title'] ?? '' ),
                'description' => sanitize_textarea_field( $form_data['settings']['description'] ?? '' ),
                'success_message' => sanitize_textarea_field( $form_data['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ) ),
            ]
        ];

        $success = DT_Webform_Core::update_form( $form_id, $args );

        if ( ! $success ) {
            return new WP_Error( 'update_failed', __( 'Failed to update form.', 'dt-webform' ), [ 'status' => 500 ] );
        }

        $form = DT_Webform_Core::get_form( $form_id );
        $admin_form = $this->prepare_form_for_admin( $form );
        return new WP_REST_Response( $this->prepare_form_for_response( $admin_form ), 200 );
    }

    /**
     * Delete a form
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_form( $request ) {
        $form_id = $request->get_param( 'id' );

        if ( ! DT_Webform_Core::delete_form( $form_id ) ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete form.', 'dt-webform' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [ 'deleted' => true ], 200 );
    }

    /**
     * Get form configuration for public rendering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_form_config( $request ) {
        $form_id = $request->get_param( 'id' );
        $form = DT_Webform_Core::get_form( $form_id );

        if ( ! $form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ), [ 'status' => 404 ] );
        }

        if ( ! $form['is_active'] ) {
            return new WP_Error( 'form_inactive', __( 'Form is not active.', 'dt-webform' ), [ 'status' => 403 ] );
        }

        // Return only the data needed for rendering
        $config = [
            'id' => $form['id'],
            'title' => $form['title'],
            'settings' => $form['settings'],
            'fields' => $form['fields'],
            'post_type' => $form['post_type'],
        ];

        return new WP_REST_Response( $config, 200 );
    }

    /**
     * Check admin permissions
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_admin_permissions( $request ) {
        return current_user_can( 'manage_dt' );
    }

    /**
     * Check form-specific permissions (for public access)
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_form_permissions( $request ) {
        // For Phase 1, allow access if user has admin permissions
        // This can be expanded later for more granular permissions
        return current_user_can( 'manage_dt' );
    }

    /**
     * Prepare form data from REST request
     *
     * @param WP_REST_Request $request
     * @return array
     */
    private function prepare_form_from_request( $request ) {
        $form_data = [];

        if ( $request->has_param( 'title' ) ) {
            $form_data['title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }

        if ( $request->has_param( 'post_type' ) ) {
            $form_data['post_type'] = sanitize_text_field( $request->get_param( 'post_type' ) );
        }

        if ( $request->has_param( 'is_active' ) ) {
            $form_data['is_active'] = (bool) $request->get_param( 'is_active' );
        }

        if ( $request->has_param( 'fields' ) ) {
            $form_data['fields'] = $request->get_param( 'fields' );
        }

        if ( $request->has_param( 'settings' ) ) {
            $form_data['settings'] = $request->get_param( 'settings' );
        }

        return $form_data;
    }

    /**
     * Prepare form for REST response
     *
     * @param array $form
     * @return array
     */
    private function prepare_form_for_response( $form ) {
        if ( ! $form ) {
            return [];
        }

        return [
            'id' => (int) $form['id'],
            'title' => $form['title'],
            'post_type' => $form['post_type'],
            'is_active' => (bool) $form['is_active'],
            'fields' => $form['fields'],
            'settings' => $form['settings'],
            'field_count' => isset( $form['field_count'] ) ? (int) $form['field_count'] : count( $form['fields'] ),
            'form_url' => $form['form_url'] ?? '',
            'embed_code' => $form['embed_code'] ?? '',
            'created_date' => $form['created_date'],
            'modified_date' => $form['modified_date'],
            'author_id' => (int) $form['author_id'],
        ];
    }

    /**
     * Get the form schema for validation
     *
     * @return array
     */
    private function get_form_schema() {
        return [
            'title' => [
                'description' => __( 'Form title.', 'dt-webform' ),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'post_type' => [
                'description' => __( 'Target DT post type.', 'dt-webform' ),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'is_active' => [
                'description' => __( 'Whether the form is active.', 'dt-webform' ),
                'type' => 'boolean',
                'default' => false,
            ],
            'fields' => [
                'description' => __( 'Form field configuration.', 'dt-webform' ),
                'type' => 'array',
                'default' => [],
            ],
            'settings' => [
                'description' => __( 'Form settings.', 'dt-webform' ),
                'type' => 'object',
                'default' => [],
            ],
        ];
    }

    /**
     * Get collection parameters
     *
     * @return array
     */
    public function get_collection_params() {
        return [
            'per_page' => [
                'description' => __( 'Maximum number of items to return.', 'dt-webform' ),
                'type' => 'integer',
                'default' => -1,
                'minimum' => -1,
            ],
            'offset' => [
                'description' => __( 'Number of items to skip.', 'dt-webform' ),
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ],
            'status' => [
                'description' => __( 'Filter by form status.', 'dt-webform' ),
                'type' => 'string',
                'enum' => [ 'active', 'inactive', 'all' ],
                'default' => 'all',
            ],
        ];
    }

    /**
     * Prepare form data for admin display
     *
     * @param array $form
     * @return array
     */
    private function prepare_form_for_admin( $form ) {
        if ( ! $form ) {
            return [];
        }

        // Add additional admin-specific data
        $form['embed_code'] = $this->generate_embed_code( $form['id'] );
        $form['form_url'] = $this->get_form_url( $form['id'] );
        $form['field_count'] = count( $form['fields'] );
        
        return $form;
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
                'id' => sanitize_text_field( $field['id'] ?? '' ),
                'key' => sanitize_key( $field['key'] ?? '' ),
                'type' => sanitize_text_field( $field['type'] ?? '' ),
                'label' => sanitize_text_field( $field['label'] ?? '' ),
                'required' => ! empty( $field['required'] ),
                'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
                'description' => sanitize_text_field( $field['description'] ?? '' ),
                'is_dt_field' => ! empty( $field['is_dt_field'] ),
                'is_custom_field' => ! empty( $field['is_custom_field'] ),
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
     * Validate form data
     *
     * @param array $form_data Form data to validate
     * @return true|WP_Error True on success, WP_Error on validation failure
     */
    private function validate_form_data( $form_data ) {
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

        return true;
    }

    /**
     * Generate embed code for a form
     *
     * @param int $form_id Form ID
     * @return string Embed code
     */
    private function generate_embed_code( $form_id ) {
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
    private function get_form_url( $form_id ) {
        return home_url( "/webform/{$form_id}" );
    }
} 