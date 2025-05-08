/**
 * Chunked file uploader for large video files
 */
(function($) {
    'use strict';

    // Initialize Plupload when the DOM is ready
    $(document).ready(function() {
        if (!$('#wsvl-chunked-uploader').length) {
            return;
        }

        // Set up default and adaptive options
        const MAX_RETRIES = 3;
        const DEFAULT_CHUNK_SIZE = wsvl_upload.chunk_size || 2097152; // 2MB default if not set
        const MAX_CHUNK_SIZE = 8388608; // 8MB max chunk size
        const MIN_CHUNK_SIZE = 1048576; // 1MB min chunk size
        
        // Performance optimization - adapt chunk size based on connection speed
        let adaptiveChunkSize = DEFAULT_CHUNK_SIZE;
        let uploadTimes = [];
        let totalFailedChunks = 0;
        let currentRetries = {};

        // Set up the uploader
        var uploader = new plupload.Uploader({
            runtimes: 'html5',
            browse_button: 'wsvl-select-video',
            container: 'wsvl-chunked-uploader',
            url: wsvl_upload.ajax_url,
            chunk_size: adaptiveChunkSize,
            max_file_size: wsvl_upload.max_file_size,
            multi_selection: false,
            filters: {
                mime_types: [
                    { title: 'Video files', extensions: 'mp4,webm,mov,ogv,m4v' }
                ]
            },
            multipart: true,
            multipart_params: {
                action: 'wsvl_upload_chunk',
                nonce: wsvl_upload.nonce,
                fileId: ''
            },
            // Add tuning parameters
            max_retries: MAX_RETRIES,
            prevent_duplicates: true
        });

        // Initialize the uploader
        uploader.init();

        // When files are added to the queue
        uploader.bind('FilesAdded', function(up, files) {
            $('#wsvl-file-list').empty();
            
            // Only allow one file at a time
            if (files.length > 1) {
                files.splice(1);
            }
            
            // Create a unique file ID
            var fileId = plupload.guid();
            up.settings.multipart_params.fileId = fileId;
            
            // Reset tracking variables
            uploadTimes = [];
            totalFailedChunks = 0;
            currentRetries = {};
            
            // Display the file in the list
            $.each(files, function(i, file) {
                $('#wsvl-file-list').append(
                    '<div id="' + file.id + '" class="wsvl-file-item">' +
                        '<div class="file-name">' + file.name + ' (' + plupload.formatSize(file.size) + ')</div>' +
                        '<div class="progress-bar"><div class="progress"></div></div>' +
                        '<div class="status">Ready to upload</div>' +
                        '<div class="stats"></div>' +
                    '</div>'
                );
                
                // Show upload info immediately
                $('#wsvl-upload-message').html(
                    '<div class="wsvl-info">' +
                    '<p>Preparing to upload your video. Please don\'t navigate away from this page.</p>' +
                    '<p>Initial chunk size: ' + plupload.formatSize(adaptiveChunkSize) + '</p>' +
                    '</div>'
                );
            });
            
            // Start the upload automatically
            uploader.start();
        });

        // Before a chunk is uploaded
        uploader.bind('BeforeUpload', function(up, file) {
            // Set dynamic chunk size based on previous upload performance
            if (uploadTimes.length >= 3) {
                // Calculate average upload speed from the last few chunks
                const recentTimes = uploadTimes.slice(-3);
                const avgTime = recentTimes.reduce((sum, time) => sum + time, 0) / recentTimes.length;
                
                // Adjust chunk size based on performance
                if (avgTime < 2.0 && adaptiveChunkSize < MAX_CHUNK_SIZE) {
                    // Good speed, increase chunk size
                    adaptiveChunkSize = Math.min(adaptiveChunkSize * 1.5, MAX_CHUNK_SIZE);
                    up.settings.chunk_size = adaptiveChunkSize;
                    $('#' + file.id + ' .stats').html('Optimizing: Increased chunk size to ' + plupload.formatSize(adaptiveChunkSize));
                } else if (avgTime > 5.0 && adaptiveChunkSize > MIN_CHUNK_SIZE) {
                    // Slow speed, decrease chunk size
                    adaptiveChunkSize = Math.max(adaptiveChunkSize / 1.5, MIN_CHUNK_SIZE);
                    up.settings.chunk_size = adaptiveChunkSize;
                    $('#' + file.id + ' .stats').html('Optimizing: Decreased chunk size to ' + plupload.formatSize(adaptiveChunkSize));
                }
            }
        });

        // While a file is being uploaded
        uploader.bind('UploadProgress', function(up, file) {
            $('#' + file.id + ' .progress').width(file.percent + '%');
            $('#' + file.id + ' .status').html('Uploading: ' + file.percent + '%');
            
            // Calculate and display estimated time remaining
            if (file.size > 0 && file.loaded > 0) {
                const bytesPerSecond = file.bytesPerSec || 0;
                if (bytesPerSecond > 0) {
                    const secondsRemaining = Math.round((file.size - file.loaded) / bytesPerSecond);
                    if (secondsRemaining > 0) {
                        let timeStr = '';
                        if (secondsRemaining > 60) {
                            const mins = Math.floor(secondsRemaining / 60);
                            const secs = secondsRemaining % 60;
                            timeStr = mins + 'm ' + secs + 's';
                        } else {
                            timeStr = secondsRemaining + 's';
                        }
                        $('#' + file.id + ' .stats').html('Speed: ' + plupload.formatSize(bytesPerSecond) + '/s • Time left: ~' + timeStr);
                    }
                }
            }
        });

        // When a chunk is uploaded
        uploader.bind('ChunkUploaded', function(up, file, info) {
            var response = JSON.parse(info.response);
            const chunkEndTime = new Date().getTime();
            
            // If we're tracking the start time, calculate performance
            if (file.chunkStartTime) {
                const chunkTime = (chunkEndTime - file.chunkStartTime) / 1000; // seconds
                uploadTimes.push(chunkTime);
                // Keep only last 5 times
                if (uploadTimes.length > 5) {
                    uploadTimes.shift();
                }
            }
            
            if (response.success) {
                $('#' + file.id + ' .status').html(response.data.message);
                delete currentRetries[file.id + '_' + info.offset];
            } else {
                // Handle chunk upload failure with retry
                const chunkId = file.id + '_' + info.offset;
                currentRetries[chunkId] = (currentRetries[chunkId] || 0) + 1;
                totalFailedChunks++;
                
                if (currentRetries[chunkId] <= MAX_RETRIES) {
                    $('#' + file.id + ' .status').html('Retrying chunk ' + (info.offset+1) + '/' + info.chunks + ' (attempt ' + currentRetries[chunkId] + '/' + MAX_RETRIES + ')');
                    
                    // Reduce chunk size if we're having failures
                    if (adaptiveChunkSize > MIN_CHUNK_SIZE && totalFailedChunks > 2) {
                        adaptiveChunkSize = Math.max(adaptiveChunkSize / 2, MIN_CHUNK_SIZE);
                        up.settings.chunk_size = adaptiveChunkSize;
                        $('#' + file.id + ' .stats').html('Connection issues detected. Reduced chunk size to ' + plupload.formatSize(adaptiveChunkSize));
                    }
                    
                    // This will make plupload retry the chunk
                    return false;
                } else {
                    $('#' + file.id + ' .status').html('Error: ' + response.data.message + ' (after ' + MAX_RETRIES + ' attempts)');
                    $('#' + file.id + ' .stats').html('Upload failed. Please try again with a better connection.');
                    up.stop();
                }
            }
            
            // Set start time for next chunk
            file.chunkStartTime = new Date().getTime();
        });

        // When a file is successfully uploaded
        uploader.bind('FileUploaded', function(up, file, info) {
            var response = JSON.parse(info.response);
            
            if (response.success) {
                $('#' + file.id + ' .status').html(
                    '<span style="color:green">✓ Upload complete!</span>'
                );
                
                // Add the file details to the form
                if ($('#wsvl-video-file').length) {
                    $('#wsvl-video-file').val(response.data.file);
                }
                
                // Auto-fill the slug field if available
                if (response.data.slug && $('#wsvl-video-slug').length) {
                    $('#wsvl-video-slug').val(response.data.slug);
                    // Add visual feedback that the slug was auto-filled
                    $('#wsvl-video-slug').css({
                        'background-color': '#f0fff0',
                        'border-color': '#c3e6c3'
                    }).animate({
                        'background-color': '#ffffff',
                        'border-color': '#7e8993'
                    }, 2000);
                }
                
                // Show a success message
                let message = '<div class="wsvl-success">' +
                    '<p>Video successfully uploaded! You can now save the product.</p>' +
                    '<p>Filename: ' + response.data.file + '</p>';
                
                // Add slug info to the success message
                if (response.data.slug) {
                    message += '<p>Generated Video Slug: ' + response.data.slug + '</p>';
                }
                
                // Add upload stats if available
                if (response.data.time) {
                    message += '<p>Upload completed in ' + response.data.time + ' seconds</p>';
                }
                
                message += '</div>';
                
                $('#wsvl-upload-message').html(message);
                $('#' + file.id + ' .stats').html('Upload time: ' + (response.data.time || 'N/A') + 's');
            } else {
                $('#' + file.id + ' .status').html('Error: ' + response.data.message);
            }
        });

        // If there are errors
        uploader.bind('Error', function(up, err) {
            let errorMsg = err.message;
            if (err.response) {
                try {
                    const response = JSON.parse(err.response);
                    if (response && response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                } catch (e) {
                    // Use original error message if we can't parse the response
                }
            }
            
            $('#wsvl-upload-message').html(
                '<div class="wsvl-error">' +
                '<p>Error: ' + errorMsg + (err.file ? ' - File: ' + err.file.name : '') + '</p>' +
                (err.code ? '<p>Code: ' + err.code + '</p>' : '') +
                '</div>'
            );
            
            up.refresh();
        });

        // Manual start button (optional)
        $('#wsvl-start-upload').on('click', function(e) {
            e.preventDefault();
            uploader.start();
        });
    });
})(jQuery); 