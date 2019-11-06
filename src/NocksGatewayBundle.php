<?php

namespace nocksapp\craft;

use craft\web\AssetBundle;

class NocksGatewayBundle extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = '@nocksapp/craft/resources';

		$this->js = [
			'js/paymentForm.js',
		];

		parent::init();
	}
}
