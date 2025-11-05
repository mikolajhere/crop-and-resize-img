<?php

/**
 * Image Regenerator Class
 *
 * Handles regeneration of image sizes and cleanup functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ICWC_Image_Regenerator
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
        // AJAX handler for regenerate
        add_action('wp_ajax_icwc_regenerate_image', array($this, 'ajax_regenerate_image'));

        // AJAX handler for cleanup
        add_action('wp_ajax_icwc_cleanup_image', array($this, 'ajax_cleanup_image'));

        // AJAX handler for bulk regenerate
        add_action('wp_ajax_icwc_bulk_regenerate', array($this, 'ajax_bulk_regenerate'));

        // AJAX handler for getting all images
        add_action('wp_ajax_icwc_get_all_images', array($this, 'ajax_get_all_images'));

        // Add bulk actions
        add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);
    }

    /**
     * Add regenerate button to attachment fields
     */
    public function add_regenerate_button($form_fields, $post)
    {
        if (strpos($post->post_mime_type, 'image/') !== false) {
            $form_fields['icwc_regenerate'] = array(
                'label' => __('Regenerate Sizes', 'image-crop-webp-converter'),
                'input' => 'html',
                'html' => sprintf(
                    '<button type="button" class="button icwc-regenerate-button" data-attachment-id="%d">%s</button>
                    <button type="button" class="button icwc-cleanup-button" data-attachment-id="%d" style="margin-left: 5px;">%s</button>',
                    $post->ID,
                    __('Regenerate All Sizes', 'image-crop-webp-converter'),
                    $post->ID,
                    __('Raw Cleanup', 'image-crop-webp-converter')
                ),
            );
        }

        return $form_fields;
    }

    /**
     * Add bulk actions
     */
    public function add_bulk_actions($bulk_actions)
    {
        $bulk_actions['icwc_regenerate'] = __('Regenerate Image Sizes', 'image-crop-webp-converter');
        $bulk_actions['icwc_cleanup'] = __('Raw Cleanup (Keep Original Only)', 'image-crop-webp-converter');
        return $bulk_actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids)
    {
        if ($action === 'icwc_regenerate') {
            foreach ($post_ids as $post_id) {
                $this->regenerate_image($post_id);
            }

            $redirect_to = add_query_arg('icwc_regenerated', count($post_ids), $redirect_to);
        } elseif ($action === 'icwc_cleanup') {
            foreach ($post_ids as $post_id) {
                $this->cleanup_image($post_id);
            }

            $redirect_to = add_query_arg('icwc_cleaned', count($post_ids), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * AJAX handler for regenerating image
     */
    public function ajax_regenerate_image()
    {
        check_ajax_referer('icwc_regenerate_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'image-crop-webp-converter')));
        }

        $result = $this->regenerate_image($attachment_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Image sizes regenerated successfully', 'image-crop-webp-converter'),
                'sizes' => $result,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to regenerate image sizes', 'image-crop-webp-converter')));
        }
    }

    /**
     * AJAX handler for cleanup
     */
    public function ajax_cleanup_image()
    {
        check_ajax_referer('icwc_cleanup_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_id = intval($_POST['attachment_id']);

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'image-crop-webp-converter')));
        }

        $result = $this->cleanup_image($attachment_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Image cleaned up successfully. Only original file remains.', 'image-crop-webp-converter'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to cleanup image', 'image-crop-webp-converter')));
        }
    }

    /**
     * AJAX handler for bulk regenerate
     */
    public function ajax_bulk_regenerate()
    {
        check_ajax_referer('icwc_bulk_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', $_POST['attachment_ids']) : array();

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No attachments selected', 'image-crop-webp-converter')));
        }

        $results = array(
            'success' => 0,
            'failed' => 0,
        );

        foreach ($attachment_ids as $attachment_id) {
            if ($this->regenerate_image($attachment_id)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for getting all images
     */
    public function ajax_get_all_images()
    {
        check_ajax_referer('icwc_bulk_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'image-crop-webp-converter')));
        }

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $attachments = get_posts($args);

        if (empty($attachments)) {
            wp_send_json_error(array('message' => __('No images found', 'image-crop-webp-converter')));
        }

        wp_send_json_success(array('attachments' => $attachments));
    }

    /**
     * Regenerate image sizes
     */
    public function regenerate_image($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return false;
        }

        // Get current metadata
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$metadata) {
            return false;
        }

        // Delete existing intermediate sizes
        $this->delete_intermediate_sizes($attachment_id, $metadata);

        // Regenerate intermediate sizes
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $new_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);

        if (is_wp_error($new_metadata)) {
            return false;
        }

        // Update metadata
        wp_update_attachment_metadata($attachment_id, $new_metadata);

        // Convert new sizes to WebP
        $webp_converter = ICWC_WebP_Converter::get_instance();
        $settings = get_option('icwc_settings', array('auto_webp_conversion' => true));

        if ($settings['auto_webp_conversion']) {
            $upload_dir = wp_upload_dir();
            $file_dir = dirname($new_metadata['file']);

            foreach ($new_metadata['sizes'] as $size_name => $size_data) {
                $size_file_path = $upload_dir['basedir'] . '/' . $file_dir . '/' . $size_data['file'];
                $webp_converter->convert_image_to_webp($size_file_path);
            }
        }

        return $new_metadata['sizes'];
    }

    /**
     * Cleanup image - keep only original
     */
    public function cleanup_image($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return false;
        }

        // Get current metadata
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$metadata) {
            return false;
        }

        // Delete all intermediate sizes
        $this->delete_intermediate_sizes($attachment_id, $metadata);

        // Clear size metadata but keep original info
        $original_file = $metadata['file'];
        $original_width = isset($metadata['width']) ? $metadata['width'] : 0;
        $original_height = isset($metadata['height']) ? $metadata['height'] : 0;

        $metadata = array(
            'file' => $original_file,
            'width' => $original_width,
            'height' => $original_height,
        );

        // Update metadata
        wp_update_attachment_metadata($attachment_id, $metadata);

        return true;
    }

    /**
     * Delete intermediate image sizes
     */
    private function delete_intermediate_sizes($attachment_id, $metadata)
    {
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $file_dir = dirname($metadata['file']);

        foreach ($metadata['sizes'] as $size_name => $size_data) {
            $file_path = $upload_dir['basedir'] . '/' . $file_dir . '/' . $size_data['file'];

            if (file_exists($file_path)) {
                @unlink($file_path);
            }

            // Delete WebP version if exists
            $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path);
            if (file_exists($webp_path)) {
                @unlink($webp_path);
            }

            // Delete WebP version with size suffix
            if (isset($size_data['webp_file'])) {
                $webp_file_path = $upload_dir['basedir'] . '/' . $file_dir . '/' . $size_data['webp_file'];
                if (file_exists($webp_file_path)) {
                    @unlink($webp_file_path);
                }
            }
        }
    }

    /**
     * Get missing image sizes
     */
    public function get_missing_sizes($attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $registered_sizes = wp_get_registered_image_subsizes();
        $missing_sizes = array();

        foreach ($registered_sizes as $size_name => $size_data) {
            if (!isset($metadata['sizes'][$size_name])) {
                $missing_sizes[] = $size_name;
            }
        }

        return $missing_sizes;
    }
}
