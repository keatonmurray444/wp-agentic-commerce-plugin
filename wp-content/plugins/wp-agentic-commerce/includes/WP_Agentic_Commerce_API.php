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
        add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
    }

    // Register API Routes for handling Agentic Commerce product submissions
    public function register_api_routes() {

        // Register Product Feed API URL
        register_rest_route( 'agentic-commerce/v1', '/products/feed', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_products' ],
            'permission_callback' => '__return_true', 
        ]);

        // Register Delegated Payment API URL
        register_rest_route('agentic-commerce/v1', '/delegate-payment', [
            'methods' => 'POST', 
            'callback' => [ $this, 'delegate_payment'],
            'permission_callback' => '__return_true', 
        ]); 
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
                'basic_product_data' => array_filter([
                    'id' => $product->get_id(),
                    'title' => $product->get_name(),
                    'description' => wp_strip_all_tags( $product->get_description() ),
                    'link' => get_permalink( $product->get_id() ),
                    'gtin' => $product->get_meta('_gtin'),
                    'mpn' => $product->get_meta('_mpn'),
                ]),
                'pricing' => array_filter([
                    'price'       => (float) $product->get_price(),
                    'sale_price'  => (float) $product->get_sale_price(),
                    'currency'    => get_woocommerce_currency(),
                    'sale_price_effective_date' => $product->get_meta('_sale_price_effective_date'),
                    'unit_pricing_measure' => $product->get_meta('_unit_pricing_measure'),
                    'pricing_trend' => $product->get_meta('_pricing_trend')
                ]),
                'assets' => array_filter([
                    'images'      => array_filter([
                        wp_get_attachment_url( $product->get_image_id() )
                    ]),
                    'videos' => array_filter([
                        'video_link' => $product->get_meta('_video_link'),
                        'model_3d_link' => $product->get_meta('_model_3d_link')
                    ])
                ]),
                'inventory' => array_filter([
                    'availability' => $product->get_stock_status(),
                    'inventory_quantity' => $product->get_stock_quantity(),
                    'availability_date' => $product->get_meta('_availability_date'),
                    'expiration_date' => $product->get_meta('_expiration_date'),
                    'pickup_method' => $product->get_meta('_pickup_method'),
                    'pickup_sla' => $product->get_meta('_pickup_sla')
                ]),
                'store_details' => array_filter([
                    'seller_name'  => get_bloginfo('name'),
                    'seller_url'   => home_url(),
                ]),
                'item_information' => array_filter([
                    'condition' => $product->get_meta('_condition'),
                    'brand' => $product->get_meta('_brand'),
                    'material' => $product->get_meta('_material'),
                    'age_group' => $product->get_meta('_age_group'),
                ]),
                'shipping' => array_filter([
                    'shipping_class' => $product->get_shipping_class(),
                    'weight' => $product->get_weight(),
                    'dimensions' => [
                        'length' => $product->get_length(),
                        'width'  => $product->get_width(),
                        'height' => $product->get_height(),
                    ],
                    'delivery_estimate' => [
                        'min_days' => $product->get_meta('_delivery_estimate_min'),
                        'max_days' => $product->get_meta('_delivery_estimate_max')
                    ],
                ]),
                'returns' => array_filter([
                    'return_policy' => $product->get_meta('_return_policy'),
                    'return_window' => $product->get_meta('_return_window')
                ]),
                'performance_signals' => array_filter([
                    'popularity_score' => $product->get_meta('_popularity_score'),
                    'return_rate' => $product->get_meta('_return_rate')
                ]),
                'compliance' => array_filter([
                    'warning_url' => $product->get_meta('_warning_url'),
                    'age_restriction' => $product->get_meta('_age_restriction')
                ]),
                'reviews' => array_filter([
                    'product_review_count' => $product->get_review_count(),
                    'product_review_rating' => 	$product->get_average_rating()
                ]),
                'related_product' => array_filter([
                    'related_product_id' => $product->get_meta('_related_product_id'),
                    'relationship_type' => $product->get_meta('_relationship_type') 
                ]),
                'geo_tagging' => array_filter([
                    'geo_price' => $product->get_meta('_geo_price'),
                    'geo_availability' => $product->get_meta('_geo_availability')
                ]),
                'enable_search'   => true,
                'enable_checkout' => true
            ];
        }

        return rest_ensure_response([
            'products' => $response
        ]);
    }

    /**
     * Delegate Payment logic does the following things 
     * - Validates POST request payload
     * - Create Woo order or cart (redirects users to the actual website's checkout page)
     * - Generates checkout URL
     * - Return JSON response
     */
    public function delegate_payment( WP_REST_Request $request ) {
        $validated = $this->validate_delegate_payment_payload( $request );
        if ( is_wp_error( $validated ) ) {
            return rest_ensure_response( $validated );
        }

        // Create WooCommerce Order
        $order = wc_create_order();

        foreach ( $validated['items'] as $item ) {
            $product = wc_get_product( $item['id'] );
            $order->add_product( $product, $item['quantity'], [
                'subtotal' => $item['price'] ? $item['price'] * $item['quantity'] : null,
                'total'    => $item['price'] ? $item['price'] * $item['quantity'] : null,
            ]);
        }

        // Currency if provided
        if ( ! empty( $validated['currency'] ) ) {
            $order->set_currency( $validated['currency'] );
        }

        // Set customer email if provided
        if ( ! empty( $validated['customer']['email'] ) ) {
            $order->set_billing_email( $validated['customer']['email'] );
        }

        // Save raw payload for debugging/tracking
        $order->update_meta_data( '_agentic_raw_payload', wp_json_encode( $validated['raw'] ) );

        // Set initial status as pending payment
        $order->set_status( 'pending' );
        $order->calculate_totals();
        $order->save();

        // Generate Checkout (Delegated Payment) URL
        $checkout_url = $order->get_checkout_payment_url( true );

        return rest_ensure_response([
            'ok'           => true,
            'order_id'     => $order->get_id(),
            'payment_url'  => $checkout_url,
        ]);
    }

    /**
     * Helper method: Validate delegate payment request payload.
     *
     * Returns array of sanitized payload on success, or WP_Error on failure.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    private function validate_delegate_payment_payload( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        if ( ! is_array( $data ) ) {
            return new \WP_Error(
                'invalid_json',
                'Request body must be a valid JSON object.',
                [ 'status' => 400 ]
            );
        }

        // items: required, non-empty array
        if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
            return new \WP_Error(
                'missing_items',
                'The payload must include an "items" array with at least one item.',
                [ 'status' => 400 ]
            );
        }

        $validated_items = [];

        foreach ( $data['items'] as $index => $item ) {
            // basic shape checks
            if ( ! is_array( $item ) ) {
                return new \WP_Error(
                    'invalid_item',
                    "Each item must be an object (item index: {$index}).",
                    [ 'status' => 400 ]
                );
            }

            // id required and must be integer or numeric string
            if ( empty( $item['id'] ) || ! is_numeric( $item['id'] ) ) {
                return new \WP_Error(
                    'invalid_item_id',
                    "Item at index {$index} requires a numeric 'id'.",
                    [ 'status' => 400 ]
                );
            }
            $product_id = (int) $item['id'];

            // quantity - default to 1 if missing, must be positive integer
            $quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
            if ( $quantity < 1 ) {
                return new \WP_Error(
                    'invalid_quantity',
                    "Item at index {$index} has an invalid 'quantity'. Must be >= 1.",
                    [ 'status' => 400 ]
                );
            }

            // optional price - if provided must be numeric
            if ( isset( $item['price'] ) && ! is_numeric( $item['price'] ) ) {
                return new \WP_Error(
                    'invalid_price',
                    "Item at index {$index} has invalid 'price'. Must be numeric.",
                    [ 'status' => 400 ]
                );
            }
            $price = isset( $item['price'] ) ? (float) $item['price'] : null;

            // Verify product exists in WooCommerce
            $wc_product = wc_get_product( $product_id );
            if ( ! $wc_product ) {
                return new \WP_Error(
                    'product_not_found',
                    "Product with ID {$product_id} (item index: {$index}) was not found.",
                    [ 'status' => 404 ]
                );
            }

            // If managing stock, ensure enough quantity
            if ( $wc_product->managing_stock() ) {
                $stock_qty = (int) $wc_product->get_stock_quantity();
                if ( $stock_qty < $quantity ) {
                    return new \WP_Error(
                        'insufficient_stock',
                        "Product ID {$product_id} does not have enough stock (requested {$quantity}, available {$stock_qty}).",
                        [ 'status' => 409 ]
                    );
                }
            }

            // Build sanitized item object
            $validated_items[] = [
                'id'       => $product_id,
                'quantity' => $quantity,
                'price'    => $price,
                'sku'      => $wc_product->get_sku(),
                'title'    => $wc_product->get_name(),
            ];
        }

        // currency: optional, but if present must be 3-letter string
        $currency = isset( $data['currency'] ) ? strtoupper( sanitize_text_field( $data['currency'] ) ) : get_woocommerce_currency();
        if ( $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
            return new \WP_Error(
                'invalid_currency',
                'Currency must be a 3-letter ISO code (e.g., USD).',
                [ 'status' => 400 ]
            );
        }

        // return_url: optional but if present must be a valid URL
        $return_url = isset( $data['return_url'] ) ? esc_url_raw( $data['return_url'] ) : null;
        if ( isset( $data['return_url'] ) && empty( $return_url ) ) {
            return new \WP_Error(
                'invalid_return_url',
                'return_url is not a valid URL.',
                [ 'status' => 400 ]
            );
        }

        // customer.email: optional but if present must be valid email
        $customer_email = null;
        if ( ! empty( $data['customer']['email'] ) ) {
            $customer_email = sanitize_email( $data['customer']['email'] );
            if ( empty( $customer_email ) ) {
                return new \WP_Error(
                    'invalid_customer_email',
                    'customer.email must be a valid email address.',
                    [ 'status' => 400 ]
                );
            }
        }

        // Build the sanitized payload to use for order/cart creation
        $sanitized = [
            'items'       => $validated_items,
            'currency'    => $currency,
            'return_url'  => $return_url,
            'customer'    => [
                'email' => $customer_email,
            ],
            // include raw data for anything else you may need
            'raw'         => $data,
        ];

        return $sanitized;
    }

}
