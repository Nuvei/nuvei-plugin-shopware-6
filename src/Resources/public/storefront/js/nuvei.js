console.log('nuveiDev');

window.nuveiLastToken = '';
window.nuveiSdkParams = {};

window.nuveiRenderCheckout = function() {
    console.log('nuveiRenderCheckout');
    
    var errorMsg    = "Unexpected error, please try different payment method!";
    var xmlhttp     = new XMLHttpRequest();
    
    // add simple blocker
    if (jQuery('div.checkout-main').find('#nuvei_blocker').length == 0) {
        jQuery('div.checkout-main').append('<div id="nuvei_blocker" style="position: fixed; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); top: 0px; left: 0px; z-index: 999; padding-left: 50%; padding-top: 50vh;"><img src="/bundles/swagnuveicheckout/storefront/img/rolling.gif" width="100" style="margin-left: -50px" /></div>');
    }

    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
            if (xmlhttp.status == 200) {
                var response = JSON.parse(xmlhttp.response);
                console.log('status 200', response);

                // error, show message
                if( (!response.hasOwnProperty('success') || 1 != response.success)) {
                    if(response.hasOwnProperty('msg') && '' != response.msg) {
                        alert(response.msg);
                    }

                    jQuery('div.checkout-main').find('#nuvei_blocker').hide();
                    return;
                }

                // hide default submit order button
                jQuery('#confirmFormSubmit').hide();

                // call Checkout SDK
                response.nuveiSdkParams.onResult    = nuveiAfterSdkResponse;
                response.nuveiSdkParams.prePayment  = nuveiUpdateCart;
                nuveiLastToken                      = response.nuveiSdkParams.sessionToken;

                console.log('nuveiSdkParams', response.nuveiSdkParams);

                checkout(response.nuveiSdkParams);
                jQuery('div.checkout-main').find('#nuvei_blocker').hide();
                return;
            }

            if (xmlhttp.status == 400) {
                console.log('There was an error 400');
                alert(errorMsg);
                jQuery('div.checkout-main').find('#nuvei_blocker').hide();
                return;
            }

            console.log('Nuvei Ajax call unexpected error.');

            alert(errorMsg);
            jQuery('div.checkout-main').find('#nuvei_blocker').hide();
            return;
        }
    };

    xmlhttp.open("GET", "/nuvei_checkout?selected_pm="+jQuery('input[name="paymentMethodId"]').val(), true);
    xmlhttp.send();
};

window.nuveiUpdateCart = function() {
    console.log('nuveiUpdateCart');

    return new Promise((resolve, reject) => {
        var errorMsg    = "Payment error, please try again later!";
        var xmlhttp     = new XMLHttpRequest();
        
        if (jQuery('input.checkout-confirm-tos-checkbox').length == 1
            && !jQuery('input.checkout-confirm-tos-checkbox').is(':checked')
        ) {
            reject();
            jQuery('#confirmFormSubmit').trigger('click');
            return;
        }

        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
                if (xmlhttp.status == 200) {
                    var resp = JSON.parse(xmlhttp.response);
                    console.log('status == 200', resp);

                    if(resp.hasOwnProperty('nuveiSdkParams')
                        && resp.nuveiSdkParams.hasOwnProperty('sessionToken') 
                        && '' != resp.nuveiSdkParams.sessionToken
                        && resp.nuveiSdkParams.sessionToken == nuveiLastToken
                    ) {
                        resolve();
                        return;
                    }

                    // reload the Checkout
                    nuveiLastToken = resp.nuveiSdkParams.sessionToken;
                    checkout(resp.nuveiSdkParams);
                    return;
                }

                if (xmlhttp.status == 400) {
                    console.log('There was an error 400');
                    reject(errorMsg);
                    return;
                }

                console.log('Nuvei Ajax call unexpected error.');
                reject(errorMsg);
                return;
            }
        };

        xmlhttp.open("GET", "/nuvei_checkout?selected_pm="+jQuery('input[name="paymentMethodId"]').val(), true);
        xmlhttp.send();
    });
};

window.nuveiAfterSdkResponse = function(resp) {
    console.log('nuveiAfterSdkResponse', resp);

    // on error
    if(typeof resp.result == 'undefined') {
        alert('Error with your Payment. Please try again later!', nuveiSdkParams);
//                checkout(nuveiSdkParams);
        return;
    }

    // on success
    if(resp.result === 'APPROVED' && resp.transactionId != 'undefined') {
        console.log('Nuvei TrId', resp.transactionId);
        
        jQuery('body').find('input[name="nuveiTransactionId"]').val(resp.transactionId);

        if(resp.hasOwnProperty('ccCardNumber') && '' != resp.ccCardNumber) {
            jQuery('body').find('input[name="nuveiPaymentMethod"]').val('Credit Card');
        }
        else {
            jQuery('body').find('input[name="nuveiPaymentMethod"]').val('APM');
        }

        jQuery('#confirmFormSubmit').trigger('click');
        return;
    }

    // on decline
    if(resp.result == 'DECLINED') {
        if (resp.hasOwnProperty('errorDescription')
            && 'insufficient funds' == resp.errorDescription.toLowerCase()
        ) {
            alert('You have Insufficient funds, please go back and remove some of the items in your shopping cart, or use another card.');
            return;
        }
        
        if (resp.hasOwnProperty('reason')
            && resp.reason.search('The currency is not supported') >= 0
        ) {
            alert(resp.reason);
            return;
        }

        alert('Your Payment was DECLINED. Please try another payment method!');
        return;
    }
};