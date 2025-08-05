# Imgproxy Image Optimizer for WordPress

A powerful WordPress plugin that optimizes images using [imgproxy](https://imgproxy.net/) with Next.js-like features including responsive srcset, priority loading, and DNS prefetch.

## Features

- 🖼️ **Automatic Image Optimization**: Converts images to modern formats (AVIF, WebP, JPEG, PNG)
- 📱 **Responsive Images**: Generates srcset for multiple screen sizes
- ⚡ **Priority Loading**: Detects and preloads critical images
- 🔒 **Secure URLs**: HMAC-signed URLs for security
- 🌐 **DNS Prefetch**: Automatic DNS prefetching for imgproxy domain
- 🎛️ **Flexible Configuration**: Extensive admin settings
- 🔗 **Multiple URL Formats**: Base64 encoded or plain URL formats
- 🌍 **Source Control**: Configurable allowed image sources/domains

## Installation

1. Download or clone this repository
2. Upload the `imgproxy-optimizer` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings under **Settings → Image Optimizer**

## Configuration

### Required Settings

Navigate to **Settings → Image Optimizer** in your WordPress admin:

1. **Imgproxy URL**: Your imgproxy server URL (e.g., `https://imgproxy.example.com`)
2. **Secret Key**: Your imgproxy secret key (hex format)
3. **Secret Salt**: Your imgproxy secret salt (hex format)

### Optional Settings

- **Image Quality**: Compression quality (1-100, default: 65)
- **Image Format**: Output format (AVIF, WebP, JPEG, PNG)
- **Responsive Widths**: Comma-separated widths for srcset generation
- **Allowed Image Sources**: Domains eligible for optimization
- **Use Base64 URL Encoding**: Toggle between base64 and plain URL formats
- **Enable Image Optimization**: Master on/off switch

## How It Works

### Image Processing Flow

1. **HTML Parsing**: Scans all `<img>` tags on frontend pages
2. **Source Validation**: Checks if images are from allowed sources
3. **URL Generation**: Creates optimized imgproxy URLs with signatures
4. **Srcset Creation**: Generates responsive image sets for different screen sizes
5. **Priority Detection**: Identifies critical images for preloading

### URL Formats

#### Base64 Format (default: disabled)
```
https://imgproxy.example.com/signature/rt:fit/w:800/h:0/q:65/f:avif/encoded_url/filename.avif
```

#### Plain Format (default: enabled)
```
https://imgproxy.example.com/signature/rt:fit/w:800/h:0/q:65/f:avif/plain/https://example.com/image.jpg
```

### Priority Image Detection

Images are considered priority and preloaded if they have:
- `fetchpriority="high"` attribute
- `loading="eager"` attribute
- CSS classes containing "priority" or "hero"

### Allowed Sources Configuration

Control which domains can be optimized:

```
# Allow specific domains
example.com
cdn.example.com

# Allow wildcard subdomains
*.cloudfront.net
*.amazonaws.com

# Leave empty to allow current site only
```

## Imgproxy Server Setup

This plugin requires an imgproxy server. Here's a basic Docker setup:

```bash
docker run -d \
  --name imgproxy \
  -p 8080:8080 \
  -e IMGPROXY_KEY="your_secret_key_here" \
  -e IMGPROXY_SALT="your_secret_salt_here" \
  -e IMGPROXY_USE_ETAG=true \
  -e IMGPROXY_ENABLE_WEBP_DETECTION=true \
  -e IMGPROXY_ENABLE_AVIF_DETECTION=true \
  darthsim/imgproxy
```

### Generate Keys

Generate secure keys for your imgproxy server:

```bash
# Generate key
openssl rand -hex 32

# Generate salt
openssl rand -hex 32
```

## Examples

### Basic Image Optimization

**Original HTML:**
```html
<img src="/uploads/image.jpg" width="800" height="600" alt="Example">
```

**Optimized HTML:**
```html
<img src="https://imgproxy.example.com/signature/rt:fit/w:800/h:0/q:65/f:avif/plain/https://example.com/uploads/image.jpg" 
     width="800" 
     height="600" 
     alt="Example"
     srcset="https://imgproxy.example.com/.../w:320/... 320w,
             https://imgproxy.example.com/.../w:640/... 640w,
             https://imgproxy.example.com/.../w:800/... 800w"
     sizes="(max-width: 800px) 100vw, 800px"
     loading="lazy">
```

### Priority Image with Preload

**HTML with priority:**
```html
<img src="/hero-image.jpg" fetchpriority="high" alt="Hero">
```

**Generated preload link:**
```html
<link rel="preload" as="image" href="https://imgproxy.example.com/.../hero-image.jpg">
```

## Technical Details

### File Structure

```
imgproxy-optimizer/
├── imgproxy-optimizer.php           # Main plugin file
├── includes/
│   ├── class-settings-manager.php   # Admin settings page
│   ├── class-imgproxy-url-generator.php  # URL generation logic
│   └── class-image-processor.php    # HTML processing and image handling
└── README.md                        # This file
```

### WordPress Hooks

- **Output Buffering**: Captures and modifies HTML content
- **DNS Prefetch**: Adds prefetch links in `wp_head`
- **Settings API**: WordPress settings framework integration
- **Activation/Deactivation**: Proper plugin lifecycle management

### Performance Optimizations

- **Efficient Parsing**: Uses DOMDocument for reliable HTML parsing
- **Conditional Processing**: Only processes when plugin is enabled and configured
- **Smart Caching**: Leverages WordPress caching mechanisms
- **Minimal Overhead**: Lightweight processing with early exits

## Troubleshooting

### Common Issues

**Invalid Signature Errors:**
- Verify secret key and salt are correct
- Ensure URLs with spaces are properly handled
- Check imgproxy server configuration

**Images Not Processing:**
- Confirm allowed sources configuration
- Verify imgproxy server is accessible
- Check if images are from external domains

**Performance Issues:**
- Review responsive widths configuration
- Consider disabling for admin pages
- Check imgproxy server capacity

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Requirements

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **Imgproxy Server**: 2.0+
- **Extensions**: DOM, OpenSSL

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

GPL v2 or later - see WordPress plugin guidelines

## Support

For issues and questions:
- Check the troubleshooting section
- Review imgproxy documentation
- Open an issue on the repository

## Changelog

### 1.0.0
- Initial release
- Basic imgproxy integration
- Responsive image generation
- Priority loading support
- Admin configuration panel
- Source domain filtering
- Base64/plain URL format options
- Space encoding fix for signatures

---

**Made with ❤️ for WordPress performance optimization**