<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Config\Block\System\Config\Form\Field\Heading;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\PackageInfoFactory;

class ModuleInfo extends Heading
{
    public function __construct(
        Context $context,
        private readonly PackageInfoFactory $packageInfoFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $packageInfo = $this->packageInfoFactory->create();
        $version = (string)$packageInfo->getVersion('Webpix_Optimizer');
        if ($version === '') {
            $version = '1.0.0';
        }

        $html = '<strong>WebPix Optimizer</strong>';
        $html .= '<p>Connect Magento 2 storefront assets to WebPix CDN for image compression, WebP/AVIF delivery, SVG optimization, CSS/JS delivery and Core Web Vitals improvements.</p>';
        $html .= '<p><strong>Service:</strong> <a href="https://webpix.io/" target="_blank" rel="noopener">https://webpix.io/</a></p>';
        $html .= '<p><strong>Version:</strong> ' . htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

        return sprintf(
            '<div class="system-fieldset-sub-head" id="row_%s"><span id="%s">%s</span></div>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $html
        );
    }
}
