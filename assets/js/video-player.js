(function($) {
    'use strict';

    class VideoPlayer {
        constructor() {
            this.video = document.querySelector('video[data-wsvl-video]');
            if (!this.video) return;

            this.videoSlug = this.video.getAttribute('data-video-slug');
            this.token = this.video.getAttribute('data-token');
            this.chunkSize = 1024 * 1024; // 1MB chunks
            this.currentChunk = 0;
            this.isLoading = false;
            this.isPlaying = false;
            this.videoUrl = null;
            this.mediaSource = null;
            this.sourceBuffer = null;
            this.playPromise = null;

            this.init();
        }

        init() {
            // Show loading indicator
            this.showLoading();

            // Handle video loading events
            this.video.addEventListener('loadstart', () => this.showLoading());
            this.video.addEventListener('canplay', () => this.hideLoading());
            this.video.addEventListener('error', (e) => this.handleError(e));
            this.video.addEventListener('timeupdate', () => this.checkBuffer());
            this.video.addEventListener('waiting', () => this.handleBuffering());
            this.video.addEventListener('playing', () => this.handlePlaying());
            this.video.addEventListener('pause', () => this.handlePause());

            // Disable right-click
            this.video.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                this.showMessage(wsvlVideoPlayer.i18n.downloadDisabled);
            });

            // Disable keyboard shortcuts
            this.video.addEventListener('keydown', (e) => {
                if (e.key === 's' || (e.ctrlKey && e.key === 's')) {
                    e.preventDefault();
                    this.showMessage(wsvlVideoPlayer.i18n.downloadDisabled);
                }
            });

            // Disable drag and drop
            this.video.addEventListener('dragstart', (e) => {
                e.preventDefault();
            });

            // Prevent video download
            this.video.addEventListener('loadedmetadata', () => {
                this.video.setAttribute('controlsList', 'nodownload');
            });

            // Add custom controls
            this.addCustomControls();

            // Initialize MediaSource
            this.initMediaSource();
        }

        initMediaSource() {
            this.mediaSource = new MediaSource();
            this.video.src = URL.createObjectURL(this.mediaSource);

            this.mediaSource.addEventListener('sourceopen', () => {
                this.sourceBuffer = this.mediaSource.addSourceBuffer('video/mp4; codecs="avc1.42E01E, mp4a.40.2"');
                
                this.sourceBuffer.addEventListener('updateend', () => {
                    if (this.sourceBuffer && !this.sourceBuffer.updating) {
                        this.loadVideoChunk();
                    }
                });

                // Start loading the first chunk
                this.loadVideoChunk();
            });
        }

        async loadVideoChunk() {
            if (this.isLoading) return;
            this.isLoading = true;

            try {
                const response = await $.ajax({
                    url: wsvlVideoPlayer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsvl_stream_video',
                        nonce: wsvlVideoPlayer.nonce,
                        video_slug: this.videoSlug,
                        token: this.token,
                        chunk: this.currentChunk,
                        size: this.chunkSize
                    },
                    xhrFields: {
                        responseType: 'blob'
                    }
                });

                if (response instanceof Blob) {
                    const arrayBuffer = await response.arrayBuffer();
                    
                    if (this.sourceBuffer && !this.sourceBuffer.updating) {
                        this.sourceBuffer.appendBuffer(arrayBuffer);
                        this.currentChunk++;
                    }
                }
            } catch (error) {
                console.error('Error loading video chunk:', error);
                this.showMessage(wsvlVideoPlayer.i18n.loadError);
            } finally {
                this.isLoading = false;
            }
        }

        async togglePlay() {
            try {
                if (this.video.paused) {
                    // If there's a pending play promise, wait for it
                    if (this.playPromise !== null) {
                        await this.playPromise;
                    }
                    
                    // Start playing
                    this.playPromise = this.video.play();
                    await this.playPromise;
                    this.isPlaying = true;
                } else {
                    // If there's a pending play promise, wait for it
                    if (this.playPromise !== null) {
                        await this.playPromise;
                    }
                    
                    // Pause the video
                    this.video.pause();
                    this.isPlaying = false;
                }
            } catch (error) {
                console.error('Playback error:', error);
                this.showMessage(wsvlVideoPlayer.i18n.playError);
            } finally {
                this.playPromise = null;
            }
        }

        handleBuffering() {
            this.isPlaying = false;
            this.showLoading();
        }

        handlePlaying() {
            this.isPlaying = true;
            this.hideLoading();
        }

        handlePause() {
            this.isPlaying = false;
        }

        checkBuffer() {
            if (this.video.buffered.length > 0) {
                const bufferedEnd = this.video.buffered.end(this.video.buffered.length - 1);
                const currentTime = this.video.currentTime;

                // If we're near the end of the buffer, load the next chunk
                if (bufferedEnd - currentTime < 5) {
                    this.loadVideoChunk();
                }
            }
        }

        showLoading() {
            const loadingIndicator = this.video.parentElement.querySelector('.wsvl-video-loading');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'block';
            }
        }

        hideLoading() {
            const loadingIndicator = this.video.parentElement.querySelector('.wsvl-video-loading');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        }

        handleError(e) {
            console.error('Video loading error:', e);
            this.hideLoading();
            this.showMessage(wsvlVideoPlayer.i18n.loadError);
        }

        addCustomControls() {
            // Create custom controls container
            const controls = document.createElement('div');
            controls.className = 'wsvl-video-controls';
            controls.style.cssText = `
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(0, 0, 0, 0.7);
                padding: 10px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                z-index: 1000;
            `;

            // Add play/pause button
            const playPauseBtn = document.createElement('button');
            playPauseBtn.innerHTML = '▶';
            playPauseBtn.style.cssText = `
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 20px;
                padding: 5px 10px;
            `;
            playPauseBtn.addEventListener('click', () => this.togglePlay());

            // Add progress bar
            const progress = document.createElement('div');
            progress.className = 'wsvl-progress';
            progress.style.cssText = `
                flex: 1;
                height: 5px;
                background: rgba(255, 255, 255, 0.3);
                margin: 0 10px;
                cursor: pointer;
            `;
            progress.addEventListener('click', (e) => this.seek(e));

            // Add time display
            const time = document.createElement('div');
            time.className = 'wsvl-time';
            time.style.cssText = `
                color: white;
                font-size: 14px;
            `;

            // Add volume control
            const volume = document.createElement('input');
            volume.type = 'range';
            volume.min = 0;
            volume.max = 1;
            volume.step = 0.1;
            volume.value = 1;
            volume.style.cssText = `
                width: 100px;
                margin: 0 10px;
            `;
            volume.addEventListener('input', () => this.setVolume(volume.value));

            // Add elements to controls
            controls.appendChild(playPauseBtn);
            controls.appendChild(progress);
            controls.appendChild(time);
            controls.appendChild(volume);

            // Add controls to video container
            const container = this.video.parentElement;
            container.style.position = 'relative';
            container.appendChild(controls);

            // Update progress and time
            this.video.addEventListener('timeupdate', () => {
                const percent = (this.video.currentTime / this.video.duration) * 100;
                progress.style.background = `linear-gradient(to right, white ${percent}%, rgba(255, 255, 255, 0.3) ${percent}%)`;
                time.textContent = this.formatTime(this.video.currentTime) + ' / ' + this.formatTime(this.video.duration);
            });
        }

        seek(e) {
            const rect = e.target.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.video.currentTime = percent * this.video.duration;
        }

        setVolume(value) {
            this.video.volume = value;
        }

        formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            seconds = Math.floor(seconds % 60);
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }

        updateSecurity() {
            // Disable native controls
            this.video.removeAttribute('controls');
            
            // Add additional security attributes
            this.video.setAttribute('playsinline', '');
            this.video.setAttribute('webkit-playsinline', '');
            this.video.setAttribute('x5-playsinline', '');
        }

        showMessage(message) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                z-index: 10000;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    }

    // Initialize video player when document is ready
    $(document).ready(() => {
        new VideoPlayer();
    });
})(jQuery); 