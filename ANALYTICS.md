# Video Analytics Documentation

## Overview

The Video Analytics system in Secure Video Locker for WooCommerce provides comprehensive tracking and reporting of video viewing behavior. This feature was introduced in version 1.1.0 and offers both admin dashboard analytics and frontend display options.

## Features

### 📊 Admin Analytics Dashboard

Access the analytics dashboard through **WooCommerce → Video Analytics**.

#### Summary Cards
- **Total Videos**: Number of videos with recorded views
- **Total Views**: Aggregate view count across all videos
- **Unique Viewers**: Total number of unique users who have watched videos
- **Total Watch Time**: Combined viewing time across all videos

#### Interactive Charts
- **Top 10 Most Watched Videos**: Bar chart showing your most popular content
- **Views Over Time**: Line chart displaying viewing trends (last 30 days)

#### Detailed Statistics Table
- Video slug and associated product name
- Total views and unique viewer counts
- Total watch time and average completion rates
- Last viewed timestamps
- Individual video detail modals

#### Export & Management
- **CSV Export**: Download complete analytics data
- **Refresh Data**: Update statistics in real-time
- **View Details**: Modal popup with comprehensive video statistics

### 🎯 Automatic Tracking

Every video stream is automatically tracked with:

#### User Data
- User ID (for logged-in users)
- Session ID for tracking individual sessions
- IP address (with proxy support)
- Referrer information

#### Device & Browser Analytics
- Device type detection (desktop, mobile, tablet)
- Browser identification (Chrome, Firefox, Safari, etc.)
- User agent string for detailed analysis

#### Viewing Metrics
- View timestamp
- Bytes streamed
- View duration (when available)
- Completion percentage (when available)

### 🎨 Frontend Display Options

#### Shortcodes

Display video statistics anywhere on your site:

```php
// Simple view count number
[wsvl_video_views slug="video-slug"]

// View count with descriptive text
[wsvl_video_views slug="video-slug" format="text"]

// Badge-style display
[wsvl_video_views slug="video-slug" format="badge"]

// Include unique viewers
[wsvl_video_views slug="video-slug" format="text" show_unique="true"]

// Comprehensive statistics
[wsvl_video_stats slug="video-slug" show_views="true" show_unique="true" show_completion="true"]
```

#### Shortcode Parameters

**`[wsvl_video_views]`**
- `slug` (required): Video slug identifier
- `format`: Display format (`count`, `text`, `badge`)
- `show_unique`: Include unique viewer count (`true`/`false`)
- `class`: Custom CSS class

**`[wsvl_video_stats]`**
- `slug` (required): Video slug identifier
- `show_views`: Display view count (`true`/`false`)
- `show_unique`: Display unique viewers (`true`/`false`)
- `show_completion`: Display completion rate (`true`/`false`)
- `class`: Custom CSS class

#### Product Page Integration

Video statistics automatically appear on WooCommerce product pages for customers who have purchased the product, showing:
- Total view count
- Unique viewer statistics
- Professional styling with responsive design

### 🔧 Developer Functions

#### Template Functions

```php
// Display view count in templates
WSVL\Frontend\VideoViewCounter::the_video_view_count('video-slug', 'text');

// Get view count for custom use
$views = WSVL\Frontend\VideoViewCounter::get_video_view_count('video-slug');

// Get unique viewer count
$unique = WSVL\Frontend\VideoViewCounter::get_video_unique_viewers('video-slug');

// Get comprehensive statistics
$stats = WSVL\Frontend\VideoViewCounter::get_video_stats_for_template('video-slug');
```

#### Static Methods

```php
// From VideoStreamer class
$view_count = VideoStreamer::get_video_view_count('video-slug');
$unique_viewers = VideoStreamer::get_video_unique_viewers('video-slug');
```

## Database Structure

### Tables Created

#### `wp_wsvl_video_views`
Detailed view records with the following fields:
- `id`: Primary key
- `video_slug`: Video identifier
- `product_id`: Associated WooCommerce product
- `user_id`: WordPress user ID
- `session_id`: Session identifier
- `ip_address`: Client IP address
- `user_agent`: Browser user agent
- `view_date`: Timestamp of view
- `view_duration`: Length of viewing session
- `bytes_streamed`: Amount of data streamed
- `completion_percentage`: How much of video was watched
- `device_type`: Device category (desktop/mobile/tablet)
- `browser`: Browser name
- `referrer`: Referring page URL

#### `wp_wsvl_video_stats`
Aggregated statistics for performance:
- `id`: Primary key
- `video_slug`: Video identifier
- `product_id`: Associated WooCommerce product
- `total_views`: Total view count
- `unique_viewers`: Count of unique users
- `total_watch_time`: Aggregate viewing time
- `avg_completion_rate`: Average completion percentage
- `last_viewed`: Most recent view timestamp
- `created_date`: Record creation date
- `updated_date`: Last update timestamp

### Indexing

Both tables include proper indexing for:
- Video slug lookups
- Product ID associations
- User ID queries
- Date-based filtering
- Performance optimization

## Privacy & Security

### Data Collection
- Only collects viewing behavior data
- No personal information beyond WordPress user IDs
- IP addresses are stored for security purposes
- User agents help with device analytics

### Access Control
- Analytics dashboard requires `manage_woocommerce` capability
- Frontend statistics respect product purchase requirements
- AJAX endpoints include nonce verification
- User permission validation on all operations

### Data Retention
- No automatic data purging (configurable in future versions)
- CSV export allows for data backup
- Database tables can be manually cleaned if needed

## Styling & Customization

### CSS Classes

The plugin includes comprehensive CSS styling:

```css
/* View count displays */
.wsvl-video-views
.wsvl-badge

/* Statistics displays */
.wsvl-video-stats
.wsvl-stat-item

/* Product page integration */
.wsvl-product-video-stats
.wsvl-stats-grid
.wsvl-stat-box
.wsvl-stat-number
.wsvl-stat-label

/* Error states */
.wsvl-error
.wsvl-no-stats
```

### Responsive Design
- Mobile-friendly analytics dashboard
- Responsive statistics displays
- Touch-friendly interface elements
- Optimized for all screen sizes

## Performance Considerations

### Database Optimization
- Proper indexing on all lookup fields
- Separate summary table for fast queries
- Efficient aggregation queries
- Minimal impact on video streaming performance

### Caching
- Summary statistics are cached in dedicated table
- Real-time updates without performance impact
- Optimized for high-traffic sites

## Troubleshooting

### Common Issues

**Analytics not showing data:**
- Ensure videos are being streamed (not just page views)
- Check that database tables were created properly
- Verify user permissions for analytics access

**Shortcodes not displaying:**
- Confirm correct video slug spelling
- Check that video has recorded views
- Verify shortcode syntax

**Dashboard not loading:**
- Check user has `manage_woocommerce` capability
- Verify JavaScript is enabled
- Check browser console for errors

### Debug Information

Enable WordPress debug mode to see detailed logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for log entries prefixed with `WSVL Monitoring:` in your debug log.

## Future Enhancements

Planned features for future versions:
- Advanced filtering and date range selection
- Email reporting and scheduled exports
- Integration with Google Analytics
- Custom dashboard widgets
- Data retention policies
- Advanced completion tracking
- Heatmap analytics for video engagement

---

For technical support or feature requests, please contact support@sparkwebstudio.com or visit our support forum. 