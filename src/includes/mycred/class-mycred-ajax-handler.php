<?php
/**
 * MyCred AJAX Handler Class
 *
 * Handles AJAX requests for cart state and button updates
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MyCred_Ajax_Handler {
    
    /**
     * Cart calculator instance
     *
     * @var MyCred_Cart_Calculator
     */
    private $calculator;
    
    /**
     * Constructor
     *
     * @param MyCred_Cart_Calculator $calculator Cart calculator instance
     */
    public function __construct(MyCred_Cart_Calculator $calculator) {
        $this->calculator = $calculator;
    }
    
    /**
     * Initialize WordPress AJAX hooks
     */
    public function init_hooks() {
        // Handle both logged-in and non-logged-in users
        add_action('wp_ajax_mycred_get_cart_state', [$this, 'get_cart_state']);
        add_action('wp_ajax_nopriv_mycred_get_cart_state', [$this, 'get_cart_state']);
        
        // Handle button refresh requests
        add_action('wp_ajax_mycred_refresh_buttons', [$this, 'refresh_buttons']);
        add_action('wp_ajax_nopriv_mycred_refresh_buttons', [$this, 'refresh_buttons']);
    }
    
    /**
     * Get current cart state for JavaScript
     */
    public function get_cart_state() {
        // Verify nonce for security
        if (!$this->verify_nonce()) {
            wp_die('Security check failed', 'Unauthorized', ['response' => 401]);
        }
        
        try {
            $user_id = get_current_user_id();
            
            // If user is not logged in, return minimal data
            if (!$user_id) {
                wp_send_json_success([
                    'userBalance' => 0,
                    'cartTotal' => 0,
                    'products' => []
                ]);
                return;
            }
            
            // Get user balance and cart total
            $user_balance = $this->calculator->get_user_balance($user_id);
            $cart_total = $this->calculator->get_cart_points_total();
            
            // Get all products in cart and their details
            $cart_products = $this->get_cart_products_data();
            
            // Get visible products on current page (for optimization)
            $visible_products = $this->get_visible_products_data();
            
            // Merge cart and visible products
            $all_products = array_merge($cart_products, $visible_products);
            
            $response_data = [
                'userBalance' => $user_balance,
                'cartTotal' => $cart_total,
                'products' => $all_products,
                'timestamp' => time()
            ];
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            error_log('myCred AJAX Error: ' . $e->getMessage());
            wp_send_json_error('Failed to get cart state: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle button refresh requests
     */
    public function refresh_buttons() {
        // Just return cart state - the frontend will handle the refresh
        $this->get_cart_state();
    }
    
    /**
     * Get cart products data
     */
    private function get_cart_products_data() {
        $products = [];
        
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return $products;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            
            if (!$product) continue;
            
            $product_id = $product->get_id();
            $product_cost = $this->calculator->get_product_point_cost($product);
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 0;
            
            $products[$product_id] = [
                'id' => $product_id,
                'cost' => $product_cost,
                'quantity' => $quantity,
                'inCart' => true,
                'totalCost' => $product_cost * $quantity
            ];
        }
        
        return $products;
    }
    
    /**
     * Get visible products data (from current page)
     * This is an optimization to avoid querying all products
     */
    private function get_visible_products_data() {
        $products = [];
        
        // Get product IDs from POST data if provided (optional optimization)
        $product_ids = isset($_POST['visible_products']) ? 
            array_map('intval', (array) $_POST['visible_products']) : [];
        
        // If no specific products requested, return empty array
        // The JavaScript will work with cart data only
        if (empty($product_ids)) {
            return $products;
        }
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            
            if (!$product) continue;
            
            $product_cost = $this->calculator->get_product_point_cost($product);
            
            // Check if this product is already in cart
            $in_cart = false;
            $cart_quantity = 0;
            
            if (function_exists('WC') && WC()->cart) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    if ($cart_item['product_id'] == $product_id) {
                        $in_cart = true;
                        $cart_quantity = $cart_item['quantity'];
                        break;
                    }
                }
            }
            
            $products[$product_id] = [
                'id' => $product_id,
                'cost' => $product_cost,
                'quantity' => $cart_quantity,
                'inCart' => $in_cart,
                'totalCost' => $product_cost * $cart_quantity
            ];
        }
        
        return $products;
    }
    
    /**
     * Verify AJAX nonce
     */
    private function verify_nonce() {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (empty($nonce)) {
            return false;
        }
        
        return wp_verify_nonce($nonce, 'wp_ajax_nonce');
    }
    
    /**
     * Get affordability status for a specific product
     *
     * @param int $product_id Product ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if affordable
     */
    public function is_product_affordable($product_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return true; // Default to affordable for non-logged-in users
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return true;
        }
        
        return $this->calculator->can_user_afford_product($product, $user_id);
    }
    
    /**
     * Log debug information
     *
     * @param string $message Debug message
     * @param mixed $data Additional data to log
     */
    private function debug_log($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = 'myCred AJAX: ' . $message;
            if ($data !== null) {
                $log_message .= ' - Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }
}