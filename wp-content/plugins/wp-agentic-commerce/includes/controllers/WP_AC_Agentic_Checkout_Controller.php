<?php

/**
 * WP_AC_Agentic_Checkout_Controller
 *
 * Handles the creation of checkout sessions for the WP Agentic Commerce plugin.
 * This controller provides a REST API endpoint to initialize a payment session
 * for a WooCommerce order, enabling users to complete payments within ChatGPT
 * or other external applications without leaving the chat interface.
 *
 * Responsibilities:
 * - Validate incoming request payload for checkout creation.
 * - Create WooCommerce orders based on requested items and customer details.
 * - Generate a payment session using a supported gateway (e.g., Stripe, PayPal).
 * - Return structured JSON response containing:
 *     - order ID
 *     - payment session information (session ID, gateway type, publishable key)
 * - Ensure security and data validation for all checkout requests.
 *
 * Example endpoint:
 *   POST /wp-json/agentic-commerce/v1/checkout_sessions
 *
 * Example payload:
 * {
 *   "items": [{"id": 13, "quantity": 1, "price": 85}],
 *   "currency": "USD",
 *   "customer": {"email": "test@example.com"},
 *   "return_url": "https://example.com/thank-you"
 * }
 *
 * @package WPAgenticCommerce\Controllers
 */

namespace WPAgenticCommerce\Controllers;
use WP_REST_Request;
use WP_Error;

class WP_AC_Agentic_Checkout_Controller {
    /**
     * Create a checkout session for Agentic Commerce.
     *
     * Expected payload:
     * {
     *   "items": [{"id":13,"quantity":1,"price":85}],
     *   "currency":"USD",
     *   "customer":{"email":"test@example.com"},
     *   "return_url":"http://localhost:8000/thank-you"
     * }
     */
    public static function create_checkout_session(WP_REST_Request $request) {
        $data = $request->get_json_params(); 

        if (empty($data['items']) || !is_array($data['items'])) {
            return new WP_Error('missing_items', 'You must provide at least one item.', ['status' => 400]);
        }

        $validated_items = [];

        foreach ($data['items'] as $index => $item) {

            if (empty($item['id']) || !is_numeric($item['id'])) {
                return new WP_Error('invalid_item_id', "Item at index {$index} must have a numeric ID.", ['status' => 400]);
            }

            $product = wc_get_product((int)$item['id']);
            if (!$product) {
                return new WP_Error('product_not_found', "Product ID {$item['id']} not found.", ['status' => 404]);
            }

            $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
            $price = isset($item['price']) ? (float)$item['price'] : null;

            $validated_items[] = [
                'product'  => $product,
                'quantity' => $quantity,
                'price'    => $price,
            ];
        }

        $currency = !empty($data['currency']) ? strtoupper(sanitize_text_field($data['currency'])) : get_woocommerce_currency();
        $customer_email = !empty($data['customer']['email']) ? sanitize_email($data['customer']['email']) : null;
        $return_url = !empty($data['return_url']) ? esc_url_raw($data['return_url']) : null;

        // --------------------------
        // Step 2: Create WooCommerce Order
        // --------------------------
        $order = wc_create_order();

        foreach ($validated_items as $item) {
            $product = $item['product'];
            $qty = $item['quantity'];
            $item_price = $item['price'] ?? (float)$product->get_price();

            $order->add_product($product, $qty, [
                'subtotal' => $item_price * $qty,
                'total'    => $item_price * $qty,
            ]);
        }

        // Set currency
        if ($currency) {
            $order->set_currency($currency);
        }

        // Set customer email
        if ($customer_email) {
            $order->set_billing_email($customer_email);
        }

        // Store raw payload for debugging
        $order->update_meta_data('_agentic_raw_payload', wp_json_encode($data));

        // Set initial status
        $order->set_status('pending');
        $order->calculate_totals();
        $order->save();

        // Generate checkout/payment URL
        $checkout_url = $order->get_checkout_payment_url(true);

        // Return structured JSON
        return rest_ensure_response([
            'ok'           => true,
            'order_id'     => $order->get_id(),
            'checkout_url' => $checkout_url,
            'return_url'   => $return_url,
        ]);
    }

    /**
     * Marks a WooCommerce order as completed.
     *
     * Accepts a POST request with the order ID, updates the order status to 'completed',
     * and returns the updated order status.
     *
     * Expected POST payload (JSON):
     * {
     *   "order_id": 123   // ID of the WooCommerce order to mark as completed
     * }
     *
     * Example cURL request:
     * curl -X POST http://localhost:8000/wp-json/agentic-commerce/v1/simulate-payment \
     *      -H "Content-Type: application/json" \
     *      -d '{"order_id":123}'
     *
     * Response JSON:
     * {
     *   "ok": true,
     *   "order_id": 123,
     *   "status": "completed"
     * }
     *
     * @param WP_REST_Request $request The REST request object containing 'order_id'.
     * @return WP_REST_Response|WP_Error REST response with updated order status.
     */
    public static function simulate_payment(WP_REST_Request $request) {
        $order_id = (int) $request->get_param('order_id');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found.', ['status' => 404]);
        }

        $order->update_status('completed', 'Payment simulated for testing.');

        return rest_ensure_response([
            'ok' => true,
            'order_id' => $order_id,
            'status' => $order->get_status(),
        ]);
    }
}