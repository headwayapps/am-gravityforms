<?php
/**
 * Form settings page for Gravity Forms Document Generator
 * 
 * This file is included by the admin class to render the form settings page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get form information
$form = GFAPI::get_form($form_id);
$settings = get_option('gf_docgen_form_' . $form_id, [
    'enabled' => '0',
    'template_id' => '',
    'output_format' => 'pdf',
    'custom_filename' => '',
    'send_email' => '0',
    'email_to' => '',
    'email_from' => '',
    'email_from_name' => '',
    'email_subject' => __('Your document is ready', 'gf-activemerge'),
    'email_message' => __('Please find your document attached.', 'gf-activemerge'),
    'email_attach_document' => '1',
    'attach_to_notifications' => '0',
    'notification_ids' => '',
    'show_download_link' => '1',
    'download_link_text' => __('Download Your Document', 'gf-activemerge'),
	'loading_title' => __('Generating Your Document', 'gf-activemerge'),
    'loading_message' => __('Please wait while we prepare your document. This page will update automatically when ready.', 'gf-activemerge'),
    'ready_title' => __('Your document is ready!', 'gf-activemerge'),
    'error_title' => __('Generation Failed', 'gf-activemerge'),
    'error_message' => __('There was an error generating your document. Please contact support.', 'gf-activemerge'),
    'field_mappings' => []
]);

// Process form submission
if (isset($_POST['gf_docgen_save_form_settings'])) {
    check_admin_referer('gf_docgen_form_settings');
    
    $settings['enabled'] = isset($_POST['gf_docgen_enabled']) ? '1' : '0';
    $settings['template_id'] = isset($_POST['gf_docgen_template_id']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_template_id'])) : '';
    $settings['output_format'] = isset($_POST['gf_docgen_output_format']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_output_format'])) : '';
    $settings['custom_filename'] = isset($_POST['gf_docgen_custom_filename']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_custom_filename'])) : '';
    $settings['send_email'] = isset($_POST['gf_docgen_send_email']) ? '1' : '0';
    $settings['email_to'] = isset($_POST['gf_docgen_email_to']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_email_to'])) : '';
    $settings['email_from'] = isset($_POST['gf_docgen_email_from']) ? sanitize_email(wp_unslash($_POST['gf_docgen_email_from'])) : '';
    $settings['email_from_name'] = isset($_POST['gf_docgen_email_from_name']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_email_from_name'])) : '';
    $settings['email_subject'] = isset($_POST['gf_docgen_email_subject']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_email_subject'])) : '';
    $settings['email_message'] = isset($_POST['gf_docgen_email_message']) ? wp_kses_post(wp_unslash($_POST['gf_docgen_email_message'])) : '';
    $settings['email_attach_document'] = isset($_POST['gf_docgen_email_attach_document']) ? '1' : '0';
    $settings['attach_to_notifications'] = isset($_POST['gf_docgen_attach_to_notifications']) ? '1' : '0';
    $settings['notification_ids'] = isset($_POST['gf_docgen_notification_ids']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_notification_ids'])) : '';
    $settings['show_download_link'] = isset($_POST['gf_docgen_show_download_link']) ? '1' : '0';
    $settings['download_link_text'] = isset($_POST['gf_docgen_download_link_text']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_download_link_text'])) : '';
	$settings['loading_title'] = isset($_POST['gf_docgen_loading_title']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_loading_title'])) : '';
	$settings['loading_message'] = isset($_POST['gf_docgen_loading_message']) ? sanitize_textarea_field(wp_unslash($_POST['gf_docgen_loading_message'])) : '';
	$settings['ready_title'] = isset($_POST['gf_docgen_ready_title']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_ready_title'])) : '';
	$settings['error_title'] = isset($_POST['gf_docgen_error_title']) ? sanitize_text_field(wp_unslash($_POST['gf_docgen_error_title'])) : '';
	$settings['error_message'] = isset($_POST['gf_docgen_error_message']) ? sanitize_textarea_field(wp_unslash($_POST['gf_docgen_error_message'])) : '';
    
    // Process field mappings
    $field_mappings = [];
    if (isset($_POST['gf_docgen_field_mapping']) && is_array($_POST['gf_docgen_field_mapping'])) {
        $raw_field_mapping = map_deep(wp_unslash($_POST['gf_docgen_field_mapping']), 'sanitize_text_field');
        foreach ($raw_field_mapping as $field_id => $placeholder) {
            if (!empty($placeholder)) {
                $field_mappings[sanitize_text_field($field_id)] = $placeholder;
            }
        }
    }
    $settings['field_mappings'] = $field_mappings;
    
    update_option('gf_docgen_form_' . $form_id, $settings);
    
    // Show success message
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Settings saved successfully.', 'gf-activemerge'); ?></p>
    </div>
    <?php
}

// Get form fields for mapping
$form_fields = [];
if (!empty($form['fields'])) {
    foreach ($form['fields'] as $field) {
        if (empty($field['inputs'])) {
            $form_fields[] = [
                'id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
            ];
        } else {
            foreach ($field['inputs'] as $input) {
                $form_fields[] = [
                    'id' => $input['id'],
                    'label' => $field['label'] . ' (' . $input['label'] . ')',
                    'type' => $field['type'] . ' input',
                ];
            }
        }
    }
}

// Add special system fields
$system_fields = [
    'form_title' => __('Form Title', 'gf-activemerge'),
    'form_id' => __('Form ID', 'gf-activemerge'),
    'entry_id' => __('Entry ID', 'gf-activemerge'),
    'date_created' => __('Date Created', 'gf-activemerge'),
    'user_ip' => __('User IP Address', 'gf-activemerge')
];

foreach ($system_fields as $key => $label) {
    $form_fields[] = [
        'id' => $key,
        'label' => $label,
        'type' => 'system field'
    ];
}

// Get form notifications
$notifications = rgar($form, 'notifications', []);
?>

<h3><?php esc_html_e('Document Generator Settings', 'gf-activemerge'); ?></h3>

<form method="post" action="">
    <?php wp_nonce_field('gf_docgen_form_settings'); ?>
    
    <div class="gform-settings-panel">
        <header class="gform-settings-panel__header">
            <h4 class="gform-settings-panel__title"><?php esc_html_e('Document Generation Settings', 'gf-activemerge'); ?></h4>
        </header>
        
        <div class="gform-settings-panel__content">
            <div class="gform-settings-field gform-settings-field__checkbox">
                <span class="gform-settings-input__container">
                    <input type="checkbox" name="gf_docgen_enabled" id="gf_docgen_enabled" value="1" 
                        <?php checked('1', $settings['enabled']); ?> />
                    <label for="gf_docgen_enabled" class="gform-settings-label">
                        <?php esc_html_e('Enable document generation for this form', 'gf-activemerge'); ?>
                    </label>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_template_id" class="gform-settings-label">
                    <?php esc_html_e('Template ID', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_template_id" id="gf_docgen_template_id" 
                        value="<?php echo esc_attr($settings['template_id']); ?>" class="regular-text" />
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Enter the template ID from your document generation service', 'gf-activemerge'); ?>
                    </div>
                    <button type="button" id="gf_docgen_fetch_placeholders" class="button button-secondary">
                        <?php esc_html_e('Fetch Template Placeholders', 'gf-activemerge'); ?>
                    </button>
                    <span id="gf_docgen_fetch_spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_output_format" class="gform-settings-label">
                    <?php esc_html_e('Output Format', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <select name="gf_docgen_output_format" id="gf_docgen_output_format">
                        <option value="pdf" <?php selected('pdf', $settings['output_format']); ?>>PDF</option>
                        <option value="docx" <?php selected('docx', $settings['output_format']); ?>>DOCX</option>
                        <option value="pptx" <?php selected('pptx', $settings['output_format']); ?>>PPTX</option>
                    </select>
                </span>
            </div>

            <div class="gform-settings-field">
                <label for="gf_docgen_custom_filename" class="gform-settings-label">
                    <?php esc_html_e('Custom Filename', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_custom_filename" id="gf_docgen_custom_filename" 
                        value="<?php echo esc_attr($settings['custom_filename']); ?>" class="regular-text" />
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Custom filename for the generated document. Leave blank to use default naming.', 'gf-activemerge'); ?><br>
                        <strong><?php esc_html_e('Note:', 'gf-activemerge'); ?></strong> <?php esc_html_e('Maximum filename length is 100 characters (excluding file extension). Longer filenames will be automatically truncated.', 'gf-activemerge'); ?><br><br>

                        <strong><?php esc_html_e('Available placeholders:', 'gf-activemerge'); ?></strong><br>
                        <div style="margin-top: 5px;">
                            <span class="gf-placeholder-tags">
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="date" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">date</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="time" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">time</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="datetime" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">datetime</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="entry_id" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">entry_id</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="form_id" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">form_id</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="form_title" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">form_title</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="site_title" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">site_title</code>
                                <code class="gf-placeholder-tag gf-filename-tag" data-tag="admin_email" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>">admin_email</code>

                                <?php if (!empty($form_fields)): ?>
                                    <?php foreach ($form_fields as $field): ?>
                                        <?php if (is_numeric($field['id'])): ?>
                                            <code class="gf-placeholder-tag gf-filename-tag" data-tag="<?php echo esc_attr($field['label']); ?>" title="<?php esc_attr_e('Click to insert into filename', 'gf-activemerge'); ?>"><?php echo esc_html($field['label']); ?></code>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </span>
                            <div style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;">
                                <?php esc_html_e('Click on any placeholder above to insert it into the filename field.', 'gf-activemerge'); ?>
                            </div>
                        </div>
                    </div>
                </span>
            </div>
        </div>
    </div>
    
    <div class="gform-settings-panel">
        <header class="gform-settings-panel__header">
            <h4 class="gform-settings-panel__title"><?php esc_html_e('Confirmation Page Settings', 'gf-activemerge'); ?></h4>
        </header>
        
        <div class="gform-settings-panel__content">
            <div class="gform-settings-field gform-settings-field__checkbox">
                <span class="gform-settings-input__container">
                    <input type="checkbox" name="gf_docgen_show_download_link" id="gf_docgen_show_download_link" value="1" 
                        <?php checked('1', $settings['show_download_link'] ?? '1'); ?> />
                    <label for="gf_docgen_show_download_link" class="gform-settings-label">
                        <?php esc_html_e('Show download link on confirmation page', 'gf-activemerge'); ?>
                    </label>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_download_link_text" class="gform-settings-label">
                    <?php esc_html_e('Download Link Text', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_download_link_text" id="gf_docgen_download_link_text" 
                        value="<?php echo esc_attr($settings['download_link_text'] ?? __('Download Your Document', 'gf-activemerge')); ?>" class="regular-text" />
                </span>
            </div>
			

					<div class="gform-settings-field">
						<label for="gf_docgen_loading_title" class="gform-settings-label">
							<?php esc_html_e('Loading Title', 'gf-activemerge'); ?>
						</label>
						<span class="gform-settings-input__container">
							<input type="text" name="gf_docgen_loading_title" id="gf_docgen_loading_title" 
								value="<?php echo esc_attr($settings['loading_title'] ?? __('Generating Your Document', 'gf-activemerge')); ?>" class="regular-text" />
						</span>
					</div>

					<div class="gform-settings-field">
						<label for="gf_docgen_loading_message" class="gform-settings-label">
							<?php esc_html_e('Loading Message', 'gf-activemerge'); ?>
						</label>
						<span class="gform-settings-input__container">
							<textarea name="gf_docgen_loading_message" id="gf_docgen_loading_message" 
								rows="3" class="large-text"><?php echo esc_textarea($settings['loading_message'] ?? __('Please wait while we prepare your document. This page will update automatically when ready.', 'gf-activemerge')); ?></textarea>
						</span>
					</div>

					<div class="gform-settings-field">
						<label for="gf_docgen_ready_title" class="gform-settings-label">
							<?php esc_html_e('Document Ready Title', 'gf-activemerge'); ?>
						</label>
						<span class="gform-settings-input__container">
							<input type="text" name="gf_docgen_ready_title" id="gf_docgen_ready_title" 
								value="<?php echo esc_attr($settings['ready_title'] ?? __('Your document is ready!', 'gf-activemerge')); ?>" class="regular-text" />
						</span>
					</div>

					<div class="gform-settings-field">
						<label for="gf_docgen_error_title" class="gform-settings-label">
							<?php esc_html_e('Error Title', 'gf-activemerge'); ?>
						</label>
						<span class="gform-settings-input__container">
							<input type="text" name="gf_docgen_error_title" id="gf_docgen_error_title" 
								value="<?php echo esc_attr($settings['error_title'] ?? __('Generation Failed', 'gf-activemerge')); ?>" class="regular-text" />
						</span>
					</div>

					<div class="gform-settings-field">
						<label for="gf_docgen_error_message" class="gform-settings-label">
							<?php esc_html_e('Error Message', 'gf-activemerge'); ?>
						</label>
						<span class="gform-settings-input__container">
							<textarea name="gf_docgen_error_message" id="gf_docgen_error_message" 
								rows="3" class="large-text"><?php echo esc_textarea($settings['error_message'] ?? __('There was an error generating your document. Please contact support.', 'gf-activemerge')); ?></textarea>
						</span>
					</div>
							</div>
						</div>
    
    <div class="gform-settings-panel">
        <header class="gform-settings-panel__header">
            <h4 class="gform-settings-panel__title"><?php esc_html_e('Email Settings', 'gf-activemerge'); ?></h4>
        </header>
        
        <div class="gform-settings-panel__content">
            <div class="gform-settings-field gform-settings-field__checkbox">
                <span class="gform-settings-input__container">
                    <input type="checkbox" name="gf_docgen_send_email" id="gf_docgen_send_email" value="1" 
                        <?php checked('1', $settings['send_email']); ?> />
                    <label for="gf_docgen_send_email" class="gform-settings-label">
                        <?php esc_html_e('Send document via email', 'gf-activemerge'); ?>
                    </label>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_email_to" class="gform-settings-label">
                    <?php esc_html_e('To', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_email_to" id="gf_docgen_email_to" 
                        value="<?php echo esc_attr($settings['email_to']); ?>" class="regular-text" />
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Enter recipient email or use merge tags like {admin_email} or {field_id}', 'gf-activemerge'); ?>
                    </div>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_email_from" class="gform-settings-label">
                    <?php esc_html_e('From Email', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_email_from" id="gf_docgen_email_from" 
                        value="<?php echo esc_attr($settings['email_from']); ?>" class="regular-text" />
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Leave blank to use the default WordPress email', 'gf-activemerge'); ?>
                    </div>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_email_from_name" class="gform-settings-label">
                    <?php esc_html_e('From Name', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_email_from_name" id="gf_docgen_email_from_name" 
                        value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text" />
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Leave blank to use the site name', 'gf-activemerge'); ?>
                    </div>
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_email_subject" class="gform-settings-label">
                    <?php esc_html_e('Subject', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <input type="text" name="gf_docgen_email_subject" id="gf_docgen_email_subject" 
                        value="<?php echo esc_attr($settings['email_subject']); ?>" class="regular-text" />
                </span>
            </div>
            
            <div class="gform-settings-field">
                <label for="gf_docgen_email_message" class="gform-settings-label">
                    <?php esc_html_e('Message', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <textarea name="gf_docgen_email_message" id="gf_docgen_email_message" 
                        rows="5" class="large-text"><?php echo esc_textarea($settings['email_message']); ?></textarea>
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('You can use merge tags like {form_title}, {entry_id}, {field_id}', 'gf-activemerge'); ?>
                    </div>
                </span>
            </div>
            
            <div class="gform-settings-field gform-settings-field__checkbox">
                <span class="gform-settings-input__container">
                    <input type="checkbox" name="gf_docgen_email_attach_document" id="gf_docgen_email_attach_document" value="1" 
                        <?php checked('1', $settings['email_attach_document']); ?> />
                    <label for="gf_docgen_email_attach_document" class="gform-settings-label">
                        <?php esc_html_e('Attach document to email', 'gf-activemerge'); ?>
                    </label>
                </span>
            </div>
        </div>
    </div>
    
    <div class="gform-settings-panel">
        <header class="gform-settings-panel__header">
            <h4 class="gform-settings-panel__title"><?php esc_html_e('Notification Settings', 'gf-activemerge'); ?></h4>
        </header>
        
        <div class="gform-settings-panel__content">
            <div class="gform-settings-field gform-settings-field__checkbox">
                <span class="gform-settings-input__container">
                    <input type="checkbox" name="gf_docgen_attach_to_notifications" id="gf_docgen_attach_to_notifications" value="1" 
                        <?php checked('1', $settings['attach_to_notifications']); ?> />
                    <label for="gf_docgen_attach_to_notifications" class="gform-settings-label">
                        <?php esc_html_e('Attach document to form notifications', 'gf-activemerge'); ?>
                    </label>
                </span>
            </div>
            
            <?php if (!empty($notifications)): ?>
            <div class="gform-settings-field">
                <label for="gf_docgen_notification_ids" class="gform-settings-label">
                    <?php esc_html_e('Select Notifications', 'gf-activemerge'); ?>
                </label>
                <span class="gform-settings-input__container">
                    <select name="gf_docgen_notification_ids" id="gf_docgen_notification_ids" multiple="multiple" class="regular-text">
                        <option value="" <?php selected(empty($settings['notification_ids'])); ?>><?php esc_html_e('All Notifications', 'gf-activemerge'); ?></option>
                        <?php foreach ($notifications as $notification): ?>
                        <option value="<?php echo esc_attr($notification['id']); ?>" 
                            <?php selected(in_array($notification['id'], explode(',', $settings['notification_ids']))); ?>>
                            <?php echo esc_html($notification['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="gform-settings-field__description">
                        <?php esc_html_e('Select which notifications should include the generated document as an attachment', 'gf-activemerge'); ?>
                    </div>
                </span>
            </div>
            <?php else: ?>
            <div class="gform-settings-field">
                <div class="notice notice-warning">
                    <p><?php esc_html_e('This form has no notifications configured.', 'gf-activemerge'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="gform-settings-panel">
        <header class="gform-settings-panel__header">
            <h4 class="gform-settings-panel__title"><?php esc_html_e('Field Mappings', 'gf-activemerge'); ?></h4>
        </header>
        
        <div class="gform-settings-panel__content">
            <div id="gf_docgen_placeholders_container" style="<?php echo empty($settings['template_id']) ? 'display:none;' : ''; ?>">
                <p><?php esc_html_e('Map form fields to template placeholders. If no mapping is provided, the field label will be used.', 'gf-activemerge'); ?></p>
                
                <table class="wp-list-table widefat fixed striped" id="gf_docgen_field_mapping_table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Form Field', 'gf-activemerge'); ?></th>
                            <th><?php esc_html_e('Template Placeholder', 'gf-activemerge'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($form_fields as $field): ?>
                        <tr>
                            <td><?php echo esc_html($field['label']); ?> (<?php echo esc_html($field['type']); ?>)</td>
                            <td>
                                <input type="text" name="gf_docgen_field_mapping[<?php echo esc_attr($field['id']); ?>]" 
                                    value="<?php echo esc_attr(isset($settings['field_mappings'][$field['id']]) ? $settings['field_mappings'][$field['id']] : ''); ?>" 
                                    class="regular-text field-mapping-input" />
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="gf_docgen_placeholders_empty" style="<?php echo !empty($settings['template_id']) ? 'display:none;' : ''; ?>">
                <p><?php esc_html_e('Enter a template ID and click "Fetch Template Placeholders" to map form fields to template placeholders.', 'gf-activemerge'); ?></p>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="gf_docgen_save_form_settings" value="1" />
    <input type="submit" name="submit" value="<?php esc_html_e('Save Settings', 'gf-activemerge'); ?>" class="button button-primary" />
</form>

