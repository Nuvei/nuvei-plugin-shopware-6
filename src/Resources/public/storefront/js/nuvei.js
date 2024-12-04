console.log('nuvei public script loaded');

window.nuveiLastToken   = '';
window.nuveiSdkParams   = {};
window.nuveiTexts       = {};

window.nuveiRenderCheckout = function() {
    console.log('nuveiRenderCheckout');
    
    var errorMsg        = "Unexpected error, please try different payment method!";
    var xmlhttp         = new XMLHttpRequest();
    let checkoutMain    = document.querySelector('div.checkout-main');
    
    // add simple blocker
    if (checkoutMain && !checkoutMain.querySelector('#nuvei_blocker')) {
        let nuveiBlocker    = document.createElement('div');
        let nuveiBlockerImg = document.createElement('img');
        
        nuveiBlocker.id                     = 'nuvei_blocker';
        nuveiBlocker.style.position         = 'fixed';
        nuveiBlocker.style.width            = '100%';
        nuveiBlocker.style.height           = '100%';
        nuveiBlocker.style.backgroundColor  = 'rgba(0, 0, 0, 0.5)';
        nuveiBlocker.style.top              = 0;
        nuveiBlocker.style.left             = 0;
        nuveiBlocker.style.zIndex           = 999;
        nuveiBlocker.style.paddingLeft      = '50%';
        nuveiBlocker.style.paddingTop       = '50vh';
        
        nuveiBlockerImg.src                 = '/bundles/swagnuveicheckout/storefront/img/rolling.gif';
        nuveiBlockerImg.style.width         = '100px';
        nuveiBlockerImg.style.marginLeft    = '-50px';
        
        nuveiBlocker.appendChild(nuveiBlockerImg);
        checkoutMain.appendChild(nuveiBlocker);
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

                    checkoutMain.querySelector('#nuvei_blocker').style.display = 'none';
                    return;
                }

                // hide default submit order button
                document.querySelector('#confirmFormSubmit').style.display = 'none';

                // set text translations
                nuveiTexts = response.texts;

                // call Checkout SDK
                response.nuveiSdkParams.onResult    = nuveiAfterSdkResponse;
                response.nuveiSdkParams.prePayment  = nuveiUpdateCart;
                
                if ('shopwareautomation.gw-4u.com' === window.location.host) {
                    response.nuveiSdkParams.webSdkEnv = 'devmobile'; 
                }
                
                nuveiLastToken = response.nuveiSdkParams.sessionToken;

                console.log('nuveiSdkParams', response.nuveiSdkParams);

                checkout(response.nuveiSdkParams);
                checkoutMain.querySelector('#nuvei_blocker').style.display = 'none';
                return;
            }

            if (xmlhttp.status == 400) {
                console.log('There was an error 400');
                
                alert(errorMsg);
                checkoutMain.querySelector('#nuvei_blocker').style.display = 'none';
                return;
            }

            console.log('Nuvei Ajax call unexpected error.');

            alert(errorMsg);
            checkoutMain.querySelector('#nuvei_blocker').style.display = 'none';
            return;
        }
    };

	let paymentMethod = document.querySelector('input[name="paymentMethodId"]');
	
    xmlhttp.open("GET", "/nuvei_checkout?selected_pm=" + (paymentMethod ? paymentMethod.value : ''), true);
    xmlhttp.send();
};

window.nuveiUpdateCart = function() {
    console.log('nuveiUpdateCart');

    return new Promise((resolve, reject) => {
        var errorMsg        = "Payment error, please try again later!";
        var xmlhttp         = new XMLHttpRequest();
        let inputConfirm    = document.querySelector('input.checkout-confirm-tos-checkbox');
        
        if (inputConfirm && !inputConfirm.checked) {
            reject();
            document.querySelector('#confirmFormSubmit').click();
            return;
        }

        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
                if (xmlhttp.status == 200) {
                    var resp = JSON.parse(xmlhttp.response);
                    
                    console.log('status == 200', resp);
                    
                    if (!resp.hasOwnProperty('success') || 0 == resp.success) {
                        reject();
                        
                        if (!alert(nuveiTexts.cardNeedToRefresh)) {
                            window.location.reload();
                        }
                        
                        return;
                    }

                    resolve();
                    return;
                }

                if (xmlhttp.status == 400) {
                    console.log('There was an error 400');
                    
                    if (!alert(errorMsg)) {
                        reject();
                    }
                    
                    return;
                }

                console.log('Nuvei Ajax call unexpected error.');
                
                if (!alert(errorMsg)) {
                    reject();
                }
                
                return;
            }
        };

		let paymentMethod = document.querySelector('input[name="paymentMethodId"]');

        xmlhttp.open("GET", "/nuvei_prepayment?selected_pm=" + (paymentMethod ? paymentMethod.value : ''), true);
        xmlhttp.send();
    });
};

window.nuveiAfterSdkResponse = function(resp) {
    console.log('nuveiAfterSdkResponse', resp);
    
    // expired session
    if (resp.hasOwnProperty('session_expired') && resp.session_expired) {
        window.location.reload();
        return;
    }

    // on error
    if(typeof resp.result == 'undefined') {
        alert('Error with your Payment. Please try again later!', nuveiSdkParams);
//                checkout(nuveiSdkParams);
        return;
    }

    // on success
    if( ('APPROVED' == resp.result || 'PENDING' == resp.result)
        && resp.transactionId != 'undefined'
    ) {
        console.log('Nuvei TrId', resp.transactionId);
        
        document.querySelector('input[name="nuveiTransactionId"]').value = resp.transactionId;

        if(resp.hasOwnProperty('ccCardNumber') && '' != resp.ccCardNumber) {
            document.querySelector('input[name="nuveiPaymentMethod"]').value = 'Credit Card';
        }
        else {
            document.querySelector('input[name="nuveiPaymentMethod"]').value = 'APM';
        }

        document.querySelector('#confirmFormSubmit').click();
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