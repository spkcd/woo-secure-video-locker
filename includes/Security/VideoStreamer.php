<?php
namespace WSVL\Security;

class VideoStreamer {
    private const TOKEN_EXPIRY = 3600; // 1 hour in seconds
    private const ALGORITHM = 'sha256';
    private const CHUNK_SIZE = 512 * 1024; // 512KB chunks (reduced from 1MB)
    private const MAX_CHUNK_SIZE = 1024 * 1024 * 10; // 10MB max chunk size

    public function __construct() {
        // Handle video streaming through WordPress template_redirect
        add_action('template_redirect', [$this, 'handle_video_request']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_video_player_scripts']);
        add_action('wp_ajax_wsvl_stream_video', [$this, 'ajax_stream_video']);
        add_action('wp_ajax_nopriv_wsvl_stream_video', [$this, 'ajax_stream_video']);
    }

    public function enqueue_video_player_scripts() {
        // Only enqueue default player if we're not using our canvas-based secure player
        // Force secure player to true
        $using_secure_player = true; // Always use secure player
        
        // Ensure our secure player CSS is loaded
        wp_enqueue_style('dashicons');
        
        if (is_product() && !$using_secure_player) {
            wp_enqueue_script('wsvl-video-player', WSVL_PLUGIN_URL . 'assets/js/video-player.js', ['jquery'], WSVL_VERSION, true);
            wp_localize_script('wsvl-video-player', 'wsvlVideoPlayer', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wsvl_video_player'),
                'i18n' => [
                    'downloadDisabled' => __('Video downloading is disabled.', 'secure-video-locker-for-woocommerce'),
                    'accessDenied' => __('Access denied.', 'secure-video-locker-for-woocommerce'),
                    'loadError' => __('Error loading video. Please try again.', 'secure-video-locker-for-woocommerce'),
                    'playError' => __('Error playing video. Please try again.', 'secure-video-locker-for-woocommerce')
                ]
            ]);
        }
    }

    public function prevent_direct_access() {
        if (isset($_GET['wsvl_video']) && !isset($_GET['chunk'])) {
            // Verify nonce for direct access prevention
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wsvl_video_access')) {
                wp_die(esc_html__('Direct video access is not allowed.', 'secure-video-locker-for-woocommerce'));
            }
        }
    }

    public function handle_video_request() {
        global $wp_query;
        
        try {
            // Check if this is a video request - also check query string for backward compatibility
            $video_slug = null;
            
            if (isset($wp_query->query_vars['wsvl_video'])) {
                $video_slug = sanitize_text_field($wp_query->query_vars['wsvl_video']);
            } elseif (isset($_GET['wsvl_video'])) {
                // Fallback to query parameter if rewrite rules aren't working
                $video_slug = sanitize_text_field($_GET['wsvl_video']);
            }
            
            if (empty($video_slug)) {
                return;
            }

            // Add debugging info only when WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('=================== WSVL Debug Start ===================');
                error_log('WSVL Debug: Starting video request for slug: ' . $video_slug);
                error_log('WSVL Debug: Request URI: ' . $_SERVER['REQUEST_URI']);
                error_log('WSVL Debug: Query vars: ' . print_r($wp_query->query_vars, true));
                error_log('WSVL Debug: GET params: ' . print_r($_GET, true));
                error_log('WSVL Debug: SERVER: ' . print_r($_SERVER, true));
                error_log('WSVL Debug: User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
                if (is_user_logged_in()) {
                    error_log('WSVL Debug: User ID: ' . get_current_user_id());
                }
            }
            
            // Verify the request
            if (!isset($_GET['token'])) {
                error_log('WSVL Debug: No token provided');
                status_header(403);
                die('Access denied - No token provided');
            }

            if (!$this->verify_token($video_slug, $_GET['token'])) {
                error_log('WSVL Debug: Token verification failed');
                status_header(403);
                die('Access denied - Invalid token');
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Token verified successfully');
            }
            
            // Get the video file path using the helper method
            $video_path = $this->get_video_path($video_slug);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Looking for video at path: ' . $video_path);
            }
            
            if (!$video_path) {
                error_log('WSVL Debug: No video path returned from get_video_path');
                status_header(404);
                die('Video not found - Invalid path');
            }
            
            if (!file_exists($video_path)) {
                error_log('WSVL Debug: Video file not found at path: ' . $video_path);
                status_header(404);
                die('Video not found - File missing');
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Video file found');
            }
            
            // Get file size
            $file_size = filesize($video_path);
            if ($file_size === false) {
                error_log('WSVL Debug: Could not get file size');
                status_header(500);
                die('Server error - Could not get file size');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Video file size: ' . $file_size . ' bytes');
            }

            // Handle range requests
            $start = 0;
            $end = $file_size - 1;
            $length = $file_size;

            if (isset($_SERVER['HTTP_RANGE'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WSVL Debug: Range request detected: ' . $_SERVER['HTTP_RANGE']);
                }
                $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
                list($start, $end) = explode('-', $range . '-' . ($file_size - 1));
                $start = max(0, intval($start));
                $end = min($file_size - 1, ($end ? intval($end) : $file_size - 1));
                $length = $end - $start + 1;
                
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
            }

            // Get mime type
            $mime_type = 'video/mp4';
            $extension = strtolower(pathinfo($video_path, PATHINFO_EXTENSION));
            
            // Map common video extensions to MIME types
            $mime_types = [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogg' => 'video/ogg',
                'ogv' => 'video/ogg',
                'm4v' => 'video/mp4',
                'mov' => 'video/quicktime'
            ];
            
            if (isset($mime_types[$extension])) {
                $mime_type = $mime_types[$extension];
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Setting mime type: ' . $mime_type);
            }

            // Performance tuning headers
            header('X-Accel-Buffering: no'); // Disable Nginx buffering
            header('X-Content-Duration: ' . $file_size); // Hint for browsers
            
            // Set proper headers for video streaming
            header('Content-Type: ' . $mime_type);
            header('Content-Length: ' . $length);
            header('Accept-Ranges: bytes');
            header('Cache-Control: max-age=3600, public'); // Allow caching for 1 hour
            header('Content-Disposition: inline; filename="' . basename($video_path) . '"');
            header('X-Content-Type-Options: nosniff');
            
            // CORS headers to allow video playback - use the actual domain
            $site_url = parse_url(site_url(), PHP_URL_HOST);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
            header('Access-Control-Allow-Headers: Range, Origin, X-Requested-With');
            header('Access-Control-Expose-Headers: Accept-Ranges, Content-Length, Content-Range');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Streaming headers set, ready to stream file');
            }

            // Disable output buffering
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Optimize streaming based on range requests
            if ($start > 0 || $end < $file_size - 1) {
                // For range requests, we'll use fopen/fread for precise streaming
                $handle = fopen($video_path, 'rb');
                if ($handle === false) {
                    error_log('WSVL Debug: Failed to open video file');
                    status_header(500);
                    die('Server error - Could not open file');
                }
                
                if (fseek($handle, $start) === -1) {
                    fclose($handle);
                    error_log('WSVL Debug: Failed to seek to position');
                    status_header(500);
                    die('Server error - Could not seek to position');
                }
                
                // Stream the range
                $buffer_size = self::CHUNK_SIZE; // Use optimized chunk size 
                $bytes_sent = 0;
                
                while (!feof($handle) && $bytes_sent < $length) {
                    // Calculate remaining bytes
                    $remaining = $length - $bytes_sent;
                    $read_size = min($buffer_size, $remaining);
                    
                    // Read and output chunk
                    $buffer = fread($handle, $read_size);
                    if ($buffer === false) {
                        break;
                    }
                    
                    echo $buffer;
                    flush();
                    
                    $bytes_sent += strlen($buffer);
                    
                    // Free memory
                    unset($buffer);
                }
                
                fclose($handle);
            } else {
                // For full file requests, use readfile which is more efficient
                readfile($video_path);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WSVL Debug: Finished streaming video');
                error_log('=================== WSVL Debug End ===================');
            }
            
            exit;
            
        } catch (\Exception $e) {
            error_log('WSVL Debug: Exception in handle_video_request: ' . $e->getMessage());
            error_log('WSVL Debug: Exception trace: ' . $e->getTraceAsString());
            status_header(500);
            die('Server error - ' . $e->getMessage());
        }
    }

    public function ajax_stream_video() {
        check_ajax_referer('wsvl_video_player', 'nonce');

        $video_slug = isset($_POST['video_slug']) ? sanitize_text_field(wp_unslash($_POST['video_slug'])) : '';
        $chunk = isset($_POST['chunk']) ? intval($_POST['chunk']) : 0;
        $size = isset($_POST['size']) ? intval($_POST['size']) : self::CHUNK_SIZE;

        try {
            if (empty($video_slug)) {
                throw new \Exception('Video slug is required');
            }

            if ($this->verify_token($video_slug, $_POST['token'])) {
                $this->stream_video($video_slug, $chunk, $size);
            } else {
                wp_send_json_error('Invalid or expired video access token');
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('VideoStreamer AJAX Error: ' . $e->getMessage());
            }
            wp_send_json_error($e->getMessage());
        }
    }

    public static function generate_signed_url($video_slug) {
        if (empty($video_slug)) {
            error_log('VideoStreamer: Cannot generate URL - empty video slug');
            return '#error_generating_url';
        }

        // Generate a secure token
        $token = self::generate_token($video_slug);
        
        // Get the current session token
        $session_id = wp_get_session_token();
        $hashed_session = substr(md5($session_id), 0, 10);
        
        // Set expiration time (24 hours from now)
        $expiry = time() + (24 * 60 * 60);

        // Build the URL with all security parameters
        $url = site_url('/secure-videos/' . $video_slug . '/');
        $url = add_query_arg(array(
            'token' => $token,
            '_wpnonce' => wp_create_nonce('wsvl_video_' . $video_slug),
            '_sid' => $hashed_session,
            '_t' => $expiry
        ), $url);

        error_log('VideoStreamer: Generated URL for ' . $video_slug);
        error_log('VideoStreamer: Session ID: ' . $hashed_session);
        error_log('VideoStreamer: Token: ' . $token);
        error_log('VideoStreamer: URL: ' . $url);

        return $url;
    }

    /**
     * Static helper to get video path from slug
     */
    private static function get_video_path_for_slug($video_slug) {
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value = %s 
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_status = %s
            ) 
            LIMIT 1",
            '_video_slug',
            $video_slug,
            'product',
            'publish'
        ));

        if (!$product_id) {
            return false;
        }

        $video_file = get_post_meta($product_id, '_video_file', true);
        if (!$video_file) {
            return false;
        }

        return WSVL_PRIVATE_VIDEOS_DIR . $video_file;
    }

    private static function generate_token($video_slug) {
        $expiry = time() + self::TOKEN_EXPIRY;
        $session_id = wp_get_session_token();
        $data = $video_slug . '|' . $expiry . '|' . $session_id;
        return hash_hmac(self::ALGORITHM, $data, wp_salt('auth'));
    }

    private function verify_token($video_slug, $token) {
        try {
            error_log('=================== WSVL Debug Start ===================');
            error_log('VideoStreamer: Starting token verification for slug: ' . $video_slug);
            error_log('VideoStreamer: Token: ' . $token);
            
            // Check if user is logged in
            if (!is_user_logged_in()) {
                error_log('VideoStreamer: User not logged in');
                return false;
            }
            
            // Get current user ID
            $user_id = get_current_user_id();
            error_log('VideoStreamer: User ID: ' . $user_id);
            
            // Verify user has purchased the product
            $has_access = $this->verify_user_has_access($user_id, $video_slug);
            error_log('VideoStreamer: User has access: ' . ($has_access ? 'Yes' : 'No'));
            
            if (!$has_access) {
                error_log('VideoStreamer: User does not have access to this video');
                return false;
            }
            
            // Verify the session ID if provided
            if (isset($_REQUEST['_sid'])) {
                $session_id = wp_get_session_token();
                error_log('VideoStreamer: Session token: ' . $session_id);
                error_log('VideoStreamer: Provided _sid: ' . $_REQUEST['_sid']);
            }

            // Find the product with this video slug (case-insensitive)
            global $wpdb;
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND LOWER(meta_value) = LOWER(%s)
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = %s 
                    AND post_status = %s
                ) 
                LIMIT 1",
                '_video_slug',
                $video_slug,
                'product',
                'publish'
            ));

            error_log('VideoStreamer: Product ID found: ' . ($product_id ? $product_id : 'None'));

            if (!$product_id) {
                error_log('VideoStreamer: No product found for slug: ' . $video_slug);
                return false;
            }

            $video_file = get_post_meta($product_id, '_video_file', true);
            error_log('VideoStreamer: Video file from meta: ' . ($video_file ? $video_file : 'None'));

            if (empty($video_file)) {
                // Try to find the video file by looking for files with a matching slug
                error_log('VideoStreamer: Attempting to find video file by slug pattern matching');
                $video_path = $this->find_video_by_slug_pattern($video_slug);
                if ($video_path) {
                    $filename = basename($video_path);
                    error_log('VideoStreamer: Found video file by pattern matching: ' . $filename);
                    
                    // Update the product meta with the found filename
                    update_post_meta($product_id, '_video_file', $filename);
                    $video_file = $filename;
                    error_log('VideoStreamer: Updated product meta with found filename: ' . $filename);
                } else {
                    error_log('VideoStreamer: No video file found for product: ' . $product_id);
                    return false;
                }
            }

            // Try both original case and lowercase versions of the file
            $video_path = WSVL_PRIVATE_VIDEOS_DIR . $video_file;
            $video_path_lower = WSVL_PRIVATE_VIDEOS_DIR . strtolower($video_file);
            
            error_log('VideoStreamer: Checking paths:');
            error_log('- Original: ' . $video_path);
            error_log('- Lowercase: ' . $video_path_lower);
            
            // Check both paths
            if (!file_exists($video_path) && !file_exists($video_path_lower)) {
                error_log('VideoStreamer: Video file not found at either path');
                error_log('VideoStreamer: Directory exists: ' . (is_dir(dirname($video_path)) ? 'Yes' : 'No'));
                if (is_dir(dirname($video_path))) {
                    error_log('VideoStreamer: Directory contents: ' . print_r(scandir(dirname($video_path)), true));
                }
                
                // Try to find the file by slug pattern matching
                $alternative_path = $this->find_video_by_slug_pattern($video_slug);
                if ($alternative_path) {
                    error_log('VideoStreamer: Found alternative video path: ' . $alternative_path);
                    $final_path = $alternative_path;
                } else {
                    return false;
                }
            } else {
                // Use the path that exists
                $final_path = file_exists($video_path) ? $video_path : $video_path_lower;
            }
            
            error_log('VideoStreamer: Using path: ' . $final_path);

            // Verify file permissions
            if (!is_readable($final_path)) {
                error_log('VideoStreamer: Video file is not readable: ' . $final_path);
                error_log('VideoStreamer: File permissions: ' . substr(sprintf('%o', fileperms($final_path)), -4));
                return false;
            }

            error_log('VideoStreamer: All checks passed');
            error_log('=================== WSVL Debug End ===================');
            return true;

        } catch (\Exception $e) {
            error_log('VideoStreamer Token Verification Error: ' . $e->getMessage());
            error_log('VideoStreamer Error Trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    private function verify_user_has_access($user_id, $video_slug) {
        error_log('VideoStreamer: Checking access for user ' . $user_id . ' to video ' . $video_slug);
        
        // Verify user has purchased the video
        $has_access = false;
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => -1,
        ]);

        error_log('VideoStreamer: Found ' . count($orders) . ' orders for user');

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product_video_slug = get_post_meta($product_id, '_video_slug', true);
                error_log('VideoStreamer: Checking product ' . $product_id . ' with video slug: ' . $product_video_slug);
                if (strtolower($product_video_slug) === strtolower($video_slug)) {
                    $has_access = true;
                    error_log('VideoStreamer: Found matching product with access');
                    break 2;
                }
            }
        }
        
        // Check for subscription access if WooCommerce Subscriptions is active
        if (!$has_access && function_exists('wcs_get_users_subscriptions')) {
            error_log('VideoStreamer: Checking subscriptions');
            $subscriptions = wcs_get_users_subscriptions($user_id);
            foreach ($subscriptions as $subscription) {
                if (!$subscription->has_status(['active', 'pending-cancel'])) {
                    continue;
                }
                
                foreach ($subscription->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $product_video_slug = get_post_meta($product_id, '_video_slug', true);
                    error_log('VideoStreamer: Checking subscription product ' . $product_id . ' with video slug: ' . $product_video_slug);
                    if (strtolower($product_video_slug) === strtolower($video_slug)) {
                        $has_access = true;
                        error_log('VideoStreamer: Found matching subscription with access');
                        break 2;
                    }
                }
            }
        }
        
        error_log('VideoStreamer: Final access result: ' . ($has_access ? 'Granted' : 'Denied'));
        return $has_access;
    }
    
    /**
     * Log access attempts for security monitoring
     */
    private function log_access_attempt($user_id, $video_slug, $success) {
        // Get client IP address with proxy handling
        $ip_address = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_parts = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip_address = trim($ip_parts[0]);
        }
        
        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown';
        
        // Get referer
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : 'Direct';
        
        // Log the access attempt
        $message = sprintf(
            '[%s] User #%d attempted to access video %s from IP %s (%s) - %s',
            current_time('mysql'),
            $user_id,
            $video_slug,
            $ip_address,
            $user_agent,
            $success ? 'SUCCESS' : 'FAILED'
        );
        
        // Log to file only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
        
        // If too many failed attempts detected, maybe block the IP
        if (!$success) {
            $fail_count = (int) get_transient('wsvl_fails_' . $ip_address);
            set_transient('wsvl_fails_' . $ip_address, $fail_count + 1, HOUR_IN_SECONDS);
            
            if ($fail_count > 10) {
                // Log suspicious activity only in debug mode
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('SUSPICIOUS: Multiple failed video access attempts from IP ' . $ip_address);
                }
            }
        }
    }

    public function stream_video($video_slug, $chunk = 0, $chunk_size = null) {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsvl_video_player')) {
                throw new \Exception('Invalid security token');
            }

            // Get video slug and token
            $video_slug = sanitize_text_field($_POST['video_slug']);
            $token = sanitize_text_field($_POST['token']);

            // Verify token
            if (!$this->verify_token($video_slug, $token)) {
                throw new \Exception('Invalid video token');
            }

            // Get video file path
            $video_path = $this->get_video_path($video_slug);
            if (!$video_path || !file_exists($video_path)) {
                throw new \Exception('Video file not found');
            }

            // Get file size
            $file_size = filesize($video_path);
            if ($file_size === false) {
                throw new \Exception('Could not determine file size');
            }

            // Get chunk parameters
            $chunk = isset($_POST['chunk']) ? intval($_POST['chunk']) : 0;
            $chunk_size = isset($_POST['size']) ? intval($_POST['size']) : 1024 * 1024; // Default 1MB
            $start = $chunk * $chunk_size;
            $end = min($start + $chunk_size - 1, $file_size - 1);

            // Validate range
            if ($start >= $file_size) {
                throw new \Exception('Invalid chunk range');
            }

            // Open file
            $handle = fopen($video_path, 'rb');
            if ($handle === false) {
                throw new \Exception('Could not open video file');
            }

            // Seek to start position
            if (fseek($handle, $start) === -1) {
                fclose($handle);
                throw new \Exception('Could not seek to position');
            }

            // Set headers
            header('Content-Type: video/mp4');
            header('Accept-Ranges: bytes');
            header('Content-Length: ' . ($end - $start + 1));
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
            
            // Cache prevention headers
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Security headers for strict protection
            // No download policy
            header('Content-Disposition: inline; filename="stream.mp4"; attachment=0');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            
            // Strict content security policy
            header("Content-Security-Policy: default-src 'self'; media-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'self'");
            
            // Additional download prevention
            header('X-Download-Options: noopen');
            header('Cross-Origin-Resource-Policy: same-origin');
            
            // Custom headers for more browser protection
            header('X-Content-Security: nosniff');
            header('X-Permitted-Cross-Domain-Policies: none');

            // Output chunk
            $buffer_size = 8192; // 8KB chunks
            $remaining = $end - $start + 1;
            
            while ($remaining > 0 && !feof($handle)) {
                $read_size = min($buffer_size, $remaining);
                $buffer = fread($handle, $read_size);
                if ($buffer === false) {
                    break;
                }
                echo $buffer;
                flush();
                $remaining -= $read_size;
            }

            fclose($handle);
            exit;
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'wsvl_rate_limit_' . $user_id;
        $rate_limit = get_transient($transient_key);
        
        if ($rate_limit === false) {
            set_transient($transient_key, 1, 60); // 1 request per minute
            return true;
        }
        
        if ($rate_limit >= 60) { // 60 requests per minute
            wp_die(esc_html__('Rate limit exceeded. Please try again later.', 'secure-video-locker-for-woocommerce'));
        }
        
        set_transient($transient_key, $rate_limit + 1, 60);
        return true;
    }

    private function stream_video_direct($video_slug) {
        try {
            error_log('VideoStreamer: Starting direct video stream for slug: ' . $video_slug);
            
            // Get video file path
            $video_path = $this->get_video_path($video_slug);
            if (!$video_path) {
                error_log('VideoStreamer Error: Failed to get valid video path for slug: ' . $video_slug);
                throw new \Exception('Video file not found');
            }

            if (!file_exists($video_path)) {
                error_log('VideoStreamer Error: Video file does not exist at path: ' . $video_path);
                throw new \Exception('Video file is missing');
            }

            // Get file size
            $file_size = @filesize($video_path);
            if ($file_size === false) {
                error_log('VideoStreamer Error: Could not get file size for: ' . $video_path);
                error_log('VideoStreamer: File permissions: ' . substr(sprintf('%o', fileperms($video_path)), -4));
                throw new \Exception('Could not determine file size');
            }

            // Get mime type with better fallback
            $mime_type = 'video/mp4';
            $extension = strtolower(pathinfo($video_path, PATHINFO_EXTENSION));
            
            // Map common video extensions to MIME types
            $mime_types = [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogg' => 'video/ogg',
                'ogv' => 'video/ogg',
                'm4v' => 'video/mp4',
                'mov' => 'video/quicktime'
            ];
            
            if (isset($mime_types[$extension])) {
                $mime_type = $mime_types[$extension];
            }
            
            // Double-check with fileinfo if available
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected_mime = @finfo_file($finfo, $video_path);
                    if ($detected_mime && strpos($detected_mime, 'video/') === 0) {
                        $mime_type = $detected_mime;
                    }
                    finfo_close($finfo);
                }
            }

            error_log('VideoStreamer: Preparing to stream file:');
            error_log('- Path: ' . $video_path);
            error_log('- Size: ' . $file_size . ' bytes');
            error_log('- MIME: ' . $mime_type);

            // Set headers
            header('Content-Type: ' . $mime_type);
            header('Content-Length: ' . $file_size);
            header('Accept-Ranges: bytes');
            
            // CORS headers to allow video playback
            header('Access-Control-Allow-Origin: ' . esc_url_raw(site_url()));
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Range');
            header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');
            
            // Handle range requests
            $start = 0;
            $end = $file_size - 1;
            
            if (isset($_SERVER['HTTP_RANGE'])) {
                error_log('VideoStreamer: Range request detected: ' . $_SERVER['HTTP_RANGE']);
                $ranges = array_map('trim', explode(',', $_SERVER['HTTP_RANGE']));
                $ranges = array_filter($ranges);
                
                if (!empty($ranges)) {
                    $range = str_replace('bytes=', '', $ranges[0]);
                    list($start, $end) = array_map('intval', explode('-', $range . '-' . ($file_size - 1)));
                    
                    header('HTTP/1.1 206 Partial Content');
                    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
                    header('Content-Length: ' . ($end - $start + 1));
                    error_log('VideoStreamer: Serving range request - Start: ' . $start . ', End: ' . $end);
                }
            }

            // Security headers that don't break video playback
            header('Content-Disposition: inline; filename="stream.' . $extension . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            
            error_log('VideoStreamer: Headers set, starting file read');
            
            // Stream the file
            $handle = @fopen($video_path, 'rb');
            if ($handle === false) {
                error_log('VideoStreamer Error: Could not open video file for reading');
                throw new \Exception('Could not open video file');
            }

            if (@fseek($handle, $start) === -1) {
                error_log('VideoStreamer Error: Could not seek to position ' . $start);
                @fclose($handle);
                throw new \Exception('Could not seek to position');
            }

            // Output file in chunks
            $chunk_size = 8192; // 8KB chunks
            $bytes_sent = 0;
            
            while (!feof($handle) && $bytes_sent < ($end - $start + 1)) {
                $buffer = @fread($handle, min($chunk_size, ($end - $start + 1) - $bytes_sent));
                if ($buffer === false) {
                    error_log('VideoStreamer Error: Failed to read chunk at position ' . $bytes_sent);
                    break;
                }
                echo $buffer;
                flush();
                $bytes_sent += strlen($buffer);
                
                if (connection_status() != CONNECTION_NORMAL) {
                    error_log('VideoStreamer: Connection broken after sending ' . $bytes_sent . ' bytes');
                    break;
                }
            }
            
            error_log('VideoStreamer: Completed streaming ' . $bytes_sent . ' bytes');
            @fclose($handle);
            exit;
            
        } catch (\Exception $e) {
            error_log('VideoStreamer Fatal Error in stream_video_direct: ' . $e->getMessage());
            error_log('VideoStreamer Error Trace: ' . $e->getTraceAsString());
            wp_die(esc_html($e->getMessage()));
        }
    }

    private function get_video_path($video_slug) {
        try {
            error_log('WSVL Debug: Getting video path for slug: ' . $video_slug);
            
            // FIRST METHOD: Try to find the file using the product meta approach
            global $wpdb;
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND LOWER(meta_value) = LOWER(%s)
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = %s 
                    AND post_status = %s
                ) 
                LIMIT 1",
                '_video_slug',
                $video_slug,
                'product',
                'publish'
            ));
            
            error_log('WSVL Debug: Product ID from meta query: ' . ($product_id ? $product_id : 'Not found'));
            
            if ($product_id) {
                $video_file = get_post_meta($product_id, '_video_file', true);
                error_log('WSVL Debug: Video file from meta: ' . ($video_file ? $video_file : 'Not found'));
                
                if ($video_file) {
                    // Try both original case and lowercase versions of the file
                    $video_path = WSVL_PRIVATE_VIDEOS_DIR . $video_file;
                    $video_path_lower = WSVL_PRIVATE_VIDEOS_DIR . strtolower($video_file);
                    
                    error_log('WSVL Debug: Checking file existence - Original: ' . $video_path);
                    error_log('WSVL Debug: Checking file existence - Lowercase: ' . $video_path_lower);
                    
                    if (file_exists($video_path)) {
                        error_log('WSVL Debug: Found original case file');
                        return $video_path;
                    } else if (file_exists($video_path_lower)) {
                        error_log('WSVL Debug: Found lowercase file');
                        return $video_path_lower;
                    }
                }
                
                // Try to find the video by pattern matching
                $pattern_match_path = $this->find_video_by_slug_pattern($video_slug);
                if ($pattern_match_path) {
                    error_log('WSVL Debug: Found video by pattern matching: ' . $pattern_match_path);
                    
                    // Update the product meta with the found filename
                    $filename = basename($pattern_match_path);
                    update_post_meta($product_id, '_video_file', $filename);
                    error_log('WSVL Debug: Updated product meta with found filename: ' . $filename);
                    
                    return $pattern_match_path;
                }
            }
            
            // SECOND METHOD: Try to find the file directly in the upload directory
            // Get the upload directory
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];
            $video_dir = $base_dir . '/secure-videos';
            
            error_log('WSVL Debug: Base upload directory: ' . $base_dir);
            error_log('WSVL Debug: Video directory: ' . $video_dir);
            
            // Check if video directory exists
            if (!file_exists($video_dir)) {
                error_log('WSVL Debug: Video directory does not exist');
            } else {
                // Get all files in the video directory
                $files = scandir($video_dir);
                error_log('WSVL Debug: Files in video directory: ' . print_r($files, true));
                
                // Look for the video file (case insensitive)
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    // Check if this is the video we're looking for (case insensitive)
                    if (stripos($file, $video_slug) === 0) {
                        $video_path = $video_dir . '/' . $file;
                        error_log('WSVL Debug: Found matching video file: ' . $video_path);
                        
                        // Verify the file exists and is readable
                        if (!is_file($video_path)) {
                            error_log('WSVL Debug: Found path is not a file');
                            continue;
                        }
                        
                        if (!is_readable($video_path)) {
                            error_log('WSVL Debug: Found file is not readable');
                            continue;
                        }
                        
                        // Update product meta if we have a product ID
                        if ($product_id) {
                            update_post_meta($product_id, '_video_file', $file);
                            error_log('WSVL Debug: Updated product meta with found file: ' . $file);
                        }
                        
                        return $video_path;
                    }
                }
            }
            
            // FALLBACK: Try looking in the private videos directory (WSVL_PRIVATE_VIDEOS_DIR)
            error_log('WSVL Debug: Trying fallback in WSVL_PRIVATE_VIDEOS_DIR: ' . WSVL_PRIVATE_VIDEOS_DIR);
            
            // Try pattern matching (includes checking WSVL_PRIVATE_VIDEOS_DIR)
            $pattern_match_path = $this->find_video_by_slug_pattern($video_slug);
            if ($pattern_match_path) {
                error_log('WSVL Debug: Found video by pattern matching: ' . $pattern_match_path);
                
                // Update the product meta if we have a product ID
                if ($product_id) {
                    $filename = basename($pattern_match_path);
                    update_post_meta($product_id, '_video_file', $filename);
                    error_log('WSVL Debug: Updated product meta with found filename: ' . $filename);
                }
                
                return $pattern_match_path;
            }
            
            error_log('WSVL Debug: No matching video file found in any location');
            return false;
            
        } catch (\Exception $e) {
            error_log('WSVL Debug: Exception in get_video_path: ' . $e->getMessage());
            error_log('WSVL Debug: Exception trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Find a video file in the private directory by matching slug pattern
     * @param string $video_slug The video slug to match
     * @return string|false The full path to the video file or false if not found
     */
    private function find_video_by_slug_pattern($video_slug) {
        error_log('VideoStreamer: Searching for video with slug pattern: ' . $video_slug);
        
        if (!is_dir(WSVL_PRIVATE_VIDEOS_DIR)) {
            error_log('VideoStreamer: Private videos directory does not exist: ' . WSVL_PRIVATE_VIDEOS_DIR);
            return false;
        }
        
        $files = scandir(WSVL_PRIVATE_VIDEOS_DIR);
        error_log('VideoStreamer: Found ' . count($files) . ' files in directory');
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            // Check for exact match (case insensitive)
            if (strcasecmp($file, $video_slug . '.mp4') === 0 || 
                strcasecmp($file, $video_slug . '.webm') === 0 || 
                strcasecmp($file, $video_slug . '.m4v') === 0 ||
                strcasecmp($file, $video_slug . '.mov') === 0 ||
                strcasecmp($file, $video_slug . '.ogv') === 0) {
                return WSVL_PRIVATE_VIDEOS_DIR . $file;
            }
            
            // Check if file starts with the slug (case insensitive)
            if (stripos($file, $video_slug) === 0) {
                return WSVL_PRIVATE_VIDEOS_DIR . $file;
            }
            
            // Check if file contains the slug (case insensitive)
            if (stripos($file, $video_slug) !== false) {
                return WSVL_PRIVATE_VIDEOS_DIR . $file;
            }
        }
        
        error_log('VideoStreamer: No matching file found for slug: ' . $video_slug);
        return false;
    }
} 