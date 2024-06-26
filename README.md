# Nuvei Simply Connect for ShopWare 6

## Requirements
Shopware 6.5.* and up.  
Enabled DMN notifications into the merchant account.  
Set DMN notifications timeout to be at least 30 seconds.  
Public access to the plugin notification URL - site_domain/nuvei_dmn/.

## Description
A Nuvei Simply Connect payment plugin for Shopware 6 with main functionality.

## Manual installation
Download the Main repo and extract the archive.

Rename the result folder "SwagNuveiCheckout" and add it to zip file with same name.

Login into the SW6 administration and go to Extensions->My Extensions. Click on "Upload extension" and pass SwagNuveiCheckout.zip file.

Please, build your storefront and your administration before start using the plugin. It contains two JS plugins, who need to be added into the system. The build commands are different for Dev and Prod mode. Example:
```
php bin/console cache:clear
php bin/console assets:install
php bin/console theme:compile
```
Again in "My Extensions" activate the plugin clicking ot the slider right of the logo and the name. Click on the "..." button on the left and select "Configure". Configure the plugin.

Add the new payment provider into the the used sale channel in "Sales Channels" menu.

## Support
Please, contact out Tech-Support team (tech-support@nuvei.com) in case of questions and difficulties. Check also our [Documentation](https://docs.nuvei.com/documentation/plugins-docs/shopware-6/).