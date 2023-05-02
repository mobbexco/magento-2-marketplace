<?php

namespace Mobbex\Marketplace\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
        )
    {
        // Init eav setup and get vendor entity to add attributes
        $this->eavSetup        = $eavSetupFactory->create(['setup' => $setup]);
        $this->$vendorEntity   = \Vnecoms\Vendors\Model\Vendor::ENTITY;
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
            // Establish attributes
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
                ],
                'mbbx_uid'=> [
                    'label'                     => 'Mobbex Entity UID',
                    'type'                      => 'varchar',
                    'input'                     => 'text',
                    'position'                  => 160,
                    'sort_order'                => 160,
                    'visible'                   => true,
                    'required'                  => false,
                    'validate_rules'            => 'a:2:{s:15:"max_text_length";i:255;s:15:"min_text_length";i:1;}',
                    'default'                   => '',
                    'user_defined'              => 1,
                    'system'                    => 0,
                    'used_in_profile_form'      => 1,
                    'used_in_registration_form' => 1,
                    'visible_in_customer_form'  => 0,
                ]
            ];

            foreach ($attributes as $id => $attribute)
                $this->setAttribute($setup, $attribute, $id);
        }

        // Checks if mbbx_uid attribute exists and set it if not
        if (!$this->eavSetup->getAttributeId($vendorEntity, 'mbbx_uid')) {
            // Establish attribute
            $attribute['mbbx_uid'] = [
                'label'                     => 'Mobbex Entity UID',
                'type'                      => 'varchar',
                'input'                     => 'text',
                'position'                  => 160,
                'sort_order'                => 160,
                'visible'                   => true,
                'required'                  => false,
                'validate_rules'            => 'a:2:{s:15:"max_text_length";i:255;s:15:"min_text_length";i:1;}',
                'default'                   => '',
                'user_defined'              => 1,
                'system'                    => 0,
                'used_in_profile_form'      => 1,
                'used_in_registration_form' => 1,
                'visible_in_customer_form'  => 0,
            ];

            $this->setAttribute($setup, $attribute, 'mbbx_uid');
        }
        $setup->endSetup();
    }

    /**
     * Set attributes and add them to vnecoms seller information form
     * 
     * @param ModuleDataSetupInterface $setup
     * @param string $attribute
     * @param array  $setup
     * 
     */
    public function setAttribute($setup, $attribute, $id)
    {
        $this->eavSetup->addAttribute($this->vendorEntity, $id, $attribute);

        if ($attribute['used_in_profile_form'])
            // Show attribute field on back-end
            $setup->getConnection()->insertForce(
                $setup->getTable('ves_vendor_fieldset_attr'),
                [
                    'fieldset_id'  => 1,
                    'attribute_id' => $this->eavSetup->getAttributeId($this->vendorEntity, $id),
                    'sort_order'   => 15
                ]
            );

        if ($attribute['used_in_registration_form'])
            // Show attribute field on front-end
            $setup->getConnection()->insertForce(
                $setup->getTable('ves_vendor_fieldset_attr'),
                [
                    'fieldset_id'  => 4,
                    'attribute_id' => $this->eavSetup->getAttributeId($this->vendorEntity, $id),
                    'sort_order'   => 15
                ]
            );
    }
}