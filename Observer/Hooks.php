<?php

namespace Mobbex\Marketplace\Observer;

class Hooks
{
    /** @var \Mobbex\Marketplace\Helper\Data */
    public $helper;

    /** @var \Mobbex\Marketplace\Helper\Refund */
    public $refund;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    /** @var \Mobbex\Webpay\Model\OrderUpdate */
    public $orderUpdate;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\Registry */
    public $registry;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Marketplace\Helper\Data $helper,
        \Magento\Sales\Model\Order $_order,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Marketplace\Helper\Refund $refund,
        \Magento\Framework\Registry $registry,
        \Mobbex\Webpay\Helper\Logger $logger

    ) {
        $this->helper = $helper;
        $this->_order = $_order;
        $this->orderUpdate = $orderUpdate;
        $this->config = $config;
        $this->refund = $refund;
        $this->registry = $registry;
        $this->logger = $logger;
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
        foreach ($this->helper->getVendorOrders($order) as $vendorOrder) {
            $statusName  = $this->orderUpdate->getStatusConfigName($webhook['payment']['status']['code']);
            $orderStatus = $this->orderUpdate->config->get($statusName);

            // Update status
            $vendorOrder->setState($orderStatus)->setStatus($orderStatus);
            $vendorOrder->save();

            // Get vendor child webhook
            $vendor = $vendorOrder->getVendor();
            $vendorWebhook = current(array_filter($webhook['childs'], function ($child) use ($vendor) {
                return $child['entity']['uid'] == $vendor->getData('mbbx_uid');
            }));

            //Get totals difference:
            $diff = $vendorWebhook['payment']['total'] - $vendorWebhook['payment']['requestedTotal'];

            if ($webhook['payment']['status']['code'] < 200 || $webhook['payment']['status']['code'] > 599)
                continue;

            // Exit if order amount is already updated or if the amounts are equal
            if ((float) $vendorOrder->getMbbxFinnancialCharge() || abs($diff) < 1)
                continue;

            // Update vendor order
            $vendorOrder->setMbbxFinnancialCharge($diff);
            $vendorOrder->setGrandTotal($vendorWebhook['payment']['total']);
            $vendorOrder->setTotalPaid($vendorWebhook['payment']['total']);
            $vendorOrder->setTotalDue(0);
            $vendorOrder->setMbbxFinalCommission($this->helper->getVendorOrderCommission($vendorOrder) * $diff);

            $vendorOrder->save();
        }
    }

    /**
     * Update vendor order totals using webhook data (fired on webhook receive).
     * 
     * @param array $webhook
     * @param Order $order
     */
    public function mobbexChildWebhookReceived($webhook, $order)
    {
        $entity = $webhook['entity']['uid'];
        $status = $webhook['payment']['status']['code'];

        // Get status config
        $prevStatus  = $order->getStatus();
        $statusName  = $this->orderUpdate->getStatusConfigName($status);
        $orderStatus = $this->orderUpdate->config->get($statusName);

        // Only process refunds child webhooks
        if (!in_array($status, [600, 601, 602, 603, 610]))
            return;

        $vendorOrder = $this->helper->getVendorOrderByUID($order, $entity);

        if (!$vendorOrder)
            return;

        if ($vendorOrder->getStatus() == $orderStatus)
            return $this->logger->log('debug', 'Mobbex: Child Order Already Refunded', $vendorOrder->getId());

        try {
            $this->refund->refund($vendorOrder);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Mobbex: Error refunding Order on Child', $order->getId());
        }

        $vendorOrder->setState($orderStatus)->setStatus($orderStatus);
        $vendorOrder->save();

        // Check if order now has closed status and change to the final status
        $this->_order->load($order->getId());
        $newStatus = $this->_order->getStatus();

        $this->logger->log('debug', 'Mobbex: Comparing Parent Order Status: ', [$prevStatus, $newStatus, $orderStatus]);

        if ($prevStatus != $newStatus && $newStatus != $orderStatus) {
            $this->logger->log('debug', "Mobbex: Updating Parent Order Status to $orderStatus");

            $order->setState($prevStatus)->setStatus($prevStatus);
            $order->save();

            $this->logger->log('debug', "Mobbex: Updated Parent Order Status to $orderStatus");
        }

        $order->addCommentToStatusHistory(sprintf(
            "Received Refund Webhook for Seller %s. Amount $ %s",
            $vendorOrder->getVendor()->getVendorId(),
            (string) $webhook['payment']['total']
        ));
        $order->save();
    }

    public function mobbexOrderPanelInfo($table, $info, $transaction, $childs)
    {
        $vendorOrder = $this->registry->registry('vendor_order');

        if (!$vendorOrder)
            return $table;

        // Get entity from vendor order and
        $vendor = $vendorOrder->getVendor();
        $entity = $vendor->getData('mbbx_uid');

        $newTable = [];

        foreach ($childs as $chd) {
            if ($chd['entity_uid'] != $entity)
                continue;

            $newTable = [
                'Transaction ID'     => $chd['payment_id'],
                'Total'              => "$ $chd[total]",
                'Source'             => "$chd[source_name], $chd[source_number]",
                'Source Installment' => "$chd[installment_count] cuota/s de $ $chd[installment_amount] (plan $chd[installment_name])",
                'Entity Name'        => "$chd[entity_name] (UID $chd[entity_uid])"
            ];
        }

        return $newTable;
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
