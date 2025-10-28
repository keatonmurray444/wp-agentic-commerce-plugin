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

        // Still not sure what this is supposed to do
        register_rest_route( 'agentic-commerce/v1', '/submit', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_submission' ],
            'permission_callback' => [ $this, 'authorize_bearer_token' ],
        ] );

        // Register Product Feed API Route
        register_rest_route( 'agentic-commerce/v1', '/products/feed', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_products' ],
            'permission_callback' => '__return_true', 
        ] );

    }

    public function authorize_bearer_token( $request ) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth_header = $headers['Authorization'] ?? '';

        if ( ! $auth_header || stripos( $auth_header, 'Bearer ' ) !== 0 ) {
            return new WP_Error( 'unauthorized', 'Missing or invalid Authorization header', [ 'status' => 401 ] );
        }

        $token = trim( str_ireplace( 'Bearer', '', $auth_header ) );
        $saved_key = get_option( 'agentic_commerce_bearer_key' );

        if ( empty( $saved_key ) || $token !== $saved_key ) {
            return new WP_Error( 'forbidden', 'Invalid token', [ 'status' => 403 ] );
        }

        return true;
    }

    public function handle_submission( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            return new WP_Error( 'bad_request', 'No JSON body provided', [ 'status' => 400 ] );
        }

        // Example: respond with the received data
        return rest_ensure_response([
            'status'   => 'success',
            'received' => $params,
        ]);
    }

    public function get_products( WP_REST_Request $request ) {

        // Query WooCommerce products
        $products = wc_get_products([
            'limit' => -1, // Displays all products
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
