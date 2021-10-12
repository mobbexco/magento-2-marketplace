<?php

namespace Mobbex\Marketplace\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class UpdateTotals implements ObserverInterface
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
     * Update vendor order totals using webhook data.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        // Get webhook data from observer
        $order   = $observer->getOrder();
        $webhook = $observer->getWebhook();

        // Get diff percentage
        $diff = $webhook['payment']['total'] / $order->getGrandTotal();

        foreach ($this->helper->getVendorOrders($order) as $vendorOrder) {
            // Get current vendor order totals
            $orderTotal    = $vendorOrder->getGrandTotal();
            $discountTotal = $vendorOrder->getDiscountAmount();

            // Get total paid in mobbex
            $totalPaid = $orderTotal * $diff;

            if ($diff < 1) {
                $vendorOrder->setDiscountAmount($discountTotal + ($orderTotal - $totalPaid));
            } else if ($diff > 1) {
                $vendorOrder->setMbbxFinnancialCharge($totalPaid - $orderTotal);
            }

            // Update vendor order
            $vendorOrder->setGrandTotal($totalPaid);
            $vendorOrder->setTotalPaid($totalPaid);
            $vendorOrder->setTotalDue(0);
            $vendorOrder->setMbbxFinalCommission($this->helper->getVendorOrderCommission($vendorOrder) * $diff);

            $vendorOrder->save();
        }
    }
}