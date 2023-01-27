// just load nuvei external js file
import './nuvei-order';

// try to customize some of the Order Details templates
//import template from './nuvei-order/order.html.twig';

Shopware.Component.override('sw-order-state-history-card', {
    //template
    created() {
        console.log('Order Details template created');
        runNuveiScripts();
    }
});