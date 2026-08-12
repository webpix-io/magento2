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
        $html = '<div class="webpix-admin-hero">';
        $html .= '<div class="webpix-admin-hero__content">';
        $html .= $this->getLogoSvg();
        $html .= '<h1>WebPix Optimizer for Magento 2</h1>';
        $html .= '<p>Connect your Magento storefront to WebPix CDN and automatically route product images, CMS images, SVG files, CSS, JavaScript and Google Fonts through optimized delivery controls. Reduce page weight, serve WebP or AVIF images, improve LCP and Core Web Vitals, and keep theme templates clean without manual URL rewrites.</p>';
        $html .= '<a href="https://webpix.io/integrations/magento" target="_blank" rel="noopener">Open Magento integration guide</a>';
        $html .= '</div>';
        $html .= '</div>';

        return sprintf(
            '<div id="row_%s" class="webpix-admin-info-row"><div id="%s">%s</div></div>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $html
        );
    }

    private function getLogoSvg(): string
    {
        return '<svg viewBox="0 0 200 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="WebPix"><path fill="#fff" fill-opacity=".01" d="M0 0h48v48H0z"/><path d="M20 4H4v16h16zm0 24H4v16h16zM44 4H28v16h16z" fill="#62bae9" stroke="#1f1960" stroke-width="4" stroke-linejoin="round"/><path d="M30.002 28v16M42 28v16" stroke="#1f1960" stroke-width="4" stroke-linecap="round"/><text x="56" y="38" font-family="Plus Jakarta Sans, Arial, sans-serif" font-weight="600" font-size="40" fill="#1b145d" letter-spacing="-1">WebPix</text></svg>';
    }

}
