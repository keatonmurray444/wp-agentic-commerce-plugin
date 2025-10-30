<?php

/**
 * Core REST API handler for the Agentic Commerce plugin.
 *
 * - Hooks into WordPress REST API initialization
 * - Registers all custom API routes under /wp-json/agentic-commerce/v1/
 * - Includes request validation and authentication callbacks
*/

namespace WPAgenticCommerce;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_Agentic_Commerce_API {

    // Hook into the REST API initialization to register the plugin's custom routes
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // Register API Routes for handling Agentic Commerce product submissions
    public function register_routes() {

        // Register Product Feed API URL
        register_rest_route( 'agentic-commerce/v1', '/products/feed', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_products' ],
            'permission_callback' => '__return_true', 
        ] );

    }

    // Product Feed Schema
    public function get_products( WP_REST_Request $request ) {

        // Query WooCommerce products
        $products = wc_get_products([
            'limit' => -1, // Displays all products (modify if necessary)
            'status' => 'publish',
        ]);

        $response = [];

        foreach ( $products as $product ) {

            $response[] = [
                'id'              => $product->get_id(),
                'name'            => $product->get_name(),
                    'description' => wp_strip_all_tags( $product->get_description() ),
                    'price'       => (float) $product->get_price(),
                    'currency'    => get_woocommerce_currency(),
                    'images'      => array_filter([
                        wp_get_attachment_url( $product->get_image_id() )
                    ]),
                    'link'        => get_permalink( $product->get_id() ),
                'enable_search'   => true,
                'enable_checkout' => true
            ];
        }

        return rest_ensure_response([
            'products' => $response
        ]);
    }

}
