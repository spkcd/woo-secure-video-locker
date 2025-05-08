<?php
namespace WSVL\Admin;

class ChunkedUploader {
    private const CHUNK_SIZE_DEFAULT = 5 * 1024 * 1024; // 5MB default chunks
    private $upload_errors = [];

    public function __construct() {
        add_action('wp_ajax_wsvl_upload_chunk', [$this, 'handle_chunk_upload']);
    }

    /**
     * Handle chunked file upload via AJAX
     */
    public function handle_chunk_upload() {
        // Security check
        check_ajax_referer('wsvl-upload-video', 'nonce');

        // Check user permissions
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_wsvl_videos')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        // Start time measurement for performance tracking
        $start_time = microtime(true);
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit(WP_CONTENT_DIR) . 'private-videos/chunks/';
        
        // Create chunks directory if it doesn't exist
        if (!file_exists($target_dir)) {
            if (!wp_mkdir_p($target_dir)) {
                $this->log_error('Failed to create chunks directory: ' . $target_dir);
                wp_send_json_error(['message' => 'Server error: Unable to create upload directory.']);
                return;
            }
            
            // Create htaccess to protect the chunks
            $htaccess = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($target_dir . '.htaccess', $htaccess);
        }
        
        // Get chunk info
        $chunk = isset($_REQUEST['chunk']) ? intval($_REQUEST['chunk']) : 0;
        $chunks = isset($_REQUEST['chunks']) ? intval($_REQUEST['chunks']) : 1;
        $fileName = isset($_REQUEST['name']) ? sanitize_file_name($_REQUEST['name']) : '';
        $fileId = isset($_REQUEST['fileId']) ? sanitize_key($_REQUEST['fileId']) : md5($fileName . time());
        
        // Make sure we have the file and it's valid
        if (empty($fileName) || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'File upload error: ' . $this->get_upload_error_message($_FILES['file']['error'] ?? 0);
            $this->log_error($error_message . ' - File: ' . $fileName);
            wp_send_json_error(['message' => $error_message]);
            return;
        }
        
        // Temporary chunk file path
        $temp_file_path = $target_dir . $fileId . '.part' . $chunk;
        
        // Final file path (without extension to be safe)
        $final_dir = trailingslashit(WP_CONTENT_DIR) . 'private-videos/';
        $final_name = pathinfo($fileName, PATHINFO_FILENAME);
        $final_ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // Check extension
        $allowed_types = ['mp4', 'webm', 'mov', 'ogv', 'm4v'];
        if (!in_array(strtolower($final_ext), $allowed_types)) {
            wp_send_json_error([
                'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)
            ]);
            return;
        }
        
        // Sanitize final filename 
        $final_name = sanitize_title($final_name);
        $final_filename = $final_name . '.' . $final_ext;
        $final_path = $final_dir . $final_filename;
        
        // Create final directory if it doesn't exist
        if (!file_exists($final_dir)) {
            if (!wp_mkdir_p($final_dir)) {
                $this->log_error('Failed to create final directory: ' . $final_dir);
                wp_send_json_error(['message' => 'Server error: Unable to create videos directory.']);
                return;
            }
            
            // Create htaccess to protect the videos directory
            $htaccess = "Order Deny,Allow\nDeny from all\n<Files \"*.php\">\nOrder Allow,Deny\nDeny from all\n</Files>";
            file_put_contents($final_dir . '.htaccess', $htaccess);
        }
        
        // Handle this chunk
        if (move_uploaded_file($_FILES['file']['tmp_name'], $temp_file_path)) {
            // Check if this is the last chunk, and if so, combine them
            if ($chunk == $chunks - 1) {
                // Attempt to combine chunks
                if (!$this->combine_chunks($fileId, $chunks, $target_dir, $final_path)) {
                    $this->log_error('Failed to combine chunks for file: ' . $fileName);
                    wp_send_json_error(['message' => 'Failed to process uploaded file.']);
                    return;
                }
                
                // Log upload time for performance monitoring
                $total_time = microtime(true) - $start_time;
                $this->log_success('Upload completed in ' . round($total_time, 2) . 's for file: ' . $final_filename);
                
                // Generate a slug from the filename if not already present
                $generated_slug = $this->generate_video_slug($final_name);
                
                // Return success with file info
                wp_send_json_success([
                    'file' => $final_filename,
                    'path' => $final_path,
                    'url' => site_url('/secure-videos/' . $final_name . '/'),
                    'message' => 'File uploaded successfully.',
                    'time' => round($total_time, 2),
                    'slug' => $generated_slug
                ]);
            } else {
                // Return progress info
                wp_send_json_success([
                    'chunk' => $chunk,
                    'chunks' => $chunks,
                    'fileId' => $fileId,
                    'message' => 'Chunk ' . ($chunk + 1) . ' of ' . $chunks . ' uploaded successfully.'
                ]);
            }
        } else {
            $this->log_error('Failed to move uploaded chunk ' . $chunk . ' for file: ' . $fileName);
            wp_send_json_error([
                'message' => 'Failed to move uploaded chunk. Server may be out of disk space or have permission issues.'
            ]);
        }
    }
    
    /**
     * Combine file chunks into the final file
     */
    private function combine_chunks($fileId, $chunks, $temp_dir, $final_path) {
        // If the final file already exists, create a backup before overwriting
        if (file_exists($final_path)) {
            $backup_path = $final_path . '.bak';
            if (!rename($final_path, $backup_path)) {
                $this->log_error('Failed to backup existing file: ' . $final_path);
            }
        }
        
        $success = false;
        $out = fopen($final_path, 'wb');
        
        if ($out) {
            // Use a buffer for more efficient combining
            $buffer_size = 8192; // 8KB buffer
            
            for ($i = 0; $i < $chunks; $i++) {
                $chunk_file = $temp_dir . $fileId . '.part' . $i;
                if (!file_exists($chunk_file)) {
                    $this->log_error('Chunk file missing: ' . $chunk_file);
                    continue;
                }
                
                $in = fopen($chunk_file, 'rb');
                if ($in) {
                    while (!feof($in)) {
                        $buff = fread($in, $buffer_size);
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    
                    // Clean up chunk immediately to free disk space
                    @unlink($chunk_file);
                }
            }
            
            fclose($out);
            
            // Set permissions
            chmod($final_path, 0644);
            $success = true;
            
            // Remove backup if combining was successful
            if (isset($backup_path) && file_exists($backup_path)) {
                @unlink($backup_path);
            }
        } else {
            $this->log_error('Failed to open output file for writing: ' . $final_path);
        }
        
        // Clean up any remaining chunks
        for ($i = 0; $i < $chunks; $i++) {
            $chunk_file = $temp_dir . $fileId . '.part' . $i;
            if (file_exists($chunk_file)) {
                @unlink($chunk_file);
            }
        }
        
        return $success;
    }
    
    /**
     * Log a successful upload for monitoring
     */
    private function log_success($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WSVL Upload Success: ' . $message);
        }
    }
    
    /**
     * Log an upload error for debugging
     */
    private function log_error($message) {
        error_log('WSVL Upload Error: ' . $message);
        $this->upload_errors[] = $message;
    }
    
    /**
     * Get human-readable upload error message
     */
    private function get_upload_error_message($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error (code: ' . $code . ')';
        }
    }

    /**
     * Generate a unique video slug from the filename
     */
    private function generate_video_slug($filename) {
        // Start with the sanitized filename (already sanitized as $final_name)
        $slug = sanitize_title($filename);
        
        // Remove any file extension that might be present
        $slug = preg_replace('/\.[^.]+$/', '', $slug);
        
        // Check if this slug already exists in the database
        global $wpdb;
        $i = 0;
        $temp_slug = $slug;
        
        while ($this->slug_exists($temp_slug) && $i < 100) {
            $i++;
            $temp_slug = $slug . '-' . $i;
        }
        
        return $temp_slug;
    }
    
    /**
     * Check if a video slug already exists in any product
     */
    private function slug_exists($slug) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value = %s",
            '_video_slug',
            $slug
        ));
        
        return $count > 0;
    }
} 