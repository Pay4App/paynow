<?php

namespace Paynow;

use \Requests;

class Paynow {

	private $integrationId;
	private $integrationKey;

	private function __construct($integrationId, $integrationKey)
	{

	}

	/**
	 * Checks if the 'hash' in payload is what we expect
	 *
	 * @param $payload Array of key => values
	 * @return bool
	 */
	public function verifyPayNowHash($payload)
	{
		$hashString = '';

		foreach ($payload as $key => $value) {
			if($key == 'hash') continue;
			$hashString .= $value;
		}

		$hashString .= $this->integrationKey;
		$hashString = strtoupper(hash('sha512', $hashString));
		return ($hashString === $payload['hash']);
	}

	/**
	 * Initiates a checkout with Paynow and returns the browserurl and pollurl in an object
	 *
	 * @throws PayNowException
	 * @throws PayNowException
	 * @throws PayNowException
	 * @throws PayNowException
	 * @throws PayNowException
	 * @throws PayNowException
	 * @return object
	 */
	public function initiatePayment($reference, $totalCost, $returnUrl, $resultUrl, $authEmail = null)
	{		
		//prepare Paynow request
		$parameters = array (
			'id' 		=> $this->integrationId,
			'reference' => $this->mOrderInfo['order_id'],
			'amount' 	=> $totalCost,
			//'additionalinfo' => '',
			'returnurl' => $returnUrl,
			'resulturl' => $resultUrl,
			'authemail' => $authemail,
			'status'	=> 'Message',
		);

		foreach ($parameters as $key => $value) {
			$parameters['hash'] .= $value;
		}

		$parameters['hash'] .= $this->integrationKey;
		$parameters['hash'] = strtoupper(hash('sha512', $parameters['hash']));
		$initiateURL = 'https://www.paynow.co.zw/interface/initiatetransaction';

		//Send request to Paynow
		try
		{
			$res = Requests::post($initiateURL, array(), $parameters);
		}
		catch(Exception $e)
		{
			//Catch fatal transport layer errors.
			$this->createFailed($e->getMessage(), $e);
		}

		if (!$res->success)
			$this->createFailed('Ecountered '.$res->status_code, $res);

		parse_str($res->body, $response);
		
		if (!array_key_exists('status', $response))
			$this->createFailed('Cannot find Paynow status', $response);

		if ($response['status'] === "error") $this->createFailed($response['error']);

		if (!$response['status'] !== 'ok')
			$this->createFailed('Unknown Paynow status: '.$response['status']);

		//Check if all the other needed keys are there
		if (!array_key_exists('browserurl', $response)) 
			$this->createFailed('No browserurl in Paynow response', $response);

		if (!array_key_exists('pollurl', $response)) 
			$this->createFailed('No pollurl in Paynow response', $response);

		if (!array_key_exists('hash', $response)) 
			$this->createFailed('No hash in Paynow response', $response);
		
		if(!$this->verifyHash($response))
			$this->createFailed('Failed to verify Paynow hash', $response);

		return (object)array(
			'browserurl' => $response['browserurl'],
			'pollurl' => $response['pollurl'],
		);

	}

	/**
	 * @param array $payload Usually the contents of $_POST
	 * @return null
	 */
	public function processStatusUpdate($payload, $handleExit = false, $pollUrl = null)
	{		
		$this->handleExit = $handleExit;

		if(
			!array_key_exists('reference', $payload) ||
			!array_key_exists('amount', $payload) ||
			!array_key_exists('paynowreference', $payload) ||
			!array_key_exists('pollurl', $payload) ||
			!array_key_exists('status', $payload) ||
			!array_key_exists('hash', $payload)
		) $this->updateProcessingFailed('Missing required fields', $payload);

		if (!$this->verifyHash($payload)) $this->updateProcessingFailed('Hash verification failed', $payload);

		//Use payload's pollurl if none is provided
		if (!$pollUrl) $pollUrl = $payload['pollUrl'];

		try
		{
			$res = Requests::post($pollurl, array(), array());			

		}
		catch(Exception $e)
		{
			$this->updateProcessingFailed('Failed to reach Paynow ('. $e->getMessage() .') for: '.$pollUrl, $e);
		}

		if (!$res->success) $this->updateProcessingFailed('Failed to reach Paynow for: '.$pollUrl);
		
		parse_str($res->body, $response);

		if(!(
			array_key_exists('reference', $response) &
			array_key_exists('amount', $response) &
			array_key_exists('paynowreference', $response) &
			array_key_exists('pollurl', $response) &
			array_key_exists('status', $response) &
			array_key_exists('hash', $payload)
		)) $this->updateProcessingFailed('Missing required parameters');

		return true;
	}

}
