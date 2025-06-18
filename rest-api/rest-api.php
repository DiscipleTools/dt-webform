<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

// Include the REST API controllers
require_once plugin_dir_path( __FILE__ ) . 'class-forms-controller.php';
require_once plugin_dir_path( __FILE__ ) . 'class-submissions-controller.php';

class Disciple_Tools_Webform_Endpoints
{
    private static $_instance = null;
    
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    /**
     * Register all API routes for the webform plugin
     */
    public function add_api_routes() {
        // Initialize controllers - they register their own routes
        new DT_Webform_Forms_Controller();
        new DT_Webform_Submissions_Controller();
        
        // Legacy endpoint for testing
        $namespace = 'dt-webform/v1';
        register_rest_route(
            $namespace, '/test', [
                'methods'  => 'GET',
                'callback' => [ $this, 'test_endpoint' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Test endpoint to verify API is working
     */
    public function test_endpoint( WP_REST_Request $request ) {
        return new WP_REST_Response( [
            'success' => true,
            'message' => 'DT Webform API is working',
            'version' => '1.0',
            'timestamp' => current_time( 'mysql' ),
        ], 200 );
    }
}

Disciple_Tools_Webform_Endpoints::instance();
