<?php
/**
 * Main settings page for Gravity Forms Document Generator
 * 
 * This file is included by the admin class to render the main settings page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Gravity Forms Document Generator Settings', 'gf-activemerge'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('gf_docgen_settings'); ?>
        <?php do_settings_sections('gf_docgen_settings'); ?>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('API Key', 'gf-activemerge'); ?></th>
                <td>
                    <input type="text" name="gf_docgen_api_key" class="regular-text" 
                           value="<?php echo esc_attr(get_option('gf_docgen_api_key', '')); ?>" />
                    <p class="description"><?php echo esc_html__('Your ActiveMerge API key for authentication', 'gf-activemerge'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Debug Mode', 'gf-activemerge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="gf_docgen_debug_mode" value="1" 
                               <?php checked('1', get_option('gf_docgen_debug_mode', '0')); ?> />
                        <?php echo esc_html__('Enable debug logging', 'gf-activemerge'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('Log API requests and responses to the error log', 'gf-activemerge'); ?></p>
                </td>
            </tr>
        </table>
        
        <div class="api-test-container" style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
            <h3><?php echo esc_html__('Test API Connection', 'gf-activemerge'); ?></h3>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Template ID', 'gf-activemerge'); ?></th>
                    <td>
                        <input type="text" id="test_template_id" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Enter a template ID to test the API connection', 'gf-activemerge'); ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Output Format', 'gf-activemerge'); ?></th>
                    <td>
                        <select id="test_output_format">
                            <option value="pdf">PDF</option>
                            <option value="docx">DOCX</option>
                            <option value="pptx">PPTX</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="button" id="test_api_button" class="button button-secondary">
                    <?php echo esc_html__('Test API Connection', 'gf-activemerge'); ?>
                </button>
                <span id="test_api_spinner" class="spinner" style="float: none; margin-top: 0;"></span>
            </p>
            
            <div id="test_api_result" style="margin-top: 15px; display: none; padding: 10px; border-radius: 4px;"></div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>