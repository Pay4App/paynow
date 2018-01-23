# Paynow PHP SDK

`composer require ihatehandles/paynow`

----

Based off the [Paynow 3rd party shopping cart or link integration guide](https://www.paynow.co.zw/Content/Paynow%203rd%20Party%20Site%20and%20Link%20Integration%20Documentation.pdf).

## Initiate a transaction

Initiating a transaction is very easy, simply instantiate the library with your Paynow integration details.

The `initiatePayment()` call will return a response as Paynow returns it, but you're most interested in the `browserurl` which you should present back to the user.

```php
<?php

require 'vendor/autoload.php';

$paynowIntegrationId = 123456;
$paynowIntegrationKey = 'abcdef';

$p = new Paynow\Paynow($paynowIntegrationId, $paynowIntegrationKey);

$reference = '123';
$amount = 10.00;
$additionalInfo = 'Payment for order '.$reference;
$returnUrl = 'http://example.com/thankyou';
$resultUrl = 'http://example.com/result';

$res = $p->initiatePayment(
	$reference,
	$amount,
	$additionalInfo,
	$returnUrl,
	$resultUrl
);

echo "<a href='".$res->browserurl."'>Make payment</a>";
```
## Verify and process a status update

I recommend making use of the `resultUrl` to receive webhook-esque transaction updates. Return URLs are fine, but for back office operations you're better off picking the more guaranteed approach because there is no guarantee the customer will make it back to your `'returnUrl`;

The library has a very handy `processStatusUpdate()` method which will process the POST'ed payload as per the Paynow docs. It will throw if anything is off, otherwise it'll return the transaction details. The most interesting property here is `status`, which we want to know is `Paid`.

```php
<?php

require 'vendor/autoload.php';

$paynowIntegrationId = 123456;
$paynowIntegrationKey = 'abcdef';

$p = new Paynow\Paynow($paynowIntegrationId, $paynowIntegrationKey);

//Method verifies status update hash, and polls Paynow to make sure
$transactionDetails = $p->processStatusUpdate($_POST);

if ($transactionDetails->status !== 'Paid') return;

//ToDo: Code to finish customer purchase, and maybe check if the transaction hasn't already been processed

```

The source is pretty self-documenting, [feel free to jump in](https://github.com/samtheson/paynow/blob/master/src/Paynow.php) and explore the extra options you can make use of.
