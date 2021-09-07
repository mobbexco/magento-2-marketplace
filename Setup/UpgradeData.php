<?php

namespace Mobbex\Marketplace\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    public function __construct(\Magento\Eav\Setup\EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Add CUIT field to vendor registration and edit form. 
     * 
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade($setup, $context)
    {
        $setup->startSetup();

        if ($context->getVersion() < '1.1.0') {
            // Init eav setup and get vendor entity to add attributes
            $eavSetup     = $this->eavSetupFactory->create(['setup' => $setup]);
            $vendorEntity = \Vnecoms\Vendors\Model\Vendor::ENTITY;

            $attributes = [
                'mbbx_cuit' => [
                    'label'                     => 'Mobbex CUIT',
                    'type'                      => 'varchar',
                    'input'                     => 'text',
                    'position'                  => 140,
                    'sort_order'                => 140,
                    'visible'                   => true,
                    'required'                  => true,
                    'validate_rules'            => 'a:2:{s:15:"max_text_length";i:255;s:15:"min_text_length";i:1;}',
                    'default'                   => '',
                    'user_defined'              => 1,
                    'system'                    => 0,
                    'used_in_profile_form'      => 1,
                    'used_in_registration_form' => 1,
                    'visible_in_customer_form'  => 0,
                ],
                'mbbx_hold' => [
                    'label'                     => __('Hold Mobbex Payments'),
                    'type'                      => 'int',
                    'input'                     => 'boolean',
                    'position'                  => 150,
                    'sort_order'                => 150,
                    'visible'                   => true,
                    'required'                  => false,
                    'default'                   => 0,
                    'user_defined'              => 1,
                    'system'                    => 0,
                    'used_in_profile_form'      => 1,
                    'used_in_registration_form' => 0,
                    'visible_in_customer_form'  => 0,
                    'hide_from_vendor_panel'    => 1,
                ]
            ];

            foreach ($attributes as $id => $attribute) {
                $eavSetup->addAttribute($vendorEntity, $id, $attribute);

                if ($attribute['used_in_profile_form']) {
                    // Show attribute field on back-end
                    $setup->getConnection()->insertForce(
                        $setup->getTable('ves_vendor_fieldset_attr'),
                        [
                            'fieldset_id'  => 1, 
                            'attribute_id' => $eavSetup->getAttributeId($vendorEntity, $id),
                            'sort_order'   => 15
                        ]
                    );
                }

                if ($attribute['used_in_registration_form']) {
                    // Show attribute field on front-end
                    $setup->getConnection()->insertForce(
                        $setup->getTable('ves_vendor_fieldset_attr'),
                        [
                            'fieldset_id'  => 4, 
                            'attribute_id' => $eavSetup->getAttributeId($vendorEntity, $id),
                            'sort_order'   => 15
                        ]
                    );
                }
            }
        }

        $setup->endSetup();
    }
}
