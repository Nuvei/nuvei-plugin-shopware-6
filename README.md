# Nuvei Simply Connect for ShopWare 6

## Requirements
- Shopware 6.5.* and up.  
- Enabled DMN notifications into the merchant account.  
- Set DMN notifications timeout to be at least 30 seconds.  
- Public access to the plugin notification URL – "site_domain/nuvei_dmn/".

## Description
A Nuvei Simply Connect payment plugin for Shopware 6 with main functionality.

## Manual Installation
1. Download the Main repo and extract the archive.
2. Rename the result folder "SwagNuveiCheckout" and add it to a ZIP file with same name.
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