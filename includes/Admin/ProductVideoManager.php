<?php
namespace WSVL\Admin;

class ProductVideoManager {
    public function __construct() {
        add_action('woocommerce_product_data_tabs', [$this, 'add_video_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_video_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_video_data']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_video_preview']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_wsvl_upload_video', [$this, 'handle_video_upload']);
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_media();
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'wsvl-admin-styles',
            WSVL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WSVL_VERSION
        );

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'wsvl-admin-product',
            WSVL_PLUGIN_URL . 'assets/js/admin-product.js',
            ['jquery'],
            WSVL_VERSION,
            true
        );

        wp_localize_script('wsvl-admin-product', 'wsvlAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsvl-admin-nonce'),
            'uploading' => __('Uploading...', 'woo-secure-video-locker'),
            'uploadComplete' => __('Upload complete!', 'woo-secure-video-locker'),
            'uploadError' => __('Upload failed. Please try again.', 'woo-secure-video-locker'),
        ]);
    }

    public function add_video_tab($tabs) {
        $tabs['video'] = [
            'label' => __('Video Content', 'woo-secure-video-locker'),
            'target' => 'video_product_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 70,
        ];
        return $tabs;
    }

    public function add_video_panel() {
        global $post;
        $video_slug = get_post_meta($post->ID, '_video_slug', true);
        $video_description = get_post_meta($post->ID, '_video_description', true);
        $video_file = get_post_meta($post->ID, '_video_file', true);
        ?>
        <div id="video_product_data" class="panel woocommerce_options_panel">
            <?php
            woocommerce_wp_text_input([
                'id' => '_video_slug',
                'label' => __('Video Slug', 'woo-secure-video-locker'),
                'desc_tip' => true,
                'description' => __('Unique identifier for the video file (e.g., course-101-intro)', 'woo-secure-video-locker'),
                'value' => $video_slug,
            ]);

            woocommerce_wp_textarea_input([
                'id' => '_video_description',
                'label' => __('Video Description', 'woo-secure-video-locker'),
                'desc_tip' => true,
                'description' => __('Brief description of the video content', 'woo-secure-video-locker'),
                'value' => $video_description,
            ]);

            // Video upload section
            ?>
            <div class="options_group">
                <p class="form-field">
                    <label for="_video_file"><?php _e('Video File', 'woo-secure-video-locker'); ?></label>
                    <input type="hidden" id="_video_file" name="_video_file" value="<?php echo esc_attr($video_file); ?>" />
                    <button type="button" class="button wsvl-upload-video" id="wsvl-upload-video">
                        <?php _e('Upload Video', 'woo-secure-video-locker'); ?>
                    </button>
                    <span class="wsvl-upload-status"></span>
                    <div class="wsvl-video-preview">
                        <?php if ($video_file) : ?>
                            <p class="description">
                                <?php _e('Current video:', 'woo-secure-video-locker'); ?> 
                                <strong><?php echo esc_html(basename($video_file)); ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_video_data($post_id) {
        $video_slug = sanitize_text_field($_POST['_video_slug'] ?? '');
        $video_description = sanitize_textarea_field($_POST['_video_description'] ?? '');
        $video_file = sanitize_text_field($_POST['_video_file'] ?? '');

        update_post_meta($post_id, '_video_slug', $video_slug);
        update_post_meta($post_id, '_video_description', $video_description);
        update_post_meta($post_id, '_video_file', $video_file);
    }

    public function handle_video_upload() {
        check_ajax_referer('wsvl-admin-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_FILES['video'])) {
            wp_send_json_error('No video file uploaded');
        }

        $file = $_FILES['video'];
        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Only MP4, WebM, and OGG videos are allowed.');
        }

        // Generate unique filename
        $filename = wp_unique_filename(WSVL_PRIVATE_VIDEOS_DIR, $file['name']);
        $filepath = WSVL_PRIVATE_VIDEOS_DIR . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error('Failed to move uploaded file');
        }

        // Set proper permissions
        chmod($filepath, 0644);

        wp_send_json_success([
            'file' => $filename,
            'url' => WSVL_PRIVATE_VIDEOS_DIR . $filename
        ]);
    }

    public function display_video_preview() {
        global $product;
        $video_slug = get_post_meta($product->get_id(), '_video_slug', true);
        $video_description = get_post_meta($product->get_id(), '_video_description', true);

        if ($video_slug) {
            ?>
            <div class="video-preview">
                <h3><?php _e('Video Preview', 'woo-secure-video-locker'); ?></h3>
                <?php if ($video_description) : ?>
                    <p><?php echo esc_html($video_description); ?></p>
                <?php endif; ?>
                <div class="video-preview-placeholder">
                    <?php _e('Purchase this product to access the full video content.', 'woo-secure-video-locker'); ?>
                </div>
            </div>
            <?php
        }
    }
} 