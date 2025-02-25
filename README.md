# Double Opt-In Webhook Forwarder (WordPress Plugin)

This WordPress plugin handles double opt-in confirmation, forwards email verification requests to an external webhook (e.g., n8n), and redirects users to a custom confirmation page.

## Features

- Intercepts GET confirmation requests
- Forwards data to an external webhook (POST request)
- Admin settings page to configure:
  - Webhook URL (e.g., n8n or any external service)
  - REST API Prefix (customizable)
  - Landing Page (redirect users to a custom thank-you page)
- WPML Support – Redirects users to the correct translated page ifa WPML is active
- Background Webhook Execution – The webhook request is sent in the background while the user is redirected

## Installation

1. Download or Clone the Plugin
   ```bash
   git clone https://github.com/yourusername/double-optin-wordpress-plugin.git
   ```
2. Upload the Plugin to WordPress
   - Go to **WordPress Dashboard > Plugins > Add New**
   - Click **Upload Plugin**
   - Select the `.zip` file and click **Install Now**
   - Activate the plugin

3. Configure Settings
   - Navigate to **Settings > Double Opt-In**
   - Enter your webhook URL
   - Set your custom API prefix
   - Choose a landing page for redirection after confirmation
   - Save settings

## Usage

1. Generate a confirmation link for users:
   ```
   https://yourwebsite.com/wp-json/double-optin/v1/confirm/?email=user@example.com&token=123456
   ```
   - The API prefix (default: `double-optin`) can be customized in settings.

2. When the user clicks the link:
   - The request is forwarded to your webhook (e.g., n8n).
   - The user is redirected to the selected landing page.

## WPML Support

If WPML is installed, the plugin redirects users to the correct language version of the selected landing page.

## Custom API Prefix

- The REST API endpoint defaults to:
  ```
  https://yourwebsite.com/wp-json/double-optin/v1/confirm/
  ```
- You can change the prefix in the settings.

## Webhook Request Details

- **Method:** `POST`
- **Content-Type:** `application/json`
- **Payload Example:**
  ```json
  {
    "email": "user@example.com",
    "token": "123456"
  }
  ```

## License

This project is licensed under the MIT License.

## Contributing

Pull requests are welcome! If you find issues, please open an issue in the repository.

## Need Help?

If you need support, open an issue on GitHub or contact me.

## Enjoy Using the Plugin?

Star this repository on GitHub to support further development.
