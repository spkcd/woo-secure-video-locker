<?php
/**
 * Admin template for adding videos to products
 * This will replace the default file upload field with our chunked uploader
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Current saved values
$video_file = get_post_meta($post_id, '_video_file', true);
$video_slug = get_post_meta($post_id, '_video_slug', true);
$video_description = get_post_meta($post_id, '_video_description', true);
?>

<div class="wsvl-product-video-panel">
    <p class="form-field">
        <label for="wsvl-video-slug"><?php _e('Video Slug', 'secure-video-locker-for-woocommerce'); ?></label>
        <input type="text" id="wsvl-video-slug" name="_video_slug" value="<?php echo esc_attr($video_slug); ?>" />
        <span class="description">
            <?php _e('A unique identifier for this video. Will be auto-generated when you upload a video file.', 'secure-video-locker-for-woocommerce'); ?>
            <?php _e('Use only letters, numbers, and hyphens.', 'secure-video-locker-for-woocommerce'); ?>
        </span>
    </p>
    
    <p class="form-field">
        <label for="wsvl-video-description"><?php _e('Video Description', 'secure-video-locker-for-woocommerce'); ?></label>
        <textarea id="wsvl-video-description" name="_video_description" rows="4"><?php echo esc_textarea($video_description); ?></textarea>
        <span class="description"><?php _e('A short description to display with the video.', 'secure-video-locker-for-woocommerce'); ?></span>
    </p>
    
    <p class="form-field">
        <label><?php _e('Current Video File', 'secure-video-locker-for-woocommerce'); ?></label>
        <?php if ($video_file) : ?>
            <span class="wsvl-current-file"><?php echo esc_html($video_file); ?></span>
            <?php if (file_exists(WSVL_PRIVATE_VIDEOS_DIR . $video_file)) : ?>
                <span class="wsvl-file-exists" style="color:green;margin-left:10px;">✓ File exists</span>
            <?php else : ?>
                <span class="wsvl-file-missing" style="color:red;margin-left:10px;">✗ File missing</span>
            <?php endif; ?>
        <?php else : ?>
            <span class="wsvl-no-file"><?php _e('No video file uploaded yet.', 'secure-video-locker-for-woocommerce'); ?></span>
        <?php endif; ?>
    </p>
    
    <div class="form-field">
        <label><?php _e('Upload Video', 'secure-video-locker-for-woocommerce'); ?></label>
        
        <!-- Hidden field to store the filename -->
        <input type="hidden" id="wsvl-video-file" name="_video_file" value="<?php echo esc_attr($video_file); ?>" />
        
        <!-- Chunked uploader container -->
        <div id="wsvl-chunked-uploader" class="wsvl-uploader">
            <div class="wsvl-upload-controls">
                <button id="wsvl-select-video" class="button"><?php _e('Select Video File', 'secure-video-locker-for-woocommerce'); ?></button>
                <span class="description"><?php printf(__('Maximum file size: %s', 'secure-video-locker-for-woocommerce'), size_format(wp_max_upload_size())); ?></span>
            </div>
            
            <div id="wsvl-file-list" class="wsvl-file-list"></div>
            <div id="wsvl-upload-message" class="wsvl-upload-message"></div>
        </div>
        
        <p class="description"><?php _e('Supported formats: MP4, WebM, MOV, OGV, M4V', 'secure-video-locker-for-woocommerce'); ?></p>
        <p class="description"><?php _e('Large videos will be uploaded in chunks to avoid timeouts. Do not navigate away from this page during upload.', 'secure-video-locker-for-woocommerce'); ?></p>
        <p class="description"><strong><?php _e('Note:', 'secure-video-locker-for-woocommerce'); ?></strong> <?php _e('When upload is complete, the Video Slug will be automatically generated from the filename.', 'secure-video-locker-for-woocommerce'); ?></p>
    </div>
</div>

<style>
.wsvl-product-video-panel .form-field {
    margin: 1em 0;
}
.wsvl-product-video-panel label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
.wsvl-product-video-panel input[type="text"],
.wsvl-product-video-panel textarea {
    width: 100%;
    max-width: 600px;
}
.wsvl-uploader {
    margin: 10px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}
.wsvl-file-list {
    margin-top: 15px;
}
.wsvl-file-item {
    margin-bottom: 10px;
    padding: 10px;
    background: #fff;
    border: 1px solid #eee;
}
.wsvl-file-item .file-name {
    margin-bottom: 5px;
    font-weight: bold;
}
.wsvl-file-item .progress-bar {
    height: 15px;
    background: #f0f0f0;
    position: relative;
    margin-bottom: 5px;
}
.wsvl-file-item .progress {
    height: 100%;
    background: #0073aa;
    width: 0;
}
.wsvl-file-item .status {
    font-size: 12px;
    color: #666;
}
.wsvl-success {
    padding: 10px;
    background: #f0fff0;
    border: 1px solid #c3e6c3;
    color: #3c763d;
}
.wsvl-error {
    padding: 10px;
    background: #fff0f0;
    border: 1px solid #e6c3c3;
    color: #763c3c;
}
.wsvl-info {
    padding: 10px;
    background: #e5f5fa;
    border: 1px solid #cce5ea;
    color: #0c5460;
}
</style> 