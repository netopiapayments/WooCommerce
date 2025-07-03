<?php
/**
 * Class Netopia_Blocks_Support
 * This class used for supporting wooCommerce Block
 * @copyright NETOPIA Payments
 * @author Dev Team
 * @version 1.0
 **/
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class netopiapaymentsBlocks extends AbstractPaymentMethodType {
	private $gateway;
	protected $name = 'netopiapayments';


	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_netopiapayments_settings', [] );
		$this->gateway = new netopiapaymentsBlocks();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
		// return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
        	wp_register_script(
				'netopiapayments-block-integration',
				plugin_dir_url(__FILE__) . '../../blocks/index.js',
				array(
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
					'wp-i18n'
				),
				null,
				true
			);
		return [ 'netopiapayments-block-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$paymentMethodArr = $this->get_setting( 'payment_methods' );
		return [
			'title'       		=> $this->get_setting( 'title' ),
			'description' 		=> $this->get_setting( 'description' ),
			'supports'    		=> $this->get_supported_features(),
			'payment_methods'   => is_array($paymentMethodArr) ? $paymentMethodArr : array(),
			'custom_html'     => $this->tmpHtml($paymentMethodArr),
		];
	}

	public function tmpHtml($paymentMethodArr) {
		if ( is_admin() ) {
			return "";
		}
		global $wpdb;
		
		/*
		* if the plugin is not return array, the payment methos must be array
		* Check the configuration
		*/
		if(empty($paymentMethodArr)) {
			wc_add_notice( __( 'NETOPIA Payment method is not configured correctly!', 'netopiapayments' ), $notice_type = 'error' );
			return false;
		}

		
		// Output the avalible payment methods
		$html = '';
		$checked = "";
		$name_methods = array(
			'credit_card'	    => __( 'Credit / Debit Card', 'netopiapayments' ),
			'bnpl.oney'  			=> __( 'Oney', 'netopiapayments' ),
			'bnpl.paypo'  			=> __( 'Paypo', 'netopiapayments' ),
			);
		
		$descriptions = [
			'credit_card'     => __('Plata online prin NETOPIA Payments.'),
			'bnpl.oney'       => __('Plata in 3-4 rate fara dobanda prin Oney.'),
			'bnpl.paypo'      => __('Plata in 30 de zile fara costuri suplimentare prin PayPo.'),
		];
		
			switch (true) {
				case empty($paymentMethodArr):
					$html .=  '<div id="netopia-methods">Nu este setata nicio metoda de plata!</div>';
				break;
				case count($paymentMethodArr) === 1:
					// Default is Credit/Debit Card (the ZERO index)
					$html .=  '<input type="hidden" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-'.esc_attr($paymentMethodArr[0]).'" value="'.esc_attr($paymentMethodArr[0]).'"/>';
				break;
				case count($paymentMethodArr) > 1:
					foreach ($paymentMethodArr as $method) {
						// Verify if the payment method is available in the list.
						if(array_key_exists($method, $name_methods)) {
							$checked = ($method == 'credit_card') ? 'checked="checked"' : "" ;
							$html .=  '<br>
											<input type="radio" name="netopia_method_pay" class="" id="netopia-method-'.$method.'" value="'.$method.'" '.$checked.' />
											<label for="netopia-method-' . $method . '" style="display: inline;">' . $name_methods[$method] . '</label>';

							// Start collapsible div hidden
							$html .= '<div class="netopia-collapse" id="collapse-' . esc_attr($method) . '" style="display:none;">';

							// Put the static description (fallback)
							$html .= esc_html($descriptions[$method]);

							$html .= '</div>';

						}
					}
				break;
			}
			
			// Add the custom HTML for Oney payment method
			if (in_array('bnpl.oney', $paymentMethodArr)) {
				
				//////////
				// Get the minimum purchase amount (adjust accordingly)
				$min_purchase_amount = 450;
				$max_purchase_amount = 12000;
				
				// Get the number of decimals set in WooCommerce
				$cart_total_raw = WC()->cart->get_cart_total(); // Get the raw cart total as a string
				$decimals = wc_get_price_decimals();
				// Remove thousand separator if decimals are 0
				if ($decimals === 0) {
					$thousand_separator = wc_get_price_thousand_separator();
					$cart_total_raw = str_replace($thousand_separator, '', $cart_total_raw);
				}
				// Format the cart total to a decimal
				$cart_total = wc_format_decimal($cart_total_raw);
				$cart_total_divided_by_3 = number_format($cart_total / 3, 2); // Calculate total divided by 3 rates and limit to 2 decimals
				$cart_total_divided_by_4 = number_format($cart_total / 4, 2); // Calculate total divided by 4 rates and limit to 2 decimals
				
				// Calculate the remaining amount for free shipping
				$remaining_amount = max(0, $min_purchase_amount - $cart_total);
				// Calculate the progress percentage
				$progress_percentage = ($cart_total / $min_purchase_amount) * 100;
				$progress_percentage = min($progress_percentage, 100); // Ensure it doesn't exceed 100%

				//////////

				
				$html .= '
				<div id="oney_ui_freame" style="display:none">
					<div id="oney_ui_section" style="display:none">
					<div class="oney-netopia-payment-progress-bar oney-netopia-style-bordered" >
						<!--- <div class="oney-netopia-progress-bar oney-netopia-free-progress-bar" > -->
							<p> Comenzile de minim 450 și maxim 12.000 de RON pot fi plătite în <strong>3-4 rate fără dobândă</strong> direct cu cardul tău de debit!</p> ';

							if ($remaining_amount <= 0) {
								if($cart_total >= $min_purchase_amount && $cart_total <= $max_purchase_amount ) {
									$html .= '<div class="oney-netopia-progress-msg"><span id="acord-remaining-amount">Comanda ta poate fi plătită</span><span class="oney-netopia-remaining-amount"></span><span id="post-acord-remaining-amount"></span> în 3 sau 4 rate prin <img src="'.NTP_PLUGIN_DIR.'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;"></div>';
									$html .= '<div class="oney-netopia-rates-wrapper">
									<div class="oney-netopia-rate">
										<input type="radio" name="installments_oney" class="" id="installments_oney_3" value="3" checked="checked" />
										<span>3 Rate: </span>
										<span class="oney-netopia-rate-value"><strong id="oney-netopia-3rate">' . $cart_total_divided_by_3 . '</strong>/lună</span>
									</div>
									<div class="oney-netopia-rate">
										<input type="radio" name="installments_oney" class="" id="installments_oney_4" value="4"  />
										<span>4 Rate: </span>
										<span class="oney-netopia-rate-value"><strong id="oney-netopia-4rate">' . $cart_total_divided_by_4 . '</strong>/lună</span>
									</div>
								</div>';
								}
							} else if($remaining_amount < 450 ) {
								$html .= '<div class="oney-netopia-progress-msg"><div class="cumpara-text"> <span id="acord-remaining-amount">Coșului tău îi lipsesc încă</span> <span class="oney-netopia-remaining-amount">' . number_format($remaining_amount, 2) . ' RON</span> <span id="post-acord-remaining-amount">pentru a putea plăti</span> în 3 sau 4 rate prin <img src="'.NTP_PLUGIN_DIR.'img/oney3x4x-logo.png" style="display: inline; width: 95px; margin-bottom: -4px;"></div></div>';
							}
					
							$html .= '
								<div class="oney-netopia-progress-bar">
								<div class="oney-netopia-progress-area">
										<div id="oney-netopia-progress-bar" class="oney-netopia-progress-bar" style="width: '.$progress_percentage.'%"></div>
									</div>
								</div>
							</div>
						</div>
					</div>';
			}
			
		return $html;
	}

	

	
}

