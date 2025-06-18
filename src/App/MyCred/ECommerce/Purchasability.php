<?php

declare(strict_types=1);

namespace MistrFachman\MyCred\ECommerce;

/**
 * MyCred Purchasability Class
 *
 * Handles product purchasability logic based on myCred points
 * and current cart contents using the new cart-aware architecture.
 *
 * This class now uses the AvailableBalanceCalculator for simple,
 * consistent affordability checks across all contexts.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Purchasability {

    public function __construct(
        private BalanceCalculator $balance_calculator,
        private Manager $manager
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        // Display-level purchasability (hides add to cart button)
        add_filter('woocommerce_is_purchasable', [$this, 'check_product_affordability'], 10, 2);
        add_filter('woocommerce_variation_is_purchasable', [$this, 'check_product_affordability'], 10, 2);

        // Cart-level validation (prevents actual addition to cart)
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);

        // Checkout-level validation (prevents checkout if cart exceeds balance)
        add_action('woocommerce_check_cart_items', [$this, 'validate_cart_on_checkout']);

        // Add AJAX error handling for when products can't be added due to insufficient points
        add_action('wp_ajax_woocommerce_add_to_cart', [$this, 'handle_ajax_add_to_cart_error'], 5);
        add_action('wp_ajax_nopriv_woocommerce_add_to_cart', [$this, 'handle_ajax_add_to_cart_error'], 5);
    }

    /**
     * Check if product is affordable based on myCred points and current cart
     *
     * This filter now only blocks display for completely unaffordable products.
     * The main validation happens at the add-to-cart level to provide better UX.
     *
     * @param bool $is_purchasable Current purchasability status
     * @param \WC_Product $product Product object
     * @return bool Modified purchasability status
     */
    public function check_product_affordability(bool $is_purchasable, \WC_Product $product): bool {
        mycred_debug('Purchasability filter called', [
            'product_id' => $product->get_id(),
            'incoming_purchasable' => $is_purchasable,
            'page_context' => is_cart() ? 'cart' : (is_checkout() ? 'checkout' : 'other')
        ], 'purchasability', 'verbose');

        if (!$is_purchasable || !$this->should_check_affordability($product)) {
            return $is_purchasable;
        }

        // PRIORITY CHECK: Block all purchases for pending users
        // This takes precedence over affordability checks
        $user_id = get_current_user_id();
        if ($this->is_pending_user($user_id)) {
            mycred_debug('Blocking purchase for pending user', [
                'user_id' => $user_id,
                'product_id' => $product->get_id(),
                'role' => 'pending_approval'
            ], 'purchasability', 'info');
            return false;
        }

        try {
            // CONTEXT-AWARE LOGIC
            if (is_cart() || is_checkout() || (function_exists('WC') && \WC()->is_rest_api_request())) {
                // In-Cart/Checkout Context: The key check is whether the *entire cart* is affordable.
                // This prevents an item in the cart from making itself un-purchasable.
                $user_balance = $this->manager->get_user_balance();
                $cart_total = $this->balance_calculator->get_cart_total();
                $can_afford = $cart_total <= $user_balance;

                mycred_debug('myCred purchasability decision (In-Cart Context)', [
                    'product_id' => $product->get_id(),
                    'user_balance' => $user_balance,
                    'cart_total' => $cart_total,
                    'is_affordable' => $can_afford
                ], 'purchasability', 'info');

                return $can_afford;

            } else {
                // Shop/Other Context: The check is whether this *specific item* can be added.
                // This is where the "limitedness" of available points is enforced.
                $can_afford = $this->balance_calculator->can_afford_product($product);

                mycred_debug('myCred purchasability decision (Shop/Add-to-Cart Context)', [
                    'product_id' => $product->get_id(),
                    'is_affordable' => $can_afford
                ], 'purchasability', 'info');

                return $can_afford;
            }

        } catch (\Exception $e) {
            mycred_debug('Error in purchasability check, defaulting to allow', [
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ], 'purchasability', 'error');

            return $is_purchasable; // Fail-safe: if something breaks, allow the purchase.
        }
    }

    /**
     * Determine if we should check affordability for this product
     */
    private function should_check_affordability(\WC_Product $product): bool {
        if (!is_user_logged_in() || !function_exists('mycred')) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has pending approval role
     */
    private function is_pending_user(int $user_id): bool {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        return in_array('pending_approval', $user->roles, true);
    }

    /**
     * Validate product addition to cart (prevents actual cart addition)
     *
     * This is called when products are added to cart via any method:
     * - Add to cart buttons
     * - Direct URLs (?add-to-cart=123)
     * - AJAX requests
     * - Programmatic additions
     *
     * @param bool $passed Validation status from previous filters
     * @param int $product_id Product ID being added
     * @param int $quantity Quantity being added
     * @return bool True if product can be added to cart
     */
    public function validate_add_to_cart(bool $passed, int $product_id, int $quantity): bool {
        mycred_debug('Add to cart validation called', [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'passed_before' => $passed,
        ], 'cart_validation', 'info');

        $product = wc_get_product($product_id);
        if (!$passed || !$product || !$this->should_check_affordability($product)) {
            return $passed;
        }

        // PRIORITY CHECK: Block pending users from adding to cart
        $user_id = get_current_user_id();
        if ($this->is_pending_user($user_id)) {
            // Get user's registration status for specific message
            $message = 'Registrace probíhá. Dokončete registraci pro možnost nákupu.';
            
            if (class_exists('\MistrFachman\Users\Manager')) {
                $users_manager = \MistrFachman\Users\Manager::get_instance();
                $user_service = $users_manager->get_user_service();
                $status = $user_service->get_user_registration_status($user_id);
                
                $message = match ($status) {
                    'needs_form' => 'Dokončete registraci pro možnost nákupu.',
                    'awaiting_review' => 'Vaše registrace čeká na schválení administrátorem.',
                    default => 'Registrace probíhá. Dokončete registraci pro možnost nákupu.'
                };
            }
            
            wc_add_notice($message, 'error');
            
            mycred_debug('Blocking add to cart for pending user', [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'role' => 'pending_approval'
            ], 'purchasability', 'info');
            
            return false;
        }

        try {
            $product_cost = (float) $product->get_price() * $quantity;
            $current_available = $this->balance_calculator->get_available_points($user_id, true);

            if ($product_cost > $current_available) {
                $message = sprintf(
                    'Nedostatek bodů pro přidání "%s" do košíku. Potřeba: %s bodů, Dostupné: %s bodů.',
                    $product->get_name(),
                    number_format($product_cost),
                    number_format(floor($current_available))
                );
                wc_add_notice($message, 'error');
                return false;
            }

            return true;

        } catch (\Exception $e) {
            mycred_debug('Error in add to cart validation, allowing by default', [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ], 'purchasability', 'error');

            return $passed;
        }
    }

    /**
     * Validate cart contents during checkout
     *
     * This prevents users from proceeding to checkout if their cart
     * exceeds their available points balance.
     */
    public function validate_cart_on_checkout(): void {
        if (!is_user_logged_in() || !function_exists('mycred')) {
            return;
        }

        try {
            $user_id = get_current_user_id();
            $user_balance = $this->manager->get_user_balance($user_id); // Use manager for raw balance
            $cart_total = $this->balance_calculator->get_cart_total(); // Use calculator for cart total

            if ($cart_total > $user_balance) {
                $message = sprintf(
                    'Celková hodnota košíku (%s bodů) překračuje váš dostupný zůstatek (%s bodů). Před pokračováním prosím odeberte některé položky.',
                    number_format($cart_total),
                    number_format($user_balance)
                );
                wc_add_notice($message, 'error');
            }
        } catch (\Exception $e) {
            mycred_debug('Error in checkout validation', ['error' => $e->getMessage()], 'purchasability', 'error');
        }
    }

    /**
     * Handle AJAX add to cart errors for insufficient points
     *
     * This runs early in the AJAX process to provide better error messages
     * when products can't be added due to myCred point restrictions.
     */
    public function handle_ajax_add_to_cart_error(): void {
        // This validation is now fully handled by the `woocommerce_add_to_cart_validation` hook,
        // which fires on AJAX requests. This method's logic is now redundant.
        // We'll keep the method stub to avoid breaking any existing action calls but it will do nothing.
        return;
    }

    /**
     * Check if specific product is affordable for user
     *
     * Public method for external use.
     */
    public function is_product_affordable(\WC_Product|int $product, ?int $user_id = null): bool {
        if (!$product instanceof \WC_Product) {
            $product = wc_get_product($product);
        }

        if (!$product || !$this->should_check_affordability($product)) {
            return true; // Default to affordable if we can't check
        }

        return $this->balance_calculator->can_afford_product($product, $user_id);
    }
}