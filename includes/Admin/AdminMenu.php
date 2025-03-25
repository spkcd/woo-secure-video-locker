/**
 * Add diagnostics tab to the settings page
 */
private function add_diagnostics_tab() {
    add_action('wsvl_settings_tabs', function($current_tab) {
        echo '<a href="?page=wsvl-settings&tab=diagnostics" class="nav-tab ' . ($current_tab === 'diagnostics' ? 'nav-tab-active' : '') . '">Diagnostics</a>';
    });
    
    add_action('wsvl_settings_content', function($current_tab) {
        if ($current_tab !== 'diagnostics') return;
        
        $video_slug = isset($_GET['check_video']) ? sanitize_text_field($_GET['check_video']) : '';
        $results = null;
        
        if (!empty($video_slug)) {
            $product_video_manager = new \WSVL\Admin\ProductVideoManager();
            $results = $product_video_manager->check_video_file($video_slug);
        }
        
        ?>
        <div class="wrap">
            <h2>Video Diagnostics</h2>
            
            <p>Use this tool to diagnose issues with video playback.</p>
            
            <form method="get">
                <input type="hidden" name="page" value="wsvl-settings">
                <input type="hidden" name="tab" value="diagnostics">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Check Video by Slug</th>
                        <td>
                            <input type="text" name="check_video" value="<?php echo esc_attr($video_slug); ?>" placeholder="Enter video slug" class="regular-text">
                            <p class="description">Enter the video slug to check if it's properly configured and accessible.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="Run Diagnostics">
                </p>
            </form>
            
            <?php if ($results): ?>
                <div class="card">
                    <h3>Diagnostic Results for "<?php echo esc_html($video_slug); ?>"</h3>
                    
                    <div class="inside">
                        <?php foreach ($results['messages'] as $message): ?>
                            <p><?php echo esc_html($message); ?></p>
                        <?php endforeach; ?>
                        
                        <?php if ($results['success']): ?>
                            <p style="color: green; font-weight: bold;">✅ Video should be playable!</p>
                        <?php else: ?>
                            <p style="color: red; font-weight: bold;">❌ Video cannot be played due to errors listed above.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Server Information</h3>
                    
                    <div class="inside">
                        <p><strong>WordPress Version:</strong> <?php echo esc_html(get_bloginfo('version')); ?></p>
                        <p><strong>PHP Version:</strong> <?php echo esc_html(phpversion()); ?></p>
                        <p><strong>Web Server:</strong> <?php echo esc_html($_SERVER['SERVER_SOFTWARE']); ?></p>
                        <p><strong>WSVL_PRIVATE_VIDEOS_DIR:</strong> <?php echo esc_html(WSVL_PRIVATE_VIDEOS_DIR); ?></p>
                        <p><strong>WP_CONTENT_DIR:</strong> <?php echo esc_html(WP_CONTENT_DIR); ?></p>
                        <p><strong>PHP file_uploads:</strong> <?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?></p>
                        <p><strong>PHP upload_max_filesize:</strong> <?php echo esc_html(ini_get('upload_max_filesize')); ?></p>
                        <p><strong>PHP post_max_size:</strong> <?php echo esc_html(ini_get('post_max_size')); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
} 