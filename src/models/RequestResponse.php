<?php

namespace nocksapp\craft\models;

use Craft;
use craft\commerce\omnipay\base\RequestResponse as BaseRequestResponse;

class RequestResponse extends BaseRequestResponse
{
	public function getMessage(): string
	{
		if ($this->response->isOpen()) {
			return Craft::t('commerce-nocks', 'We have not received a definite payment status. Depending on the payment method, it may take a while until we receive the payment');
		}
		
		if (!$this->response->isSuccessful()) {
			return Craft::t('commerce-nocks', 'The payment failed.');
		}

		if ($this->response->isCancelled()) {
			return Craft::t('commerce-nocks', 'The payment was cancelled.');
		}

		return (string)$this->response->getMessage();
	}
}
