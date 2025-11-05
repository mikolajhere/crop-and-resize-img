/**
 * Admin JavaScript for Image Crop & WebP Converter
 */

(function ($) {
  "use strict";

  var ICWC = {
    cropper: null,
    currentAttachmentId: null,
    originalImageWidth: null,
    originalImageHeight: null,

    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      var self = this;

      // Crop button click
      $(document).on(
        "click",
        ".icwc-open-crop, .icwc-crop-button, .icwc-recrop-size",
        function (e) {
          e.preventDefault();
          console.log("ICWC: Crop button clicked!", this);
          var attachmentId = $(this).data("attachment-id");
          var size = $(this).data("size") || "";
          console.log("ICWC: Attachment ID:", attachmentId, "Size:", size);

          if (!attachmentId) {
            console.error("ICWC: No attachment ID found on button!");
            alert(
              "Error: No attachment ID found. Please try refreshing the page."
            );
            return;
          }

          self.openCropModal(attachmentId, size);
        }
      );

      // Modal close
      $(document).on(
        "click",
        ".icwc-modal-close, .icwc-modal-cancel",
        function (e) {
          e.preventDefault();
          self.closeCropModal();
        }
      );

      // Close modal on outside click
      $(document).on("click", ".icwc-modal", function (e) {
        if ($(e.target).hasClass("icwc-modal")) {
          self.closeCropModal();
        }
      });

      // Aspect ratio change
      $(document).on("change", "#icwc-aspect-ratio", function () {
        var ratio = $(this).val();
        if (self.cropper) {
          if (ratio === "free") {
            self.cropper.setAspectRatio(NaN);
          } else {
            self.cropper.setAspectRatio(parseFloat(ratio));
          }
        }
      });

      // Enable/disable resize options
      $(document).on("change", "#icwc-enable-resize", function () {
        if ($(this).is(":checked")) {
          $("#icwc-resize-options").slideDown(200);
        } else {
          $("#icwc-resize-options").slideUp(200);
        }
      });

      // Auto-calculate height based on width (maintain aspect ratio)
      $(document).on("input", "#icwc-resize-width", function () {
        var width = parseInt($(this).val());
        if (width && self.originalImageWidth && self.originalImageHeight) {
          var aspectRatio = self.originalImageWidth / self.originalImageHeight;
          var height = Math.round(width / aspectRatio);
          $("#icwc-resize-height").val(height);
        }
      });

      // Auto-calculate width based on height (maintain aspect ratio)
      $(document).on("input", "#icwc-resize-height", function () {
        var height = parseInt($(this).val());
        if (height && self.originalImageWidth && self.originalImageHeight) {
          var aspectRatio = self.originalImageWidth / self.originalImageHeight;
          var width = Math.round(height * aspectRatio);
          $("#icwc-resize-width").val(width);
        }
      });

      // Apply crop
      $(document).on("click", ".icwc-apply-crop", function (e) {
        e.preventDefault();
        self.applyCrop();
      });

      // Regenerate button click
      $(document).on("click", ".icwc-regenerate-button", function (e) {
        e.preventDefault();
        var attachmentId = $(this).data("attachment-id");
        self.regenerateImage(attachmentId);
      });

      // Cleanup button click
      $(document).on("click", ".icwc-cleanup-button", function (e) {
        e.preventDefault();
        if (confirm(icwcAdmin.i18n.confirmCleanup)) {
          var attachmentId = $(this).data("attachment-id");
          self.cleanupImage(attachmentId);
        }
      });

      // Generate size button click
      $(document).on("click", ".icwc-generate-size", function (e) {
        e.preventDefault();
        var attachmentId = $(this).data("attachment-id");
        self.regenerateImage(attachmentId);
      });

      // Bulk regenerate
      $(document).on("click", "#icwc-bulk-regenerate", function (e) {
        e.preventDefault();
        self.bulkRegenerateImages();
      });

      // Bulk WebP conversion
      $(document).on("click", "#icwc-bulk-webp", function (e) {
        e.preventDefault();
        self.bulkConvertToWebP();
      });

      // Convert to WebP button
      $(document).on("click", ".icwc-convert-webp", function (e) {
        e.preventDefault();
        var attachmentId = $(this).data("attachment-id");
        self.convertToWebP(attachmentId);
      });

      // Copy WebP URL button
      $(document).on("click", ".icwc-copy-webp-url", function (e) {
        e.preventDefault();
        var targetId = $(this).data("target");
        self.copyToClipboard(targetId, $(this));
      });

      // Delete crop profile button
      $(document).on("click", ".icwc-delete-crop-profile", function (e) {
        e.preventDefault();
        var attachmentId = $(this).data("attachment-id");
        var profileName = $(this).data("profile");
        self.deleteCropProfile(attachmentId, profileName, $(this));
      });
    },

    /**
     * Open crop modal
     */
    openCropModal: function (attachmentId, size) {
      var self = this;
      self.currentAttachmentId = attachmentId;

      console.log("ICWC: Opening crop modal for attachment:", attachmentId);
      console.log(
        "ICWC: Modal element exists:",
        $("#icwc-crop-modal").length > 0
      );

      // Check if modal exists, if not show error
      if ($("#icwc-crop-modal").length === 0) {
        console.error("ICWC: Crop modal HTML not found in DOM!");
        alert("Crop modal not found. Please refresh the page and try again.");
        return;
      }

      // Get image URL via AJAX to ensure we have the correct full-size image
      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_get_image_url",
          nonce: icwcAdmin.cropNonce,
          attachment_id: attachmentId,
        },
        success: function (response) {
          console.log("ICWC: AJAX response:", response);
          if (response.success && response.data.url) {
            self.loadCropModal(response.data.url);
          } else {
            console.log("ICWC: AJAX failed, trying DOM fallback");
            // Fallback to trying to get URL from DOM
            var imageUrl = self.getImageUrlFromDOM(attachmentId);
            if (imageUrl) {
              self.loadCropModal(imageUrl);
            } else {
              console.error("ICWC: Could not get image URL from DOM");
              alert(icwcAdmin.i18n.error);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("ICWC: AJAX error:", status, error);
          // Fallback to trying to get URL from DOM
          var imageUrl = self.getImageUrlFromDOM(attachmentId);
          if (imageUrl) {
            console.log("ICWC: Using DOM fallback URL:", imageUrl);
            self.loadCropModal(imageUrl);
          } else {
            console.error("ICWC: Could not get image URL from DOM");
            alert(icwcAdmin.i18n.error);
          }
        },
      });
    },

    /**
     * Get image URL from DOM
     */
    getImageUrlFromDOM: function (attachmentId) {
      var imageUrl = null;

      // Try from media library grid view
      imageUrl = $("#post-" + attachmentId)
        .find("img")
        .attr("src");

      if (!imageUrl) {
        // Try from edit media page
        imageUrl = $(".attachment-details img").attr("src");
      }

      if (!imageUrl) {
        // Try from media modal
        imageUrl = $(".attachment-info .thumbnail img").attr("src");
      }

      if (!imageUrl) {
        // Try from attachment preview
        imageUrl = $('.attachment[data-id="' + attachmentId + '"] img').attr(
          "src"
        );
      }

      // If we found a thumbnail, try to get full size by removing size suffix
      if (imageUrl && imageUrl.match(/-\d+x\d+\.(jpg|jpeg|png|gif|webp)/i)) {
        imageUrl = imageUrl.replace(
          /-\d+x\d+(\.(jpg|jpeg|png|gif|webp))$/i,
          "$1"
        );
      }

      return imageUrl;
    },

    /**
     * Load crop modal with image
     */
    loadCropModal: function (imageUrl) {
      var self = this;

      console.log("ICWC: Loading crop modal with URL:", imageUrl);

      // Show modal
      var modal = $("#icwc-crop-modal");
      modal.addClass("show");
      console.log("ICWC: Modal show class added");
      console.log("ICWC: Modal element:", modal.length);
      console.log("ICWC: Modal has show class:", modal.hasClass("show"));
      console.log("ICWC: Modal display style:", modal.css("display"));
      console.log("ICWC: Modal z-index:", modal.css("z-index"));
      console.log("ICWC: Modal visibility:", modal.css("visibility"));
      console.log("ICWC: Modal opacity:", modal.css("opacity"));

      // Debug profile name field
      var profileNameField = $("#icwc-crop-profile-name");
      console.log("ICWC: Profile name field exists:", profileNameField.length);
      if (profileNameField.length > 0) {
        console.log(
          "ICWC: Profile name field display:",
          profileNameField.css("display")
        );
        console.log(
          "ICWC: Profile name field visibility:",
          profileNameField.css("visibility")
        );
        console.log(
          "ICWC: Profile name field opacity:",
          profileNameField.css("opacity")
        );
        console.log(
          "ICWC: Profile name field height:",
          profileNameField.css("height")
        );
        console.log(
          "ICWC: Profile name field parent:",
          profileNameField.parent().get(0)
        );

        // Clear any previous value and ensure visibility
        profileNameField.val("").show();
      } else {
        console.error("ICWC: Profile name field NOT FOUND in modal!");
      }

      var cropImage = $("#icwc-crop-image");
      console.log("ICWC: Crop image element:", cropImage.length);

      cropImage.attr("src", imageUrl);
      console.log("ICWC: Image src set to:", imageUrl);

      // Check if Cropper is available
      if (typeof Cropper === "undefined") {
        console.error("ICWC: Cropper.js library not loaded!");
        alert("Error: Cropper.js library not loaded. Please refresh the page.");
        return;
      }

      // Initialize cropper after image loads
      cropImage.off("load").on("load", function () {
        console.log("ICWC: Image loaded successfully");

        // Store original image dimensions
        self.originalImageWidth = this.naturalWidth;
        self.originalImageHeight = this.naturalHeight;
        console.log(
          "ICWC: Original image dimensions:",
          self.originalImageWidth,
          "x",
          self.originalImageHeight
        );

        // Display original dimensions
        $("#icwc-original-size").text(
          self.originalImageWidth + " × " + self.originalImageHeight + "px"
        );
        $("#icwc-original-dimensions").show();

        if (self.cropper) {
          console.log("ICWC: Destroying existing cropper");
          self.cropper.destroy();
        }

        console.log("ICWC: Initializing new Cropper instance");
        try {
          self.cropper = new Cropper(this, {
            viewMode: 1,
            dragMode: "move",
            aspectRatio: NaN,
            autoCropArea: 1,
            restore: false,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            ready: function () {
              console.log("ICWC: Cropper is ready!");
            },
            crop: function (event) {
              // Update preview
              self.updatePreview(event.detail);
            },
          });
          console.log("ICWC: Cropper initialized successfully");
        } catch (error) {
          console.error("ICWC: Error initializing Cropper:", error);
          alert("Error initializing cropper: " + error.message);
        }
      });

      // Handle error loading image
      cropImage.off("error").on("error", function () {
        console.error("ICWC: Error loading image:", imageUrl);
        alert(icwcAdmin.i18n.error + ": Could not load image");
        self.closeCropModal();
      });

      // Trigger load if image is already cached
      var imgElement = cropImage[0];
      console.log("ICWC: Image element:", imgElement);
      console.log(
        "ICWC: Image complete:",
        imgElement ? imgElement.complete : "no element"
      );
      console.log(
        "ICWC: Image naturalHeight:",
        imgElement ? imgElement.naturalHeight : "no element"
      );

      if (imgElement && imgElement.complete && imgElement.naturalHeight !== 0) {
        console.log("ICWC: Image already cached, triggering load");
        cropImage.trigger("load");
      } else {
        console.log("ICWC: Waiting for image to load...");
      }
    },

    /**
     * Close crop modal
     */
    closeCropModal: function () {
      $("#icwc-crop-modal").removeClass("show");
      $("#icwc-crop-profile-name").val("");
      $("#icwc-enable-resize").prop("checked", false);
      $("#icwc-resize-options").hide();
      $("#icwc-resize-width").val("");
      $("#icwc-resize-height").val("");
      $("#icwc-original-dimensions").hide();
      this.originalImageWidth = null;
      this.originalImageHeight = null;
      if (this.cropper) {
        this.cropper.destroy();
        this.cropper = null;
      }
    },

    /**
     * Update preview
     */
    updatePreview: function (cropData) {
      // Preview can be enhanced here
      var previewBox = $("#icwc-preview-box");
      var previewHtml = '<div style="padding: 10px; font-size: 12px;">';
      previewHtml += "X: " + Math.round(cropData.x) + "px<br>";
      previewHtml += "Y: " + Math.round(cropData.y) + "px<br>";
      previewHtml += "Width: " + Math.round(cropData.width) + "px<br>";
      previewHtml += "Height: " + Math.round(cropData.height) + "px";
      previewHtml += "</div>";
      previewBox.html(previewHtml);
    },

    /**
     * Apply crop
     */
    applyCrop: function () {
      var self = this;

      if (!self.cropper) {
        alert(icwcAdmin.i18n.error);
        return;
      }

      // Get profile name
      var profileName = $("#icwc-crop-profile-name").val().trim();

      if (!profileName) {
        alert("Please enter a profile name for this crop.");
        $("#icwc-crop-profile-name").focus();
        return;
      }

      // Sanitize profile name (only alphanumeric, dash, underscore)
      profileName = profileName.toLowerCase().replace(/[^a-z0-9\-_]/g, "-");

      var cropData = self.cropper.getData();
      var button = $(".icwc-apply-crop");
      var originalText = button.text();

      // Get resize options if enabled
      var resizeData = null;
      if ($("#icwc-enable-resize").is(":checked")) {
        var resizeWidth = $("#icwc-resize-width").val();
        var resizeHeight = $("#icwc-resize-height").val();

        if (resizeWidth || resizeHeight) {
          resizeData = {
            width: resizeWidth ? parseInt(resizeWidth) : null,
            height: resizeHeight ? parseInt(resizeHeight) : null,
          };
        }
      }

      button.text(icwcAdmin.i18n.processing).prop("disabled", true);

      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_crop_image",
          nonce: icwcAdmin.cropNonce,
          attachment_id: self.currentAttachmentId,
          profile_name: profileName,
          resize_data: resizeData,
          crop_data: {
            x: cropData.x,
            y: cropData.y,
            width: cropData.width,
            height: cropData.height,
          },
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(response.data.message, "success");
            self.closeCropModal();
            // Reload page after 1 second
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            self.showMessage(response.data.message, "error");
          }
        },
        error: function () {
          self.showMessage(icwcAdmin.i18n.error, "error");
        },
        complete: function () {
          button.text(originalText).prop("disabled", false);
        },
      });
    },

    /**
     * Regenerate image
     */
    regenerateImage: function (attachmentId) {
      var self = this;
      var button = $(
        '.icwc-regenerate-button[data-attachment-id="' + attachmentId + '"]'
      );
      var originalText = button.text();

      button
        .html(icwcAdmin.i18n.processing + ' <span class="icwc-loading"></span>')
        .prop("disabled", true);

      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_regenerate_image",
          nonce: icwcAdmin.regenerateNonce,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(response.data.message, "success");
            // Reload page after 1 second
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            self.showMessage(response.data.message, "error");
          }
        },
        error: function () {
          self.showMessage(icwcAdmin.i18n.error, "error");
        },
        complete: function () {
          button.text(originalText).prop("disabled", false);
        },
      });
    },

    /**
     * Cleanup image
     */
    cleanupImage: function (attachmentId) {
      var self = this;
      var button = $(
        '.icwc-cleanup-button[data-attachment-id="' + attachmentId + '"]'
      );
      var originalText = button.text();

      button
        .html(icwcAdmin.i18n.processing + ' <span class="icwc-loading"></span>')
        .prop("disabled", true);

      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_cleanup_image",
          nonce: icwcAdmin.cleanupNonce,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(response.data.message, "success");
            // Reload page after 1 second
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            self.showMessage(response.data.message, "error");
          }
        },
        error: function () {
          self.showMessage(icwcAdmin.i18n.error, "error");
        },
        complete: function () {
          button.text(originalText).prop("disabled", false);
        },
      });
    },

    /**
     * Bulk regenerate images
     */
    bulkRegenerateImages: function () {
      var self = this;

      if (
        !confirm(
          "This will regenerate all images in your media library. This may take some time. Continue?"
        )
      ) {
        return;
      }

      var button = $("#icwc-bulk-regenerate");
      var originalText = button.text();

      button.text(icwcAdmin.i18n.processing).prop("disabled", true);
      $("#icwc-bulk-progress").show();

      // Get all image attachments
      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_get_all_images",
          nonce: icwcAdmin.bulkNonce,
        },
        success: function (response) {
          if (response.success && response.data.attachments) {
            self.processImagesInBatches(
              response.data.attachments,
              "regenerate",
              function () {
                $("#icwc-bulk-progress").hide();
                button.text(originalText).prop("disabled", false);
                self.showMessage(
                  "All images processed successfully!",
                  "success"
                );
              }
            );
          }
        },
      });
    },

    /**
     * Convert single image to WebP
     */
    convertToWebP: function (attachmentId) {
      var self = this;
      var button = $(
        '.icwc-convert-webp[data-attachment-id="' + attachmentId + '"]'
      );
      var originalText = button.text();

      button
        .html(icwcAdmin.i18n.processing + ' <span class="icwc-loading"></span>')
        .prop("disabled", true);

      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_convert_to_webp",
          nonce: icwcAdmin.regenerateNonce,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(response.data.message, "success");
            // Reload page after 1 second
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            self.showMessage(response.data.message, "error");
            button.text(originalText).prop("disabled", false);
          }
        },
        error: function () {
          self.showMessage(icwcAdmin.i18n.error, "error");
          button.text(originalText).prop("disabled", false);
        },
      });
    },

    /**
     * Bulk convert to WebP
     */
    bulkConvertToWebP: function () {
      var self = this;

      if (
        !confirm(
          "This will convert all images to WebP format. This may take some time. Continue?"
        )
      ) {
        return;
      }

      var button = $("#icwc-bulk-webp");
      var originalText = button.text();

      button.text(icwcAdmin.i18n.processing).prop("disabled", true);
      $("#icwc-bulk-progress").show();

      // Get all image attachments
      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_get_all_images",
          nonce: icwcAdmin.bulkNonce,
        },
        success: function (response) {
          if (response.success && response.data.attachments) {
            self.processWebPConversions(response.data.attachments, function () {
              $("#icwc-bulk-progress").hide();
              button.text(originalText).prop("disabled", false);
              self.showMessage(
                "All images converted to WebP successfully!",
                "success"
              );
            });
          } else {
            $("#icwc-bulk-progress").hide();
            button.text(originalText).prop("disabled", false);
            self.showMessage("No images found to convert", "error");
          }
        },
        error: function () {
          $("#icwc-bulk-progress").hide();
          button.text(originalText).prop("disabled", false);
          self.showMessage("Error fetching images", "error");
        },
      });
    },

    /**
     * Process WebP conversions in batches
     */
    processWebPConversions: function (attachments, callback) {
      var self = this;
      var total = attachments.length;
      var processed = 0;
      var batchSize = 3; // Slower for WebP conversion

      function processBatch(start) {
        var batch = attachments.slice(start, start + batchSize);
        var promises = [];

        batch.forEach(function (attachmentId) {
          var promise = $.ajax({
            url: icwcAdmin.ajaxUrl,
            type: "POST",
            data: {
              action: "icwc_convert_to_webp",
              nonce: icwcAdmin.regenerateNonce,
              attachment_id: attachmentId,
            },
          });
          promises.push(promise);
        });

        $.when.apply($, promises).always(function () {
          processed += batch.length;
          var percentage = Math.round((processed / total) * 100);

          $("#icwc-progress-bar").val(percentage);
          $("#icwc-progress-text").text(
            "Converting: " +
              processed +
              " / " +
              total +
              " (" +
              percentage +
              "%)"
          );

          if (processed < total) {
            processBatch(processed);
          } else {
            if (callback) {
              callback();
            }
          }
        });
      }

      processBatch(0);
    },

    /**
     * Process images in batches
     */
    processImagesInBatches: function (attachments, action, callback) {
      var self = this;
      var total = attachments.length;
      var processed = 0;
      var batchSize = 5;

      function processBatch(start) {
        var batch = attachments.slice(start, start + batchSize);
        var promises = [];

        batch.forEach(function (attachmentId) {
          var promise = $.ajax({
            url: icwcAdmin.ajaxUrl,
            type: "POST",
            data: {
              action: "icwc_" + action + "_image",
              nonce: icwcAdmin.regenerateNonce,
              attachment_id: attachmentId,
            },
          });
          promises.push(promise);
        });

        $.when.apply($, promises).always(function () {
          processed += batch.length;
          var percentage = Math.round((processed / total) * 100);

          $("#icwc-progress-bar").val(percentage);
          $("#icwc-progress-text").text(
            "Processing: " +
              processed +
              " / " +
              total +
              " (" +
              percentage +
              "%)"
          );

          if (processed < total) {
            processBatch(processed);
          } else {
            if (callback) {
              callback();
            }
          }
        });
      }

      processBatch(0);
    },

    /**
     * Show message
     */
    showMessage: function (message, type) {
      var messageClass = "icwc-message " + type;
      var messageHtml =
        '<div class="' + messageClass + '">' + message + "</div>";

      // Remove existing messages
      $(".icwc-message").remove();

      // Add new message
      if ($(".icwc-crop-interface").length) {
        $(".icwc-crop-interface").prepend(messageHtml);
      } else if ($(".wrap").length) {
        $(".wrap h1").after(messageHtml);
      }

      // Auto-hide after 5 seconds
      setTimeout(function () {
        $(".icwc-message").fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Copy to clipboard
     */
    copyToClipboard: function (targetId, button) {
      var input = $("#" + targetId);

      if (!input.length) {
        return;
      }

      // Select the text
      input[0].select();
      input[0].setSelectionRange(0, 99999); // For mobile devices

      // Copy to clipboard
      try {
        var successful = document.execCommand("copy");

        if (successful) {
          // Change button text temporarily
          var originalHtml = button.html();
          button.html(
            '<span class="dashicons dashicons-yes" style="vertical-align: middle; color: green;"></span> Skopiowano!'
          );

          setTimeout(function () {
            button.html(originalHtml);
          }, 2000);
        } else {
          alert("Nie udało się skopiować. Użyj Ctrl+C (Cmd+C na Mac)");
        }
      } catch (err) {
        // Fallback for modern browsers
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard
            .writeText(input.val())
            .then(function () {
              var originalHtml = button.html();
              button.html(
                '<span class="dashicons dashicons-yes" style="vertical-align: middle; color: green;"></span> Skopiowano!'
              );

              setTimeout(function () {
                button.html(originalHtml);
              }, 2000);
            })
            .catch(function () {
              alert("Nie udało się skopiować. Użyj Ctrl+C (Cmd+C na Mac)");
            });
        } else {
          alert("Nie udało się skopiować. Użyj Ctrl+C (Cmd+C na Mac)");
        }
      }
    },

    /**
     * Delete crop profile
     */
    deleteCropProfile: function (attachmentId, profileName, button) {
      var self = this;

      if (
        !confirm(
          'Are you sure you want to delete the crop profile "' +
            profileName +
            '"? This action cannot be undone.'
        )
      ) {
        return;
      }

      var card = button.closest(".icwc-crop-profile-card");
      var originalHtml = button.html();

      button.html('<span class="icwc-loading"></span>').prop("disabled", true);

      $.ajax({
        url: icwcAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "icwc_delete_crop_profile",
          nonce: icwcAdmin.cropNonce,
          attachment_id: attachmentId,
          profile_name: profileName,
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(response.data.message, "success");
            // Fade out and remove the card
            card.fadeOut(400, function () {
              $(this).remove();
              // Check if there are no more profiles and remove the entire section
              if ($(".icwc-crop-profile-card").length === 0) {
                card
                  .closest(".icwc-crop-profile-card")
                  .parent()
                  .parent()
                  .fadeOut(400, function () {
                    $(this).remove();
                  });
              }
            });
          } else {
            self.showMessage(response.data.message, "error");
            button.html(originalHtml).prop("disabled", false);
          }
        },
        error: function () {
          self.showMessage(icwcAdmin.i18n.error, "error");
          button.html(originalHtml).prop("disabled", false);
        },
      });
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    ICWC.init();
  });
})(jQuery);
