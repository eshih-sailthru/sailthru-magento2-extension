<?php

namespace Sailthru\MageSail\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\Helper\AbstractHelper as MageAbstractHelper;

class AbstractHelper extends MageAbstractHelper
{
    /** @var StoreManager  */
    protected $storeManager;

    public function __construct(Context $context, StoreManager $storeManager) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
    }

    public function getSettingsVal($val)
    {
        $scopeType = ScopeInterface::SCOPE_STORES;
        $scopeCode = null;

        $storeCode = $this->_request->getParam('store');
        $websiteCode = $this->_request->getParam('website');
        if ($storeCode) {
            $scopeType = ScopeInterface::SCOPE_STORE;
            $scopeCode = $storeCode;
        } elseif ($websiteCode) {
            $scopeType = ScopeInterface::SCOPE_WEBSITE;
            $scopeCode = $websiteCode;
        } else {
            $scopeCode = $this->storeManager->getStore()->getCode();
        }
        return $this->scopeConfig->getValue($val, $scopeType, $scopeCode);
    }
}