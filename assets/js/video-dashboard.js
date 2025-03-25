/**
 * Video Dashboard JS
 * Handles video streaming and security
 */
(function($) {
    'use strict';

    // Global variables
    let videoChunks = {};
    let currentChunk = {};
    let refreshTimer = {};
    
    /**
     * Initialize video dashboard
     */
    function init() {
        // Apply protection to all videos
        initializeSecureVideos();
        
        // Refresh URL periodically to maintain session validity
        setInterval(function() {
            $('.wsvl-video-container video').each(function() {
                const videoSlug = $(this).data('slug');
                if (videoSlug) {
                    refreshVideoUrl(videoSlug);
                }
            });
        }, 30 * 60 * 1000); // Refresh every 30 minutes
    }
    
    /**
     * Initialize secure videos with chunked loading
     */
    function initializeSecureVideos() {
        $('.wsvl-video-container video').each(function() {
            const video = this;
            const videoSlug = $(video).data('slug');
            
            if (!videoSlug) return;
            
            // Initialize video tracking for this slug
            videoChunks[videoSlug] = 0;
            currentChunk[videoSlug] = 0;
            
            // Add event listeners for chunked loading
            video.addEventListener('timeupdate', function() {
                handleTimeUpdate(video, videoSlug);
            });
            
            video.addEventListener('error', function() {
                console.log('Video error occurred. Refreshing URL.');
                refreshVideoUrl(videoSlug);
            });
            
            // Make sure browser can't save the video
            video.setAttribute('controlsList', 'nodownload');
            video.disablePictureInPicture = true;
            
            // Add anti-devtools detection
            addAntiDevToolsProtection();
        });
    }
    
    /**
     * Handle video timeupdate event to load next chunk when needed
     */
    function handleTimeUpdate(video, videoSlug) {
        const duration = video.duration;
        const currentTime = video.currentTime;
        
        if (isNaN(duration)) return;
        
        // Calculate current chunk and preload next chunk
        const secondsPerChunk = 10; // Assuming 10-second chunks
        const totalChunks = Math.ceil(duration / secondsPerChunk);
        const currentChunkIndex = Math.floor(currentTime / secondsPerChunk);
        const nextChunkIndex = currentChunkIndex + 1;
        
        // If we're approaching the end of the current chunk, load the next chunk
        if (nextChunkIndex < totalChunks && 
            currentChunkIndex === currentChunk[videoSlug] && 
            currentTime % secondsPerChunk > secondsPerChunk * 0.8) {
            
            currentChunk[videoSlug] = nextChunkIndex;
            refreshVideoUrl(videoSlug, nextChunkIndex);
        }
    }
    
    /**
     * Add anti-devtools protection to detect developer tools
     */
    function addAntiDevToolsProtection() {
        let devtoolsOpen = false;
        
        // Function to check for devtools using window size
        function checkDevTools() {
            const threshold = 160;
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;
            
            if (widthThreshold || heightThreshold) {
                if (!devtoolsOpen) {
                    devtoolsOpen = true;
                    handleDevtoolsOpen();
                }
            } else {
                devtoolsOpen = false;
            }
        }
        
        // Add event listener for resize to detect devtools opening
        window.addEventListener('resize', checkDevTools);
        setInterval(checkDevTools, 1000);
    }
    
    /**
     * Handle detection of devtools opening
     */
    function handleDevtoolsOpen() {
        // Display warning
        if (!document.getElementById('devtools-warning')) {
            const warning = document.createElement('div');
            warning.id = 'devtools-warning';
            warning.innerText = 'Developer tools detected. This activity is being recorded for security purposes.';
            document.body.appendChild(warning);
            
            // Remove warning after 5 seconds
            setTimeout(function() {
                if (document.getElementById('devtools-warning')) {
                    document.getElementById('devtools-warning').remove();
                }
            }, 5000);
            
            // Log activity
            try {
                console.log('Developer tools opened at ' + new Date().toString());
            } catch(e) {}
        }
    }
    
    /**
     * Refresh video URL via AJAX
     */
    function refreshVideoUrl(videoSlug, chunkIndex = 0) {
        clearTimeout(refreshTimer[videoSlug]);
        
        $.ajax({
            url: wsvlData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'refresh_video_url',
                nonce: wsvlData.nonce,
                video_slug: videoSlug
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    updateVideoSource(videoSlug, response.data.url, chunkIndex);
                }
            },
            error: function() {
                console.log('Error refreshing video URL');
                
                // Try again in 10 seconds
                refreshTimer[videoSlug] = setTimeout(function() {
                    refreshVideoUrl(videoSlug, chunkIndex);
                }, 10000);
            }
        });
    }
    
    /**
     * Update video source with new URL
     */
    function updateVideoSource(videoSlug, url, chunkIndex) {
        const video = $(`video[data-slug="${videoSlug}"]`)[0];
        if (!video) return;
        
        // Add chunk parameter to URL
        const chunkUrl = url + `&chunk=${chunkIndex}`;
        
        // Only update if this is the current chunk or we're preloading the next one
        if (chunkIndex === currentChunk[videoSlug] || chunkIndex === currentChunk[videoSlug] + 1) {
            const currentTime = video.currentTime;
            const wasPaused = video.paused;
            
            // Update the source
            $(`video[data-slug="${videoSlug}"] source`).attr('src', chunkUrl);
            video.load();
            
            // If this is the current chunk, restore playback state
            if (chunkIndex === currentChunk[videoSlug]) {
                video.currentTime = currentTime;
                if (!wasPaused) {
                    video.play().catch(function(e) {
                        // Handle autoplay restrictions
                        console.log('Autoplay prevented by browser, user interaction required');
                    });
                }
            }
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize dashicons
        if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
            // Force load dashicons if WordPress editor is available
            wp.editor.initialize();
        }
        
        // Make sure all videos are responsive
        $('.wsvl-video-container').each(function() {
            $(this).find('canvas').css({
                'width': '100%',
                'height': '100%'
            });
        });
        
        // Handle window resize
        $(window).on('resize', function() {
            $('.wsvl-video-container').each(function() {
                const width = $(this).width();
                const height = width * 9 / 16; // 16:9 aspect ratio
                
                $(this).find('.wsvl-video-wrapper').css({
                    'height': 0,
                    'padding-top': '56.25%'
                });
            });
        }).trigger('resize');
    });
    
})(jQuery); 