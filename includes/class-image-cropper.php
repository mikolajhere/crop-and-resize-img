<?php

/**
 * Image Cropper Class
 *
 * Handles image cropping functionality with live preview
 */

if (!defined('ABSPATH')) {
    exit;
}

class ICWC_Image_Cropper
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
        // AJAX handler for crop
        add_action('wp_ajax_icwc_crop_image', array($this, 'ajax_crop_image'));

        // AJAX handler for getting crop preview
        add_action('wp_ajax_icwc_get_crop_preview', array($this, 'ajax_get_crop_preview'));

        // AJAX handler for getting image URL
        add_action('wp_ajax_icwc_get_image_url', array($this, 'ajax_get_image_url'));

        // AJAX handler for deleting crop profile
        add_action('wp_ajax_icwc_delete_crop_profile', array($this, 'ajax_delete_crop_profile'));

        // Add crop interface to edit media page
        add_action('edit_form_after_title', array($this, 'add_crop_interface'));
    }

    /**
     * Add crop button to attachment fields
     */
    public function add_crop_button($form_fields, $post)
    {
        if (strpos($post->post_mime_type, 'image/') !== false) {
            $form_fields['icwc_crop'] = array(
                'label' => __('Crop Image', 'image-crop-webp-converter'),
                'input' => 'html',
                'html' => sprintf(
                    '<button type="button" class="button icwc-crop-button" data-attachment-id="%d">%s</button>',
                    $post->ID,
                    __('Crop & Select Sizes', 'image-crop-webp-converter')
                ),
            );
        }

        return $form_fields;
    }

    /**
     * Add crop interface to edit media page
     */
    public function add_crop_interface()
    {
        global $post;

        if (!$post || $post->post_type !== 'attachment' || strpos($post->post_mime_type, 'image/') === false) {
            return;
        }

        $attachment_id = $post->ID;
        $image_url = wp_get_attachment_url($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);
        $image_sizes = $this->get_image_sizes_info($attachment_id);

        include ICWC_PLUGIN_DIR . 'admin/crop-interface.php';
    }

    /**
     * Get information about registered image sizes
     */
    public function get_image_sizes_info($attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $image_sizes = array();

        // Get all registered image sizes
        $registered_sizes = wp_get_registered_image_subsizes();

        foreach ($registered_sizes as $size_name => $size_data) {
            $exists = isset($metadata['sizes'][$size_name]);
            $file_path = '';
            $dimensions = array(
                'width' => $size_data['width'],
                'height' => $size_data['height'],
                'crop' => $size_data['crop'],
            );

            if ($exists) {
                $upload_dir = wp_upload_dir();
                $file_dir = dirname($metadata['file']);
                $file_path = $upload_dir['basedir'] . '/' . $file_dir . '/' . $metadata['sizes'][$size_name]['file'];
                $dimensions['actual_width'] = $metadata['sizes'][$size_name]['width'];
                $dimensions['actual_height'] = $metadata['sizes'][$size_name]['height'];
            }

            $image_sizes[$size_name] = array(
                'name' => $size_name,
                'exists' => $exists,
                'dimensions' => $dimensions,
                'file_path' => $file_path,
            );
        }

        return $image_sizes;
    }

    /**
     * AJAX handler for cropping image
     */
    public function ajax_crop_image()
    {
        check_ajax_referer('icwc_crop_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $crop_data = isset($_POST['crop_data']) ? $_POST['crop_data'] : array();
        $resize_data = isset($_POST['resize_data']) ? $_POST['resize_data'] : null;
        $profile_name = isset($_POST['profile_name']) ? sanitize_text_field($_POST['profile_name']) : '';

        if (!$attachment_id || empty($crop_data)) {
            wp_send_json_error(array('message' => __('Invalid data', 'image-crop-webp-converter')));
        }

        if (empty($profile_name)) {
            wp_send_json_error(array('message' => __('Profile name is required', 'image-crop-webp-converter')));
        }

        $result = $this->crop_image_profile($attachment_id, $crop_data, $profile_name, $resize_data);

        if ($result) {
            $message = sprintf(__('Crop profile "%s" saved successfully as WebP!', 'image-crop-webp-converter'), $profile_name);
            if ($resize_data) {
                $message .= ' ' . __('(with resize applied)', 'image-crop-webp-converter');
            }
            wp_send_json_success(array(
                'message' => $message,
                'url' => $result['url'],
                'profile' => $profile_name,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create crop profile', 'image-crop-webp-converter')));
        }
    }

    /**
     * AJAX handler for getting crop preview
     */
    public function ajax_get_crop_preview()
    {
        check_ajax_referer('icwc_crop_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $crop_data = isset($_POST['crop_data']) ? $_POST['crop_data'] : array();

        if (!$attachment_id || empty($crop_data)) {
            wp_send_json_error(array('message' => __('Invalid data', 'image-crop-webp-converter')));
        }

        // Generate temporary preview
        $preview_url = $this->generate_crop_preview($attachment_id, $crop_data);

        if ($preview_url) {
            wp_send_json_success(array('preview_url' => $preview_url));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate preview', 'image-crop-webp-converter')));
        }
    }

    /**
     * AJAX handler for getting image URL
     */
    public function ajax_get_image_url()
    {
        check_ajax_referer('icwc_crop_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'image-crop-webp-converter')));
        }

        $image_url = wp_get_attachment_url($attachment_id);

        if (!$image_url) {
            wp_send_json_error(array('message' => __('Could not get image URL', 'image-crop-webp-converter')));
        }

        wp_send_json_success(array('url' => $image_url));
    }

    /**
     * Crop image and save as named profile in WebP format
     */
    public function crop_image_profile($attachment_id, $crop_data, $profile_name, $resize_data = null)
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return false;
        }

        // Get image editor
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return false;
        }

        // Apply resize first if requested
        if ($resize_data && (!empty($resize_data['width']) || !empty($resize_data['height']))) {
            $resize_width = !empty($resize_data['width']) ? intval($resize_data['width']) : null;
            $resize_height = !empty($resize_data['height']) ? intval($resize_data['height']) : null;

            // Resize the image
            $resize_result = $image_editor->resize($resize_width, $resize_height, false);

            if (is_wp_error($resize_result)) {
                return false;
            }

            // Calculate scaling factors for crop coordinates
            $original_size = $image_editor->get_size();
            $file_size = getimagesize($file_path);

            if ($file_size && $original_size) {
                $scale_x = $original_size['width'] / $file_size[0];
                $scale_y = $original_size['height'] / $file_size[1];

                // Adjust crop coordinates based on resize
                $crop_data['x'] = floatval($crop_data['x']) * $scale_x;
                $crop_data['y'] = floatval($crop_data['y']) * $scale_y;
                $crop_data['width'] = floatval($crop_data['width']) * $scale_x;
                $crop_data['height'] = floatval($crop_data['height']) * $scale_y;
            }
        }

        // Apply crop
        $crop_result = $image_editor->crop(
            floatval($crop_data['x']),
            floatval($crop_data['y']),
            floatval($crop_data['width']),
            floatval($crop_data['height'])
        );

        if (is_wp_error($crop_result)) {
            return false;
        }

        // Generate filename with profile name
        $path_info = pathinfo($file_path);
        $new_filename = $path_info['filename'] . '-' . $profile_name . '.webp';
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;

        // Save as WebP
        $settings = get_option('icwc_settings', array());
        $quality = isset($settings['webp_quality']) ? intval($settings['webp_quality']) : 80;

        // Set quality and save as WebP
        $image_editor->set_quality($quality);
        $saved = $image_editor->save($new_file_path, 'image/webp');

        if (is_wp_error($saved)) {
            return false;
        }

        // Store crop profile metadata
        $crop_profiles = get_post_meta($attachment_id, '_icwc_crop_profiles', true);
        if (!is_array($crop_profiles)) {
            $crop_profiles = array();
        }

        $crop_profiles[$profile_name] = array(
            'filename' => $new_filename,
            'path' => $new_file_path,
            'width' => $saved['width'],
            'height' => $saved['height'],
            'created' => current_time('mysql'),
            'crop_data' => $crop_data,
            'resize_data' => $resize_data,
        );

        update_post_meta($attachment_id, '_icwc_crop_profiles', $crop_profiles);

        $upload_dir = wp_upload_dir();
        $base_url = dirname(wp_get_attachment_url($attachment_id));

        return array(
            'path' => $new_file_path,
            'url' => $base_url . '/' . $new_filename,
            'filename' => $new_filename,
            'width' => $saved['width'],
            'height' => $saved['height'],
        );
    }

    /**
     * Crop image
     */
    public function crop_image($attachment_id, $crop_data, $size_name = '')
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return false;
        }

        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return false;
        }

        // Apply crop
        $crop_result = $image_editor->crop(
            floatval($crop_data['x']),
            floatval($crop_data['y']),
            floatval($crop_data['width']),
            floatval($crop_data['height'])
        );

        if (is_wp_error($crop_result)) {
            return false;
        }

        // Generate filename
        $path_info = pathinfo($file_path);
        $suffix = $size_name ? "-{$size_name}-crop" : '-crop';
        $new_filename = $path_info['filename'] . $suffix . '-' . time() . '.' . $path_info['extension'];
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;

        // Save cropped image
        $saved = $image_editor->save($new_file_path);

        if (is_wp_error($saved)) {
            return false;
        }

        // Update metadata if cropping specific size
        if ($size_name) {
            $metadata = wp_get_attachment_metadata($attachment_id);

            if (!isset($metadata['sizes'])) {
                $metadata['sizes'] = array();
            }

            $upload_dir = wp_upload_dir();
            $metadata['sizes'][$size_name] = array(
                'file' => $new_filename,
                'width' => $saved['width'],
                'height' => $saved['height'],
                'mime-type' => $saved['mime-type'],
            );

            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Convert to WebP if enabled
        $webp_converter = ICWC_WebP_Converter::get_instance();
        $webp_converter->convert_image_to_webp($new_file_path);

        return array(
            'path' => $new_file_path,
            'url' => str_replace(basename($file_path), $new_filename, wp_get_attachment_url($attachment_id)),
        );
    }

    /**
     * Generate crop preview
     */
    private function generate_crop_preview($attachment_id, $crop_data)
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return false;
        }

        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return false;
        }

        // Apply crop
        $image_editor->crop(
            floatval($crop_data['x']),
            floatval($crop_data['y']),
            floatval($crop_data['width']),
            floatval($crop_data['height'])
        );

        // Resize for preview
        $image_editor->resize(300, 300, false);

        // Save temporary preview
        $upload_dir = wp_upload_dir();
        $preview_filename = 'preview-' . $attachment_id . '-' . time() . '.jpg';
        $preview_path = $upload_dir['basedir'] . '/icwc-previews/' . $preview_filename;

        // Create preview directory if it doesn't exist
        if (!file_exists($upload_dir['basedir'] . '/icwc-previews/')) {
            wp_mkdir_p($upload_dir['basedir'] . '/icwc-previews/');
        }

        $saved = $image_editor->save($preview_path);

        if (is_wp_error($saved)) {
            return false;
        }

        return $upload_dir['baseurl'] . '/icwc-previews/' . $preview_filename;
    }

    /**
     * AJAX handler for deleting crop profile
     */
    public function ajax_delete_crop_profile()
    {
        check_ajax_referer('icwc_crop_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $profile_name = isset($_POST['profile_name']) ? sanitize_text_field($_POST['profile_name']) : '';

        if (!$attachment_id || empty($profile_name)) {
            wp_send_json_error(array('message' => __('Invalid data', 'image-crop-webp-converter')));
        }

        // Get crop profiles
        $crop_profiles = get_post_meta($attachment_id, '_icwc_crop_profiles', true);

        if (!is_array($crop_profiles) || !isset($crop_profiles[$profile_name])) {
            wp_send_json_error(array('message' => __('Crop profile not found', 'image-crop-webp-converter')));
        }

        // Delete the file
        $profile_data = $crop_profiles[$profile_name];
        if (file_exists($profile_data['path'])) {
            @unlink($profile_data['path']);
        }

        // Remove from metadata
        unset($crop_profiles[$profile_name]);
        update_post_meta($attachment_id, '_icwc_crop_profiles', $crop_profiles);

        wp_send_json_success(array(
            'message' => sprintf(__('Crop profile "%s" deleted successfully', 'image-crop-webp-converter'), $profile_name),
        ));
    }
}
