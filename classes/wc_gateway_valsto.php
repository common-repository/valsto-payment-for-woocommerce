<?php
/**
 *
 * Provides a Valsto Payment Gateway.
 *
 * @class 		WC_Gateway_Valsto
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		WooThemes
 */

abstract class WC_Gateway_Valsto extends WC_Payment_Gateway {

	/**
	 * Version
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Checkout template for payment fields
	 */
	public $checkout_template = '';

	/**
	 * Whether or not the gateway is enabled in wp-admin
	 * This is initialized from the option, but can differ from $this->enabled
	 * in the event the gateway is declared not-valid-for-use during construction.
	 */
	protected $enabled_original_setting = '';

	/**
	 * Whether or not debug is enabled in wp-admin
	 */
	protected $debug = false;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->icon        = false;
		$this->has_fields  = true;
		$this->title       = '';
		$this->description = '';

		$this->method_title = __( 'Valsto Payment', WC_Valsto_VButton_Loader::PLUGIN_NAME_SPACE );
		$this->method_description = sprintf(
			__( 'Works by accepting payment information in a secure form hosted by Valsto.', 'valsto-vpayment-button' ),
			'<a href="https://www.valsto.com/">', '</a>'
		);

		$this->supports = array('products');

		$this->capture = $this->get_option( 'capture', 'yes' ) === 'yes' ? true : false;

		$this->merchant_access_token = get_option( 'wc_valsto_merchant_access_token', '' );
		$this->merchant_id = get_option( 'wc_valsto_merchant_id', '' );
		$this->testmode = $this->get_option( 'test_mode_valsto', 'yes' ) === 'yes';
		
		$this->init_form_fields();
		$this->init_settings();

		$this->debug = $this->get_option( 'debug' ) === 'yes';
		$this->enabled_original_setting = $this->enabled;

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	
	/**
	 * Initialise Gateway Settings Form Fields common to all the gateways this extension supports
	 * Child classes may add additional fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields () 
	{
		$this->form_fields = WC_Valsto_VButton_Loader::getInstance()->get_shared_form_fields();
	}
	
	public function admin_options() 
	{
		$current_user = wp_get_current_user();
		$section_slug = strtolower( get_class( $this ) );

		$production_connect_url = 'https://www.valsto.com/login';
		$sandbox_connect_url = 'https://www.valsto.com/login';

		$redirect_url = add_query_arg(
			array(
				'page' => 'wc-settings',
				'tab' => 'checkout',
				'section' => $section_slug
			),
			admin_url( 'admin.php' )
		);
		$redirect_url = wp_nonce_url( $redirect_url, 'connect_valsto', 'wc_valsto_admin_nonce' );

		$query_args = array(
			'redirect' => urlencode( urlencode( $redirect_url ) ),
			'scopes' => 'read_write'
		);

		$current_user = wp_get_current_user();

		$query_args['user_email'] = $current_user->user_email;

		if ( ! empty( $current_user->user_firstname ) ) {
			$query_args[ 'user_firstName' ] = $current_user->user_firstname;
		}

		if ( ! empty( $current_user->user_lastname ) ) {
			$query_args[ 'user_lastName' ] = $current_user->user_lastname;
		}

		$query_args[ 'business_currency' ] = get_woocommerce_currency();

		// Let's go ahead and assume the user and business are in the same region and country,
		// because they probably are.  If not, they can edit these anyways
		$base_location = wc_get_base_location();
		if ( array_key_exists( 'country', $base_location ) ) {
			$country = $base_location[ 'country' ];
			if ( ! empty( $country ) ) {
				$query_args[ 'business_country' ] = $country;
				$query_args[ 'user_country' ] = $country;
			}
		}
		if ( array_key_exists( 'state', $base_location ) ) {
			$state = $base_location[ 'state' ];
			if ( ! empty( $state ) ) {
				$query_args[ 'business_region' ] = $state;
				$query_args[ 'user_region' ] = $state;
			}
		}

		$site_name = get_bloginfo( 'name' );
		if ( ! empty( $site_name ) ) {
			$query_args[ 'business_name' ] = $site_name;
		}

		$site_description = get_bloginfo( 'description' );
		if ( ! empty( $site_description ) ) {
			$query_args[ 'business_description' ] = $site_description;
		}

		$query_args[ 'business_website' ] = get_bloginfo( 'url' );

		$production_connect_url = add_query_arg( $query_args, $production_connect_url );
		$sandbox_connect_url = add_query_arg( $query_args, $sandbox_connect_url );

		$disconnect_url = add_query_arg(
			array(
				'page' => 'wc-settings',
				'tab' => 'checkout',
				'section' => $section_slug,
				'disconnect_valsto' => 1
			),
			admin_url( 'admin.php' )
		);
		$disconnect_url = wp_nonce_url( $disconnect_url, 'disconnect_valsto', 'wc_valsto_admin_nonce' );

		?>
			<div class='valsto-admin-header'>
				<div class='valsto-admin-brand'>
					<br/>
					<img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/logo-default.png'; ?>" />
				</div>
			</div>
			<?php if ( empty( $this->merchant_access_token ) ) { ?>
				<p class='valsto-admin-connect-prompt'>
					<a href="<?php echo WC_Valsto_VButton_Loader::get_login_url() ?>" target="_blank"><?php echo esc_html( 'Login', WC_Valsto_VButton_Loader::PLUGIN_NAME_SPACE ); ?></a>
					<?php echo esc_html( 'to Valsto Platform and get your access token.', WC_Valsto_VButton_Loader::PLUGIN_NAME_SPACE ); ?>
					<a href="<?php echo WC_Valsto_VButton_Loader::get_about_url() ?>" target="_blank">
						<?php echo esc_html( 'Learn more', WC_Valsto_VButton_Loader::PLUGIN_NAME_SPACE ); ?>
					</a>
				</p>
			<?php } ?>



			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php add_thickbox(); ?>
			<div id="valsto-apikey-data" style="display:none;">
					<div>
						<br/>
						<div align="center">
							<img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/logo-default.png'; ?>" />
						</div>
						<p>
							Valsto payment gateway is the easiest and most convenient option for the web developer to implement the Valsto payment method on WooComerce.
							<br><br>Get your Valsto API keys by following these steps:<br><br>
							1. Log in to <a target="_new" href="https://www.valsto.com"> valsto.com</a> as a user with the Administrator role for the merchant<br>
							2. Access the merchant account associated to this e-commerce<br>
							3. Go to Settings by clicking on the gear icon<br>
							4. Locate the plugin for WooCommerce under Payment Gateway and click on it<br>
							5. Paste the info below in the appropriate boxes<br>
							6. Click on "GENERATE API KEYS" and copy both of them to notepad<br>
							7. Close this window and paste the public and private keys in the appropriate boxes<br>
							8. Save changes
						</p>
					</div>
					<?php $siteURL = get_site_url(); ?>
					<?php $eSiteURL = explode('/', $siteURL)?>	
					<div>
					 	<table style="width:100%;border:0px; background-color:gainsboro">
						 	<tbody>
								<tr>
									<td><label><strong>Domain:</strong></td>
									<td><?php echo sprintf('%s//%s', $eSiteURL[0], $eSiteURL[2]) ?></td>
								</tr>
								<tr>
									<td><label><strong>Context Path:</strong></td>
									<td>/<?php echo isset($eSiteURL[3]) ? $eSiteURL[3] : "";  ?></td>
								</tr>
								<tr>
									<td><label><strong>IPN:</strong></td>
									<td>/<?php echo WC_VALSTO_VBUTTON_WEBHOOK_CONTEXT ?></td>
								</tr>
							<tbody>
						</table>
					</div>
					<br>
				</div>
				
				<a id="openThickboxValsto" href="#TB_inline?width=400&height=440&inlineId=valsto-apikey-data" class="thickbox"></a>
				
				<script>
						(function ($) {
							$('#woocommerce_valsto_payment_merchant_account_private_pk_valsto').parent().append(
								'<input onclick="jQuery(\'#openThickboxValsto\').click()" type="button" value="Get Valsto API Keys" class="button-secondary">'
							)
						})(jQuery);
				</script>
		<?php
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() 
	{

		// If the gateway is supposed to be enabled, check for required settings
		if ( 'yes' === $this->enabled_original_setting ) {

			$general_settings_url = add_query_arg( 'page', 'wc-settings', admin_url( 'admin.php' ) );
			$checkout_settings_url = add_query_arg( 'tab', 'checkout', $general_settings_url );
			$gateway_settings_url = add_query_arg( 'section', strtolower( get_class( $this ) ), $checkout_settings_url );

		}

	}

	/**
	 * Don't allow use of this extension if the currency is not supported or if setup is incomplete
	 *
	 * @since 1.0.0
	 */
	function is_valid_for_use() 
	{
		if ( ! is_ssl() && ! $this->testmode ) {
			return false;
		}

		if ( empty( $this->merchant_access_token ) ) {
			return false;
		}

		return true;
	}

	/**
	 * payment_fields
	 *
	 * @since 1.0.0
	 */
	public function payment_fields() 
	{

		$description = $this->get_description();
		if ( $this->testmode ) {
			$description .= ' ' . __( '(Sandbox mode is enabled -- Use a test account)', 'woocommerce-gateway-valsto' );
		}

		$model = array(
			'description' => $description,
			'js_resource' => $this->js_resource
		);

		if ( ! empty( $this->checkout_template ) ) {
			wc_get_template(
				$this->checkout_template,
				array(
					'model' => $model
				),
				'',
				dirname( __FILE__ ) . '/../templates/'
			);
		}
	}

	/**
	 * validate_fields
	 *
	 * @since 1.0.0
	 */
	public function validate_fields() 
	{
		return true;
	}

	/**
	 * 
	 */
	public static function get_posted_variable( $variable, $default = '' ) 
	{
		return ( isset( $_POST[$variable] ) ? $_POST[$variable] : $default );
	}

	/**
	 * 
	 * @param array $array
	 * @return NULL[][]
	 */
	public static function sanitize_array( $array ) 
	{
		$sanitized_array = array();

		foreach( $array as $key => $value ) {
			$sanitized_array[$key] = is_array( $value ) ? self::sanitize_array( $value ) : sanitize_text_field( $value );
		}

		return $sanitized_array;
	}

	/**
	 * process_payment
	 *
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ) 
	{
		$order = wc_get_order( $order_id );
		
		if(isset($_GET['vTID'])){
			add_post_meta( $order_id, '_valsto_transaction_id', $_GET['vTID'], true);
			$discount = (float) $_GET['vDiscount'];
			$vDiscountAmount = $_GET['vDiscountAmount'];
			$discountedTaxes = ["tax_amount", "shipping_tax_amount"];
			$discountedTaxesAmount = 0; 
			
			//Calculate Taxes discounts;
			foreach ($order->get_taxes() as $taxId => $taxes) { 
				foreach ($taxes['item_meta'] as $key => $tax) {
					if(in_array($key, $discountedTaxes)){
						echo "<pre>";
						if(isset($tax[0])){
							$taxVal = (float) $tax[0];
							if($taxVal > 0){
								$newTax = $taxVal - (($discount / 100) * $taxVal);
								wc_update_order_item_meta($taxId, $key, $newTax);
								$discountedTaxesAmount += $taxVal - $newTax;
							}
						}
					}
					
				}
			}
			
			//Update total discounts;
			$orderTotal = get_post_meta($order_id, '_order_total');
			$orderTotal = $orderTotal[0];
			$orderTotal = $orderTotal - $discountedTaxesAmount;
			update_post_meta( $order_id, '_order_total', ($orderTotal - $vDiscountAmount));
			
			$item_id = wc_add_order_item($order_id, array('order_item_name' => "Valsto discount (". number_format ($discount, 2) ." %)", 'order_item_type' => 'fee'));
			// Add vDiscount line item
			if ($item_id) {
				wc_add_order_item_meta($item_id, '_tax_class', 0);
				wc_add_order_item_meta($item_id, '_line_total', ($vDiscountAmount * -1));
				wc_add_order_item_meta($item_id, '_line_tax', 0);
				wc_add_order_item_meta($item_id, '_line_tax_data', array('total' => array(), 'subtotal' => array()));
			}
		}

		if(isset($_SESSION['current_order'])){
			if (!session_id()) {
    			session_start();
			}
			unset($_SESSION['current_order']);
		}
			
		// on success, return thank you page redirect
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);
	}
	
	/**
	 * admin_enqueue_scripts
	 *
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script( 'thickbox' );
	}


	/**
	 * When cart based Checkout with Valsto is in effect, we need to select ourselves as the payment method.
	 *
	 * @since 1.0.0
	 */
	public function possibly_set_chosen_payment_method() {

		// skip if this is a real POST
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// set as chosen payment method (for WC 2.3+)
		$this->chosen = true;
	}


	/**
	 * When cart based Checkout with Valsto is in effect, we need to take the data we saved in the session
	 * and fill in the checkout form with it.
	 *
	 * @since 1.0.0
	 */
	public function possibly_set_checkout_value( $value, $key ) {

		// skip if this is a real POST
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			return $value;
		}

		$postback_data = WC()->session->get( 'valsto_postback' );
		if ( array_key_exists( $key, $postback_data ) ) {
			return $postback_data[$key];
		}

		if ( 'order_comments' === $key ) {
			if ( array_key_exists( 'order_note', $postback_data ) ) {
				return $postback_data['order_note'];
			}
		}

		return $value;
	}
}
