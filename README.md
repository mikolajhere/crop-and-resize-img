# Image Crop & WebP Converter

Powerful WordPress plugin for image management with automatic WebP conversion and advanced crop functionality.

## Features

### 🎨 Advanced Image Cropping
- **Interactive Crop Interface**: User-friendly cropping tool with live preview
- **Multiple Aspect Ratios**: Free, Square (1:1), 4:3, 16:9, and more
- **Cropper.js Integration**: Professional-grade cropping with intuitive controls
- **Per-Size Cropping**: Crop different versions for specific image sizes
- **Instant Preview**: See your crop results in real-time

### 🖼️ Automatic WebP Conversion
- **Background Conversion**: Automatically converts uploaded images to WebP format
- **Quality Control**: Adjustable WebP quality settings (1-100)
- **Keep Originals**: Option to keep original files alongside WebP versions
- **All Sizes Converted**: Converts both original and intermediate image sizes
- **Transparent Support**: Proper handling of PNG transparency

### 🔄 Image Regeneration
- **One-Click Regeneration**: Regenerate all image sizes with a single button
- **Missing Size Detection**: Automatically detects and displays missing image sizes
- **Bulk Regeneration**: Process multiple images at once from media library
- **Progress Tracking**: Visual progress bar for bulk operations

### 🧹 Raw Cleanup
- **Keep Original Only**: Delete all generated sizes and keep only the original
- **Fresh Start**: Clean slate for regenerating images with new settings
- **File Management**: Removes both regular and WebP versions of intermediate sizes

### 📊 Enhanced Media Library
- **Size Information Column**: See image size status at a glance
- **WebP Indicators**: Visual confirmation of WebP conversion
- **Missing Size Warnings**: Easily identify images with missing sizes
- **Detailed Status**: Complete overview of registered image sizes

### ⚙️ Flexible Settings
- **Enable/Disable Features**: Toggle WebP conversion, crop, and regenerate features
- **Quality Control**: Adjust WebP conversion quality
- **Original File Retention**: Choose whether to keep original files
- **Post Type Support**: Works with posts, pages, and custom post types including WooCommerce

## Installation

1. Download the plugin folder
2. Upload to `/wp-content/plugins/` directory
3. Activate through the 'Plugins' menu in WordPress
4. Configure settings at Settings > Image Crop & WebP

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD Library or ImageMagick extension
- WebP support in GD/ImageMagick

## Usage

### Cropping Images

1. Go to Media Library
2. Select an image to edit
3. Click "Crop Image" button
4. Use the crop tool to select desired area
5. Choose aspect ratio if needed
6. Click "Apply Crop" to save

### Regenerating Images

1. Open any image in Media Library
2. Click "Regenerate All Sizes" button
3. Wait for process to complete
4. All image sizes will be regenerated with current settings

### Bulk Operations

1. Go to Settings > Image Crop & WebP
2. Use "Regenerate All Images" for bulk regeneration
3. Use "Convert All Images to WebP" for bulk conversion
4. Monitor progress in the progress bar

### Raw Cleanup

1. Open any image in Media Library
2. Click "Raw Cleanup" button
3. Confirm the action
4. All intermediate sizes will be deleted, keeping only the original

## Settings

### WebP Conversion Settings

- **Enable Auto WebP Conversion**: Toggle automatic WebP conversion on upload
- **Keep Original Files**: Retain original files after WebP conversion
- **WebP Quality**: Set conversion quality (1-100, default: 80)

### Crop & Regenerate Settings

- **Enable Crop Feature**: Enable/disable crop functionality
- **Enable Regenerate Feature**: Enable/disable image regeneration

## Compatibility

- ✅ WordPress 5.0+
- ✅ WooCommerce products
- ✅ Custom post types
- ✅ Gutenberg block editor
- ✅ Classic editor
- ✅ Responsive layouts
- ✅ Multisite compatible

## Technical Details

### Hooks & Filters

The plugin uses WordPress standard hooks:
- `wp_handle_upload` - WebP conversion on upload
- `wp_generate_attachment_metadata` - Intermediate size conversion
- `attachment_fields_to_edit` - Add buttons to media modal
- `edit_form_after_title` - Crop interface on edit media page

### File Structure

```
image-crop-webp-converter/
├── admin/
│   ├── class-admin-interface.php
│   └── crop-interface.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-webp-converter.php
│   ├── class-image-cropper.php
│   └── class-image-regenerator.php
├── image-crop-webp-converter.php
├── uninstall.php
└── README.md
```

## Frequently Asked Questions

**Q: Does this work with WooCommerce?**
A: Yes! The plugin fully supports WooCommerce product images.

**Q: Will WebP images display on all browsers?**
A: WebP is supported by all modern browsers. For older browsers, you may need additional server-side configuration.

**Q: Can I disable WebP conversion?**
A: Yes, go to Settings > Image Crop & WebP and uncheck "Enable Auto WebP Conversion".

**Q: What happens to my original images?**
A: By default, original images are kept. You can change this in settings.

**Q: Can I crop specific image sizes?**
A: Yes, use the "Re-crop" button next to any registered image size.

## Support

For support, please visit the [GitHub repository](https://github.com/mikolajhere/claude-code-online).

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.0.0
- Initial release
- WebP automatic conversion
- Interactive image cropping
- Image regeneration
- Raw cleanup functionality
- Bulk operations
- Enhanced media library interface
