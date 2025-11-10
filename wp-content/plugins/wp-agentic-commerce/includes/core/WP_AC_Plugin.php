<?php

/**
 * Core plugin bootstrap for WP Agentic Commerce.
 *
 * Handles plugin initialization, admin configuration pages, and settings storage.
 * API and commerce features are delegated to separate classes for modularity.
 */

namespace WPAgenticCommerce\Core;
use WPAgenticCommerce\Core\WP_AC_Register_API_Endpoints;
use WPAgenticCommerce\Helpers\WP_AC_WC_Meta_Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AC_Plugin {

    public function __construct() {
        $this->enqueue_admin_styles();
        $this->init_hooks();
    }

    // Enqueue stylesheet
    private function enqueue_admin_styles() {
        wp_enqueue_style(
            'agentic-commerce-admin-style',
            plugin_dir_url(__FILE__) . '../../assets/css/agentic-commerce-style.css',
            [],
        '1.0.0'
        );
    }

    private function init_hooks() {
        // Hook to initiazize API Layer
        add_action( 'init', function() {
            new WP_AC_Register_API_Endpoints();
        });

        // Hook to initialize custom Woocommerce meta fields
        add_action('init', function() {
            new WP_AC_WC_Meta_Fields();
        });
    }   

    // Initialize all hooks for the plugin (you may add more hooks to extend functionality)
    public function run() {
        
        $this->register_admin_hooks();
    }

    private function register_admin_hooks() {
        // Load the admin page
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );

        // Save handler
        add_action( 'admin_post_save_bearer_key', [ $this, 'save_bearer_key' ] );
    }

    /**
     * Add “Agentic Commerce” page to WP Admin sidebar
     */
    public function add_admin_page() {
        add_menu_page(
            'Agentic Commerce',
            'Agentic Commerce',
            'manage_options',
            'agentic-commerce',
            [ $this, 'render_admin_page' ],
            'dashicons-lock',
            80
        );
    }

    /**
     * Display the bearer key form
     */
    public function render_admin_page() {
        $bearer_key = get_option( 'agentic_commerce_bearer_key', '' );
        ?>
        <div class="wrap">
            <h1>WP Agentic Commerce</h1>
            <p>Enter a Bearer key to use for API authentication. You’ll need to include this key in the Authorization header when testing with Postman.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'save_bearer_key_action', 'save_bearer_key_nonce' ); ?>
                <input type="hidden" name="action" value="save_bearer_key">

                <table class="form-table">
                    <tr>
                        <th><label for="bearer_key">Bearer Key</label></th>
                        <td>
                            <input type="text" name="bearer_key" id="bearer_key" value="<?php echo esc_attr( $bearer_key ); ?>" class="regular-text" placeholder="Enter your bearer key here">
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Bearer Key' ); ?>
            </form>

            <?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) : ?>
                <div class="updated notice notice-success is-dismissible">
                    <p><strong>Bearer key saved successfully!</strong></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save the bearer key to database
     */
    public function save_bearer_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'save_bearer_key_action', 'save_bearer_key_nonce' );

        $bearer_key = sanitize_text_field( $_POST['bearer_key'] ?? '' );
        update_option( 'agentic_commerce_bearer_key', $bearer_key );

        wp_redirect( admin_url( 'admin.php?page=agentic-commerce&updated=true' ) );
        exit;
    }
}
