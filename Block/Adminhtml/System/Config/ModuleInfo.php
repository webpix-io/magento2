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
        $html = '<style>' . $this->getStyles() . '</style>';
        $html .= '<div class="webpix-admin-hero">';
        $html .= '<div class="webpix-admin-hero__content">';
        $html .= $this->getLogoSvg();
        $html .= '<h1>WebPix Optimizer for Magento 2</h1>';
        $html .= '<p>Connect your Magento storefront to WebPix CDN and automatically route product images, CMS images, SVG files, CSS, JavaScript and Google Fonts through optimized delivery controls. Reduce page weight, serve WebP or AVIF images, improve LCP and Core Web Vitals, and keep theme templates clean without manual URL rewrites.</p>';
        $html .= '<a href="https://webpix.io/integrations/magento" target="_blank" rel="noopener">Open Magento integration guide</a>';
        $html .= '</div>';
        $html .= '<div class="webpix-admin-hero__status"><span>Status</span><strong data-webpix-status>Settings</strong></div>';
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

    private function getStyles(): string
    {
        return <<<'CSS'
#webpix_optimizer_general-head,
#webpix_optimizer_access-head,
#webpix_optimizer_image-head,
#webpix_optimizer_css-head,
#webpix_optimizer_js-head,
#webpix_optimizer_fonts-head {
    color: #1f1960;
    font-weight: 800;
}
.webpix-admin-info-row > td,
.webpix-admin-info-row > th {
    padding: 0 !important;
}
.webpix-admin-hero {
    margin: 0 0 22px;
    padding: 26px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 180px;
    gap: 24px;
    align-items: start;
    background: #fff;
    border: 1px solid #dbe7f3;
    border-radius: 12px;
    box-shadow: 0 14px 36px rgba(31,25,96,.08);
}
.webpix-admin-hero svg {
    width: 200px;
    height: 48px;
    display: block;
    margin-bottom: 18px;
}
.webpix-admin-hero h1 {
    margin: 0 0 10px;
    color: #1f1960;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 800;
}
.webpix-admin-hero p {
    max-width: 820px;
    margin: 0 0 14px;
    color: #344054;
    font-size: 15px;
    line-height: 1.65;
}
.webpix-admin-hero a {
    color: #0969da;
    font-weight: 700;
    text-decoration: none;
}
.webpix-admin-hero a:hover {
    text-decoration: underline;
}
.webpix-admin-hero__status {
    padding: 16px;
    border-radius: 10px;
    background: #f1f8fc;
    border: 1px solid #cbeafe;
}
.webpix-admin-hero__status span {
    display: block;
    margin-bottom: 5px;
    color: #667085;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .06em;
}
.webpix-admin-hero__status strong {
    color: #1f1960;
    font-size: 20px;
}
#webpix_optimizer_general,
#webpix_optimizer_access,
#webpix_optimizer_image,
#webpix_optimizer_css,
#webpix_optimizer_js,
#webpix_optimizer_fonts {
    border: 1px solid #dbe7f3;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 22px rgba(31,25,96,.045);
}
#webpix_optimizer_general .config,
#webpix_optimizer_access .config,
#webpix_optimizer_image .config,
#webpix_optimizer_css .config,
#webpix_optimizer_js .config,
#webpix_optimizer_fonts .config {
    background: #fff;
}
#webpix_optimizer_general .label,
#webpix_optimizer_access .label,
#webpix_optimizer_image .label,
#webpix_optimizer_css .label,
#webpix_optimizer_js .label,
#webpix_optimizer_fonts .label {
    color: #1f1960;
    font-weight: 700;
}

CSS;
    }

}
