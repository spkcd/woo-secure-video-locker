# Secure Video Locker for WooCommerce

Contributors: sparkwebstudio
Tags: video, streaming, security, woocommerce, content protection
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that provides secure video streaming for WooCommerce products, preventing unauthorized downloads while maintaining a great user experience.

![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)
![WP tested](https://img.shields.io/badge/WordPress-5.8+-green.svg)
![WC tested](https://img.shields.io/badge/WooCommerce-5.0+-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-orange.svg)

## 🔒 Features

### Core Security Features
- **Secure Video Streaming**: Delivers videos through temporary, signed URLs that expire automatically
- **Anti-Download Protection**: Multiple layers of security to prevent video downloads:
  - Chunked video delivery
  - Right-click protection
  - Keyboard shortcut blocking
  - DevTools detection
  - Custom HTTP headers
  - User-specific watermarking
- **Rate Limiting**: Prevents abuse through request limiting

### 📊 NEW: Video Analytics & Monitoring (v1.1.0)
- **Comprehensive Analytics Dashboard**: Track video performance with detailed statistics
- **Automatic View Tracking**: Every video stream is recorded with user, device, and session data
- **Interactive Charts**: Visual analytics with Chart.js integration
- **Real-time Statistics**: Views, unique viewers, watch time, and completion rates
- **Device & Browser Analytics**: Automatic detection and tracking of user devices
- **CSV Export**: Export analytics data for external analysis
- **Frontend Display**: Show video statistics on product pages and posts
- **Shortcode Support**: Display video stats anywhere with simple shortcodes

### Integration & Management
- **WooCommerce Integration**: Seamlessly connects with WooCommerce products
- **User Dashboard**: Customers can access purchased videos from their account
- **Admin Controls**: Easy video upload and management with analytics
- **Mobile-Friendly**: Works on all devices with responsive analytics
- **Large File Support**: Chunked upload system for videos up to 2GB

## 📋 Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Modern browser with HTML5 video support

## 🚀 Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Activate the plugin
5. Create a directory at `wp-content/private-videos` and ensure it has proper write permissions

### Manual Installation

1. Upload the plugin files to the `/wp-content/plugins/woo-secure-video-locker` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Create a directory at `wp-content/private-videos` and ensure it has proper write permissions

## ⚙️ Configuration

### Adding Videos to Products

1. Create or edit a WooCommerce product
2. Scroll down to the "Video Settings" meta box
3. Upload a video file (supports files up to 2GB with chunked upload)
4. Add a description (optional)
5. Save the product

### Video Analytics Dashboard

Access comprehensive video analytics:

1. Go to WooCommerce > Video Analytics
2. View summary statistics and interactive charts
3. Click "View Details" on any video for detailed analytics
4. Export data as CSV for external analysis
5. Use shortcodes to display stats on frontend

### Frontend Shortcodes

Display video statistics anywhere on your site:

```php
// Simple view count
[wsvl_video_views slug="your-video-slug"]

// View count with text format
[wsvl_video_views slug="your-video-slug" format="text"]

// Badge style display
[wsvl_video_views slug="your-video-slug" format="badge"]

// Comprehensive statistics
[wsvl_video_stats slug="your-video-slug" show_views="true" show_unique="true"]
```

### Security Settings

The plugin comes pre-configured with optimal security settings, but you can modify:

1. Go to WooCommerce > Settings > Products > Video Locker
2. Adjust security levels and options
3. Save changes

## 🎬 How It Works

### For Customers

1. Customers purchase video products through WooCommerce
2. Once purchased, videos appear in their account dashboard under "My Videos"
3. Customers can stream videos securely without being able to download them
4. Each video stream includes a watermark with the customer's email address

### For Admins

1. Upload videos directly to products (up to 2GB with chunked upload)
2. Track video access through comprehensive analytics dashboard
3. Monitor video performance with real-time statistics
4. Export analytics data for reporting and analysis
5. Manage all aspects of video security

### Technical Details

The plugin implements multiple layers of protection:

- **Server-side Protection**:
  - Signed URLs with short expiration times
  - Chunked video delivery
  - Content-Security-Policy headers
  - Rate limiting to prevent abuse
  - Access logging

- **Client-side Protection**:
  - JavaScript barriers to prevent downloading
  - Custom video controls
  - Watermarking with customer identification
  - DevTools detection

## 🛡️ Security Considerations

While this plugin implements multiple layers of protection, no system can provide 100% security against determined attackers. The plugin focuses on preventing casual copying by typical users while maintaining a good viewing experience.

For absolute security of extremely valuable content, consider additional DRM solutions or streaming services that specialize in high-security media delivery.

## 📝 Documentation

- [CHANGELOG.md](CHANGELOG.md) - Version history and changes
- [ANALYTICS.md](ANALYTICS.md) - Complete video analytics documentation

## 📜 License

This project is licensed under the GPLv2 or later - see the [LICENSE](LICENSE) file for details.

## 👥 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute.

## 🤝 Support

For support, please:

1. Check the [documentation](https://sparkwebstudio.com/woo-secure-video-locker-docs)
2. Visit the [support forum](https://wordpress.org/support/plugin/woo-secure-video-locker/)
3. For premium support, contact us at support@sparkwebstudio.com

## 🔗 Related

- [WordPress](https://wordpress.org/)
- [WooCommerce](https://woocommerce.com/)

---

Built with ❤️ for the WordPress community 
