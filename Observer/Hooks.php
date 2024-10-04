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
                $itemsTotal = 0;

                //Get items totals
                foreach ($vendorOrder->getAllItems() as $item)
                    $itemsTotal += round($item->getRowTotalInclTax(), 2);

                //Add charges/discounts if there are differences between totals.
                $difference = $vendorOrder->getGrandTotal() - $itemsTotal;

                if($difference > 0 || $difference < 0) {
                    $label = $difference > 0 ? 'Envío/Recargos' : 'Descuento/s';

                    $body['items'][] = [
                        "description" => "$label ${$vendor->getName()}",
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

            // Update status
            $vendorOrder->setState($orderStatus)->setStatus($orderStatus);
            $vendorOrder->save();

            // Only modify totals if the payment was successful
            if ($webhook['payment']['status']['code'] < 200 || $webhook['payment']['status']['code'] > 599)
                continue;

            // Exit if order amount is already updated or if the amounts are equal
            if ((float) $vendorOrder->getMbbxFinnancialCharge() || abs($webhook['payment']['total'] - $webhook['payment']['requestedTotal']) < 1)
                continue;

            // Get total paid in mobbex
            $totalPaid = $vendorOrder->getGrandTotal() * $diff;

            // Update vendor order
            $vendorOrder->setMbbxFinnancialCharge($totalPaid - $vendorOrder->getGrandTotal());
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
