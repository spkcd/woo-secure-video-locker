<?php
namespace WSVL\Security;

class VideoStreamer {
    private const TOKEN_EXPIRY = 3600; // 1 hour in seconds
    private const ALGORITHM = 'sha256';
    private const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
    private const MAX_CHUNK_SIZE = 1024 * 1024 * 10; // 10MB max chunk size

    public function __construct() {
        add_action('init', [$this, 'handle_video_request']);
        add_action('template_redirect', [$this, 'prevent_direct_access']);
    }

    public function prevent_direct_access() {
        if (isset($_GET['wsvl_video']) && !isset($_GET['chunk'])) {
            wp_die(__('Direct video access is not allowed.', 'woo-secure-video-locker'));
        }
    }

    public function handle_video_request() {
        if (isset($_GET['wsvl_video']) && isset($_GET['token'])) {
            $video_slug = sanitize_text_field($_GET['wsvl_video']);
            $token = sanitize_text_field($_GET['token']);

            try {
                if ($this->verify_token($video_slug, $token)) {
                    $this->stream_video($video_slug);
                } else {
                    wp_die(__('Invalid or expired video access token.', 'woo-secure-video-locker'));
                }
            } catch (\Exception $e) {
                error_log('VideoStreamer Error: ' . $e->getMessage());
                wp_die(__('Error processing video request.', 'woo-secure-video-locker'));
            }
        }
    }

    public static function generate_signed_url($video_slug) {
        $token = self::generate_token($video_slug);
        return add_query_arg([
            'wsvl_video' => $video_slug,
            'token' => $token,
        ], home_url());
    }

    private static function generate_token($video_slug) {
        $expiry = time() + self::TOKEN_EXPIRY;
        $data = $video_slug . '|' . $expiry;
        return hash_hmac(self::ALGORITHM, $data, wp_salt('auth'));
    }

    private function verify_token($video_slug, $token) {
        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                error_log('VideoStreamer: User not logged in');
                return false;
            }
            
            // Get current user ID
            $user_id = get_current_user_id();
            
            // Verify user has purchased the product
            if (!$this->verify_user_has_access($user_id, $video_slug)) {
                error_log('VideoStreamer: User does not have access to this video');
                return false;
            }

            // Find the product with this video slug
            $args = array(
                'post_type' => 'product',
                'meta_key' => '_video_slug',
                'meta_value' => $video_slug,
                'posts_per_page' => 1
            );
            
            $query = new \WP_Query($args);
            if (!$query->have_posts()) {
                error_log('VideoStreamer: No product found for slug: ' . $video_slug);
                return false;
            }

            $product_id = $query->posts[0]->ID;
            $video_file = get_post_meta($product_id, '_video_file', true);

            if (!$video_file) {
                error_log('VideoStreamer: No video file found for product: ' . $product_id);
                return false;
            }

            $video_path = WSVL_PRIVATE_VIDEOS_DIR . $video_file;
            
            if (!file_exists($video_path)) {
                error_log('VideoStreamer: Video file not found at path: ' . $video_path);
                return false;
            }

            // Verify file permissions
            if (!is_readable($video_path)) {
                error_log('VideoStreamer: Video file is not readable: ' . $video_path);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log('VideoStreamer Token Verification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify that user has purchased the video product
     */
    private function verify_user_has_access($user_id, $video_slug) {
        // Verify user has purchased the video
        $has_access = false;
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => -1,
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if (get_post_meta($product_id, '_video_slug', true) === $video_slug) {
                    $has_access = true;
                    break 2;
                }
            }
        }
        
        // Check for subscription access if WooCommerce Subscriptions is active
        if (!$has_access && function_exists('wcs_get_users_subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions($user_id);
            foreach ($subscriptions as $subscription) {
                if (!$subscription->has_status(['active', 'pending-cancel'])) {
                    continue;
                }
                
                foreach ($subscription->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if (get_post_meta($product_id, '_video_slug', true) === $video_slug) {
                        $has_access = true;
                        break 2;
                    }
                }
            }
        }
        
        // Track video access attempts
        $this->log_access_attempt($user_id, $video_slug, $has_access);
        
        return $has_access;
    }
    
    /**
     * Log access attempts for security monitoring
     */
    private function log_access_attempt($user_id, $video_slug, $success) {
        // Get client IP address with proxy handling
        $ip_address = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_parts[0]);
        }
        
        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        
        // Get referer
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
        
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
        
        // Log to file
        error_log($message);
        
        // Maybe store in database for advanced monitoring
        
        // If too many failed attempts detected, maybe block the IP
        if (!$success) {
            $fail_count = (int) get_transient('wsvl_fails_' . $ip_address);
            set_transient('wsvl_fails_' . $ip_address, $fail_count + 1, HOUR_IN_SECONDS);
            
            if ($fail_count > 10) {
                // Log suspicious activity
                error_log('SUSPICIOUS: Multiple failed video access attempts from IP ' . $ip_address);
            }
        }
    }

    private function stream_video($video_slug) {
        try {
            // Check rate limit before processing
            $this->check_rate_limit();
            
            // Find the product with this video slug
            $args = array(
                'post_type' => 'product',
                'meta_key' => '_video_slug',
                'meta_value' => $video_slug,
                'posts_per_page' => 1
            );
            
            $query = new \WP_Query($args);
            if (!$query->have_posts()) {
                throw new \Exception('Product not found for video slug: ' . $video_slug);
            }

            $product_id = $query->posts[0]->ID;
            $video_file = get_post_meta($product_id, '_video_file', true);

            if (!$video_file) {
                throw new \Exception('No video file found for product: ' . $product_id);
            }

            $video_path = WSVL_PRIVATE_VIDEOS_DIR . $video_file;
            
            if (!file_exists($video_path)) {
                throw new \Exception('Video file not found at path: ' . $video_path);
            }

            // Get file size
            $size = filesize($video_path);
            if ($size === false) {
                throw new \Exception('Could not determine video file size');
            }

            // Get file mime type
            $mime_type = mime_content_type($video_path);
            if ($mime_type === false) {
                $mime_type = 'video/mp4';
            }

            // Handle chunked requests
            $chunk = isset($_GET['chunk']) ? intval($_GET['chunk']) : 0;
            $chunk_size = isset($_GET['size']) ? min(intval($_GET['size']), self::MAX_CHUNK_SIZE) : self::CHUNK_SIZE;
            $start = $chunk * $chunk_size;
            $end = min($start + $chunk_size - 1, $size - 1);
            $length = $end - $start + 1;

            // Set headers for chunked streaming
            header('HTTP/1.1 206 Partial Content');
            header('Accept-Ranges: bytes');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . $length);
            header('Content-Type: ' . $mime_type);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: same-origin');
            
            // Add Content-Disposition header to prevent saving
            header('Content-Disposition: inline');
            
            // Add Content-Security-Policy to block downloads
            header("Content-Security-Policy: default-src 'self'; media-src 'self'; object-src 'none'; frame-ancestors 'self'");
            
            // Add Cross-Origin-Resource-Policy header
            header('Cross-Origin-Resource-Policy: same-origin');
            
            // Add Feature-Policy to disable download capability
            header("Feature-Policy: downloads 'none'");

            // Stream the video chunk
            $handle = fopen($video_path, 'rb');
            if ($handle === false) {
                throw new \Exception('Could not open video file');
            }

            fseek($handle, $start);
            
            // Output the chunk
            while (!feof($handle) && ftell($handle) <= $end) {
                echo fread($handle, 8192);
            }

            fclose($handle);
            exit;
        } catch (\Exception $e) {
            error_log('VideoStreamer Streaming Error: ' . $e->getMessage());
            wp_die($e->getMessage());
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
            wp_die(__('Rate limit exceeded. Please try again later.', 'woo-secure-video-locker'));
        }
        
        set_transient($transient_key, $rate_limit + 1, 60);
        return true;
    }
} 