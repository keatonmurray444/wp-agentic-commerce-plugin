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
 * }
 *
 * @package WPAgenticCommerce\Controllers
 */

namespace WPAgenticCommerce\Controllers;
use WP_REST_Request;
use WP_Error;

class WP_AC_Agentic_Checkout_Controller {

    public static function create_checkout_session(WP_REST_Request $request) {

        // --------------------------
        // Step 0: Read headers
        // --------------------------
        $auth_header   = $request->get_header('authorization');       
        $idempotency   = $request->get_header('idempotency-key');    
        $request_id    = $request->get_header('request-id');         
        $timestamp     = $request->get_header('timestamp');          
        $signature     = $request->get_header('signature');          
        $api_version   = $request->get_header('api-version');        

        // Optional: validate Authorization
        if (!$auth_header || !preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
            return new WP_Error('unauthorized', 'Missing or invalid Authorization header.', ['status' => 401]);
        }

        $api_key = $matches[1];

        // Optional: check Idempotency-Key to prevent duplicates
        if ($idempotency) {
            $existing = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_idempotency_key',
                'meta_value' => $idempotency
            ]);
            if (!empty($existing)) {
                $order = $existing[0];
                return rest_ensure_response([
                    'id' => 'checkout_session_' . $order->get_id(),
                    'status' => 'ready_for_payment', 
                    'checkout_url' => $order->get_checkout_payment_url(true),
                    'message' => 'Duplicate request ignored via Idempotency-Key'
                ]);
            }
        }

        // --------------------------
        // Step 1: Parse payload
        // --------------------------
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
            $price = isset($item['price']) ? (float)$item['price'] : (float)$product->get_price();

            $validated_items[] = [
                'product'  => $product,
                'quantity' => $quantity,
                'price'    => $price,
            ];
        }

        $currency = !empty($data['currency']) ? strtoupper(sanitize_text_field($data['currency'])) : get_woocommerce_currency();
        $customer_email = !empty($data['customer']['email']) ? sanitize_email($data['customer']['email']) : null;
        $return_url = !empty($data['return_url']) ? esc_url_raw($data['return_url']) : null;
        $fulfillment_address = !empty($data['fulfillment_address']) ? $data['fulfillment_address'] : null;

        // --------------------------
        // Step 2: Create WooCommerce Order
        // --------------------------
        $order = wc_create_order();

        foreach ($validated_items as $item) {
            $product = $item['product'];
            $qty = $item['quantity'];
            $item_price = $item['price'];

            $order->add_product($product, $qty, [
                'subtotal' => $item_price * $qty,
                'total'    => $item_price * $qty,
            ]);
        }

        $order->set_currency($currency);
        if ($customer_email) {
            $order->set_billing_email($customer_email);
        }

        if ($idempotency) $order->update_meta_data('_idempotency_key', $idempotency);
        if ($request_id) $order->update_meta_data('_request_id', $request_id);
        $order->update_meta_data('_agentic_raw_payload', wp_json_encode($data));

        $order->set_status('pending');
        $order->calculate_totals();
        $order->save();

        $checkout_url = $order->get_checkout_payment_url(true);

        // --------------------------
        // Step 3: Build line items response
        // --------------------------
        $line_items_response = [];
        foreach ($validated_items as $index => $item) {
            $line_items_response[] = [
                'id' => 'line_item_' . $item['product']->get_id(),
                'item' => [
                    'id' => $item['product']->get_id(),
                    'quantity' => $item['quantity']
                ],
                'base_amount' => $item['price'] * $item['quantity'],
                'discount' => 0, // static for now, this can be set via Woocommerce config dynamically
                'subtotal' => 0,
                'tax' => 0, // static for now, this can be set via Woocommerce config dynamically
                'total' => $item['price'] * $item['quantity']
            ];
        }

        // --------------------------
        // Step 4: Build messages
        // --------------------------
        $messages = [];
        if (!$fulfillment_address) {
            $messages[] = [
                'type' => 'error',
                'code' => 'missing_fulfillment_address',
                'path' => '$.fulfillment_address',
                'content_type' => 'plain',
                'content' => 'No fulfillment address provided. Checkout cannot be completed yet.'
            ];
        }

        foreach ($validated_items as $index => $item) {
            if (!$item['product']->is_in_stock()) {
                $messages[] = [
                    'type' => 'error',
                    'code' => 'out_of_stock',
                    'path' => '$.line_items[' . $index . ']',
                    'content_type' => 'plain',
                    'content' => 'This item is not available for sale.'
                ];
            }
        }

        // --------------------------
        // Step 5: Calculate dynamic totals
        // --------------------------
        $items_base_amount = 0;
        foreach ($order->get_items() as $item) {
            $items_base_amount += $item->get_total();
        }

        $tax_amount = $order->get_total_tax();
        $fulfillment_amount = $fulfillment_address ? 100 : 0; // example fee - We can make this dynamic via Woocommerce Dashboard
        $total_amount = $items_base_amount + $tax_amount + $fulfillment_amount;

        $totals = [
            ['type'=>'items_base_amount','display_text'=>'Item(s) total','amount'=>$items_base_amount],
            ['type'=>'subtotal','display_text'=>'Subtotal','amount'=>$items_base_amount],
            ['type'=>'tax','display_text'=>'Tax','amount'=>$tax_amount],
            ['type'=>'fulfillment','display_text'=>'Fulfillment','amount'=>$fulfillment_amount],
            ['type'=>'total','display_text'=>'Total','amount'=>$total_amount]
        ];

        // --------------------------
        // Step 6: Determine status
        // --------------------------
        $status = $fulfillment_address ? 'ready_for_payment' : 'not_ready_for_payment';

        // --------------------------
        // Step 7: Build final response
        // --------------------------
        $response = [
            'id' => 'checkout_session_' . $order->get_id(),
            'payment_provider' => [
                'provider' => 'stripe',
                'supported_payment_methods' => ['card']
            ],
            'status' => $status,
            'currency' => strtolower($currency),
            'line_items' => $line_items_response,
            'fulfillment_address' => $fulfillment_address,
            'fulfillment_option_id' => 'fulfillment_option_123',
            'totals' => $totals,
            'fulfillment_options' => [],
            'messages' => $messages,
            'links' => [
                ['type'=>'terms_of_use','url'=>'https://www.testshop.com/legal/terms-of-use']
            ],
            'checkout_url' => $checkout_url,
            'return_url' => $return_url
        ];

        return rest_ensure_response($response);
    }
}