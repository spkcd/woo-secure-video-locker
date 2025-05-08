<?php
/**
 * Secure Video Locker - Test File
 * Place this in your WordPress root directory
 */

// Relative path to wp-load.php
require_once('wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Unauthorized access');
}

echo '<html><head><title>WSVL Test</title>';
echo '<style>
body { font-family: sans-serif; line-height: 1.5; padding: 20px; }
pre { background: #f0f0f0; padding: 10px; overflow: auto; }
.section { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 20px; }
h2 { margin-top: 30px; }
</style>';
echo '</head><body>';
echo '<h1>Secure Video Locker - Testing Tool</h1>';

// Test video slug
$test_slug = isset($_GET['slug']) ? sanitize_text_field($_GET['slug']) : 'twoblue1';

// 1. Check rewrite rules
echo '<div class="section">';
echo '<h2>Rewrite Rules</h2>';
echo '<p>Testing if WordPress rewrite rules are working properly for video slugs.</p>';

global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();

echo '<p>Rules matching "secure-videos" or "wsvl_video":</p>';
echo '<pre>';
$found = 0;
foreach ($rules as $pattern => $query) {
    if (strpos($pattern, 'secure-videos') !== false || strpos($query, 'wsvl_video') !== false) {
        echo htmlspecialchars("[$pattern] => [$query]") . "\n";
        $found++;
    }
}
if ($found === 0) {
    echo "No matching rules found!";
}
echo '</pre>';

// Try to flush rules
echo '<p><a href="?action=flush_rules&slug=' . esc_attr($test_slug) . '">Flush Rewrite Rules</a></p>';
if (isset($_GET['action']) && $_GET['action'] === 'flush_rules') {
    flush_rewrite_rules();
    echo '<p style="color:green">Rewrite rules flushed!</p>';
}
echo '</div>';

// 2. Check video file 
echo '<div class="section">';
echo '<h2>Video File Check</h2>';
echo '<p>Checking for video file with slug: <strong>' . esc_html($test_slug) . '</strong></p>';

// Check in upload directory
$upload_dir = wp_upload_dir();
$video_dirs = [
    'Private Videos' => WP_CONTENT_DIR . '/private-videos/',
    'Secure Videos Upload' => $upload_dir['basedir'] . '/secure-videos/'
];

foreach ($video_dirs as $label => $dir) {
    echo '<h3>' . esc_html($label) . '</h3>';
    echo '<p>Directory: ' . esc_html($dir) . ' - ';
    echo (is_dir($dir) ? 'Exists' : 'Missing') . '</p>';
    
    if (is_dir($dir)) {
        $files = scandir($dir);
        echo '<p>Files:</p><ul>';
        $found_matching = false;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $is_match = (stripos($file, $test_slug) === 0);
            if ($is_match) $found_matching = true;
            
            echo '<li>' . esc_html($file);
            if ($is_match) {
                echo ' - <strong>MATCH!</strong>';
                echo ' (' . (is_readable($dir . $file) ? 'Readable' : 'Not readable') . ')';
            }
            echo '</li>';
        }
        
        if (!$found_matching) {
            echo '<li>No matching files for slug: ' . esc_html($test_slug) . '</li>';
        }
        
        echo '</ul>';
    }
}

// Check in database
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} 
    WHERE meta_key = %s 
    AND LOWER(meta_value) = LOWER(%s)
    LIMIT 1",
    '_video_slug',
    $test_slug
));

echo '<h3>Database Check</h3>';
echo '<p>Product with video slug: ' . ($product_id ? 'Found (ID: ' . $product_id . ')' : 'Not found') . '</p>';

if ($product_id) {
    $video_file = get_post_meta($product_id, '_video_file', true);
    echo '<p>Video filename from meta: ' . ($video_file ? esc_html($video_file) : 'Not set') . '</p>';
    
    if ($video_file) {
        $full_path = WP_CONTENT_DIR . '/private-videos/' . $video_file;
        echo '<p>Full path: ' . esc_html($full_path) . ' - ';
        echo (file_exists($full_path) ? 'File exists' : 'File missing') . '</p>';
    }
}
echo '</div>';

// 3. Token generation
echo '<div class="section">';
echo '<h2>Token Generation</h2>';
echo '<p>Testing URL generation for slug: <strong>' . esc_html($test_slug) . '</strong></p>';

try {
    if (class_exists('WSVL\Security\VideoStreamer')) {
        $url = \WSVL\Security\VideoStreamer::generate_signed_url($test_slug);
        echo '<p>Generated URL: <a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></p>';
        
        // Parse URL parts
        $parts = parse_url($url);
        parse_str($parts['query'], $query_params);
        
        echo '<p>URL Components:</p>';
        echo '<ul>';
        echo '<li>Path: ' . esc_html($parts['path']) . '</li>';
        echo '<li>Token: ' . esc_html($query_params['token']) . '</li>';
        echo '<li>Nonce: ' . esc_html($query_params['_wpnonce']) . '</li>';
        echo '<li>Session ID: ' . esc_html($query_params['_sid']) . '</li>';
        echo '</ul>';
    } else {
        echo '<p style="color:red">VideoStreamer class not found!</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red">Error: ' . esc_html($e->getMessage()) . '</p>';
}
echo '</div>';

// 4. CORS and Headers Test
echo '<div class="section">';
echo '<h2>CORS and Headers Test</h2>';
echo '<p>Testing server CORS settings:</p>';

$test_url = site_url('/secure-videos/' . $test_slug . '/');
echo '<pre id="cors-test">Testing CORS for ' . esc_html($test_url) . '...</pre>';

echo '<script>
fetch("' . esc_url($test_url) . '", {
    method: "OPTIONS",
    headers: {
        "Origin": "' . esc_url(site_url()) . '"
    }
})
.then(response => {
    const el = document.getElementById("cors-test");
    
    el.innerHTML = "CORS Test Response:\\n";
    el.innerHTML += "Status: " + response.status + " " + response.statusText + "\\n\\n";
    el.innerHTML += "Headers:\\n";
    
    response.headers.forEach((value, name) => {
        el.innerHTML += name + ": " + value + "\\n";
    });
})
.catch(error => {
    document.getElementById("cors-test").innerHTML = "Error: " + error.message;
});
</script>';
echo '</div>';

// Add other video slugs to test
echo '<div class="section">';
echo '<h2>Try Another Slug</h2>';
echo '<form method="get">';
echo '<input type="text" name="slug" value="' . esc_attr($test_slug) . '" placeholder="Video slug">';
echo '<button type="submit">Test</button>';
echo '</form>';
echo '</div>';

echo '</body></html>'; 