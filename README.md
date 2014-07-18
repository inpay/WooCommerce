Installation
------------
Install Method:
After you have downloaded the latest plugin zip file unzip this archive and copy the folder including its contents into your plugins directory.



Configuration for Woocommerce versions 2.x and newer
-------------
1. Request an API key at InPay.pl
2. In the Admin panel click Plugins, then click Activate under Inpay Woocommerce.
3. In Admin panel click Woocommerce > Settings > Checkout > InPay.
a. Verify that the module is enabled.
b. Enter your API keys from step 2.
c. Click the Save changes button to store your settings.


Usage
-----
When a shopper chooses the Bitcoin payment method and places their order, they will be redirected to inpay.com to pay.
InPay will send a notification to your server which this plugin handles.  Then the customer will be redirected to an order summary page.
The order status in the admin panel will be "on-hold" when the order is placed and "processing" if payment has been submitted. Order notes will be added as the order progresses from "processing" to "complete". Invalid orders will be marked as "failed".
Note: This extension does not provide a means of automatically pulling a current BTC exchange rate for presenting BTC prices to shoppers. The invoice automatically displays the correctly converted bitcoin amount as determined by BitPay.


Troubleshooting
----------------
The official InPay API documentation should always be your first reference for development, errors and troubleshooting:
https://InPay.pl/docs


Version
-------

Version 1.0
  - Tested against Woocommerce 2.0.1, 2.1.0, 2.1.1, Wordpress versions 3.5.1, 3.8.1, PHP version 5.3.8
