<?php

namespace Mobbex\Marketplace\Observer;

class Hooks
{
    /** @var \Mobbex\Marketplace\Helper\Data */
    public $helper;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    public function __construct(
        \Mobbex\Marketplace\Helper\Data $helper,
        \Magento\Sales\Model\Order $_order
    ) {
        $this->helper = $helper;
        $this->_order = $_order;
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
        foreach ($this->helper->getVendorOrders($orderId) as $vendorOrder) {
            $vendor = $vendorOrder->getVendor();

            $body['split'][] = [
                'description' => "Split: VID: {$vendor->getId()} VOID: {$vendorOrder->getId()}",
                'reference'   => "$body[reference]_split_{$vendor->getId()}",
                'entity'      => $vendor->getData('mbbx_uid'),
                'tax_id'      => $vendor->getData('mbbx_cuit'),
                'total'       => (float) $vendorOrder->getGrandTotal(),
                'fee'         => $this->helper->getVendorOrderCommission($vendorOrder),
                'hold'        => (bool) $vendor->getData('mbbx_hold') ?: false,
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

    /**
     * Gets entity uid from vendor item
     * 
     * @param mixed $item
     * 
     * @return string $uid | $entity
     */
    public function mobbexGetVendorEntity($item)
    {
        return $this->helper->getVendor($item)->getData('mbbx_uid');
    }
}