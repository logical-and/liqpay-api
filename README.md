LiqPay API extension with Packagist compatibiluty
==================

```php
try
		{
			$api = $this->getAPI();
			$xml = $api->getPaymentForCallback($request->request->all());

			switch ($xml->status)
			{
				case 'success':
					$this->updateInvoice($xml->order_id, InvoiceDetail::STATUS_PAID);
					break;

				case 'wait_secure':
					$this->updateInvoice($xml->order_id, InvoiceDetail::STATUS_PROCESSING);
					break;

				default:
					$this->updateInvoice($xml->order_id, InvoiceDetail::STATUS_FAILURE);
			}

		} catch (\ErrorException $e)
		{
			/** @noinspection PhpUndefinedVariableInspection */
			$xml = $api->lastXml;
			switch ($e->getCode())
			{
				case API::ERROR_BAD_REQUEST:
					throw new NotFoundHttpException();

				case API::ERROR_SIGNATURE_MISMATCH:
					if ($xml->order_id)
					{
						$this->getLogger()->warn('Signature mismatch for OrderDetail#id ' . $xml->order_id);
					}
					throw new BadRequestHttpException($e->getMessage());

				default:
					throw $e;
			}
		}
```

```php
$fields = $this->getAPI()->getFieldsForPayment(
	100,
	API::CURRENCY_USD,
	$redirect,
	$callback,
	$orderDetail->getId(),
	'Perevod polzovately "' . $orderDetail->getUserToPay()->getEmail() . '" ot polzovatelya "' . $orderDetail->getInvoice()->getUser()->getEmail() . '"',
	API::PAYWAY_LIQPAY | API::PAYWAY_CARD
)
```

```php
$api->sendToPhone('ORDER_1', '380661234567', '10', 'UAH', 'Payment description');
$balance = $api->viewBalance();
```

Based on https://github.com/4you4ever/yii-liqpay