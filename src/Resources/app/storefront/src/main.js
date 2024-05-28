// Import all necessary Storefront plugins
import NuveiCheckout from './nuvei-checkout/nuvei-checkout.plugin';

// Register your plugin via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NuveiCheckout', NuveiCheckout, '[class="checkout-main"]');
