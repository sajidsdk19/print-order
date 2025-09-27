<?php
/**
 * Plugin Name: WooCommerce Print Orders
 * Plugin URI: https://sajidkhan.me
 * Description: Add print functionality to WooCommerce orders in admin dashboard. Print single orders or bulk print multiple orders with professional formatting. Compatible with HPOS.
 * Version: 1.2.0
 * Author: Sajid Khan
 * Author URI: https://sajidkhan.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-print-orders
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * 
 * @package WC_Print_Orders
 * @author Sajid Khan
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Main plugin class
 */
class WC_Print_Orders {
    
    /**
     * Plugin version
     */
    const VERSION = '1.2.0';
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'check_woocommerce'));
        
        // Order actions - Multiple priority levels to ensure it loads
        add_filter('woocommerce_admin_order_actions', array($this, 'add_print_order_action'), 10, 2);
        add_filter('woocommerce_admin_order_actions', array($this, 'add_print_order_action'), 20, 2);
        
        // Add print button to individual order page (next to refund button)
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'add_order_print_button'));
        
        // CSS and scripts
        add_action('admin_head', array($this, 'add_print_order_css'));
        
        // AJAX handler
        add_action('wp_ajax_print_order', array($this, 'handle_print_order_request'));
        
        // Bulk actions - Use HPOS compatible hooks
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_print_action'));
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_bulk_print_orders'), 10, 3);
        
        // Legacy support for older WooCommerce versions
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_print_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_print_orders'), 10, 3);
        
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-print-orders', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . 
             __('WooCommerce Print Orders', 'wc-print-orders') . 
             '</strong> ' . 
             __('requires WooCommerce to be installed and active.', 'wc-print-orders') . 
             '</p></div>';
    }
    
    /**
     * Add print action to order actions dropdown
     */
    public function add_print_order_action($actions, $order) {
        // Ensure we have a valid order object
        if (!$order || !is_object($order)) {
            return $actions;
        }
        
        // Get order ID - compatible with both WC_Order and WP_Post objects
        $order_id = is_a($order, 'WC_Order') ? $order->get_id() : $order->ID;
        
        if (!$order_id) {
            return $actions;
        }
        
        // Add the print action
        $actions['print_order'] = array(
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=print_order&order_id=' . $order_id), 'print_order'),
            'name'      => __('Print Order', 'wc-print-orders'),
            'action'    => "print_order",
        );
        
        return $actions;
    }
    
    /**
     * Add print button to individual order page (next to refund button)
     */
    public function add_order_print_button($order) {
        if (!$order || !is_object($order)) {
            return;
        }
        
        $order_id = is_a($order, 'WC_Order') ? $order->get_id() : $order->ID;
        
        if (!$order_id) {
            return;
        }
        
        $print_url = wp_nonce_url(admin_url('admin-ajax.php?action=print_order&order_id=' . $order_id), 'print_order');
        
        echo '<button type="button" class="button button-primary print-order-btn" onclick="window.open(\'' . esc_url($print_url) . '\', \'_blank\')">';
        echo '<span class="dashicons dashicons-media-document" style="margin-right: 5px;"></span>';
        echo __('Print Order', 'wc-print-orders');
        echo '</button>';
    }
    
    /**
     * Add CSS for the print icon
     */
    public function add_print_order_css() {
        $screen = get_current_screen();
        // Support both HPOS and legacy order screens
        if ($screen && (
            $screen->id === 'edit-shop_order' || 
            $screen->id === 'woocommerce_page_wc-orders' ||
            $screen->base === 'woocommerce_page_wc-orders' ||
            strpos($screen->id, 'wc-orders') !== false ||
            $screen->id === 'shop_order' // Individual order edit page
        )) {
            echo '<style>
                /* Print button styling for orders list */
                .print_order::after {
                    font-family: Dashicons !important;
                    content: "\f179" !important;
                    color: #999 !important;
                    font-size: 16px !important;
                }
                .print_order:hover::after {
                    color: #2271b1 !important;
                }
                
                /* Alternative styling if dashicons don\'t work */
                .wc-action-button-print_order::after {
                    font-family: Dashicons !important;
                    content: "\f179" !important;
                    color: #999 !important;
                }
                .wc-action-button-print_order:hover::after {
                    color: #2271b1 !important;
                }
                
                /* Make sure the action buttons are visible */
                .order_actions .wc-action-button {
                    display: inline-block !important;
                    margin-right: 2px !important;
                }
                
                /* Debug styling to make buttons more visible */
                .order_actions {
                    min-width: 80px !important;
                }
                
                /* Force display if hidden */
                .woocommerce_page_wc-orders .order_actions a[data-tip*="Print"] {
                    display: inline-block !important;
                }
                
                /* Styling for print button on individual order page */
                .print-order-btn {
                    margin-left: 10px !important;
                    background: #2271b1 !important;
                    border-color: #2271b1 !important;
                    color: white !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    text-decoration: none !important;
                }
                
                .print-order-btn:hover {
                    background: #135e96 !important;
                    border-color: #135e96 !important;
                    color: white !important;
                }
                
                .print-order-btn .dashicons {
                    font-size: 16px !important;
                    width: 16px !important;
                    height: 16px !important;
                    line-height: 1 !important;
                }
                
                /* Ensure the button appears inline with other action buttons */
                .wc-order-item-add-action-buttons .print-order-btn {
                    vertical-align: top !important;
                }
            </style>';
        }
    }
    
    /**
     * Handle the print order AJAX request
     */
    public function handle_print_order_request() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'print_order')) {
            wp_die(__('Security check failed', 'wc-print-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to access this page', 'wc-print-orders'));
        }
        
        $order_id = intval($_GET['order_id']);
        
        // Use HPOS compatible method to get order
        $order = wc_get_order($order_id);
        
        if (!$order || !is_a($order, 'WC_Order')) {
            wp_die(__('Order not found', 'wc-print-orders'));
        }
        
        // Generate the printable page
        $this->generate_printable_order_page($order);
        exit;
    }
    
    /**
     * Generate the printable order page
     */
    private function generate_printable_order_page($order) {
        $order_id = $order->get_id();
        $order_date = $order->get_date_created()->date('F j, Y g:i A');
        $order_status = $order->get_status();
        
        // Get billing and shipping addresses
        $billing_address = $order->get_formatted_billing_address();
        $shipping_address = $order->get_formatted_shipping_address();
        
        // Get site info
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php printf(__('Order #%s - Print', 'wc-print-orders'), $order_id); ?></title>
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    margin: 0;
                    padding: 20px;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #333;
                    background: #fff;
                }
                .container {
                    max-width: 800px;
                    margin: 0 auto;
                }
                .header {
                    text-align: center;
                    margin-bottom: 40px;
                    border-bottom: 3px solid #0073aa;
                    padding-bottom: 20px;
                }
                .company-name {
                    font-size: 28px;
                    font-weight: bold;
                    margin-bottom: 5px;
                    color: #0073aa;
                }
                .company-url {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 10px;
                }
                .document-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #333;
                }
                .order-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                    margin-bottom: 30px;
                }
                .info-section {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #0073aa;
                }
                .section-title {
                    font-weight: bold;
                    font-size: 16px;
                    margin-bottom: 15px;
                    color: #0073aa;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .info-row {
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                }
                .info-label {
                    font-weight: 600;
                    min-width: 120px;
                    color: #555;
                }
                .info-value {
                    flex: 1;
                }
                .addresses {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                    margin-bottom: 30px;
                }
                .address-section {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #28a745;
                }
                .address-content {
                    line-height: 1.8;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                    background: #fff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                th {
                    background: linear-gradient(135deg, #0073aa, #005a87);
                    color: white;
                    padding: 15px 12px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #eee;
                }
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                tr:hover {
                    background-color: #e3f2fd;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .product-meta {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
                .totals-table {
                    width: 350px;
                    margin-left: auto;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .totals-table td {
                    padding: 10px 15px;
                }
                .total-row {
                    background: linear-gradient(135deg, #28a745, #20c997) !important;
                    color: white;
                    font-weight: bold;
                    font-size: 16px;
                }
                .status {
                    padding: 6px 12px;
                    border-radius: 20px;
                    display: inline-block;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 11px;
                    letter-spacing: 0.5px;
                }
                .status-completed { background-color: #d4edda; color: #155724; }
                .status-processing { background-color: #fff3cd; color: #856404; }
                .status-pending { background-color: #f8d7da; color: #721c24; }
                .status-cancelled { background-color: #e2e3e5; color: #383d41; }
                .status-on-hold { background-color: #cce7ff; color: #004085; }
                .notes-section {
                    background: #fff3cd;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #ffc107;
                    margin-top: 30px;
                }
                .print-info {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                }
                @media print {
                    body { 
                        margin: 0; 
                        padding: 15px;
                        font-size: 12px;
                    }
                    .container {
                        max-width: none;
                    }
                    .no-print { 
                        display: none !important; 
                    }
                    .header {
                        margin-bottom: 30px;
                    }
                    .order-info, .addresses {
                        gap: 20px;
                        margin-bottom: 20px;
                    }
                    table {
                        box-shadow: none;
                    }
                    .info-section, .address-section {
                        box-shadow: none;
                    }
                }
                @media screen and (max-width: 768px) {
                    .order-info, .addresses {
                        grid-template-columns: 1fr;
                        gap: 20px;
                    }
                    .totals-table {
                        width: 100%;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="company-name"><?php echo esc_html($site_name); ?></div>
                    <div class="company-url"><?php echo esc_url($site_url); ?></div>
                    <div class="document-title"><?php _e('Order Details', 'wc-print-orders'); ?></div>
                </div>

                <div class="order-info">
                    <div class="info-section">
                        <div class="section-title"><?php _e('Order Information', 'wc-print-orders'); ?></div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Order Number:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><strong>#<?php echo $order_id; ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Order Date:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><?php echo $order_date; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Order Status:', 'wc-print-orders'); ?></span>
                            <span class="info-value">
                                <span class="status status-<?php echo $order_status; ?>">
                                    <?php echo wc_get_order_status_name($order_status); ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Payment Method:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><?php echo $order->get_payment_method_title(); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="section-title"><?php _e('Customer Information', 'wc-print-orders'); ?></div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Customer:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Email:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><?php echo $order->get_billing_email(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Phone:', 'wc-print-orders'); ?></span>
                            <span class="info-value"><?php echo $order->get_billing_phone() ?: __('N/A', 'wc-print-orders'); ?></span>
                        </div>
                        <?php if ($order->get_customer_id()) : ?>
                        <div class="info-row">
                            <span class="info-label"><?php _e('Customer ID:', 'wc-print-orders'); ?></span>
                            <span class="info-value">#<?php echo $order->get_customer_id(); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="addresses">
                    <div class="address-section">
                        <div class="section-title"><?php _e('Billing Address', 'wc-print-orders'); ?></div>
                        <div class="address-content">
                            <?php echo $billing_address ? $billing_address : '<em>' . __('No billing address set.', 'wc-print-orders') . '</em>'; ?>
                        </div>
                    </div>
                    <div class="address-section">
                        <div class="section-title"><?php _e('Shipping Address', 'wc-print-orders'); ?></div>
                        <div class="address-content">
                            <?php 
                            if ($shipping_address) {
                                echo $shipping_address;
                            } elseif ($billing_address) {
                                echo '<em>' . __('Same as billing address', 'wc-print-orders') . '</em>';
                            } else {
                                echo '<em>' . __('No shipping address set.', 'wc-print-orders') . '</em>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="section-title"><?php _e('Order Items', 'wc-print-orders'); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Product', 'wc-print-orders'); ?></th>
                            <th><?php _e('SKU', 'wc-print-orders'); ?></th>
                            <th class="text-center"><?php _e('Qty', 'wc-print-orders'); ?></th>
                            <th class="text-right"><?php _e('Unit Price', 'wc-print-orders'); ?></th>
                            <th class="text-right"><?php _e('Total', 'wc-print-orders'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item_id => $item) : 
                            $product = $item->get_product();
                            $sku = $product ? $product->get_sku() : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($item->get_name()); ?></strong>
                                <?php 
                                // Display item meta (variations, etc.)
                                $item_meta = $item->get_formatted_meta_data();
                                if (!empty($item_meta)) {
                                    echo '<div class="product-meta">';
                                    foreach ($item_meta as $meta) {
                                        echo '<div>' . esc_html($meta->display_key) . ': ' . esc_html($meta->display_value) . '</div>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </td>
                            <td><?php echo $sku ? esc_html($sku) : '<em>' . __('N/A', 'wc-print-orders') . '</em>'; ?></td>
                            <td class="text-center"><?php echo $item->get_quantity(); ?></td>
                            <td class="text-right"><?php echo wc_price($order->get_item_subtotal($item, false, true)); ?></td>
                            <td class="text-right"><strong><?php echo wc_price($order->get_line_subtotal($item, false, true)); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table class="totals-table">
                    <tr>
                        <td><strong><?php _e('Subtotal:', 'wc-print-orders'); ?></strong></td>
                        <td class="text-right"><?php echo wc_price($order->get_subtotal()); ?></td>
                    </tr>
                    <?php if ($order->get_total_shipping() > 0) : ?>
                    <tr>
                        <td><strong><?php _e('Shipping:', 'wc-print-orders'); ?></strong></td>
                        <td class="text-right"><?php echo wc_price($order->get_shipping_total()); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order->get_total_tax() > 0) : ?>
                    <tr>
                        <td><strong><?php _e('Tax:', 'wc-print-orders'); ?></strong></td>
                        <td class="text-right"><?php echo wc_price($order->get_total_tax()); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order->get_total_discount() > 0) : ?>
                    <tr>
                        <td><strong><?php _e('Discount:', 'wc-print-orders'); ?></strong></td>
                        <td class="text-right">-<?php echo wc_price($order->get_total_discount()); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong><?php _e('Total:', 'wc-print-orders'); ?></strong></td>
                        <td class="text-right"><strong><?php echo wc_price($order->get_total()); ?></strong></td>
                    </tr>
                </table>

                <?php 
                // Display order notes if any
                $customer_note = $order->get_customer_note();
                if (!empty($customer_note)) : ?>
                <div class="notes-section">
                    <div class="section-title"><?php _e('Customer Notes', 'wc-print-orders'); ?></div>
                    <p><?php echo nl2br(esc_html($customer_note)); ?></p>
                </div>
                <?php endif; ?>

                <div class="print-info">
                    <?php printf(__('Printed on %s', 'wc-print-orders'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
                    <br>
                    <?php _e('Generated by WooCommerce Print Orders plugin', 'wc-print-orders'); ?>
                </div>
            </div>

            <script>
                // Auto-print when page loads
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                });
                
                // Close window after printing
                window.addEventListener('afterprint', function() {
                    window.close();
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Add bulk action for printing multiple orders
     */
    public function add_bulk_print_action($bulk_actions) {
        $bulk_actions['print_orders'] = __('Print Orders', 'wc-print-orders');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk print action
     */
    public function handle_bulk_print_orders($redirect_to, $action, $post_ids) {
        if ($action !== 'print_orders') {
            return $redirect_to;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return $redirect_to;
        }
        
        // Ensure we have valid order IDs
        $order_ids = array();
        foreach ($post_ids as $id) {
            $order = wc_get_order($id);
            if ($order && is_a($order, 'WC_Order')) {
                $order_ids[] = $id;
            }
        }
        
        if (empty($order_ids)) {
            return add_query_arg('bulk_print_error', '1', $redirect_to);
        }
        
        // Generate print page for multiple orders
        $this->generate_bulk_print_page($order_ids);
        exit;
    }
    
    /**
     * Generate bulk print page
     */
    private function generate_bulk_print_page($order_ids) {
        if (empty($order_ids)) {
            wp_die(__('No orders selected for printing.', 'wc-print-orders'));
        }
        
        $site_name = get_bloginfo('name');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Bulk Order Print', 'wc-print-orders'); ?></title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    font-size: 13px; 
                    line-height: 1.5;
                    color: #333;
                }
                .order-summary {
                    page-break-after: always; 
                    margin-bottom: 40px;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background: #f9f9f9;
                }
                .order-summary:last-child {
                    page-break-after: auto;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #0073aa; 
                    padding-bottom: 15px; 
                }
                .order-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: #0073aa;
                    margin-bottom: 10px;
                }
                .order-meta {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 15px;
                }
                .meta-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 5px 0;
                    border-bottom: 1px dotted #ccc;
                }
                .meta-label {
                    font-weight: 600;
                    color: #555;
                }
                .status {
                    padding: 4px 8px;
                    border-radius: 15px;
                    font-size: 11px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .status-completed { background-color: #d4edda; color: #155724; }
                .status-processing { background-color: #fff3cd; color: #856404; }
                .status-pending { background-color: #f8d7da; color: #721c24; }
                .status-cancelled { background-color: #e2e3e5; color: #383d41; }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .order-summary { 
                        box-shadow: none; 
                        border: 1px solid #ccc;
                        margin-bottom: 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo esc_html($site_name); ?></h1>
                <h2><?php _e('Bulk Order Print Summary', 'wc-print-orders'); ?></h2>
                <p><?php printf(__('Total Orders: %d', 'wc-print-orders'), count($order_ids)); ?></p>
            </div>

            <?php foreach ($order_ids as $order_id) : 
                $order = wc_get_order($order_id);
                if (!$order || !is_a($order, 'WC_Order')) continue;
            ?>
            <div class="order-summary">
                <div class="order-title"><?php printf(__('Order #%s', 'wc-print-orders'), $order->get_id()); ?></div>
                
                <div class="order-meta">
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Date:', 'wc-print-orders'); ?></span>
                        <span><?php echo $order->get_date_created()->date('F j, Y g:i A'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Customer:', 'wc-print-orders'); ?></span>
                        <span><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Status:', 'wc-print-orders'); ?></span>
                        <span class="status status-<?php echo $order->get_status(); ?>">
                            <?php echo wc_get_order_status_name($order->get_status()); ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Total:', 'wc-print-orders'); ?></span>
                        <span><strong><?php echo wc_price($order->get_total()); ?></strong></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Items:', 'wc-print-orders'); ?></span>
                        <span><?php echo $order->get_item_count(); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Payment:', 'wc-print-orders'); ?></span>
                        <span><?php echo $order->get_payment_method_title(); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <script>
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                });
                
                window.addEventListener('afterprint', function() {
                    window.close();
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check for WooCommerce
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WooCommerce to be installed and active.', 'wc-print-orders'));
        }
        
        // Check WooCommerce version
        if (version_compare(WC()->version, '6.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WooCommerce version 6.0 or higher.', 'wc-print-orders'));
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Add activation timestamp
        update_option('wc_print_orders_activated', time());
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
        
        // Remove activation timestamp
        delete_option('wc_print_orders_activated');
    }
    
    /**
     * Add action links to plugin page
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-orders') . '">' . __('Orders', 'wc-print-orders') . '</a>',
            '<a href="https://sajidkhan.me" target="_blank">' . __('Support', 'wc-print-orders') . '</a>',
        );
        return array_merge($plugin_links, $links);
    }
}

// Initialize the plugin
WC_Print_Orders::get_instance();

/**
 * Check for plugin updates and HPOS compatibility
 */
add_action('plugins_loaded', function() {
    if (class_exists('WC_Print_Orders')) {
        // Plugin loaded successfully
        do_action('wc_print_orders_loaded');
    }
});

/**
 * Add admin notice for bulk print errors
 */
add_action('admin_notices', function() {
    if (isset($_GET['bulk_print_error']) && $_GET['bulk_print_error'] === '1') {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . __('No valid orders found to print.', 'wc-print-orders') . '</p>';
        echo '</div>';
    }
});

/**
 * Helper function to check if HPOS is enabled
 */
function wc_print_orders_is_hpos_enabled() {
    return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
           \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}