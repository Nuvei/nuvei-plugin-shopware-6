# Nuvei Simply Connect for ShopWare 6

## Requirements
Shopware 6.4.  
Enabled DMN notifications into the merchant account.  
Public access to the plugin notification URL - site_domain/nuvei_dmn/.

## Description
A Nuvei Simply Connect payment plugin for Shopware 6 with main functionality.

## Manual installation
Download the Main repo and extract the archive.

Rename the result folder "SwagNuveiCheckout" and add it to zip file with same name.

Login into the SW6 administration and go to Extensions->My Extensions. Click on "Upload extension" and pass SwagNuveiCheckout.zip file.

Please, build your storefront and install plugin assets before start using the plugin. Example:

```
bin/build-storefront.sh
bin/console assets:install
```

Again in "My Extensions" activate the plugin clicking ot the slider right of the logo and the name. Click on the "..." button on the left and select "Configure". Configure the plugin.

Add the new payment provider into the the used sale channel in "Sales Channels" menu.

## Support
Please, contact out Tech-Support team (tech-support@nuvei.com) in case of questions and difficulties.
