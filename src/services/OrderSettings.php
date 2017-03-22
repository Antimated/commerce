<?php
namespace craft\commerce\services;

use craft\commerce\helpers\Db;
use craft\commerce\models\OrderSettings as OrderSettingsModel;
use craft\commerce\records\OrderSettings as OrderSettingsRecord;
use yii\base\Component;

/**
 * Order settings service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderSettings extends Component
{
    /**
     * @var
     */
    private $_orderSettingsById;

    /**
     * @param int $orderSettingsId
     *
     * @return \craft\commerce\models\OrderSettings|null
     */
    public function getOrderSettingById($orderSettingsId)
    {
        if (!isset($this->_orderSettingsById) || !array_key_exists($orderSettingsId, $this->_orderSettingsById)) {
            $result = OrderSettingsRecord::model()->findById($orderSettingsId);

            if ($result) {
                $orderSetting = new OrderSettingsModel($result);
            } else {
                $orderSetting = null;
            }

            $this->_orderSettingsById[$orderSettingsId] = $orderSetting;
        }

        if (isset($this->_orderSettingsById[$orderSettingsId])) {
            return $this->_orderSettingsById[$orderSettingsId];
        }
    }

    /**
     * @param string $handle
     *
     * @return OrderSettingsModel|null
     */
    public function getOrderSettingByHandle($handle)
    {
        $result = OrderSettingsRecord::model()->findByAttributes(['handle' => $handle]);

        if ($result) {
            $orderSetting = new OrderSettingsModel($result);
            $this->_orderSettingsById[$orderSetting->id] = $orderSetting;

            return $orderSetting;
        }

        return null;
    }

    /**
     * @param OrderSettingsModel $orderSettings
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveOrderSetting(OrderSettingsModel $orderSettings)
    {
        if ($orderSettings->id) {
            $orderSettingsRecord = OrderSettingsRecord::model()->findById($orderSettings->id);
            if (!$orderSettingsRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order settings exists with the ID “{id}”',
                    ['id' => $orderSettings->id]));
            }

            $oldOrderSettings = new OrderSettingsModel($orderSettingsRecord);
            $isNewOrderSettings = false;
        } else {
            $orderSettingsRecord = new OrderSettingsRecord();
            $isNewOrderSettings = true;
        }

        $orderSettingsRecord->name = $orderSettings->name;
        $orderSettingsRecord->handle = $orderSettings->handle;

        $orderSettingsRecord->validate();
        $orderSettings->addErrors($orderSettingsRecord->getErrors());

        if (!$orderSettings->hasErrors()) {
            Db::beginStackedTransaction();
            try {
                if (!$isNewOrderSettings && $oldOrderSettings->fieldLayoutId) {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldOrderSettings->fieldLayoutId);
                }

                // Save the new one
                $fieldLayout = $orderSettings->getFieldLayout();
                craft()->fields->saveLayout($fieldLayout);

                // Update the calendar record/model with the new layout ID
                $orderSettings->fieldLayoutId = $fieldLayout->id;
                $orderSettingsRecord->fieldLayoutId = $fieldLayout->id;

                // Save it!
                $orderSettingsRecord->save(false);

                // Now that we have a calendar ID, save it on the model
                if (!$orderSettings->id) {
                    $orderSettings->id = $orderSettingsRecord->id;
                }

                // Update service's cache
                $this->_orderSettingsById[$orderSettings->id] = $orderSettings;

                Db::commitStackedTransaction();
            } catch (\Exception $e) {
                Db::rollbackStackedTransaction();
                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

}