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

    public function __construct(
        \Vnecoms\Vendors\Model\VendorFactory $vendorFactory,
        \Vnecoms\VendorsSales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->orderFactory  = $orderFactory;
        $this->eventManager  = $eventManager;
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
     * Retrieve order items ordered by cuit of each vendor.
     * 
     * @param Order $order
     * 
     * @return array
     */
    public function getVendorsByOrder($order)
    {
        $vendors = [];

        foreach ($order->getAllVisibleItems() as $item) {
            // Get cuit from item vendor
            $cuit = $this->getVendor($item)->getData('mbbx_cuit');

            // Exit if cuit is empty
            if (empty($cuit))
                return [];

            $vendors[$cuit][] = $item;
        }

        return $vendors;
    }

    /**
     * Search seller uid of a product and returns it in entity position
     * 
     * @param object $item
     * 
     * @return string $uid |$entity
     */
    public function getVendorUid($item)
    {
        $entity = '';
        // Get vendor uid from vnecoms vendor information or vendor id from product
        $uid = $this->getVendor($item)->getData('mbbx_uid') ? $this->getVendor($item)->getData('mbbx_uid') : $item->getProduct()->getVendorId();

        return $uid ?: $entity;
    }

    /**
     * Get vendor orders from magento parent order.
     * 
     * @param Order $order
     * 
     * @return Vnecoms\VendorsSales\Model\Order[]
     */
    public function getVendorOrders($order)
    {
        $vendorOrders = [];

        foreach ($this->getVendorsByOrder($order) as $items)
            $vendorOrders[] = $this->getVendorOrder($items[0]);

        return $vendorOrders;
    }

    /**
     * Retrieve vendor order commission calculated by Vnecoms.
     * 
     * @param Vnecoms\VendorsSales\Model\Order $order
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