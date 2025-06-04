<?php
if(!defined("HOUR_IN_SECONDS"))define("HOUR_IN_SECONDS",3600);
// Minimal WordPress environment for tests
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Stub WordPress functions used during plugin loading
$stubs = [
    'add_action', 'add_filter', 'remove_all_actions',
    'register_activation_hook', 'register_deactivation_hook',
    'add_submenu_page', 'register_setting', 'add_settings_section',
    'add_settings_field', 'plugins_url', 'wp_enqueue_style',
    'get_option', 'update_option', 'wp_remote_get',
    'wp_remote_retrieve_body', 'is_wp_error', 'sanitize_text_field',
    'sanitize_email', 'sanitize_textarea_field', 'wp_cache_get',
    'wp_cache_set', 'set_transient', 'get_transient', 'delete_transient',
    'remove_all_filters', 'apply_filters'
];
foreach ($stubs as $func) {
    if (!function_exists($func)) {
        eval('function '.$func.'(){ return null; }');
    }
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}

require_once dirname(__DIR__) . '/cf7-advanced-honeypot.php';
