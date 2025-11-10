<?php

/**
 * WP_AC_Delegate_Payment_Controller
 *
 * Handles the "Delegate Payment" REST API endpoint for WooCommerce.
 * This controller allows external applications, such as ChatGPT plugins,
 * to create WooCommerce orders programmatically and generate a checkout
 * URL for delegated payment.
 *
 * Features:
 * - Validates incoming JSON POST payload (items, customer, currency, return_url).
 * - Checks for product existence and available stock.
 * - Sanitizes and prepares order data.
 * - Creates a WooCommerce order with items, currency, and customer email.
 * - Saves raw payload for debugging or tracking purposes.
 * - Sets initial order status as "pending payment".
 * - Calculates totals and persists the order.
 * - Generates a checkout URL for payment completion.
 * - Returns JSON response including:
 *     - ok (boolean)
 *     - order_id (integer)
 *     - payment_url (string)
 *
 * Payload example (Postman):
 * {
 *   "items": [{"id":13,"quantity":1,"price":85}],
 *   "currency": "USD",
 *   "customer": {"email": "test@example.com"},
 *   "return_url": "https://example.com/thank-you"
 * }
 * 
 * Payload example (cURL): 
 * curl -X POST http://localhost:8000/wp-json/agentic-commerce/v1/delegate-payment \
 * -H "Content-Type: application/json" \
 * -d '{
 *   "items": [{"id":13,"quantity":1,"price":85}],
 *  "currency":"USD",
 *  "customer":{"email":"test@example.com"},
 *  "return_url":"http://localhost:8000/thank-you"
 *}'
 *
 *
 * Example usage:
 *   POST /wp-json/agentic-commerce/v1/delegate-payment
 *   Headers: Content-Type: application/json
 *
 * Notes:
 * - Optional fields: currency, customer.email, return_url
 * - Validates stock if product manages inventory
 * - Returns WP_Error for invalid payloads or insufficient stock
 *
 * @package WPAgenticCommerce\Controllers
 */

namespace WPAgenticCommerce\Controllers; 
use WP_REST_Request;

if ( ! defined('ABSPATH') ) exit;

class WP_AC_Delegate_Payment_Controller {
    public static function delegate_payment( WP_REST_Request $request ) {
        $validated = self::validate_delegate_payment_payload( $request );
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
    private static function validate_delegate_payment_payload( WP_REST_Request $request ) {
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