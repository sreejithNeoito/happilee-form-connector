# Happilee Forms Connector

> Connect your WordPress contact forms to the [Happilee](https://happilee.io) WhatsApp chatbot platform via API integration.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPLv2%2B-green)
![Version](https://img.shields.io/badge/Version-1.0.6-orange)
![Tested up to](https://img.shields.io/badge/Tested%20up%20to-WordPress%206.7-blue)

---

## Table of Contents

- [Description](#description)
- [Supported Form Plugins](#supported-form-plugins)
- [Key Features](#key-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [External Services](#external-services)
- [Security](#security)
- [Developer Reference](#developer-reference)
- [Changelog](#changelog)
- [License](#license)

---

## Description

Happilee Forms Connector seamlessly integrates your WordPress contact forms with the [Happilee](https://happilee.io) WhatsApp chatbot platform. As a Meta Business Partner, Happilee provides powerful WhatsApp business solutions, and this plugin bridges your form submissions directly to the Happilee API — so every lead captured via a WordPress form instantly flows into your WhatsApp automation workflows.

---

## Supported Form Plugins

| Plugin               | Status       |
| -------------------- | ------------ |
| Contact Form 7 (CF7) | ✅ Supported |
| WPForms              | ✅ Supported |
| Ninja Forms          | ✅ Supported |
| Forminator           | ✅ Supported |

---

## Key Features

- Easy API connection setup with the Happilee platform
- Automatic form submission sync to WhatsApp
- Support for multiple popular WordPress form plugins
- Secure API key storage using AES-256-CBC encryption
- Real-time data transmission on every form submission
- Simple settings page with field mapping interface
- Automatic country calling code detection via IP lookup

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenSSL PHP extension (for API key encryption)
- WordPress REST API enabled
- An active [Happilee account](https://happilee.io) and API key
- At least one supported form plugin installed

---

## Installation

**Via WordPress admin:**

1. Go to **Plugins → Add New**
2. Search for **Happilee Forms Connector**
3. Click **Install Now**, then **Activate**

**Manual upload:**

1. Download the plugin zip file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

**Via FTP:**

1. Extract the zip and upload the `happilee-forms-connector` folder to `/wp-content/plugins/`
2. Activate through the **Plugins** screen in WordPress admin

---

## Configuration

1. Go to **Settings → Happilee Forms Connector**
2. Enter your Happilee API key (found in your Happilee dashboard under **Project → Settings → Integrations**)
3. Click **Save Settings** — the plugin validates your key against the Happilee API
4. Navigate to **Configure Forms**
5. Select a form, enable it, and map its fields to the Happilee contact fields
6. Submit a test form to confirm data is flowing through

---

## How It Works

```
Visitor submits a WordPress form
         ↓
Plugin intercepts the submission via form plugin hook
         ↓
Field values are mapped to Happilee contact fields
         ↓
Country calling code is resolved (from field mapping or IP lookup)
         ↓
Payload is sent to Happilee createContact API endpoint
         ↓
Contact is created / updated in Happilee dashboard
         ↓
WhatsApp automation workflow is triggered
```

**Data sent to Happilee on each submission:**

```json
{
  "phone_number": "+911234567890",
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "country_code": "+91"
}
```

---

## External Services

This plugin makes HTTP requests to the following third-party services.

### 1. Happilee API

Used for API key validation and forwarding form submissions.

| Endpoint                                              | Purpose                       |
| ----------------------------------------------------- | ----------------------------- |
| `https://devapi.happilee.io/api/v1/getProjectDetails` | Validates your API key        |
| `https://devapi.happilee.io/api/v1/createContact`     | Forwards form submission data |

Data transmitted includes your API key, form field values, form metadata, page URL, submission timestamp, and user IP/agent.

- [Happilee Website](https://happilee.io)
- [Terms of Service](https://happilee.io/terms-of-use/)
- [Privacy Policy](https://happilee.io/privacy-policy/)

> No data is sent unless a valid API key is configured and at least one form is enabled.

### 2. ipapi.co — Country code lookup

When no country calling code is mapped to a form field, the plugin sends the visitor's IP address to ipapi.co to resolve the correct international dialling prefix (e.g. `+44`, `+91`).

- Endpoint: `https://ipapi.co/{visitor_ip}/country_calling_code/`
- [ipapi.co Website](https://ipapi.co)
- [Terms of Service](https://ipapi.co/terms/)
- [Privacy Policy](https://ipapi.co/privacy/)

---

## Security

- API keys encrypted at rest using **AES-256-CBC** with a key derived from WordPress `AUTH_KEY` + `SECURE_AUTH_KEY`
- All external requests use **HTTPS**
- REST API endpoints protected by **`manage_options` capability check**
- All REST API parameters validated with `validate_callback` and sanitized with `sanitize_callback`
- Rate limiting applied to API key verification (one attempt per 10 seconds per user)
- `$_POST` data unslashed and sanitized individually on all form handlers
- Uninstall routine removes all plugin data: custom table, API key, encryption key, and db version option

---

## Developer Reference

### Filter Hooks

| Filter                                 | Description                                           |
| -------------------------------------- | ----------------------------------------------------- |
| `happfoco_api_validate_endpoint`       | Override the live Happilee validation endpoint URL    |
| `happfoco_api_create_contact_endpoint` | Override the live Happilee createContact endpoint URL |

**Example:**

```php
// Override the live Happilee validation endpoint
add_filter( 'happfoco_api_validate_endpoint', function( $endpoint ) {
    return 'https://your-custom-endpoint.com/validate';
} );
```

### REST API Endpoints

All endpoints are under the `happfoco/v1` namespace and require `manage_options` capability.

| Method | Route                 | Description                                |
| ------ | --------------------- | ------------------------------------------ |
| `POST` | `/save-api-config`    | Save and validate API key                  |
| `GET`  | `/get-api-config`     | Retrieve current API configuration         |
| `GET`  | `/fetch-forms`        | List all forms from supported plugins      |
| `POST` | `/fetch-form-fields`  | Get fields for a specific form             |
| `POST` | `/save-form-settings` | Save form enable/disable and field mapping |
| `GET`  | `/fetch-form-data`    | Retrieve all stored form configurations    |

### Database

The plugin creates one custom table on activation:

**`wp_happfoco_forms_data`**

| Column             | Type           | Description                                                |
| ------------------ | -------------- | ---------------------------------------------------------- |
| `id`               | `mediumint(9)` | Primary key                                                |
| `form_id`          | `varchar(100)` | Form identifier from the form plugin                       |
| `form_name`        | `varchar(255)` | Human-readable form title                                  |
| `form_type`        | `varchar(100)` | Plugin type: `cf7`, `wpforms`, `ninja_forms`, `forminator` |
| `is_enabled`       | `tinyint(1)`   | Whether this form actively syncs to Happilee               |
| `active_hook`      | `varchar(255)` | WordPress action hook used to capture submissions          |
| `connected_fields` | `longtext`     | JSON field mapping (form field → Happilee field)           |
| `created_at`       | `datetime`     | Record creation timestamp                                  |

All plugin data is removed cleanly on uninstall.

---

## Build Process

The admin UI is built with React and compiled into `assets/js/bundle.js`.

**Prerequisites:** Node.js 18+ and npm

```bash
cd app
npm install
npm run build
```

Output is written to `assets/js/bundle.js` and `assets/css/main.css`.

**Key dependencies:**

| Package          | Purpose                     |
| ---------------- | --------------------------- |
| React & ReactDOM | UI framework                |
| react-toastify   | Toast notifications         |
| Tailwind CSS     | Utility-first styling       |
| Webpack          | Module bundler and minifier |

---

## Changelog

### 1.0.6

- Removed README.md from distributed plugin package

### 1.0.5

- Fixed issue with form_id type mismatch
- Improved validation for form_type

### 1.0.4

- Removed src/ source folder from distributed plugin package

### 1.0.3

- Removed demo mode, demo API key, and webhook.site endpoint
- Removed Source Code & Build Process section from readme.txt

### 1.0.2

- Fixed broken Terms of Service and Privacy Policy URLs in External Services section
- Added automatic database table creation on `plugins_loaded` to prevent missing table errors after migration or manual updates

### 1.0.1

- Added REST API parameter validation with `sanitize_callback` and `validate_callback` for all endpoints
- Fixed missing `wp_unslash()` on `$_POST` access in Forminator submission handler
- Fixed redundant double-sanitization of form field values
- Added External Services disclosure section
- Corrected database option cleanup on uninstall (encryption key and db version options now removed)
- Removed development source files from distributed zip
- Updated tested up to WordPress 6.7

### 1.0.0

- Initial release
- Support for Contact Form 7, WPForms, Ninja Forms, and Forminator
- Happilee API integration with secure encrypted key storage
- Settings page with API configuration
- Form field mapping interface
- Real-time form data transmission

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

**Support:** [happilee.io/contact-us](https://happilee.io/contact-us/)  
**Author:** [Neoito](https://neoito.com)  
**Plugin URI:** [happilee.io](https://happilee.io)
