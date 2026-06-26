<?php
/**
 * API Handler for Gravity Forms Document Generator
 * 
 * Handles all API communication with the ActiveMerge document generation service
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GF_Docgen_API_Handler {
    private $api_base_url;
    private $api_generation_endpoint;
    private $api_key;
    private $debug_mode;
    
    public function __construct() {
        // Fixed API endpoint - no need to make it configurable
        $this->api_generation_endpoint = 'https://app.activemerge.com/api/document-generation/generate';
        $this->api_base_url = 'https://app.activemerge.com/api';
        
        // Get settings
        $this->api_key = get_option('gf_docgen_api_key', '');
        $this->debug_mode = get_option('gf_docgen_debug_mode', '0');
        
        
        // Debug log the API key (partially masked for security)
        if (!empty($this->api_key)) {
            $masked_key = substr($this->api_key, 0, 4) . '...' . substr($this->api_key, -4);
            $this->log_message('Using API key: ' . $masked_key);
        } else {
            $this->log_message('No API key configured');
        }
        
        $this->log_message('API Base URL (for placeholders): ' . $this->api_base_url);
        $this->log_message('Document Generation Endpoint: ' . $this->api_generation_endpoint);
    }
    
    /**
     * Generate a document using the ActiveMerge API
     */
    public function generate_document($template_id, $form_data, $output_format = 'pdf', $custom_filename = '') {

        // Prepare data for the API
        $api_data = $this->prepare_api_data($template_id, $form_data, $output_format);
        
        // Send request to document generation API
        return $this->generate_and_save_document($api_data, $output_format, $custom_filename);
    }
    
    /**
     * Test the API connection
     */
    public function test_api_connection($template_id, $test_data, $output_format = 'pdf') {
        $this->log_message('Testing API connection');
        
        // Enable debug mode for the test
        $original_debug = $this->debug_mode;
        $this->debug_mode = '1';
        
        // Prepare data for the API
        $api_data = $this->prepare_api_data($template_id, $test_data, $output_format);
        
        // Send request to document generation API
        $start_time = microtime(true);
        $document_path = $this->generate_and_save_document($api_data, $output_format, 'test_document');
        $response_time = microtime(true) - $start_time;
        
        // Restore original debug mode
        $this->debug_mode = $original_debug;
        
        if (!$document_path) {
            return [
                'success' => false,
                'message' => 'API request failed or no document was generated',
                'response_time' => round($response_time, 2)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'API connection successful',
            'document_path' => $document_path,
            'document_url' => site_url('wp-content/uploads/gf-documents/' . basename($document_path)),
            'response_time' => round($response_time, 2)
        ];
    }
    
    /**
     * Fetch placeholders from a template using a separate API endpoint
     */
    public function fetch_placeholders($template_id) {
        
        // Build the API URL for placeholders - note this uses a different base URL
        $placeholders_url = $this->api_base_url . '/templates/' . $template_id . '/placeholders';
      
        
        // Send request to placeholders API
        return $this->get_placeholders_from_api($placeholders_url);
    }
    
    /**
     * Get authentication headers for API requests
     */
    private function get_auth_headers() {
        $headers = [];
        
        // Check if we have an API key
        if (!empty($this->api_key)) {
            // Use API-KEY header exactly as shown in the API documentation
            $headers['API-KEY'] = $this->api_key;
            $this->log_message('Using API key authentication with API-KEY header');
        } else {
            $this->log_message('Warning: No API key configured');
        }
        
        return $headers;
    }
    
    /**
     * Make request to placeholders API endpoint
     */
    private function get_placeholders_from_api($placeholders_url) {
        // Set up request arguments
        $args = [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ];
        
        // Add authentication headers
        $auth_headers = $this->get_auth_headers();
        $args['headers'] = array_merge($args['headers'], $auth_headers);
        
        // Send the request
        $this->log_message('Sending placeholders API request to: ' . $placeholders_url);
        $response = wp_remote_get($placeholders_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_message('Error fetching placeholders: ' . $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->log_message('Placeholders API returned non-200 status code: ' . $response_code);
            $this->log_message('Placeholders API response body: ' . $response_body);
            return false;
        }
        
        // Parse the response
        $placeholders = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_message('Error parsing placeholders JSON response: ' . json_last_error_msg());
            return false;
        }
        
        // Check if we got a valid response
        if (!is_array($placeholders)) {
            $this->log_message('Invalid placeholders response: ' . $response_body);
            return false;
        }
        
        $this->log_message('Successfully fetched ' . count($placeholders) . ' placeholders');
        return $placeholders;
    }
    
    /**
     * Prepare data for the API request
     */
    private function prepare_api_data($template_id, $form_data, $output_format) {
        // Process form data for API
        $data_fields = [];
        
        foreach ($form_data as $field_name => $field_value) {
            // Handle arrays (checkboxes, etc.)
            if (is_array($field_value)) {
                $field_value = implode(', ', $field_value);
            }
            
            // Add to data fields
            $data_fields[$field_name] = $field_value;
        }
        
        // Create the API data structure exactly as shown in the API documentation
        $api_data = [
            'template_id' => $template_id,
            'data' => $data_fields,
            'format' => $output_format
        ];
        
        $this->log_message('Prepared API data: ' . json_encode($api_data));
        
        return $api_data;
    }
    
    /**
     * Send request to document generation API and save the document
     */
    private function generate_and_save_document($api_data, $output_format, $custom_filename = '') {
        $this->log_message('=== STARTING DOCUMENT GENERATION PROCESS ===');
        $this->log_message('Preparing API request arguments');
        
        // Set up request arguments - exactly matching the API documentation curl example
        $args = [
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($api_data),
            'cookies' => []
        ];
        
        $this->log_message('Adding authentication headers');
        
        // Add authentication headers
        $auth_headers = $this->get_auth_headers();
        $args['headers'] = array_merge($args['headers'], $auth_headers);
        
        $this->log_message('Sending POST request to API endpoint: ' . $this->api_generation_endpoint);
        $this->log_message('Request body size: ' . strlen($args['body']) . ' bytes');
        
        // Send the request
        $response = wp_remote_post($this->api_generation_endpoint, $args);
        
        $this->log_message('Checking API response for errors');
        
        // Check for errors
        if (is_wp_error($response)) {
            $this->log_message('ERROR - Document generation API request error: ' . $response->get_error_message());
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        $this->log_message('Processing API response (Status Code: ' . $response_code . ')');
        
        // Get response body
        $response_body = wp_remote_retrieve_body($response);
        
        if (empty($response_body)) {
            $this->log_message('ERROR - Document generation API returned empty response body');
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('Response body size: ' . strlen($response_body) . ' bytes');
        
        // Log first 500 characters of response for debugging (to avoid logging huge responses)
        $this->log_message('API response preview: ' . substr($response_body, 0, 500));
        
        $this->log_message('Parsing JSON response from API');
        
        // Parse JSON response as per API documentation
        $json_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_message('ERROR - Error parsing JSON response: ' . json_last_error_msg());
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('JSON parsed successfully, checking response codes and content');
        
        // Handle different response codes
        if ($response_code === 422) {
            // Validation error
            $this->log_message('ERROR - Validation error (422) received from API');
            $error_message = isset($json_data['message']) ? $json_data['message'] : 'Validation error';
            if (isset($json_data['errors'])) {
                $error_details = [];
                foreach ($json_data['errors'] as $field => $errors) {
                    $error_details[] = $field . ': ' . implode(', ', (array)$errors);
                }
                $error_message .= ' - ' . implode('; ', $error_details);
            }
            $this->log_message('Validation error details: ' . $error_message);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        } elseif ($response_code === 500) {
            // Server error
            $this->log_message('ERROR - Server error (500) received from API');
            $error_message = isset($json_data['message']) ? $json_data['message'] : 'Server error';
            $this->log_message('Server error details: ' . $error_message);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        } elseif ($response_code !== 200) {
            // Other error
            $this->log_message('ERROR - Unexpected response code: ' . $response_code);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('Validating successful API response (200 OK)');
        
        // Check for error in response (legacy error handling)
        if (isset($json_data['error'])) {
            $this->log_message('ERROR - Document generation API returned error: ' . $json_data['error']);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        // According to API docs, successful response contains a 'url' field
        if (!isset($json_data['url'])) {
            $this->log_message('ERROR - API response does not contain url field');
            $this->log_message('JSON Response: ' . json_encode($json_data));
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $document_url = $json_data['url'];
        $this->log_message('Document URL found in response: ' . $document_url);
        
        // Validate URL
        if (!filter_var($document_url, FILTER_VALIDATE_URL)) {
            $this->log_message('ERROR - Invalid document URL received: ' . $document_url);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('Document URL is valid, proceeding with download');
        
        $this->log_message('Downloading document from URL');
        
        // Download the document from the provided URL
        $download_response = wp_remote_get($document_url, [
            'timeout' => 60,
            'sslverify' => true
        ]);
        
        if (is_wp_error($download_response)) {
            $this->log_message('ERROR - Failed to download document: ' . $download_response->get_error_message());
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $download_code = wp_remote_retrieve_response_code($download_response);
        $this->log_message('Download response code: ' . $download_code);
        
        if ($download_code !== 200) {
            $this->log_message('ERROR - Download failed with status code: ' . $download_code);
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $document_content = wp_remote_retrieve_body($download_response);
        
        if (empty($document_content)) {
            $this->log_message('ERROR - Downloaded document is empty');
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('Document downloaded successfully, size: ' . strlen($document_content) . ' bytes');
        
        $this->log_message('Determining file extension from content-type');
        
        // Get extension from content-type if available
        $download_content_type = wp_remote_retrieve_header($download_response, 'content-type');
        $extension = $output_format; // Default to requested format
        
        $this->log_message('Content-Type header: ' . ($download_content_type ?: 'not provided'));
        $this->log_message('Requested output format: ' . $output_format);
        
        if ($download_content_type) {
            if (strpos($download_content_type, 'application/pdf') !== false) {
                $extension = 'pdf';
            } elseif (strpos($download_content_type, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') !== false) {
                $extension = 'docx';
            } elseif (strpos($download_content_type, 'application/vnd.openxmlformats-officedocument.presentationml.presentation') !== false) {
                $extension = 'pptx';
            }
        }
        
        $this->log_message('Final file extension determined: ' . $extension);
        
        $this->log_message('Creating documents directory structure');
        
        // Create documents directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $document_dir = $upload_dir['basedir'] . '/gf-documents/';
        
        $this->log_message('Target document directory: ' . $document_dir);
        
        if (!file_exists($document_dir)) {
            $this->log_message('Directory does not exist, creating it...');
            if (!wp_mkdir_p($document_dir)) {
                $this->log_message('ERROR - Failed to create document directory: ' . $document_dir);
                $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
                return false;
            }
            $this->log_message('Directory created successfully');
        } else {
            $this->log_message('Directory already exists');
        }
        
        $this->log_message('Setting up directory security (.htaccess)');
        
        // Add .htaccess to protect documents directory
        $htaccess_file = $document_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $this->log_message('Creating .htaccess file for directory security');
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch \"\\.(pdf|docx|pptx)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            $htaccess_result = @file_put_contents($htaccess_file, $htaccess_content);
            if ($htaccess_result === false) {
                $this->log_message('WARNING - Failed to create .htaccess file, but continuing with document save');
            } else {
                $this->log_message('.htaccess file created successfully');
            }
        } else {
            $this->log_message('.htaccess file already exists');
        }
        
        $this->log_message('Generating filename and saving document');
        
        // Generate filename - use custom filename if provided, otherwise use default
        if (!empty($custom_filename)) {
            $document_name = $custom_filename . '.' . $extension;
            $this->log_message('Using custom filename: ' . $custom_filename);
        } else {
            $document_name = 'document_' . gmdate('Y-m-d_His') . '_' . uniqid() . '.' . $extension;
            $this->log_message('Using default filename pattern');
        }
        
        // Ensure filename is unique to avoid conflicts
        $original_document_name = $document_name;
        $counter = 1;
        while (file_exists($document_dir . $document_name)) {
            $name_without_ext = pathinfo($original_document_name, PATHINFO_FILENAME);
            $document_name = $name_without_ext . '_' . $counter . '.' . $extension;
            $counter++;
        }
        
        $document_path = $document_dir . $document_name;
        
        $this->log_message('Generated filename: ' . $document_name);
        $this->log_message('Full document path: ' . $document_path);
        
        // Save the document
        $result = file_put_contents($document_path, $document_content);
        
        if ($result === false) {
            $this->log_message('ERROR - Failed to save document to: ' . $document_path);
            $this->log_message('Check directory permissions and available disk space');
            $this->log_message('=== DOCUMENT GENERATION PROCESS FAILED ===');
            return false;
        }
        
        $this->log_message('Document save successful!');
        $this->log_message('Document saved successfully: ' . $document_path . ' (' . $result . ' bytes)');
        $this->log_message('=== DOCUMENT GENERATION PROCESS COMPLETED SUCCESSFULLY ===');
        
        // Return the path to the saved document
        return $document_path;
    }
    
    /**
     * Get user credits
     */
    public function get_user_credits() {
        $this->log_message('Fetching user credits');
        
        $credits_url = $this->api_base_url . '/user/credits';
        
        // Set up request arguments
        $args = [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ];
        
        // Add authentication headers
        $auth_headers = $this->get_auth_headers();
        $args['headers'] = array_merge($args['headers'], $auth_headers);
        
        // Send the request
        $response = wp_remote_get($credits_url, $args);
        
        if (is_wp_error($response)) {
            $this->log_message('Error fetching credits: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->log_message('Credits API returned non-200 status code: ' . $response_code);
            return false;
        }
        
        $credits_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_message('Error parsing credits JSON response: ' . json_last_error_msg());
            return false;
        }
        
        $this->log_message('Successfully fetched credits: ' . json_encode($credits_data));
        return $credits_data;
    }
    
    /**
     * Log messages for debugging
     */
    private function log_message($message) {
        // Always log if debug mode is enabled
        if ($this->debug_mode === '1') {
            gf_docgen_custom_log('GF Document Generator API: ' . $message);
        }
    }
}