# Nuvei Simply Connect for ShopWare 6

# 1.0.4
```
    * Added Auto-Void logic in case the DMN does not find associated order.
    * Fix for checkout freez when added additional product from another tab.
    * Trim merchant credetntials befor use them.
    * If validation of the checksum fails, save message to the log.
    * In the DMN Controller removed the check for empty Transaction ID and Transaction Type.
    * Lower the time who plugin wait when search for new order.
    * Fix for the checkout JS, when the SDK response is with status PENDING.
    * Removed the authCode from the requests parameters.
    * Set different delay time in the DMN logic according the environment.
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