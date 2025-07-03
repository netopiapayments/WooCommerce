<?php

/*
Plugin Name: NETOPIA Payments Payment Gateway
Plugin URI: https://www.netopia-payments.ro
Description: accept payments through NETOPIA Payments
Author: Netopia
Version: 2.0
License: GPLv2
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'netopiapayments_init', 0 );
function netopiapayments_init() {
    // set Api Version to work with plugin

    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	DEFINE ('NTP_PLUGIN_DIR', plugins_url(basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_netopiapayments_gateway' );
    function add_netopiapayments_gateway( $methods ) {
        $methods[] = 'netopiapayments';
        return $methods;
        }

    // Add custom action links to Wordpress Admin
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'netopia_action_links' );
    function netopia_action_links( $links ) {
        $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=netopiapayments' ) . '">' . __( 'Settings', 'netopiapayments' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    // Api v2 Started Here 
    // If we made it this far, then include our Gateway Class
    include_once( 'v2/wc-netopiapayments-gateway.php' );
    include_once( 'wc-netopiapayments-update-key.php' );

    add_action( 'admin_enqueue_scripts', 'netopiapaymentsjs_init' );
    function netopiapaymentsjs_init($hook) {
        if ( 'woocommerce_page_wc-settings' != $hook ) {
            return;
            }

        
        // Get ntp_notify_value if exist
        $ntpNotify = '';
        $ntpOptions = get_option( 'woocommerce_netopiapayments_settings' );
        if($ntpOptions) {
            $ntpNotify = array_key_exists('ntp_notify_value', $ntpOptions) ? $ntpOptions['ntp_notify_value'] : '';
        }
        

        wp_enqueue_script( 'netopiapaymentsjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiapayments.js',array('jquery'),'1.0' ,true);
		wp_enqueue_script( 'netopiaOneyjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiaOney.js',array('jquery'),'2.0' ,true);
        wp_enqueue_script( 'netopiaUIjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiaCustom.js',array(),'1.1' ,true);
    	
        wp_localize_script( 'netopiaUIjs', 'netopiaUIPath_data', array(
            'plugin_url' => getAbsoulutFilePath(),
            'site_url' => get_option('siteurl'),
            'sKey' => base64_encode(md5(json_encode($ntpOptions).json_encode(get_home_url()))),
            'ntp_notify' => $ntpNotify,
            )
        );

		
    }


    /**
	 * Custom function to declare compatibility with cart_checkout_blocks feature 
	*/
	function declare_netopiapayments_blocks_compatibility() {
		// Check if the required class exists
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			// Declare compatibility for 'cart_checkout_blocks'
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		}
	}
	// Hook the custom function to the 'before_woocommerce_init' action
	add_action('before_woocommerce_init', 'declare_netopiapayments_blocks_compatibility');

    
    // Hook in Blocks integration. This action is called in a callback on plugins loaded
	add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_netopia_block_support' );
	function woocommerce_gateway_netopia_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			
			// Include the custom Block checkout class
			require_once dirname( __FILE__ ) . '/v2/netopia/Payment/Blocks.php';

			// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					add_action(
						'woocommerce_blocks_payment_method_type_registration',
						function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
							// Registre an instance of netopiaBlocks
							$payment_method_registry->register( new netopiapaymentsBlocks );
						 }
					);
				},
				5
			);
		} else {
			// The Current installation of wordpress not sue WooCommerce Block
			return;
		}
	}

	// Including UI Oney Possibility
	// Including Oney Addon
	$oney_add_on_path = plugin_dir_path(__FILE__) . 'v2/oney/oney-add-on-netopia.php';
	if (file_exists($oney_add_on_path)) {
		include_once($oney_add_on_path);
	}
}

function getAbsoulutFilePath() {
	// Get the absolute path to the plugin directory
	$plugin_dir_path = plugin_dir_path( __FILE__ );

	// Get the absolute path to the WordPress installation directory
	$wordpress_dir_path = realpath( ABSPATH . '..' );

	// Remove the WordPress installation directory from the plugin directory path
	$plugin_dir_path = str_replace( $wordpress_dir_path, '', $plugin_dir_path );

	// Remove the leading directory separator
	$plugin_dir_path = ltrim( $plugin_dir_path, '/' );

	// Remove the first directory name (which is the site directory name)
	$plugin_dir_path = preg_replace( '/^[^\/]+\//', '/', $plugin_dir_path );

	return $plugin_dir_path;
}

/**
 * Activation hook  once after install / update will execute
 * By "verify-regenerat" key will verify if certifications not exist
 * Then try to regenerated the certifications
 * */ 
register_activation_hook( __FILE__, 'plugin_activated' );
function plugin_activated(){
	add_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
}


