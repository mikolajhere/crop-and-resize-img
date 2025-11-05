<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options
 */
delete_option('icwc_settings');

/**
 * Delete post meta created by the plugin
 */
global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_webp_path'");

/**
 * Delete preview images directory
 */
$upload_dir = wp_upload_dir();
$preview_dir = $upload_dir['basedir'] . '/icwc-previews/';

if (file_exists($preview_dir)) {
    // Delete all files in preview directory
    $files = glob($preview_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    // Remove directory
    @rmdir($preview_dir);
}

/**
 * For multisite installations
 */
if (is_multisite()) {
    $sites = get_sites(array('fields' => 'ids'));

    foreach ($sites as $site_id) {
        switch_to_blog($site_id);

        delete_option('icwc_settings');

        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_webp_path'");

        $upload_dir = wp_upload_dir();
        $preview_dir = $upload_dir['basedir'] . '/icwc-previews/';

        if (file_exists($preview_dir)) {
            $files = glob($preview_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($preview_dir);
        }

        restore_current_blog();
    }
}
