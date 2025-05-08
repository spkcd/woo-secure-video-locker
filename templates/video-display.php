<?php
/**
 * Template for displaying video content
 */

if (!defined('ABSPATH')) {
    exit;
}

// The following variables should be available before including this template:
// $video_slug - The slug of the video to display
// $video_description - The description of the video (optional)

// If essential variables are missing, exit
if (empty($video_slug)) {
    return;
}

// Generate signed URL for the video
$signed_url = WSVL\Security\VideoStreamer::generate_signed_url($video_slug);

// Generate a unique ID for this video instance
$video_instance_id = 'video_' . substr(md5($video_slug . uniqid()), 0, 8);
?>

<!-- Load Video.js CSS -->
<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">

<!-- Load Video.js -->
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

<div class="wsvl-video-container" data-video-slug="<?php echo esc_attr($video_slug); ?>" data-instance-id="<?php echo esc_attr($video_instance_id); ?>">
    <div class="wsvl-video-wrapper">
        <video 
            id="wsvl-video-<?php echo esc_attr($video_instance_id); ?>"
            class="video-js vjs-default-skin vjs-big-play-centered"
            controls
            preload="metadata"
            crossorigin="anonymous"
            playsinline
            data-setup='{
                "fluid": true,
                "playbackRates": [0.5, 1, 1.5, 2],
                "html5": {
                    "nativeTextTracks": false,
                    "nativeAudioTracks": false,
                    "nativeVideoTracks": false,
                    "hls": {
                        "overrideNative": true
                    },
                    "vhs": {
                        "overrideNative": true
                    }
                },
                "controlBar": {
                    "children": [
                        "playToggle",
                        "volumePanel",
                        "progressControl",
                        "currentTimeDisplay",
                        "timeDivider",
                        "durationDisplay",
                        "fullscreenToggle"
                    ]
                }
            }'
        >
            <!-- Try both MP4 and direct query string format as fallback -->
            <source src="<?php echo esc_url($signed_url); ?>" type="video/mp4">
            <source src="<?php echo esc_url(add_query_arg('wsvl_video', $video_slug, site_url('/'))); ?>&token=<?php echo $_GET['token'] ?? ''; ?>" type="video/mp4">
            <p class="vjs-no-js">
                To view this video please enable JavaScript, and consider upgrading to a
                web browser that supports HTML5 video
            </p>
        </video>
    </div>
    
    <?php if (!empty($video_description)) : ?>
        <div class="wsvl-video-description">
            <?php echo wp_kses_post($video_description); ?>
        </div>
    <?php endif; ?>
    
    <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
    <div class="wsvl-debug-info" style="margin-top: 20px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">
        <h4>Debug Information (Only visible in debug mode)</h4>
        <p>Video Slug: <strong><?php echo esc_html($video_slug); ?></strong></p>
        <p>Signed URL: <a href="<?php echo esc_url($signed_url); ?>" target="_blank"><?php echo esc_url($signed_url); ?></a></p>
        <p>Instance ID: <strong><?php echo esc_html($video_instance_id); ?></strong></p>
        <p>
            <button type="button" onclick="refreshVideoUrl('<?php echo esc_js($video_slug); ?>', '<?php echo esc_js($video_instance_id); ?>')">
                Refresh Video URL
            </button>
            <button type="button" onclick="testVideoAccess('<?php echo esc_js($signed_url); ?>')">
                Test Direct Access
            </button>
        </p>
        <div id="debug-result-<?php echo esc_attr($video_instance_id); ?>"></div>
    </div>
    <?php endif; ?>
</div>

<style>
.wsvl-video-container {
    max-width: 100%;
    margin: 20px 0;
    position: relative;
}

.wsvl-video-wrapper {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 Aspect Ratio */
    background: #000;
    overflow: hidden;
}

.video-js {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.wsvl-video-description {
    margin-top: 15px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
}

/* Custom Video.js theme overrides */
.video-js .vjs-big-play-button {
    background-color: rgba(0, 0, 0, 0.6);
    border-color: #fff;
    border-radius: 50%;
    width: 80px;
    height: 80px;
    line-height: 80px;
    margin-left: -40px;
    margin-top: -40px;
}

.video-js:hover .vjs-big-play-button {
    background-color: rgba(0, 0, 0, 0.8);
    border-color: #fff;
}

.video-js .vjs-control-bar {
    background-color: rgba(0, 0, 0, 0.7);
}

.video-js .vjs-progress-control {
    position: absolute;
    top: -10px;
    right: 0;
    left: 0;
    width: 100%;
    height: 10px;
}

.video-js .vjs-progress-holder {
    height: 10px;
}

.video-js .vjs-progress-holder .vjs-play-progress {
    background-color: #fff;
}

.video-js .vjs-progress-holder .vjs-progress-holder {
    background-color: rgba(255, 255, 255, 0.3);
}

.video-js .vjs-progress-holder:hover .vjs-progress-holder {
    height: 10px;
}

.video-js .vjs-progress-holder:hover .vjs-play-progress {
    background-color: #fff;
}
</style>

<script>
// Wait for Video.js to be loaded
function waitForVideoJS(callback, maxAttempts = 50) {
    let attempts = 0;
    
    function checkVideoJS() {
        attempts++;
        if (typeof videojs !== 'undefined') {
            callback();
        } else if (attempts < maxAttempts) {
            setTimeout(checkVideoJS, 100);
        } else {
            console.error('Video.js failed to load after ' + maxAttempts + ' attempts');
        }
    }
    
    checkVideoJS();
}

// Initialize the player once Video.js is loaded
waitForVideoJS(function() {
    const container = document.querySelector(`[data-instance-id="<?php echo esc_js($video_instance_id); ?>"]`);
    if (!container) return;

    const video = container.querySelector('video');
    
    try {
        // Initialize Video.js player with improved options
        const player = videojs(video.id, {
            html5: {
                vhs: {
                    overrideNative: true
                },
                nativeVideoTracks: false,
                nativeAudioTracks: false,
                nativeTextTracks: false,
                hls: {
                    overrideNative: true
                }
            }
        });

        // Prevent right-click
        video.addEventListener('contextmenu', e => e.preventDefault());

        // Error handling with improved details
        player.on('error', function(e) {
            console.error('Video error:', e);
            if (player.error()) {
                console.error('Media error code:', player.error().code);
                console.error('Media error message:', player.error().message);
                
                <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                // Display error in debug div
                const debugDiv = document.getElementById('debug-result-<?php echo esc_js($video_instance_id); ?>');
                if (debugDiv) {
                    debugDiv.innerHTML = `
                        <div style="color: red; margin-top: 10px;">
                            <strong>Video Error:</strong><br>
                            Error Code: ${player.error().code}<br>
                            Message: ${player.error().message}
                        </div>
                    `;
                }
                <?php endif; ?>
            }
        });

        // Add debugging info for network requests
        player.on('loadstart', function() {
            console.log('Video loadstart event fired');
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (player) {
                player.dispose();
            }
        });
    } catch (error) {
        console.error('Error initializing Video.js:', error);
    }
});

<?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
// Debug helper functions
function refreshVideoUrl(videoSlug, instanceId) {
    const debugDiv = document.getElementById(`debug-result-${instanceId}`);
    if (!debugDiv) return;
    
    debugDiv.innerHTML = '<p>Refreshing URL...</p>';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'refresh_video_url',
            'nonce': '<?php echo wp_create_nonce('wsvl-video-nonce'); ?>',
            'video_slug': videoSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            debugDiv.innerHTML = `
                <p style="color: green;">URL refreshed successfully</p>
                <p>New URL: <a href="${data.data.url}" target="_blank">${data.data.url}</a></p>
            `;
            
            // Update the video source
            const player = videojs(`wsvl-video-${instanceId}`);
            if (player) {
                player.src({
                    src: data.data.url,
                    type: 'video/mp4'
                });
                player.load();
            }
        } else {
            debugDiv.innerHTML = `<p style="color: red;">Error: ${data.data}</p>`;
        }
    })
    .catch(error => {
        debugDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
    });
}

function testVideoAccess(url) {
    const debugDiv = document.getElementById(`debug-result-<?php echo esc_js($video_instance_id); ?>`);
    if (!debugDiv) return;
    
    debugDiv.innerHTML = '<p>Testing direct access...</p>';
    
    fetch(url, {
        method: 'HEAD',
        credentials: 'include',
        mode: 'cors'
    })
    .then(response => {
        debugDiv.innerHTML = `
            <p>Status: ${response.status} ${response.statusText}</p>
            <p>Headers:</p>
            <ul style="font-size: 11px;">
                ${Array.from(response.headers).map(([key, value]) => `<li><strong>${key}:</strong> ${value}</li>`).join('')}
            </ul>
        `;
    })
    .catch(error => {
        debugDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
    });
}
<?php endif; ?>
</script> 