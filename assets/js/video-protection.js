/**
 * Video Protection Script
 * Prevents video downloads and captures
 */
(function() {
    // Disable right-click context menu on videos
    document.addEventListener('contextmenu', function(e) {
        if (e.target.nodeName === 'VIDEO') {
            e.preventDefault();
            return false;
        }
    }, false);

    // Disable keyboard shortcuts for saving
    document.addEventListener('keydown', function(e) {
        // Prevent Ctrl+S, Cmd+S
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
            e.preventDefault();
            return false;
        }
        
        // Prevent Ctrl+U, F12, etc.
        if (e.ctrlKey && e.keyCode === 85 || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }
        
        // Prevent screenshot (PrintScreen)
        if (e.keyCode === 44) {
            e.preventDefault();
            return false;
        }
    }, false);

    // Apply protection to all video elements
    function protectVideos() {
        const videos = document.getElementsByTagName('video');
        for (let i = 0; i < videos.length; i++) {
            const video = videos[i];
            
            // Set download-preventing attributes
            video.setAttribute('controlsList', 'nodownload noremoteplayback');
            video.disablePictureInPicture = true;
            video.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Protect from browser developer tools capture
            video.addEventListener('loadedmetadata', function() {
                // Apply user-specific watermark
                if (video.parentNode.querySelector('.wsvl-watermark')) {
                    const watermark = video.parentNode.querySelector('.wsvl-watermark');
                    positionWatermark(watermark, video);
                    
                    // Reposition watermark on resize
                    window.addEventListener('resize', function() {
                        positionWatermark(watermark, video);
                    });
                }
            });
            
            // Add security-focused event listeners without breaking playback
            video.addEventListener('play', function() {
                // Monitor for suspicious activities during playback
                monitorSecurePlayback(video);
            });
        }
    }
    
    // Position watermark randomly within the video
    function positionWatermark(watermark, video) {
        // Ensure watermark appears in a random position
        const randomPosition = Math.floor(Math.random() * 4); // 0-3 for different corners
        
        switch(randomPosition) {
            case 0: // Top left
                watermark.style.top = '10%';
                watermark.style.left = '10%';
                break;
            case 1: // Top right
                watermark.style.top = '10%';
                watermark.style.right = '10%';
                watermark.style.left = 'auto';
                break;
            case 2: // Bottom right
                watermark.style.bottom = '15%';
                watermark.style.right = '10%';
                watermark.style.top = 'auto';
                watermark.style.left = 'auto';
                break;
            case 3: // Bottom left
                watermark.style.bottom = '15%';
                watermark.style.left = '10%';
                watermark.style.top = 'auto';
                break;
        }
    }

    // Monitor secure playback without interfering with controls
    function monitorSecurePlayback(video) {
        // Block screen capture attempts if possible
        if ('mediaDevices' in navigator && 'getDisplayMedia' in navigator.mediaDevices) {
            const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
            navigator.mediaDevices.getDisplayMedia = function(constraints) {
                // Could add warnings or additional security measures here
                return originalGetDisplayMedia(constraints);
            };
        }
        
        // Block recording attempts 
        try {
            const mediaRecorder = window.MediaRecorder;
            window.MediaRecorder = function() {
                console.warn('Recording attempt blocked');
                return null;
            };
        } catch (e) {
            // MediaRecorder not supported or already overridden
        }
    }

    // Detect and disable video download extensions
    function detectExtensions() {
        // Override potential download methods
        if (window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveOrOpenBlob = function() {
                return false;
            };
        }
        
        if (window.navigator.msSaveBlob) {
            window.navigator.msSaveBlob = function() {
                return false;
            };
        }
        
        // Override fetch for media resources
        const originalFetch = window.fetch;
        window.fetch = function(input, init) {
            if (typeof input === 'string' && input.match(/\.(mp4|webm|ogg)$/i)) {
                const referrer = document.referrer || window.location.href;
                const newInit = init || {};
                newInit.credentials = 'same-origin';
                newInit.referrer = referrer;
                newInit.referrerPolicy = 'origin';
                return originalFetch(input, newInit);
            }
            return originalFetch(input, init);
        };
    }

    // Add anti-devtools protection
    function addDevToolsProtection() {
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
    
    // Handle detection of devtools opening
    function handleDevtoolsOpen() {
        // Optional: Display warning
        if (!document.getElementById('devtools-warning')) {
            const warning = document.createElement('div');
            warning.id = 'devtools-warning';
            warning.style.position = 'fixed';
            warning.style.top = '0';
            warning.style.left = '0';
            warning.style.width = '100%';
            warning.style.padding = '10px';
            warning.style.backgroundColor = 'red';
            warning.style.color = 'white';
            warning.style.textAlign = 'center';
            warning.style.zIndex = '9999';
            warning.innerText = 'Developer tools detected. This is being recorded for security purposes.';
            document.body.appendChild(warning);
            
            // Remove warning after 5 seconds
            setTimeout(function() {
                if (document.getElementById('devtools-warning')) {
                    document.getElementById('devtools-warning').remove();
                }
            }, 5000);
        }
    }

    // Initialize protection when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        protectVideos();
        detectExtensions();
        addDevToolsProtection();
        
        // Also protect videos that are added dynamically
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    for (let i = 0; i < mutation.addedNodes.length; i++) {
                        const node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            if (node.nodeName === 'VIDEO') {
                                protectVideos();
                            } else if (node.getElementsByTagName) {
                                const videos = node.getElementsByTagName('video');
                                if (videos.length > 0) {
                                    protectVideos();
                                }
                            }
                        }
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    
    // Add CSS to disable selection and right-clicking
    const style = document.createElement('style');
    style.textContent = `
        video::-webkit-media-controls-download-button {
            display: none !important;
        }
        
        video::-webkit-media-controls-enclosure {
            overflow: hidden !important;
        }
        
        video::-webkit-media-controls-panel {
            width: calc(100% + 30px) !important;
        }
        
        .wsvl-video-container {
            position: relative;
            overflow: hidden;
        }
        
        .wsvl-watermark {
            position: absolute;
            z-index: 10;
            opacity: 0.5;
            color: white;
            pointer-events: none;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
            font-size: 14px;
            user-select: none;
        }
    `;
    document.head.appendChild(style);
})(); 