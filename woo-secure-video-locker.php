<?php
/**
 * Plugin Name: Woo Secure Video Locker
 * Plugin URI: https://yourwebsite.com/woo-secure-video-locker
 * Description: Securely deliver video content to WooCommerce customers with temporary, signed URLs and automatic refresh.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: woo-secure-video-locker
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WSVL_VERSION', '1.0.0');
define('WSVL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSVL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSVL_PRIVATE_VIDEOS_DIR', WP_CONTENT_DIR . '/private-videos/');

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Woo Secure Video Locker requires WooCommerce to be installed and active.', 'woo-secure-video-locker'); ?></p>
        </div>
        <?php
    });
    return;
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'WSVL\\';
    $base_dir = WSVL_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function wsvl_init() {
    // Create necessary directories
    if (!file_exists(WSVL_PRIVATE_VIDEOS_DIR)) {
        wp_mkdir_p(WSVL_PRIVATE_VIDEOS_DIR);
    }

    // Create .htaccess to protect video directory
    $htaccess_content = "deny from all\n";
    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . '.htaccess', $htaccess_content);

    // Load text domain
    load_plugin_textdomain('woo-secure-video-locker', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize plugin components
    new \WSVL\Admin\ProductVideoManager();
    new \WSVL\Frontend\VideoDashboard();
    new \WSVL\Security\VideoStreamer();
    
    // Register scripts and styles
    add_action('wp_enqueue_scripts', 'wsvl_register_assets');
}
add_action('plugins_loaded', 'wsvl_init');

// Register and enqueue scripts and styles
function wsvl_register_assets() {
    wp_register_script(
        'wsvl-video-protection',
        WSVL_PLUGIN_URL . 'assets/js/video-protection.js',
        array(),
        WSVL_VERSION,
        true
    );
    
    wp_register_style(
        'wsvl-video-protection',
        WSVL_PLUGIN_URL . 'assets/css/video-protection.css',
        array(),
        WSVL_VERSION
    );
    
    // Enqueue them on video dashboard page
    if (is_page('video-dashboard') || isset($_GET['wsvl_video'])) {
        wp_enqueue_script('wsvl-video-protection');
        wp_enqueue_style('wsvl-video-protection');
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary directories
    if (!file_exists(WSVL_PRIVATE_VIDEOS_DIR)) {
        wp_mkdir_p(WSVL_PRIVATE_VIDEOS_DIR);
    }

    // Create .htaccess to protect video directory
    $htaccess_content = "deny from all\n";
    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . '.htaccess', $htaccess_content);

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
}); 