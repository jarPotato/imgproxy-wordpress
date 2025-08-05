<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImgproxyOptimizer_ImgproxyUrlGenerator {

    private $base_url;
    private $key;
    private $salt;
    private $quality;
    private $format;
    private $use_base64;

    public function __construct() {
        $this->base_url = rtrim(get_option('imgproxy_optimizer_url', ''), '/');
        $this->key = get_option('imgproxy_optimizer_key', '');
        $this->salt = get_option('imgproxy_optimizer_salt', '');
        $this->quality = get_option('imgproxy_optimizer_quality', 65);
        $this->format = get_option('imgproxy_optimizer_format', 'avif');
        $this->use_base64 = get_option('imgproxy_optimizer_use_base64', 1);
    }

    public function generate_url($image_url, $width = 0, $height = 0, $resize_type = 'fit') {
        if (empty($this->base_url) || empty($this->key) || empty($this->salt)) {
            return $image_url;
        }

        // Build the processing options
        $options = array(
            'rt:' . $resize_type,
            'w:' . intval($width),
            'h:' . intval($height),
            'q:' . intval($this->quality),
            'f:' . $this->format
        );

        $processing_options = '/' . implode('/', $options);

        if ($this->use_base64) {
            // Base64 encoded URL format with filename
            $parsed_url = parse_url($image_url);
            $path_info = pathinfo($parsed_url['path']);
            $filename = isset($path_info['filename']) ? $path_info['filename'] : 'image';

            // Base64 encode the source URL
            $encoded_url = rtrim(strtr(base64_encode($image_url), '+/', '-_'), '=');

            // Build the path without signature
            $path = $processing_options . '/' . $encoded_url . '/' . $filename . '.' . $this->format;
        } else {
            // Plain URL format - encode spaces to %20 for proper signature calculation
            // This ensures the signature matches what imgproxy receives after URL encoding
            $encoded_image_url = str_replace(' ', '%20', $image_url);
            $path = $processing_options . '/plain/' . $encoded_image_url;
        }

        // Generate HMAC signature
        $signature = $this->generate_signature($path);

        // Return the complete imgproxy URL
        return $this->base_url . '/' . $signature . $path;
    }

    private function generate_signature($path) {
        if (empty($this->key) || empty($this->salt)) {
            return '';
        }

        // Decode hex key and salt
        $key_bin = pack('H*', $this->key);
        $salt_bin = pack('H*', $this->salt);

        // Generate HMAC
        $hmac = hash_hmac('sha256', $salt_bin . $path, $key_bin, true);

        // Base64 encode and make URL-safe
        return rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
    }

    public function generate_srcset($image_url, $widths, $original_width = null) {
        if (!is_array($widths)) {
            $widths = explode(',', $widths);
        }

        $srcset_parts = array();
        
        foreach ($widths as $width) {
            $width = intval(trim($width));
            if ($width <= 0) continue;
            
            // If original width is known and current width is larger, skip it
            if ($original_width && $width > $original_width) {
                continue;
            }
            
            $optimized_url = $this->generate_url($image_url, $width, 0, 'fit');
            $srcset_parts[] = $optimized_url . ' ' . $width . 'w';
        }

        return implode(', ', $srcset_parts);
    }

    public function is_configured() {
        return !empty($this->base_url) && !empty($this->key) && !empty($this->salt);
    }

    public function get_preload_url($image_url, $width = 0, $height = 0) {
        return $this->generate_url($image_url, $width, $height, 'fit');
    }
}