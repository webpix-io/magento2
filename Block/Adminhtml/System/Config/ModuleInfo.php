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

        $html = '<strong>WebPix Optimizer for Magento 2</strong>';
        $html .= '<p>WebPix Optimizer connects your Magento storefront to WebPix CDN and automatically routes images, SVG files, CSS and JavaScript through optimized delivery endpoints. It helps reduce page weight, serve WebP or AVIF images, improve LCP and Core Web Vitals, and keep theme templates clean without manual URL changes.</p>';
        $html .= '<p>Use this section to enable optimization, add your WebPix access credentials, choose image quality and configure asset delivery for the storefront.</p>';
        $html .= '<p><strong>Service:</strong> <a href="https://webpix.io" target="_blank" rel="noopener">https://webpix.io</a></p>';
        $html .= '<p><strong>Magento docs:</strong> <a href="https://webpix.io/integrations/magento" target="_blank" rel="noopener">https://webpix.io/integrations/magento</a></p>';
        $html .= '<p><strong>Version:</strong> ' . htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

        return sprintf(
            '<div id="row_%s"><div id="%s" style="margin:0 0 18px;padding:14px 18px;background:#f8fafc;border-left:4px solid #007bdb;">%s</div></div>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $html
        );
    }
}
