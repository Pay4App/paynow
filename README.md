# Paynow PHP SDK

----

Docs soon

Based off the [Paynow 3rd party shopping cart or link integration guide](https://www.paynow.co.zw/Content/Paynow%203rd%20Party%20Site%20and%20Link%20Integration%20Documentation.pdf).

## Initiate a transaction

```php
<?php

require 'vendor/autoload.php';

$p = new Paynow\Paynow(0000, 'abcdef');

$reference = '123';
$totalCost = 10.00;
$additionalInfo = 'Payment for order '.$reference;
$returnUrl = 'http://example.com/thankyou';
$resultUrl = 'http://example.com/result';

$res = $p->initiatePayment(
	$reference,
	$totalCost,
	$additionalInfo,
	$returnUrl,
	$resultUrl
);

echo "<a href='".$res->browserurl."'>Make payment</a>";
```
## Verify and process a status update

```php
<?php

require 'vendor/autoload.php';

$p = new Paynow\Paynow(0000, 'abcdef');

//Method verifies status update hash, and polls Paynow to make sure
$transactionDetails = $p->processStatusUpdate($_POST, $pollUrl = null);

if ($transactionDetails->status !== 'Paid') return;

//ToDo: Code to finish customer purchase

```