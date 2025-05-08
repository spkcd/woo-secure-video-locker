<?php
// Increase PHP upload limits
@ini_set('upload_max_filesize', '2G');
@ini_set('post_max_size', '2G');
@ini_set('memory_limit', '512M'); // Reduced from 2G to avoid excessive memory usage
@ini_set('max_execution_time', '600'); // Increased to 10 minutes for large uploads
@ini_set('max_input_time', '600');

// Optimize streaming performance
@ini_set('output_buffering', 'Off'); // Disable output buffering for streaming
@ini_set('zlib.output_compression', 'Off'); // Disable compression for streaming
@ini_set('implicit_flush', 'On'); // Enable implicit flushing

// Add to WordPress
add_filter('upload_size_limit', function($size) {
    return 2 * 1024 * 1024 * 1024; // 2GB
});

// Performance settings for WP
add_filter('wp_memory_limit', function() {
    return '256M'; // Reasonable memory limit for WordPress
});

// Set recommended timeout values for large file operations
function wsvl_set_streaming_timeout($timeout) {
    // Increase timeout for video operations
    if (isset($_GET['wsvl_video']) || isset($_REQUEST['action']) && $_REQUEST['action'] == 'wsvl_upload_chunk') {
        return 600; // 10 minutes
    }
    return $timeout;
}
add_filter('http_request_timeout', 'wsvl_set_streaming_timeout');

// Add to .htaccess if possible
function wsvl_add_upload_limits_to_htaccess() {
    $htaccess_file = ABSPATH . '.htaccess';
    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        $upload_limits = "
# BEGIN WSVL Upload Limits
php_value upload_max_filesize 2G
php_value post_max_size 2G
php_value memory_limit 512M
php_value max_execution_time 600
php_value max_input_time 600
php_value output_buffering Off
php_value zlib.output_compression Off
php_value implicit_flush On

# Performance tuning for large files
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType video/mp4 \"access plus 1 year\"
    ExpiresByType video/webm \"access plus 1 year\"
    ExpiresByType video/ogg \"access plus 1 year\"
</IfModule>

<IfModule mod_deflate.c>
    # Disable compression for video files
    SetEnvIfNoCase Request_URI \\.(?:mp4|webm|ogv|m4v)$ no-gzip dont-vary
</IfModule>
# END WSVL Upload Limits
";
        if (strpos($htaccess_content, '# BEGIN WSVL Upload Limits') === false) {
            file_put_contents($htaccess_file, $upload_limits . "\n" . $htaccess_content);
        } else {
            // Update existing upload limits
            $pattern = '/(# BEGIN WSVL Upload Limits)(.*?)(# END WSVL Upload Limits)/s';
            $htaccess_content = preg_replace($pattern, $upload_limits, $htaccess_content);
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
}
add_action('admin_init', 'wsvl_add_upload_limits_to_htaccess');

// Optimize video processing in backend
function wsvl_optimize_video_processing() {
    // Only apply these optimizations on video-related pages
    $is_video_page = isset($_GET['wsvl_video']) || 
                    (isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'wsvl_') === 0);
    
    if ($is_video_page) {
        // Disable unnecessary WordPress actions
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        
        // Disable heartbeat API for video pages
        wp_deregister_script('heartbeat');
        
        // Disable admin bar for better performance
        add_filter('show_admin_bar', '__return_false');
    }
}
add_action('init', 'wsvl_optimize_video_processing', 1); 