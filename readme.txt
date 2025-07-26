=== Storage for EDD via S3-Compatible ===
author: mohammadr3z
Contributors:
Tags: easy-digital-downloads, s3, storage, digital-downloads, file-storage
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.7.2
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable secure cloud storage and delivery of your digital products through S3-compatible services for Easy Digital Downloads.

== Description ==

Storage for EDD via S3-Compatible is a powerful extension for Easy Digital Downloads that allows you to store and deliver your digital products using S3-compatible storage services. This plugin provides seamless integration with various S3-compatible storage providers including MinIO, DigitalOcean Spaces, Linode Object Storage, and many others.

= Key Features =

* **S3 Compatible Storage Support**: Works with MinIO, DigitalOcean Spaces, Linode Object Storage, and other S3-compatible services
* **Secure File Delivery**: Generates time-limited, secure download URLs for your digital products
* **Easy File Management**: Upload files directly to S3 storage through WordPress admin
* **Media Library Integration**: Browse and select files from your S3 storage within WordPress
* **Configurable Expiry**: Set custom expiration times for download links
* **Security First**: Built with WordPress security best practices
* **Developer Friendly**: Clean, well-documented code with hooks and filters

= Requirements =

* Easy Digital Downloads plugin (active)
* PHP 7.4 or higher
* Composer dependencies (included in release)
* S3-compatible storage account

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/storage-for-edd-via-s3-compatible` directory, or install the plugin through the WordPress plugins screen directly.
2. Make sure you have Easy Digital Downloads plugin installed and activated.
3. Run `composer install` in the plugin directory if installing from source (not needed for release versions).
4. Activate the plugin through the 'Plugins' screen in WordPress.
5. Navigate to Downloads > Settings > Extensions > S3 Storage to configure the plugin.

== Configuration ==

1. Go to Downloads > Settings > Extensions > S3 Storage
2. Enter your S3 credentials:
   * Access Key
   * Secret Key
   * Endpoint URL (e.g., https://s3.example.com)
   * Bucket Name
3. Set the download link expiry time (in minutes)
4. Save the settings

== Usage ==

= Uploading Files =

1. When creating or editing a download in Easy Digital Downloads
2. Click on "Upload File" or "Choose File"
3. Select the "Upload to S3" tab
4. Choose your file and upload it directly to S3 storage
5. The file URL will be automatically set with the S3 prefix

= File Management =

* Use the "S3 Library" tab to browse existing files in your S3 storage
* Files are organized by the path structure in your S3 bucket
* Click "Select" to use an existing file for your download

== Frequently Asked Questions ==

= Which S3-compatible services are supported? =

This plugin works with any S3-compatible storage service including:
* Amazon S3
* DigitalOcean Spaces
* Linode Object Storage
* Wasabi
* Backblaze B2 (with S3-compatible API)
* Cloudflare R2
* MinIO
* Storj
* ArvanCloud
* Hetzner Object Storage
* And many others

= How secure are the download links? =

The plugin generates presigned URLs with configurable expiration times. These URLs are temporary and cannot be shared or reused after expiration, ensuring your digital products remain secure.

= Can I migrate existing files to S3? =

Yes, you can upload your existing files to S3 storage and update the file URLs in your downloads. The plugin provides an easy interface for uploading and managing files.

= What happens if S3 is unavailable? =

If your S3 service is temporarily unavailable, download attempts will fail gracefully. The plugin includes error handling and logging for troubleshooting.

= Can I use custom domains with my S3 storage? =

Yes, you can configure custom endpoint URLs in the plugin settings to use your own domain or CDN.

== Screenshots ==

1. Admin panel user interface
2. File selection from S3 storage section
3. File upload to S3 storage interface

== Changelog ==

= 1.0.7 =
* Automatically prepended `https://` to Endpoint URL to prevent XML parsing errors.
* Improved Endpoint URL validation and user guidance.
* XML parsing errors in S3 client and media library functions.

= 1.0.6 =
* Centralized version management using S3CS_EDD_VERSION constant
* Updated Persian translation files

= 1.0.5 =
* Removed: Dark mode support to simplify styling and improve consistency across all themes.

= 1.0.4 =
* Fixed: Responsive S3 file selection now displays file name, size, date, and select button on mobile.

= 1.0.3 =
* Fixed `WP_Scripts::localize` error by using `wp_add_inline_script()` for non-array values.
* Separated all inline JavaScript into dedicated `.js` files for better maintainability and performance.
* Separated inline CSS into dedicated `.css` files.

= 1.0.2 =
* Enhanced S3 upload section styling for a modern look and improved user experience.
* Improved responsive design for better display on various screen sizes.
* Refined the display of the current directory/bucket name in the S3 upload section.

= 1.0.1 =
* Added a non-dismissible admin notice to alert users when S3 Access Key or Secret Key are not configured, with a direct link to settings.
* Added Persian language support.
* Implemented direct file download functionality, preventing files like JSON or text from opening in the browser and forcing download.

= 1.0.0 =
* Initial release
* S3-compatible storage integration
* Secure presigned URL generation
* Media library integration
* File upload functionality
* Admin settings interface
* Security enhancements and validation
* Internationalization support

== Support ==

For support and documentation, please visit: https://Mohammadr3z.com/

== Privacy Policy ==

This plugin does not collect or store any personal data. All file storage and delivery is handled through your configured S3-compatible storage service.
