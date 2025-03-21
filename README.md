# Woo Secure Video Locker

A WordPress plugin that provides secure video streaming for WooCommerce products, preventing unauthorized downloads while maintaining a great user experience.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WP tested](https://img.shields.io/badge/WordPress-5.8+-green.svg)
![WC tested](https://img.shields.io/badge/WooCommerce-5.0+-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-orange.svg)

## 🔒 Features

- **Secure Video Streaming**: Delivers videos through temporary, signed URLs that expire automatically
- **Anti-Download Protection**: Multiple layers of security to prevent video downloads:
  - Chunked video delivery
  - Right-click protection
  - Keyboard shortcut blocking
  - DevTools detection
  - Custom HTTP headers
  - User-specific watermarking
- **WooCommerce Integration**: Seamlessly connects with WooCommerce products
- **User Dashboard**: Customers can access purchased videos from their account
- **Admin Controls**: Easy video upload and management
- **Mobile-Friendly**: Works on all devices
- **Rate Limiting**: Prevents abuse through request limiting

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
3. Upload a video file
4. Add a description (optional)
5. Save the product

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

1. Upload videos directly to products
2. Track video access through detailed security logs
3. Manage all aspects of video security

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

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

## 📜 License

This project is licensed under the GPLv2 or later - see the [LICENSE](LICENSE) file for details.

## 👥 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute.

## 🤝 Support

For support, please:

1. Check the [documentation](https://krischislett.com/woo-secure-video-locker-docs)
2. Visit the [support forum](https://wordpress.org/support/plugin/woo-secure-video-locker/)
3. For premium support, contact us at valentin@krischislett.com

## 🔗 Related

- [WordPress](https://wordpress.org/)
- [WooCommerce](https://woocommerce.com/)

---

Built with ❤️ for the WordPress community 
