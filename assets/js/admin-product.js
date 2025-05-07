(function($) {
    'use strict';

    class VideoUploader {
        constructor() {
            this.uploadButton = $('#wsvl-upload-video');
            this.fileInput = $('<input type="file" accept="video/mp4,video/webm,video/ogg" style="display: none;">');
            this.statusSpan = $('.wsvl-upload-status');
            this.videoPreview = $('.wsvl-video-preview');
            this.videoFileInput = $('#_video_file');
            this.videoSlugInput = $('#_video_slug');
            this.progressBar = $('<div class="wsvl-progress-bar"><div class="wsvl-progress"></div></div>');

            this.init();
        }

        init() {
            this.uploadButton.on('click', () => this.fileInput.click());
            this.fileInput.on('change', (e) => this.handleFileSelect(e));
            
            // Add progress bar after the upload button
            this.uploadButton.after(this.progressBar);
            
            // Add some basic styles
            this.addStyles();
        }

        addStyles() {
            const styles = `
                .wsvl-progress-bar {
                    width: 100%;
                    height: 20px;
                    background-color: #f0f0f0;
                    border-radius: 3px;
                    margin: 10px 0;
                    display: none;
                }
                .wsvl-progress {
                    width: 0;
                    height: 100%;
                    background-color: #2271b1;
                    border-radius: 3px;
                    transition: width 0.3s ease-in-out;
                }
                .wsvl-upload-status {
                    display: block;
                    margin: 5px 0;
                    color: #666;
                }
                .wsvl-video-preview {
                    margin-top: 10px;
                }
            `;
            
            const styleSheet = document.createElement('style');
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        }

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!allowedTypes.includes(file.type)) {
                alert(wsvlAdmin.uploadError + ' Only MP4, WebM, and OGG videos are allowed.');
                return;
            }

            // Validate file size (max 2GB)
            const maxSize = 2 * 1024 * 1024 * 1024; // 2GB in bytes
            if (file.size > maxSize) {
                alert(wsvlAdmin.uploadError + ' File size must be less than 2GB.');
                return;
            }

            // Generate a slug from the filename
            const slug = this.generateSlug(file.name);
            this.videoSlugInput.val(slug);

            this.uploadFile(file);
        }

        generateSlug(filename) {
            // Remove file extension
            const nameWithoutExt = filename.replace(/\.[^/.]+$/, '');
            // Convert to lowercase and replace spaces/special chars with hyphens
            return nameWithoutExt
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
        }

        async uploadFile(file) {
            const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            let uploadedChunks = 0;

            this.statusSpan.text(wsvlAdmin.uploading);
            this.progressBar.show();
            this.progressBar.find('.wsvl-progress').css('width', '0%');

            try {
                console.log('Starting chunked upload to:', wsvlAdmin.ajaxUrl);
                console.log('File details:', {
                    name: file.name,
                    type: file.type,
                    size: file.size,
                    totalChunks: totalChunks
                });

                // First, initiate the upload
                const initResponse = await $.ajax({
                    url: wsvlAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wsvl_init_upload',
                        nonce: wsvlAdmin.nonce,
                        filename: file.name,
                        totalChunks: totalChunks,
                        totalSize: file.size
                    }
                });

                if (!initResponse.success) {
                    throw new Error(initResponse.data || 'Failed to initialize upload');
                }

                const uploadId = initResponse.data.uploadId;

                // Upload chunks
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('action', 'wsvl_upload_chunk');
                    formData.append('nonce', wsvlAdmin.nonce);
                    formData.append('uploadId', uploadId);
                    formData.append('chunkIndex', chunkIndex);
                    formData.append('chunk', chunk);

                    const chunkResponse = await $.ajax({
                        url: wsvlAdmin.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: () => {
                            const xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', (evt) => {
                                if (evt.lengthComputable) {
                                    const chunkProgress = (evt.loaded / evt.total) * (100 / totalChunks);
                                    const totalProgress = (uploadedChunks * (100 / totalChunks)) + chunkProgress;
                                    this.updateProgress(totalProgress);
                                }
                            }, false);
                            return xhr;
                        }
                    });

                    if (!chunkResponse.success) {
                        throw new Error(chunkResponse.data || 'Failed to upload chunk');
                    }

                    uploadedChunks++;
                }

                // Complete the upload
                const completeResponse = await $.ajax({
                    url: wsvlAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wsvl_complete_upload',
                        nonce: wsvlAdmin.nonce,
                        uploadId: uploadId
                    }
                });

                if (!completeResponse.success) {
                    throw new Error(completeResponse.data || 'Failed to complete upload');
                }

                this.videoFileInput.val(completeResponse.data.file);
                this.videoPreview.html(`
                    <p class="description">
                        ${wsvlAdmin.uploadComplete}<br>
                        <strong>${completeResponse.data.file}</strong>
                    </p>
                `);
                this.statusSpan.text('');
                this.progressBar.hide();

            } catch (error) {
                console.error('Upload error details:', {
                    error: error,
                    status: error.status,
                    statusText: error.statusText,
                    responseText: error.responseText
                });
                this.statusSpan.text(wsvlAdmin.uploadError);
                this.progressBar.hide();
                console.error('Upload error:', error);
                alert(error.message || wsvlAdmin.uploadError);
            }
        }

        updateProgress(percent) {
            this.progressBar.find('.wsvl-progress').css('width', percent + '%');
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new VideoUploader();
    });

})(jQuery); 