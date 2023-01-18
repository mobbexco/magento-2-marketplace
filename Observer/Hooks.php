<?php

namespace Mobbex\Marketplace\Observer;

class Hooks
{
    /** @var \Mobbex\Marketplace\Helper\Data */
    public $helper;

    /** @var \Magento\Framework\ObjectManagerInterface */
    public $_objectManager;

    public function __construct(
        \Mobbex\Marketplace\Helper\Data $helper,
        \Magento\Framework\ObjectManagerInterface $_objectManager
    ) {
        $this->helper         = $helper;
        $this->_objectManager = $_objectManager
    }

    /**
     * 
     * Add split data to checkout body (fired on mobbex checkout creation).
     * 
     * @param array $body
     * @param string $orderId
     * 
     * @return array
     */
    public function mobbexCheckoutRequest($body, $orderId)
    {
        //get the order
        $order = $this->_objectManager->create('\Magento\Sales\Model\OrderRepository');
        $order->loadByIncrementId($orderId);

        $vendors = $this->helper->getVendorsByOrder($order);

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

        return $body;
    }

    /**
     * Update vendor order totals using webhook data (fired on webhook receive).
     * 
     * @param array $webhook
     * @param Order $order
     */
    public function mobbexWebhookReceived($webhook, $order)
    {
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