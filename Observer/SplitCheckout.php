<?php

namespace Mobbex\Marketplace\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SplitCheckout implements ObserverInterface
{
    public function __construct(
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Vnecoms\Vendors\Model\VendorFactory $vendorFactory,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->customField   = $customFieldFactory->create();
        $this->vendorFactory = $vendorFactory;
        $this->session       = $session;
        $this->eventManager  = $eventManager;

        $this->session->start();
    }

    /**
     * Add split data to checkout body.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        $body    = $observer->getBody();
        $vendors = $this->getVendorsByOrder($observer->getOrder());

        foreach ($vendors as $cuit => $items) {
            $total = $fee = 0;
            $productIds = [];

            foreach ($items as $item) {
                $product = $item->getProduct();

                $total       += $item->getRowTotalInclTax() ?: $product->getFinalPrice();
                $fee         += $this->getCommission($item);
                $productIds[] = $product->getId();
            }

            $body['split'][] = [
                'tax_id'      => $cuit,
                'description' => "Split payment - CUIT: $cuit - Product IDs: " . implode(", ", $productIds),
                'total'       => $total,
                'reference'   => $body['reference'] . '_split_' . $cuit,
                'fee'         => $fee,
                'hold'        => $this->customField->getCustomField($product->getVendorId(), 'vendor', 'hold') ?: false,
            ];
        }

        $this->session->setMobbexCheckoutBody($body);
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
            // Get vendor from product
            $vendorId = $item->getProduct()->getVendorId();
            $vendor   = $this->vendorFactory->create()->load($vendorId);

            // Get cuit from vendor customfield
            $customerId = $vendor->getResource()->getRelatedCustomerIdByVendorId($vendorId);
            $cuit       = $this->customField->getCustomField($customerId, 'customer', 'cuit');

            // Exit if cuit is empty
            if (empty($cuit))
                return [];

            $vendors[$cuit][] = $item;
        }

        return $vendors;
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
        // Get vendor from product
        $product = $item->getProduct();
        $vendor  = $this->vendorFactory->create()->load($product->getVendorId());

        // Set the item as invoice so Vnecoms can calculate the commission
        $item->setInvoice($item);

        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE)
            $product->setPrice($item->getPrice())->setPriceCalculation(false);

        $commission = new \Magento\Framework\DataObject(['fee' => 0]);

        $this->eventManager->dispatch(
            'ves_vendorscredit_calculate_commission',
            [
                'commission'    => $commission,
                'invoice_item'  => $item,
                'product'       => $product,
                'vendor'        => $vendor,
            ]
        );

        // Set invoice to null and return fee
        $item->setInvoice(null);
        return $commission->getFee();
    }
}