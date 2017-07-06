<?php
 
namespace Sailthru\MageSail\Model\Config\Source;

use \Magento\Framework\Option\ArrayInterface;
use \Sailthru\MageSail\Helper\ClientManager;
use \Sailthru\MageSail\Helper\Settings as SailthruSettings;

class SailthruTemplates extends AbstractSource
{

    /** @inheritdoc */
    protected function getDisplayData()
    {
        $data = $this->clientManager->getClient()->getTemplates();
        $templates = $data["templates"];
        $tpl_options = [
            ['value'=> 0, 'label'=>' ']
        ];
        foreach ($templates as $tpl) {
                $tpl_options[] = [
                    'value' => $tpl['name'],
                    'label' => __($tpl['name'])
                ];
        }
        return $tpl_options;
    }
}
