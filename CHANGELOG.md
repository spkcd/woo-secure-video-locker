# Changelog

All notable changes to the "Woo Secure Video Locker" plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2025-01-20

### Added - Video Analytics & Monitoring System
- **Comprehensive Video Analytics Dashboard**: New admin interface under WooCommerce → Video Analytics
- **Automatic View Tracking**: Every video stream is now automatically recorded with detailed metrics
- **Database Tables**: Two new tables for detailed view records and summary statistics
  - `wp_wsvl_video_views`: Individual view records with user, device, and session data
  - `wp_wsvl_video_stats`: Aggregated statistics for fast queries
- **Interactive Charts**: Visual analytics with Chart.js integration
  - Top 10 most watched videos bar chart
  - Views over time line chart
- **Detailed Statistics**: Track views, unique viewers, watch time, and completion rates
- **Device & Browser Analytics**: Automatic detection and tracking of user devices and browsers
- **CSV Export**: Export analytics data for external analysis
- **Frontend Shortcodes**: Display video statistics on any page or post
  - `[wsvl_video_views slug="video-slug"]` - Simple view count
  - `[wsvl_video_stats slug="video-slug"]` - Comprehensive statistics
- **Product Page Integration**: Automatic display of video stats on WooCommerce product pages
- **Template Functions**: PHP functions for theme developers
- **Real-time Data**: Live statistics with refresh functionality

### Enhanced
- **Video Streaming**: Now records detailed analytics for every video stream
- **User Experience**: Modern, responsive analytics dashboard with professional styling
- **Performance**: Optimized database queries with proper indexing
- **Security**: User permission validation for analytics access
- **Mobile Support**: Fully responsive analytics interface

### Technical Improvements
- Added VideoAnalytics admin class with comprehensive dashboard
- Added VideoViewCounter frontend class with shortcode support
- Enhanced VideoStreamer with automatic view recording
- Added Chart.js integration for visual analytics
- Implemented AJAX-powered admin interface
- Added device and browser detection algorithms
- Enhanced CSS with analytics-specific styling

### Fixed
- Bug where auto-generated video slugs would disappear after saving the product
- Improved form field handling in WooCommerce product data panels
- Enhanced data persistence when switching between product tabs

## [1.0.2] - 2025-05-09

### Performance Improvements
- Optimized video streaming performance with reduced chunk size for better memory usage
- Implemented readfile() for more efficient streaming of large files
- Added performance-related headers to improve streaming speed
- Added caching configuration for better playback performance
- Improved upload processing with adaptive chunk sizing based on connection quality
- Reduced logging verbosity to improve performance in production environments
- Optimized PHP memory usage settings (reduced from 2GB to more efficient 512MB)
- Added retry mechanism for failed uploads with automatic chunk size adjustment

### Usability Enhancements
- Added automatic video slug generation from the uploaded filename
- Auto-populate the video slug field when a file is uploaded
- Added upload time statistics and progress estimation for large files
- Improved feedback during file uploads with better progress reporting
- Added visual feedback when the slug field is auto-filled

### Added
- Added comprehensive performance diagnostic tool (wsvl-performance-check.php)
- Added automated performance tuning based on connection speed
- Added pattern matching to better associate videos with products
- Added automatic metadata updates when a matching video is found

### Technical Changes
- Modified VideoStreamer.php with enhanced file finding capabilities
- Updated ChunkedUploader.php with improved error handling and cleanup
- Added better streaming headers for improved compatibility
- Optimized PHP settings in upload-config.php
- Added automatic .htaccess optimization for video streaming
- Added efficient file combining method for chunked uploads

### Fixed
- Fixed issue where video files weren't properly associated with products
- Fixed memory leaks during large file streaming
- Improved handling of range requests for better seeking in videos
- Added proper cleanup of temporary files during chunked uploads
- Fixed 404 errors for videos with special characters in filenames

### Security
- Implemented better permission checks for file operations
- Added backup functionality for uploaded files
- Improved security headers for video streaming
- Better file extension validation

## [1.0.1] - 2025-04-15

### Enhancements
- Implemented chunked upload support for large video files
- Added progress bar for upload status
- Increased maximum upload size to 2GB
- Added better error handling and logging

### Technical Changes
- Modified admin-product.js to support chunked uploads (5MB chunks)
- Updated ProductVideoManager.php with new chunked upload handlers
- Added upload-config.php for PHP upload limit configuration
- Added .htaccess configuration for upload limits
- Improved error logging throughout the upload process

### Fixed
- Fixed HTTP/2 protocol errors with large file uploads
- Fixed timeout issues with large video uploads
- Improved error handling for failed uploads

### Security
- Added proper file type validation
- Added nonce verification for all upload operations
- Added proper permission checks
- Improved file handling security

### Dependencies
- Requires PHP 7.4 or higher
- Requires WordPress 5.8 or higher
- Requires WooCommerce 5.0 or higher

## [1.0.0] - 2025-03-22

### Added
- Initial plugin release
- Secure video streaming for WooCommerce products
- Multiple anti-download protection layers:
  - Chunked video delivery
  - Right-click protection
  - Keyboard shortcut blocking
  - DevTools detection
  - HTTP security headers
  - User-specific watermarking
- WooCommerce product integration
- Customer video dashboard
- Admin management interface
- Rate limiting protection
- Access logging
- Mobile-friendly responsive design 