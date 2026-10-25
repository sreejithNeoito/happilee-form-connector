=== Happilee Forms Connector ===
Contributors: happilee
Author: happilee
Author URI: https://neoito.com
Donate link: https://happilee.io/pricing
Tags: happilee, whatsapp, chatbot, contact form, api integration
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.0.10
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress contact forms to Happilee WhatsApp chatbot platform via API integration.

== Description ==

Happilee Forms Connector seamlessly integrates your WordPress contact forms with the Happilee WhatsApp chatbot platform. As a Meta Business Partner, Happilee provides powerful WhatsApp business solutions, and this plugin bridges your form submissions directly to the Happilee API.

**Supported Form Plugins:**

* Contact Form 7 (CF7)
* WPForms
* Ninja Forms
* Forminator

**Key Features:**

* Easy API connection setup with Happilee platform
* Automatic form submission sync to WhatsApp
* Support for multiple popular WordPress form plugins
* Secure API authentication with encrypted storage
* Real-time data transmission
* Simple configuration interface
* WhatsApp template message support with dynamic parameter mapping

**About Happilee:**

Happilee is a Meta Business Partner providing WhatsApp chatbot services that help businesses automate customer communication, lead generation, and support through WhatsApp Business API.

**How It Works:**

1. Install and activate the plugin
2. Configure your Happilee API credentials
3. Enable the forms you want to connect and open their settings
4. Choose the trigger hook, the phone number field and a WhatsApp message template from your Happilee account (only approved templates whose name starts with `wp_` are listed)
5. Map the template's dynamic parameters (e.g. {{customer_name}}) to your form fields
6. Form submissions automatically sync to Happilee and trigger the selected WhatsApp template message

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/happilee-forms-connector` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Settings → Happilee Forms Connector to configure the plugin.
4. In the API Configuration section, enter your Happilee API key (available from your Happilee dashboard).
5. Save your settings and test the connection.

**Requirements:**

* Active Happilee account (sign up at https://happilee.io)
* Happilee API key
* One or more supported form plugins installed (CF7, WPForms, Ninja Forms, or Forminator)

== Frequently Asked Questions ==

= What is Happilee? =

Happilee is a Meta Business Partner that provides WhatsApp chatbot services for businesses to automate customer communication through WhatsApp Business API.

= Which form plugins are supported? =

Currently, the plugin supports Contact Form 7, WPForms, Ninja Forms, and Forminator.

= Do I need a Happilee account? =

Yes, you need an active Happilee account and API key. Visit https://happilee.io to create an account.

= Where do I find my Happilee API key? =

Log in to your Happilee dashboard and navigate to Project → Settings → Integrations to find your API key.

= Is the data transmission secure? =

Yes, all data is transmitted securely via HTTPS. API keys are encrypted using AES-256-CBC encryption before being stored in the database.

= Can I connect multiple forms? =

Yes, you can connect as many forms as you need from any of the supported form plugins.

= Does this work with custom forms? =

Currently, the plugin supports the four major form plugins listed. Custom form support may be added in future updates.

= What data is sent to Happilee? =

The plugin sends the Mobile field selected in the form settings together with the visitor's country calling code, the form's ID and name, and a lead source in the format "{form name}-Happilee web", plus the form fields mapped to the selected WhatsApp template's parameters. Contact fields mapped in earlier versions of the plugin (First Name, Last Name, Birthday and Tags) continue to be sent. Only mapped fields are sent, and the Mobile field is mandatory.

= How do I verify the plugin is working? =

After configuring your API key and connecting a form, submit a test form. You should see the submission data in your Happilee dashboard.

= Can I send WhatsApp template messages when a form is submitted? =

Yes. After connecting a form, you can select a WhatsApp message template from your Happilee account and map its dynamic parameters (such as {{customer_name}} or {{order_id}}) to the corresponding form fields. When the form is submitted, the plugin automatically sends the template message to the contact's phone number with the mapped values filled in.

= Why is my template not listed? =

Only approved WhatsApp templates whose name starts with `wp_` (for example `wp_order_confirmation`) are shown in the form settings. Rename or create the template with the `wp_` prefix in your Happilee account and wait for it to be approved.

= What are template parameters? =

Template parameters are dynamic placeholders in your WhatsApp message template, written as {{param_name}}. For example, a delivery template might contain {{pickup_time}} and {{customer_address}}. The plugin lets you map each placeholder to a specific form field so the correct submitted value is inserted when the message is sent.

= What happens if a template has no parameters? =

Templates without dynamic parameters are fully supported. The plugin will send the template message as-is without requiring any field mapping.

== External Services ==

This plugin connects to the following external services:

**1. Happilee API (happilee.io)**

This plugin communicates with the Happilee WhatsApp chatbot platform API to:

* Validate your API key and retrieve your project details — sent once when you save your API credentials in the settings page.
* Forward form submission data — sent every time a visitor submits a connected WordPress form.
* Fetch available WhatsApp templates — sent once when you configure a form connection.
* Send WhatsApp template messages — sent every time a connected form is submitted with a template selected.

Data sent to Happilee includes: your API key (for authentication), the visitor's mobile number and country calling code, the form ID, form name and lead source (e.g. "Contact Us-Happilee web"), the submitted form field values mapped to the selected template's parameters, and the selected template ID. Forms configured in earlier versions of the plugin also send their mapped First Name, Last Name, Birthday and Tags fields.

Data is transmitted to the following Happilee API endpoints:
* `https://api.happilee.io/api/v1/getProjectDetails` — API key validation
* `https://api.happilee.io/api/v1/createContact` — form submission forwarding
* `https://api.happilee.io/api/v1/getTemplateMessages` — fetching available WhatsApp templates
* `https://api.happilee.io/api/v1/sendMessage` — sending WhatsApp template messages on form submission

This service is provided by Happilee (a Meta Business Partner):
* Website: https://happilee.io
* Terms of Service: https://happilee.io/terms-of-use/
* Privacy Policy: https://happilee.io/privacy-policy/

No data is sent to Happilee unless a valid API key has been configured and at least one form has been enabled in the plugin settings.

**2. ipapi.co — Country code lookup**

When a connected form is submitted, the plugin detects the visitor's country calling code by sending the visitor's IP address to ipapi.co. Forms configured in earlier versions of the plugin with a Country Code field mapping use the submitted value instead, and only fall back to this lookup when that value is empty.

Data sent: the visitor's IP address.
When: on submission of a connected form, at most once per submission, unless the country code is already provided by the form.
Why: to resolve the correct international phone dialling prefix (e.g. `+44`, `+91`) before forwarding the submission to Happilee.

The request is made to:
`https://ipapi.co/{visitor_ip}/country_calling_code/`

This service is provided by ipapi.co:
* Website: https://ipapi.co
* Terms of Service: https://ipapi.co/terms/
* Privacy Policy: https://ipapi.co/privacy/

== Screenshots ==

1. Plugin settings page with API configuration
2. List of available forms
3. Form settings with template selection, parameter mapping and live WhatsApp preview

== Changelog ==

= 1.0.10 =
* Changed: Hook, field mapping, template selection and parameter mapping are now configured in a single modal with a live WhatsApp preview
* Changed: Only approved templates whose name starts with "wp_" are listed for selection
* Added: Form ID, form name and lead source ("{form name}-Happilee web") are sent with each contact
* Fixed: Saved settings now take effect immediately on sites with a persistent object cache
* Fixed: Forms from different form plugins that share the same ID no longer share settings
* Fixed: WPForms Name fields are now split correctly into first and last name
* Fixed: Plugin data is now removed from every site on multisite uninstall
* Fixed: Contact Form 7 submissions marked as spam or failing validation no longer trigger a WhatsApp message
* Improved: Security enhancements and WordPress coding standards compliance

= 1.0.9 =
* Changed: Tested up to WordPress 7.1
* Changed: Tags field mapping is now a free-text input (comma-separated) instead of a dropdown tied to a submitted form field

= 1.0.8 =
* Changed: Tested up to WordPress 7.0.1

= 1.0.7 =
* Added: WhatsApp template message sending on form submission
* Added: Template selection interface with search and pagination
* Added: Template parameter mapping to form fields
* Added: Live WhatsApp-style message preview in template settings
* Fixed: Duplicate API calls when multiple hooks were active for the same form
* Improved: Parameter mapping now uses original form field names for accurate value resolution

= 1.0.6 =
* Changed: General package cleanup

= 1.0.5 =
* Fixed: Form ID type mismatch
* Improved: Validation for form type

= 1.0.4 =
* Changed: General package cleanup

= 1.0.3 =
* Removed: Demo mode and associated test endpoint
* Changed: Updated documentation

= 1.0.2 =
* Fixed: Broken Terms of Service and Privacy Policy URLs in External Services section

= 1.0.1 =
* Added: REST API parameter validation and sanitization for all endpoints
* Fixed: Missing input sanitization in the Forminator submission handler
* Fixed: Redundant double-sanitization of form field values
* Added: External Services disclosure section
* Fixed: Incomplete cleanup of plugin options on uninstall
* Changed: Tested up to WordPress 6.9

= 1.0.0 =
* Initial release
* Added: Support for Contact Form 7, WPForms, Ninja Forms, and Forminator
* Added: Happilee API integration with secure encrypted storage
* Added: Settings page for API configuration
* Added: Form field mapping interface
* Added: Real-time form data transmission

== Upgrade Notice ==

= 1.0.10 =
Form settings are now configured in a single modal with a live WhatsApp preview. Fixes settings not applying on sites with a persistent object cache, and incomplete cleanup on multisite uninstall. Upgrade recommended.

= 1.0.5 =
Bug fix update. Resolves form field listing issue with form_id type mismatch. Upgrade recommended for all users.

= 1.0.0 =
Initial release of Happilee Forms Connector. Connect your WordPress forms to Happilee WhatsApp chatbot platform with secure API integration.

== Additional Information ==

**Support:**
For plugin support, visit https://happilee.io/contact-us/

For Happilee platform support and API documentation, visit your Happilee dashboard.

**Privacy:**
This plugin transmits form submission data to the Happilee API. Please review Happilee's privacy policy and ensure compliance with your local data protection regulations (GDPR, CCPA, etc.).
