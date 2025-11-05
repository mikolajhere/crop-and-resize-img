<?php

/**
 * Admin Interface Class
 *
 * Handles admin interface, settings, and media library enhancements
 */

if (!defined('ABSPATH')) {
    exit;
}

class ICWC_Admin_Interface
{

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add action links to plugin page
        add_filter('plugin_action_links_' . ICWC_PLUGIN_BASENAME, array($this, 'add_action_links'));

        // Add media column for image info
        add_filter('manage_media_columns', array($this, 'add_media_column'));
        add_action('manage_media_custom_column', array($this, 'display_media_column'), 10, 2);

        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Add settings page
     */
    public function add_settings_page()
    {
        add_options_page(
            __('Image Crop & WebP Converter Settings', 'image-crop-webp-converter'),
            __('Image Crop & WebP', 'image-crop-webp-converter'),
            'manage_options',
            'icwc-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('icwc_settings_group', 'icwc_settings', array($this, 'sanitize_settings'));

        // WebP Settings Section
        add_settings_section(
            'icwc_webp_section',
            __('WebP Conversion Settings', 'image-crop-webp-converter'),
            array($this, 'render_webp_section'),
            'icwc-settings'
        );

        add_settings_field(
            'auto_webp_conversion',
            __('Enable Auto WebP Conversion', 'image-crop-webp-converter'),
            array($this, 'render_checkbox_field'),
            'icwc-settings',
            'icwc_webp_section',
            array('field' => 'auto_webp_conversion', 'label' => __('Automatically convert uploaded images to WebP format', 'image-crop-webp-converter'))
        );

        add_settings_field(
            'keep_original',
            __('Keep Original Files', 'image-crop-webp-converter'),
            array($this, 'render_checkbox_field'),
            'icwc-settings',
            'icwc_webp_section',
            array('field' => 'keep_original', 'label' => __('Keep original files after WebP conversion', 'image-crop-webp-converter'))
        );

        add_settings_field(
            'webp_quality',
            __('WebP Quality', 'image-crop-webp-converter'),
            array($this, 'render_number_field'),
            'icwc-settings',
            'icwc_webp_section',
            array('field' => 'webp_quality', 'min' => 1, 'max' => 100, 'default' => 80, 'description' => __('Quality of WebP conversion (1-100)', 'image-crop-webp-converter'))
        );

        // Crop Settings Section
        add_settings_section(
            'icwc_crop_section',
            __('Crop & Regenerate Settings', 'image-crop-webp-converter'),
            array($this, 'render_crop_section'),
            'icwc-settings'
        );

        add_settings_field(
            'enable_crop',
            __('Enable Crop Feature', 'image-crop-webp-converter'),
            array($this, 'render_checkbox_field'),
            'icwc-settings',
            'icwc_crop_section',
            array('field' => 'enable_crop', 'label' => __('Enable crop functionality in media library', 'image-crop-webp-converter'))
        );

        add_settings_field(
            'enable_regenerate',
            __('Enable Regenerate Feature', 'image-crop-webp-converter'),
            array($this, 'render_checkbox_field'),
            'icwc-settings',
            'icwc_crop_section',
            array('field' => 'enable_regenerate', 'label' => __('Enable image regeneration functionality', 'image-crop-webp-converter'))
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        $sanitized['auto_webp_conversion'] = isset($input['auto_webp_conversion']) ? true : false;
        $sanitized['keep_original'] = isset($input['keep_original']) ? true : false;
        $sanitized['webp_quality'] = isset($input['webp_quality']) ? intval($input['webp_quality']) : 80;
        $sanitized['enable_crop'] = isset($input['enable_crop']) ? true : false;
        $sanitized['enable_regenerate'] = isset($input['enable_regenerate']) ? true : false;

        // Validate quality range
        if ($sanitized['webp_quality'] < 1 || $sanitized['webp_quality'] > 100) {
            $sanitized['webp_quality'] = 80;
            add_settings_error('icwc_settings', 'invalid_quality', __('WebP quality must be between 1 and 100. Reset to default (80).', 'image-crop-webp-converter'));
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check WebP support
        $webp_support = function_exists('imagewebp');
        $gd_info = function_exists('gd_info') ? gd_info() : array();
        $webp_enabled = isset($gd_info['WebP Support']) ? $gd_info['WebP Support'] : false;

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!$webp_support || !$webp_enabled): ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('WebP Support Not Available!', 'image-crop-webp-converter'); ?></strong></p>
                    <p><?php _e('Your server does not support WebP image format. Please contact your hosting provider to enable WebP support in GD library.', 'image-crop-webp-converter'); ?></p>
                    <p><?php _e('Technical details:', 'image-crop-webp-converter'); ?></p>
                    <ul>
                        <li>imagewebp() function: <?php echo $webp_support ? '✓ Available' : '✗ Not available'; ?></li>
                        <li>GD WebP Support: <?php echo $webp_enabled ? '✓ Enabled' : '✗ Disabled'; ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong><?php _e('WebP Support: Active', 'image-crop-webp-converter'); ?></strong></p>
                </div>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('icwc_settings_group');
                do_settings_sections('icwc-settings');
                submit_button(__('Save Settings', 'image-crop-webp-converter'));
                ?>
            </form>

            <div class="icwc-tools-section" style="margin-top: 40px;">
                <h2><?php _e('Bulk Tools', 'image-crop-webp-converter'); ?></h2>
                <p><?php _e('These tools allow you to process all images in your media library.', 'image-crop-webp-converter'); ?></p>

                <div class="icwc-bulk-tools">
                    <button type="button" class="button button-primary" id="icwc-bulk-regenerate">
                        <?php _e('Regenerate All Images', 'image-crop-webp-converter'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="icwc-bulk-webp">
                        <?php _e('Convert All Images to WebP', 'image-crop-webp-converter'); ?>
                    </button>
                    <div id="icwc-bulk-progress" style="display: none; margin-top: 20px;">
                        <progress id="icwc-progress-bar" value="0" max="100" style="width: 100%; height: 30px;"></progress>
                        <p id="icwc-progress-text"></p>
                    </div>
                </div>
            </div>

            <div class="icwc-tools-section" style="margin-top: 40px;">
                <h2><?php _e('System Information', 'image-crop-webp-converter'); ?></h2>
                <table class="widefat" style="max-width: 600px;">
                    <tbody>
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>GD Library:</strong></td>
                            <td><?php echo extension_loaded('gd') ? 'Installed' : 'Not installed'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>ImageMagick:</strong></td>
                            <td><?php echo extension_loaded('imagick') ? 'Installed' : 'Not installed'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>WebP Support:</strong></td>
                            <td><?php echo $webp_enabled ? '✓ Yes' : '✗ No'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Auto WebP Conversion:</strong></td>
                            <td><?php
                                $settings = get_option('icwc_settings', array());
                                $enabled = isset($settings['auto_webp_conversion']) ? $settings['auto_webp_conversion'] : false;
                                echo $enabled ? '✓ Enabled' : '✗ Disabled';
                                ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php
    }

    /**
     * Render sections
     */
    public function render_webp_section()
    {
        echo '<p>' . __('Configure automatic WebP conversion for uploaded images.', 'image-crop-webp-converter') . '</p>';
    }

    public function render_crop_section()
    {
        echo '<p>' . __('Configure image cropping and regeneration features.', 'image-crop-webp-converter') . '</p>';
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args)
    {
        $settings = get_option('icwc_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : false;
    ?>
        <label>
            <input type="checkbox" name="icwc_settings[<?php echo esc_attr($args['field']); ?>]" value="1" <?php checked($value, true); ?>>
            <?php echo esc_html($args['label']); ?>
        </label>
    <?php
    }

    /**
     * Render number field
     */
    public function render_number_field($args)
    {
        $settings = get_option('icwc_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : $args['default'];
    ?>
        <input type="number" name="icwc_settings[<?php echo esc_attr($args['field']); ?>]" value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($args['min']); ?>" max="<?php echo esc_attr($args['max']); ?>" class="small-text">
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on media pages, post edit pages, and settings page
        if (!in_array($hook, array('post.php', 'post-new.php', 'upload.php', 'settings_page_icwc-settings'))) {
            return;
        }

        wp_enqueue_style('icwc-admin-css', ICWC_PLUGIN_URL . 'assets/css/admin.css', array(), ICWC_VERSION);
        wp_enqueue_script('icwc-admin-js', ICWC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ICWC_VERSION, true);

        // Enqueue Cropper.js library for crop functionality
        wp_enqueue_style('cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css', array(), '1.5.13');
        wp_enqueue_script('cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js', array('jquery'), '1.5.13', true);

        // Localize script
        wp_localize_script('icwc-admin-js', 'icwcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'cropNonce' => wp_create_nonce('icwc_crop_nonce'),
            'regenerateNonce' => wp_create_nonce('icwc_regenerate_nonce'),
            'cleanupNonce' => wp_create_nonce('icwc_cleanup_nonce'),
            'bulkNonce' => wp_create_nonce('icwc_bulk_nonce'),
            'i18n' => array(
                'cropTitle' => __('Crop Image', 'image-crop-webp-converter'),
                'regenerateTitle' => __('Regenerate Image Sizes', 'image-crop-webp-converter'),
                'processing' => __('Processing...', 'image-crop-webp-converter'),
                'success' => __('Success!', 'image-crop-webp-converter'),
                'error' => __('Error occurred', 'image-crop-webp-converter'),
                'confirmCleanup' => __('This will delete all generated image sizes and keep only the original. Continue?', 'image-crop-webp-converter'),
            ),
        ));

        // Add modal HTML to footer for upload.php, post.php, and post-new.php
        if (in_array($hook, array('upload.php', 'post.php', 'post-new.php'))) {
            add_action('admin_footer', array($this, 'render_crop_modal'));
        }

        // Also add to print_media_templates for media modal
        add_action('print_media_templates', array($this, 'render_crop_modal'));
    }

    /**
     * Add action links
     */
    public function add_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=icwc-settings'),
            __('Settings', 'image-crop-webp-converter')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Add media column
     */
    public function add_media_column($columns)
    {
        $columns['icwc_info'] = __('Image Info', 'image-crop-webp-converter');
        return $columns;
    }

    /**
     * Display media column
     */
    public function display_media_column($column_name, $post_id)
    {
        if ($column_name !== 'icwc_info') {
            return;
        }

        $post = get_post($post_id);
        if (strpos($post->post_mime_type, 'image/') === false) {
            return;
        }

        $metadata = wp_get_attachment_metadata($post_id);
        $registered_sizes = wp_get_registered_image_subsizes();
        $existing_sizes = isset($metadata['sizes']) ? count($metadata['sizes']) : 0;
        $total_sizes = count($registered_sizes);
        $missing_sizes = $total_sizes - $existing_sizes;

        // Check for WebP version
        $file_path = get_attached_file($post_id);
        $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path);
        $has_webp = file_exists($webp_path);

        echo '<div class="icwc-info">';
        echo sprintf(__('Sizes: %d/%d', 'image-crop-webp-converter'), $existing_sizes, $total_sizes);

        if ($missing_sizes > 0) {
            echo ' <span style="color: orange;">(' . sprintf(__('%d missing', 'image-crop-webp-converter'), $missing_sizes) . ')</span>';
        }

        if ($has_webp) {
            echo '<br><span style="color: green;">✓ WebP</span>';
        }

        echo '</div>';
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices()
    {
        if (isset($_GET['icwc_regenerated'])) {
            $count = intval($_GET['icwc_regenerated']);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('%d image(s) regenerated successfully.', 'image-crop-webp-converter'), $count) . '</p>';
            echo '</div>';
        }

        if (isset($_GET['icwc_cleaned'])) {
            $count = intval($_GET['icwc_cleaned']);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('%d image(s) cleaned up successfully.', 'image-crop-webp-converter'), $count) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render crop modal in footer for media library
     */
    public function render_crop_modal()
    {
        // Prevent duplicate rendering
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
    ?>
        <!-- Crop Modal -->
        <div id="icwc-crop-modal" class="icwc-modal" style="display: none;">
            <div class="icwc-modal-content">
                <div class="icwc-modal-header">
                    <h2><?php _e('Crop Image', 'image-crop-webp-converter'); ?></h2>
                    <button type="button" class="icwc-modal-close">&times;</button>
                </div>
                <div class="icwc-modal-body">
                    <div class="icwc-crop-container">
                        <img id="icwc-crop-image" src="" alt="">
                    </div>
                    <div class="icwc-crop-options">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">
                                <strong><?php _e('Profile Name:', 'image-crop-webp-converter'); ?></strong>
                            </label>
                            <input type="text" id="icwc-crop-profile-name" class="widefat" placeholder="<?php _e('e.g. hero, thumbnail, banner', 'image-crop-webp-converter'); ?>" style="margin-bottom: 5px;">
                            <p class="description" style="margin: 0; color: #666;">
                                <?php _e('Enter a name for this crop profile. The file will be saved as: <strong>image-name-profile.webp</strong>', 'image-crop-webp-converter'); ?>
                            </p>
                            <p class="description" style="margin: 5px 0 0 0; color: #2271b1;">
                                <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle;"></span>
                                <?php _e('Each crop is saved as a separate WebP file alongside the original image.', 'image-crop-webp-converter'); ?>
                            </p>
                        </div>
                        <label>
                            <?php _e('Aspect Ratio:', 'image-crop-webp-converter'); ?>
                            <select id="icwc-aspect-ratio">
                                <option value="free"><?php _e('Free', 'image-crop-webp-converter'); ?></option>
                                <option value="1"><?php _e('Square (1:1)', 'image-crop-webp-converter'); ?></option>
                                <option value="1.333"><?php _e('4:3', 'image-crop-webp-converter'); ?></option>
                                <option value="1.777"><?php _e('16:9', 'image-crop-webp-converter'); ?></option>
                                <option value="0.75"><?php _e('3:4', 'image-crop-webp-converter'); ?></option>
                                <option value="0.5625"><?php _e('9:16', 'image-crop-webp-converter'); ?></option>
                            </select>
                        </label>
                    </div>
                    <div class="icwc-crop-preview">
                        <h4><?php _e('Preview', 'image-crop-webp-converter'); ?></h4>
                        <div id="icwc-preview-box"></div>
                    </div>
                </div>
                <div class="icwc-modal-footer">
                    <button type="button" class="button button-secondary icwc-modal-cancel"><?php _e('Cancel', 'image-crop-webp-converter'); ?></button>
                    <button type="button" class="button button-primary icwc-apply-crop"><?php _e('Apply Crop', 'image-crop-webp-converter'); ?></button>
                </div>
            </div>
        </div>
<?php
    }
}
