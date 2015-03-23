# EasyPost Customs 
===========

WooCommerce integration for EasyPost, now with Customs Forms

EasyPost is a simple shipping API for your app or site. 

With over a million downloads, Woocommerce is the #1 shopping cart for WordPress.

This plugin allows you to use EasyPost within WooCommerce, and ship anywhere in the world. 

EasyShipper Customs is a fork of Sean Voss' [EasyShipper](http://github.com/seanvoss/easyshipper) plugin for EasyPost

------
### Settings

Enter your "from" address and EasyPost API credentials on the plugin settings page.

**Note**: You must enter dimensions and weight of your products on indivudal product pages in order to create shipping labels of all kinds.


### Shipping Internationally:

This plugin adds a box on every product page for you to enter the HS Tariff Code in order to ship internationally, and uses it to create your Customs form. These settings are available on Shipping tab of individual product pages.

**Note**: You cannot create a customs form without this number declared on your product pages.

------

### Known Issues

When "ship to different address" is selected on checkout pages, this plugin sometimes creates shipping labels with the addressee name missing.


------

### Roadmap

- Would like to make a "default" box in plugin admin that allows seller with only one category of product to set and forget HS Tariff setting

- Right now, plugin is hard-coded to retrieve only First Class and Priority rates from USPS for both domestic and int'l shipments.

- Would like to setup preferred shipping methods in plugin admin

- Plugin retrieval of rates is not always reliable. Sometimes first-class rate fails, usually because zipcode is not declared when rate is retrieved.