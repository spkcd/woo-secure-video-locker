<?php
namespace WSVL\Frontend;

class VideoDashboard {
    public function __construct() {
        add_action('init', [$this, 'add_endpoints']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_video_menu_item']);
        add_action('woocommerce_account_secure-videos_endpoint', [$this, 'video_dashboard_content']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_refresh_video_url', [$this, 'ajax_refresh_video_url']);
    }

    public function add_endpoints() {
        add_rewrite_endpoint('secure-videos', EP_ROOT | EP_PAGES);
    }

    public function add_video_menu_item($items) {
        $items['secure-videos'] = __('My Videos', 'woo-secure-video-locker');
        return $items;
    }

    public function video_dashboard_content() {
        $user_id = get_current_user_id();
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => -1,
        ]);

        $video_products = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $video_slug = get_post_meta($product_id, '_video_slug', true);
                if ($video_slug) {
                    $video_products[] = [
                        'product_id' => $product_id,
                        'video_slug' => $video_slug,
                        'product_name' => $item->get_name(),
                        'video_description' => get_post_meta($product_id, '_video_description', true),
                    ];
                }
            }
        }

        if (empty($video_products)) {
            echo '<p>' . esc_html__('You haven\'t purchased any video products yet.', 'woo-secure-video-locker') . '</p>';
            return;
        }

        ?>
        <div class="wsvl-video-dashboard">
            <?php foreach ($video_products as $video) : 
                $video_url = \WSVL\Security\VideoStreamer::generate_signed_url($video['video_slug']);
            ?>
                <div class="wsvl-video-item" data-video-slug="<?php echo esc_attr($video['video_slug']); ?>">
                    <h3><?php echo esc_html($video['product_name']); ?></h3>
                    <?php if ($video['video_description']) : ?>
                        <p><?php echo esc_html($video['video_description']); ?></p>
                    <?php endif; ?>
                    <div class="wsvl-video-container">
                        <!-- Add user info as watermark -->
                        <div class="wsvl-watermark"><?php echo esc_html(wp_get_current_user()->user_email); ?></div>
                        
                        <video 
                            data-slug="<?php echo esc_attr($video['video_slug']); ?>"
                            controls
                            controlsList="nodownload noremoteplayback"
                            disablePictureInPicture
                            oncontextmenu="return false;"
                        >
                            <source src="<?php echo esc_url($video_url . '&chunk=0'); ?>" type="video/mp4">
                            <?php _e('Your browser does not support the video tag.', 'woo-secure-video-locker'); ?>
                        </video>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        if (is_account_page() && is_wc_endpoint_url('secure-videos')) {
            wp_enqueue_style(
                'wsvl-video-dashboard',
                WSVL_PLUGIN_URL . 'assets/css/video-dashboard.css',
                [],
                WSVL_VERSION
            );

            wp_enqueue_script(
                'wsvl-video-dashboard',
                WSVL_PLUGIN_URL . 'assets/js/video-dashboard.js',
                ['jquery'],
                WSVL_VERSION,
                true
            );

            wp_localize_script('wsvl-video-dashboard', 'wsvlData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wsvl-video-nonce'),
            ]);
        }
    }

    public function ajax_refresh_video_url() {
        check_ajax_referer('wsvl-video-nonce', 'nonce');

        $video_slug = sanitize_text_field($_POST['video_slug'] ?? '');
        if (!$video_slug) {
            wp_send_json_error('Invalid video slug');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not authenticated');
        }

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

        if (!$has_access) {
            wp_send_json_error('Access denied');
        }

        // Generate new signed URL
        $video_url = \WSVL\Security\VideoStreamer::generate_signed_url($video_slug);
        wp_send_json_success(['url' => $video_url]);
    }
} 