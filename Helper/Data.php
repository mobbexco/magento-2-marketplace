<?php

namespace Mobbex\Marketplace\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Vnecoms\Vendors\Model\VendorFactory */
    public $vendorFactory;

    /** @var \Magento\Quote\Model\QuoteRepository */
    public $quoteRepository;

    /** @var \Vnecoms\VendorsSales\Model\OrderFactory */
    public $orderFactory;

    /** @var \Magento\Framework\Event\ManagerInterface */
    public $eventManager;

    /** @var \Vnecoms\VendorsSales\Model\ResourceModel\Order\CollectionFactory */
    public $vendorOrderCF;

    public function __construct(
        \Vnecoms\Vendors\Model\VendorFactory $vendorFactory,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Vnecoms\VendorsSales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Vnecoms\VendorsSales\Model\ResourceModel\Order\CollectionFactory $vendorOrderCF
    ) {
        $this->vendorFactory   = $vendorFactory;
        $this->quoteRepository = $quoteRepository;
        $this->orderFactory    = $orderFactory;
        $this->eventManager    = $eventManager;
        $this->vendorOrderCF   = $vendorOrderCF;
    }

    /**
     * Get vendor from an item.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return Vnecoms\Vendors\Model\Vendor
     */
    public function getVendor($item)
    {
        return $this->vendorFactory->create()->load($item->getProduct()->getVendorId());
    }

    /**
     * Get vendor order from an item.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return Vnecoms\VendorsSales\Model\Order
     */
    public function getVendorOrder($item)
    {
        return $this->orderFactory->create()->load($item->getVendorOrderId());
    }

    /**
     * Get item commission.
     * 
     * @param Magento\Sales\Model\Order\Item $item
     * 
     * @return int
     */
    public function getCommission($item)
    {
        // Support configurable products calculation
        $product = $item->getProduct();
        $product->getTypeId() == 'configurable' ? $product->setPrice($item->getPrice())->setPriceCalculation(false) : null;

        // Set the vendor order as invoice so Vnecoms can get store id
        $item->setInvoice($this->getVendorOrder($item));

        $commission = new \Magento\Framework\DataObject(['fee' => 0]);

        $this->eventManager->dispatch(
            'ves_vendorscredit_calculate_commission',
            [
                'commission'    => $commission,
                'invoice_item'  => $item,
                'product'       => $product,
                'vendor'        => $this->getVendor($item),
            ]
        );

        // Set invoice to null and return fee
        $item->setInvoice(null);
        return $commission->getFee();
    }

    /**
     * Get vendor orders from magento parent order.
     * 
     * @param Order|int|string $order The instance or him id.
     * 
     * @return \Vnecoms\VendorsSales\Model\Order[]
     */
    public function getVendorOrders($order)
    {
        return $this->vendorOrderCF->create()->addFieldToFilter(
            'order_id',
            is_object($order) ? $order->getId() : $order
        );
    }

    /**
     * Get the vendor order by his entity uid.
     * 
     * @param Order|int|string $order The instance or him id.
     * @param string $uid
     * 
     * @return \Vnecoms\VendorsSales\Model\Order
     */
    public function getVendorOrderByUID($order, $uid)
    {
        foreach ($this->getVendorOrders($order) as $vendorOrder) {
            $vendor = $vendorOrder->getVendor();

            if ($vendor->getData('mbbx_uid') == $uid)
                return $vendorOrder;
        }
    }

    /**
     * Retrieve vendor order commission calculated by Vnecoms.
     * 
     * @param \Vnecoms\VendorsSales\Model\Order $order
     * 
     * @return int
     */
    public function getVendorOrderCommission($order)
    {
        $amount = 0;

        foreach ($order->getAllVisibleItems() as $item)
            $amount += $this->getCommission($item);

        return $amount;
    }

    /**
     * Create temporal vendor orders from quote.
     * 
     * @param string|number $quoteId
     * 
     * @return array
     */
    public function createVendorOrders($quoteId)
    {
        $vendorOrders = $vendorOrderItems = [];
        $currentTimestamp = (new \DateTime())->getTimestamp();

        $quote = $this->quoteRepository->get($quoteId);

        // Group items by vendor
        foreach ($quote->getAllItems() as $item) {
            $vendorId = $item->getProduct()->getVendorId();
            
            if ($vendorId)
                $vendorOrderItems[$vendorId][] = $item;
        }

        foreach ($vendorOrderItems as $vendorId => $items) {
            $vendorOrder = $this->orderFactory->create();

            // Set default order values
            $orderData = [
                'vendor_id'                                    => $vendorId,
                'order_id'                                     => $quote->getReservedOrderId(),
                'state'                                        => \Magento\Sales\Model\Order::STATE_NEW,
                'status'                                       => \Magento\Sales\Model\Order::STATE_NEW,
                'store_id'                                     => $quote->getStoreId(),
                'subtotal'                                     => 0,
                'weight'                                       => 0,
                'grand_total'                                  => 0,
                'created_at'                                   => $currentTimestamp,
                'updated_at'                                   => $currentTimestamp,
                'tax_amount'                                   => 0,
                'base_subtotal'                                => 0,
                'base_tax_amount'                              => 0,
                'discount_amount'                              => 0,
                'shipping_amount'                              => 0,
                'shipping_incl_tax'                            => 0,
                'subtotal_incl_tax'                            => 0,
                'base_subtotal_incl_tax'                       => 0,
                'shipping_method'                              => '',
                'base_discount_amount'                         => 0,
                'base_grand_total'                             => 0,
                'base_shipping_amount'                         => 0,
                'shipping_tax_amount'                          => 0,
                'discount_tax_compensation_amount'             => 0,
                'base_discount_tax_compensation_amount'        => 0,
                'shipping_discount_tax_compensation_amount'    => 0,
                'base_shipping_discount_tax_compensation_amnt' => 0,
                'discount_tax_compensation_invoiced'           => 0,
                'base_discount_tax_compensation_invoiced'      => 0,
                'discount_tax_compensation_refunded'           => 0,
                'base_discount_tax_compensation_refunded'      => 0,
                'base_shipping_tax_amount'                     => 0,
                'base_shipping_incl_tax'                       => 0,
                'total_due'                                    => 0,
                'base_total_due'                               => 0,
            ];

            $itemsQty = 0;

            foreach ($items as $item) {
                if ($item->getParentItemId()) {
                    // Nothing... Skip parent items
                } else if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                    foreach ($item->getChildrenItems() as $child) {
                        $orderData['subtotal']                                     += $child->getData('row_total');
                        $orderData['base_subtotal']                                += $child->getData('base_row_total');
                        $orderData['weight']                                       += $child->getData('row_weight');
                        $orderData['tax_amount']                                   += $child->getData('tax_amount');
                        $orderData['base_tax_amount']                              += $child->getData('base_tax_amount');
                        $orderData['discount_amount']                              += $child->getData('discount_amount');
                        $orderData['base_discount_amount']                         += $child->getData('base_discount_amount');
                        $orderData['subtotal_incl_tax']                            += $child->getData('row_total_incl_tax');
                        $orderData['base_subtotal_incl_tax']                       += $child->getData('base_row_total_incl_tax');
                        $orderData['discount_tax_compensation_amount']             += $child->getData('discount_tax_compensation_amount');
                        $orderData['base_discount_tax_compensation_amount']        += $child->getData('base_discount_tax_compensation_amount');
                        $orderData['shipping_discount_tax_compensation_amount']    += $child->getData('shipping_discount_tax_compensation_amount');
                        $orderData['base_shipping_discount_tax_compensation_amnt'] += $child->getData('base_shipping_discount_tax_compensation_amnt');
                        $orderData['discount_tax_compensation_invoiced']           += $child->getData('discount_tax_compensation_invoiced');
                        $orderData['base_discount_tax_compensation_invoiced']      += $child->getData('base_discount_tax_compensation_invoiced');
                        $orderData['discount_tax_compensation_refunded']           += $child->getData('discount_tax_compensation_refunded');
                        $orderData['base_discount_tax_compensation_refunded']      += $child->getData('base_discount_tax_compensation_refunded');

                        $itemsQty += $child->getQtyOrdered();
                    }
                } else if (!$item->getHasChildren() || !$item->isChildrenCalculated()) {
                    $orderData['subtotal']                                     += $item->getData('row_total');
                    $orderData['base_subtotal']                                += $item->getData('base_row_total');
                    $orderData['weight']                                       += $item->getData('row_weight');
                    $orderData['tax_amount']                                   += $item->getData('tax_amount');
                    $orderData['base_tax_amount']                              += $item->getData('base_tax_amount');
                    $orderData['discount_amount']                              += $item->getData('discount_amount');
                    $orderData['base_discount_amount']                         += $item->getData('base_discount_amount');
                    $orderData['subtotal_incl_tax']                            += $item->getData('row_total_incl_tax');
                    $orderData['base_subtotal_incl_tax']                       += $item->getData('base_row_total_incl_tax');
                    $orderData['discount_tax_compensation_amount']             += $item->getData('discount_tax_compensation_amount');
                    $orderData['base_discount_tax_compensation_amount']        += $item->getData('base_discount_tax_compensation_amount');
                    $orderData['shipping_discount_tax_compensation_amount']    += $item->getData('shipping_discount_tax_compensation_amount');
                    $orderData['base_shipping_discount_tax_compensation_amnt'] += $item->getData('base_shipping_discount_tax_compensation_amnt');
                    $orderData['discount_tax_compensation_invoiced']           += $item->getData('discount_tax_compensation_invoiced');
                    $orderData['base_discount_tax_compensation_invoiced']      += $item->getData('base_discount_tax_compensation_invoiced');
                    $orderData['discount_tax_compensation_refunded']           += $item->getData('discount_tax_compensation_refunded');
                    $orderData['base_discount_tax_compensation_refunded']      += $item->getData('base_discount_tax_compensation_refunded');

                    $itemsQty += $item->getQtyOrdered();
                }
            }

            $orderData['total_qty_ordered'] = $itemsQty;

            $orderData['total_due'] = $orderData['grand_total'] = (
                $orderData['subtotal']
                + $orderData['shipping_amount']
                + $orderData['tax_amount']
                - abs($orderData['discount_amount'])
                + $orderData['discount_tax_compensation_amount']
            );

            $orderData['base_total_due'] = $orderData['base_grand_total'] = (
                $orderData['base_subtotal']
                + $orderData['base_shipping_amount']
                + $orderData['base_tax_amount']
                - abs($orderData['base_discount_amount'])
                + $orderData['base_discount_tax_compensation_amount']
            );

            $vendorOrder->setData($orderData)->setItems($items);
            $vendorOrders[$vendorId] = $vendorOrder;
        }

        return $vendorOrders;
    }
}