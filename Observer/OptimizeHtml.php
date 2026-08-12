<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Observer;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Webpix\Optimizer\Helper\Css;
use Webpix\Optimizer\Helper\Data;
use Webpix\Optimizer\Helper\Html\Assets;
use Webpix\Optimizer\Helper\Html\Fonts;
use Webpix\Optimizer\Helper\Html\Images;
use Webpix\Optimizer\Helper\Html\Lcp;
use Webpix\Optimizer\Helper\Js;

class OptimizeHtml implements ObserverInterface
{
    public function __construct(
        private readonly Data $dataHelper,
        private readonly Css $cssHelper,
        private readonly Js $jsHelper,
        private readonly Assets $assetsRewriter,
        private readonly Fonts $fontsRewriter,
        private readonly Images $imagesRewriter,
        private readonly Lcp $lcpRewriter
    ) {
    }

    public function execute(Observer $observer): void
    {
        $fontsEnabled = $this->dataHelper->isGoogleFontsOptimizationEnabled();
        $configured = $this->dataHelper->isConfigured();
        if (!$configured && !$fontsEnabled) {
            return;
        }

        $response = $observer->getEvent()->getResponse();
        if (!$response instanceof HttpResponse || !$this->isHtmlResponse($response)) {
            return;
        }

        $html = (string)$response->getBody();
        if ($html === '') {
            return;
        }

        $jsEnabled = $configured && $this->jsHelper->isEnabled();
        $cssEnabled = $configured && $this->cssHelper->isEnabled();
        $imagesEnabled = $this->imagesRewriter->isEnabled();

        if (!$jsEnabled && !$cssEnabled && !$imagesEnabled && !$fontsEnabled) {
            return;
        }

        $updated = $html;

        if ($fontsEnabled) {
            $updated = $this->fontsRewriter->normalizeGoogleFontsUrls($updated);
        }

        if ($jsEnabled && stripos($updated, '<script') !== false) {
            $updated = $this->assetsRewriter->replaceJsLinks($updated);
            if (strpos($updated, 'baseUrl') !== false && strpos($updated, '/static/') !== false) {
                $updated = $this->jsHelper->rewriteRequireBaseUrls($updated);
            }
        }

        if ($cssEnabled && stripos($updated, '<link') !== false && stripos($updated, 'stylesheet') !== false) {
            $updated = $this->assetsRewriter->replaceCssLinks($updated);
        }

        if ($imagesEnabled) {
            $updated = $this->imagesRewriter->replace($updated);
        }

        if ($configured && $this->dataHelper->isProductEnabled()) {
            $updated = $this->lcpRewriter->optimizeProductImage(
                $updated,
                $this->dataHelper->isListingLcpOptimizationEnabled()
            );
        }

        if ($updated !== $html) {
            $response->setBody($updated);
        }
    }

    private function isHtmlResponse(HttpResponse $response): bool
    {
        $header = $response->getHeader('Content-Type');
        $contentType = $header ? (string)$header->getFieldValue() : '';

        return $contentType === '' || stripos($contentType, 'text/html') !== false;
    }
}
