<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Product service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ProductsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     * @param int $localeId
     *
     * @return Commerce_ProductModel
     */
    public function getProductById($id, $localeId = null)
    {
        return craft()->elements->getElementById($id, 'Commerce_Product', $localeId);
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveProduct(Commerce_ProductModel $product)
    {
        if (!$product->id) {
            $record = new Commerce_ProductRecord();
        } else {
            $record = Commerce_ProductRecord::model()->findById($product->id);

            if (!$record) {
                throw new Exception(Craft::t('No product exists with the ID “{id}”',
                    ['id' => $product->id]));
            }
        }

        $record->postDate = $product->postDate;
        $record->expiryDate = $product->expiryDate;
        $record->typeId = $product->typeId;
        $record->authorId = $product->authorId;
        $record->promotable = $product->promotable;
        $record->freeShipping = $product->freeShipping;
        $record->taxCategoryId = $product->taxCategoryId;

        $record->validate();
        $product->addErrors($record->getErrors());

        $productType = craft()->commerce_productTypes->getProductTypeById($product->typeId);

        if(!$productType){
            throw new Exception(Craft::t('No product type exists with the ID “{id}”',
                ['id' => $product->typeId]));
        }

        $variantsValid = true;
        $defaultVariant = null;
        foreach ($product->getVariants() as $variant) {

            $titleFormat = $productType->titleFormat ? $productType->titleFormat : '{sku';
            $variant->getContent()->title = craft()->templates->renderObjectTemplate($titleFormat, $variant);

            // Make the first variant (or the last one that says it isDefault) the default.
            if ($defaultVariant === null || $variant->isDefault)
            {
                $defaultVariant = $variant;
            }

            if (!craft()->commerce_variants->validateVariant($variant)) {
                $variantsValid = false;
                if($variant->getError('title') && !$variant->getError('sku')){
                    $variant->addError('sku',Craft::t('Could not generate the title from variant. Ensure custom fields used to generate the variant title are set to required.'));
                }
            }
        }

        CommerceDbHelper::beginStackedTransaction();
        try {
            if (!$product->hasErrors() && $variantsValid) {

                 $record->defaultVariantId = $defaultVariant->getPurchasableId();
                 $record->defaultSku = $defaultVariant->getSku();
                 $record->defaultPrice = $defaultVariant->getPrice();
                 $record->defaultHeight = $defaultVariant->height;
                 $record->defaultLength = $defaultVariant->length;
                 $record->defaultWidth = $defaultVariant->width;
                 $record->defaultWeight = $defaultVariant->weight;

                if (craft()->elements->saveElement($product)) {
                    $record->id = $product->id;
                    $record->save(false);

                    $keepVariantIds = [];
                    $oldVariantIds = craft()->db->createCommand()
                        ->select('id')
                        ->from('commerce_variants')
                        ->where('productId = :productId', [':productId' => $product->id])
                        ->queryColumn();

                    foreach ($product->getVariants() as $variant) {
                        if($defaultVariant === $variant){
                            $variant->isDefault = true;
                        }else{
                            $variant->isDefault = false;
                        }
                        $variant->productId = $product->id;
                        craft()->commerce_variants->save($variant);
                        $keepVariantIds[] = $variant->id;
                    }

                    foreach (array_diff($oldVariantIds, $keepVariantIds) as $keepId) {
                        craft()->commerce_variants->deleteById($keepId);
                    }

                    CommerceDbHelper::commitStackedTransaction();

                    return true;
                }
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws \CDbException
     */
    public function deleteProduct($product)
    {
        $product = Commerce_ProductRecord::model()->findById($product->id);
        if ($product) {
            $variants = craft()->commerce_variants->getAllByProductId($product->id);
            if (craft()->elements->deleteElementById($product->id)) {
                foreach ($variants as $v) {
                    craft()->elements->deleteElementById($v->id);
                }

                return true;
            } else {

                return false;
            }
        }
    }
}
