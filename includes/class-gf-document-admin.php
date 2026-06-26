<?php
/**
 * Admin class for Gravity Forms Document Generator
 * 
 * Handles all admin settings and form configuration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GF_Docgen_Admin {
    private $api_handler;
    
    public function __construct($api_handler) {
        $this->api_handler = $api_handler;
        
        // Add admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add form settings tab for Gravity Forms
        add_filter('gform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);
        add_action('gform_form_settings_page_document_generator', array($this, 'render_form_settings'));
        
        // Add entry meta box to show documents
        add_action('gform_entry_detail_content_after', array($this, 'add_entry_meta_box'), 10, 2);
        
        // Add scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_gf_docgen_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_gf_docgen_fetch_placeholders', array($this, 'ajax_fetch_placeholders'));
    }
    
    /**
     * Add plugin menu under Settings
     */
    public function add_admin_menu() {
        add_options_page(
            __('Document Generator Settings', 'gf-activemerge'),
            __('Document Generator', 'gf-activemerge'),
            'manage_options',
            'gf-document-generator',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('gf_docgen_settings', 'gf_docgen_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        register_setting('gf_docgen_settings', 'gf_docgen_debug_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        // Debug: Log which settings are being registered
        gf_docgen_custom_log('GF Document Generator: Settings registered - API key setting included');
    }
    
    /**
     * Render the main settings page
     */
    public function render_settings_page() {
        include_once GF_DOCGEN_PLUGIN_DIR . 'includes/admin/settings-page.php';
    }
    
    /**
     * Add Document Generator tab to form settings
     * 
     * @param array $menu Form settings menu tabs
     * @param int $form_id Current form ID
     * @return array Modified menu tabs
     */
    public function add_form_settings_menu($menu, $form_id_for_menu) { // Renamed for clarity
        if (defined('WP_DEBUG') && WP_DEBUG) {
            gf_docgen_custom_log('GF DocGen Admin Class: add_form_settings_menu method CALLED for form ID: ' . wp_json_encode($form_id_for_menu));
        }
        $menu[] = [
            'name' => 'document_generator',
            'label' => __('Document Generator', 'gf-activemerge'),
            'icon' => 'fa-file-text-o', // Make sure you have Font Awesome or similar if using icons
        ];
        return $menu;
    }
        
    /**
     * Render form-specific settings page
     *
     * @param int $form_id Current form ID from Gravity Forms hook
     */
    public function render_form_settings($form_id_from_hook) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            gf_docgen_custom_log('GF DocGen Admin Class: render_form_settings method CALLED.');
            gf_docgen_custom_log('GF DocGen Admin Class: Value of $form_id_from_hook (parameter from hook) is: ' . wp_json_encode($form_id_from_hook));
        }

        $form_id_to_use = $form_id_from_hook;

        // Attempt to get form_id from URL if the hook parameter is empty
        if (empty($form_id_to_use) && function_exists('rgget')) {
            $form_id_from_url = absint(rgget('id')); // rgget is a GF utility to get URL params
            if (defined('WP_DEBUG') && WP_DEBUG) {
                gf_docgen_custom_log('GF DocGen Admin Class: $form_id_from_hook was empty. Value from rgget("id") is: ' . wp_json_encode($form_id_from_url));
            }
            if ($form_id_from_url > 0) {
                $form_id_to_use = $form_id_from_url;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    gf_docgen_custom_log('GF DocGen Admin Class: Using $form_id_from_url: ' . $form_id_to_use);
                }
            }
        }

        // Make the determined form_id available to the included script
        $form_id = $form_id_to_use;

        // Ensure all your previous edits and debug code are in THIS specific file path:
        // GF_DOCGEN_PLUGIN_DIR . 'includes/admin/form-settings.php'
        include_once GF_DOCGEN_PLUGIN_DIR . 'includes/admin/form-settings.php';
    }
    
    /**
     * Add meta box to entry detail page to show generated documents
     * 
     * @param array $form Current form
     * @param array $entry Current entry
     */
    public function add_entry_meta_box($form, $entry) {
        $document_url = gform_get_meta($entry['id'], 'document_url');
        
        if (!$document_url) {
            return;
        }
        
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php esc_html_e('Generated Documents', 'gf-activemerge'); ?></h3>
            <div class="inside">
                <p>
                    <a href="<?php echo esc_url($document_url); ?>" target="_blank" class="button">
                        <?php esc_html_e('View Generated Document', 'gf-activemerge'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for testing the API connection
     */
    public function ajax_test_api() {
        // Security check
        check_ajax_referer('gf_docgen_admin', 'security');
        
        // Only allow admins
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'gf-activemerge'));
        }
        
        // Get test data
        $template_id = isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '';
        $output_format = isset($_POST['output_format']) ? sanitize_text_field(wp_unslash($_POST['output_format'])) : 'pdf';

        if (empty($template_id)) {
            wp_send_json_error(__('Please provide a template ID.', 'gf-activemerge'));
        }
        
        // Prepare test data
        $test_data = array(
            'test_field' => 'Test Value',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'date' => gmdate('Y-m-d'),
            'form_title' => 'Test Form',
            'form_id' => '0',
            'entry_id' => '0',
        );
        
        // Test API connection
        $result = $this->api_handler->test_api_connection($template_id, $test_data, $output_format);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for fetching template placeholders
     */
    public function ajax_fetch_placeholders() {
        // Security check
        check_ajax_referer('gf_docgen_admin', 'security');
        
        // Only allow admins
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'gf-activemerge'));
        }
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '';
        
        if (empty($template_id)) {
            wp_send_json_error(__('Please provide a template ID.', 'gf-activemerge'));
        }
        
        // Fetch placeholders
        $placeholders = $this->api_handler->fetch_placeholders($template_id);
        
        if ($placeholders !== false) {
            wp_send_json_success($placeholders);
        } else {
            wp_send_json_error(__('Failed to fetch placeholders.', 'gf-activemerge'));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_admin_scripts($hook) {
        // Check if we're on our settings page or Gravity Forms pages
        $is_settings_page = ('settings_page_gf-document-generator' === $hook);
        $is_gf_page = (strpos($hook, 'gf_') !== false || strpos($hook, 'forms_page_gf') !== false);
        
        if (!$is_settings_page && !$is_gf_page) {
            return;
        }
        
        // Enqueue jQuery and jQuery UI
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_style('jquery-ui', GF_DOCGEN_PLUGIN_URL . 'assets/css/jquery-ui.css', array(), GF_DOCGEN_VERSION);
        
        // Load JavaScript from file
        require_once GF_DOCGEN_PLUGIN_DIR . 'assets/js/admin.js.php';
        wp_add_inline_script('jquery', gf_docgen_admin_js());
        
        // Add admin styles if the file exists
        if (file_exists(GF_DOCGEN_PLUGIN_DIR . 'assets/css/admin.css')) {
            wp_enqueue_style('gf-docgen-admin', GF_DOCGEN_PLUGIN_URL . 'assets/css/admin.css', array(), GF_DOCGEN_VERSION);
        }
	}
}