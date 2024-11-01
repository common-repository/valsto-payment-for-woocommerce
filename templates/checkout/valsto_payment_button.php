<?php
/**
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( array_key_exists( 'description', $model ) && ! empty( $model['description'] ) ) {
    echo wpautop( wptexturize( $model['description'] ) );
}

?>

<?php
    $woocommerce = WC();
    
	$taxes = $woocommerce->cart->get_taxes(); 
    // Add each taxes to $discount
	
	$totalTax = 0;
	
	foreach($taxes as $tax){
	    $totalTax += $tax;
	} 
?>

<div id="vpayment-button" class="vpayment-button"
	onclick="submitFormAjax()"
	data-vproxy="<?php echo WC_VALSTO_VBUTTON_VALIDATE_PAYMENT_URL; ?>"
    data-shipping_postcode="<?php echo $woocommerce->customer->shipping_postcode?>"
	data-shipping_city="<?php echo $woocommerce->customer->shipping_city?>"
	data-shipping_address_1="<?php echo $woocommerce->customer->shipping_address_1?>"
	data-shipping_address_2="<?php echo $woocommerce->customer->shipping_address_2?>"
	data-shipping_state="<?php echo $woocommerce->customer->shipping_state?>"
	data-shipping_country="<?php echo $woocommerce->customer->shipping_country?>"
	data-tax="<?php echo $totalTax ?>"
	data-shipping="<?php echo $woocommerce->cart->shipping_total > 0 ? $woocommerce->cart->shipping_total : 0 ?>"
	<?php $i =1; foreach($woocommerce->cart->get_cart() as $item => $values): ?>
		<?php echo sprintf('data-item-%s="%s"', $i, $values['data']->post->post_title) ?>
		<?php echo sprintf('data-item-ammount-%s="%s"', $i, get_post_meta($values['product_id'] , '_price', true)) ?>
		<?php echo sprintf('data-item-quantity-%s="%s"', $i++, $values['quantity']) ?>
	<?php endforeach;?>
>
</div>

<script type="text/javascript">
    (function() {
    	var vButton = vPaymentButton.init({sandbox: false, beforeOpen: function(){ validateFormAjax()}})();
		
		function validateFormAjax()
		{
			var xhr= window.XMLHttpRequest 
				? new XMLHttpRequest() 
				: new ActiveXObject("Microsoft.XMLHTTP");
			xhr.open("POST", "?wc-ajax=checkout");
			xhr.onreadystatechange = function () {
			  if(xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
				var response = JSON.parse(xhr.response);
				console.log(response);
				if(response.result === "failure"){
					document.getElementById('place_order').click();
				}else{	
					vButton.open();
				}
			  }
			};
			var form = document.getElementsByName("checkout")[0];
			var data = new FormData(form);
			xhr.send(data);
		}	
	})();

	function submitFormAjax()
	{
		var xmlhttp= window.XMLHttpRequest 
			? new XMLHttpRequest() 
			: new ActiveXObject("Microsoft.XMLHTTP");
		var form = document.getElementsByName("checkout")[0];
		var data = new FormData(form);
		xmlhttp.open("POST", "<?php echo WC_VALSTO_VBUTTON_PREPARE_PURCHASE_URL ?>");
		xmlhttp.send(data);
	}
	
	jQuery( function( $ ) {
		"use strict";
		$('body').on('change', 'input[name="payment_method"]', function() {
			$('body').trigger('update_checkout');
		});
	});
</script>