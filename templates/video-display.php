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

<div class="wsvl-video-container" data-video-slug="<?php echo esc_attr($video_slug); ?>" data-instance-id="<?php echo esc_attr($video_instance_id); ?>">
    <div class="wsvl-video-wrapper">
        <video 
            id="wsvl-video-<?php echo esc_attr($video_instance_id); ?>"
            class="wsvl-video-player"
            preload="metadata"
            crossorigin="anonymous"
            playsinline
            disablePictureInPicture
            controlsList="nodownload noplaybackrate"
        >
            <source src="<?php echo esc_url($signed_url); ?>" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
        
        <!-- Custom Video Controls -->
        <div class="wsvl-custom-controls">
            <div class="wsvl-controls-left">
                <button class="wsvl-play-pause" title="Play/Pause">
                    <span class="dashicons dashicons-controls-play"></span>
                </button>
                
                <div class="wsvl-volume-control">
                    <button class="wsvl-mute" title="Mute/Unmute">
                        <span class="dashicons dashicons-volume-high"></span>
                    </button>
                    <input type="range" class="wsvl-volume-slider" min="0" max="1" step="0.1" value="1">
                </div>
            </div>

            <div class="wsvl-progress-bar">
                <div class="wsvl-progress-background"></div>
                <div class="wsvl-progress-loaded"></div>
                <div class="wsvl-progress-current"></div>
                <input type="range" class="wsvl-progress-seek" min="0" max="100" step="0.1" value="0">
            </div>

            <div class="wsvl-controls-right">
                <button class="wsvl-fullscreen" title="Fullscreen">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            </div>
        </div>
    </div>
    <?php if (!empty($video_description)) : ?>
        <div class="wsvl-video-description">
            <?php echo wp_kses_post($video_description); ?>
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

.wsvl-video-player {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

.wsvl-custom-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.7);
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.wsvl-controls-left,
.wsvl-controls-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.wsvl-progress-bar {
    position: relative;
    flex-grow: 1;
    height: 4px;
    cursor: pointer;
    margin: 0 10px;
}

.wsvl-progress-background {
    position: absolute;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.wsvl-progress-loaded {
    position: absolute;
    height: 100%;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    width: 0;
}

.wsvl-progress-current {
    position: absolute;
    height: 100%;
    background: #fff;
    border-radius: 2px;
    width: 0;
}

.wsvl-progress-seek {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    margin: 0;
    top: 0;
    left: 0;
}

.wsvl-progress-bar:hover {
    height: 8px;
    margin-top: -2px;
    margin-bottom: -2px;
    transition: height 0.1s ease-in-out;
}

.wsvl-custom-controls button {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 5px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wsvl-custom-controls button:hover {
    color: #ddd;
}

.wsvl-volume-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.wsvl-volume-slider {
    width: 80px;
    height: 4px;
    -webkit-appearance: none;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    outline: none;
}

.wsvl-volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 12px;
    height: 12px;
    background: white;
    border-radius: 50%;
    cursor: pointer;
}

.wsvl-video-description {
    margin-top: 15px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector(`[data-instance-id="<?php echo esc_js($video_instance_id); ?>"]`);
    if (!container) return;

    const video = container.querySelector('video');
    const playPauseBtn = container.querySelector('.wsvl-play-pause');
    const muteBtn = container.querySelector('.wsvl-mute');
    const volumeSlider = container.querySelector('.wsvl-volume-slider');
    const fullscreenBtn = container.querySelector('.wsvl-fullscreen');
    const progressBar = container.querySelector('.wsvl-progress-bar');
    const progressLoaded = container.querySelector('.wsvl-progress-loaded');
    const progressCurrent = container.querySelector('.wsvl-progress-current');
    const progressSeek = container.querySelector('.wsvl-progress-seek');

    // Prevent right-click
    container.addEventListener('contextmenu', e => e.preventDefault());

    // Play/Pause
    playPauseBtn.addEventListener('click', () => {
        if (video.paused) {
            video.play();
            playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-play');
            playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-pause');
        } else {
            video.pause();
            playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
            playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
        }
    });

    // Mute/Unmute
    muteBtn.addEventListener('click', () => {
        video.muted = !video.muted;
        muteBtn.querySelector('.dashicons').classList.toggle('dashicons-volume-high', !video.muted);
        muteBtn.querySelector('.dashicons').classList.toggle('dashicons-volume-off', video.muted);
        volumeSlider.value = video.muted ? 0 : video.volume;
    });

    // Volume Control
    volumeSlider.addEventListener('input', () => {
        video.volume = volumeSlider.value;
        video.muted = video.volume === 0;
        muteBtn.querySelector('.dashicons').classList.toggle('dashicons-volume-high', !video.muted);
        muteBtn.querySelector('.dashicons').classList.toggle('dashicons-volume-off', video.muted);
    });

    // Fullscreen
    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            container.requestFullscreen();
            fullscreenBtn.querySelector('.dashicons').classList.remove('dashicons-fullscreen-alt');
            fullscreenBtn.querySelector('.dashicons').classList.add('dashicons-fullscreen-exit-alt');
        } else {
            document.exitFullscreen();
            fullscreenBtn.querySelector('.dashicons').classList.remove('dashicons-fullscreen-exit-alt');
            fullscreenBtn.querySelector('.dashicons').classList.add('dashicons-fullscreen-alt');
        }
    });

    // Update play/pause button on video events
    video.addEventListener('play', () => {
        playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-play');
        playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-pause');
    });

    video.addEventListener('pause', () => {
        playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
        playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
    });

    // Error handling
    video.addEventListener('error', (e) => {
        console.error('Video error:', e);
        if (e.target.error) {
            console.error('Media error code:', e.target.error.code);
            console.error('Media error message:', e.target.error.message);
        }
    });

    // Progress bar update
    video.addEventListener('timeupdate', () => {
        if (!video.duration) return;
        const percent = (video.currentTime / video.duration) * 100;
        progressCurrent.style.width = `${percent}%`;
        progressSeek.value = percent;
    });

    // Buffer progress
    video.addEventListener('progress', () => {
        if (video.buffered.length > 0) {
            const bufferedEnd = video.buffered.end(video.buffered.length - 1);
            const duration = video.duration;
            progressLoaded.style.width = `${(bufferedEnd / duration) * 100}%`;
        }
    });

    // Seek functionality
    progressSeek.addEventListener('input', () => {
        const time = (progressSeek.value / 100) * video.duration;
        video.currentTime = time;
    });

    // Click on progress bar
    progressBar.addEventListener('click', (e) => {
        const rect = progressBar.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        video.currentTime = pos * video.duration;
    });
});
</script> 