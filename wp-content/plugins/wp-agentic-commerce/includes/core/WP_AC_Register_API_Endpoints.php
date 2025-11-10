<?php

/**
 * WP_AC_Register_API_Endpoints
 *
 * Core REST API handler for the WP Agentic Commerce plugin.
 * This class is responsible for registering all custom API endpoints
 * used by the plugin, including product feeds and delegated payment flows.
 *
 * Responsibilities:
 * - Hook into WordPress REST API initialization (`rest_api_init`).
 * - Register custom API routes/endpoints with their callbacks.
 * - Define permission callbacks for endpoints.
 * - Serve as the central entry point for plugin API functionality.
 *
 * Endpoints registered:
 * 1. GET /wp-json/agentic-commerce/v1/products/feed
 *    - Returns a structured product feed for external integrations.
 *    - Callback: WP_AC_Product_Feed_Controller::get_products
 *    - Permissions: public (can be customized)
 *
 * 2. POST /wp-json/agentic-commerce/v1/delegate-payment
 *    - Creates WooCommerce orders and returns a checkout URL.
 *    - Callback: WP_AC_Delegate_Payment_Controller::delegate_payment
 *    - Permissions: public (can be customized)
 * 
 * 3. POST /wp-json/agentic-commerce/v1/create-sessions
 *
 * Example usage:
 *   GET  /wp-json/agentic-commerce/v1/products/feed
 *   POST /wp-json/agentic-commerce/v1/delegate-payment
 *
 * @package WPAgenticCommerce\Core
 */

namespace WPAgenticCommerce\Core;

use WPAgenticCommerce\Controllers\WP_AC_Product_Feed_Controller;
use WPAgenticCommerce\Controllers\WP_AC_Delegate_Payment_Controller;
use WPAgenticCommerce\Controllers\WP_AC_Agentic_Checkout_Controller;
use WP_Error;

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

        // Register Agentic Checkout API URL
        register_rest_route('agentic-commerce/v1', '/checkout_sessions', [
            'methods' => 'POST',
            'callback' => [ WP_AC_Agentic_Checkout_Controller::class, 'create_checkout_session' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('agentic-commerce/v1', '/simulate-payment', [
            'methods' => 'POST',
            'callback' => [ WP_AC_Agentic_Checkout_Controller::class, 'simulate_payment' ],
            'permission_callback' => '__return_true',
        ]);
    }
}