=== Happilee Forms Connector ===
Contributors: happilee
Author: Neoito
Author URI: https://neoito.com
Donate link: https://happilee.io/pricing
Tags: happilee, whatsapp, chatbot, contact form, api integration
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
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
* Built-in demo mode for testing

**About Happilee:**

Happilee is a Meta Business Partner providing WhatsApp chatbot services that help businesses automate customer communication, lead generation, and support through WhatsApp Business API.

**How It Works:**

1. Install and activate the plugin
2. Configure your Happilee API credentials
3. Select which forms to connect
4. Form submissions automatically sync to your Happilee WhatsApp chatbot

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

**Testing Without Happilee Account:**

The plugin includes a demo endpoint for testing purposes. You can test the plugin functionality before signing up for Happilee:

1. Install and activate the plugin
2. Install one of the supported form plugins (e.g., Contact Form 7)
3. Create a test form
4. In Happilee Forms Connector settings, you can use demo API key: `demo-test-key-12345`
5. Submit the test form
6. Form data will be sent to the demo endpoint for verification

== Frequently Asked Questions ==

= What is Happilee? =

Happilee is a Meta Business Partner that provides WhatsApp chatbot services for businesses to automate customer communication through WhatsApp Business API.

= Which form plugins are supported? =

Currently, the plugin supports Contact Form 7, WPForms, Ninja Forms, and Forminator.

= Do I need a Happilee account? =

Yes, you need an active Happilee account and API key for production use. However, you can test the plugin functionality using the demo mode before signing up. Visit https://happilee.io to create an account.

= Where do I find my Happilee API key? =

Log in to your Happilee dashboard and navigate to Project → Settings → Integrations to find your API key.

= Can I test the plugin before getting a Happilee account? =

Yes! The plugin includes a demo endpoint for testing. Use the API key `demo-test-key-12345` in the settings to test form submissions. This allows you to verify the plugin works before signing up for Happilee.

= Is the data transmission secure? =

Yes, all data is transmitted securely via HTTPS. API keys are encrypted using AES-256-CBC encryption before being stored in the database.

= Can I connect multiple forms? =

Yes, you can connect as many forms as you need from any of the supported form plugins.

= Does this work with custom forms? =

Currently, the plugin supports the four major form plugins listed. Custom form support may be added in future updates.

= What data is sent to Happilee? =

The plugin sends form field names and values, submission timestamp, form name/ID, page URL, and user information (if available) to the Happilee API endpoint.

= How do I verify the plugin is working? =

After configuring your API key and connecting a form, submit a test form. You should see the submission data in your Happilee dashboard. For demo mode testing, form submissions will be sent to the demo endpoint.

== Screenshots ==

1. Plugin settings page with API configuration
2. Form selection and field mapping interface
3. Successful API connection status
4. Integration with Contact Form 7
5. Connected forms management

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Contact Form 7
* Support for WPForms
* Support for Ninja Forms
* Support for Forminator
* Happilee API integration with secure encrypted storage
* Settings page for API configuration
* Form field mapping interface
* Demo mode for testing
* Real-time form data transmission

== Upgrade Notice ==

= 1.0.0 =
Initial release of Happilee Forms Connector. Connect your WordPress forms to Happilee WhatsApp chatbot platform with secure API integration.

== Additional Information ==

**Support:**
For plugin support, visit https://happilee.io/contact-us/

For Happilee platform support and API documentation, visit your Happilee dashboard.

**Privacy:**
This plugin transmits form submission data to the Happilee API. Please review Happilee's privacy policy and ensure compliance with your local data protection regulations (GDPR, CCPA, etc.).

**For Plugin Reviewers:**

This plugin integrates WordPress form submissions with the Happilee WhatsApp chatbot platform.

**Testing Instructions:**

1. Install and activate the plugin
2. Install Contact Form 7 (or any supported form plugin)
3. Go to Settings → Happilee Forms Connector
4. Enter API key: `demo-test-key-12345` (for demo mode)
5. Click "Save Settings"
6. Create a simple contact form using Contact Form 7
7. Navigate to Happilee Forms Connector → Configure Forms
8. Enable the form and map fields
9. Submit the test form on the frontend
10. Form data will be sent to the demo endpoint (webhook.site)

**Demo Mode:**
The plugin includes a demo endpoint for testing without requiring actual Happilee credentials. This allows reviewers to verify all functionality including:
- API configuration and validation
- Form detection and listing
- Field mapping interface
- Form submission handling
- Data transmission to API endpoint

**What Gets Sent:**
Form submissions are sent as JSON with the following structure:
- form_id: Form identifier
- form_name: Form title
- form_type: Plugin type (cf7, wpforms, ninja_forms, forminator)
- fields: Array of field names and values
- submission_time: Timestamp
- page_url: URL where form was submitted
- user_info: User agent and IP (if available)

**Security Features:**
- API keys stored with AES-256-CBC encryption
- All API requests use HTTPS
- Rate limiting on API verification
- Data sanitization and validation
- WordPress nonce verification
- Capability checks (administrator/manage_woocommerce)

If you need a production Happilee API key for extended testing, please contact support@happilee.io

== Developer Information ==

**Hooks & Filters:**

The plugin provides the following filter hook for developers:

`wphfc_api_endpoint` - Filter the API endpoint URL

Example:
```php
add_filter('wphfc_api_endpoint', function($endpoint) {
    return 'https://your-custom-endpoint.com/api';
});
```

**Database:**
The plugin creates a custom table `wp_hfc_forms_data` to store form configuration and field mappings.

**Requirements:**
- OpenSSL PHP extension (for API key encryption)
- WordPress REST API enabled
- One or more supported form plugins