<?php

/**
 * Core REST API Handler Class for registering API Endpoints
 * 
 * Responsibilities:
 * - Registers API endpoints
 * - Hooks into WordPress REST API initialization
 * - Includes request validation and authentication callbacks
 */

namespace WPAgenticCommerce\Core; 
use WPAgenticCommerce\Controllers\WP_AC_Product_Feed_Controller;
use WPAgenticCommerce\Controllers\WP_AC_Delegate_Payment_Controller;

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AC_Register_API_Endpoints {
     // Hook into the REST API initialization to register the plugin's custom routes
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
    }

    // Register API Routes for handling Agentic Commerce product submissions
    public function register_api_routes() {

        // Register Product Feed API URL
        register_rest_route( 'agentic-commerce/v1', '/products/feed', [
            'methods'  => 'GET',
            'callback' => [ WP_AC_Product_Feed_Controller::class, 'get_products' ],
            'permission_callback' => '__return_true', 
        ]);

        // Register Delegated Payment API URL
        register_rest_route('agentic-commerce/v1', '/delegate-payment', [
            'methods' => 'POST', 
            'callback' => [ WP_AC_Delegate_Payment_Controller::class, 'delegate_payment'],
            'permission_callback' => '__return_true',
        ]);
    }
}