<?php

namespace Mobbex\Marketplace\Helper;

class Refund
{
    /** @var \Vnecoms\VendorsSales\Model\Order\Invoice */
    public $invoice;

    /** @var \Vnecoms\VendorsSales\Model\Order\CreditmemoFactory */
    public $creditmemoFactory;

    /** @var \Vnecoms\VendorsSales\Model\Service\CreditmemoService */
    public $creditmemoService;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    public function __construct(
        \Vnecoms\VendorsSales\Model\Order\Invoice $invoice,
        \Vnecoms\VendorsSales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Vnecoms\VendorsSales\Model\Service\CreditmemoService $creditmemoService,
        \Mobbex\Webpay\Helper\Config $config
    ) {
        $this->invoice = $invoice;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->config = $config;
    }

    public function refund($order)
    {
        if ($order->canCancel())
            return $order->registerCancellation('', true, true);

        if (!$order->canCreditMemo() || $order->getCreditmemoCollection()->count())
            return;

        if (!$this->config->get('creditmemo_on_refund'))
            return;

        $creditmemo = $this->creditmemoFactory->createByVendorOrder($order);

        // Back to stock all the items
        foreach ($creditmemo->getAllItems() as $item)
            $item->setBackToStock((bool) $this->config->get('memo_stock'));

        $this->creditmemoService->refund($creditmemo);
    }
}
