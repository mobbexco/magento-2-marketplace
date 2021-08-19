<?php

namespace Mobbex\Marketplace\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SplitCheckout implements ObserverInterface
{
    public function __construct(
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Vnecoms\Vendors\Model\Vendor $vendor,
        \Magento\Framework\Session\SessionManagerInterface $session
    ) {
        $this->customField = $customFieldFactory->create();
        $this->vendor      = $vendor;
        $this->session     = $session;

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
                $fee         += 0 * $item->getQtyOrdered();
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
     * Retrieve order items ordered by cuit of each vendor
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
            $vendorId   = $item->getProduct()->getVendorId();
            $vendor     = $this->vendor->loadByIdentifier($vendorId);

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
}