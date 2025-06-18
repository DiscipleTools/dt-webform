<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * REST API Controller for Form Submissions
 */
class DT_Webform_Submissions_Controller extends WP_REST_Controller {

    protected $namespace = 'dt-public/v1';
    protected $rest_base = 'webform/submit';

    public function __construct() {
        $this->register_routes();
    }

    /**
     * Register the routes for the submissions controller
     */
    public function register_routes() {
        // POST /submit/{form_id} - Submit form data
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<form_id>\d+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'submit_form' ],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'form_id' => [
                    'description' => __( 'Unique identifier for the form.', 'dt-webform' ),
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ] );
    }

    /**
     * Handle form submission
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function submit_form( $request ) {
        $form_id = $request->get_param( 'form_id' );
        $submission_data = $request->get_json_params();

        // Get form configuration
        $form = DT_Webform_Core::get_form( $form_id );
        if ( ! $form ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'dt-webform' ), [ 'status' => 404 ] );
        }

        if ( ! $form['is_active'] ) {
            return new WP_Error( 'form_inactive', __( 'Form is not active.', 'dt-webform' ), [ 'status' => 403 ] );
        }

        // Initialize submission processor
        $processor = new DT_Webform_Submission_Processor();
        
        // Validate submission data
        $validation_result = $processor->validate_submission( $form, $submission_data );
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }

        // Process submission
        $result = $processor->process_submission( $form, $submission_data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Return success response
        return new WP_REST_Response( [
            'success' => true,
            'message' => $form['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ),
            'record_id' => $result['id'] ?? null,
        ], 200 );
    }
} 