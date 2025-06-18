<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Core functionality for DT Webform plugin
 */
class DT_Webform_Core {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_capabilities' ] );
    }

    /**
     * Register the dt_webform custom post type
     */
    public function register_post_type() {
        register_post_type( 'dt_webform', [
            'labels' => [
                'name' => __( 'DT Webforms', 'dt-webform' ),
                'singular_name' => __( 'DT Webform', 'dt-webform' ),
                'menu_name' => __( 'Webforms', 'dt-webform' ),
                'add_new' => __( 'Add New', 'dt-webform' ),
                'add_new_item' => __( 'Add New Webform', 'dt-webform' ),
                'edit_item' => __( 'Edit Webform', 'dt-webform' ),
                'new_item' => __( 'New Webform', 'dt-webform' ),
                'view_item' => __( 'View Webform', 'dt-webform' ),
                'search_items' => __( 'Search Webforms', 'dt-webform' ),
                'not_found' => __( 'No webforms found', 'dt-webform' ),
                'not_found_in_trash' => __( 'No webforms found in trash', 'dt-webform' ),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => [ 'title', 'author' ],
            'capability_type' => 'dt_webform',
            'map_meta_cap' => true,
            'capabilities' => [
                'create_posts' => 'create_dt_webforms',
                'edit_posts' => 'edit_dt_webforms',
                'edit_others_posts' => 'edit_others_dt_webforms',
                'publish_posts' => 'publish_dt_webforms',
                'read_private_posts' => 'read_private_dt_webforms',
                'delete_posts' => 'delete_dt_webforms',
                'delete_private_posts' => 'delete_private_dt_webforms',
                'delete_published_posts' => 'delete_published_dt_webforms',
                'delete_others_posts' => 'delete_others_dt_webforms',
                'edit_private_posts' => 'edit_private_dt_webforms',
                'edit_published_posts' => 'edit_published_dt_webforms',
            ]
        ] );
    }

    /**
     * Register capabilities for dt_webform post type
     */
    public function register_capabilities() {
        $capabilities = [
            'create_dt_webforms',
            'edit_dt_webforms',
            'edit_others_dt_webforms',
            'publish_dt_webforms',
            'read_private_dt_webforms',
            'delete_dt_webforms',
            'delete_private_dt_webforms',
            'delete_published_dt_webforms',
            'delete_others_dt_webforms',
            'edit_private_dt_webforms',
            'edit_published_dt_webforms',
        ];

        // Get roles that should have webform capabilities
        $admin_role = get_role( 'administrator' );
        $dispatcher_role = get_role( 'dispatcher' );
        $dt_admin_role = get_role( 'dt_admin' );

        // Add capabilities to appropriate roles
        foreach ( $capabilities as $cap ) {
            if ( $admin_role ) {
                $admin_role->add_cap( $cap );
            }
            if ( $dispatcher_role ) {
                $dispatcher_role->add_cap( $cap );
            }
            if ( $dt_admin_role ) {
                $dt_admin_role->add_cap( $cap );
            }
        }
    }

    /**
     * Create a new webform
     *
     * @param array $args Form configuration
     * @return int|WP_Error Form ID on success, WP_Error on failure
     */
    public static function create_form( $args = [] ) {
        $defaults = [
            'post_title' => __( 'New Webform', 'dt-webform' ),
            'post_type' => 'contacts',
            'is_active' => true,
            'config' => [],
            'fields' => [],
            'settings' => [
                'title' => '',
                'description' => '',
                'success_message' => __( 'Thank you for your submission!', 'dt-webform' ),
            ]
        ];

        $args = wp_parse_args( $args, $defaults );

        // Create the post
        $form_id = wp_insert_post( [
            'post_type' => 'dt_webform',
            'post_title' => sanitize_text_field( $args['post_title'] ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ] );

        if ( is_wp_error( $form_id ) ) {
            return $form_id;
        }

        // Store form metadata
        update_post_meta( $form_id, 'dt_webform_post_type', sanitize_text_field( $args['post_type'] ) );
        update_post_meta( $form_id, 'dt_webform_is_active', $args['is_active'] ? 1 : 0 );
        update_post_meta( $form_id, 'dt_webform_config', $args['config'] );
        update_post_meta( $form_id, 'dt_webform_fields', $args['fields'] );
        update_post_meta( $form_id, 'dt_webform_settings', $args['settings'] );

        return $form_id;
    }

    /**
     * Get a webform by ID
     *
     * @param int $form_id Form ID
     * @return array|null Form data or null if not found
     */
    public static function get_form( $form_id ) {
        $post = get_post( $form_id );

        if ( ! $post || $post->post_type !== 'dt_webform' ) {
            return null;
        }

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'post_type' => get_post_meta( $form_id, 'dt_webform_post_type', true ),
            'is_active' => (bool) get_post_meta( $form_id, 'dt_webform_is_active', true ),
            'config' => get_post_meta( $form_id, 'dt_webform_config', true ) ?: [],
            'fields' => get_post_meta( $form_id, 'dt_webform_fields', true ) ?: [],
            'settings' => get_post_meta( $form_id, 'dt_webform_settings', true ) ?: [],
            'created_date' => $post->post_date,
            'modified_date' => $post->post_modified,
            'author_id' => $post->post_author,
        ];
    }

    /**
     * Update a webform
     *
     * @param int $form_id Form ID
     * @param array $args Form data to update
     * @return bool True on success, false on failure
     */
    public static function update_form( $form_id, $args = [] ) {
        $post = get_post( $form_id );

        if ( ! $post || $post->post_type !== 'dt_webform' ) {
            return false;
        }

        // Update post if title changed
        if ( isset( $args['post_title'] ) && $args['post_title'] !== $post->post_title ) {
            wp_update_post( [
                'ID' => $form_id,
                'post_title' => sanitize_text_field( $args['post_title'] ),
            ] );
        }

        // Update metadata
        if ( isset( $args['post_type'] ) ) {
            update_post_meta( $form_id, 'dt_webform_post_type', sanitize_text_field( $args['post_type'] ) );
        }
        if ( isset( $args['is_active'] ) ) {
            update_post_meta( $form_id, 'dt_webform_is_active', $args['is_active'] ? 1 : 0 );
        }
        if ( isset( $args['config'] ) ) {
            update_post_meta( $form_id, 'dt_webform_config', $args['config'] );
        }
        if ( isset( $args['fields'] ) ) {
            update_post_meta( $form_id, 'dt_webform_fields', $args['fields'] );
        }
        if ( isset( $args['settings'] ) ) {
            update_post_meta( $form_id, 'dt_webform_settings', $args['settings'] );
        }

        return true;
    }

    /**
     * Delete a webform
     *
     * @param int $form_id Form ID
     * @return bool True on success, false on failure
     */
    public static function delete_form( $form_id ) {
        $post = get_post( $form_id );

        if ( ! $post || $post->post_type !== 'dt_webform' ) {
            return false;
        }

        return wp_delete_post( $form_id, true ) !== false;
    }

    /**
     * Get all webforms
     *
     * @param array $args Query arguments
     * @return array Array of forms
     */
    public static function get_forms( $args = [] ) {
        $defaults = [
            'post_type' => 'dt_webform',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );
        $posts = get_posts( $args );

        $forms = [];
        foreach ( $posts as $post ) {
            $forms[] = self::get_form( $post->ID );
        }

        return $forms;
    }

    /**
     * Get available DT post types for webforms
     *
     * @return array Available post types
     */
    public static function get_available_post_types() {
        $available_types = [];

        // Get all DT post types
        $post_types = DT_Posts::get_post_types();

        foreach ( $post_types as $post_type ) {
            if ( current_user_can( "create_{$post_type}" ) ) {
                $available_types[ $post_type ] = [
                    'label' => ucfirst( str_replace( '_', ' ', $post_type ) ),
                    'fields' => DT_Posts::get_post_field_settings( $post_type ),
                ];
            }
        }

        return $available_types;
    }
}

// Initialize the core class
DT_Webform_Core::instance(); 