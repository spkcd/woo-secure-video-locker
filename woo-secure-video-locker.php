<?php
/**
 * Plugin Name: Secure Video Locker for WooCommerce
 * Plugin URI: https://yourwebsite.com/secure-video-locker-for-woocommerce
 * Description: Securely deliver video content to WooCommerce customers with temporary, signed URLs and automatic refresh.
 * Version: 1.1.0
 * Author: SPARKWEB Studio
 * Author URI: https://sparkwebstudio.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secure-video-locker-for-woocommerce
 * Domain Path: languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WSVL_VERSION', '1.1.0');
define('WSVL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSVL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSVL_PRIVATE_VIDEOS_DIR', trailingslashit(WP_CONTENT_DIR) . 'private-videos/');

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Secure Video Locker for WooCommerce requires WooCommerce to be installed and active.', 'secure-video-locker-for-woocommerce'); ?></p>
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

    // Create .htaccess to protect video directory but allow PHP access
    $htaccess_content = <<<EOT
# Deny direct access to files
<FilesMatch ".*">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Allow PHP to handle video requests
<FilesMatch "\.(mp4|webm|ogv)$">
    Order Allow,Deny
    Allow from all
    ForceType application/octet-stream
    Header set Content-Disposition "attachment"
</FilesMatch>

# Prevent script execution
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|phar|pl|py|jsp|asp|htm|html|shtml|sh|cgi)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Set proper MIME types
<IfModule mod_mime.c>
    AddType video/mp4 .mp4
    AddType video/webm .webm
    AddType video/ogg .ogv
</IfModule>

# Enable CORS for video streaming
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, HEAD, OPTIONS"
    Header set Access-Control-Allow-Headers "Range"
    Header set Access-Control-Expose-Headers "Accept-Ranges, Content-Length, Content-Range"
</IfModule>
EOT;

    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . '.htaccess', $htaccess_content);

    // Create empty index.php for additional security
    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . 'index.php', '<?php // Silence is golden');

    // Load text domain
    load_plugin_textdomain('secure-video-locker-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize plugin components
    new \WSVL\Admin\ProductVideoManager();
    new \WSVL\Frontend\VideoDashboard();
    new \WSVL\Security\VideoStreamer();
    
    // Register scripts and styles
    add_action('wp_enqueue_scripts', 'wsvl_register_assets');
}
add_action('plugins_loaded', 'wsvl_init');

// Add rewrite rules at the correct time
function wsvl_add_rewrite_rules() {
    // Add rewrite rule for video requests
    add_rewrite_rule(
        'wsvl_video=([^&]+)',
        'index.php?wsvl_video=$matches[1]',
        'top'
    );
    
    // Flush rewrite rules only if they haven't been flushed yet
    if (get_option('wsvl_rewrite_rules_flushed') != WSVL_VERSION) {
        flush_rewrite_rules();
        update_option('wsvl_rewrite_rules_flushed', WSVL_VERSION);
    }
}
add_action('init', 'wsvl_add_rewrite_rules', 10);

// Add query vars
function wsvl_add_query_vars($vars) {
    $vars[] = 'wsvl_video';
    return $vars;
}
add_filter('query_vars', 'wsvl_add_query_vars');

// Register and enqueue scripts and styles
function wsvl_register_assets() {
    // Register video protection script
    wp_register_script(
        'wsvl-video-protection',
        WSVL_PLUGIN_URL . 'assets/js/video-protection.js',
        array(),
        WSVL_VERSION,
        true
    );
    
    // Register global protection styles - these will be loaded everywhere
    wp_register_style(
        'wsvl-video-protection',
        WSVL_PLUGIN_URL . 'assets/css/video-protection.css',
        array(),
        WSVL_VERSION
    );
    
    // Load video protection styles globally
    wp_enqueue_style('wsvl-video-protection');
    
    // Enqueue scripts only on video dashboard page
    if (is_page('video-dashboard') || isset($_GET['wsvl_video'])) {
        wp_enqueue_script('wsvl-video-protection');
    }
}

/**
 * Plugin activation
 */
function wsvl_activate() {
    global $wp_version;

    // Check WP version
    if (version_compare($wp_version, '5.0', '<')) {
        deactivate_plugins(basename(__FILE__));
        wp_die('This plugin requires WordPress version 5.0 or higher.');
    }

    // Check WooCommerce
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and activated.');
    }

    // Create private videos directory and protect it
    if (!file_exists(WSVL_PRIVATE_VIDEOS_DIR)) {
        wp_mkdir_p(WSVL_PRIVATE_VIDEOS_DIR);
    }

    // Set proper permissions
    chmod(WSVL_PRIVATE_VIDEOS_DIR, 0755);

    // Create .htaccess for Apache servers to deny direct access
    $htaccess_content = <<<EOT
# Deny direct access to files
<FilesMatch ".*">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Deny directory listing
Options -Indexes
EOT;

    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . '.htaccess', $htaccess_content);

    // Create an empty index.php file to prevent directory listing on some servers
    file_put_contents(WSVL_PRIVATE_VIDEOS_DIR . 'index.php', '<?php // Silence is golden');

    // Add roles and capabilities
    wsvl_add_roles_and_capabilities();

    // Setup database tables if needed
    wsvl_setup_database();

    // Flush rewrite rules on activation
    flush_rewrite_rules();
}

// Activation hook
register_activation_hook(__FILE__, 'wsvl_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
}); 