<?php
/**
 * Plugin Name: Imgproxy Image Optimizer
 * Plugin URI: https://myjar.app
 * Description: WordPress plugin for optimizing images using imgproxy with Next.js-like features including responsive srcset, priority loading, and DNS prefetch.
 * Version: 1.0.0
 * Author: Jar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IMGPROXY_OPTIMIZER_VERSION', '1.0.0');
define('IMGPROXY_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMGPROXY_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'ImgproxyOptimizer') === 0) {
        $class_file = str_replace('_', '-', strtolower(substr($class_name, 17)));
        $file_path = IMGPROXY_OPTIMIZER_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

class ImgproxyOptimizer {
    private static $instance = null;
    private $settings_manager;
    private $image_processor;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        // Load required classes
        require_once IMGPROXY_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
        require_once IMGPROXY_OPTIMIZER_PLUGIN_DIR . 'includes/class-imgproxy-url-generator.php';
        require_once IMGPROXY_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-processor.php';

        // Initialize components
        $this->settings_manager = new ImgproxyOptimizer_SettingsManager();
        $this->image_processor = new ImgproxyOptimizer_ImageProcessor();

        // Hook into WordPress
        add_action('init', array($this, 'start_output_buffering'));
        add_action('wp_head', array($this, 'add_dns_prefetch'), 1);
    }

    public function start_output_buffering() {
        if (!is_admin() && !is_feed() && !is_robots() && !is_trackback()) {
            ob_start(array($this->image_processor, 'process_html'));
        }
    }

    public function add_dns_prefetch() {
        $imgproxy_url = get_option('imgproxy_optimizer_url', '');
        if (!empty($imgproxy_url)) {
            $parsed_url = parse_url($imgproxy_url);
            if (isset($parsed_url['host'])) {
                echo '<link rel="dns-prefetch" href="//' . esc_attr($parsed_url['host']) . '">' . "\n";
            }
        }
    }

    public static function activate() {
        // Set default options
        add_option('imgproxy_optimizer_url', '');
        add_option('imgproxy_optimizer_key', '');
        add_option('imgproxy_optimizer_salt', '');
        add_option('imgproxy_optimizer_quality', 65);
        add_option('imgproxy_optimizer_format', 'avif');
        add_option('imgproxy_optimizer_widths', '320,640,768,1024,1280,1920');
        add_option('imgproxy_optimizer_enabled', 1);
    }

    public static function deactivate() {
        // Clean up if needed
        wp_cache_flush();
    }

    public static function uninstall() {
        // Remove all options
        delete_option('imgproxy_optimizer_url');
        delete_option('imgproxy_optimizer_key');
        delete_option('imgproxy_optimizer_salt');
        delete_option('imgproxy_optimizer_quality');
        delete_option('imgproxy_optimizer_format');
        delete_option('imgproxy_optimizer_widths');
        delete_option('imgproxy_optimizer_enabled');
    }
}

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, array('ImgproxyOptimizer', 'activate'));
register_deactivation_hook(__FILE__, array('ImgproxyOptimizer', 'deactivate'));
register_uninstall_hook(__FILE__, array('ImgproxyOptimizer', 'uninstall'));

// Initialize the plugin
ImgproxyOptimizer::get_instance();