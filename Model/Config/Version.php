<?php

namespace Mobbex\Marketplace\Model\Config;

use Magento\Framework\Module\ResourceInterface;

class Version extends \Magento\Framework\App\Config\Value
{
    /** @var ResourceInterface */
    protected $moduleResource;

    public function __construct(ResourceInterface $moduleResource)
    {
        $this->moduleResource = $moduleResource;
    }

    /**
     * Display module version.
     */
    public function afterLoad()
    {
        $version = $this->moduleResource->getDbVersion('Mobbex_Marketplace');
        $this->setValue($version);
    }
}