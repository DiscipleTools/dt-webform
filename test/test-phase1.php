<?php
/**
 * Basic test script for Phase 1 functionality
 * This file can be used to verify that the core infrastructure is working
 * 
 * Note: This is for development testing only and should not be used in production
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Test Phase 1 Implementation
 */
function dt_webform_test_phase1() {
    echo "<h2>DT Webform Phase 1 Test Results</h2>";
    
    $results = [];
    $all_passed = true;
    
    // Test 1: Check if core class exists
    $results['core_class'] = class_exists( 'DT_Webform_Core' );
    if ( ! $results['core_class'] ) {
        $all_passed = false;
    }
    
    // Test 2: Check if REST controller exists
    $results['rest_controller_class'] = class_exists( 'DT_Webform_Forms_Controller' );
    if ( ! $results['rest_controller_class'] ) {
        $all_passed = false;
    }
    
    // Test 3: Check if custom post type is registered
    $results['post_type_registered'] = post_type_exists( 'dt_webform' );
    if ( ! $results['post_type_registered'] ) {
        $all_passed = false;
    }
    
    // Test 4: Check if capabilities are registered
    $admin_role = get_role( 'administrator' );
    $results['capabilities_registered'] = $admin_role && $admin_role->has_cap( 'create_dt_webforms' );
    if ( ! $results['capabilities_registered'] ) {
        $all_passed = false;
    }
    
    // Test 5: Test form creation
    if ( $results['core_class'] ) {
        $test_form_id = DT_Webform_Core::create_form( [
            'post_title' => 'Test Form - Phase 1',
            'post_type' => 'contacts',
            'is_active' => true,
            'fields' => [
                [
                    'key' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                ]
            ]
        ] );
        
        $results['form_creation'] = ! is_wp_error( $test_form_id );
        if ( ! $results['form_creation'] ) {
            $all_passed = false;
        }
        
        // Test 6: Test form retrieval
        if ( $results['form_creation'] ) {
            $retrieved_form = DT_Webform_Core::get_form( $test_form_id );
            $results['form_retrieval'] = $retrieved_form !== null && $retrieved_form['title'] === 'Test Form - Phase 1';
            if ( ! $results['form_retrieval'] ) {
                $all_passed = false;
            }
            
            // Clean up test form
            DT_Webform_Core::delete_form( $test_form_id );
        } else {
            $results['form_retrieval'] = false;
            $all_passed = false;
        }
    } else {
        $results['form_creation'] = false;
        $results['form_retrieval'] = false;
        $all_passed = false;
    }
    
    // Test 7: Check available post types
    if ( $results['core_class'] ) {
        $available_types = DT_Webform_Core::get_available_post_types();
        $results['available_post_types'] = ! empty( $available_types ) && isset( $available_types['contacts'] );
        if ( ! $results['available_post_types'] ) {
            $all_passed = false;
        }
    } else {
        $results['available_post_types'] = false;
        $all_passed = false;
    }
    
    // Test 8: Check admin tab classes
    $results['admin_forms_tab'] = class_exists( 'Disciple_Tools_Webform_Tab_Forms' );
    $results['admin_settings_tab'] = class_exists( 'Disciple_Tools_Webform_Tab_Settings' );
    if ( ! $results['admin_forms_tab'] || ! $results['admin_settings_tab'] ) {
        $all_passed = false;
    }
    
    // Display results
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Test</th><th>Result</th><th>Status</th></tr>";
    
    foreach ( $results as $test => $passed ) {
        $status = $passed ? '✅ PASS' : '❌ FAIL';
        $test_name = ucwords( str_replace( '_', ' ', $test ) );
        echo "<tr><td>{$test_name}</td><td>" . ( $passed ? 'Success' : 'Failed' ) . "</td><td>{$status}</td></tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Overall Result: " . ( $all_passed ? '✅ ALL TESTS PASSED' : '❌ SOME TESTS FAILED' ) . "</h3>";
    
    if ( $all_passed ) {
        echo "<p><strong>🎉 Phase 1 implementation is working correctly!</strong></p>";
        echo "<p>You can now:</p>";
        echo "<ul>";
        echo "<li>Create and manage webforms through the admin interface</li>";
        echo "<li>Use the REST API endpoints for CRUD operations</li>";
        echo "<li>Store form configurations using WordPress posts and metadata</li>";
        echo "<li>Set appropriate user permissions for form management</li>";
        echo "</ul>";
    } else {
        echo "<p><strong>⚠️ Some components need attention.</strong></p>";
        echo "<p>Please check the failed tests above and ensure all required files are properly loaded.</p>";
    }
    
    return $all_passed;
}

// Only run if this file is accessed directly with proper authentication
if ( isset( $_GET['run_phase1_test'] ) && current_user_can( 'manage_dt' ) ) {
    dt_webform_test_phase1();
} 