<?php
    // Add CSS for the Oney Netopia Add-on
    add_action('wp_enqueue_scripts', function () {

        
        // $plugin_url = plugin_dir_url(__FILE__);
        $plugin_url = plugin_dir_url(__DIR__);
        $version = '1.0'; // Or use filemtime() for cache busting

        wp_register_style(
            'oney-netopia-addon-css',
            // untrailingslashit($plugin_url) . '../../../v2/css/oney-netopia-addon.css',
            untrailingslashit($plugin_url) . '/css/oney-netopia-addon.css',
            array(),
            $version
        );

        wp_enqueue_style('oney-netopia-addon-css');

        // Enqueue JS
        wp_register_script(
            'bnpl-netopia-addon-js',
            // untrailingslashit($plugin_url) . '../../../v2/js/bnpl-netopia-addon.js',
            untrailingslashit($plugin_url) . '/js/bnpl-netopia-addon.js',
            array(), // Add 'jquery' if needed
            $version,
            true // Load in footer
        );
        wp_enqueue_script('bnpl-netopia-addon-js');

    });

// /** The Clasic Checkout */	
// add_filter('woocommerce_available_payment_gateways', 'oneynetopia_checkout_classic');
// function oneynetopia_checkout_classic($gateways){
//     if (is_admin()) {
//         return $gateways;
//     }

//     if (!isset($gateways['netopiapayments'])) {
//         return $gateways;
//     }

//     $gateway = $gateways['netopiapayments'];

//     // Validate that payment_methods is an array
//     if (!is_array($gateway->settings['payment_methods'])) {
//         return $gateways;
//     }

//     // Skip if 'bnpl.oney' is not enabled
//     if (!in_array('bnpl.oney', $gateway->settings['payment_methods'])) {
//         return $gateways;
//     }

//     // Get cart total safely
//     $cart = WC()->cart;
//     if (is_null($cart) || is_null($cart->total)) {
//         return $gateways;
//     }

//     $cart_total = $cart->total;
//     $min_purchase_amount = 450;
//     $max_purchase_amount = 12000;
//     $remaining_amount = max(0, $min_purchase_amount - $cart_total);
//     $progress_percentage = min(($cart_total / $min_purchase_amount) * 100, 100);
//     $cart_total_divided_by_3 = number_format($cart_total / 3, 2);
//     $cart_total_divided_by_4 = number_format($cart_total / 4, 2);

//     // START: Build the final description
//     $extra_desc = '';
//     $base_desc = '<div class="oney-netopia-payment-progress-bar oney-netopia-style-bordered" style="display:block">
// 				    <div class="oney-netopia-progress-bar oney-netopia-free-progress-bar">
//                         <p>Comenzile de minim 450 și maxim 12.000 de RON pot fi plătite în <strong>3-4 rate fără dobândă</strong> direct cu cardul tău de debit!</p>';

//     if ($cart_total >= $min_purchase_amount && $cart_total <= $max_purchase_amount) {
//         $extra_desc = '
//         <div class="oney-netopia-progress-msg">
//             <div class="oney-netopia-progress-msg"><span id="acord-remaining-amount">Comanda ta poate fi plătită</span><span class="oney-netopia-remaining-amount"></span><span id="post-acord-remaining-amount"></span> în 3 sau 4 rate prin <img src="'.NTP_PLUGIN_DIR.'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;"></div>
//             <div class="oney-netopia-rates-wrapper">
//                 <div class="oney-netopia-rate">
//                     <span>3 Rate: </span>
//                     <span class="oney-netopia-rate-value"><strong>' . $cart_total_divided_by_3 . '</strong>/lună</span>
//                 </div>
//                 <div class="oney-netopia-rate">
//                     <span>4 Rate: </span>
//                     <span class="oney-netopia-rate-value"><strong>' . $cart_total_divided_by_4 . '</strong>/lună</span>
//                 </div>
//             </div>
//         </div>';
//     } else if($remaining_amount < $min_purchase_amount ){
//         $extra_desc = '
//         <div class="oney-netopia-progress-msg">
//             <div class="cumpara-text">
//                 <span id="acord-remaining-amount">Coșului tău îi lipsesc încă</span>
//                 <span class="oney-netopia-remaining-amount">' . number_format($remaining_amount, 2) . ' RON</span>
//                 <span id="post-acord-remaining-amount">pentru a putea plăti</span> în 3 sau 4 rate prin 
//                 <img src="' . NTP_PLUGIN_DIR . 'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;">
//             </div>
//         </div>';
//     }

//     $extra_desc .= '<div class="oney-netopia-progress-area">
//                         <div id="oney-netopia-progress-bar" class="oney-netopia-progress-bar" style="width: ' . $progress_percentage . '%"></div>
//                     </div>';

    
//     // Set full description — no appending
//     $gateway->description = $base_desc . $extra_desc;

//     do_action('woocommerce_available_payment_gateways_customized');

//     return $gateways;
// }



/** Product Items */
add_action('woocommerce_after_add_to_cart_form', 'oneynetopia_test_message_after_cart_form');
function oneynetopia_test_message_after_cart_form() {
global $product;

    if (function_exists('WC') && null === WC()->cart) {
        wc_load_cart();
    }

    $settings = get_option('woocommerce_netopiapayments_settings', []);

    if (
        empty($settings) ||
        empty($settings['payment_methods']) ||
        !in_array('bnpl.oney', (array) $settings['payment_methods'])
    ) {
        return;
    }

    $cart_total = (float) WC()->cart->total;

    $min_purchase_amount = 450;
    $max_purchase_amount = 12000;

    $remaining_amount = max(0, $min_purchase_amount - $cart_total);
    $progress_percentage = min(($cart_total / $min_purchase_amount) * 100, 100);
    $price_divided_by_3 = number_format($cart_total / 3, 2);
    $price_divided_by_4 = number_format($cart_total / 4, 2);

    echo '<div class="oney-netopia-payment-progress-bar oney-netopia-style-bordered" style="margin-top: 15px;">';
    echo '<div class="oney-netopia-progress-bar oney-netopia-free-progress-bar">';
    echo '<p>Comenzile de minim 450 și maxim 12.000 de RON pot fi plătite în <strong>3-4 rate fără dobândă</strong> direct cu cardul tău de debit!</p>';

    if ($cart_total >= $min_purchase_amount && $cart_total <= $max_purchase_amount) {
        echo '<div class="oney-netopia-progress-msg">
                <div class="oney-netopia-progress-msg"><span id="acord-remaining-amount">Comanda ta poate fi plătită</span><span class="oney-netopia-remaining-amount"></span><span id="post-acord-remaining-amount"></span> în 3 sau 4 rate prin <img src="'.NTP_PLUGIN_DIR.'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;"></div>
                <div class="oney-netopia-rates-wrapper">
                    <div class="oney-netopia-rate">
                        <span>3 Rate: </span>
                        <span class="oney-netopia-rate-value"><strong>' . $price_divided_by_3 . '</strong>/lună</span>
                    </div>
                    <div class="oney-netopia-rate">
                        <span>4 Rate: </span>
                        <span class="oney-netopia-rate-value"><strong>' . $price_divided_by_4 . '</strong>/lună</span>
                    </div>
                </div>
            </div>';
    } else if($remaining_amount < $min_purchase_amount ){
        echo '<div class="oney-netopia-progress-msg">
                <div class="cumpara-text"> 
                    <span id="acord-remaining-amount">Comenzii tale îi lipsesc încă</span> 
                    <span class="oney-netopia-remaining-amount">' . number_format($remaining_amount, 2) . ' RON</span> 
                    <span id="post-acord-remaining-amount">pentru a putea fi plătită în rate prin</span> 
                    <img src="' . NTP_PLUGIN_DIR . 'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;">
                </div>
            </div>';
    } 

    echo '<div class="oney-netopia-progress-area">
            <div id="oney-netopia-progress-bar" class="oney-netopia-progress-bar" style="width: ' . $progress_percentage . '%"></div>
        </div>';

    echo '</div></div>';
}



/**
 * Save custom payment data from a Block Checkout API request.
 * This version correctly parses the payment_data array to save metadata.
 *
 * @param \WC_Order        $order   The order object being updated.
 * @param \WP_REST_Request $request The full API request object.
 */
function save_oney_installments_from_api_request( $order, $request ) {

    $payload = $request->get_json_params();
    
    // Start logging to help with debugging.
    error_log( "---- Block Checkout API Hook Log for Order #" . $order->get_id() . " ----" );
    error_log( "Hook 'woocommerce_store_api_checkout_update_order_from_request' triggered." );

    // Check if payment_data exists.
    if ( ! isset( $payload['payment_data'] ) || ! is_array( $payload['payment_data'] ) ) {
        error_log( "'payment_data' was not found in the JSON payload." );
        return;
    }

    // --- CORRECTED LOGIC ---

    // Step 1: Initialize variables to store the data we find.
    $sub_method   = null;
    $installments = null;

    // Step 2: Loop through the payment data once to find all our values.
    foreach ( $payload['payment_data'] as $data_item ) {
        if ( isset( $data_item['key'] ) ) {
            if ( 'netopia_method_pay' === $data_item['key'] ) {
                $sub_method = $data_item['value'];
            }
            if ( 'installments_oney' === $data_item['key'] ) {
                $installments = $data_item['value'];
            }
        }
    }

    error_log("Data found: Sub-Method = {$sub_method}, Installments = {$installments}");

    // Step 3: Now that we have the data, use it to update the order.
    if ( ! is_null( $sub_method ) ) {
        switch ( $sub_method ) {
            case 'bnpl.oney':
                $order->add_order_note( 'Client chose to pay with Oney.' );
                // If Oney was chosen AND an installment value was found, save it.
                if ( ! is_null( $installments ) ) {
                    $installments_value = sanitize_text_field( $installments );
                    $order->update_meta_data( '_oney_installments', $installments_value );
                    $order->add_order_note( 'Number of Oney installments: ' . $installments_value );
                    error_log( "SUCCESS: Saved '{$installments_value}' to order #" . $order->get_id() . " meta." );
                }
                break;
            
            case 'bnpl.paypo':
                $order->add_order_note( 'Client chose to pay with Paypo.' );
                break;
            
            case 'credit_card':
            default:
                $order->add_order_note( 'Client chose to pay with Card.' );
                break;
        }
    }
}

// Ensure the hook is correctly added.
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'save_oney_installments_from_api_request', 10, 2 );

