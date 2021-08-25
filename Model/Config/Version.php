<?php

namespace Mobbex\Marketplace\Model\Config;

use Magento\Framework\App\Config\Value;

class Version extends Value
{
    public function __construct(Magento\Framework\Module\ResourceInterface $moduleResource)
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