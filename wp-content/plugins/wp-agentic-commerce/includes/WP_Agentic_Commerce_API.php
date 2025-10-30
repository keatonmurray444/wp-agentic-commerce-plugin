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

}
