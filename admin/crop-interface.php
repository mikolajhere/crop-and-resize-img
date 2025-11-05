<?php

/**
 * Crop Interface Template
 *
 * Displays the crop interface on the edit media page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="icwc-crop-interface" style="margin: 20px 0;">
    <h2 style="padding-left:0"><?php _e('Image Crop & Size Management', 'image-crop-webp-converter'); ?></h2>

    <div class="icwc-actions" style="margin: 15px 0;">
        <!-- <button type="button" class="button button-primary icwc-open-crop" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
            <?php _e('Crop Image', 'image-crop-webp-converter'); ?>
        </button>
        <button type="button" class="button icwc-regenerate-button" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
            <?php _e('Regenerate All Sizes', 'image-crop-webp-converter'); ?>
        </button> -->
        <?php
        // Check if WebP version exists
        $file_path = get_attached_file($attachment_id);
        $path_info = pathinfo($file_path);
        $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
        $has_webp = file_exists($webp_path);
        $button_text = $has_webp ? __('Regenerate WebP', 'image-crop-webp-converter') : __('Convert to WebP', 'image-crop-webp-converter');
        $button_style = $has_webp ? 'background: #46b450; color: #fff; border-color: #46b450;' : '';
        ?>
        <button type="button" class="button icwc-convert-webp" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" style="<?php echo esc_attr($button_style); ?>">
            <?php echo esc_html($button_text); ?>
        </button>
        <!-- <button type="button" class="button icwc-cleanup-button" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" style="color: #a00;">
            <?php _e('Raw Cleanup', 'image-crop-webp-converter'); ?>
        </button> -->
    </div>

    <?php if ($has_webp): ?>

        <?php
        // Get WebP URL
        $original_url = wp_get_attachment_url($attachment_id);
        $webp_url = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $original_url);
        ?>
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 10px 0; border-radius: 4px;">
            <h3 style="margin-top: 0;"><?php _e('WebP File URL', 'image-crop-webp-converter'); ?></h3>
            <div style="display: flex; gap: 10px; align-items: flex-start;">
                <input type="text" readonly value="<?php echo esc_attr($webp_url); ?>"
                    class="widefat icwc-webp-url-field"
                    id="icwc-webp-url-<?php echo esc_attr($attachment_id); ?>"
                    style="flex: 1;">
                <button type="button"
                    class="button icwc-copy-webp-url"
                    data-target="icwc-webp-url-<?php echo esc_attr($attachment_id); ?>"
                    style="white-space: nowrap;">
                    <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                    <?php _e('Copy WebP URL', 'image-crop-webp-converter'); ?>
                </button>
            </div>
            <p class="description" style="margin: 10px 0 0 0;">
                <?php _e('Use this URL to display the WebP version of your image.', 'image-crop-webp-converter'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php
    // Display existing crop profiles
    $crop_profiles = get_post_meta($attachment_id, '_icwc_crop_profiles', true);
    if (!empty($crop_profiles) && is_array($crop_profiles)):
    ?>
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 10px 0; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;"><?php _e('Crop and resize profiles', 'image-crop-webp-converter'); ?></h3>
                <button type="button" class="button button-primary icwc-open-crop" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-top: 3px;"></span>
                    <?php _e('Add Profile', 'image-crop-webp-converter'); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 0;"><?php _e('These are your saved crop and resize profiles in WebP format:', 'image-crop-webp-converter'); ?></p>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php
                $original_url = wp_get_attachment_url($attachment_id);
                $base_url = dirname($original_url);

                foreach ($crop_profiles as $profile_name => $profile_data):
                    $profile_url = $base_url . '/' . $profile_data['filename'];
                ?>
                    <div class="icwc-crop-profile-card" data-profile="<?php echo esc_attr($profile_name); ?>" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;">
                        <div style="background: #fff; padding: 5px; margin-bottom: 10px; text-align: center; border-radius: 4px;">
                            <img src="<?php echo esc_url($profile_url); ?>" alt="<?php echo esc_attr($profile_name); ?>" style="max-width: 100%; height: auto; display: block; border-radius: 4px;">
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong style="display: block; color: #2271b1; margin-bottom: 5px;"><?php echo esc_html($profile_name); ?></strong>
                            <small style="color: #666;">
                                <?php echo esc_html($profile_data['width']); ?> × <?php echo esc_html($profile_data['height']); ?>px<br>
                                <?php
                                if (!empty($profile_data['resize_data'])) {
                                    echo '<span style="color: #2271b1;">📐 ' . __('Resized', 'image-crop-webp-converter') . '</span><br>';
                                }
                                echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($profile_data['created']));
                                ?>
                            </small>
                        </div>
                        <input type="text" readonly value="<?php echo esc_attr($profile_url); ?>"
                            class="widefat icwc-webp-url-field"
                            id="icwc-profile-url-<?php echo esc_attr($attachment_id . '-' . $profile_name); ?>"
                            style="font-size: 11px; margin-bottom: 8px;">
                        <div style="display: flex; gap: 5px;">
                            <button type="button"
                                class="button button-small icwc-copy-webp-url"
                                data-target="icwc-profile-url-<?php echo esc_attr($attachment_id . '-' . $profile_name); ?>"
                                style="flex: 1; font-size: 11px;">
                                <span class="dashicons dashicons-clipboard" style="font-size: 14px; vertical-align: middle;"></span>
                                <?php _e('Copy', 'image-crop-webp-converter'); ?>
                            </button>
                            <button type="button"
                                class="button button-small icwc-delete-crop-profile"
                                data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                data-profile="<?php echo esc_attr($profile_name); ?>"
                                style="flex: 1; font-size: 11px; color: #a00;">
                                <span class="dashicons dashicons-trash" style="font-size: 14px; vertical-align: middle;"></span>
                                <?php _e('Delete', 'image-crop-webp-converter'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 10px 0; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;"><?php _e('Crop and resize profiles', 'image-crop-webp-converter'); ?></h3>
                <button type="button" class="button button-primary icwc-open-crop" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-top: 3px;"></span>
                    <?php _e('Add Profile', 'image-crop-webp-converter'); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 0; color: #666;">
                <?php _e('No crop profiles yet. Create custom crops of this image in WebP format by clicking "Add Profile" above.', 'image-crop-webp-converter'); ?>
            </p>
            <div style="padding: 30px; text-align: center; background: #f9f9f9; border-radius: 4px; border: 2px dashed #ddd;">
                <span class="dashicons dashicons-images-alt2" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></span>
                <p style="color: #666; margin: 10px 0 0 0;">
                    <?php _e('Click "Add Profile" to create your first crop profile', 'image-crop-webp-converter'); ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="icwc-sizes-info" style="display: none">
        <h3><?php _e('Registered Image Sizes', 'image-crop-webp-converter'); ?></h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Size Name', 'image-crop-webp-converter'); ?></th>
                    <th><?php _e('Dimensions', 'image-crop-webp-converter'); ?></th>
                    <th><?php _e('Crop', 'image-crop-webp-converter'); ?></th>
                    <th><?php _e('Status', 'image-crop-webp-converter'); ?></th>
                    <th><?php _e('Actions', 'image-crop-webp-converter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($image_sizes as $size_name => $size_info): ?>
                    <tr>
                        <td><strong><?php echo esc_html($size_name); ?></strong></td>
                        <td>
                            <?php
                            $dimensions = $size_info['dimensions'];
                            echo esc_html($dimensions['width']) . ' × ' . esc_html($dimensions['height']);
                            if (isset($dimensions['actual_width']) && isset($dimensions['actual_height'])) {
                                echo '<br><small>(' . esc_html($dimensions['actual_width']) . ' × ' . esc_html($dimensions['actual_height']) . ')</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($dimensions['crop']) {
                                echo '<span style="color: green;">✓ ' . __('Yes', 'image-crop-webp-converter') . '</span>';
                            } else {
                                echo '<span style="color: gray;">' . __('No', 'image-crop-webp-converter') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($size_info['exists']): ?>
                                <span style="color: green;">✓ <?php _e('Exists', 'image-crop-webp-converter'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;">⚠ <?php _e('Missing', 'image-crop-webp-converter'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($size_info['exists']): ?>
                                <button type="button" class="button button-small icwc-recrop-size" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-size="<?php echo esc_attr($size_name); ?>">
                                    <?php _e('Re-crop', 'image-crop-webp-converter'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="button button-small icwc-generate-size" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-size="<?php echo esc_attr($size_name); ?>">
                                    <?php _e('Generate', 'image-crop-webp-converter'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Crop Modal -->
<div id="icwc-crop-modal" class="icwc-modal" style="display: none;">
    <div class="icwc-modal-content">
        <div class="icwc-modal-header">
            <h2><?php _e('Crop Image', 'image-crop-webp-converter'); ?></h2>
            <button type="button" class="icwc-modal-close">&times;</button>
        </div>
        <div class="icwc-modal-body">
            <div class="icwc-crop-container">
                <img id="icwc-crop-image" src="<?php echo esc_url($image_url); ?>" alt="">
            </div>
            <div class="icwc-crop-options">
                <div style="margin-bottom: 15px;">
                    <label for="icwc-crop-profile-name">
                        <strong><?php _e('Profile Name:', 'image-crop-webp-converter'); ?></strong>
                    </label>
                    <input type="text"
                        id="icwc-crop-profile-name"
                        name="icwc-crop-profile-name"
                        placeholder="<?php _e('e.g., thumbnail, hero, banner', 'image-crop-webp-converter'); ?>"
                        style="width: 100%; padding: 8px 12px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    <p class="description" style="margin-top: 5px;">
                        <?php _e('Enter a unique name for this crop profile (alphanumeric, dash, underscore)', 'image-crop-webp-converter'); ?>
                    </p>
                </div>

                <div style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #ddd;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center;">
                            <input type="checkbox" id="icwc-enable-resize" style="margin: 0 8px 0 0;">
                            <label for="icwc-enable-resize" style="margin: 0; font-weight: 600;">
                                <?php _e('Resize image before cropping (optional)', 'image-crop-webp-converter'); ?>
                            </label>
                        </div>
                        <span id="icwc-original-dimensions" style="font-size: 12px; color: #666; display: none;">
                            <?php _e('Original:', 'image-crop-webp-converter'); ?> <strong id="icwc-original-size"></strong>
                        </span>
                    </div>
                    <div id="icwc-resize-options" style="display: none; margin-top: 10px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label for="icwc-resize-width" style="display: block; margin-bottom: 5px;">
                                    <strong><?php _e('Width (px):', 'image-crop-webp-converter'); ?></strong>
                                </label>
                                <input type="number" id="icwc-resize-width" min="1" placeholder="<?php _e('e.g., 1920', 'image-crop-webp-converter'); ?>" style="width: 100%; padding: 8px;">
                            </div>
                            <div>
                                <label for="icwc-resize-height" style="display: block; margin-bottom: 5px;">
                                    <strong><?php _e('Height (px):', 'image-crop-webp-converter'); ?></strong>
                                </label>
                                <input type="number" id="icwc-resize-height" min="1" placeholder="<?php _e('e.g., 1080', 'image-crop-webp-converter'); ?>" style="width: 100%; padding: 8px;">
                            </div>
                        </div>
                        <p class="description" style="margin: 8px 0 0 0;">
                            <strong>💡 <?php _e('Tip:', 'image-crop-webp-converter'); ?></strong> <?php _e('Enter width or height - the other value will be calculated automatically to maintain aspect ratio.', 'image-crop-webp-converter'); ?>
                        </p>
                    </div>
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