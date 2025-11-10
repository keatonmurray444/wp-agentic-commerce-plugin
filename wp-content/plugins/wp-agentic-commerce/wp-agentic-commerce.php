<?php

/*
 * Plugin Name:       WP Agentic Commerce
 * Description:       This plugin is to handle API Endpoints to allow product display on ChatGPT
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      8.3
 * Author:            Coalition Technologies
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       my-basics-plugin
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define namespace
use WPAgenticCommerce\Core\WP_AC_Plugin;

// Define constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include vendor file for autoloading classes
require_once MY_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Initialize the plugin
 */

if (!class_exists('WPAgenticCommerce\Core\WP_AC_Plugin')) {
    die('Autoload failed: WP_AC_Plugin not found');
}

function my_plugin_run() {
    $plugin = new WP_AC_Plugin();
    $plugin->run();
}

add_action( 'plugins_loaded', 'my_plugin_run' );
