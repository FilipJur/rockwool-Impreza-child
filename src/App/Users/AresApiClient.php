<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * ARES API Client - Handles communication with Czech ARES business registry
 *
 * Provides methods for validating IČO (business identification numbers)
 * with the official Czech ARES API and extracting business information.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AresApiClient {

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
    public function extract_ares_address(array $ares_data): string {
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
    public function log_ares_discrepancies(string $ico, array $comparison_data): array {
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