<?php
/**
 * Main plugin class for Gravity Forms Document Generator
 * 
 * Handles core plugin functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GF_Docgen_Generator {
    private static $instance = null;
    private $api_handler = null;
    
    // Private constructor to prevent direct creation
    private function __construct() {
        // Initialize API handler
        $this->api_handler = new GF_Docgen_API_Handler();
        
        // Initialize admin
        if (is_admin()) {
            new GF_Docgen_Admin($this->api_handler);
        }
        
        // Add hooks
        add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2);
        
        // Add async document generation hook
        add_action('gf_docgen_generate_async', array($this, 'generate_document_async'), 10, 2);
        
        // Add AJAX handlers for status checking
        add_action('wp_ajax_gf_docgen_check_status', array($this, 'ajax_check_document_status'));
        add_action('wp_ajax_nopriv_gf_docgen_check_status', array($this, 'ajax_check_document_status'));
        
        // Add AJAX handler for getting real job ID
        add_action('wp_ajax_gf_docgen_get_real_job_id', array($this, 'ajax_get_real_job_id'));
        add_action('wp_ajax_nopriv_gf_docgen_get_real_job_id', array($this, 'ajax_get_real_job_id'));
        
        // Add shortcode for document download
        add_shortcode('gf_docgen_download', array($this, 'document_download_shortcode'));
        
        // Add confirmation page content - CRITICAL: Use higher priority
        add_filter('gform_confirmation', array($this, 'add_download_link_to_confirmation'), 20, 4);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // DEBUG: Log that hooks are being added
        gf_docgen_custom_log('GF_Docgen_Generator: Hooks added, gform_confirmation filter registered');
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            gf_docgen_custom_log('GF_Docgen_Generator: Instance created');
        }
        return self::$instance;
    }
    
    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend
        if (!is_admin()) {
            wp_enqueue_style('gf-docgen-frontend', GF_DOCGEN_PLUGIN_URL . 'assets/css/frontend.css', array(), GF_DOCGEN_VERSION);
            wp_enqueue_script('gf-docgen-frontend', GF_DOCGEN_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), GF_DOCGEN_VERSION, true);
            wp_localize_script('gf-docgen-frontend', 'gf_docgen_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gf_docgen_nonce')
            ));
        }
    }
    
    /**
     * Process form submission to generate a document
     * 
     * @param array $entry The entry created from the form submission
     * @param array $form The form object
     */
    public function process_form_submission($entry, $form) {
        gf_docgen_custom_log('GF_Docgen_Generator: process_form_submission called for form ' . $form['id']);
        
        // Check if document generation is enabled for this form
        $form_settings = get_option('gf_docgen_form_' . $form['id'], null);
        
        gf_docgen_custom_log('GF_Docgen_Generator: Form settings for form ' . $form['id'] . ': ' . wp_json_encode($form_settings));
        
        if (empty($form_settings) || empty($form_settings['enabled']) || $form_settings['enabled'] !== '1') {
            gf_docgen_custom_log('GF_Docgen_Generator: Document generation not enabled for form ' . $form['id']);
            return;
        }
        
        // Get template ID and output format
        $template_id = !empty($form_settings['template_id']) ? $form_settings['template_id'] : '';
        $output_format = !empty($form_settings['output_format']) ? $form_settings['output_format'] : 'pdf';
        
        if (empty($template_id)) {
            gf_docgen_custom_log('GF Document Generator: No template ID specified for form ' . $form['id']);
            return;
        }
        
        // Generate a unique job ID
        $job_id = uniqid('gf_docgen_' . $entry['id'] . '_');
        
        gf_docgen_custom_log('GF_Docgen_Generator: Created job ID: ' . $job_id . ' for entry: ' . $entry['id']);
        
        // Store job status in database
        $job_data = array(
            'status' => 'processing',
            'entry_id' => $entry['id'],
            'form_id' => $form['id'],
            'template_id' => $template_id,
            'output_format' => $output_format,
            'created' => time(),
            'form_settings' => $form_settings
        );
        update_option('gf_docgen_job_' . $job_id, $job_data);
        
        // Store job ID in entry meta for later reference
        gform_update_meta($entry['id'], 'document_job_id', $job_id);
        gform_update_meta($entry['id'], 'document_status', 'processing');
        
        gf_docgen_custom_log('GF_Docgen_Generator: Stored job data and entry meta for job: ' . $job_id);
        
        // Schedule async document generation
        wp_schedule_single_event(time(), 'gf_docgen_generate_async', array($job_id, $entry['id']));
        
        gf_docgen_custom_log('GF_Docgen_Generator: Scheduled async generation for job: ' . $job_id);
    }
    
    /**
     * Handle async document generation
     */
    public function generate_document_async($job_id, $entry_id) {
        gf_docgen_custom_log('GF_Docgen_Generator: generate_document_async called for job: ' . $job_id);
        
        // Get job data
        $job_data = get_option('gf_docgen_job_' . $job_id);
        if (!$job_data) {
            gf_docgen_custom_log('GF Document Generator: Job data not found for ' . $job_id);
            return;
        }
        
        // Get entry and form
        $entry = GFAPI::get_entry($entry_id);
        $form = GFAPI::get_form($job_data['form_id']);
        
        if (is_wp_error($entry) || is_wp_error($form)) {
            $this->update_job_status($job_id, 'failed', 'Entry or form not found');
            return;
        }
        
        try {
            // Prepare form data
            $form_data = $this->prepare_form_data($entry, $form);
            
            gf_docgen_custom_log('GF_Docgen_Generator: Form data prepared: ' . wp_json_encode($form_data));
            
            // Generate custom filename if specified
            $custom_filename = '';
            if (!empty($job_data['form_settings']['custom_filename'])) {
                $custom_filename = $this->replace_filename_placeholders(
                    $job_data['form_settings']['custom_filename'], 
                    $entry, 
                    $form
                );
                gf_docgen_custom_log('GF_Docgen_Generator: Custom filename generated: ' . $custom_filename);
            }
            
            // Generate the document
            $document_path = $this->api_handler->generate_document(
                $job_data['template_id'], 
                $form_data, 
                $job_data['output_format'],
                $custom_filename
            );
            
            if ($document_path) {
                // Update entry meta with document path
                $upload_dir = wp_upload_dir();
                $document_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $document_path);
                
                gform_update_meta($entry['id'], 'document_path', $document_path);
                gform_update_meta($entry['id'], 'document_url', $document_url);
                gform_update_meta($entry['id'], 'document_status', 'completed');
                
                // Update job status
                $this->update_job_status($job_id, 'completed', '', array(
                    'document_path' => $document_path,
                    'document_url' => $document_url
                ));
                
                gf_docgen_custom_log('GF_Docgen_Generator: Document generated successfully for job: ' . $job_id . ', URL: ' . $document_url);
                
                // Handle email and notifications
                $form_settings = $job_data['form_settings'];
                
                // Send email if needed
                if (!empty($form_settings['send_email']) && $form_settings['send_email'] === '1') {
                    $this->send_document_email($entry, $form, $document_path, $document_url);
                }
                
            } else {
                $this->update_job_status($job_id, 'failed', 'Document generation failed');
                gform_update_meta($entry['id'], 'document_status', 'failed');
                gf_docgen_custom_log('GF_Docgen_Generator: Document generation failed for job: ' . $job_id);
            }
            
        } catch (Exception $e) {
            gf_docgen_custom_log('GF Document Generator Error: ' . $e->getMessage());
            $this->update_job_status($job_id, 'failed', $e->getMessage());
            gform_update_meta($entry['id'], 'document_status', 'failed');
        }
    }
   
    /**
     * Update job status in database
     */
    private function update_job_status($job_id, $status, $error_message = '', $additional_data = array()) {
        $job_data = get_option('gf_docgen_job_' . $job_id, array());
        $job_data['status'] = $status;
        $job_data['updated'] = time();
        
        if (!empty($error_message)) {
            $job_data['error'] = $error_message;
        }
        
        if (!empty($additional_data)) {
            $job_data = array_merge($job_data, $additional_data);
        }
        
        update_option('gf_docgen_job_' . $job_id, $job_data);
        
        gf_docgen_custom_log('GF_Docgen_Generator: Updated job status to: ' . $status . ' for job: ' . $job_id);
    }
    
    /**
     * AJAX handler for checking document status
     */
    public function ajax_check_document_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce'])) {
            wp_send_json_error('Missing nonce');
            return;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if (!wp_verify_nonce($nonce, 'gf_docgen_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!isset($_POST['job_id']) || empty($_POST['job_id'])) {
            wp_send_json_error('Missing job_id');
            return;
        }
        $job_id = sanitize_text_field(wp_unslash($_POST['job_id']));
        $job_data = get_option('gf_docgen_job_' . $job_id);
        
        if (!$job_data) {
            wp_send_json_error('Job not found');
            return;
        }
        
        gf_docgen_custom_log('GF_Docgen_Generator: AJAX status check for job: ' . $job_id . ', status: ' . $job_data['status']);
        
        wp_send_json_success($job_data);
    }
    
    /**
     * AJAX handler for getting real job ID from entry
     */
    public function ajax_get_real_job_id() {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce'])) {
            wp_send_json_error('Missing nonce');
            return;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if (!wp_verify_nonce($nonce, 'gf_docgen_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!isset($_POST['entry_id']) || empty($_POST['entry_id'])) {
            wp_send_json_error('Missing entry_id');
            return;
        }
        $entry_id = sanitize_text_field(wp_unslash($_POST['entry_id']));
        $job_id = gform_get_meta($entry_id, 'document_job_id');
        
        if (!empty($job_id)) {
            wp_send_json_success(array('job_id' => $job_id));
        } else {
            wp_send_json_error('Job ID not found yet');
        }
    }
    
    /**
     * Prepare form data for API
     * 
     * @param array $entry The form entry
     * @param array $form The form object
     * @return array Formatted form data
     */
    private function prepare_form_data($entry, $form) {
        $form_data = array();
        
        // Get form settings for field mappings
        $form_settings = get_option('gf_docgen_form_' . $form['id'], null);
        $field_mappings = !empty($form_settings['field_mappings']) ? $form_settings['field_mappings'] : [];
        
        // Loop through form fields
        foreach ($form['fields'] as $field) {
            // Skip fields without inputs (like sections, HTML)
            if (empty($field['inputs'])) {
                $field_id = $field['id'];
                
                // Only process this field if it has a mapping configured
                if (empty($field_mappings[$field_id])) {
                    continue;
                }
                
                $field_value = rgar($entry, $field_id);
                
                // Skip empty values
                if (empty($field_value)) {
                    continue;
                }
                
                // Add the field to our data using the mapped name
                $field_name = $field_mappings[$field_id];
                
                $form_data[$field_name] = $field_value;
            } 
            // Handle fields with multiple inputs (like checkboxes, name fields)
            else {
                foreach ($field['inputs'] as $input) {
                    $input_id = $input['id'];
                    
                    // Only process this input if it has a mapping configured
                    if (empty($field_mappings[$input_id])) {
                        continue;
                    }
                    
                    $input_value = rgar($entry, $input_id);
                    
                    // Skip empty values
                    if (empty($input_value)) {
                        continue;
                    }
                    
                    // Add the input to our data using the mapped name
                    $input_name = $field_mappings[$input_id];
                    
                    $form_data[$input_name] = $input_value;
                }
            }
        }
        
        // Only add system fields if there are corresponding mappings for them
        $system_fields = [
            'form_title' => $form['title'],
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'date_created' => $entry['date_created'],
            'user_ip' => $entry['ip']
        ];
        
        foreach ($system_fields as $field_key => $field_value) {
            // Only add if there's a mapping for this system field
            if (!empty($field_mappings[$field_key])) {
                $form_data[$field_mappings[$field_key]] = $field_value;
            }
        }
        
        // Allow filtering the form data
        return apply_filters('gf_docgen_form_data', $form_data, $entry, $form);
    }
    
    /**
     * Send email with the generated document
     * 
     * @param array $entry The form entry
     * @param array $form The form object
     * @param string $document_path Path to the document
     * @param string $document_url URL to the document
     */
    private function send_document_email($entry, $form, $document_path, $document_url) {
        $form_settings = get_option('gf_docgen_form_' . $form['id'], null);
        
        // Get email settings
        $to = $this->replace_merge_tags($form_settings['email_to'] ?? '', $entry, $form);
        $subject = $this->replace_merge_tags($form_settings['email_subject'] ?? 'Your document is ready', $entry, $form);
        $message = $this->replace_merge_tags($form_settings['email_message'] ?? 'Please find your document attached.', $entry, $form);
        
        // Add document link to email
        // translators: %s is the URL to the generated document
        $message .= "\n\n" . sprintf(__('You can view your document here: %s', 'gf-activemerge'), $document_url);

        // Set up email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Add from name and email if specified
        if (!empty($form_settings['email_from'])) {
            $from_email = $this->replace_merge_tags($form_settings['email_from'], $entry, $form);
            $from_name = !empty($form_settings['email_from_name']) ? 
                $this->replace_merge_tags($form_settings['email_from_name'], $entry, $form) :
                get_bloginfo('name');
                
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }
        
        // Set up attachments if needed
        $attachments = array();
        if (!empty($form_settings['email_attach_document']) && $form_settings['email_attach_document'] === '1') {
            $attachments[] = $document_path;
        }
        
        // Send the email
        wp_mail($to, $subject, $message, $headers, $attachments);
    }
    
    /**
     * Replace merge tags in a string with actual values
     * 
     * @param string $text Text containing merge tags
     * @param array $entry The form entry
     * @param array $form The form object
     * @return string Text with merge tags replaced
     */
    private function replace_merge_tags($text, $entry, $form) {
        // Replace basic merge tags
        $text = str_replace('{form_title}', $form['title'], $text);
        $text = str_replace('{form_id}', $form['id'], $text);
        $text = str_replace('{entry_id}', $entry['id'], $text);
        $text = str_replace('{date_created}', $entry['date_created'], $text);
        
        // Replace field-specific merge tags
        preg_match_all('/{([^}]+)}/', $text, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                // Check if it's a field ID
                if (is_numeric($match)) {
                    $value = rgar($entry, $match);
                    $text = str_replace('{' . $match . '}', $value, $text);
                }
            }
        }
        
        return $text;
    }
    
    /**
     * Replace filename placeholders with actual values
     * 
     * Supports system placeholders: {date}, {time}, {datetime}, {entry_id}, {form_id}, 
     * {form_title}, {site_title}, {admin_email}
     * 
     * Supports field placeholders using field labels: {field_label}, {first_name}, etc.
     * Field labels are matched both exactly and with sanitized versions (spaces become underscores, 
     * special characters removed, case insensitive).
     * 
     * @param string $filename_template Template string with placeholders
     * @param array $entry The form entry
     * @param array $form The form object
     * @return string Filename with placeholders replaced and sanitized for filesystem
     */
    private function replace_filename_placeholders($filename_template, $entry, $form) {
        if (empty($filename_template)) {
            return '';
        }
        
        $filename = $filename_template;
        
        $filename = str_replace('{date}', gmdate('Y-m-d'), $filename);
        $filename = str_replace('{time}', gmdate('H-i-s'), $filename);
        $filename = str_replace('{datetime}', gmdate('Y-m-d_H-i-s'), $filename);
        $filename = str_replace('{entry_id}', $entry['id'], $filename);
        $filename = str_replace('{form_id}', $form['id'], $filename);
        $filename = str_replace('{form_title}', sanitize_title($form['title']), $filename);
        $filename = str_replace('{site_title}', sanitize_title(get_bloginfo('name')), $filename);
        $filename = str_replace('{admin_email}', sanitize_email(get_bloginfo('admin_email')), $filename);
        
        // Form field placeholders - using field labels
        preg_match_all('/{([^}]+)}/', $filename, $field_matches);
        
        if (!empty($field_matches[1])) {
            // Create a map of field labels to values
            $field_label_map = array();
            
            foreach ($form['fields'] as $field) {
                // Skip fields without inputs (simple fields)
                if (empty($field['inputs'])) {
                    $field_label = !empty($field['label']) ? $field['label'] : 'Field ' . $field['id'];
                    $field_value = rgar($entry, $field['id']);
                    
                    // Create sanitized label for matching
                    $sanitized_label = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $field_label));
                    $sanitized_label = preg_replace('/\s+/', '_', trim($sanitized_label));
                    
                    $field_label_map[$sanitized_label] = $field_value;
                    $field_label_map[$field_label] = $field_value; // Also keep original label
                } 
                // Handle fields with multiple inputs (like checkboxes, name fields)
                else {
                    foreach ($field['inputs'] as $input) {
                        $input_label = !empty($input['label']) ? $input['label'] : 
                                      (!empty($field['label']) ? $field['label'] . ' ' . $input['id'] : 'Field ' . $input['id']);
                        $input_value = rgar($entry, $input['id']);
                        
                        // Create sanitized label for matching
                        $sanitized_label = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $input_label));
                        $sanitized_label = preg_replace('/\s+/', '_', trim($sanitized_label));
                        
                        $field_label_map[$sanitized_label] = $input_value;
                        $field_label_map[$input_label] = $input_value; // Also keep original label
                    }
                }
            }
            
            // Replace placeholders with field values
            foreach ($field_matches[1] as $placeholder) {
                // Skip system placeholders (already handled above)
                $system_placeholders = array('date', 'time', 'datetime', 'entry_id', 'form_id', 'form_title', 'site_title', 'admin_email');
                if (in_array($placeholder, $system_placeholders)) {
                    continue;
                }
                
                // Try to find matching field value
                $field_value = '';
                
                // First try exact match
                if (isset($field_label_map[$placeholder])) {
                    $field_value = $field_label_map[$placeholder];
                }
                // Then try sanitized match
                else {
                    $sanitized_placeholder = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $placeholder));
                    $sanitized_placeholder = preg_replace('/\s+/', '_', trim($sanitized_placeholder));
                    
                    if (isset($field_label_map[$sanitized_placeholder])) {
                        $field_value = $field_label_map[$sanitized_placeholder];
                    }
                }
                
                // Sanitize field value for filename use
                if (!empty($field_value)) {
                    $sanitized_value = sanitize_file_name($field_value);
                    // Remove common problematic characters and limit length
                    $sanitized_value = preg_replace('/[^a-zA-Z0-9\-_]/', '', $sanitized_value);
                    $sanitized_value = substr($sanitized_value, 0, 50); // Limit length
                    $filename = str_replace('{' . $placeholder . '}', $sanitized_value, $filename);
                } else {
                    $filename = str_replace('{' . $placeholder . '}', 'empty', $filename);
                }
            }
        }
        
        // Final sanitization for filesystem safety
        $filename = sanitize_file_name($filename);
        
        // Remove any remaining curly braces and clean up
        $filename = preg_replace('/[{}]/', '', $filename);
        
        // Ensure we don't have empty filename or only special characters
        if (empty($filename) || preg_match('/^[.\-_]+$/', $filename)) {
            $filename = 'document_' . gmdate('Y-m-d_H-i-s') . '_' . $entry['id'];
        }
        
        // Limit total filename length (excluding extension)
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        
        return $filename;
    }
    
    /**
     * Shortcode to display document download link
     * 
     * Usage: [gf_docgen_download entry_id="123" text="Download Your Document" class="btn btn-primary"]
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function document_download_shortcode($atts) {
        $atts = shortcode_atts(array(
            'entry_id' => '',
            'text' => __('Download Your Document', 'gf-activemerge'),
            'class' => 'gf-document-download-link',
            'target' => '_blank',
            'style' => ''
        ), $atts);
        
        // If no entry ID provided, try to get from URL or session
        if (empty($atts['entry_id'])) {
            $atts['entry_id'] = $this->get_current_entry_id();
        }
        
        if (empty($atts['entry_id'])) {
            return '<p class="gf-document-error">' . __('No document available for download.', 'gf-activemerge') . '</p>';
        }
        
        // Get document URL from entry meta
        $document_url = gform_get_meta($atts['entry_id'], 'document_url');
        
        if (empty($document_url)) {
            return '<p class="gf-document-error">' . __('Document is being generated. Please refresh in a moment.', 'gf-activemerge') . '</p>';
        }
        
        // Build the download link
        $link_html = sprintf(
            '<a href="%s" class="%s" target="%s" style="%s">%s</a>',
            esc_url($document_url),
            esc_attr($atts['class']),
            esc_attr($atts['target']),
            esc_attr($atts['style']),
            esc_html($atts['text'])
        );
        
        return $link_html;
    }
    
    /**
     * Add download link to form confirmation page
     * 
     * @param string $confirmation Current confirmation content
     * @param array $form Current form
     * @param array $entry Current entry
     * @param bool $ajax Whether this is an AJAX submission
     * @return string Modified confirmation content
     */
     public function add_download_link_to_confirmation($confirmation, $form, $entry, $ajax) {
        gf_docgen_custom_log('GF_Docgen_Generator: add_download_link_to_confirmation called for form ' . $form['id'] . ', entry ' . $entry['id']);
        gf_docgen_custom_log('GF_Docgen_Generator: Current confirmation content: ' . substr($confirmation, 0, 200) . '...');
        
        // Check if document generation is enabled for this form
        $form_settings = get_option('gf_docgen_form_' . $form['id'], null);
        
        gf_docgen_custom_log('GF_Docgen_Generator: Form settings: ' . wp_json_encode($form_settings));
        
        if (empty($form_settings) || empty($form_settings['enabled']) || $form_settings['enabled'] !== '1') {
            gf_docgen_custom_log('GF_Docgen_Generator: Document generation not enabled for form ' . $form['id']);
            return $confirmation;
        }
        
        // Check if download link should be shown on confirmation
        if (empty($form_settings['show_download_link']) || $form_settings['show_download_link'] !== '1') {
            gf_docgen_custom_log('GF_Docgen_Generator: Show download link not enabled for form ' . $form['id']);
            return $confirmation;
        }
        
        // Get job ID from entry meta
        $job_id = gform_get_meta($entry['id'], 'document_job_id');
        $document_status = gform_get_meta($entry['id'], 'document_status');
        
        gf_docgen_custom_log('GF_Docgen_Generator: Job ID: ' . $job_id . ', Document status: ' . $document_status);
        
        // If no job ID yet, check if we just submitted the form (job might not be created yet)
        if (empty($job_id)) {
            // Generate a temporary job ID based on entry ID and show loading state
            $temp_job_id = 'temp_' . $entry['id'] . '_' . time();
            gf_docgen_custom_log('GF_Docgen_Generator: No job ID found for entry ' . $entry['id'] . ', using temporary ID: ' . $temp_job_id);
            $job_id = $temp_job_id;
            $show_temp_loading = true;
        } else {
            $show_temp_loading = false;
        }
        
        // Check if document is already ready
        $document_url = gform_get_meta($entry['id'], 'document_url');
        
        gf_docgen_custom_log('GF_Docgen_Generator: Document URL: ' . $document_url);
        
        if (!empty($document_url) && $document_status === 'completed') {
            // Document is ready - show download link
            $download_text = !empty($form_settings['download_link_text']) ? 
                $form_settings['download_link_text'] : 
                __('Download Your Document', 'gf-activemerge');
                
            $ready_title = !empty($form_settings['ready_title']) ? 
                $form_settings['ready_title'] : 
                __('Your document is ready!', 'gf-activemerge');
            
            // Extract the actual filename from the URL
            $filename = basename(wp_parse_url($document_url, PHP_URL_PATH));
            
            $download_link = sprintf(
                '<div class="gf-document-download-container" style="margin-top: 20px; text-align: center;">
                    <h3 style="color: #28a745;">%s</h3>
                    <a href="%s" class="gf-document-download-link" target="_blank" download="%s" style="display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">%s</a>
                </div>',
                esc_html($ready_title),
                esc_url($document_url),
                esc_attr($filename),
                esc_html($download_text)
            );
            
            $confirmation .= $download_link;
            
            gf_docgen_custom_log('GF_Docgen_Generator: Added completed download link to confirmation');
        } else {
            // Document is still being generated - show loading state
            $download_text = !empty($form_settings['download_link_text']) ? 
                $form_settings['download_link_text'] : 
                __('Download Your Document', 'gf-activemerge');
            
            $loading_html = sprintf(
                '<div id="gf-document-status" data-job-id="%s" data-entry-id="%s" data-temp-loading="%s" style="margin-top: 20px; text-align: center;">
                    <div class="gf-document-loading">
                        <h3>%s</h3>
                        <div class="gf-docgen-spinner"></div>
                        <p>%s</p>
                    </div>
                    <div id="gf-download-ready" style="display: none;">
                        <h3 style="color: #28a745;">%s</h3>
                        <a href="#" id="gf-document-download-link" class="gf-document-download-link" target="_blank" style="display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">%s</a>
                    </div>
                    <div id="gf-download-error" style="display: none; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">
                        <h3>%s</h3>
                        <p>%s</p>
                    </div>
                </div>',
                esc_attr($job_id),
                esc_attr($entry['id']),
                $show_temp_loading ? '1' : '0',
                esc_html(__('Generating Your Document', 'gf-activemerge')),
                esc_html(__('Please wait while we prepare your document. This page will update automatically when ready.', 'gf-activemerge')),
                esc_html(__('Your document is ready!', 'gf-activemerge')),
                esc_html($download_text),
                esc_html(__('Generation Failed', 'gf-activemerge')),
                esc_html(__('There was an error generating your document. Please contact support.', 'gf-activemerge'))
            );
            
            $confirmation .= $loading_html;
            
            gf_docgen_custom_log('GF_Docgen_Generator: Added loading state to confirmation');
        }
        
        gf_docgen_custom_log('GF_Docgen_Generator: Final confirmation length: ' . strlen($confirmation));
        
        return $confirmation;
    }
    
    /**
     * Try to get current entry ID from various sources
     * 
     * @return string|null Entry ID or null if not found
     */
    private function get_current_entry_id() {
        // Nonce verification for security
        if (!isset($_GET['gf_docgen_nonce']) || empty($_GET['gf_docgen_nonce'])) {
            return null;
        }
        $nonce = sanitize_text_field(wp_unslash($_GET['gf_docgen_nonce']));
        if (!wp_verify_nonce($nonce, 'gf_docgen_nonce')) {
            return null;
        }

        // Try to get from URL parameter
        if (!empty($_GET['entry_id'])) {
            return sanitize_text_field(wp_unslash($_GET['entry_id']));
        }

        // Try to get from Gravity Forms confirmation
        if (!empty($_GET['gf_entry_id'])) {
            return sanitize_text_field(wp_unslash($_GET['gf_entry_id']));
        }

        // Could also try session or other methods here
        return null;
    }
}