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
        private BusinessDataManager $business_manager
    ) {}

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
        $ares_data = $this->validate_ico_with_ares($ico);

        if (!$ares_data['valid']) {
            throw new \Exception('ARES validace selhala: ' . $ares_data['error']);
        }

        // STRICT ARES ENFORCEMENT: Force exact ARES data when validation succeeds
        $enforced_company_name = $ares_data['data']['obchodniJmeno'];
        $enforced_address = $this->extract_ares_address($ares_data['data']);
        
        // Log any discrepancies between submitted and ARES data
        $discrepancies = $this->log_ares_discrepancies($ico, [
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
        $check_digit = $remainder < 2 ? $remainder : 11 - $remainder;
        
        return ((int)$ico[7]) === $check_digit;
    }

    /**
     * Validate IČO with ARES API
     *
     * @param string $ico IČO to validate
     * @return array Validation result with 'valid' boolean and 'data'/'error'
     */
    public function validate_ico_with_ares(string $ico): array {
        $ares_url = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}";
        
        $response = wp_remote_get($ares_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/MistrFachman Business Registration'
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => 'Chyba připojení k ARES API: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 404) {
            return [
                'valid' => false,
                'error' => 'IČO nebylo nalezeno v ARES'
            ];
        }

        if ($status_code !== 200) {
            return [
                'valid' => false,
                'error' => "ARES API chyba (HTTP {$status_code})"
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['obchodniJmeno'])) {
            return [
                'valid' => false,
                'error' => 'Neplatná odpověď z ARES API'
            ];
        }

        return [
            'valid' => true,
            'data' => [
                'obchodniJmeno' => $data['obchodniJmeno'],
                'sidlo' => $data['sidlo'] ?? null,
                'pravniForma' => $data['pravniForma'] ?? null,
                'datumVzniku' => $data['datumVzniku'] ?? null,
                'verified_at' => current_time('mysql')
            ]
        ];
    }

    /**
     * Extract formatted address from ARES data
     *
     * @param array $ares_data ARES API response data
     * @return string Formatted address or empty string
     */
    private function extract_ares_address(array $ares_data): string {
        if (!isset($ares_data['sidlo'])) {
            return '';
        }

        $sidlo = $ares_data['sidlo'];
        
        // Check if textovaAdresa exists (preferred format)
        if (isset($sidlo['textovaAdresa']) && !empty($sidlo['textovaAdresa'])) {
            return $sidlo['textovaAdresa'];
        }

        // Build address from components
        $address_parts = [];
        
        // Street and number
        if (isset($sidlo['nazevUlice'])) {
            $street = $sidlo['nazevUlice'];
            if (isset($sidlo['cisloDomovni'])) {
                $street .= ' ' . $sidlo['cisloDomovni'];
                if (isset($sidlo['cisloOrientacni'])) {
                    $street .= '/' . $sidlo['cisloOrientacni'];
                }
            }
            $address_parts[] = $street;
        }

        // City and postal code
        if (isset($sidlo['nazevObce'])) {
            $city_line = $sidlo['nazevObce'];
            if (isset($sidlo['psc'])) {
                $city_line = $sidlo['psc'] . ' ' . $city_line;
            }
            $address_parts[] = $city_line;
        }

        return implode(', ', $address_parts);
    }

    /**
     * Log discrepancies between submitted data and ARES data
     *
     * @param string $ico IČO being validated
     * @param array $comparison_data Comparison data
     * @return array List of discrepancies found
     */
    private function log_ares_discrepancies(string $ico, array $comparison_data): array {
        $discrepancies = [];

        // Compare company name
        if ($comparison_data['submitted_company_name'] !== $comparison_data['ares_company_name']) {
            $discrepancies[] = [
                'field' => 'company_name',
                'submitted' => $comparison_data['submitted_company_name'],
                'ares_value' => $comparison_data['ares_company_name']
            ];
        }

        // Compare address (normalize whitespace for comparison)
        $submitted_address_normalized = preg_replace('/\s+/', ' ', trim($comparison_data['submitted_address']));
        $ares_address_normalized = preg_replace('/\s+/', ' ', trim($comparison_data['ares_address']));
        
        if ($submitted_address_normalized !== $ares_address_normalized) {
            $discrepancies[] = [
                'field' => 'address',
                'submitted' => $comparison_data['submitted_address'],
                'ares_value' => $comparison_data['ares_address']
            ];
        }

        // Log discrepancies if found
        if (!empty($discrepancies)) {
            mycred_debug('ARES data discrepancies detected - enforcing ARES data', [
                'ico' => $ico,
                'discrepancies_count' => count($discrepancies),
                'discrepancies' => $discrepancies,
                'enforcement_action' => 'Used ARES data instead of submitted data'
            ], 'users', 'warning');
        } else {
            mycred_debug('ARES data matches submitted data perfectly', [
                'ico' => $ico,
                'company_name' => $comparison_data['ares_company_name'],
                'address' => $comparison_data['ares_address']
            ], 'users', 'info');
        }

        return $discrepancies;
    }
}