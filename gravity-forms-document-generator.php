<?php
/**
 * Plugin Name: GF ActiveMerge Document Generator
 * Plugin URI: https://activemerge.com/integrations/wordpress
 * Description: Generate documents from Gravity Forms submissions using the ActiveMerge document generation service
 * Version: 1.0.0
 * Author: ActiveMerge
 * Author URI: https://activemerge.com
 * Text Domain: gf-activemerge
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * 
 * GF ActiveMerge Document Generator is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wp_filesystem;

// Define plugin constants
define('GF_DOCGEN_VERSION', '1.0.0');
define('GF_DOCGEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GF_DOCGEN_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Initialize and verify WP_Filesystem is properly set up
 * 
 * @return bool True if filesystem is initialized, false otherwise
 */
function gf_docgen_init_filesystem() {
    global $wp_filesystem;
    
    // If already initialized and working, return true
    if (is_object($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base) {
        return true;
    }
    
    // Try to initialize WP_Filesystem
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Attempt to initialize with credentials
    $creds = request_filesystem_credentials('', '', false, false, array());
    
    if ($creds === false) {
        // If we can't get credentials, try direct method
        if (WP_Filesystem()) {
            return is_object($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base;
        }
        return false;
    }
    
    // Initialize with credentials
    if (WP_Filesystem($creds)) {
        return is_object($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base;
    }
    
    return false;
}

// Check if Gravity Forms is active
add_action('plugins_loaded', 'gf_docgen_check_dependency');

/**
 * Check if Gravity Forms is active and load plugin files
 */
function gf_docgen_check_dependency() {
    if (!class_exists('GFForms')) {
        add_action('admin_notices', 'gf_docgen_admin_notice');
        return;
    }
    
    // Ensure WP_Filesystem is properly initialized
    global $wp_filesystem;
    if (!gf_docgen_init_filesystem()) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>GF Document Generator: Could not initialize WordPress filesystem.</p></div>';
        });
        return;
    }
    
    // Create includes directory if it doesn't exist
    if (!file_exists(GF_DOCGEN_PLUGIN_DIR . 'includes')) {
        $wp_filesystem->mkdir(GF_DOCGEN_PLUGIN_DIR . 'includes', 0755);
    }
    
    // Create assets directories if they don't exist
    if (!file_exists(GF_DOCGEN_PLUGIN_DIR . 'assets')) {
        $wp_filesystem->mkdir(GF_DOCGEN_PLUGIN_DIR . 'assets', 0755);
        $wp_filesystem->mkdir(GF_DOCGEN_PLUGIN_DIR . 'assets/js', 0755);
        $wp_filesystem->mkdir(GF_DOCGEN_PLUGIN_DIR . 'assets/css', 0755);
    }
    
    // Load required files
    require_once GF_DOCGEN_PLUGIN_DIR . 'includes/class-gf-document-api-handler.php';
    require_once GF_DOCGEN_PLUGIN_DIR . 'includes/class-gf-document-admin.php';
    require_once GF_DOCGEN_PLUGIN_DIR . 'includes/class-gf-document-generator.php';
    
    // Initialize the plugin
    GF_Docgen_Generator::get_instance();
}

/**
 * Display admin notice if Gravity Forms is not active
 */
function gf_docgen_admin_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Gravity Forms Document Generator requires Gravity Forms to be installed and activated.', 'gf-activemerge'); ?></p>
    </div>
    <?php
}

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'gf_docgen_activate');

function gf_docgen_activate() {
    // Ensure WP_Filesystem is properly initialized
    if (!gf_docgen_init_filesystem()) {
        wp_die('Could not initialize WordPress filesystem for GF Document Generator activation.');
        return;
    }
    
    global $wp_filesystem;
    
    // Create upload directory
    $upload_dir = wp_upload_dir();
    $document_dir = $upload_dir['basedir'] . '/gf-documents/';
    
    if (!file_exists($document_dir)) {
        $wp_filesystem->mkdir($document_dir, 0755, true);
    }
    
    // Set default options
    add_option('gf_docgen_api_key', '');
    add_option('gf_docgen_debug_mode', '0');
    
    // Debug: Check if options are being saved
    gf_docgen_custom_log('GF Document Generator: Activation - API Key option added');
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'gf_docgen_deactivate');

function gf_docgen_deactivate() {
    // Cleanup if needed
}

/**
 * error_log is not allowed in WordPress Standard, so we create our own log file.
 * Uses date-based rotation - creates new log file each day and cleans up old files.
 */
function gf_docgen_custom_log($message) {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/gf-docgen-logs/';
    $log_file = $log_dir . 'gf-docgen-' . gmdate('Y-m-d') . '.log';
    $max_days = 30; // Keep logs for 30 days

    // Ensure log directory exists
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        // Protect log directory from direct access
        $htaccess_file = $log_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            global $wp_filesystem;
            if (is_object($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base) {
                $wp_filesystem->put_contents($htaccess_file, "deny from all\n", FS_CHMOD_FILE);
            }
        }
    }

    // Clean up old log files (only run occasionally to avoid performance impact)
    if (wp_rand(1, 100) === 1) { // Run cleanup 1% of the time
        gf_docgen_cleanup_old_logs($log_dir, $max_days);
    }

    $log_entry = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    global $wp_filesystem;
    if (is_object($wp_filesystem) && $wp_filesystem instanceof WP_Filesystem_Base) {
        $existing = '';
        if ($wp_filesystem->exists($log_file)) {
            $existing = $wp_filesystem->get_contents($log_file);
        }
        $wp_filesystem->put_contents($log_file, $existing . $log_entry, FS_CHMOD_FILE);
    }
}

/**
 * Clean up old log files based on retention period
 */
function gf_docgen_cleanup_old_logs($log_dir, $max_days) {
    $cutoff_date = time() - ($max_days * 24 * 60 * 60);
    $pattern = $log_dir . 'gf-docgen-*.log';
    
    foreach (glob($pattern) as $file) {
        if (file_exists($file) && filemtime($file) < $cutoff_date) {
            wp_delete_file($file);
        }
    }
}