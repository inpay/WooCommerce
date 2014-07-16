<strong>(c)2014 Robertas Dereskevicius info@softdb.eu</strong>

The MIT License (MIT)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.


Installation
------------
<strong>Install Method:</strong><br />
After you have downloaded the latest plugin zip file  unzip this archive and copy the folder including its contents into your plugins directory.



Configuration for Woocommerce versions 2.x and newer
-------------
1. Create an API key at paycoin.pl.
2. In the Admin panel click Plugins, then click Activate under Inpay Woocommerce.
3. In Admin panel click Woocommerce > Settings > Checkout > Inpay.<br />
a. Verify that the module is enabled.<br />
b. Enter your API keys from step 2.<br />
c. Click the Save changes button to store your settings.


Usage
-----
When a shopper chooses the Bitcoin payment method and places their order, they will be redirected to inpay.com to pay.  Inpay will send a notification to your server which this plugin handles.  Then the customer will be redirected to an order summary page.  

The order status in the admin panel will be "on-hold" when the order is placed and "processing" if payment has been submitted. Order notes will be added as the order progresses from "processing" to "complete". Invalid orders will be marked as "failed".

Note: This extension does not provide a means of automatically pulling a current BTC exchange rate for presenting BTC prices to shoppers. The invoice automatically displays the correctly converted bitcoin amount as determined by BitPay.


Troubleshooting
----------------
The official InPay API documentation should always be your first reference for development, errors and troubleshooting:
http://paycoin.pl/docs


Version
-------

Version 1.0
  - Tested against Woocommerce 2.0.1, 2.1.0, 2.1.1, Wordpress versions 3.5.1, 3.8.1, PHP version 5.3.8
