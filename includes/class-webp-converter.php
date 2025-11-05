<?php

/**
 * WebP Converter Class
 *
 * Handles automatic conversion of uploaded images to WebP format
 */

if (!defined('ABSPATH')) {
    exit;
}

class ICWC_WebP_Converter
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
        // Convert to WebP after upload
        add_filter('wp_handle_upload', array($this, 'convert_to_webp_on_upload'), 10, 2);

        // Convert intermediate sizes to WebP
        add_filter('wp_generate_attachment_metadata', array($this, 'convert_intermediate_sizes_to_webp'), 10, 2);

        // Add WebP to allowed mime types
        add_filter('upload_mimes', array($this, 'add_webp_mime_type'));

        // Fix WebP display in media library
        add_filter('file_is_displayable_image', array($this, 'webp_is_displayable'), 10, 2);

        // Add convert button and WebP URL to attachment fields
        add_filter('attachment_fields_to_edit', array($this, 'add_convert_button'), 10, 2);

        // AJAX handler for manual conversion
        add_action('wp_ajax_icwc_convert_to_webp', array($this, 'ajax_convert_to_webp'));
    }

    /**
     * Add WebP to allowed mime types
     */
    public function add_webp_mime_type($mimes)
    {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    }

    /**
     * Make WebP displayable in media library
     */
    public function webp_is_displayable($result, $path)
    {
        if ($result === false) {
            $info = @getimagesize($path);
            if ($info && $info['mime'] === 'image/webp') {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Convert uploaded image to WebP
     */
    public function convert_to_webp_on_upload($upload, $context)
    {
        $settings = get_option('icwc_settings', array('auto_webp_conversion' => true));

        if (!$settings['auto_webp_conversion']) {
            return $upload;
        }

        // Only process images
        if (strpos($upload['type'], 'image/') === false) {
            return $upload;
        }

        // Skip if already WebP
        if ($upload['type'] === 'image/webp') {
            return $upload;
        }

        $file_path = $upload['file'];
        $webp_path = $this->convert_image_to_webp($file_path);

        if ($webp_path) {
            $keep_original = isset($settings['keep_original']) ? $settings['keep_original'] : true;

            if (!$keep_original) {
                // Replace original with WebP
                @unlink($file_path);
                $upload['file'] = $webp_path;
                $upload['type'] = 'image/webp';
                $upload['url'] = str_replace(basename($file_path), basename($webp_path), $upload['url']);
            } else {
                // Keep both - store WebP path in metadata
                update_post_meta($upload['id'], '_webp_path', $webp_path);
            }
        }

        return $upload;
    }

    /**
     * Convert intermediate image sizes to WebP
     */
    public function convert_intermediate_sizes_to_webp($metadata, $attachment_id)
    {
        $settings = get_option('icwc_settings', array('auto_webp_conversion' => true));

        if (!$settings['auto_webp_conversion']) {
            return $metadata;
        }

        if (!isset($metadata['file'])) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $file_dir = dirname($metadata['file']);

        // Convert each intermediate size
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $file_path = $base_dir . '/' . $file_dir . '/' . $size_data['file'];

                if (file_exists($file_path)) {
                    $webp_path = $this->convert_image_to_webp($file_path);

                    if ($webp_path) {
                        $metadata['sizes'][$size_name]['webp_file'] = basename($webp_path);
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Convert image to WebP format
     *
     * @param string $file_path Path to the image file
     * @return string|false Path to WebP file or false on failure
     */
    public function convert_image_to_webp($file_path)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        $settings = get_option('icwc_settings', array());
        $quality = isset($settings['webp_quality']) ? intval($settings['webp_quality']) : 80;

        // Get image info
        $image_info = @getimagesize($file_path);
        if (!$image_info) {
            return false;
        }

        $mime_type = $image_info['mime'];

        // Create image resource based on mime type
        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file_path);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file_path);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Preserve transparency for PNG
        if ($mime_type === 'image/png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        // Generate WebP path
        $path_info = pathinfo($file_path);
        $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';

        // Convert to WebP
        $result = imagewebp($image, $webp_path, $quality);
        imagedestroy($image);

        if ($result && file_exists($webp_path)) {
            return $webp_path;
        }

        return false;
    }

    /**
     * Get WebP version of an image if exists
     *
     * @param string $image_url Original image URL
     * @return string WebP URL or original URL
     */
    public function get_webp_url($image_url)
    {
        $path_info = pathinfo($image_url);
        $webp_url = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';

        // Convert URL to path
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);

        if (file_exists($webp_path)) {
            return $webp_url;
        }

        return $image_url;
    }

    /**
     * Add convert button to attachment fields
     */
    public function add_convert_button($form_fields, $post)
    {
        if (strpos($post->post_mime_type, 'image/') !== false && $post->post_mime_type !== 'image/webp') {
            // Check if WebP version exists
            $file_path = get_attached_file($post->ID);
            $path_info = pathinfo($file_path);
            $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
            $has_webp = file_exists($webp_path);

            $button_text = $has_webp
                ? __('Regenerate WebP', 'image-crop-webp-converter')
                : __('Convert to WebP', 'image-crop-webp-converter');

            $status_text = $has_webp
                ? '<span style="color: green;">✓ WebP version exists</span>'
                : '<span style="color: orange;">⚠ No WebP version</span>';

            $html = sprintf(
                '%s<br><button type="button" class="button icwc-convert-webp" data-attachment-id="%d">%s</button>',
                $status_text,
                $post->ID,
                $button_text
            );

            // If WebP exists, add URL field with copy button
            if ($has_webp) {
                $original_url = wp_get_attachment_url($post->ID);
                $webp_url = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $original_url);

                $html .= sprintf(
                    '<div style="margin-top: 10px;">
                        <strong>%s:</strong><br>
                        <input type="text" readonly value="%s" class="widefat icwc-webp-url-field" id="icwc-webp-url-%d" style="margin-top: 5px;">
                        <button type="button" class="button button-small icwc-copy-webp-url" data-target="icwc-webp-url-%d" style="margin-top: 5px;">
                            <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> %s
                        </button>
                    </div>',
                    __('WebP File URL', 'image-crop-webp-converter'),
                    esc_attr($webp_url),
                    $post->ID,
                    $post->ID,
                    __('Copy WebP URL', 'image-crop-webp-converter')
                );
            }

            $form_fields['icwc_webp'] = array(
                'label' => __('WebP Conversion', 'image-crop-webp-converter'),
                'input' => 'html',
                'html' => $html,
            );
        }

        return $form_fields;
    }

    /**
     * AJAX handler for manual WebP conversion
     */
    public function ajax_convert_to_webp()
    {
        check_ajax_referer('icwc_regenerate_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'image-crop-webp-converter')));
        }

        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => __('Image file not found', 'image-crop-webp-converter')));
        }

        // Convert main image
        $webp_path = $this->convert_image_to_webp($file_path);

        if (!$webp_path) {
            wp_send_json_error(array('message' => __('Failed to convert image to WebP. Check if WebP is supported on your server.', 'image-crop-webp-converter')));
        }

        // Convert all intermediate sizes
        $metadata = wp_get_attachment_metadata($attachment_id);
        $converted_sizes = 0;

        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $file_dir = dirname($metadata['file']);

            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $size_file_path = $upload_dir['basedir'] . '/' . $file_dir . '/' . $size_data['file'];

                if (file_exists($size_file_path)) {
                    $size_webp_path = $this->convert_image_to_webp($size_file_path);
                    if ($size_webp_path) {
                        $converted_sizes++;
                        $metadata['sizes'][$size_name]['webp_file'] = basename($size_webp_path);
                    }
                }
            }

            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Successfully converted to WebP! Main image + %d size(s) converted.', 'image-crop-webp-converter'),
                $converted_sizes
            ),
        ));
    }
}
