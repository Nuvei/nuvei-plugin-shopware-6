console.log('admin index.js');

//  add this script for easy develpment
var _nuveiScript = document.createElement('script');
_nuveiScript.src = '/bundles/swagnuveicheckout/administration/js/nuvei-admin.js';

document.head.appendChild(_nuveiScript);

// for SW 6.4.x
import templateOld from './nuvei-checkout-old.html.twig';

Shopware.Component.override('sw-order-detail-base', {
	template: templateOld,
	
    created() {
        console.log('sw-order-detail-base created');
        runNuveiScripts();
    }
});

// for SW 6.5.x
import templateNew from './nuvei-checkout-new.html.twig';

Shopware.Component.override('sw-order-detail-general', {
    template: templateNew,
	
	created() {
		console.log('sw-order-detail-general created');
		runNuveiScripts();
	}
});