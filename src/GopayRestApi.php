<?php


namespace Markette\Gopay\Api;


use Nette\Object;
use Tracy\Debugger;

class GopayRestApi extends Object
{

	const WS = 'https://testgw.gopay.cz/';

	const RESTAPI_OAUTH2 = 'api/oauth2/token';
	const RESTAPI_PAYMENT = '/api/payments/payment';

	public function createBasePayment(
		$clientId,
		$clientSecret,
		$targetGoId,
		$productName,
		$totalPriceInCents,
		$currency,
		$orderNumber,
		$successURL,
		$failedURL,
		$paymentChannels,
		$defaultPaymentChannel,
		$preauthorized,
		$firstName,
		$lastName,
		$city,
		$street,
		$postalCode,
		$countryCode,
		$email,
		$phoneNumber,
		$p1,
		$p2,
		$p3,
		$p4,
		$lang
	)
	{
		$accessToken = $this->loadToken($clientId, $clientSecret);

		$url = self::WS;

		$url .= self::RESTAPI_PAYMENT;

		$curl = curl_init($url);

		$headers = [
			"POST ",
			"Content-type: application/json",
			"Accept: application/json",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"Authorization: Bearer " . $accessToken
		];

		$customerData = array(
			"first_name"   => $firstName,
			"last_name"    => $lastName,
			"city"         => $city,
			"street"       => $street,
			"postal_code"  => $postalCode,
			"country_code" => $countryCode,
			"email"        => $email,
			"phone_number" => $phoneNumber,
		);

		$items = [
			[
				"name"   => trim($productName),
				"amount" => (int)$totalPriceInCents
			]
		];

		$paymentCommand = [
			"payer"             => [
				"default_payment_instrument"  => $defaultPaymentChannel,
				"allowed_payment_instruments" => $paymentChannels,
				"default_swift"               => 'FIOBCZPP',
				"contact"                     => $customerData
			],
			"target"            => [
				"type" => 'ACCOUNT',
				"goid" => (float)$targetGoId
			],
			"amount"            => (int)$totalPriceInCents,
			"currency"          => trim($currency),
			"order_number"      => trim($orderNumber),
			"order_description" => trim($productName),
			"items"             => $items,
			"preauthorization"  => $preauthorized,
			"callback"          => [
				"return_url"       => trim($successURL),
				"notification_url" => trim($failedURL),
			],
			"lang"              => $lang

		];

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($paymentCommand));

		$curl_response = curl_exec($curl);

		curl_close($curl);

		$responseArray = (array)json_decode($curl_response);

		/*
		 * Kontrola stavu platby - musi byt ve stavu CREATED, kontrola parametru platby
		 */
		if ($responseArray['state'] == GopayHelper::CREATED
			&& $responseArray['id'] > 0
		) {
			return $responseArray;
		} else {
			throw new \Exception("Create payment failed: " . $responseArray['id']);
		}
	}

	public function createStandardPayment(
		$clientId,
		$clientSecret,
		$targetGoId,
		$productName,
		$totalPriceInCents,
		$currency,
		$orderNumber,
		$successURL,
		$failedURL,
		$paymentChannels,
		$defaultPaymentChannel,
		$firstName,
		$lastName,
		$city,
		$street,
		$postalCode,
		$countryCode,
		$email,
		$phoneNumber,
		$p1,
		$p2,
		$p3,
		$p4,
		$lang)
	{
		return $this->createBasePayment($clientId,
			$clientSecret,
			$targetGoId,
			$productName,
			$totalPriceInCents,
			$currency,
			$orderNumber,
			$successURL,
			$failedURL,
			$paymentChannels,
			$defaultPaymentChannel,
			false,
			$firstName,
			$lastName,
			$city,
			$street,
			$postalCode,
			$countryCode,
			$email,
			$phoneNumber,
			$p1,
			$p2,
			$p3,
			$p4,
			$lang);
	}

	public function createPreauthorizedPayment(
		$clientId,
		$clientSecret,
		$targetGoId,
		$productName,
		$totalPriceInCents,
		$currency,
		$orderNumber,
		$successURL,
		$failedURL,
		$paymentChannels,
		$defaultPaymentChannel,
		$firstName,
		$lastName,
		$city,
		$street,
		$postalCode,
		$countryCode,
		$email,
		$phoneNumber,
		$p1,
		$p2,
		$p3,
		$p4,
		$lang)
	{
		return $this->createBasePayment($clientId,
			$clientSecret,
			$targetGoId,
			$productName,
			$totalPriceInCents,
			$currency,
			$orderNumber,
			$successURL,
			$failedURL,
			$paymentChannels,
			$defaultPaymentChannel,
			true,
			$firstName,
			$lastName,
			$city,
			$street,
			$postalCode,
			$countryCode,
			$email,
			$phoneNumber,
			$p1,
			$p2,
			$p3,
			$p4,
			$lang);
	}

	public function getStateOfPayment($clientId, $clientSecret, $paymentId)
	{
		$accessToken = $this->loadToken($clientId, $clientSecret);

		$url = self::WS;

		$url .= self::RESTAPI_PAYMENT . '/' . $paymentId;

		$curl = curl_init($url);

		$headers = array(
			"GET ",
			"Content-type: application/x-www-form-urlencoded",
			"Accept: application/json",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"Authorization: Bearer " . $accessToken
		);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, false);

		$curl_response = curl_exec($curl);

		curl_close($curl);

		$responseArray = (array)json_decode($curl_response);
		return $responseArray['state'];
	}

	private function loadToken($clientId, $clientSecret)
	{
		$url = self::WS;

		$url .= self::RESTAPI_OAUTH2;

		$curl = curl_init($url);

		$headers = array(
			"POST ",
			"Content-type: application/x-www-form-urlencoded",
			"Accept: application/json",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"Authorization: Basic " . base64_encode($clientId . ':' . $clientSecret)
		);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&scope=payment-all');

		$curl_response = curl_exec($curl);

		curl_close($curl);

		$responseArray = (array)json_decode($curl_response);

		return $responseArray['access_token'];
	}
}