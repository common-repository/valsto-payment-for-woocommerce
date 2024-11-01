<?php

/**
 * WC_Gateway_Valsto_Payment_Button class.
 *
 * @extends WC_Gateway_Valsto
 */
class WC_Gateway_Valsto_Payment_Button extends WC_Gateway_Valsto 
{

    /**
     * Constructor for the gateway.
     */
	public function __construct() 
	{
	    $this->add_valsto_script_button();
	    
	    $this->id                = 'valsto_payment';
		parent::__construct();
		$this->icon              = apply_filters( 'woocommerce_cod_icon', '' );
		$this->has_fields        = true;
		$this->title             = $this->get_option( 'title_valsto' );
		$this->description       = $this->get_option( 'description_valsto' );
		$this->js_resource       = $this->get_option( 'js_resource_valsto', 'https://www.valsto.com/resources/base/js/vpayment-button.js');
		$this->checkout_template = 'checkout/valsto_payment_button.php';
	}
	
	public function add_valsto_script_button(){
	   wp_enqueue_script( 'omar.yepez.js' );
	}
}
