<?php
/**
 * Secure Video Locker for WooCommerce - Performance Diagnostic Tool
 * 
 * Usage: Copy this file to your WordPress root directory and access it via:
 * https://your-site.com/wsvl-performance-check.php
 * 
 * IMPORTANT: Delete this file after use, as it contains diagnostic information
 * that should not be exposed on a production server.
 */

// Basic security check - use a password to protect this script
$access_password = 'wsvl-diagnostic-2025'; // Change this password!
$password_provided = isset($_GET['password']) ? $_GET['password'] : '';

if ($password_provided !== $access_password) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    echo '<p>Please provide the correct password to access this diagnostic tool.</p>';
    echo '<p>Usage: wsvl-performance-check.php?password=your-password</p>';
    exit;
}

// Set headers for plain text output
header('Content-Type: text/plain');

echo "===================================================================\n";
echo "WSVL PERFORMANCE DIAGNOSTIC TOOL\n";
echo "===================================================================\n\n";

echo "Running diagnostics on " . date('Y-m-d H:i:s') . "\n\n";

// Check PHP version and configuration
echo "PHP CONFIGURATION\n";
echo "-------------------------------------------------------------------\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds\n";
echo "Output Buffering: " . (ini_get('output_buffering') ? 'Enabled' : 'Disabled') . "\n";
echo "Zlib Compression: " . (ini_get('zlib.output_compression') ? 'Enabled' : 'Disabled') . "\n";
echo "Implicit Flush: " . (ini_get('implicit_flush') ? 'Enabled' : 'Disabled') . "\n\n";

// Check WordPress & WooCommerce environment
if (file_exists('./wp-load.php')) {
    require_once('./wp-load.php');
    
    echo "WORDPRESS CONFIGURATION\n";
    echo "-------------------------------------------------------------------\n";
    echo "WordPress Version: " . get_bloginfo('version') . "\n";
    
    if (function_exists('WC')) {
        echo "WooCommerce Version: " . WC()->version . "\n";
    } else {
        echo "WooCommerce: Not activated\n";
    }
    
    echo "WordPress Memory Limit: " . WP_MEMORY_LIMIT . "\n";
    echo "WordPress Debug Mode: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "\n";
} else {
    echo "WARNING: Could not load WordPress - script may not be in the correct location.\n\n";
}

// Check server environment
echo "SERVER ENVIRONMENT\n";
echo "-------------------------------------------------------------------\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Server OS: " . PHP_OS . "\n";
echo "Server IP: " . $_SERVER['SERVER_ADDR'] . "\n";
echo "Disk Free Space: " . formatBytes(disk_free_space('/')) . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

// Check file system permissions
echo "FILE SYSTEM CHECKS\n";
echo "-------------------------------------------------------------------\n";

$wp_content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__FILE__);
$private_videos_dir = $wp_content_dir . '/private-videos/';

echo "WP Content Directory: " . $wp_content_dir . "\n";
echo "Private Videos Directory: " . $private_videos_dir . "\n";

if (file_exists($private_videos_dir)) {
    echo "Private Videos Directory Exists: Yes\n";
    echo "Private Videos Directory Permissions: " . substr(sprintf('%o', fileperms($private_videos_dir)), -4) . "\n";
    echo "Private Videos Directory Writable: " . (is_writable($private_videos_dir) ? 'Yes' : 'No') . "\n";
    
    // Check htaccess
    if (file_exists($private_videos_dir . '.htaccess')) {
        echo "Private Videos .htaccess Exists: Yes\n";
    } else {
        echo "Private Videos .htaccess Exists: No (Security risk!)\n";
    }
    
    // Count video files
    $video_count = 0;
    $total_size = 0;
    if ($handle = opendir($private_videos_dir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && !is_dir($private_videos_dir . $entry)) {
                $file_ext = pathinfo($entry, PATHINFO_EXTENSION);
                if (in_array(strtolower($file_ext), ['mp4', 'webm', 'ogv', 'mov', 'm4v'])) {
                    $video_count++;
                    $file_size = filesize($private_videos_dir . $entry);
                    $total_size += $file_size;
                }
            }
        }
        closedir($handle);
    }
    
    echo "Number of Video Files: " . $video_count . "\n";
    echo "Total Video Size: " . formatBytes($total_size) . "\n";
} else {
    echo "Private Videos Directory Exists: No\n";
}

echo "\n";

// Check server connection speed
echo "CONNECTION SPEED TEST\n";
echo "-------------------------------------------------------------------\n";
echo "Testing server response time...\n";

$start_time = microtime(true);
// Simulate a few requests 
for ($i = 0; $i < 5; $i++) {
    $ch = curl_init($_SERVER['REQUEST_URI'] . '?speed_test=' . $i . '&password=' . $password_provided);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
$end_time = microtime(true);
$response_time = ($end_time - $start_time) / 5;

echo "Average Response Time: " . round($response_time * 1000, 2) . " ms\n";
if ($response_time > 0.5) {
    echo "WARNING: Server response time is slow (>500ms). This may affect video streaming performance.\n";
}
echo "\n";

// Performance recommendations
echo "PERFORMANCE RECOMMENDATIONS\n";
echo "-------------------------------------------------------------------\n";

// Memory limit check
$memory_limit = ini_get('memory_limit');
$memory_limit_bytes = return_bytes($memory_limit);
if ($memory_limit_bytes < 256 * 1024 * 1024) {
    echo "- CRITICAL: Memory limit is too low. Increase to at least 256M (512M recommended).\n";
} else if ($memory_limit_bytes < 512 * 1024 * 1024) {
    echo "- RECOMMENDED: Consider increasing memory limit to 512M for better performance.\n";
}

// Execution time
$max_execution_time = ini_get('max_execution_time');
if ($max_execution_time < 300) {
    echo "- CRITICAL: Max execution time is too low. Increase to at least 300 seconds.\n";
}

// Output buffering
if (ini_get('output_buffering')) {
    echo "- RECOMMENDED: Disable output buffering for better streaming performance.\n";
}

// Zlib compression
if (ini_get('zlib.output_compression')) {
    echo "- RECOMMENDED: Disable zlib compression for better streaming performance.\n";
}

// Server software checks
if (isset($_SERVER['SERVER_SOFTWARE'])) {
    if (stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
        echo "- INFORMATION: Apache detected. Make sure mod_headers and mod_expires are enabled.\n";
    } else if (stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
        echo "- INFORMATION: Nginx detected. Check your nginx.conf for optimized video delivery.\n";
        echo "  Add: sendfile on; tcp_nopush on; client_max_body_size 2000m;\n";
    }
}

// WP debug mode
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo "- WARNING: Debug mode is enabled which may impact performance in production.\n";
}

// Disk space check
if (disk_free_space('/') < 10 * 1024 * 1024 * 1024) { // Less than 10GB
    echo "- WARNING: Less than 10GB of disk space available. Video uploads may fail.\n";
}

echo "\n";
echo "===================================================================\n";
echo "END OF REPORT\n";
echo "===================================================================\n";
echo "\nIMPORTANT: Delete this file after use for security reasons!\n";

// Helper functions
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch ($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
} 