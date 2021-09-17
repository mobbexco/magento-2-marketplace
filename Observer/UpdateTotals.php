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

        // Only update if payment has a discount
        if ($webhook['payment']['total'] >= $order->getGrandTotal())
            return;

        // Get discount percentage
        $discount = $webhook['payment']['total'] / $order->getGrandTotal();

        foreach ($this->helper->getVendorOrders($order) as $vendorOrder) {
            // Get current vendor order totals
            $vendorTotal    = $vendorOrder->getGrandTotal();
            $discountAmount = $vendorOrder->getDiscountAmount();

            // Update vendor order
            $vendorOrder->setDiscountAmount($discountAmount + ($vendorTotal - ($vendorTotal * $discount)));
            $vendorOrder->setGrandTotal($vendorTotal * $discount);
            $vendorOrder->setTotalPaid($vendorTotal * $discount);
            $vendorOrder->setTotalDue(0);

            $vendorOrder->save();
        }
    }
}