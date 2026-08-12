<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Block;

use Magento\Framework\View\Element\Template;
use Webpix\Optimizer\Helper\Data;

class Preconnect extends Template
{
    private Data $helper;

    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    public function getHost(): string
    {
        if (!$this->helper->isConfigured()) {
            return '';
        }

        return $this->helper->getWebpixHost();
    }
}
