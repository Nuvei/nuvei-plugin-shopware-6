// just load nuvei external js file
import './nuvei-order';

// try to customize some of the Order Details templates
//import template from './nuvei-order/order.html.twig';

// run script on Order Details page
Shopware.Component.override('sw-order-state-history-card', {
    //template
    created() {
        console.log('Order Details template created');
        runNuveiScripts();
    }
});

// run script on Order list page
Shopware.Component.override('sw-order-list', {
    //template
    created() {
        console.log('Order list created');
        runNuveiOrderListScript();
    }
});