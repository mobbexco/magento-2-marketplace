<?php

namespace Mobbex\Marketplace\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SplitCheckout implements ObserverInterface
{
    public function __construct(
        \Mobbex\Marketplace\Helper\Data $helper,
        \Magento\Framework\Session\SessionManagerInterface $session
    ) {
        $this->helper  = $helper;
        $this->session = $session;

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
        $vendors = $this->helper->getVendorsByOrder($observer->getOrder());

        foreach ($vendors as $cuit => $items) {
            $total = $fee = $shipping = 0;
            $productIds = [];

            foreach ($items as $item) {
                $product = $item->getProduct();

                $total       += $item->getRowTotalInclTax() - $item->getBaseDiscountAmount();
                $fee         += $this->helper->getCommission($item);
                $shipping     = $shipping ?: $this->helper->getVendorOrder($item)->getShippingInclTax();
                $productIds[] = $product->getId();
            }

            $body['split'][] = [
                'tax_id'      => $cuit,
                'description' => "Split payment - CUIT: $cuit - Product IDs: " . implode(", ", $productIds),
                'total'       => $total + $shipping,
                'reference'   => $body['reference'] . '_split_' . $cuit,
                'fee'         => $fee,
                'hold'        => (bool) $this->helper->getVendor($item)->getData('mbbx_hold') ?: false,
            ];
        }

        $this->session->setMobbexCheckoutBody($body);
    }
}