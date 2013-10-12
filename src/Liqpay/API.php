<?php

namespace Liqpay;

use ErrorException;
use SimpleXMLElement;

class API {

	const PAYWAY_LIQPAY            = 1;
	const PAYWAY_CARD              = 2;
	const PAYWAY_DELAYED           = 4;
	const CURRENCY_USD             = 'USD';
	const CURRENCY_UAH             = 'UAH';
	const CURRENCY_EUR             = 'EUR';
	const CURRENCY_RUR             = 'RUR';
	const ERROR_BAD_REQUEST        = 1;
	const ERROR_SIGNATURE_MISMATCH = 2;
	/**
	 * Merchant ID
	 *
	 * @var string
	 */
	public $merchant = 'i0000000000';
	/**
	 * Send money API LiqPay sign
	 *
	 * @var string
	 */
	public $sendSign = '';
	/**
	 * Other operations API LiqPay sign
	 *
	 * @var string
	 */
	public $otherSign = '';
	/**
	 * API Version
	 *
	 * @var string
	 */
	public $version = '1.2';
	/**
	 * Last given xml from LiqPay (for thrown exceptions cases)
	 *
	 * @var SimpleXMLElement
	 */
	public $lastXml;

	public function __construct($merchantID = NULL, $sendSign = NULL, $otherSign = NULL, $version = NULL)
	{
		if ($merchantID) $this->merchant = $merchantID;
		if ($sendSign) $this->sendSign = $sendSign;
		if ($otherSign) $this->otherSign = $otherSign;
		if ($version) $this->version = $version;
	}

	/**
	 * Get fields to build form, so user can pay
	 *
	 * @param int $amount How much money
	 * @param string $currency Currency to bill user
	 * @param string $redirectUrl User will go to this user when he will made the payment
	 * @param string $callbackUrl
	 * @param string|int|null $orderId
	 * @param string|int|null $description
	 * @param int|null $payway Bitmask of allowed payments methods
	 * @return array
	 * @see https://liqpay.com/?do=pages&p=cnb12
	 */
	public function getFieldsForPayment($amount, $currency, $redirectUrl, $callbackUrl, $orderId = NULL,
		$description = NULL, $payway = NULL)
	{
		$paywayArray = array();
		if ($payway)
		{
			if ($payway & self::PAYWAY_CARD) $paywayArray[ ] = 'card';
			if ($payway & self::PAYWAY_LIQPAY) $payway[ ] = 'liqpay';
			if ($payway & self::PAYWAY_DELAYED) $payway[ ] = 'delayed';
			$payway = join(',', $paywayArray);
		}

		$xml = $this->_buildRequest(array(
			'version'     => $this->version,
			'merchant_id' => $this->merchant,
			'result_url'  => $redirectUrl,
			'server_url'  => $callbackUrl,
			'order_id'    => $orderId,
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $description,
			'pay_way'     => $payway
		));

		return array(
			'operation_xml' => base64_encode($xml),
			'signature'     => base64_encode(sha1($this->otherSign . $xml . $this->otherSign, 1))
		);
	}

	/**
	 * Payment callback handler
	 *
	 * @param array $request
	 * @return SimpleXMLElement
	 * @throws \ErrorException
	 */
	public function getPaymentForCallback(array $request = NULL)
	{
		if (!$request) $request = $_GET + (!empty($_POST) ? $_POST : array());

		if (empty($request[ 'operation_xml' ]) OR empty($request[ 'signature' ]))
		{
			throw new ErrorException('Request must have operation_xml and signature fields', self::ERROR_BAD_REQUEST);
		}

		$xml       = base64_decode($request[ 'operation_xml' ]);
		$signature = $request[ 'signature' ];
		$xmlObject = $this->lastXml = new SimpleXMLElement($xml);

		// Check the signature
		if (!$signature == base64_encode(sha1($this->otherSign . $xml . $this->otherSign, 1)))
		{
			throw new ErrorException('Signature mismatch', self::ERROR_SIGNATURE_MISMATCH);
		}

		return $xmlObject;
	}

	/**
	 * Send money
	 *
	 * @param string $kind Тип перевода (phone|card)
	 * @param string $orderId Номер счета
	 * @param string $recipient Получатель
	 * @param double $amount Сумма перевода
	 * @param string $currency Валюта перевода
	 * @param string $description Описание
	 * @return SimpleXMLElement
	 * @see https://liqpay.com/?do=pages&p=api
	 */
	public function sendMoney($kind, $orderId, $recipient, $amount, $currency, $description)
	{
		return $this->_send($this->sendSign, array(
			'action'      => 'send_money',
			'kind'        => $kind,
			'order_id'    => $orderId,
			'to'          => $recipient,
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $description
		));
	}

	public function sendToPhone($orderId, $recipient, $amount, $currency, $description)
	{
		return $this->sendMoney('phone', $orderId, $recipient, $amount, $currency, $description);
	}

	public function sendToCard($orderId, $recipient, $amount, $currency, $description)
	{
		return $this->sendMoney('card', $orderId, $recipient, $amount, $currency, $description);
	}

	/**
	 * Current balance
	 *
	 * @return SimpleXMLElement
	 * @see https://liqpay.com/?do=pages&p=api
	 */
	public function viewBalance()
	{
		return $this->_send($this->otherSign, array(
			'action' => 'view_balance'
		));
	}

	/**
	 * Get transaction data
	 *
	 * @param int $transactionId Идентификатор транзакции
	 * @param string $transactionOrderId Номер счета
	 * @return SimpleXMLElement
	 * @see https://liqpay.com/?do=pages&p=api
	 */
	public function viewTransaction($transactionId, $transactionOrderId)
	{
		return $this->_send($this->otherSign, array(
			'action'               => 'view_transaction',
			'transaction_id'       => $transactionId,
			'transaction_order_id' => $transactionOrderId
		));
	}

	/**
	 * Upload funds to mobile phone
	 *
	 * @param string $orderId
	 * @param string $phone
	 * @param double $amount
	 * @param string $currency
	 * @return SimpleXMLElement
	 */
	public function phoneCredit($orderId, $phone, $amount, $currency)
	{
		return $this->_send($this->otherSign, array(
			'action'   => 'phone_credit',
			'amount'   => $amount,
			'currency' => $currency,
			'phone'    => $phone,
			'order_id' => $orderId
		));
	}

	/**
	 * Отправка запроса к LiqPay
	 *
	 * @param string $sign
	 * @param array $params
	 * @return SimpleXMLElement
	 * @throws ErrorException
	 */
	private function _send($sign, $params = array())
	{
		$url        = "https://www.liqpay.com/?do=api_xml";
		$xml        = $this->_buildRequest($params);
		$sign       = base64_encode(sha1($sign . $xml . $sign, 1));
		$xmlEncoded = base64_encode($xml);

		$operationEnvelope = '
            <operation_envelope>
                <operation_xml>' . $xmlEncoded . '</operation_xml>
                <signature>' . $sign . '</signature>
            </operation_envelope>';

		$post = '<?xml version=\"1.0\" encoding=\"UTF-8\"?>
            <request>
               <liqpay>' . $operationEnvelope . '</liqpay>
            </request>';

		$headers = array("POST /?do=api_xml HTTP/1.0",
			"Content-type: text/xml;charset=\"utf-8\"",
			"Accept: text/xml",
			"Content-length: " . strlen($post)
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$response = curl_exec($ch);

		if ($response === FALSE)
		{
			throw new ErrorException(curl_error($ch), curl_errno($ch));
		}

		curl_close($ch);

		$responseXml  = $this->lastXml = simplexml_load_string($response);
		$operationXml = $this->lastXml = simplexml_load_string(base64_decode($responseXml->liqpay->operation_envelope->operation_xml));

		if ($operationXml->status != 'success')
		{
			throw new ErrorException($operationXml->response_description);
		}

		return $operationXml;
	}

	private function _buildRequest($params)
	{
		$params[ 'version' ]     = $this->version;
		$params[ 'merchant_id' ] = $this->merchant;

		$xml = '<request>';
		foreach ($params as $param => $value)
		{
			if (NULL !== $value) $xml .= '<' . $param . '>' . $value . '</' . $param . '>';
		}
		$xml .= '</request>';

		return $xml;
	}

}
