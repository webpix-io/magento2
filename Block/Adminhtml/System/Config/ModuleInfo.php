<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Config\Block\System\Config\Form\Field\Heading;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ModuleInfo extends Heading
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $html = '<strong><a href="https://webpix.io/integrations/magento" target="_blank" rel="noopener">WebPix Optimizer for Magento 2</a></strong>';
        $html .= '<p>WebPix Optimizer connects your Magento storefront to WebPix CDN and automatically routes images, SVG files, CSS and JavaScript through optimized delivery endpoints. It helps reduce page weight, serve WebP or AVIF images, improve LCP and Core Web Vitals, and keep theme templates clean without manual URL changes.</p>';
        $html .= '<p>Use this section to enable optimization, add your WebPix access credentials, choose image quality and configure asset delivery for the storefront.</p>';

        return sprintf(
            '<div id="row_%s"><div id="%s" style="margin:0 0 18px;padding:14px 18px;background:#f8fafc;border-left:4px solid #007bdb;">%s</div></div>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $html
        );
    }
}
