<?php

namespace Mobbex\Marketplace\Block;

class Totals extends \Magento\Framework\View\Element\Template
{
    /**
     * Get current order.
     *
     * @return \Vnecoms\VendorsSales\Model\Order
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals()
    {
        $order = $this->getSource();

        $finnancialCharge = $order->getMbbxFinnancialCharge() ?: 0;
        $finalCommission  = $order->getMbbxFinalCommission();

        if ($finnancialCharge != 0) {
            $this->getParentBlock()->addTotalBefore(
                new \Magento\Framework\DataObject([
                    'code' => 'mbbx_finnancial_charge',
                    'strong' => true,
                    'value' => $finnancialCharge,
                    'label' => __($finnancialCharge > 0 ? 'Finnancial Charge' : 'Descuento financiero'),
                ]), 
                'grand_total'
            );
        }

        if ($finalCommission != 0) {
            $this->getParentBlock()->addTotalBefore(
                new \Magento\Framework\DataObject([
                    'code' => 'mbbx_final_commission',
                    'strong' => true,
                    'value' => $finalCommission,
                    'label' => __('Final Commission'),
                ]), 
                'grand_total'
            );
        }

        return $this;
    }
}