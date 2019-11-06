<?php

namespace nocksapp\craft\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use nocksapp\craft\models\RequestResponse;
use craft\commerce\omnipay\base\OffsiteGateway;
use craft\helpers\UrlHelper;
use craft\web\View;
use nocksapp\craft\models\forms\NocksOffsitePaymentForm;
use nocksapp\craft\NocksGatewayBundle;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Omnipay\Nocks\Gateway as OmnipayGateway;
use yii\base\NotSupportedException;

class Gateway extends OffsiteGateway
{
	public $supportedMethods;

	/**
	 * @var string
	 */
	public $accessToken;

	/**
	 * @var string
	 */
	public $merchant;

	/**
	 * @var string
	 */
	public $testMode;
	
	public $paymentMethods;

	public function __construct($config = []) {
		parent::__construct($config);

		$this->supportedMethods = [
			['id' => 'ideal', 'label' => Craft::t('commerce-nocks', 'iDeal'), 'sourceCurrency' => 'EUR'],
			['id' => 'sepa', 'label' => Craft::t('commerce-nocks', 'Wiretransfer'), 'sourceCurrency' => 'EUR'],
			['id' => 'bitcoin', 'label' => Craft::t('commerce-nocks', 'Bitcoin'), 'sourceCurrency' => 'BTC'],
			['id' => 'ethereum', 'label' => Craft::t('commerce-nocks', 'Ethereum'), 'sourceCurrency' => 'ETH'],
			['id' => 'gulden', 'label' => Craft::t('commerce-nocks', 'Gulden'), 'sourceCurrency' => 'NLG'],
			['id' => 'litecoin', 'label' => Craft::t('commerce-nocks', 'Litecoin'), 'sourceCurrency' => 'LTC'],
			['id' => 'balance', 'label' => Craft::t('commerce-nocks', 'Nocks Balance'), 'sourceCurrency' => null],
		];
	}

	public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
	{
		if ($paymentForm) {
			/** @var NocksOffsitePaymentForm $paymentForm */
			if ($paymentForm->paymentMethod) {
				$paymentMethodIndex = array_search($paymentForm->paymentMethod, array_column($this->supportedMethods, 'id'));

				$request['paymentMethod'] = $this->supportedMethods[$paymentMethodIndex]['id'];
				$request['sourceCurrency'] = $this->supportedMethods[$paymentMethodIndex]['sourceCurrency'];

				if ($this->supportedMethods[$paymentMethodIndex]['id'] === 'ideal') {
					$request['issuer'] = $paymentForm->issuer;
				}
			}
		}
	}

	public function completePurchase(Transaction $transaction): RequestResponseInterface
	{
		if (!$this->supportsCompletePurchase()) {
			throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
		}

		$request = $this->createRequest($transaction);
		$request['transactionId'] = json_decode($transaction->response, true)['data']['uuid'];
		$completeRequest = $this->prepareCompletePurchaseRequest($request);

		return $this->performRequest($completeRequest, $transaction);
	}

	public static function displayName(): string
	{
		return Craft::t('commerce', 'Nocks');
	}

	public function supportsWebhooks(): bool
	{
		return true;
	}

	public function getWebhookUrl(array $params = []): string
	{
		return UrlHelper::actionUrl('commerce/payments/complete-payment', $params);
	}

	public function getPaymentTypeOptions(): array
	{
		return [
			'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)')
		];
	}

	public function getSettingsHtml()
	{
		return Craft::$app->getView()->renderTemplate('commerce-nocks/gatewaySettings', [
			'gateway' => $this,
			'currency' => Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
		]);
	}

	public function getPaymentFormModel(): BasePaymentForm
	{
		return new NocksOffsitePaymentForm();
	}

	public function getPaymentFormHtml(array $params)
	{
		$defaults = [
			'paymentMethods' => array_filter($this->supportedMethods, function($supportedMethod) {
				return in_array($supportedMethod['id'], $this->paymentMethods);
			}),
			'paymentForm' => $this->getPaymentFormModel(),
			'issuers' => $this->fetchIssuers(),
		];
		
		$view = Craft::$app->getView();

		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$view->registerAssetBundle(NocksGatewayBundle::class);

		$html = $view->renderTemplate('commerce-nocks/paymentForm', array_merge($params, $defaults));
		$view->setTemplateMode($previousMode);

		return $html;
	}

	/**
	 * @param array $parameters
	 * @return mixed
	 */
	public function fetchIssuers(array $parameters = [])
	{
		$issuersRequest = $this->createGateway()->fetchIssuers($parameters);

		return $issuersRequest->sendData($issuersRequest->getData())->getIssuers();
	}

	public function rules()
	{
		$rules = parent::rules();
		$rules[] = ['paymentType', 'compare', 'compareValue' => 'purchase'];
		$rules[] = [['accessToken', 'merchant'], 'required'];

//		var_dump(Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso());
//		die();

		return $rules;
	}

	protected function createGateway(): AbstractGateway
	{
		/** @var OmnipayGateway $gateway */
		$gateway = static::createOmnipayGateway($this->getGatewayClassName());

		$gateway->setAccessToken(Craft::parseEnv($this->accessToken));
		$gateway->setTestMode($this->testMode === '1');

		return $gateway;
	}

	/**
	 * @inheritdoc
	 */
	protected function getGatewayClassName()
	{
		return '\\' . OmnipayGateway::class;
	}

	/**
	 * @inheritdoc
	 */
	protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
	{
		return new RequestResponse($response, $transaction);
	}

	/**
	 * inheritdoc
	 */
	protected function createPaymentRequest(Transaction $transaction, $card = null, $itemBag = null): array
	{
		$params = ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash];
		$returnUrl = UrlHelper::actionUrl('commerce/payments/complete-payment', $params);
		$notifyUrl = str_replace('rc.craft.local', 'umbushka.eu.ngrok.io', $this->getWebhookUrl($params));

		return [
			'merchant' => $this->merchant,
			'amount' => $transaction->paymentAmount,
			'currency' => $transaction->paymentCurrency,
			'returnUrl' => $returnUrl,
			'notifyUrl' => $notifyUrl,
			'metadata' => [
				'order_id' => $transaction->orderId,
			],
			'description' => Craft::t('commerce', 'Order').' #'.$transaction->orderId,
		];
	}
}
