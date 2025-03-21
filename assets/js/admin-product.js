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
        }

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Only MP4, WebM, and OGG videos are allowed.');
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
            const formData = new FormData();
            formData.append('action', 'wsvl_upload_video');
            formData.append('nonce', wsvlAdmin.nonce);
            formData.append('video', file);

            this.statusSpan.text(wsvlAdmin.uploading);
            this.progressBar.show();
            this.progressBar.find('.wsvl-progress').css('width', '0%');

            try {
                const response = await $.ajax({
                    url: wsvlAdmin.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: () => {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', (evt) => {
                            if (evt.lengthComputable) {
                                const percentComplete = (evt.loaded / evt.total) * 100;
                                this.updateProgress(percentComplete);
                            }
                        }, false);
                        return xhr;
                    }
                });

                if (response.success) {
                    this.videoFileInput.val(response.data.file);
                    this.videoPreview.html(`
                        <p class="description">
                            ${wsvlAdmin.uploadComplete}<br>
                            <strong>${response.data.file}</strong>
                        </p>
                    `);
                    this.statusSpan.text('');
                    this.progressBar.hide();
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                this.statusSpan.text(wsvlAdmin.uploadError);
                this.progressBar.hide();
                console.error('Upload error:', error);
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