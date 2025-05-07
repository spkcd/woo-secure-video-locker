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
        
        // Chunked upload handlers
        add_action('wp_ajax_wsvl_init_upload', [$this, 'handle_init_upload']);
        add_action('wp_ajax_wsvl_upload_chunk', [$this, 'handle_upload_chunk']);
        add_action('wp_ajax_wsvl_complete_upload', [$this, 'handle_complete_upload']);
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'wsvl-admin',
            WSVL_PLUGIN_URL . 'assets/js/admin-product.js',
            ['jquery'],
            WSVL_VERSION,
            true
        );

        wp_localize_script('wsvl-admin', 'wsvlAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsvl-admin-nonce'),
            'uploading' => __('Uploading...', 'secure-video-locker-for-woocommerce'),
            'uploadComplete' => __('Upload complete!', 'secure-video-locker-for-woocommerce'),
            'uploadError' => __('Upload failed. Please try again.', 'secure-video-locker-for-woocommerce'),
        ]);
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
            // Video Slug
            woocommerce_wp_text_input([
                'id' => '_video_slug',
                'label' => __('Video Slug', 'secure-video-locker-for-woocommerce'),
                'description' => __('Unique identifier for the video file (e.g., course-101-intro)', 'secure-video-locker-for-woocommerce'),
                'value' => get_post_meta($post->ID, '_video_slug', true),
            ]);

            // Video Description
            woocommerce_wp_textarea_input([
                'id' => '_video_description',
                'label' => __('Video Description', 'secure-video-locker-for-woocommerce'),
                'description' => __('Brief description of the video content', 'secure-video-locker-for-woocommerce'),
                'value' => get_post_meta($post->ID, '_video_description', true),
            ]);

            // Video File Upload
            $video_file = get_post_meta($post->ID, '_video_file', true);
            ?>
            <div class="options_group">
                <p class="form-field">
                    <label for="_video_file"><?php esc_html_e('Video File', 'secure-video-locker-for-woocommerce'); ?></label>
                    <input type="hidden" id="_video_file" name="_video_file" value="<?php echo esc_attr($video_file); ?>">
                    <button type="button" class="button" id="wsvl-upload-video">
                        <?php esc_html_e('Upload Video', 'secure-video-locker-for-woocommerce'); ?>
                    </button>
                    <span class="wsvl-upload-status"></span>
                    <div class="wsvl-video-preview">
                        <?php if ($video_file) : ?>
                            <p class="description">
                                <?php esc_html_e('Current video:', 'secure-video-locker-for-woocommerce'); ?>
                                <strong><?php echo esc_html($video_file); ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </p>
            </div>
            <?php
            do_action('woocommerce_product_options_end');
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

    public function handle_init_upload() {
        error_log('WSVL: Initializing chunked upload');
        
        try {
            check_ajax_referer('wsvl-admin-nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }

            $filename = sanitize_file_name($_POST['filename']);
            $totalChunks = intval($_POST['totalChunks']);
            $totalSize = intval($_POST['totalSize']);

            // Validate file type
            $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
            $file_type = wp_check_filetype($filename)['type'];
            if (!in_array($file_type, $allowed_types)) {
                wp_send_json_error('Invalid file type. Only MP4, WebM, and OGG videos are allowed.');
            }

            // Generate unique upload ID
            $upload_id = wp_generate_password(32, false);
            
            // Create temporary directory for chunks
            $chunks_dir = WSVL_PRIVATE_VIDEOS_DIR . 'chunks/' . $upload_id;
            if (!file_exists($chunks_dir)) {
                wp_mkdir_p($chunks_dir);
            }

            // Store upload info
            $upload_info = [
                'filename' => $filename,
                'totalChunks' => $totalChunks,
                'totalSize' => $totalSize,
                'uploadedChunks' => 0,
                'started' => time()
            ];
            
            update_option('wsvl_upload_' . $upload_id, $upload_info);

            wp_send_json_success(['uploadId' => $upload_id]);
        } catch (Exception $e) {
            error_log('WSVL: Error initializing upload: ' . $e->getMessage());
            wp_send_json_error('Failed to initialize upload: ' . $e->getMessage());
        }
    }

    public function handle_upload_chunk() {
        error_log('WSVL: Processing chunk upload');
        
        try {
            check_ajax_referer('wsvl-admin-nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }

            $upload_id = sanitize_text_field($_POST['uploadId']);
            $chunk_index = intval($_POST['chunkIndex']);

            // Get upload info
            $upload_info = get_option('wsvl_upload_' . $upload_id);
            if (!$upload_info) {
                wp_send_json_error('Invalid upload ID');
            }

            if (!isset($_FILES['chunk'])) {
                wp_send_json_error('No chunk file uploaded');
            }

            // Initialize WordPress filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }

            if (!$wp_filesystem) {
                wp_send_json_error('Failed to initialize filesystem');
            }

            // Save chunk
            $chunks_dir = WSVL_PRIVATE_VIDEOS_DIR . 'chunks/' . $upload_id;
            $chunk_file = $chunks_dir . '/' . $chunk_index;

            if (!$wp_filesystem->move($_FILES['chunk']['tmp_name'], $chunk_file)) {
                wp_send_json_error('Failed to save chunk');
            }

            // Update upload info
            $upload_info['uploadedChunks']++;
            update_option('wsvl_upload_' . $upload_id, $upload_info);

            wp_send_json_success();
        } catch (Exception $e) {
            error_log('WSVL: Error processing chunk: ' . $e->getMessage());
            wp_send_json_error('Failed to process chunk: ' . $e->getMessage());
        }
    }

    public function handle_complete_upload() {
        error_log('WSVL: Completing chunked upload');
        
        try {
            check_ajax_referer('wsvl-admin-nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }

            $upload_id = sanitize_text_field($_POST['uploadId']);

            // Get upload info
            $upload_info = get_option('wsvl_upload_' . $upload_id);
            if (!$upload_info) {
                wp_send_json_error('Invalid upload ID');
            }

            // Initialize WordPress filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }

            if (!$wp_filesystem) {
                wp_send_json_error('Failed to initialize filesystem');
            }

            // Generate unique filename
            $filename = wp_unique_filename(WSVL_PRIVATE_VIDEOS_DIR, $upload_info['filename']);
            $filepath = WSVL_PRIVATE_VIDEOS_DIR . $filename;

            // Combine chunks
            $chunks_dir = WSVL_PRIVATE_VIDEOS_DIR . 'chunks/' . $upload_id;
            $output = fopen($filepath, 'wb');

            for ($i = 0; $i < $upload_info['totalChunks']; $i++) {
                $chunk_file = $chunks_dir . '/' . $i;
                if (!file_exists($chunk_file)) {
                    throw new Exception("Missing chunk $i");
                }
                $chunk = file_get_contents($chunk_file);
                fwrite($output, $chunk);
            }

            fclose($output);

            // Set proper permissions
            $wp_filesystem->chmod($filepath, 0644);

            // Clean up chunks
            $wp_filesystem->rmdir($chunks_dir, true);
            delete_option('wsvl_upload_' . $upload_id);

            wp_send_json_success([
                'file' => $filename,
                'url' => WSVL_PRIVATE_VIDEOS_DIR . $filename
            ]);
        } catch (Exception $e) {
            error_log('WSVL: Error completing upload: ' . $e->getMessage());
            wp_send_json_error('Failed to complete upload: ' . $e->getMessage());
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