<?php

namespace Mobbex\Marketplace\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Vnecoms\Vendors\Model\VendorFactory */
    public $vendorFactory;

    /** @var \Vnecoms\VendorsSales\Model\OrderFactory */
    public $orderFactory;

    /** @var \Magento\Framework\Event\ManagerInterface */
    public $eventManager;

    /** @var \Vnecoms\VendorsSales\Model\ResourceModel\Order\CollectionFactory */
    public $vendorOrderCF;

    public function __construct(
        \Vnecoms\Vendors\Model\VendorFactory $vendorFactory,
        \Vnecoms\VendorsSales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Vnecoms\VendorsSales\Model\ResourceModel\Order\CollectionFactory $vendorOrderCF
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->orderFactory  = $orderFactory;
        $this->eventManager  = $eventManager;
        $this->vendorOrderCF = $vendorOrderCF;
    }

    /**
     * Get vendor from an item.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return Vnecoms\Vendors\Model\Vendor
     */
    public function getVendor($item)
    {
        return $this->vendorFactory->create()->load($item->getProduct()->getVendorId());
    }

    /**
     * Get vendor order from an item.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return Vnecoms\VendorsSales\Model\Order
     */
    public function getVendorOrder($item)
    {
        return $this->orderFactory->create()->load($item->getVendorOrderId());
    }

    /**
     * Get item commission.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return int
     */
    public function getCommission($item)
    {
        // Support configurable products calculation
        $product = $item->getProduct();
        $product->getTypeId() == 'configurable' ? $product->setPrice($item->getPrice())->setPriceCalculation(false) : null;

        // Set the vendor order as invoice so Vnecoms can get store id
        $item->setInvoice($this->getVendorOrder($item));

        $commission = new \Magento\Framework\DataObject(['fee' => 0]);

        $this->eventManager->dispatch(
            'ves_vendorscredit_calculate_commission',
            [
                'commission'    => $commission,
                'invoice_item'  => $item,
                'product'       => $product,
                'vendor'        => $this->getVendor($item),
            ]
        );

        // Set invoice to null and return fee
        $item->setInvoice(null);
        return $commission->getFee();
    }

    /**
     * Get vendor orders from magento parent order.
     * 
     * @param Order|int|string $order The instance or him id.
     * 
     * @return \Vnecoms\VendorsSales\Model\Order[]
     */
    public function getVendorOrders($order)
    {
        return $this->vendorOrderCF->create()->addFieldToFilter(
            'order_id',
            is_object($order) ? $order->getId() : $order
        );
    }

    /**
     * Retrieve vendor order commission calculated by Vnecoms.
     * 
     * @param \Vnecoms\VendorsSales\Model\Order $order
     * 
     * @return int
     */
    public function getVendorOrderCommission($order)
    {
        $amount = 0;

        foreach ($order->getAllVisibleItems() as $item)
            $amount += $this->getCommission($item);

        return $amount;
    }
}