<?php
add_action('wpcf7_mail_sent', 'odeslat_do_leadhubu_predregistrace');

function odeslat_do_leadhubu_predregistrace($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();

    // Získání a sanitizace dat z formuláře
    $email = isset($data['your-email']) ? sanitize_email($data['your-email']) : '';
    $first_name = isset($data['your-name']) ? sanitize_text_field($data['your-name']) : '';
    $last_name = isset($data['your-lastname']) ? sanitize_text_field($data['your-lastname']) : '';

    if (empty($email) || !is_email($email)) {
        error_log('Neplatný e-mail pro Leadhub: ' . $email);
        return;
    }

    // Získání access tokenu z Leadhub API
    $auth_response = wp_remote_post('https://api.leadhub.co/token', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode('TVŮJ_API_USER:TVÉ_API_HESLO'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query([
            'grant_type' => 'password',
            'username'   => base64_encode('TVŮJ_CLIENT_USER'),
            'password'   => base64_encode('TVÉ_CLIENT_PASSWORD'),
        ]),
        'timeout' => 10
    ]);

    if (is_wp_error($auth_response)) {
        error_log('Chyba při získání tokenu z Leadhubu: ' . $auth_response->get_error_message());
        return;
    }

    $auth_body = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = isset($auth_body['access_token']) ? $auth_body['access_token'] : null;

    if (!$access_token) {
        error_log('Přístupový token Leadhubu nebyl získán: ' . wp_remote_retrieve_body($auth_response));
        return;
    }

    // Odeslání kontaktu do seznamu predregistrace
    $api_response = wp_remote_post('https://api.leadhub.co/interest-lists/predregistrace/subscriptions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'email'      => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'tags'       => ['web-form', 'cf7'],
        ]),
        'timeout' => 10
    ]);

    if (is_wp_error($api_response)) {
        error_log('Chyba při zápisu kontaktu do Leadhubu: ' . $api_response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($api_response);
    if ($response_code >= 400) {
        error_log('Leadhub API odpovědělo chybou: ' . wp_remote_retrieve_body($api_response));
    }
}
