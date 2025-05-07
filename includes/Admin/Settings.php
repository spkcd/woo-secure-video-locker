<?php
namespace WSVL\Admin;

class Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_menu() {
        add_submenu_page(
            'woocommerce',
            __('Video Locker Settings', 'secure-video-locker-for-woocommerce'),
            __('Video Locker', 'secure-video-locker-for-woocommerce'),
            'manage_options',
            'wsvl-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'wsvl_settings',
            'wsvl_browse_products_url',
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => get_permalink(wc_get_page_id('shop'))
            ]
        );

        add_settings_section(
            'wsvl_general_section',
            __('General Settings', 'secure-video-locker-for-woocommerce'),
            [$this, 'general_section_callback'],
            'wsvl_settings'
        );

        add_settings_field(
            'wsvl_browse_products_url',
            __('Browse Products URL', 'secure-video-locker-for-woocommerce'),
            [$this, 'browse_products_url_callback'],
            'wsvl_settings',
            'wsvl_general_section'
        );
    }

    public function general_section_callback() {
        echo '<p>' . __('Configure general settings for the Secure Video Locker plugin.', 'secure-video-locker-for-woocommerce') . '</p>';
    }

    public function browse_products_url_callback() {
        $url = get_option('wsvl_browse_products_url', get_permalink(wc_get_page_id('shop')));
        ?>
        <input type="url" name="wsvl_browse_products_url" value="<?php echo esc_attr($url); ?>" class="regular-text">
        <p class="description">
            <?php _e('Enter the URL where users will be directed when clicking "Browse Products" from the video dashboard.', 'secure-video-locker-for-woocommerce'); ?>
        </p>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wsvl_settings');
                do_settings_sections('wsvl_settings');
                submit_button(__('Save Settings', 'secure-video-locker-for-woocommerce'));
                ?>
            </form>
        </div>
        <?php
    }
} 