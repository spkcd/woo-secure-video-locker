<?php
namespace WSVL\Frontend;

class VideoDashboard {
    public function __construct() {
        add_action('init', [$this, 'register_endpoints']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_video_menu_item']);
        add_action('woocommerce_account_secure-videos_endpoint', [$this, 'render_video_dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_refresh_video_url', [$this, 'ajax_refresh_video_url']);
    }

    public function register_endpoints() {
        add_rewrite_endpoint('secure-videos', EP_ROOT | EP_PAGES);
    }

    public function add_video_menu_item($items) {
        $new_items = [];
        
        // Add the video menu item after dashboard
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['secure-videos'] = __('My Videos', 'secure-video-locker-for-woocommerce');
            }
        }
        
        return $new_items;
    }

    public function render_video_dashboard() {
        if (!is_user_logged_in()) {
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

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
                        'title' => $item->get_name(),
                        'description' => get_post_meta($product_id, '_video_description', true),
                        'order_date' => $order->get_date_created()->format('Y-m-d'),
                    ];
                }
            }
        }

        if (empty($video_products)) {
            echo '<div class="wsvl-no-videos">';
            echo '<p>' . esc_html__('You haven\'t purchased any video products yet.', 'secure-video-locker-for-woocommerce') . '</p>';
            
            // Get custom URL from options or use default shop page
            $browse_products_url = get_option('wsvl_browse_products_url', get_permalink(wc_get_page_id('shop')));
            
            echo '<a href="' . esc_url($browse_products_url) . '" class="button">' . 
                 esc_html__('Browse Products', 'secure-video-locker-for-woocommerce') . '</a>';
            echo '</div>';
            return;
        }

        ?>
        <div class="wsvl-video-dashboard">
            <?php foreach ($video_products as $product) : ?>
                <div class="wsvl-video-item">
                    <h3><?php echo esc_html($product['title']); ?></h3>
                    <?php if ($product['description']) : ?>
                        <p class="description"><?php echo esc_html($product['description']); ?></p>
                    <?php endif; ?>
                    <p class="purchase-date">
                        <?php echo esc_html__('Purchased on: ', 'secure-video-locker-for-woocommerce') . 
                             esc_html($product['order_date']); ?>
                    </p>
                    <?php 
                    // Set required variables for the template
                    $video_slug = $product['video_slug'];
                    $video_description = $product['description'];
                    // Include our secure video display template
                    include WSVL_PLUGIN_DIR . 'templates/video-display.php';
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        if (is_account_page() && is_wc_endpoint_url('secure-videos')) {
            // Enqueue dashicons for our player controls
            wp_enqueue_style('dashicons');
            
            wp_enqueue_style(
                'wsvl-video-dashboard',
                WSVL_PLUGIN_URL . 'assets/css/video-dashboard.css',
                [],
                WSVL_VERSION
            );
            
            // Disable the default video player script
            wp_dequeue_script('wsvl-video-player');
            
            // Make sure jQuery is loaded
            wp_enqueue_script('jquery');
            
            // Enqueue our secure video player scripts instead
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
                'debug' => true
            ]);
        }
    }

    public function ajax_refresh_video_url() {
        check_ajax_referer('wsvl-video-nonce', 'nonce');

        $video_slug = sanitize_text_field(wp_unslash($_POST['video_slug'] ?? ''));
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