<?php
/**
 * Class WP_Agentic_Commerce_Meta_Fields
 *
 * Adds and manages custom WooCommerce product fields required for
 * OpenAI Agentic Commerce product feed integration.
 *
 * Responsibilities:
 *  - Registers custom product fields (e.g., GTIN, MPN, Brand, Condition)
 *  - Saves field values when a product is updated
 *  - Provides getter methods for use in the product feed
 *
 * Usage:
 *  Instantiate the class in the main plugin file to automatically
 *  hook into WooCommerce product screens and saving process.
 *
 * @package WPAgenticCommerce
 */

namespace WPAgenticCommerce;
class WP_Agentic_Commerce_Meta_Fields {
    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action(
            'woocommerce_product_options_general_product_data',
            [ $this, 'ac_add_custom_fields' ]
        );

        add_action(
            'woocommerce_process_product_meta',
            [ $this, 'ac_save_custom_fields' ]
        );
    }

    public function ac_add_custom_fields() {
        woocommerce_wp_text_input([
            'id' => '_gtin',
            'label' => 'GTIN / UPC',
        ]);

         woocommerce_wp_text_input([
            'id' => '_mpn',
            'label' => 'MPN',
        ]);
    }    

    public function ac_save_custom_fields($post_id) {
        if (isset($_POST['_gtin'])) {
            update_post_meta($post_id, '_gtin', sanitize_text_field($_POST['_gtin']));
        }
        if (isset($_POST['_mpn'])) {
            update_post_meta($post_id, '_mpn', sanitize_text_field($_POST['_mpn']));
        }
    }
}