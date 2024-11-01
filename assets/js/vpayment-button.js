/*!
 * valsto pay button
 * JavaScript integration for Valsto's platform
 * @version 1.0.0 - 2016-03-01
 * @author Omar Yepez <https://www.valsto.com>
 */
"use strict";
var vPaymentButton = vPaymentButton || (function(){

	// private attrs
    var _attrs = {}; 
	var callback;
    var currency = "USD" 
	var styleClass = "vpayment-button";
	var valstoHome = "https://qa.valsto.com/vpayment/initSession";
	var sandboxHome = "https://qa.valsto.com/vpayment/initSession";
	var buttonCaption = "";
	var cssButtonClassName = "vpay-button";
	var cssOverlayClassName = "vpay-overlay";
	var cssDialogClassName = "vpay-dialog";
	var cssIframeClassName = "vpay-iframe";
	var cssButtonCloseDialogClassName = "vpay-fialog-close-button";
	
	/**
	 * Build the payment buttom
	 */
	var buildButtons = function(){
		var x = document.getElementsByClassName(styleClass);
		var i;
		for (i = 0; i < x.length; i++) {
			buildForm(x[i], i + 1);
		}
	};
	
	/**
	 * Build the payment form.
	 */
	var buildForm = function(el, index){
		var frm = document.createElement("form");
		var proxy = el.getAttribute('data-vproxy');

		if(proxy != undefined || proxy != ""){
			frm.action = proxy;
		}else{
			frm.action = _attrs.sandbox == undefined || _attrs.sandbox === false  ? valstoHome: sandboxHome;
		}
				
		frm.target = "valstoDialogIframe";
		frm.method = "POST";
		frm.id = "vapp-button-form-" + index;
		buildDefaultFormFields(frm, el);
		buildFormFields(frm, el);
		buildItemsFormFields(frm, el);
		buildButton(frm);
		buildDialog();
		
		if(el.getAttribute('data-auto-open') === "true"){
			openDialog();
		}
		
		el.appendChild(frm);
	};
	
	/**
	 * Build the html form fields.
	 */
	var buildFormFields = function(frm, el){
		var fields = ["data-tax", "data-shipping","data-shipping_postcode",
			"data-shipping_city","data-shipping_address_1","data-shipping_address_2",
			"data-shipping_state","data-shipping_country","data-vproxy"];
		
		for (var i = 0; i < fields.length; i++) {
			
			var attr = fields[i];
			var attrVal = el.getAttribute(attr);
			
			if(attrVal == null || attrVal == undefined){
				attrVal = 0;
			}
			
			if(fields[i] == "data-vproxy"){
				attr == "data-vproxy-endpoint";
				attrVal = _attrs.sandbox === undefined || _attrs.sandbox === false  ? valstoHome: sandboxHome;
			}
			
			addInput(frm, attr, attrVal, "hidden");
		}		
	};
	
	/**
	 * Build the default form fields.
	 */
	var buildDefaultFormFields = function(frm, el){
		var fields = {"data-merchant": _attrs.merchant, "data-api-key": _attrs.apiKey, 'data-currency-code': _attrs.currencyCode, 'referer': window.location.href || document.URL };

		for(var key in fields){

			var attr = key;
			var attrVal = el.getAttribute(attr);
			
			if(attrVal == null || attrVal == undefined){
				attrVal = fields[key];
			}
			
			addInput(frm, attr, attrVal, "hidden");
		}		
	};
	
	/**
	 * Build the form for each item in the shoping cart.
	 */
	var buildItemsFormFields = function(frm, el){
		var fields = ["data-item-ammount-", "data-item-quantity-"];
		var i = 1;
		var attr = "data-item-" + i;
		var attrVal = el.getAttribute(attr);
		while (attrVal != null && attrVal != undefined) {
			
			addInput(frm, "item_" + i, attrVal, "hidden");
			
			for (var j = 0; j < fields.length; j++) {
				attr = fields[j] + i;
				attrVal = el.getAttribute(attr);
				
				if(attrVal == null || attrVal == undefined){
					if(fields[j] == "data-item-quantity-"){
						attrVal = 1;
					}else{
						attrVal = 0;
					}
				}
				
				addInput(frm, attr, attrVal, "hidden");
				
			}
						
			attr = "data-item-" + i;
			i++;
			attrVal = el.getAttribute("data-item-" + i);
		}
	};
	
	/**
	 * Created a new input form field.
	 */
	var addInput = function(frm, name, value, type){
		var inp = document.createElement("input");
		inp.name = name.replace("data-","").replace(/-/gi,"_");
		inp.value = value;
		inp.type = type;
		frm.appendChild(inp);
	};
		
	/**
	 * Build the vPayment Button.
	 */
	var buildButton = function(el){
		var btn = document.createElement("button");
		btn.innerHTML = buttonCaption;
		btn.type = "submit";
		btn.addEventListener("click", function(){
			openDialog();
		});
		btn.className = cssButtonClassName;
		el.appendChild(btn);
	};
	
	/**
	 * Build the vPayment Dialog
	 */
	var buildDialog = function(){
		var dialog = document.getElementById('valstoDialogForm');
		if(!dialog){
			dialog = document.createElement("div");
			dialog.id='valstoDialogForm';
			dialog.className += cssDialogClassName + ' valsto-dialog-content';
			var closeButton = document.createElement("span");
			closeButton.innerHTML = 'x';
			closeButton.className = cssButtonCloseDialogClassName;
			closeButton.addEventListener("click", function(e){
				closeDialog(e);
			});
			dialog.appendChild(closeButton);
			var iframe = document.createElement("iframe");
			iframe.id="valstoDialogIframe";
			iframe.name="valstoDialogIframe";
			iframe.className = cssIframeClassName;
			dialog.appendChild(iframe);
			buildDialogOverlay();
			document.getElementsByTagName('body')[0].appendChild(dialog);
		}
	};
	
	/**
	 * Build the overlay of the dialog.
	 */
	var buildDialogOverlay = function(){
		var overlay = document.getElementById('valstoOverlay');
		if(!overlay){
			overlay = document.createElement("div");
			overlay.id='valstoOverlay';
			overlay.className += cssOverlayClassName + ' valsto-overlay';			
			document.getElementsByTagName('body')[0].appendChild(overlay);
		}
	};
	
	/**
	 * Open the dialog.
	 */
	var openDialog = function(force){
		if(force === true || _attrs.beforeOpen === undefined){
			window.location.hash = '#';
			document.getElementById('valstoDialogForm').style.display='block';
			document.getElementById('valstoOverlay').style.display='block';
			window.location.hash = '#valstoDialogForm';
		}else{
			_attrs.beforeOpen();
		}
	};
	
	/**
	 * Close the dialog.
	 */
	var closeDialog = function(e){
		var iframe = document.getElementById('valstoDialogIframe');
		iframe.contentWindow.location = 'about:blank';
		document.getElementById('valstoDialogForm').style.display='none';
		document.getElementById('valstoOverlay').style.display='none';
		window.location.hash = e.target.id;
	};

	/**
	 * return the vPaymentButton Object.
	 */
    return {
    	
    	////////////////////////////////
    	
    	/**
    	 * Initiliaze the vPaymentButton.
    	 */
        init : function(attrs) {
            _attrs = attrs;
            return this.build;
        },
		
        /**
         * Build the vPaymentButton
         */
        build : function() {	
			buildButtons();
			return vPaymentButton;
        },
		
        /**
         * Show the dialog.
         */
		open : function(){
			openDialog(true)
		},
    };
}());
