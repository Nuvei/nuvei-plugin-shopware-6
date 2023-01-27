// Import all necessary Storefront plugins
import NuveiStorefront from './nuvei';

// Register your plugin via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('NuveiStorefront', NuveiStorefront, '[class="checkout-main"]');
