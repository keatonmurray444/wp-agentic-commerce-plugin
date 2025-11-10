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

namespace WPAgenticCommerce\Helpers;
class WP_AC_WC_Meta_Fields {
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

        echo 
        '<p class="form-field" style="margin: 0 0 12px; color: #555;">
            <strong>Help shoppers discover your products!</strong><br>
            These fields are used by OpenAI to improve search relevance and product visibility.
            You can leave them blank, but providing accurate details may boost discoverability.
        </p>';

        woocommerce_wp_text_input([
            'id' => '_gtin',
            'label' => 'GTIN / UPC',
            'placeholder'   => 'e.g., 012345',
        ]);

        woocommerce_wp_text_input([
            'id' => '_mpn',
            'label' => 'MPN (Required if missing GTIN)',
            'placeholder' => 'eg., 012345'
        ]);

        woocommerce_wp_text_input([
            'id' => '_condition',
            'label' => 'Condition (Required)',
            'placeholder' => 'eg., New/Used'
        ]);

        woocommerce_wp_text_input([
            'id' => '_brand',
            'label' => 'Brand (Required)',
        ]);

        woocommerce_wp_text_input([
            'id' => '_material',
            'label' => 'Material (Required)',
        ]);

        woocommerce_wp_text_input([
            'id' => '_age_group',
            'label' => 'Age Group',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_video_link',
            'label'         => 'Product Video URL',
            'placeholder'   => 'https://...',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_model_3d_link',
            'label'         => '3D Model URL',
            'placeholder'   => 'https://example.com/model.glb',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_sale_price_effective_date',
            'label'         => 'Sale Price Effective Date',
            'type'          => 'date',
            'class'         => 'no-width datepicker',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_unit_pricing_measure',
            'label'         => 'Unit Pricing Measure/Base Measure',
            'wrapper_class' => 'form-row form-row-full',
            'placeholder'   => 'eg., 1kg'
        ]);

        woocommerce_wp_text_input([
            'id'            => '_pricing_trend',
            'label'         => 'Pricing Trend', 
            'placeholder'   => 'Increasing, stable, or declining'
        ]);

        woocommerce_wp_text_input([
            'id'            => '_availability_date',
            'label'         => 'Availability Date',
            'type'          => 'date',
            'class'         => 'no-width datepicker',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_expiration_date',
            'label'         => 'Expiration Date',
            'type'          => 'date',
            'class'         => 'no-width datepicker',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_pickup_method',
            'label'         => 'Pickup Method',
            'placeholder'   => 'Curbside pickup, in-store pickup, etc.',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_pickup_sla',
            'label'         => 'Pickup SLA',
            'placeholder'   => 'e.g., Ready within 2–4 hours',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id' => '_delivery_estimate_min',
            'label' => 'Delivery Estimate (Min Days)',
            'type' => 'number',
            'custom_attributes' => [ 'min' => '0' ],
            'wrapper_class' => 'form-row form-row-full'
        ]);

        woocommerce_wp_text_input([
            'id' => '_delivery_estimate_max',
            'label' => 'Delivery Estimate (Max Days)',
            'type' => 'number',
            'custom_attributes' => [ 'min' => '0' ],
            'wrapper_class' => 'form-row form-row-full'
        ]);

        woocommerce_wp_text_input([
            'id'            => '_return_policy',
            'label'         => 'Return Policy',
            'placeholder'   => 'Example: 30-day returns. Buyer pays return shipping.',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_return_window',
            'label'         => 'Return Window (Days)',
            'type'          => 'number',
            'placeholder'   => 'e.g. 30',
            'custom_attributes' => [ 'min' => '0' ],
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_popularity_score',
            'label'         => 'Popularity Score (Required)',
            'placeholder'   => 'eg., High, Medium, Low',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_return_rate',
            'label'         => 'Return Rate (Required)',
            'placeholder'   => 'eg., High, Fair, Low',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_warning_url',
            'label'         => 'Warning URL',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_age_restriction_',
            'label'         => 'Age Restriction',
            'placeholder'   => 'For 18 and above',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_related_product_id',
            'label'         => 'Related Product ID',
            'placeholder'   => 'eg., 1',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_relationship_type',
            'label'         => 'Product Relation Type',
            'placeholder'   => 'e.g., Accessory, Replacement, Similar, Variant',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_geo_price',
            'label'         => 'Geo Price',
            'placeholder'   => 'e.g., US: 19.99, CA: 24.99, UK: 17.99',
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'            => '_geo_availability',
            'label'         => 'Geo Availability',
            'placeholder'   => 'e.g., US, CA, UK',
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }    

    public function ac_save_custom_fields($post_id) {
        if (isset($_POST['_gtin'])) {
            update_post_meta($post_id, '_gtin', sanitize_text_field($_POST['_gtin']));
        }
        if (isset($_POST['_mpn'])) {
            update_post_meta($post_id, '_mpn', sanitize_text_field($_POST['_mpn']));
        }
        if (isset($_POST['_condition'])) {
            update_post_meta($post_id, '_condition', sanitize_text_field($_POST['_condition']));
        }
        if (isset($_POST['_brand'])) {
            update_post_meta($post_id, '_brand', sanitize_text_field($_POST['_brand']));
        }
        if (isset($_POST['_material'])) {
            update_post_meta($post_id, '_material', sanitize_text_field($_POST['_material']));
        }
        if (isset($_POST['_age_group'])) {
            update_post_meta($post_id, '_age_group', sanitize_text_field($_POST['_age_group']));
        }
        if (isset($_POST['_video_link'])) {
            update_post_meta($post_id, '_video_link', sanitize_text_field($_POST['_video_link']));
        }
        if (isset($_POST['_model_3d_link'])) {
            update_post_meta($post_id, '_model_3d_link', sanitize_text_field($_POST['_model_3d_link']));
        }
        if (isset($_POST['_sale_price_effective_date'])) {
            update_post_meta($post_id, '_sale_price_effective_date', sanitize_text_field($_POST['_sale_price_effective_date']));
        }
        if (isset($_POST['_unit_pricing_measure'])) {
            update_post_meta($post_id, '_unit_pricing_measure', sanitize_text_field($_POST['_unit_pricing_measure']));
        }
        if (isset($_POST['_pricing_trend'])) {
            update_post_meta($post_id, '_pricing_trend', sanitize_text_field($_POST['_pricing_trend']));
        }
        if (isset($_POST['_availability_date'])) {
            update_post_meta($post_id, '_availability_date', sanitize_text_field($_POST['_availability_date']));
        }
        if (isset($_POST['_expiration_date'])) {
            update_post_meta($post_id, '_expiration_date', sanitize_text_field($_POST['_expiration_date']));
        }
        if (isset($_POST['_pickup_method'])) {
            update_post_meta($post_id, '_pickup_method', sanitize_text_field($_POST['_pickup_method']));
        }
        if (isset($_POST['_pickup_sla'])) {
            update_post_meta($post_id, '_pickup_sla', sanitize_text_field($_POST['_pickup_sla']));
        }
        if (isset($_POST['_delivery_estimate_min'])) {
            update_post_meta($post_id, '_delivery_estimate_min', sanitize_text_field($_POST['_delivery_estimate_min']));
        }
        if (isset($_POST['_delivery_estimate_max'])) {
            update_post_meta($post_id, '_delivery_estimate_max', sanitize_text_field($_POST['_delivery_estimate_max']));
        }
        if (isset($_POST['_return_policy'])) {
            update_post_meta($post_id, '_return_policy', sanitize_text_field($_POST['_return_policy']));
        }
        if (isset($_POST['_return_window'])) {
            update_post_meta($post_id, '_return_window', sanitize_text_field($_POST['_return_window']));
        }
        if (isset($_POST['_popularity_score'])) {
            update_post_meta($post_id, '_popularity_score', sanitize_text_field($_POST['_popularity_score']));
        }
        if (isset($_POST['_return_rate'])) {
            update_post_meta($post_id, '_return_rate', sanitize_text_field($_POST['_return_rate']));
        }
        if (isset($_POST['_warning_url'])) {
            update_post_meta($post_id, '_warning_url', sanitize_text_field($_POST['_warning_url']));
        }
        if (isset($_POST['_age_restriction_'])) {
            update_post_meta($post_id, '_age_restriction_', sanitize_text_field($_POST['_age_restriction_']));
        }
        if (isset($_POST['_related_product_id'])) {
            update_post_meta($post_id, '_related_product_id', sanitize_text_field($_POST['_related_product_id']));
        }
        if (isset($_POST['_relationship_type'])) {
            update_post_meta($post_id, '_relationship_type', sanitize_text_field($_POST['_relationship_type']));
        }
        if (isset($_POST['_geo_price'])) {
            update_post_meta($post_id, '_geo_price', sanitize_text_field($_POST['_geo_price']));
        }
        if (isset($_POST['_geo_availability'])) {
            update_post_meta($post_id, '_geo_availability', sanitize_text_field($_POST['_geo_availability']));
        }
    }
}