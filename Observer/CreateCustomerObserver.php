<?php

namespace Mobbex\Marketplace\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CreateCustomerObserver implements ObserverInterface
{
    public function __construct(\Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory)
    {
        $this->customFieldFactory = $customFieldFactory;
    }

    public function execute(Observer $observer)
    {
        $request = $observer->getAccountController()->getRequest();

        if (!$request->getParam('is_seller', false) || !$request->getParam('mbbx_cuit'))
            return;

        // Save cuit as custom field
        $customFieldCommon = $this->customFieldFactory->create();
        $customFieldCommon->saveCustomField($observer->getCustomer()->getId(), 'customer', 'cuit', $request->getParam('mbbx_cuit'));
    }
}