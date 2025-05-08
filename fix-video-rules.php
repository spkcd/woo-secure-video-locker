<?php
/**
 * Fix Video Rewrite Rules
 * This script adds the missing rewrite rule for the Secure Video Locker
 */

// Load WordPress
require_once('wp-load.php');

// Security check - only allow admins to run this
if (!current_user_can('manage_options')) {
    die('You need to be an administrator to run this script.');
}

echo '<h1>Fixing Secure Video Locker Rewrite Rules</h1>';

// 1. Add the missing rewrite rule
echo '<p>Adding rewrite rule for /secure-videos/...</p>';

add_rewrite_rule(
    '^secure-videos/([^/]+)/?$',
    'index.php?wsvl_video=$matches[1]',
    'top'
);

// 2. Register the query var (just in case)
echo '<p>Registering wsvl_video query var...</p>';
function wsvl_fix_query_vars($vars) {
    $vars[] = 'wsvl_video';
    return $vars;
}
add_filter('query_vars', 'wsvl_fix_query_vars');

// 3. Force-delete the rewrite rules option to ensure a clean slate
echo '<p>Deleting old rewrite rules from database...</p>';
delete_option('rewrite_rules');
delete_option('wsvl_rewrite_rules_flushed');

// 4. Flush the rewrite rules
echo '<p>Flushing rewrite rules...</p>';
flush_rewrite_rules(true);

// 5. Test if the rule is now present
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();
$found = false;

echo '<p>Checking if rule was added successfully:</p>';
echo '<pre>';
foreach ($rules as $pattern => $query) {
    if (strpos($pattern, 'secure-videos') !== false) {
        echo "FOUND: [$pattern] => [$query]\n";
        $found = true;
    }
}

if (!$found) {
    echo "ERROR: Rule not found in rewrite rules!\n";
} else {
    echo "SUCCESS: Rule added successfully!\n";
}
echo '</pre>';

// 6. Provide a test link
$test_url = site_url('/secure-videos/twoblue1/');
echo '<p>You can test the rule by visiting: <a href="' . esc_url($test_url) . '?token=test" target="_blank">' . esc_html($test_url) . '?token=test</a></p>';

// 7. Add a link to permanently fix in the plugin
echo '<h2>Permanent Fix</h2>';
echo '<p>To ensure this rule is always added when the plugin is activated, you should also modify your plugin code:</p>';
echo '<pre>
// Add this in woo-secure-video-locker.php where the other rewrite rules are defined:

function wsvl_add_rewrite_rules() {
    // Add rewrite rule for video requests
    add_rewrite_rule(
        \'^secure-videos/([^/]+)/?$\',
        \'index.php?wsvl_video=$matches[1]\',
        \'top\'
    );
    
    // Flush rewrite rules only if they haven\'t been flushed yet
    if (get_option(\'wsvl_rewrite_rules_flushed\') != WSVL_VERSION) {
        flush_rewrite_rules();
        update_option(\'wsvl_rewrite_rules_flushed\', WSVL_VERSION);
    }
}
add_action(\'init\', \'wsvl_add_rewrite_rules\', 10);
</pre>';

echo '<p><strong>Done!</strong> Your video should now play correctly. If you still have issues, please check your server error logs for any messages from "VideoStreamer" or "WSVL".</p>';

// Add a button to go back to the video
echo '<p><a href="' . esc_url(site_url('/my-account/secure-videos/')) . '" style="padding: 10px 15px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px;">Return to Videos</a></p>'; 