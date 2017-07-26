<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\gateways\base\CreditCardGateway;
use Omnipay\Dummy\Gateway;

/**
 * Dummy represents a dummy gateway.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Dummy extends CreditCardGateway
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $dummyApiKey;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Dummy gateway');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['dummyApiKey'], 'required'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return Gateway::class;
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce/_components/gateways/Dummy/settings',
            [
                'gateway' => $this,
            ]);
    }

    /**
     * @inheritdoc
     */
    public function purchase()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function refund()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function capture()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund()
    {
        return true;
    }
}