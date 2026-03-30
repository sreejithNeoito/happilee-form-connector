=== Happilee Forms Connector ===
Contributors: happilee
Author: happilee
Author URI: https://neoito.com
Donate link: https://happilee.io/pricing
Tags: happilee, whatsapp, chatbot, contact form, api integration
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.2
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

The plugin includes a demo mode for testing purposes. You can test the plugin functionality before signing up for Happilee:

1. Install and activate the plugin
2. Install one of the supported form plugins (e.g., Contact Form 7)
3. Create a test form
4. In Happilee Forms Connector settings, enter the demo API key: `demo-test-key-12345`
5. Submit the test form
6. Form data will be routed to the demo endpoint for verification

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

Yes! The plugin includes a demo mode for testing. Use the API key `demo-test-key-12345` in the settings to test form submissions. This allows you to verify the plugin works before signing up for Happilee.

= Is the data transmission secure? =

Yes, all data is transmitted securely via HTTPS. API keys are encrypted using AES-256-CBC encryption before being stored in the database.

= Can I connect multiple forms? =

Yes, you can connect as many forms as you need from any of the supported form plugins.

= Does this work with custom forms? =

Currently, the plugin supports the four major form plugins listed. Custom form support may be added in future updates.

= What data is sent to Happilee? =

The plugin sends form field names and values, submission timestamp, form name/ID, page URL, and user information (if available) to the Happilee API endpoint.

= How do I verify the plugin is working? =

After configuring your API key and connecting a form, submit a test form. You should see the submission data in your Happilee dashboard. For demo mode testing, form submissions will be routed to the demo endpoint.

== External Services ==

This plugin connects to the following external services:

**1. Happilee API (happilee.io)**

This plugin communicates with the Happilee WhatsApp chatbot platform API to:

* Validate your API key and retrieve your project details — sent once when you save your API credentials in the settings page.
* Forward form submission data — sent every time a visitor submits a connected WordPress form.

Data sent to Happilee includes: your API key (for authentication), submitted form field values (e.g. name, email, phone), the form name and ID, the page URL where the form was submitted, the submission timestamp, and basic user information (IP address and user agent).

Data is transmitted to the following Happilee API endpoints:
* `https://api.happilee.io/api/v1/getProjectDetails` — API key validation
* `https://api.happilee.io/api/v1/createContact` — form submission forwarding

This service is provided by Happilee (a Meta Business Partner):
* Website: https://happilee.io
* Terms of Service: https://happilee.io/terms-of-use/
* Privacy Policy: https://happilee.io/privacy-policy/

No data is sent to Happilee unless a valid API key has been configured and at least one form has been enabled in the plugin settings.

**2. ipapi.co — Country code lookup**

When a form is submitted and no country calling code has been mapped to a field, the plugin automatically detects the visitor's country calling code by sending the visitor's IP address to ipapi.co.

Data sent: the visitor's IP address.
When: automatically on every form submission where a country code field is not already populated by the form.
Why: to resolve the correct international phone dialling prefix (e.g. `+44`, `+91`) before forwarding the submission to Happilee.

The request is made to:
`https://ipapi.co/{visitor_ip}/country_calling_code/`

This service is provided by ipapi.co:
* Website: https://ipapi.co
* Terms of Service: https://ipapi.co/terms/
* Privacy Policy: https://ipapi.co/privacy/

**3. webhook.site (webhook.site) — Demo / testing mode only**

When the demo API key (`demo-test-key-12345`) is used, the plugin routes all API calls to a public webhook.site endpoint instead of the live Happilee API. This allows testing of the plugin without a real Happilee account.

Data sent is identical in structure to what would be sent to Happilee (form field values, form metadata, timestamp, page URL). This data is visible to anyone who has access to the webhook.site token URL.

* Website: https://webhook.site
* Terms of Service: https://webhook.site/terms
* Privacy Policy: https://webhook.site/terms

This endpoint is used **only** in demo mode and is **never** contacted in normal production use (i.e. when a real Happilee API key is configured).

== Screenshots ==

1. Plugin settings page with API configuration
2. Form selection and field mapping interface
3. Successful API connection status
4. Integration with Contact Form 7
5. Connected forms management

== Changelog ==

= 1.0.2 =
* Fixed broken Terms of Service and Privacy Policy URLs in External Services section
* Added automatic database table creation on plugins_loaded to prevent missing table errors after migration or manual updates

= 1.0.1 =
* Added REST API parameter validation with sanitize_callback and validate_callback for all endpoints
* Fixed missing wp_unslash() on $_POST access in Forminator submission handler
* Fixed redundant double-sanitization of form field values
* Added External Services disclosure section
* Corrected database option cleanup on uninstall (encryption key and db version options now removed)
* Removed development source files from distributed zip
* Updated Tested up to: 6.7

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
4. Enter API key: `demo-test-key-12345` (activates demo/test mode)
5. Click "Save Settings" — the plugin validates the key against a webhook.site demo endpoint which always returns HTTP 200, so no real Happilee account is needed
6. Create a simple contact form using Contact Form 7
7. Navigate to Happilee Forms Connector → Configure Forms
8. Enable the form and map fields
9. Submit the test form on the frontend
10. The form payload is sent to the webhook.site demo endpoint — the response confirms successful transmission

**Demo Mode:**

When the API key `demo-test-key-12345` is used, the plugin automatically switches both endpoints (validate and createContact) to point to a public webhook.site URL instead of the live Happilee API. This allows full end-to-end testing with no Happilee account required.

The demo webhook.site endpoint URL can be overridden with your own webhook.site token by adding this to your theme's `functions.php`:

```php
add_filter( 'happfoco_api_demo_create_contact_endpoint', function( $endpoint ) {
    return 'https://webhook.site/your-unique-token';
} );
```

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
- REST API parameter validation with sanitize_callback and validate_callback
- Data sanitization on all form field values
- Capability checks (manage_options)

If you need a production Happilee API key for extended testing, please contact support@happilee.io

== Source Code & Build Process ==

The JavaScript bundled in `assets/js/bundle.js` is compiled from React source code publicly available at:
https://github.com/sreejithNeoito/happilee-form-connector

**Build tools required:** Node.js 18+ and npm

**Build steps:**

1. `cd app`
2. `npm install`
3. `npm run build`

The compiled output is written to `assets/js/bundle.js` and `assets/css/bundle.css`.

**npm dependencies used** (see `app/package.json` for full list):
* React & ReactDOM — UI framework
* react-toastify — toast notifications
* Tailwind CSS — utility-first styling
* Webpack — module bundler and minifier

== Developer Information ==

**Hooks & Filters:**

The plugin provides the following filter hooks for developers:

`happfoco_api_validate_endpoint` — Override the live Happilee validation endpoint URL

`happfoco_api_create_contact_endpoint` — Override the live Happilee createContact endpoint URL

`happfoco_api_demo_validate_endpoint` — Override the webhook.site URL used during demo mode validation

`happfoco_api_demo_create_contact_endpoint` — Override the webhook.site URL used during demo mode form submission

Example — point demo submissions to your own webhook.site URL:
```php
add_filter( 'happfoco_api_demo_create_contact_endpoint', function( $endpoint ) {
    return 'https://webhook.site/your-unique-token';
} );
```

**Database:**
The plugin creates a custom table `wp_happfoco_forms_data` to store form configuration and field mappings.

**Requirements:**
- OpenSSL PHP extension (for API key encryption)
- WordPress REST API enabled
- One or more supported form plugins