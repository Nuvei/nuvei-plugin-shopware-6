console.log('admin index.js');

// Put our custom JS code here, just to be sure it loads before the template
// 
// for Order Details page
function runNuveiScripts() {
	console.log('runNuveiScripts()');

	// this is not the Order Details page
	if (window.location.toString().search('/order/detail/') < 0) {
		console.log('Not Orders details page. Stop Nuvei process');
		return;
	}

	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
			if (xmlhttp.status == 200) {
				var resp = JSON.parse(xmlhttp.response);
				console.log('status == 200', resp);

				// some error
				if (!resp.hasOwnProperty('success') || 1 != resp.success) {
					if (resp.hasOwnProperty('message') && '' != resp.message) {
						alert(resp.message);
						return;
					}

					alert('Nuvei Ajax call unexpected error. Please, refresh the page!');
					return;

				}

				// SUCCESS
				nuveiBuildButtons(resp);
				return;
			}

			if (xmlhttp.status == 400) {
				console.log('There was an error 400.');
				return;
			}

			alert('Nuvei Ajax call unexpected error. Please, refresh the page!');
			return;
		}
	};

	xmlhttp.open("GET", "/api/nuvei/check_order/?hash=" + window.location.hash.replace('#', ''), true);
	xmlhttp.send(); 
}

/**
 * A help function to build Nuvei action buttons for the Order
 * 
 * @param object resp The response of the Ajax call
 * @returns void
 */
function nuveiBuildButtons(resp) {
    let statusContainer = document.querySelector('.sw-order-user-card'); // SW 6.4
	let infoCont        = document.querySelector('.sw-order-detail-base__general-info'); // SW 6.5
                
    // the main container div
    let div_1 = document.createElement('div');
    div_1.setAttribute('id', 'nuveiActions');

    // the header
    let div_2 = document.createElement('div');
    div_2.setAttribute('class', 'sw-card has--header has--title');
    div_1.appendChild(div_2);

    let div_21 = document.createElement('div');
    div_21.setAttribute('class', 'sw-card__header');
    div_2.appendChild(div_21);

    let div_3 = document.createElement('div');
    div_3.setAttribute('class', 'sw-card__titles');
    div_21.appendChild(div_3);
    
    let nuveiImg            = document.createElement('img');
    nuveiImg.src            = "/bundles/swagnuveicheckout/storefront/img/rolling.gif";
    nuveiImg.width          = 20;
    nuveiImg.style.display  = 'none';
    nuveiImg.setAttribute('id', 'nuveiLoader');
    div_21.appendChild(nuveiImg);

    // the header title
    let div_4 = document.createElement('div');
    div_4.setAttribute('class', 'sw-card__title');
    div_4.innerHTML = 'Nuvei Actions for the Order';
    div_3.appendChild(div_4);

    // the content after the header
    let div_5 = document.createElement('div');
    div_5.setAttribute('class', 'sw-card__content');
    div_2.appendChild(div_5);

    let div_6 = document.createElement('div');
    div_6.setAttribute('class', 'sw-container');
    div_6.style.display = 'block';
    div_5.appendChild(div_6);

    // create buttons
    let btnContent = document.createElement('span');
    btnContent.setAttribute('class', 'sw-button__content');

    // check for Refund button
    if (resp.hasOwnProperty('canRefund') && true === resp.canRefund) {
        let canRefund = document.createElement('button');
        canRefund.setAttribute('class', 'sw-button sw-button--primary nuveiButton');
        canRefund.setAttribute('id', 'nuveiRefundBtn');
        canRefund.setAttribute('type', 'button');
        canRefund.style.marginRight = '5px';

        let clone2 = btnContent.cloneNode(true);
        clone2.innerHTML = 'Refund';
        canRefund.appendChild(clone2);

        div_6.appendChild(canRefund);
    }

    // check for Settle button
    if (resp.hasOwnProperty('canSettle') && true === resp.canSettle) {
        let canSettle = document.createElement('button');
        canSettle.setAttribute('class', 'sw-button sw-button--primary nuveiButton');
        canSettle.setAttribute('id', 'nuveiSettleBtn');
        canSettle.setAttribute('type', 'button');
        canSettle.style.marginRight = '5px';

        let clone2 = btnContent.cloneNode(true);
        clone2.innerHTML = 'Settle';
        canSettle.appendChild(clone2);

        div_6.appendChild(canSettle);
    }

    // check for Void button
    if (resp.hasOwnProperty('canVoid') && true === resp.canVoid) {
        let canVoid = document.createElement('button');
        canVoid.setAttribute('class', 'sw-button sw-button--primary nuveiButton');
        canVoid.setAttribute('id', 'nuveiVoidBtn');
        canVoid.setAttribute('type', 'button');
        canVoid.style.marginRight = '5px';

        let clone2 = btnContent.cloneNode(true);
        clone2.innerHTML = 'Void';
        canVoid.appendChild(clone2);

        div_6.appendChild(canVoid);
    }

	// append all after the Status container
	if (statusContainer) {
		statusContainer.parentElement.after(div_1);
	}
	if (infoCont) {
		infoCont.parentElement.after(div_1);
	}
    
    nuveiSetEvents(resp);
}

function nuveiBuildNotes(resp) {
    if (!resp.hasOwnProperty('notes') || 0 == resp.notes.length) {
        return;
    }
    
    let nuveiActions = document.getElementById('nuveiActions');
                
    // the main container div
    let div_1 = document.createElement('div');
    div_1.setAttribute('id', 'nuveiNotes');

    // the header
    let div_2 = document.createElement('div');
    div_2.setAttribute('class', 'sw-card has--header has--title');
    div_1.appendChild(div_2);

    let div_21 = document.createElement('div');
    div_21.setAttribute('class', 'sw-card__header');
    div_2.appendChild(div_21);

    let div_3 = document.createElement('div');
    div_3.setAttribute('class', 'sw-card__titles');
    div_21.appendChild(div_3);
    
    // the header title
    let div_4 = document.createElement('div');
    div_4.setAttribute('class', 'sw-card__title');
    div_4.innerHTML = 'Nuvei Notes for the Order';
    div_3.appendChild(div_4);
    
    // the content after the header
    let div_5 = document.createElement('div');
    div_5.setAttribute('class', 'sw-card__content');
    div_2.appendChild(div_5);
    
    let customTable = document.createElement('table');
    customTable.setAttribute('border', 0);
    customTable.style.width = '100%';
    
    let row_1 = document.createElement('tr');
    customTable.appendChild(row_1);
    
    let th_11 = document.createElement('th');
    th_11.innerHTML = "Date";
    th_11.style.width = '150px';
    th_11.style.textAlign = 'left';
    row_1.appendChild(th_11);

    let th_12 = document.createElement('th');
    th_12.innerHTML = "Note";
    th_12.style.textAlign = 'left';
    row_1.appendChild(th_12);

    for (var trId in resp.notes) {
        // the rows
        let tabRow = document.createElement('tr');
        
        // the columns
        let tabColDate                  = document.createElement('td');
        tabColDate.innerHTML            = resp.notes[trId].date;
        tabColDate.style.verticalAlign  = 'top';
        tabColDate.style.textAlign      = 'left';
        tabRow.appendChild(tabColDate);
        
        let tabColNote                  = document.createElement('td');
        tabColNote.innerHTML            = resp.notes[trId].note;
        tabColNote.style.textAlign      = 'left';
        tabColNote.style.paddingBottom  = '10px';
        tabRow.appendChild(tabColNote);
        
        customTable.appendChild(tabRow);
    }
    
    div_5.appendChild(customTable);
    
    // append all after the Status container
    nuveiActions.after(div_1);
}

/**
 * A help function to set events to the Nuvei buttons, if any.
 * 
 * @returns void
 */
function nuveiSetEvents(resp) {
    console.log('nuveiSetEvents()');
    
    let nuveiRefundBtn  = document.getElementById("nuveiRefundBtn");
    let nuveiSettleBtn  = document.getElementById("nuveiSettleBtn");
    let nuveiVoidBtn    = document.getElementById("nuveiVoidBtn");
    
    if (nuveiRefundBtn) {
        nuveiRefundBtn.addEventListener('click', function() { 
            nuveiAction('refund', resp.orderNumber);
        });
    }
    
    if (nuveiSettleBtn) {
        nuveiSettleBtn.addEventListener('click', function() { 
            nuveiAction('settle', resp.orderNumber);
        });
    }
    
    if (nuveiVoidBtn) {
        nuveiVoidBtn.addEventListener('click', function() { 
            nuveiAction('void', resp.orderNumber);
        });
    }
    
    nuveiBuildNotes(resp);
}

function nuveiAction(action, orderNumber) {
	if (!confirm('Are you sure you want to execute this action?')) {
		return;
	}

	console.log(action, orderNumber);

	document.getElementById("nuveiLoader").style.display = "inline-block";

	// disable all Nuvie buttons
	let nuveiButtons = document.getElementsByClassName('nuveiButton');

	for (var i = 0; i < nuveiButtons.length; i++) {
		nuveiButtons[i].setAttribute('disabled', true);
	}

	var defaultErrorMsg = 'Unexpected error. Please, refresh the page and try again!';

	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
			if (xmlhttp.status == 200) {
				var response = JSON.parse(xmlhttp.response);
				console.log('Nuvei response', response);

				if (response.hasOwnProperty('success')
					&& 1 == response.success
				) {
					window.location.reload();
					return;
				}

				if (response.hasOwnProperty('message')
					&& '' != response.message
				) {
					alert(response.message);
				}
				else {
					alert(defaultErrorMsg);
				}
				// enable buttons
				for (var i = 0; i < nuveiButtons.length; i++) {
					nuveiButtons[i].removeAttribute('disabled');
				}
				// remove the loader
				document.getElementById("nuveiLoader").style.display = "none";
				return;
			}

			alert(defaultErrorMsg);

			// enable buttons
			for (var i = 0; i < nuveiButtons.length; i++) {
				nuveiButtons[i].removeAttribute('disabled');
			}
			// remove the loader
			document.getElementById("nuveiLoader").style.display = "none";
			return;
		}


	}
	xmlhttp.open(
		"GET",
		"/api/nuvei/order_action?action=" + action + "&order_number=" + orderNumber, 
		true
	);
	xmlhttp.send();
}
// /for Order Details page

// for SW 6.4.x
Shopware.Component.override('sw-order-detail-base', {
    created() {
        console.log('sw-order-detail-base created');
        
        setTimeout(function(){
            runNuveiScripts();
        }, 1000);
    }
});

// for SW 6.5.x
Shopware.Component.override('sw-order-detail-general', {
	created() {
		console.log('sw-order-detail-general created');
		
        setTimeout(function(){
            runNuveiScripts();
        }, 1000);
	}
});
