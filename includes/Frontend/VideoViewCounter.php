<?php
namespace WSVL\Frontend;

use WSVL\Security\VideoStreamer;

class VideoViewCounter {
    
    public function __construct() {
        add_shortcode('wsvl_video_views', [$this, 'display_video_views_shortcode']);
        add_shortcode('wsvl_video_stats', [$this, 'display_video_stats_shortcode']);
        add_action('woocommerce_single_product_summary', [$this, 'display_product_video_stats'], 25);
    }

    /**
     * Shortcode to display video view count
     * Usage: [wsvl_video_views slug="video-slug"]
     */
    public function display_video_views_shortcode($atts) {
        $atts = shortcode_atts([
            'slug' => '',
            'format' => 'count', // 'count', 'text', 'badge'
            'show_unique' => false,
            'class' => 'wsvl-video-views'
        ], $atts, 'wsvl_video_views');

        if (empty($atts['slug'])) {
            return '<span class="wsvl-error">Video slug is required</span>';
        }

        $view_count = VideoStreamer::get_video_view_count($atts['slug']);
        $unique_viewers = $atts['show_unique'] ? VideoStreamer::get_video_unique_viewers($atts['slug']) : 0;

        $output = '';
        
        switch ($atts['format']) {
            case 'text':
                $output = sprintf(
                    '<span class="%s">%s</span>',
                    esc_attr($atts['class']),
                    sprintf(
                        _n('%d view', '%d views', $view_count, 'secure-video-locker-for-woocommerce'),
                        $view_count
                    )
                );
                if ($atts['show_unique'] && $unique_viewers > 0) {
                    $output .= sprintf(
                        ' <span class="%s-unique">(%s)</span>',
                        esc_attr($atts['class']),
                        sprintf(
                            _n('%d unique viewer', '%d unique viewers', $unique_viewers, 'secure-video-locker-for-woocommerce'),
                            $unique_viewers
                        )
                    );
                }
                break;
                
            case 'badge':
                $output = sprintf(
                    '<span class="%s wsvl-badge">%d %s</span>',
                    esc_attr($atts['class']),
                    $view_count,
                    _n('view', 'views', $view_count, 'secure-video-locker-for-woocommerce')
                );
                break;
                
            case 'count':
            default:
                $output = sprintf(
                    '<span class="%s">%d</span>',
                    esc_attr($atts['class']),
                    $view_count
                );
                break;
        }

        return $output;
    }

    /**
     * Shortcode to display comprehensive video statistics
     * Usage: [wsvl_video_stats slug="video-slug"]
     */
    public function display_video_stats_shortcode($atts) {
        $atts = shortcode_atts([
            'slug' => '',
            'show_views' => true,
            'show_unique' => true,
            'show_completion' => false,
            'class' => 'wsvl-video-stats'
        ], $atts, 'wsvl_video_stats');

        if (empty($atts['slug'])) {
            return '<span class="wsvl-error">Video slug is required</span>';
        }

        $video_streamer = new VideoStreamer();
        $stats = $video_streamer->get_video_stats($atts['slug']);

        if (!$stats || !$stats['summary']) {
            return '<span class="wsvl-no-stats">No statistics available</span>';
        }

        $summary = $stats['summary'];
        $output = '<div class="' . esc_attr($atts['class']) . '">';

        if ($atts['show_views']) {
            $output .= sprintf(
                '<span class="wsvl-stat-item wsvl-views">%s: %d</span>',
                __('Views', 'secure-video-locker-for-woocommerce'),
                $summary->total_views
            );
        }

        if ($atts['show_unique']) {
            $output .= sprintf(
                '<span class="wsvl-stat-item wsvl-unique">%s: %d</span>',
                __('Unique Viewers', 'secure-video-locker-for-woocommerce'),
                $summary->unique_viewers
            );
        }

        if ($atts['show_completion'] && $summary->avg_completion_rate > 0) {
            $output .= sprintf(
                '<span class="wsvl-stat-item wsvl-completion">%s: %.1f%%</span>',
                __('Avg. Completion', 'secure-video-locker-for-woocommerce'),
                $summary->avg_completion_rate
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Display video statistics on WooCommerce product pages
     */
    public function display_product_video_stats() {
        global $product;

        if (!$product || !is_user_logged_in()) {
            return;
        }

        // Check if user has purchased this product
        $user_id = get_current_user_id();
        $has_access = false;

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => -1,
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product->get_id()) {
                    $has_access = true;
                    break 2;
                }
            }
        }

        if (!$has_access) {
            return;
        }

        $video_slug = get_post_meta($product->get_id(), '_video_slug', true);
        
        if (empty($video_slug)) {
            return;
        }

        $view_count = VideoStreamer::get_video_view_count($video_slug);
        $unique_viewers = VideoStreamer::get_video_unique_viewers($video_slug);

        if ($view_count > 0) {
            echo '<div class="wsvl-product-video-stats">';
            echo '<h4>' . __('Video Statistics', 'secure-video-locker-for-woocommerce') . '</h4>';
            echo '<div class="wsvl-stats-grid">';
            
            echo sprintf(
                '<div class="wsvl-stat-box"><span class="wsvl-stat-number">%d</span><span class="wsvl-stat-label">%s</span></div>',
                $view_count,
                _n('View', 'Views', $view_count, 'secure-video-locker-for-woocommerce')
            );
            
            if ($unique_viewers > 0) {
                echo sprintf(
                    '<div class="wsvl-stat-box"><span class="wsvl-stat-number">%d</span><span class="wsvl-stat-label">%s</span></div>',
                    $unique_viewers,
                    _n('Unique Viewer', 'Unique Viewers', $unique_viewers, 'secure-video-locker-for-woocommerce')
                );
            }
            
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Get video statistics for template use
     */
    public static function get_video_stats_for_template($video_slug) {
        if (empty($video_slug)) {
            return null;
        }

        $video_streamer = new VideoStreamer();
        return $video_streamer->get_video_stats($video_slug);
    }

    /**
     * Template function to display video view count
     */
    public static function the_video_view_count($video_slug, $format = 'count') {
        if (empty($video_slug)) {
            return;
        }

        $view_count = VideoStreamer::get_video_view_count($video_slug);
        
        switch ($format) {
            case 'text':
                printf(
                    _n('%d view', '%d views', $view_count, 'secure-video-locker-for-woocommerce'),
                    $view_count
                );
                break;
            case 'count':
            default:
                echo $view_count;
                break;
        }
    }

    /**
     * Template function to get video view count
     */
    public static function get_video_view_count($video_slug) {
        return VideoStreamer::get_video_view_count($video_slug);
    }

    /**
     * Template function to get unique viewer count
     */
    public static function get_video_unique_viewers($video_slug) {
        return VideoStreamer::get_video_unique_viewers($video_slug);
    }
} 