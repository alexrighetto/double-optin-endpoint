<?php
/**
 * Plugin Name: Double Opt-In Webhook Forwarder
 * Description: Handles double opt-in verification, forwards data to an external webhook, and redirects users.
 * Version: 1.3
 * Author: Alex Righetto
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the REST API endpoint dynamically
add_action('rest_api_init', function () {
    $api_prefix = get_option('double_optin_api_prefix', 'double-optin'); // Default: double-optin

    register_rest_route($api_prefix . '/v1', '/confirm/', array(
        'methods' => 'GET',
        'callback' => 'double_optin_webhook_forwarder',
        'args' => array(
            'email' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_email($param);
                }
            ),
            'token' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return !empty($param);
                }
            )
        ),
        'permission_callback' => '__return_true',
    ));
});

// Callback function to handle the request
function double_optin_webhook_forwarder(WP_REST_Request $request)
{
    $email = sanitize_email($request->get_param('email'));
    $token = sanitize_text_field($request->get_param('token'));

    // Get webhook URL and selected landing page from settings
    $webhook_url = get_option('double_optin_webhook_url', '');
    $redirect_page_id = get_option('double_optin_redirect_page', '');
    
    if (empty($webhook_url)) {
        wp_die('Webhook URL is not configured.', 'Error', array('response' => 500));
    }

    // Get the selected landing page URL
    if (!empty($redirect_page_id)) {
        $redirect_url = get_permalink($redirect_page_id);
        
        // If WPML is active, get the translated URL
        if (function_exists('icl_object_id')) {
            $translated_page_id = icl_object_id($redirect_page_id, 'page', true);
            $redirect_url = get_permalink($translated_page_id);
        }
    } else {
        $redirect_url = home_url('/thank-you'); // Default fallback
    }

    // Prepare the payload
    $body = array(
        'email' => $email,
        'token' => $token
    );

    // Send the webhook in the background (Non-blocking request)
    wp_remote_post($webhook_url, array(
        'method'    => 'POST',
        'body'      => json_encode($body),
        'headers'   => array('Content-Type' => 'application/json'),
        'timeout'   => 0.01, // Prevents user waiting for webhook response
        'blocking'  => false, // Run in the background
    ));

    // Redirect the user to the selected landing page
    wp_redirect($redirect_url);
    exit;
}

/**
 * Admin Menu: Add Double Opt-In Settings Page
 */
add_action('admin_menu', function () {
    add_options_page(
        'Double Opt-In Settings',
        'Double Opt-In',
        'manage_options',
        'double-optin-settings',
        'double_optin_settings_page'
    );
});

/**
 * Admin Settings Page Content
 */
function double_optin_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Double Opt-In Settings</h1>
        <p>
            This plugin provides a Double Opt-In system. When a user clicks a confirmation link, 
            the request is sent to a REST API endpoint, which forwards the data to an external webhook (e.g., n8n).
        </p>
        <p>
            <strong>How to Use:</strong>
            <ol>
                <li>Set up your external webhook URL (n8n or another service).</li>
                <li>Define the API prefix (default: <code>double-optin</code>).</li>
                <li>Choose a landing page where users will be redirected after confirmation.</li>
                <li>Construct the confirmation link in this format:<br>
                    <code><?php echo esc_url(home_url('/wp-json/' . get_option('double_optin_api_prefix', 'double-optin') . '/v1/confirm/?email=user@example.com&token=123456')); ?></code>
                </li>
            </ol>
        </p>
        <form method="post" action="options.php">
            <?php
            settings_fields('double_optin_settings_group');
            do_settings_sections('double-optin-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register Settings
 */
add_action('admin_init', function () {
    register_setting('double_optin_settings_group', 'double_optin_webhook_url');
    register_setting('double_optin_settings_group', 'double_optin_api_prefix');
    register_setting('double_optin_settings_group', 'double_optin_redirect_page');

    add_settings_section(
        'double_optin_settings_section',
        'Webhook & API Settings',
        function () {
            echo '<p>Configure the webhook URL and API settings.</p>';
        },
        'double-optin-settings'
    );

    add_settings_field(
        'double_optin_webhook_url',
        'Webhook URL',
        function () {
            $webhook_url = get_option('double_optin_webhook_url', '');
            echo '<input type="text" name="double_optin_webhook_url" value="' . esc_attr($webhook_url) . '" class="regular-text">';
        },
        'double-optin-settings',
        'double_optin_settings_section'
    );

    add_settings_field(
        'double_optin_api_prefix',
        'REST API Prefix',
        function () {
            $api_prefix = get_option('double_optin_api_prefix', 'double-optin');
            echo '<input type="text" name="double_optin_api_prefix" value="' . esc_attr($api_prefix) . '" class="regular-text">';
            echo '<p class="description">This defines the API route. Default is <code>double-optin</code>. Change this if needed.</p>';
        },
        'double-optin-settings',
        'double_optin_settings_section'
    );

    add_settings_field(
        'double_optin_redirect_page',
        'Landing Page',
        function () {
            $selected_page = get_option('double_optin_redirect_page', '');
            wp_dropdown_pages(array(
                'name'              => 'double_optin_redirect_page',
                'selected'          => $selected_page,
                'show_option_none'  => 'Select a page',
                'option_none_value' => ''
            ));
            echo '<p class="description">Choose the page where users will land after confirmation.</p>';
        },
        'double-optin-settings',
        'double_optin_settings_section'
    );
});
