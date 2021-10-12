<?php

namespace Mobbex\Marketplace\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Add commission and finnancial charge columns in vendor order table. 
     * 
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade($setup, $context)
    {
        $setup->startSetup();

        if ($context->getVersion() < '1.2.0') {
            $vendorOrderTable = $setup->getTable('ves_vendor_sales_order');

            $columns = [
                'mbbx_final_commission' => [
                    'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length'   => '10,4',
                    'default'  => 0,
                    'nullable' => true,
                    'comment'  => __('Final Commission')
                ],
                'mbbx_finnancial_charge' => [
                    'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length'   => '10,4',
                    'default'  => 0,
                    'nullable' => true,
                    'comment'  => __('Finnacial Charge')
                ],
            ];

            foreach ($columns as $id => $column)
                $setup->getConnection()->addColumn($vendorOrderTable, $id, $column);
        }

        $setup->endSetup();
    }
}