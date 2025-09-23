<?php
/**
 * Admin JavaScript for Gravity Forms Document Generator
 * 
 * This file outputs JavaScript code for admin functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function gf_docgen_admin_js() {
    ob_start();
    ?>
    jQuery(document).ready(function($) {
        // Test API button handler
        $('#test_api_button').on('click', function() {
            var button = $(this);
            var spinner = $('#test_api_spinner');
            var resultDiv = $('#test_api_result');
            var templateId = $('#test_template_id').val();
            var outputFormat = $('#test_output_format').val();
            
            if (!templateId) {
                resultDiv.removeClass('notice-success notice-error').addClass('notice notice-error');
                resultDiv.html('<p>Please enter a template ID.</p>').show();
                return;
            }
            
            // Show spinner and disable button
            spinner.addClass('is-active');
            button.prop('disabled', true);
            resultDiv.hide();
            
            // Send AJAX request
            $.post(ajaxurl, {
                action: 'gf_docgen_test_api',
                template_id: templateId,
                output_format: outputFormat,
                security: '<?php echo esc_html(wp_create_nonce('gf_docgen_admin')); ?>'
            }, function(response) {
                spinner.removeClass('is-active');
                button.prop('disabled', false);
                
                if (response.success) {
                    resultDiv.removeClass('notice-error').addClass('notice notice-success');
                    var message = '<p><strong>Success!</strong> ' + response.data.message + '</p>';
                    message += '<p>Response time: ' + response.data.response_time + ' seconds</p>';
                    if (response.data.document_url) {
                        message += '<p><a href="' + response.data.document_url + '" target="_blank" class="button button-secondary">View Test Document</a></p>';
                    }
                    resultDiv.html(message).show();
                } else {
                    resultDiv.removeClass('notice-success').addClass('notice notice-error');
                    var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred';
                    resultDiv.html('<p><strong>Error:</strong> ' + errorMessage + '</p>').show();
                }
            }).fail(function() {
                spinner.removeClass('is-active');
                button.prop('disabled', false);
                resultDiv.removeClass('notice-success').addClass('notice notice-error');
                resultDiv.html('<p><strong>Error:</strong> Failed to connect to the server.</p>').show();
            });
        });
        
        // Fetch placeholders button handler
        $('#gf_docgen_fetch_placeholders').on('click', function() {
            var button = $(this);
            var spinner = $('#gf_docgen_fetch_spinner');
            var templateId = $('#gf_docgen_template_id').val();
            var mappingTable = $('#gf_docgen_field_mapping_table');
            var placeholdersContainer = $('#gf_docgen_placeholders_container');
            var placeholdersEmpty = $('#gf_docgen_placeholders_empty');
            
            if (!templateId) {
                alert('Please enter a template ID first.');
                return;
            }
            
            // Show spinner and disable button
            spinner.addClass('is-active');
            button.prop('disabled', true);
            
            // Send AJAX request
            $.post(ajaxurl, {
                action: 'gf_docgen_fetch_placeholders',
                template_id: templateId,
                security: '<?php echo esc_html(wp_create_nonce('gf_docgen_admin')); ?>'
            }, function(response) {
                spinner.removeClass('is-active');
                button.prop('disabled', false);
                
                console.log('Placeholder response:', response);
                
                if (response.success && response.data) {
                    // Show the placeholders container
                    placeholdersContainer.show();
                    placeholdersEmpty.hide();
                    
                    // Get the placeholders
                    var placeholders = response.data;
                    
                    // Remove any existing placeholder info
                    $('#gf_docgen_placeholders_info').remove();
                    $('.placeholder-suggestion').remove();
                    
                    // Add a section showing all available placeholders
                    var placeholderInfoHtml = '<div id="gf_docgen_placeholders_info" class="notice notice-info" style="margin-bottom: 20px;">';
                    placeholderInfoHtml += '<h4 style="margin-top: 10px;">Available Template Placeholders (' + placeholders.length + ' found):</h4>';
                    placeholderInfoHtml += '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0;">';
                    
                    placeholders.forEach(function(placeholder) {
                        placeholderInfoHtml += '<span class="placeholder-tag" style="background: #e5e5e5; padding: 5px 10px; border-radius: 3px; cursor: pointer;" data-placeholder="' + placeholder + '">';
                        placeholderInfoHtml += '<strong>' + placeholder + '</strong>';
                        placeholderInfoHtml += '</span>';
                    });
                    
                    placeholderInfoHtml += '</div>';
                    placeholderInfoHtml += '<p style="margin-bottom: 5px;"><em>Click on a placeholder to copy it, or type it in the field mapping below.</em></p>';
                    placeholderInfoHtml += '</div>';
                    
                    // Insert the placeholder info before the mapping table
                    mappingTable.before(placeholderInfoHtml);
                    
                    // Add click handler for placeholder tags
                    $('.placeholder-tag').on('click', function() {
                        var placeholder = $(this).data('placeholder');
                        
                        // Copy to clipboard
                        var tempInput = $('<input>');
                        $('body').append(tempInput);
                        tempInput.val(placeholder).select();
                        document.execCommand('copy');
                        tempInput.remove();
                        
                        // Visual feedback
                        var originalText = $(this).html();
                        $(this).html('<strong>Copied!</strong>');
                        var $this = $(this);
                        setTimeout(function() {
                            $this.html(originalText);
                        }, 1000);
                    });
                    
                    // Add placeholder suggestions to each row
                    mappingTable.find('tbody tr').each(function() {
                        var $row = $(this);
                        var $input = $row.find('.field-mapping-input');
                        
                        // Add a suggestion div after the input
                        var suggestionHtml = '<div class="placeholder-suggestion" style="margin-top: 5px; font-size: 12px; color: #666;">';
                        suggestionHtml += 'Suggestions: ';
                        
                        // Get the field label from the first column
                        var fieldLabel = $row.find('td:first').text();
                        var cleanLabel = fieldLabel.replace(/\s*\([^)]*\)\s*/g, '').trim();
                        
                        // Find matching placeholders (case-insensitive)
                        var matches = placeholders.filter(function(p) {
                            return p.toLowerCase().indexOf(cleanLabel.toLowerCase()) !== -1 || 
                                   cleanLabel.toLowerCase().indexOf(p.toLowerCase()) !== -1;
                        });
                        
                        if (matches.length > 0) {
                            matches.forEach(function(match, index) {
                                if (index > 0) suggestionHtml += ', ';
                                suggestionHtml += '<a href="#" class="placeholder-suggestion-link" data-placeholder="' + match + '" style="text-decoration: none; color: #0073aa;">' + match + '</a>';
                            });
                        } else {
                            suggestionHtml += '<em>No automatic matches found</em>';
                        }
                        
                        suggestionHtml += '</div>';
                        $input.after(suggestionHtml);
                    });
                    
                    // Add click handlers for suggestion links
                    $('.placeholder-suggestion-link').on('click', function(e) {
                        e.preventDefault();
                        var placeholder = $(this).data('placeholder');
                        var $input = $(this).closest('td').find('.field-mapping-input');
                        $input.val(placeholder);
                        
                        // Visual feedback
                        $input.css('background-color', '#e8f5e9');
                        setTimeout(function() {
                            $input.css('background-color', '');
                        }, 500);
                    });
                    
                    // Add autocomplete to all field mapping inputs
                    $('.field-mapping-input').each(function() {
                        var input = $(this);
                        
                        // Remove any existing autocomplete
                        if (input.hasClass('ui-autocomplete-input')) {
                            input.autocomplete('destroy');
                        }
                        
                        // Add new autocomplete
                        input.autocomplete({
                            source: placeholders,
                            minLength: 0,
                            delay: 0
                        }).on('focus', function() {
                            $(this).autocomplete('search', '');
                        });
                    });
                    
                    // Scroll to the placeholders info if it exists
                    if ($('#gf_docgen_placeholders_info').length > 0) {
                        $('html, body').animate({
                            scrollTop: $('#gf_docgen_placeholders_info').offset().top - 50
                        }, 500);
                    }
                    
                } else {
                    alert('Failed to fetch placeholders. Please check your template ID and API key.');
                    console.error('Placeholder fetch failed:', response);
                }
            }).fail(function(xhr, status, error) {
                spinner.removeClass('is-active');
                button.prop('disabled', false);
                alert('Failed to connect to the server. Error: ' + error);
                console.error('AJAX error:', status, error);
            });
        });
        
        // Auto-show field mappings if template ID is already filled
        if ($('#gf_docgen_template_id').val()) {
            $('#gf_docgen_placeholders_container').show();
            $('#gf_docgen_placeholders_empty').hide();
        }
        
        // Handle multiple select for notifications
        $('#gf_docgen_notification_ids').on('change', function() {
            var selected = $(this).val();
            if (selected && selected.includes('') && selected.length > 1) {
                // If "All Notifications" is selected along with others, just keep "All"
                $(this).val(['']);
            }
        });
        
        // Toggle email fields visibility based on send_email checkbox
        $('#gf_docgen_send_email').on('change', function() {
            var emailFields = $('#gf_docgen_email_to, #gf_docgen_email_from, #gf_docgen_email_from_name, #gf_docgen_email_subject, #gf_docgen_email_message, #gf_docgen_email_attach_document')
                .closest('.gform-settings-field');
            
            if ($(this).is(':checked')) {
                emailFields.show();
            } else {
                emailFields.hide();
            }
        }).trigger('change');
        
        // Toggle notification fields visibility
        $('#gf_docgen_attach_to_notifications').on('change', function() {
            var notificationField = $('#gf_docgen_notification_ids').closest('.gform-settings-field');
            
            if ($(this).is(':checked')) {
                notificationField.show();
            } else {
                notificationField.hide();
            }
        }).trigger('change');
        
        // Handle filename placeholder tag clicks
        $('.gf-filename-tag').on('click', function() {
            var tag = $(this).data('tag');
            var filenameField = $('#gf_docgen_custom_filename');
            var currentValue = filenameField.val();
            var placeholder = '{' + tag + '}';
            
            // Insert the placeholder at the cursor position or append to the end
            var cursorPos = filenameField[0].selectionStart || currentValue.length;
            var newValue = currentValue.substring(0, cursorPos) + placeholder + currentValue.substring(cursorPos);
            
            filenameField.val(newValue);
            
            // Set focus back to the filename field and position cursor after the inserted placeholder
            filenameField.focus();
            var newCursorPos = cursorPos + placeholder.length;
            filenameField[0].setSelectionRange(newCursorPos, newCursorPos);
            
            // Visual feedback - briefly highlight the tag
            $(this).css('background-color', '#e8f5e9');
            setTimeout(function() {
                $(this).css('background-color', '');
            }.bind(this), 500);
        });
    });
    <?php
    return ob_get_clean();
}
?>