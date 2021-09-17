<?php

namespace Mobbex\Marketplace\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
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
}