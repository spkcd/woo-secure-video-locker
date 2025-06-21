jQuery(document).ready(function($) {
    'use strict';

    // Initialize charts when page loads
    initializeCharts();
    
    // Handle modal interactions
    initializeModal();
    
    // Handle export functionality
    initializeExport();
    
    // Handle refresh functionality
    initializeRefresh();

    /**
     * Initialize all charts
     */
    function initializeCharts() {
        if (typeof window.wsvlVideoStats === 'undefined' || !window.wsvlVideoStats.length) {
            console.log('No video statistics data available');
            return;
        }

        initializeTopVideosChart();
        initializeViewsTimeChart();
    }

    /**
     * Initialize top videos chart
     */
    function initializeTopVideosChart() {
        const ctx = document.getElementById('topVideosChart');
        if (!ctx) return;

        // Get top 10 videos by views
        const topVideos = window.wsvlVideoStats
            .sort((a, b) => parseInt(b.total_views) - parseInt(a.total_views))
            .slice(0, 10);

        const labels = topVideos.map(video => video.video_slug);
        const data = topVideos.map(video => parseInt(video.total_views));
        const colors = generateColors(topVideos.length);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Views',
                    data: data,
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const video = topVideos[context.dataIndex];
                                return [
                                    'Unique Viewers: ' + video.unique_viewers,
                                    'Avg. Completion: ' + parseFloat(video.avg_completion_rate).toFixed(1) + '%'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize views over time chart
     */
    function initializeViewsTimeChart() {
        const ctx = document.getElementById('viewsTimeChart');
        if (!ctx) return;

        // For now, we'll create a simple chart based on available data
        // In a real implementation, you'd want to fetch daily view data
        const labels = [];
        const data = [];
        
        // Generate last 30 days
        for (let i = 29; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString());
            
            // Simulate some data - in real implementation, fetch from server
            data.push(Math.floor(Math.random() * 50) + 10);
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Views',
                    data: data,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize modal functionality
     */
    function initializeModal() {
        const modal = $('#videoDetailsModal');
        const closeBtn = $('.wsvl-modal-close');

        // Handle view details buttons
        $(document).on('click', '.view-details', function() {
            const videoSlug = $(this).data('video-slug');
            showVideoDetails(videoSlug);
        });

        // Handle close button
        closeBtn.on('click', function() {
            modal.hide();
        });

        // Handle click outside modal
        $(window).on('click', function(event) {
            if (event.target === modal[0]) {
                modal.hide();
            }
        });
    }

    /**
     * Show video details in modal
     */
    function showVideoDetails(videoSlug) {
        const modal = $('#videoDetailsModal');
        const content = $('#videoDetailsContent');
        
        modal.show();
        content.html('<div class="wsvl-loading">' + wsvlAnalytics.i18n.loading + '</div>');

        // Fetch detailed video statistics
        $.ajax({
            url: wsvlAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'wsvl_get_video_stats',
                video_slug: videoSlug,
                nonce: wsvlAnalytics.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayVideoDetails(response.data);
                } else {
                    content.html('<div class="wsvl-error">' + (response.data || wsvlAnalytics.i18n.error) + '</div>');
                }
            },
            error: function() {
                content.html('<div class="wsvl-error">' + wsvlAnalytics.i18n.error + '</div>');
            }
        });
    }

    /**
     * Display video details in modal
     */
    function displayVideoDetails(data) {
        const content = $('#videoDetailsContent');
        const summary = data.summary;
        const topViewers = data.top_viewers || [];

        let html = '<div class="wsvl-video-details">';
        
        // Summary section
        html += '<div class="wsvl-details-section">';
        html += '<h3>Summary</h3>';
        html += '<div class="wsvl-details-grid">';
        html += '<div class="wsvl-detail-item"><strong>Video Slug:</strong> ' + summary.video_slug + '</div>';
        html += '<div class="wsvl-detail-item"><strong>Total Views:</strong> ' + summary.total_views + '</div>';
        html += '<div class="wsvl-detail-item"><strong>Unique Viewers:</strong> ' + summary.unique_viewers + '</div>';
        html += '<div class="wsvl-detail-item"><strong>Total Watch Time:</strong> ' + formatDuration(summary.total_watch_time) + '</div>';
        html += '<div class="wsvl-detail-item"><strong>Avg. Completion:</strong> ' + parseFloat(summary.avg_completion_rate).toFixed(1) + '%</div>';
        html += '<div class="wsvl-detail-item"><strong>Last Viewed:</strong> ' + (summary.last_viewed || 'Never') + '</div>';
        html += '</div>';
        html += '</div>';

        // Top viewers section
        if (topViewers.length > 0) {
            html += '<div class="wsvl-details-section">';
            html += '<h3>Top Viewers</h3>';
            html += '<table class="wsvl-details-table">';
            html += '<thead><tr><th>User ID</th><th>Views</th><th>Watch Time</th><th>Avg. Completion</th><th>Last View</th></tr></thead>';
            html += '<tbody>';
            
            topViewers.forEach(function(viewer) {
                html += '<tr>';
                html += '<td>' + viewer.user_id + '</td>';
                html += '<td>' + viewer.view_count + '</td>';
                html += '<td>' + formatDuration(viewer.total_duration) + '</td>';
                html += '<td>' + parseFloat(viewer.avg_completion).toFixed(1) + '%</td>';
                html += '<td>' + viewer.last_view + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '</div>';
        }

        html += '</div>';
        content.html(html);
    }

    /**
     * Initialize export functionality
     */
    function initializeExport() {
        $('#exportAnalytics').on('click', function() {
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text(wsvlAnalytics.i18n.loading);

            // Create a form and submit it to trigger download
            const form = $('<form>', {
                method: 'POST',
                action: wsvlAnalytics.ajaxurl
            });

            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'wsvl_export_analytics'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: wsvlAnalytics.nonce
            }));

            $('body').append(form);
            form.submit();
            form.remove();

            // Re-enable button after a delay
            setTimeout(function() {
                button.prop('disabled', false).text(originalText);
            }, 2000);
        });
    }

    /**
     * Initialize refresh functionality
     */
    function initializeRefresh() {
        $('#refreshData').on('click', function() {
            location.reload();
        });
    }

    /**
     * Generate colors for charts
     */
    function generateColors(count) {
        const colors = [
            '#0073aa', '#00a0d2', '#0085ba', '#005177', '#003f5c',
            '#2c3e50', '#3498db', '#9b59b6', '#e74c3c', '#f39c12',
            '#27ae60', '#16a085', '#34495e', '#95a5a6', '#7f8c8d'
        ];

        const background = [];
        const border = [];

        for (let i = 0; i < count; i++) {
            const color = colors[i % colors.length];
            background.push(color + '80'); // Add transparency
            border.push(color);
        }

        return { background, border };
    }

    /**
     * Format duration in seconds to human readable format
     */
    function formatDuration(seconds) {
        if (!seconds || seconds < 0) return '0s';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return hours + 'h ' + minutes + 'm ' + secs + 's';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        } else {
            return secs + 's';
        }
    }
}); 