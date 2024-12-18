# Nuvei Simply Connect for ShopWare 6

# 1.2.2
```
    * The minimum required ShopWare core version was changed to 6.6.
    * Explicitly add "stateMachineState" relation when get an Order or a Transaction, as required in ShopWare 6.6.
    * When the plugin is used on QA site, add specific parameter to the Simply Connect.
    * The option "Auto close APM popup" was removed.
```

# 1.2.1
```
    * Get the sourceApplication from a public method.
    * Pass sourceApplication to Simply Connect.
    * Formatted the readme.
```

# 1.2.0
```
    * Support for SW 6.6.*.
    * Changed sourceApplication parameter.
    * The compiled JS files for the frontstore are included.
    * This version does not support ShopWare 6.4.*.
```

# 1.1.0
```
    * Support for SW 6.5.*.
    * Add option to mask/unmask user details in the log.
```

# 1.0.4-p1
```
    * Added Tag SDK URL for test cases.
```

# 1.0.4
```
    * Added Auto-Void logic in case the DMN does not find associated order.
    * Removed the authCode from the requests parameters.
    * Removed the updateOrder request from SDK pre-payment event.
    * Fix for checkout freez when added additional product from another tab.
    * Fix for the checkout JS, when the SDK response is with status PENDING.
    * Trim merchant credetntials befor use them.
    * If validation of the checksum fails, save message to the log.
    * In the DMN Controller removed the check for empty Transaction ID and Transaction Type.
    * Lower the time who plugin wait when search for new order.
    * Set different delay time in the DMN logic according the environment.
    * Save original total and currency and save them as custom fields, use them later.
    * Disable DCC when Order total is Zero.
    * Fix for the "SDK translations" setting example.
    * Added locale for Gpay button.
```

# 1.0.3
```
    * Added new domain for Sandbox endpoints.
    * Added option to change the SDK theme.
    * Fix for the Zero-total Order amount error with the SDK.
    * Fix for the Zero-total Order when in the admin is set Block Payment methods list.
    * Pass Order amount to the SDK as string.
    * When Auth/Sale DMN come and do find the Order, return response statuse 400 to Cashier and waith for the next DMN.
```

# 1.0.2
```
    * Added new values for sourceApplication and webMasterId parameters.
```

# 1.0.1
```
    * Removed billingAddress and userData form SDK request.
    * Imported a missing class for the admin controller.
    * Force transaction type to Auth when Order total amount is 0.
```

# 1.0.0
```
    * First version with base functionality.
```