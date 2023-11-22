<?php

namespace Mobbex\Marketplace\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class UpgradeData implements UpgradeDataInterface
{
    /** @var \Magento\Eav\Setup\EavSetupFactory */
    public $eavSetupFactory;
    
    /** @var \Magento\Eav\Setup\EavSetup */
    public $eavSetup;

    /** @var string */
    public $vendorEntity;

    /** @var \Magento\Framework\Module\Manager */
    private $moduleManager;


    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        // Load module manager and checks that vnecoms is enable
        $this->moduleManager = $moduleManager;
        $this->checkDependencies();

        // Continue adding props
        $this->eavSetupFactory = $eavSetupFactory;
        $this->vendorEntity    = \Vnecoms\Vendors\Model\Vendor::ENTITY;
    }

    /**
     * Add CUIT field to vendor registration and edit form. 
     * 
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade($setup, $context)
    {
        $this->eavSetup = $this->eavSetupFactory->create(compact('setup'));
        $setup->startSetup();

        $attributes = [
            'mbbx_cuit' => [
                'label'                     => 'Mobbex CUIT (Deprecated)',
                'type'                      => 'varchar',
                'input'                     => 'text',
                'position'                  => 140,
                'sort_order'                => 140,
                'visible'                   => true,
                'required'                  => false,
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

        foreach ($attributes as $name => $attributeData) {
            $this->eavSetup->addAttribute($this->vendorEntity, $name, $attributeData);

            if ($attributeData['used_in_profile_form'])
                $this->addAttributeFieldset($setup, $name, 1);
    
            // Show attribute field on front-end
            if ($attributeData['used_in_registration_form'])
                $this->addAttributeFieldset($setup, $name, 4);
        }

        $setup->endSetup();
    }

    /**
     * Try to add an attribute to a fieldset.
     * 
     * @param ModuleDataSetupInterface $setup
     * @param string $name The attribute name.
     * @param int $fieldsetId The id of the fieldset to add in.
     * @param int $sortOrder Optional. The order to show it in the view.
     * 
     * @return bool|null Insert result. Null if exists.
     */
    public function addAttributeFieldset($setup, $name, $fieldsetId, $sortOrder = 15)
    {
        $exists = $setup->getConnection()->fetchRow(sprintf(
            "SELECT * FROM %s WHERE attribute_id = '%s' AND fieldset_id = %d;",
            $setup->getTable('ves_vendor_fieldset_attr'),
            $this->eavSetup->getAttributeId($this->vendorEntity, $name),
            $fieldsetId
        ));

        if ($exists)
            return;

        return $setup->getConnection()->insertForce(
            $setup->getTable('ves_vendor_fieldset_attr'),
            [
                'fieldset_id'  => $fieldsetId,
                'attribute_id' => $this->eavSetup->getAttributeId($this->vendorEntity, $name),
                'sort_order'   => $sortOrder
            ]
        );
    }

    /**
     * Checks that Vnecoms module is installed
     */
    public function checkDependencies()
    {
        // Check if Vnecoms Module is installed to continue
        if (!$this->moduleManager->isEnabled('Vnecoms_Core')) {
            throw new \Exception('Error: Vnecoms module is not installed. Please install Vnecoms module before installing Mobbex Marketplace.', 500);
        }
    }
}