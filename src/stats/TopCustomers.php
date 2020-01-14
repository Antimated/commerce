<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\commerce\Plugin;
use yii\db\Expression;

/**
 * Top Customers Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopCustomers extends Stat
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $handle = 'topCustomers';

    /**
     * @var string Type of start either 'total' or 'average'.
     */
    public $type = 'total';

    /**
     * @var int Number of customers to show.
     */
    public $limit = 5;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, $type = null, $startDate = null, $endDate = null)
    {
        if ($type) {
            $this->type = $type;
        }

        parent::__construct($dateRange, $startDate, $endDate);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $topCustomers = $this->_createStatQuery()
            ->select([
                new Expression('SUM([[total]]) as orderTotal'),
                new Expression('(SUM([[total]]) / COUNT([[id]])) as orderTotalAverage'),
                'customerId',
                new Expression('COUNT([[id]]) as orderCount'),
            ])
            ->groupBy('customerId')
            ->limit($this->limit);

        if ($this->type == 'average') {
            $topCustomers->orderBy(new Expression('(SUM([[total]]) / COUNT([[id]])) DESC'));
        } else {
            $topCustomers->orderBy(new Expression('SUM([[total]]) DESC'));
        }

        return $topCustomers->all();
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        return $this->handle . $this->type;
    }

    /**
     * @inheritDoc
     */
    public function processData($data)
    {
        foreach ($data as &$topCustomer) {
            $topCustomer['customer'] = Plugin::getInstance()->getCustomers()->getCustomerById((int)$topCustomer['customerId']);
        }

        return $data;
    }
}