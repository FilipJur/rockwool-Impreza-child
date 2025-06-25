<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Business Data Processor - Handles processing and shaping of business data
 *
 * Processes form submission data into structured business data format,
 * handles ARES enforcement, and manages data validation.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BusinessDataProcessor {

    public function __construct(
        private AresApiClient $ares_client,
        private BusinessDataManager $business_manager
    ) {}

    /**
     * Shape business data from posted form data.
     * This method processes and sanitizes data, handling optional fields gracefully.
     *
     * @param array $posted_data The submitted form data.
     * @param int|null $current_user_id Current user ID (to exclude from IČO uniqueness check).
     * @return array Shaped business data.
     */
    public function shape_business_data(array $posted_data, ?int $current_user_id = null): array {
        // Suppress unused variable warning - parameter kept for potential IČO validation re-enabling
        unset($current_user_id);
        
        // Extract and sanitize fields
        $ico = sanitize_text_field($posted_data['ico'] ?? '');
        $company_name = sanitize_text_field($posted_data['company-name'] ?? '');
        
        // Handle both single and destructured address formats
        $address = sanitize_text_field($posted_data['address'] ?? '');
        $street_address = sanitize_text_field($posted_data['street-address'] ?? '');
        $city = sanitize_text_field($posted_data['city'] ?? '');
        $postal_code = sanitize_text_field($posted_data['postal-code'] ?? '');
        
        // Determine if we have destructured address data
        $has_destructured_address = !empty($street_address) || !empty($city) || !empty($postal_code);
        
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

        // Check IČO uniqueness - DISABLED to allow multiple users with same IČO
        // if ($this->business_manager->is_ico_already_registered($ico, $current_user_id)) {
        //     $existing_user_id = $this->business_manager->find_user_by_ico($ico);
        //     $existing_user = get_userdata($existing_user_id);
        //     
        //     if ($existing_user) {
        //         throw new \Exception(sprintf(
        //             'IČO %s je již registrované na účet %s. Jeden IČO může být použit pouze jednou.',
        //             $ico,
        //             $existing_user->user_login
        //         ));
        //     } else {
        //         throw new \Exception(sprintf('IČO %s je již registrované. Jeden IČO může být použit pouze jednou.', $ico));
        //     }
        // }

        // Validate with ARES API
        $ares_data = $this->ares_client->validate_ico_with_ares($ico);
        if (!$ares_data['valid']) {
            throw new \Exception('ARES validace selhala: ' . $ares_data['error']);
        }

        // STRICT ARES ENFORCEMENT: Force exact ARES data when validation succeeds
        $enforced_company_name = $ares_data['data']['obchodniJmeno'];
        $enforced_address = $this->ares_client->extract_ares_address($ares_data['data']);
        $enforced_destructured_address = $this->ares_client->extract_destructured_address($ares_data['data']);
        
        // Prepare submitted address for comparison based on form format
        $submitted_address_for_comparison = $has_destructured_address 
            ? trim($street_address . ' ' . $city . ' ' . $postal_code)
            : $address;
        
        // Log any discrepancies between submitted and ARES data
        $discrepancies = $this->ares_client->log_ares_discrepancies($ico, [
            'submitted_company_name' => $company_name,
            'submitted_address' => $submitted_address_for_comparison,
            'ares_company_name' => $enforced_company_name,
            'ares_address' => $enforced_address
        ]);

        // Build business data structure with ENFORCED ARES data
        return [
            'ico' => $ico,
            'company_name' => $enforced_company_name, // ENFORCED: Use ARES data
            'address' => $enforced_address, // ENFORCED: Use ARES data (backward compatibility)
            // Include destructured address components
            'street_address' => $enforced_destructured_address['street_address'],
            'city' => $enforced_destructured_address['city'],
            'postal_code' => $enforced_destructured_address['postal_code'],
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
                'verified_at' => current_time('mysql'),
                'has_destructured_address' => $has_destructured_address
            ],
            'submitted_at' => current_time('mysql')
        ];
    }
}