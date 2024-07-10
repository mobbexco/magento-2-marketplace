<?php

namespace Mobbex\Marketplace\Observer;

class Hooks
{
    /** @var \Mobbex\Marketplace\Helper\Data */
    public $helper;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    /** @var \Mobbex\Webpay\Model\OrderUpdate */
    public $orderUpdate;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    public function __construct(
        \Mobbex\Marketplace\Helper\Data $helper,
        \Magento\Sales\Model\Order $_order,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate,
        \Mobbex\Webpay\Helper\Config $config

    ) {
        $this->helper = $helper;
        $this->_order = $_order;
        $this->orderUpdate = $orderUpdate;
        $this->config = $config;
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

            if (in_array($this->config->get('multivendor'), ['unified', 'active'])) {

                //Total to compare with vendor grand total
                $itemsTotal = 0;

                //Get items totals
                foreach ($vendorOrder->getAllItems() as $item)
                    $itemsTotal += round($item->getPrice(), 2) * $item->getQtyOrdered();

                //Add charges/discounts if there are differences between totals.
                $difference = $vendorOrder->getGrandTotal() - $itemsTotal;

                if($difference > 0 || $difference < 0) {
                    $body['items'][] = [
                        "description" => ($difference > 0 ? 'Charges ' : 'Discounts ') . $vendor->getName(),
                        "quantity"    => 1,
                        "total"       => $difference,
                        "entity"      => $vendor->getData('mbbx_uid')
                    ];    
                }

            } else {
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
        $diff = $webhook['payment']['total'] / $webhook['payment']['requestedTotal'];

        foreach ($this->helper->getVendorOrders($order) as $vendorOrder) {
            $statusName  = $this->orderUpdate->getStatusConfigName($webhook['payment']['status']['code']);
            $orderStatus = $this->orderUpdate->config->get($statusName);

            // Set suborder status
            $vendorOrder->setState($orderStatus)->setStatus($orderStatus);

            // Avoid to modify total in refund webhooks
            if (in_array($webhook['payment']['status']['code'], ['601', '602', '603', '604', '605', '610'])) {
                $vendorOrder->save();
                continue;
            }

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
     * Cancel sub-orders for an order id.
     * 
     * @param int|string $orderId
     * 
     */
    public function mobbexCancelSubOrder($orderId)
    {
        // Cancel each sub-order
        foreach ($this->helper->getVendorOrders($orderId) as $vendorOrder) {
            $vendorOrder->registerCancellation('', true, false);
            $vendorOrder->save();
        }
    }

    /**
     * Gets entity uid from vendor item (fired on mobbex checkout creation)
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
