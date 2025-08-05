<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImgproxyOptimizer_SettingsManager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_options_page(
            'Imgproxy Image Optimizer',
            'Image Optimizer',
            'manage_options',
            'imgproxy-optimizer',
            array($this, 'admin_page')
        );
    }

    public function register_settings() {
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_url');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_key');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_salt');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_quality');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_format');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_widths');
        register_setting('imgproxy_optimizer_settings', 'imgproxy_optimizer_enabled');

        add_settings_section(
            'imgproxy_optimizer_main',
            'Imgproxy Configuration',
            array($this, 'section_callback'),
            'imgproxy_optimizer_settings'
        );

        add_settings_field(
            'imgproxy_optimizer_enabled',
            'Enable Image Optimization',
            array($this, 'checkbox_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array('option_name' => 'imgproxy_optimizer_enabled')
        );

        add_settings_field(
            'imgproxy_optimizer_url',
            'Imgproxy URL',
            array($this, 'text_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_url',
                'description' => 'The base URL of your imgproxy server (e.g., https://imgproxy.example.com)'
            )
        );

        add_settings_field(
            'imgproxy_optimizer_key',
            'Secret Key',
            array($this, 'text_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_key',
                'description' => 'Imgproxy secret key for URL signing'
            )
        );

        add_settings_field(
            'imgproxy_optimizer_salt',
            'Secret Salt',
            array($this, 'text_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_salt',
                'description' => 'Imgproxy secret salt for URL signing'
            )
        );

        add_settings_field(
            'imgproxy_optimizer_quality',
            'Image Quality',
            array($this, 'number_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_quality',
                'description' => 'Image quality (1-100, default: 65)',
                'min' => 1,
                'max' => 100
            )
        );

        add_settings_field(
            'imgproxy_optimizer_format',
            'Image Format',
            array($this, 'select_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_format',
                'description' => 'Output image format',
                'options' => array(
                    'avif' => 'AVIF',
                    'webp' => 'WebP',
                    'jpeg' => 'JPEG',
                    'png' => 'PNG'
                )
            )
        );

        add_settings_field(
            'imgproxy_optimizer_widths',
            'Responsive Widths',
            array($this, 'text_field'),
            'imgproxy_optimizer_settings',
            'imgproxy_optimizer_main',
            array(
                'option_name' => 'imgproxy_optimizer_widths',
                'description' => 'Comma-separated list of widths for responsive images (e.g., 320,640,768,1024,1280,1920)'
            )
        );
    }

    public function section_callback() {
        echo '<p>Configure your imgproxy server settings for image optimization.</p>';
    }

    public function text_field($args) {
        $option_name = $args['option_name'];
        $value = get_option($option_name, '');
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<input type="text" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function number_field($args) {
        $option_name = $args['option_name'];
        $value = get_option($option_name, '');
        $description = isset($args['description']) ? $args['description'] : '';
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        
        echo '<input type="number" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="small-text"';
        if (!empty($min)) echo ' min="' . esc_attr($min) . '"';
        if (!empty($max)) echo ' max="' . esc_attr($max) . '"';
        echo ' />';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function select_field($args) {
        $option_name = $args['option_name'];
        $value = get_option($option_name, '');
        $description = isset($args['description']) ? $args['description'] : '';
        $options = isset($args['options']) ? $args['options'] : array();
        
        echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function checkbox_field($args) {
        $option_name = $args['option_name'];
        $value = get_option($option_name, 1);
        $description = isset($args['description']) ? $args['description'] : '';
        
        echo '<input type="checkbox" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="1"' . checked($value, 1, false) . ' />';
        echo '<label for="' . esc_attr($option_name) . '">Enable image optimization</label>';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Imgproxy Image Optimizer Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('imgproxy_optimizer_settings');
                do_settings_sections('imgproxy_optimizer_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}