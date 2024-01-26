console.log('nuvei-admin.js');

// main function for Order Details page
function runNuveiScripts() {
	console.log('runNuveiScripts');

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
				nuveiBuildNotes(resp);
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
	let nuveiActionBtns = document.querySelectorAll('#nuveiActions .sw-container .sw-button');
	
	if (nuveiActionBtns.length == 0) {
		console.log('Nuvei Error - action buttons are missing!');
		return;
	}

	// check for Refund button
	if (resp.hasOwnProperty('canRefund') && true === resp.canRefund) {
		document.querySelector('#nuveiActions .sw-container #nuveiRefundBtn').style.display = 'inline';
		document.querySelector('#nuveiRefundBtn').addEventListener('click', function() { 
			nuveiAction('refund', resp.orderNumber);
		});
	}

	// check for Settle button
	if (resp.hasOwnProperty('canSettle') && true === resp.canSettle) {
		document.querySelector('#nuveiActions .sw-container #nuveiSettleBtn').style.display = 'inline';
		document.querySelector('#nuveiSettleBtn').addEventListener('click', function() { 
			nuveiAction('settle', resp.orderNumber);
		});
	}

	// check for Void button
	if (resp.hasOwnProperty('canVoid') && true === resp.canVoid) {
		document.querySelector('#nuveiActions .sw-container #nuveiVoidBtn').style.display = 'inline';
		document.querySelector('#nuveiVoidBtn').addEventListener('click', function() { 
			nuveiAction('void', resp.orderNumber);
		});
	}
}

function nuveiBuildNotes(resp) {
	if (!resp.hasOwnProperty('notes') || 0 == resp.notes.length) {
		return;
	}

	let notesTable	= document.querySelector('#nuveiNotes table');
	let htmlRows = '';

	for (var trId in resp.notes) {
		htmlRows += '<tr><td style="vertical-align: top; text-align: left;">'
			+ resp.notes[trId].date + '</td><td style="text-align: left; padding-bottom: 10px;">'
			+ resp.notes[trId].note +'</td></tr>';
	}
	
	notesTable.innerHTML		+= htmlRows;
	notesTable.style.display	= 'block';
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

/**
 * /Order Details page
 */