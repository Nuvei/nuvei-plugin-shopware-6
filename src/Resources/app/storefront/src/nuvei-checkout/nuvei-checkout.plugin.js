import HttpClient from 'src/service/http-client.service'
import Plugin from 'src/plugin-system/plugin.class';

export default class NuveiCheckout extends Plugin {
	init() {
        // initalize the HttpClient
        this._client = new HttpClient();
        
        // register the events
        window.addEventListener('load', this.onLoad.bind(this));
    }
	
	onLoad() {
		console.log('.checkout-main loaded');
        
        var paymentOptions = document.querySelector('input[name="paymentMethodId"]');
        
        // checkout page, but there are no payment options to select
        if (paymentOptions && paymentOptions.length == 0) {
            return;
        }
        
        // append Nuvei Checkout container
        let checkoutCont    = document.querySelector('.checkout-main').closest('.container');
        let nuveiCheckout   = document.createElement('div');
        
        nuveiCheckout.id = 'nuvei_checkout';
        checkoutCont.appendChild(nuveiCheckout);
        
        // check if #confirmFormSubmit exists
		let confirmFormSubmit	= document.querySelector('#confirmFormSubmit');
		
		if (!confirmFormSubmit) {
			return;
		}
		
		// add few Nuvei inputs
        let submitedFormCont    = document.querySelector('#confirmFormSubmit').closest('form');
        let inputPaymentMethod  = document.createElement('input');
        let inputTrId           = document.createElement('input');
        
        inputPaymentMethod.type     = 'hidden';
        inputPaymentMethod.name     = 'nuveiPaymentMethod';
        inputPaymentMethod.value    = '';
        
        inputTrId.type  = 'hidden';
        inputTrId.name  = 'nuveiTransactionId';
        inputTrId.value = '';
        
        submitedFormCont.appendChild(inputPaymentMethod);
        submitedFormCont.appendChild(inputTrId);
        
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
            
            nuveiSdk.src = 'https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js';
            
            // for QA site use Tag SDK
            try {
                if ('shopware6automation.gw-4u.com' === window.location.host) {
                    nuveiSdk.src = 'https://devmobile.sccdev-qa.com/checkoutNext/checkout.js'; 
                }
            }
            catch (_exception) {
                console.log('Nuvei Error', _exception);
            }
            
            document.head.appendChild(nuveiSdk);
        };
        nuveiScript.src      = '/bundles/swagnuveicheckout/storefront/js/nuvei.js';
        document.head.appendChild(nuveiScript);
	}
}