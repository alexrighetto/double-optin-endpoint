<?php
/**
 * Plugin Name: Double Opt-In Webhook Forwarder
 * Description: Handles double opt-in verification, forwards data to an external webhook, and redirects users.
 * Version: 1.6
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the REST API endpoint dynamically
add_action('rest_api_init', function () {
    $api_prefix = get_option('double_optin_api_prefix', 'double-optin');

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
            ),
            'expiration' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return DateTime::createFromFormat('m-d-Y', $param) !== false;
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
    $expiration = sanitize_text_field($request->get_param('expiration'));

    // Get the date format from settings (default: 'm-d-Y')
    $date_format = get_option('double_optin_date_format', 'm-d-Y');

    // Convert expiration date to timestamp
    $expiration_date = DateTime::createFromFormat($date_format, $expiration);
    if (!$expiration_date) {
        double_optin_redirect_to_error_page();
    }

    $expiration_timestamp = $expiration_date->getTimestamp();
    $current_timestamp = current_time('timestamp');

    // Get webhook URL and selected landing/expired/error pages from settings
    $webhook_url = get_option('double_optin_webhook_url', '');
    $redirect_page_id = get_option('double_optin_redirect_page', '');
    $expired_page_id = get_option('double_optin_expired_page', '');
    $error_page_id = get_option('double_optin_error_page', '');

    // Redirect if link is expired
    if ($current_timestamp > $expiration_timestamp + (48 * 60 * 60)) {
        double_optin_redirect_to_selected_page($expired_page_id, '/expired');
    }

    // Redirect if webhook URL is not set
    if (empty($webhook_url)) {
        double_optin_redirect_to_error_page();
    }

    // If everything is valid, send data and redirect to the thank-you page
    if (!empty($redirect_page_id)) {
        $redirect_url = get_permalink($redirect_page_id);
        if (function_exists('icl_object_id')) {
            $translated_page_id = icl_object_id($redirect_page_id, 'page', true);
            $redirect_url = get_permalink($translated_page_id);
        }
    } else {
        $redirect_url = home_url('/thank-you');
    }

    // Prepare the payload
    $body = array(
        'email' => $email,
        'token' => $token
    );

    // Send the webhook in the background
    wp_remote_post($webhook_url, array(
        'method'    => 'POST',
        'body'      => json_encode($body),
        'headers'   => array('Content-Type' => 'application/json'),
        'timeout'   => 0.01,
        'blocking'  => false,
    ));

    wp_redirect($redirect_url);
    exit;
}

// Redirect to the selected error page
function double_optin_redirect_to_error_page() {
    double_optin_redirect_to_selected_page(get_option('double_optin_error_page', ''), '/error');
}

// General function to handle redirections
function double_optin_redirect_to_selected_page($page_id, $default_slug) {
    if (!empty($page_id)) {
        $redirect_url = get_permalink($page_id);
        if (function_exists('icl_object_id')) {
            $translated_page_id = icl_object_id($page_id, 'page', true);
            $redirect_url = get_permalink($translated_page_id);
        }
    } else {
        $redirect_url = home_url($default_slug);
    }
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
                <li>Select a <strong>Landing Page</strong> where users will be redirected after successful confirmation.</li>
                <li>Select an <strong>Expired Page</strong> where users will be redirected if the link is older than **48 hours**.</li>
                <li>Select an <strong>Error Page</strong> where users will be redirected in case of an **invalid email, token, or expiration date**.</li>
                <li>Construct the confirmation link in this format:<br>
                    <code><?php echo esc_url(home_url('/wp-json/' . get_option('double_optin_api_prefix', 'double-optin') . '/v1/confirm/?email=user@example.com&token=123456&expiration=02-27-2025')); ?></code>
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
    register_setting('double_optin_settings_group', 'double_optin_expired_page');
    register_setting('double_optin_settings_group', 'double_optin_error_page');
    register_setting('double_optin_settings_group', 'double_optin_date_format');

    add_settings_section('double_optin_settings_section', 'Webhook & API Settings', function () {
        echo '<p>Configure the webhook URL, expiration settings, and API settings.</p>';
    }, 'double-optin-settings');

    add_settings_field('double_optin_webhook_url', 'Webhook URL', function () {
        echo '<input type="text" name="double_optin_webhook_url" value="' . esc_attr(get_option('double_optin_webhook_url', '')) . '" class="regular-text">';
    }, 'double-optin-settings', 'double_optin_settings_section');

    add_settings_field('double_optin_api_prefix', 'REST API Prefix', function () {
        echo '<input type="text" name="double_optin_api_prefix" value="' . esc_attr(get_option('double_optin_api_prefix', 'double-optin')) . '" class="regular-text">';
    }, 'double-optin-settings', 'double_optin_settings_section');
    
    add_settings_field('double_optin_date_format', 'Date Format', function () {
    echo '<input type="text" name="double_optin_date_format" value="' . esc_attr(get_option('double_optin_date_format', 'm-d-Y')) . '" class="regular-text">';
    echo '<p class="description">Enter the date format (default: <code>m-d-Y</code>). Based on PHP <code>date()</code> formats or Luxon documentation.</p>';
    }, 'double-optin-settings', 'double_optin_settings_section');

    foreach (['redirect_page' => 'Landing Page', 'expired_page' => 'Expired Page', 'error_page' => 'Error Page'] as $option => $label) {
        add_settings_field('double_optin_' . $option, $label, function () use ($option) {
            wp_dropdown_pages(['name' => 'double_optin_' . $option, 'selected' => get_option('double_optin_' . $option, ''), 'show_option_none' => 'Select a page', 'option_none_value' => '']);
        }, 'double-optin-settings', 'double_optin_settings_section');
    }
});
