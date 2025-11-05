<?php
/**
 * Plugin Name: Image Crop & WebP Converter
 * Plugin URI: https://github.com/mikolajhere/claude-code-online
 * Description: Powerful image management plugin with automatic WebP conversion and advanced crop functionality. Allows regenerating image sizes, cropping with live preview, and cleaning up unused files.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/mikolajhere
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image-crop-webp-converter
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ICWC_VERSION', '1.0.0');
define('ICWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ICWC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Image_Crop_WebP_Converter {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once ICWC_PLUGIN_DIR . 'includes/class-webp-converter.php';
        require_once ICWC_PLUGIN_DIR . 'includes/class-image-cropper.php';
        require_once ICWC_PLUGIN_DIR . 'includes/class-image-regenerator.php';
        require_once ICWC_PLUGIN_DIR . 'admin/class-admin-interface.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize WebP converter
        ICWC_WebP_Converter::get_instance();

        // Initialize image cropper
        ICWC_Image_Cropper::get_instance();

        // Initialize image regenerator
        ICWC_Image_Regenerator::get_instance();

        // Initialize admin interface
        if (is_admin()) {
            ICWC_Admin_Interface::get_instance();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            deactivate_plugins(ICWC_PLUGIN_BASENAME);
            wp_die(__('This plugin requires PHP version 7.0 or higher.', 'image-crop-webp-converter'));
        }

        // Check if GD library is available
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            deactivate_plugins(ICWC_PLUGIN_BASENAME);
            wp_die(__('This plugin requires GD or ImageMagick extension.', 'image-crop-webp-converter'));
        }

        // Set default options
        $default_options = array(
            'auto_webp_conversion' => true,
            'keep_original' => true,
            'webp_quality' => 80,
            'enable_crop' => true,
            'enable_regenerate' => true,
        );

        add_option('icwc_settings', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'image-crop-webp-converter',
            false,
            dirname(ICWC_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * Initialize the plugin
 */
function icwc_init() {
    return Image_Crop_WebP_Converter::get_instance();
}

// Start the plugin
icwc_init();
