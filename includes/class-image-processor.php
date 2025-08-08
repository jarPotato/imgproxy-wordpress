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
        $attributes = $matches[1];
        
        // Parse attributes into an array
        $attrs = $this->parse_image_attributes($attributes);
        
        // Skip if no src or if it's already an imgproxy URL
        if (empty($attrs['src']) || $this->is_imgproxy_url($attrs['src'])) {
            return $full_tag;
        }

        // Skip external images unless they're from allowed domains
        if (!$this->should_process_image($attrs['src'])) {
            return $full_tag;
        }

        // Get image dimensions
        $width = isset($attrs['width']) ? intval($attrs['width']) : 0;
        $height = isset($attrs['height']) ? intval($attrs['height']) : 0;

        // Check for priority loading
        $is_priority = $this->is_priority_image_from_attrs($attrs);

        // Generate optimized src
        if ($width > 0 || $height > 0) {
            $optimized_src = $this->url_generator->generate_url($attrs['src'], $width, 0, 'fit');
        } else {
            $optimized_src = $this->url_generator->generate_url($attrs['src'], 0, 0, 'fit');
        }

        // Store original src for srcset generation
        $original_src = $attrs['src'];
        
        // Update attributes
        $attrs['src'] = $optimized_src;

        // Generate and set srcset for responsive images
        if ($width > 0) {
            $widths = get_option('imgproxy_optimizer_widths', '320,640,768,1024,1280,1920');
            $srcset = $this->url_generator->generate_srcset($original_src, $widths, $width);
            if (!empty($srcset)) {
                $attrs['srcset'] = $srcset;
                
                // Add sizes attribute if not present
                if (!isset($attrs['sizes'])) {
                    $attrs['sizes'] = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
                }
            }
        }

        // Add to preload list if priority
        if ($is_priority) {
            $this->preload_images[] = array(
                'url' => $optimized_src,
                'width' => $width,
                'height' => $height
            );
        }

        // Ensure loading attribute is set appropriately
        if (!isset($attrs['loading'])) {
            $attrs['loading'] = $is_priority ? 'eager' : 'lazy';
        }

        // Rebuild the img tag with updated attributes
        return $this->rebuild_image_tag($attrs);
    }

    private function parse_image_attributes($attr_string) {
        $attributes = array();
        
        // Match attribute="value" or attribute='value' or attribute=value
        preg_match_all('/(\w+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/i', $attr_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            $value = isset($match[2]) && $match[2] !== '' ? $match[2] : 
                    (isset($match[3]) && $match[3] !== '' ? $match[3] : 
                    (isset($match[4]) && $match[4] !== '' ? $match[4] : ''));
            $attributes[$name] = $value;
        }
        
        return $attributes;
    }

    private function rebuild_image_tag($attrs) {
        $tag = '<img';
        
        foreach ($attrs as $name => $value) {
            if ($value === '') {
                // Handle boolean attributes (like 'required', 'disabled', etc.)
                $tag .= ' ' . $name;
            } else {
                $tag .= ' ' . $name . '="' . esc_attr($value) . '"';
            }
        }
        
        $tag .= '>';
        return $tag;
    }

    private function is_priority_image_from_attrs($attrs) {
        // Check for fetchpriority="high"
        if (isset($attrs['fetchpriority']) && $attrs['fetchpriority'] === 'high') {
            return true;
        }

        // Check for loading="eager"
        if (isset($attrs['loading']) && $attrs['loading'] === 'eager') {
            return true;
        }

        // Check for priority class or data attribute
        if (isset($attrs['class'])) {
            $class = $attrs['class'];
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