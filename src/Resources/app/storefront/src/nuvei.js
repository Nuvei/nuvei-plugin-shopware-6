import HttpClient from 'src/service/http-client.service'
import Plugin from 'src/plugin-system/plugin.class';

export default class NuveiPlugin extends Plugin {
	init() {
        // initalize the HttpClient
        this._client = new HttpClient();
        
        // register the events
        window.addEventListener('load', this.onLoad.bind(this));
    }
	
	onLoad() {
		console.log('.checkout-main loaded');
        
        var paymentOptions = jQuery('input[name="paymentMethodId"]');
        
        // checkout page, but there are no payment options to select
        if (paymentOptions.length == 0) {
            return;
        }
        
        // append Nuvei Checkout container
        jQuery('body').find('.checkout-main').closest('.container')
            .append('<div id="nuvei_checkout"></div>');
    
        // add few Nuvei inputs
        var submitedForm = jQuery('#confirmFormSubmit').closest('form');
        submitedForm.append('<input type="hidden" name="nuveiPaymentMethod" value="" />');
        submitedForm.append('<input type="hidden" name="nuveiTransactionId" value="" />');
        
        // temp load external files
        var nuveiScript      = document.createElement('script');
        nuveiScript.onload   = function () {
            console.log('nuvei temp file loaded');
            
            // load Checkout SDK
            var nuveiSdk      = document.createElement('script');
            nuveiSdk.onload   = function () {
                console.log('nuvei checkout loaded');
                nuveiRenderCheckout();
            };
            nuveiSdk.src      = 'https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js';
            document.head.appendChild(nuveiSdk);
        };
        nuveiScript.src      = '/bundles/swagnuveicheckout/storefront/js/nuvei.js';
        document.head.appendChild(nuveiScript);
	}
}