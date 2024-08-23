# Nuvei Simply Connect for ShopWare 6

## Description
Nuvei supports major international credit and debit cards enabling you to accept payments from your global customers. 

A wide selection of region-specific payment methods can help your business grow in new markets. Other popular payment methods, from mobile payments to e-wallets, can be easily implemented on your checkout page.

The correct payment methods at the checkout page can bring you global reach, help you increase conversions, and create a seamless experience for your customers.

## System Requirements
- Shopware 6.5.* and up.  
- Working PHP cURL module.
- Public access to the plugin notification URL – "site_domain/nuvei_dmn/".

## Nuvei Requirements
- Enabled DMN notifications into the merchant account.  
- Set DMN notifications timeout to be at least 30 seconds.  

## Manual Installation
1. Download the last release of the plugin ("SwagNuveiCheckout.zip") or form main branch.
2. Select one of the following methods:
  - If you downloaded the plugin from some of the branches:
    1. Extract the plugin and rename the folder to "SwagNuveiCheckout".
	2. Add it to a ZIP archive.
  - If you downloaded the plugin from the Releases page continue.
3. Log in to the SW6 administration and go to Extensions->My Extensions.
4. Press "Upload extension" and upload the SwagNuveiCheckout.zip file.
5. Before starting to use the plugin, build your storefront and your administration. It contains two JS plugins that need to be added into the system. The build commands are different for Dev and Prod mode.  

  For Example:

```
    php bin/console cache:clear
    php bin/console assets:install
    php bin/console theme:compile
```

6. Again in "My Extensions", activate the plugin by pressing the slider to the right of the logo and the name.
7. Press the "..." button on the left and select "Configure". Configure the plugin.
8. Add the new payment provider into the used sale channel in "Sales Channels" menu.

## Support
Please contact our Technical Support team (tech-support@nuvei.com) for any questions and difficulties.