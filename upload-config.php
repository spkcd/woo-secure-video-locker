<?php
// Increase PHP upload limits
@ini_set('upload_max_filesize', '2G');
@ini_set('post_max_size', '2G');
@ini_set('memory_limit', '2G');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');

// Add to WordPress
add_filter('upload_size_limit', function($size) {
    return 2 * 1024 * 1024 * 1024; // 2GB
});

// Add to .htaccess if possible
function wsvl_add_upload_limits_to_htaccess() {
    $htaccess_file = ABSPATH . '.htaccess';
    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        $upload_limits = "
# BEGIN WSVL Upload Limits
php_value upload_max_filesize 2G
php_value post_max_size 2G
php_value memory_limit 2G
php_value max_execution_time 300
php_value max_input_time 300
# END WSVL Upload Limits
";
        if (strpos($htaccess_content, '# BEGIN WSVL Upload Limits') === false) {
            file_put_contents($htaccess_file, $upload_limits . "\n" . $htaccess_content);
        }
    }
}
add_action('admin_init', 'wsvl_add_upload_limits_to_htaccess'); 