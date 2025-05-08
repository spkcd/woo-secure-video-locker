<?php
namespace WSVL\Admin;

class ProductVideoManager {
    public function __construct() {
        // Add video fields to product data tabs
        add_filter('woocommerce_product_data_tabs', [$this, 'add_video_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_video_product_data_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_video_data']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Initialize the chunked uploader
        new ChunkedUploader();
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        // Enqueue Plupload for chunked uploads
        wp_enqueue_script('plupload-all');
        
        // jQuery UI for the progress bar
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        // Our custom script for handling chunked uploads
        wp_enqueue_script(
            'wsvl-chunked-upload',
            WSVL_PLUGIN_URL . 'assets/js/chunked-upload.js',
            ['jquery', 'plupload-all', 'jquery-ui-progressbar'],
            WSVL_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('wsvl-chunked-upload', 'wsvl_upload', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsvl-upload-video'),
            'chunk_size' => 1024 * 1024 * 2, // 2MB chunks
            'max_file_size' => '2048mb',
            'i18n' => [
                'uploading' => __('Uploading...', 'secure-video-locker-for-woocommerce'),
                'upload_complete' => __('Upload complete!', 'secure-video-locker-for-woocommerce'),
                'upload_error' => __('Upload failed. Please try again.', 'secure-video-locker-for-woocommerce')
            ]
        ]);
        
        // Admin styles
        wp_enqueue_style(
            'wsvl-admin',
            WSVL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WSVL_VERSION
        );
    }

    public function add_video_product_data_tab($tabs) {
        $tabs['video_content'] = [
            'label' => __('Video Content', 'secure-video-locker-for-woocommerce'),
            'target' => 'video_product_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 70,
        ];
        return $tabs;
    }

    public function add_video_product_data_fields() {
        global $post;
        ?>
        <div id="video_product_data" class="panel woocommerce_options_panel">
            <?php
            // Include our custom template instead of using the WooCommerce field builder
            include WSVL_PLUGIN_DIR . 'templates/admin-product-video.php';
            ?>
        </div>
        <?php
    }

    public function save_video_data($post_id) {
        // Unslash and sanitize input
        $video_slug = sanitize_text_field(wp_unslash($_POST['_video_slug'] ?? ''));
        $video_description = sanitize_textarea_field(wp_unslash($_POST['_video_description'] ?? ''));
        $video_file = sanitize_text_field(wp_unslash($_POST['_video_file'] ?? ''));

        update_post_meta($post_id, '_video_slug', $video_slug);
        update_post_meta($post_id, '_video_description', $video_description);
        update_post_meta($post_id, '_video_file', $video_file);
    }

    public function display_video_preview() {
        global $product;
        $video_slug = get_post_meta($product->get_id(), '_video_slug', true);
        $video_description = get_post_meta($product->get_id(), '_video_description', true);

        if ($video_slug) {
            ?>
            <div class="video-preview">
                <h3><?php esc_html_e('Video Preview', 'secure-video-locker-for-woocommerce'); ?></h3>
                <?php if ($video_description) : ?>
                    <p><?php echo esc_html($video_description); ?></p>
                <?php endif; ?>
                <div class="video-preview-placeholder">
                    <?php esc_html_e('Purchase this product to access the full video content.', 'secure-video-locker-for-woocommerce'); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Check video file access and validity
     */
    public function check_video_file($video_slug) {
        // First, check if the slug exists in the database
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

        $result = array(
            'success' => false,
            'messages' => array()
        );

        if (!$product_id) {
            $result['messages'][] = "Error: No product found with video slug '$video_slug'";
            return $result;
        }

        $result['messages'][] = "Found product ID: $product_id with video slug '$video_slug'";
        
        // Check if video file meta exists
        $video_file = get_post_meta($product_id, '_video_file', true);
        if (!$video_file) {
            $result['messages'][] = "Error: No video file associated with this slug";
            return $result;
        }
        
        $result['messages'][] = "Video filename: $video_file";
        
        // Check if the video directory exists
        if (!file_exists(WSVL_PRIVATE_VIDEOS_DIR)) {
            $result['messages'][] = "Error: Private videos directory does not exist: " . WSVL_PRIVATE_VIDEOS_DIR;
            return $result;
        }
        
        $result['messages'][] = "Private videos directory exists: " . WSVL_PRIVATE_VIDEOS_DIR;
        
        // Check if the video file exists
        $full_path = WSVL_PRIVATE_VIDEOS_DIR . $video_file;
        if (!file_exists($full_path)) {
            $result['messages'][] = "Error: Video file does not exist at: $full_path";
            
            // List files in the directory to help diagnose
            $files = scandir(WSVL_PRIVATE_VIDEOS_DIR);
            if (count($files) > 2) { // More than . and ..
                $result['messages'][] = "Found " . (count($files) - 2) . " files in videos directory. First 5 files:";
                $files = array_slice(array_diff($files, array('.', '..')), 0, 5);
                foreach ($files as $file) {
                    $result['messages'][] = " - $file";
                }
            } else {
                $result['messages'][] = "No files found in the private videos directory";
            }
            
            return $result;
        }
        
        $result['messages'][] = "Video file exists at: $full_path";
        
        // Check file permissions
        $perms = fileperms($full_path);
        $result['messages'][] = "File permissions: " . substr(sprintf('%o', $perms), -4);
        
        // Check if file is readable
        if (!is_readable($full_path)) {
            $result['messages'][] = "Error: File is not readable by the web server";
            return $result;
        }
        
        $result['messages'][] = "File is readable by the web server";
        
        // Check file size
        $size = filesize($full_path);
        if ($size === false) {
            $result['messages'][] = "Error: Could not determine file size";
            return $result;
        }
        
        $result['messages'][] = "File size: " . size_format($size);
        
        // All checks passed
        $result['success'] = true;
        $result['messages'][] = "All checks passed! The video file should be accessible.";
        
        return $result;
    }
} 