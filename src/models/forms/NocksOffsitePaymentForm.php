<?php

namespace nocksapp\craft\models\forms;

use craft\commerce\models\payments\BasePaymentForm;

class NocksOffsitePaymentForm extends BasePaymentForm
{
	public $paymentMethod;
	public $issuer;
}