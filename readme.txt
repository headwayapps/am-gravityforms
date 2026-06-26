=== GF ActiveMerge Document Generator ===
Contributors: headwayapps, ActiveMerge
Tags: gravity forms, pdf, docx, document generation
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate documents from Gravity Forms submissions using the ActiveMerge document generation service.

== Description ==

GF ActiveMerge Document Generator is a WordPress plugin that allows you to generate documents from Gravity Forms submissions. It integrates with the ActiveMerge https://activemerge.com document generation service to create PDF, DOCX, or PPTX documents with data from your form submissions.

= Features =

* Generate PDF, DOCX, or PPTX documents from Gravity Forms submissions
* Map form fields to template placeholders
* Send generated documents via email
* Attach documents to Gravity Forms notifications
* Test API connection before setting up forms
* Debug mode for troubleshooting

= Requirements =

* WordPress 5.0 or higher
* Gravity Forms 2.5 or higher
* PHP 7.4 or higher
* An ActiveMerge account (or compatible document generation service)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gravity-forms-document-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Document Generator to configure the API settings
4. Edit a Gravity Form and go to the Document Generator tab to configure document generation settings

== Frequently Asked Questions ==

= What document formats are supported? =

The plugin supports PDF, DOCX, and PPTX formats.

= Do I need an API key? =

Yes, you need an API key from ActiveMerge or a compatible document generation service.

= How do I map form fields to template placeholders? =

Edit a form, go to the Document Generator tab, enter your template ID, and click "Fetch Template Placeholders". Then you can map your form fields to template placeholders.

== Screenshots ==

1. Main settings page
2. Form settings page
3. Field mapping
4. Entry detail with document link

== External Services ==

This plugin connects to the ActiveMerge document generation API to generate PDF, DOCX, and PPTX documents from Gravity Forms submissions.

= What data is sent and when =

* **Template placeholders:** When configuring a form, the plugin sends a request to retrieve available template placeholders from your ActiveMerge account.
* **Document generation:** When a Gravity Forms submission occurs (and document generation is enabled for that form), the plugin sends the mapped form field data along with the template ID and desired output format to the ActiveMerge API to generate a document.
* **User credits:** The plugin may request your account credit balance from the ActiveMerge API.

All API requests include your ActiveMerge API key for authentication.

= Service details =

This service is provided by ActiveMerge.

* Service website: [https://activemerge.com](https://activemerge.com)
* Terms of Service: [https://activemerge.com/terms-conditions/](https://activemerge.com/terms-conditions/)
* Privacy Policy: [https://activemerge.com/privacy-policy/](https://activemerge.com/privacy-policy/)

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release
