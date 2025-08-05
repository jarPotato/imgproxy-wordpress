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

        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $images = $dom->getElementsByTagName('img');
        $images_array = array();
        
        // Convert NodeList to array to avoid live list issues
        foreach ($images as $img) {
            $images_array[] = $img;
        }

        foreach ($images_array as $img) {
            $this->process_image_element($img);
        }

        // Get the processed HTML
        $processed_html = $dom->saveHTML();
        
        // Remove the XML encoding declaration if it exists
        $processed_html = preg_replace('/^<\?xml[^>]+>/', '', $processed_html);
        
        return $processed_html;
    }

    private function process_image_element($img) {
        $src = $img->getAttribute('src');
        
        // Skip if no src or if it's already an imgproxy URL
        if (empty($src) || $this->is_imgproxy_url($src)) {
            return;
        }

        // Skip external images unless they're from allowed domains
        if (!$this->should_process_image($src)) {
            return;
        }

        // Get image dimensions
        $width = $img->getAttribute('width');
        $height = $img->getAttribute('height');
        $width = !empty($width) ? intval($width) : 0;
        $height = !empty($height) ? intval($height) : 0;

        // Check for priority loading
        $is_priority = $this->is_priority_image($img);

        // Generate optimized src
        if ($width > 0 || $height > 0) {
            // Use specified dimensions
            $optimized_src = $this->url_generator->generate_url($src, $width, 0, 'fit');
        } else {
            // No dimensions specified, let imgproxy auto-size
            $optimized_src = $this->url_generator->generate_url($src, 0, 0, 'fit');
        }

        // Update the src attribute
        $img->setAttribute('src', $optimized_src);

        // Generate and set srcset for responsive images
        if ($width > 0) {
            $this->add_srcset_to_image($img, $src, $width);
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
        if (!$img->hasAttribute('loading')) {
            $img->setAttribute('loading', $is_priority ? 'eager' : 'lazy');
        }
    }

    private function add_srcset_to_image($img, $original_src, $original_width) {
        $widths = get_option('imgproxy_optimizer_widths', '320,640,768,1024,1280,1920');
        $srcset = $this->url_generator->generate_srcset($original_src, $widths, $original_width);
        
        if (!empty($srcset)) {
            $img->setAttribute('srcset', $srcset);
            
            // Add sizes attribute if not present
            if (!$img->hasAttribute('sizes')) {
                $img->setAttribute('sizes', '(max-width: ' . $original_width . 'px) 100vw, ' . $original_width . 'px');
            }
        }
    }

    private function is_priority_image($img) {
        // Check for fetchpriority="high"
        if ($img->getAttribute('fetchpriority') === 'high') {
            return true;
        }

        // Check for loading="eager"
        if ($img->getAttribute('loading') === 'eager') {
            return true;
        }

        // Check for priority class or data attribute
        $class = $img->getAttribute('class');
        if (strpos($class, 'priority') !== false || strpos($class, 'hero') !== false) {
            return true;
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