<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImgproxyOptimizer_ImageProcessor {

    private $url_generator;
    private $preload_images = array();

    public function __construct() {
        $this->url_generator = new ImgproxyOptimizer_ImgproxyUrlGenerator();
        add_action('wp_head', array($this, 'output_preload_links'), 5);
    }

    public function process_html($html) {
        // Only process if plugin is enabled and configured
        if (!get_option('imgproxy_optimizer_enabled', 1) || !$this->url_generator->is_configured()) {
            return $html;
        }

        // Use regex-based approach to avoid HTML corruption issues with DOMDocument
        try {
            return $this->process_html_safe($html);
        } catch (Exception $e) {
            // Fallback to original HTML if processing fails
            error_log('ImgproxyOptimizer: Failed to process HTML - ' . $e->getMessage());
            return $html;
        }
    }

    private function process_html_safe($html) {
        // Find all img tags using regex
        $pattern = '/<img\s+([^>]*?)>/i';
        
        return preg_replace_callback($pattern, array($this, 'process_image_tag_callback'), $html);
    }

    private function process_image_tag_callback($matches) {
        $full_tag = $matches[0];
        
        // Extract src attribute value using regex (surgical approach)
        // Use negative lookbehind to avoid matching :src (Alpine.js dynamic attribute)
        $src_pattern = '/(?<!:)\bsrc\s*=\s*(["\'])([^"\']+)\1/i';
        if (!preg_match($src_pattern, $full_tag, $src_matches)) {
            return $full_tag; // No src attribute found
        }
        
        $quote_char = $src_matches[1]; // Preserve original quote style
        $original_src = $src_matches[2];
        
        // Skip if it's already an imgproxy URL
        if ($this->is_imgproxy_url($original_src)) {
            return $full_tag;
        }

        // Skip external images unless they're from allowed domains
        if (!$this->should_process_image($original_src)) {
            return $full_tag;
        }

        // Extract dimensions for optimization (optional, for width/height-specific optimization)
        $width = 0;
        $height = 0;
        if (preg_match('/\bwidth\s*=\s*["\']?(\d+)["\']?/i', $full_tag, $width_match)) {
            $width = intval($width_match[1]);
        }
        if (preg_match('/\bheight\s*=\s*["\']?(\d+)["\']?/i', $full_tag, $height_match)) {
            $height = intval($height_match[1]);
        }

        // Generate optimized src
        if ($width > 0 || $height > 0) {
            $optimized_src = $this->url_generator->generate_url($original_src, $width, 0, 'fit');
        } else {
            $optimized_src = $this->url_generator->generate_url($original_src, 0, 0, 'fit');
        }

        // Check for priority loading (minimal extraction needed)
        $is_priority = $this->is_priority_image_from_tag($full_tag);

        // Add to preload list if priority
        if ($is_priority) {
            $this->preload_images[] = array(
                'url' => $optimized_src,
                'width' => $width,
                'height' => $height
            );
        }

        // Start with the original tag
        $updated_tag = $full_tag;
        
        // Replace src attribute value (preserving quotes and everything else)
        $updated_tag = preg_replace($src_pattern, 'src=' . $quote_char . $optimized_src . $quote_char, $updated_tag);

        // Handle srcset generation if width is available
        if ($width > 0) {
            $widths = get_option('imgproxy_optimizer_widths', '320,640,768,1024,1280,1920');
            $srcset = $this->url_generator->generate_srcset($original_src, $widths, $width);
            if (!empty($srcset)) {
                // Check if srcset already exists and replace it, or add it
                if (preg_match('/\bsrcset\s*=\s*(["\'])([^"\']*)\1/i', $updated_tag)) {
                    $updated_tag = preg_replace('/\bsrcset\s*=\s*(["\'])([^"\']*)\1/i', 'srcset=$1' . $srcset . '$1', $updated_tag);
                } else {
                    // Add srcset after src attribute
                    $updated_tag = preg_replace('/(\bsrc\s*=\s*["\'][^"\']+["\'])/', '$1 srcset="' . $srcset . '"', $updated_tag);
                }
                
                // Add sizes attribute if not present
                if (!preg_match('/\bsizes\s*=/i', $updated_tag)) {
                    $sizes_value = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
                    $updated_tag = preg_replace('/(\bsrcset\s*=\s*["\'][^"\']+["\'])/', '$1 sizes="' . $sizes_value . '"', $updated_tag);
                }
            }
        }

        // Add loading attribute if not present
        if (!preg_match('/\bloading\s*=/i', $updated_tag)) {
            $loading_value = $is_priority ? 'eager' : 'lazy';
            // Add loading attribute before the closing >
            $updated_tag = preg_replace('/\s*>$/', ' loading="' . $loading_value . '">', $updated_tag);
        }

        return $updated_tag;
    }

    private function is_priority_image_from_tag($tag) {
        // Check for fetchpriority="high"
        if (preg_match('/\bfetchpriority\s*=\s*["\']?high["\']?/i', $tag)) {
            return true;
        }

        // Check for loading="eager"
        if (preg_match('/\bloading\s*=\s*["\']?eager["\']?/i', $tag)) {
            return true;
        }

        // Check for priority or hero class
        if (preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/', $tag, $class_matches)) {
            $class = $class_matches[1];
            if (strpos($class, 'priority') !== false || strpos($class, 'hero') !== false) {
                return true;
            }
        }

        return false;
    }




    private function should_process_image($src) {
        // Skip data URLs
        if (strpos($src, 'data:') === 0) {
            return false;
        }

        // Get allowed sources setting
        $allowed_sources = get_option('imgproxy_optimizer_allowed_sources', '');
        
        // If no allowed sources specified, default to current site only
        if (empty(trim($allowed_sources))) {
            if (preg_match('/^https?:\/\//', $src)) {
                $site_url = site_url();
                $site_host = parse_url($site_url, PHP_URL_HOST);
                $img_host = parse_url($src, PHP_URL_HOST);
                
                return $img_host === $site_host;
            }
            return true; // Allow relative URLs
        }

        // Parse allowed sources
        $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_sources)));
        
        // For relative URLs, always allow
        if (!preg_match('/^https?:\/\//', $src)) {
            return true;
        }

        // Check if the image URL matches any allowed source
        $img_host = parse_url($src, PHP_URL_HOST);
        if (!$img_host) {
            return false;
        }

        foreach ($allowed_domains as $allowed_domain) {
            if (empty($allowed_domain)) continue;
            
            // Handle wildcard domains (e.g., *.cloudfront.net)
            if (strpos($allowed_domain, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($allowed_domain, '/'));
                if (preg_match('/^' . $pattern . '$/i', $img_host)) {
                    return true;
                }
            } else {
                // Exact domain match
                if (strcasecmp($img_host, $allowed_domain) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function is_imgproxy_url($url) {
        $imgproxy_url = get_option('imgproxy_optimizer_url', '');
        if (empty($imgproxy_url)) {
            return false;
        }
        
        $imgproxy_host = parse_url($imgproxy_url, PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);
        
        return $imgproxy_host === $url_host;
    }

    public function output_preload_links() {
        if (empty($this->preload_images)) {
            return;
        }

        foreach ($this->preload_images as $image) {
            echo '<link rel="preload" as="image" href="' . esc_url($image['url']) . '"';
            if ($image['width'] > 0) {
                echo ' imagesrcset="' . esc_attr($this->generate_preload_srcset($image)) . '"';
                echo ' imagesizes="(max-width: ' . intval($image['width']) . 'px) 100vw, ' . intval($image['width']) . 'px"';
            }
            echo '>' . "\n";
        }
    }

    private function generate_preload_srcset($image) {
        // Generate a smaller srcset for preloading (fewer sizes)
        $preload_widths = array(320, 640, 1024);
        $widths_in_range = array();
        
        foreach ($preload_widths as $width) {
            if ($width <= $image['width']) {
                $widths_in_range[] = $width;
            }
        }
        
        if (empty($widths_in_range)) {
            $widths_in_range[] = $image['width'];
        }
        
        return $this->url_generator->generate_srcset($image['url'], $widths_in_range, $image['width']);
    }
}