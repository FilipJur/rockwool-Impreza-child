<?php
add_action('wpcf7_mail_sent', 'odeslat_do_leadhubu_bez_oauth');

function odeslat_do_leadhubu_bez_oauth($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();

    // Získání a sanitizace dat z formuláře (uprav názvy podle svého formuláře)
    $email = isset($data['email']) ? sanitize_email($data['email']) : '';
    $first_name = isset($data['first-name']) ? sanitize_text_field($data['first-name']) : '';
    $last_name = isset($data['last-name']) ? sanitize_text_field($data['last-name']) : '';

    if (empty($email) || !is_email($email)) {
        error_log('Neplatný e-mail pro Leadhub: ' . $email);
        return;
    }

    // Odeslání kontaktu přímo pomocí API tokenu
    $response = wp_remote_post('https://api.leadhub.co/interest-lists/predregistrace/subscriptions', [
        'headers' => [
            'Accept'  => 'application/json',
            'Authorization' => LEADHUB_TOKEN, // <- hodnota uvedena ve wp-config
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'operation' => 'subscribe',
            'email_address'      => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            /*'tags'       => ['cf7-form', 'mistrfachman']*/
        ]),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        error_log('Chyba při volání Leadhub API: ' . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code >= 400) {
        error_log('Leadhub API odpovědělo chybou (' . $status_code . '): ' . wp_remote_retrieve_body($response));
    }
}

/*add_action('wpforms_process_complete', 'odeslat_do_leadhubu_z_konkretniho_formulare', 10, 4);

function odeslat_do_leadhubu_z_konkretniho_formulare($fields, $entry, $form_data, $entry_id) {
    // Zkontroluj ID formuláře – pokračuj jen pokud odpovídá konkrétnímu
    if ((int)$form_data['id'] !== 134) {
        return;
    }

    // Získání a sanitizace dat (uprav názvy polí dle tvého formuláře)
    $email      = isset($fields['email']['value']) ? sanitize_email($fields['email']['value']) : '';
    $first_name = isset($fields['first_name']['value']) ? sanitize_text_field($fields['first_name']['value']) : '';
    $last_name  = isset($fields['last_name']['value']) ? sanitize_text_field($fields['last_name']['value']) : '';

    if (empty($email) || !is_email($email)) {
        error_log('Leadhub: Neplatný e-mail – ' . $email);
        return;
    }

    // Připrav data pro API
    $payload = [
        'email'      => $email,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'tags'       => ['wpforms', 'mistrfachman']
    ];

    // Odeslání požadavku
    $response = wp_remote_post('https://api.leadhub.co/interest-lists/predregistrace/subscriptions', [
        'headers' => [
            'Authorization' => 'Bearer ba52922c7e4b4d4ab54cb42e21b882018748a370ce2b40cfb9299b89f8faed51f21c9a1671cf47e8a3d7a6f82f64fcf887eea57b8ed14ecf9ca808959acc7de6', // ← sem dej svůj skutečný token
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($payload),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        error_log('Leadhub API chyba: ' . $response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 400) {
        error_log('Leadhub API odpověď (' . $code . '): ' . wp_remote_retrieve_body($response));
    }
}
*/

function child_theme_customScript() {
    echo "<script data-cookieconsent='ignore'>
       window.dataLayer = window.dataLayer || [];
       function gtag() {
           dataLayer.push(arguments);
       }
       gtag('consent', 'default', {
           ad_storage: 'denied',
           analytics_storage: 'denied',
           functionality_storage: 'denied',
           personalization_storage: 'denied',
           security_storage: 'granted',
           wait_for_update: 500,
       });
       gtag('set', 'ads_data_redaction', true);
   </script>";
   
   echo "<!-- Google Tag Manager -->
   <script data-cookieconsent='ignore'>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
   new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
   j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
   'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
   })(window,document,'script','dataLayer','GTM-PGGDXLZG');</script>
   <!-- End Google Tag Manager -->";
   }
   add_action( "wp_head", "child_theme_customScript", 1 );