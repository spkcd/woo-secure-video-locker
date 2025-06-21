<?php
namespace WSVL\Admin;

use WSVL\Security\VideoStreamer;

class VideoAnalytics {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_wsvl_export_analytics', [$this, 'export_analytics']);
    }

    /**
     * Add admin menu for video analytics
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Video Analytics', 'secure-video-locker-for-woocommerce'),
            __('Video Analytics', 'secure-video-locker-for-woocommerce'),
            'manage_woocommerce',
            'wsvl-video-analytics',
            [$this, 'display_analytics_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wsvl-video-analytics') {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        wp_enqueue_script('wsvl-analytics', WSVL_PLUGIN_URL . 'assets/js/admin-analytics.js', ['jquery', 'chart-js'], WSVL_VERSION, true);
        wp_enqueue_style('wsvl-analytics', WSVL_PLUGIN_URL . 'assets/css/admin-analytics.css', [], WSVL_VERSION);
        
        wp_localize_script('wsvl-analytics', 'wsvlAnalytics', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsvl_admin_nonce'),
            'i18n' => [
                'loading' => __('Loading...', 'secure-video-locker-for-woocommerce'),
                'error' => __('Error loading data', 'secure-video-locker-for-woocommerce'),
                'noData' => __('No data available', 'secure-video-locker-for-woocommerce'),
                'exportSuccess' => __('Analytics exported successfully', 'secure-video-locker-for-woocommerce'),
                'exportError' => __('Error exporting analytics', 'secure-video-locker-for-woocommerce')
            ]
        ]);
    }

    /**
     * Display the analytics page
     */
    public function display_analytics_page() {
        $video_streamer = new VideoStreamer();
        $all_stats = $video_streamer->get_all_video_stats(100, 0);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Video Analytics', 'secure-video-locker-for-woocommerce'); ?></h1>
            
            <div class="wsvl-analytics-dashboard">
                <!-- Summary Cards -->
                <div class="wsvl-summary-cards">
                    <div class="wsvl-card">
                        <h3><?php _e('Total Videos', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <div class="wsvl-stat-number"><?php echo esc_html($all_stats['total']); ?></div>
                    </div>
                    <div class="wsvl-card">
                        <h3><?php _e('Total Views', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <div class="wsvl-stat-number"><?php echo esc_html(array_sum(array_column($all_stats['videos'], 'total_views'))); ?></div>
                    </div>
                    <div class="wsvl-card">
                        <h3><?php _e('Unique Viewers', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <div class="wsvl-stat-number"><?php echo esc_html(array_sum(array_column($all_stats['videos'], 'unique_viewers'))); ?></div>
                    </div>
                    <div class="wsvl-card">
                        <h3><?php _e('Total Watch Time', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <div class="wsvl-stat-number"><?php echo esc_html($this->format_duration(array_sum(array_column($all_stats['videos'], 'total_watch_time')))); ?></div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="wsvl-charts-section">
                    <div class="wsvl-chart-container">
                        <h3><?php _e('Top 10 Most Watched Videos', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <canvas id="topVideosChart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="wsvl-chart-container">
                        <h3><?php _e('Views Over Time (Last 30 Days)', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <canvas id="viewsTimeChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Video Statistics Table -->
                <div class="wsvl-table-section">
                    <div class="wsvl-table-header">
                        <h3><?php _e('Video Statistics', 'secure-video-locker-for-woocommerce'); ?></h3>
                        <div class="wsvl-table-actions">
                            <button id="exportAnalytics" class="button button-secondary">
                                <?php _e('Export CSV', 'secure-video-locker-for-woocommerce'); ?>
                            </button>
                            <button id="refreshData" class="button button-primary">
                                <?php _e('Refresh Data', 'secure-video-locker-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Video Slug', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Product Name', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Total Views', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Unique Viewers', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Watch Time', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Avg. Completion', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Last Viewed', 'secure-video-locker-for-woocommerce'); ?></th>
                                <th><?php _e('Actions', 'secure-video-locker-for-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_stats['videos'])): ?>
                                <?php foreach ($all_stats['videos'] as $video): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($video->video_slug); ?></strong></td>
                                        <td><?php echo esc_html($video->product_name ?: __('Unknown Product', 'secure-video-locker-for-woocommerce')); ?></td>
                                        <td><?php echo esc_html($video->total_views); ?></td>
                                        <td><?php echo esc_html($video->unique_viewers); ?></td>
                                        <td><?php echo esc_html($this->format_duration($video->total_watch_time)); ?></td>
                                        <td><?php echo esc_html(number_format($video->avg_completion_rate, 1)); ?>%</td>
                                        <td><?php echo esc_html($video->last_viewed ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($video->last_viewed)) : '-'); ?></td>
                                        <td>
                                            <button class="button button-small view-details" data-video-slug="<?php echo esc_attr($video->video_slug); ?>">
                                                <?php _e('View Details', 'secure-video-locker-for-woocommerce'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">
                                        <?php _e('No video statistics available yet.', 'secure-video-locker-for-woocommerce'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Video Details Modal -->
            <div id="videoDetailsModal" class="wsvl-modal" style="display: none;">
                <div class="wsvl-modal-content">
                    <div class="wsvl-modal-header">
                        <h2><?php _e('Video Details', 'secure-video-locker-for-woocommerce'); ?></h2>
                        <span class="wsvl-modal-close">&times;</span>
                    </div>
                    <div class="wsvl-modal-body">
                        <div id="videoDetailsContent">
                            <?php _e('Loading...', 'secure-video-locker-for-woocommerce'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            // Pass data to JavaScript
            window.wsvlVideoStats = <?php echo json_encode($all_stats['videos']); ?>;
        </script>
        <?php
    }

    /**
     * Format duration in seconds to human readable format
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return $hours . 'h ' . $minutes . 'm ' . $secs . 's';
        }
    }

    /**
     * Export analytics data as CSV
     */
    public function export_analytics() {
        // Verify user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        check_ajax_referer('wsvl_admin_nonce', 'nonce');
        
        $video_streamer = new VideoStreamer();
        $all_stats = $video_streamer->get_all_video_stats(1000, 0); // Get more data for export
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="video-analytics-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Video Slug',
            'Product Name',
            'Product ID',
            'Total Views',
            'Unique Viewers',
            'Total Watch Time (seconds)',
            'Average Completion Rate (%)',
            'Last Viewed',
            'Created Date'
        ]);
        
        // Add data rows
        foreach ($all_stats['videos'] as $video) {
            fputcsv($output, [
                $video->video_slug,
                $video->product_name ?: 'Unknown Product',
                $video->product_id,
                $video->total_views,
                $video->unique_viewers,
                $video->total_watch_time,
                number_format($video->avg_completion_rate, 2),
                $video->last_viewed,
                $video->created_date
            ]);
        }
        
        fclose($output);
        exit;
    }
} 