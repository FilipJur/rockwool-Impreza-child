<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Business Data Validator - Business Data Validation and ARES Integration
 *
 * Handles validation of business registration data including IČO validation
 * with ARES API integration for Czech business registry verification.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BusinessDataValidator {

    public function __construct(
        private BusinessDataManager $business_manager,
        private AresApiClient $ares_client
    ) {}

    /**
     * Dynamically validates posted data against the form's actual required fields.
     * This method inspects the form tags for the '*' required marker.
     * It throws a single exception with all missing fields.
     *
     * @param array $posted_data The submitted form data.
     * @param \WPCF7_ContactForm $form The Contact Form 7 form object.
     * @throws \Exception
     */
    public function validate_form_data(array $posted_data, \WPCF7_ContactForm $form): void {
        $form_tags = $form->scan_form_tags();
        $missing_fields = [];

        foreach ($form_tags as $tag) {
            // We only care about tags that are explicitly marked as required.
            if (!$tag->is_required()) {
                continue; // Skip optional fields like your [select position]
            }

            $field_name = $tag->name;

            // Check if the required field is missing or empty.
            if (empty($posted_data[$field_name])) {
                $missing_fields[] = $field_name;
            }
        }

        if (!empty($missing_fields)) {
            throw new \Exception('Prosím, vyplňte všechna povinná pole: ' . implode(', ', $missing_fields));
        }

        // Specific format validations can still run on fields if they exist.
        if (!empty($posted_data['ico']) && !$this->validate_ico_format($posted_data['ico'])) {
            throw new \Exception('Zadané IČO má neplatný formát.');
        }

        if (!empty($posted_data['contact-email']) && !is_email($posted_data['contact-email'])) {
            throw new \Exception('Zadaný e-mail má neplatný formát.');
        }
    }

    /**
     * Shape business data from posted form data.
     * This method processes and sanitizes data, handling optional fields gracefully.
     *
     * @param array $posted_data The submitted form data.
     * @param int|null $current_user_id Current user ID (to exclude from IČO uniqueness check).
     * @return array Shaped business data.
     */
    public function shape_business_data(array $posted_data, ?int $current_user_id = null): array {
        // Extract and sanitize fields
        $ico = sanitize_text_field($posted_data['ico'] ?? '');
        $company_name = sanitize_text_field($posted_data['company-name'] ?? '');
        $address = sanitize_text_field($posted_data['address'] ?? '');
        $first_name = sanitize_text_field($posted_data['first-name'] ?? '');
        $last_name = sanitize_text_field($posted_data['last-name'] ?? '');
        
        // Handle position field which comes as array from CF7 select
        $position_raw = $posted_data['position'] ?? '';
        $position = is_array($position_raw) ? sanitize_text_field($position_raw[0] ?? '') : sanitize_text_field($position_raw);
        
        $contact_email = sanitize_email($posted_data['contact-email'] ?? '');
        $promo_code = sanitize_text_field($posted_data['promo-code'] ?? '');

        // Handle business criteria acceptances (CF7 acceptance fields come as arrays)
        $zateplovani_raw = $posted_data['zateplovani'] ?? [];
        $kriteria_obratu_raw = $posted_data['kriteria-obratu'] ?? [];
        
        $zateplovani = is_array($zateplovani_raw) ? !empty($zateplovani_raw) : !empty($zateplovani_raw);
        $kriteria_obratu = is_array($kriteria_obratu_raw) ? !empty($kriteria_obratu_raw) : !empty($kriteria_obratu_raw);

        // Check IČO uniqueness - prevent duplicate registrations
        if ($this->business_manager->is_ico_already_registered($ico, $current_user_id)) {
            $existing_user_id = $this->business_manager->find_user_by_ico($ico);
            $existing_user = get_userdata($existing_user_id);
            
            if ($existing_user) {
                throw new \Exception(sprintf(
                    'IČO %s je již registrované na účet %s. Jeden IČO může být použit pouze jednou.',
                    $ico,
                    $existing_user->user_login
                ));
            } else {
                throw new \Exception(sprintf('IČO %s je již registrované. Jeden IČO může být použit pouze jednou.', $ico));
            }
        }

        // Validate with ARES API
        $ares_data = $this->ares_client->validate_ico_with_ares($ico);
        if (!$ares_data['valid']) {
            throw new \Exception('ARES validace selhala: ' . $ares_data['error']);
        }

        // STRICT ARES ENFORCEMENT: Force exact ARES data when validation succeeds
        $enforced_company_name = $ares_data['data']['obchodniJmeno'];
        $enforced_address = $this->ares_client->extract_ares_address($ares_data['data']);
        
        // Log any discrepancies between submitted and ARES data
        $discrepancies = $this->ares_client->log_ares_discrepancies($ico, [
            'submitted_company_name' => $company_name,
            'submitted_address' => $address,
            'ares_company_name' => $enforced_company_name,
            'ares_address' => $enforced_address
        ]);

        // Build business data structure with ENFORCED ARES data
        return [
            'ico' => $ico,
            'company_name' => $enforced_company_name, // ENFORCED: Use ARES data
            'address' => $enforced_address, // ENFORCED: Use ARES data
            'representative' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $contact_email,
                'position' => $position // Will be empty string if not provided, which is fine
            ],
            'acceptances' => [
                'zateplovani' => $zateplovani,
                'kriteria_obratu' => $kriteria_obratu
            ],
            'promo_code' => $promo_code,
            'validation' => [
                'ares_verified' => true,
                'ares_data' => $ares_data['data'],
                'ares_enforced' => true,
                'discrepancies_found' => count($discrepancies) > 0,
                'discrepancies' => $discrepancies,
                'verified_at' => current_time('mysql')
            ],
            'submitted_at' => current_time('mysql')
        ];
    }

    /**
     * Validate and process business registration data
     *
     * @param array $posted_data Form submission data
     * @param int|null $current_user_id Current user ID (to exclude from IČO uniqueness check)
     * @return array Validated business data
     * @throws \Exception If validation fails
     */
    public function validate_and_process_business_data(array $posted_data, ?int $current_user_id = null): array {
        // Extract and validate required fields
        $ico = sanitize_text_field($posted_data['ico'] ?? '');
        $company_name = sanitize_text_field($posted_data['company-name'] ?? '');
        $address = sanitize_text_field($posted_data['address'] ?? '');
        $first_name = sanitize_text_field($posted_data['first-name'] ?? '');
        $last_name = sanitize_text_field($posted_data['last-name'] ?? '');
        
        // Handle position field which comes as array from CF7 select
        $position_raw = $posted_data['position'] ?? '';
        $position = is_array($position_raw) ? sanitize_text_field($position_raw[0] ?? '') : sanitize_text_field($position_raw);
        
        $contact_email = sanitize_email($posted_data['contact-email'] ?? '');
        $promo_code = sanitize_text_field($posted_data['promo-code'] ?? '');

        // Check required fields
        $required_fields = [
            'ico' => $ico,
            'company-name' => $company_name,
            'address' => $address,
            'first-name' => $first_name,
            'last-name' => $last_name,
            'position' => $position,
            'contact-email' => $contact_email
        ];

        $missing_fields = [];
        foreach ($required_fields as $field_name => $value) {
            // Special handling for select fields and specific field types
            if ($field_name === 'position') {
                // For position field, check if it's one of the valid options
                $valid_positions = ['jednatel', 'ředitel', 'majitel', 'zástupce'];
                if (empty($value) || !in_array($value, $valid_positions, true)) {
                    $missing_fields[] = $field_name . ' (musí být: ' . implode(', ', $valid_positions) . ')';
                }
            } else {
                // Standard empty check for other fields
                if (empty($value)) {
                    $missing_fields[] = $field_name;
                }
            }
        }

        if (!empty($missing_fields)) {
            throw new \Exception('Chybí povinná pole: ' . implode(', ', $missing_fields));
        }

        // Validate email format
        if (!is_email($contact_email)) {
            throw new \Exception('Neplatný formát e-mailové adresy');
        }

        // Validate IČO format
        if (!$this->validate_ico_format($ico)) {
            throw new \Exception('Neplatný formát IČO');
        }

        // Check IČO uniqueness - prevent duplicate registrations
        if ($this->business_manager->is_ico_already_registered($ico, $current_user_id)) {
            $existing_user_id = $this->business_manager->find_user_by_ico($ico);
            $existing_user = get_userdata($existing_user_id);
            
            if ($existing_user) {
                throw new \Exception(sprintf(
                    'IČO %s je již registrované na účet %s. Jeden IČO může být použit pouze jednou.',
                    $ico,
                    $existing_user->user_login
                ));
            } else {
                throw new \Exception(sprintf('IČO %s je již registrované. Jeden IČO může být použit pouze jednou.', $ico));
            }
        }

        // Validate business criteria acceptances (CF7 acceptance fields come as arrays)
        $zateplovani_raw = $posted_data['zateplovani'] ?? [];
        $kriteria_obratu_raw = $posted_data['kriteria-obratu'] ?? [];
        
        $zateplovani = is_array($zateplovani_raw) ? !empty($zateplovani_raw) : !empty($zateplovani_raw);
        $kriteria_obratu = is_array($kriteria_obratu_raw) ? !empty($kriteria_obratu_raw) : !empty($kriteria_obratu_raw);

        if (!$zateplovani || !$kriteria_obratu) {
            throw new \Exception('Musíte souhlasit s obchodními kritérii');
        }

        // Validate IČO with ARES API
        $ares_data = $this->ares_client->validate_ico_with_ares($ico);

        if (!$ares_data['valid']) {
            throw new \Exception('ARES validace selhala: ' . $ares_data['error']);
        }

        // STRICT ARES ENFORCEMENT: Force exact ARES data when validation succeeds
        $enforced_company_name = $ares_data['data']['obchodniJmeno'];
        $enforced_address = $this->ares_client->extract_ares_address($ares_data['data']);
        
        // Log any discrepancies between submitted and ARES data
        $discrepancies = $this->ares_client->log_ares_discrepancies($ico, [
            'submitted_company_name' => $company_name,
            'submitted_address' => $address,
            'ares_company_name' => $enforced_company_name,
            'ares_address' => $enforced_address
        ]);

        // Build business data structure with ENFORCED ARES data
        return [
            'ico' => $ico,
            'company_name' => $enforced_company_name, // ENFORCED: Use ARES data
            'address' => $enforced_address, // ENFORCED: Use ARES data
            'representative' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $contact_email,
                'position' => $position
            ],
            'acceptances' => [
                'zateplovani' => $zateplovani,
                'kriteria_obratu' => $kriteria_obratu
            ],
            'promo_code' => $promo_code,
            'validation' => [
                'ares_verified' => true,
                'ares_data' => $ares_data['data'],
                'ares_enforced' => true,
                'discrepancies_found' => count($discrepancies) > 0,
                'discrepancies' => $discrepancies,
                'verified_at' => current_time('mysql')
            ],
            'submitted_at' => current_time('mysql')
        ];
    }

    /**
     * Validate IČO with ARES API (delegated to AresApiClient)
     *
     * @param string $ico IČO to validate
     * @return array Validation result with 'valid' boolean and 'data'/'error'
     */
    public function validate_ico_with_ares(string $ico): array {
        return $this->ares_client->validate_ico_with_ares($ico);
    }

    /**
     * Validate IČO format
     *
     * @param string $ico IČO to validate
     * @return bool True if format is valid
     */
    public function validate_ico_format(string $ico): bool {
        // Remove spaces and normalize
        $ico = preg_replace('/\s+/', '', $ico);
        
        // Must be 8 digits
        if (!preg_match('/^\d{8}$/', $ico)) {
            return false;
        }

        // Check digit validation (Czech IČO algorithm)
        $weights = [8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        
        for ($i = 0; $i < 7; $i++) {
            $sum += ((int)$ico[$i]) * $weights[$i];
        }
        
        $remainder = $sum % 11;
        $check_digit = $remainder === 0 ? 1 : ($remainder === 1 ? 0 : 11 - $remainder);
        
        return ((int)$ico[7]) === $check_digit;
    }

}