<?php

namespace Paynow;

use \Requests;

use Paynow\CreateFailedException;
use Paynow\UpdateProcessingException;

class Paynow {

    /**
     * Merchant's Paynow integration Id
     *
     * @var int
     */
	private $integrationId;

	/**
     * Merchant's Paynow integration key
     *
     * @var string
     */
	private $integrationKey;

	/**
     * Generic store for extended error messages
     *
     * @var mixed
     */
	private $error;

	/**
	 * Create a new Paynow instance
	 *
	 * @param 	int 		$integrationId Paynow integration Id
	 * @param 	string 		$integrationKey Paynow integration key
	 * @return 	void
	 */
	public function __construct($integrationId, $integrationKey)
	{
		$this->integrationId = $integrationId;
		$this->integrationKey = $integrationKey;
	}

	/**
	 * Initiates a checkout with Paynow and returns the browserurl and pollurl in an object
	 *
	 * @param 	mixed 	$reference 			Transaction reference
	 * @param 	float 	$amount 	 		Amount
	 * @param 	string 	$additionalInfo 	Additional info
	 * @param 	string 	$returnUrl 			Return URL
	 * @param 	string 	$resultUrl 			Result URL
	 * @param 	string 	$authEmail 			(Optional) Auth email to use
	 *
	 * @throws 	Paynow\CreateFailedException
	 *
	 * @return 	Object
	 */
	public function initiatePayment($reference, $amount, $additionalInfo, $returnUrl, $resultUrl, $authEmail = null)
	{		
		//prepare Paynow request
		$parameters = array (
			'id' 		=> $this->integrationId,
			'reference' => $reference,
			'amount' 	=> $amount,
			'additionalinfo' => $additionalInfo,
			'returnurl' => $returnUrl,
			'resulturl' => $resultUrl,
			'status'	=> 'Message',
			'hash'		=> '',
		);

		if ($authEmail) $parameters['authemail'] = $authEmail;

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

		if ($response['status'] === "Error") $this->createFailed($response['error']);

		if ($response['status'] !== 'Ok') $this->createFailed('Unknown Paynow status: '.$response['status']);

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
	 * Checks if a status update has a valid hash, and confirms it with Paynow via a poll
	 * 
	 *
	 * @param 	array 	$payload 	Details of the status update (Usually the contents of $_POST)
	 * @param 	string 	$pollUrl 	(Optional) The URL to use for the poll we use for confirmation.
	 *	 							Method will use the pollurl in $payload if none is provided.							
	 * @return 	null
	 */
	public function processStatusUpdate($payload, $pollUrl = null)
	{		
		if(
			!array_key_exists('reference', $payload) ||
			!array_key_exists('amount', $payload) ||
			!array_key_exists('paynowreference', $payload) ||
			!array_key_exists('pollurl', $payload) ||
			!array_key_exists('status', $payload) ||
			!array_key_exists('hash', $payload)
		) $this->updateProcessingFailed('Missing required fields', $payload);

		if (!$this->verifyHash($payload))
			$this->updateProcessingFailed('Hash verification failed', $payload);

		//Use payload's pollurl if none is provided
		if (!$pollUrl) $pollUrl = $payload['pollurl'];

		try
		{
			$res = Requests::post($pollUrl, array(), array());

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
		)) $this->updateProcessingFailed('Paynow response missing expected parameters');

		//Compare if the posted transaction matches the one we fetched
		$detailsMatch = ($payload === $response);

		if (!$detailsMatch)	$this->updateProcessingFailed('Posted transaction is different from polled transaction');

		return (object)array(
			'reference' => $payload['reference'],
			'amount' => $payload['amount'],
			'paynowreference' => $payload['paynowreference'],
			'pollurl' => $payload['pollurl'],
			'status' => $payload['status'],
			'hash' => $payload['hash'],
		);

	}

	/**
	 * Checks if the 'hash' in payload is what we expect
	 *
	 * @param $payload Array of key => values
	 *
	 * @return bool
	 */
	public function verifyHash($payload)
	{
		$hashString = '';

		foreach ($payload as $key => $value) {
			if($key === 'hash') continue;
			$hashString .= $value;
		}

		$hashString .= $this->integrationKey;
		$hashString = strtoupper(hash('sha512', $hashString));
		return ($hashString === $payload['hash']);
	}

	/**
	 * Throw a CreateFailedException with $message
	 * 
	 * @param string $message 	Exception message
	 * @param mixed  $errorData (Optional) Additional error information, saved to $this->error
	 *
	 * @return void
	 */
	private function createFailed($message, $errorData = null)
	{
		$this->error = $errorData;
		throw new CreateFailedException($message, 1);
	}

	/**
	 * Throw an UpdateProcessingException with $message
	 * 
	 * @param string $message 	Exception message
	 * @param mixed  $errorData (Optional) Additional error information, saved to $this->error
	 *
	 * @return void
	 */
	private function updateProcessingFailed($message, $errorData = null)
	{
		$this->error = $errorData;
		throw new UpdateProcessingException($message, 1);
	}

}
