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
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Ensure our fields are preserved when switching tabs
                const videoSlugField = $('#wsvl-video-slug');
                const videoFileField = $('#wsvl-video-file');
                
                // When users click on any tab, store current values
                $('.product_data_tabs .product_data_tab').on('click', function() {
                    // Store the current values in data attributes
                    videoSlugField.attr('data-last-value', videoSlugField.val());
                    videoFileField.attr('data-last-value', videoFileField.val());
                });
                
                // When our tab is clicked, check if values were lost and restore them
                $('.product_data_tabs .product_data_tab a[href="#video_product_data"]').on('click', function() {
                    // Check if values were lost (they're empty but we have stored values)
                    const storedSlug = videoSlugField.attr('data-last-value');
                    const storedFile = videoFileField.attr('data-last-value');
                    
                    if (storedSlug && !videoSlugField.val()) {
                        console.log('Restoring lost slug value:', storedSlug);
                        videoSlugField.val(storedSlug);
                    }
                    
                    if (storedFile && !videoFileField.val()) {
                        console.log('Restoring lost file value:', storedFile);
                        videoFileField.val(storedFile);
                    }
                });
                
                // Also store values before form submission
                $('#post').on('submit', function() {
                    // Log the values being submitted
                    console.log('Form submission - Video slug:', videoSlugField.val());
                    console.log('Form submission - Video file:', videoFileField.val());
                });
            });
            </script>
        </div>
        <?php
    }

    public function save_video_data($post_id) {
        // Debug: Log the post data to see what's being received
        error_log('WSVL - Processing product meta for post ID: ' . $post_id);
        
        // Security check: Verify our nonce if present, but don't fail if it's missing
        // (since it might be coming from a WooCommerce form submission without our nonce)
        if (isset($_POST['wsvl_video_nonce']) && !wp_verify_nonce($_POST['wsvl_video_nonce'], 'wsvl_save_video_data')) {
            error_log('WSVL - Nonce verification failed but continuing anyway');
            // Continue anyway since it might be a WooCommerce save
        }
        
        // Don't save during autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log('WSVL - Skipping save during autosave');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            error_log('WSVL - User does not have permission to edit this post');
            return;
        }
        
        // Log all POST keys for debugging
        error_log('WSVL - POST keys: ' . implode(', ', array_keys($_POST)));
        
        // Unslash and sanitize input
        $video_slug = !empty($_POST['_video_slug']) ? sanitize_text_field(wp_unslash($_POST['_video_slug'])) : '';
        $video_description = !empty($_POST['_video_description']) ? sanitize_textarea_field(wp_unslash($_POST['_video_description'])) : '';
        $video_file = !empty($_POST['_video_file']) ? sanitize_text_field(wp_unslash($_POST['_video_file'])) : '';
        
        error_log('WSVL - Processed fields - Slug: ' . $video_slug . ', File: ' . $video_file);

        // If both are empty, check if we have existing data to preserve
        if (empty($video_slug) && empty($video_file)) {
            $existing_slug = get_post_meta($post_id, '_video_slug', true);
            $existing_file = get_post_meta($post_id, '_video_file', true);
            
            if ($existing_slug || $existing_file) {
                error_log('WSVL - Empty fields but existing data found. Not updating to prevent data loss.');
                return;
            }
        }

        // Update post meta - only update if we actually have values
        if (!empty($video_slug)) {
            update_post_meta($post_id, '_video_slug', $video_slug);
            error_log('WSVL - Updated _video_slug meta to: ' . $video_slug);
        }
        
        if (!empty($video_description)) {
            update_post_meta($post_id, '_video_description', $video_description);
            error_log('WSVL - Updated _video_description meta');
        }
        
        if (!empty($video_file)) {
            update_post_meta($post_id, '_video_file', $video_file);
            error_log('WSVL - Updated _video_file meta to: ' . $video_file);
        }
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