<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper\Html;

use Webpix\Optimizer\Helper\Css;
use Webpix\Optimizer\Helper\Data;
use Webpix\Optimizer\Helper\Js;

class Assets
{
    public function __construct(
        private readonly Data $dataHelper,
        private readonly Css $cssHelper,
        private readonly Js $jsHelper
    ) {
    }

    public function replaceCssLinks(string $html): string
    {
        if (!$this->cssHelper->isEnabled()) {
            return $html;
        }

        return (string)preg_replace_callback(
            '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])[^>]*\bhref=["\']([^"\' ]+)["\'][^>]*>/i',
            function (array $matches): string {
                $originalTag = $matches[0];
                $originalUrl = trim($matches[1]);
                if ($originalUrl === '' || $this->dataHelper->isWebpixUrl($originalUrl)) {
                    return $originalTag;
                }

                return $this->stripIntegrityAttributes(str_replace($originalUrl, $this->cssHelper->replaceCssUrl($originalUrl), $originalTag));
            },
            $html
        );
    }

    public function replaceJsLinks(string $html): string
    {
        if (!$this->jsHelper->isEnabled()) {
            return $html;
        }

        return (string)preg_replace_callback(
            '/<script[^>]+src=["\']([^"\']+)["\'][^>]*><\/script>/i',
            function (array $matches): string {
                $originalTag = $matches[0];
                $originalUrl = trim($matches[1]);
                if ($originalUrl === '' || $this->dataHelper->isWebpixUrl($originalUrl)) {
                    return $originalTag;
                }

                $updatedTag = $this->stripIntegrityAttributes(str_replace($originalUrl, $this->jsHelper->replaceJsUrl($originalUrl), $originalTag));
                if (preg_match('#(?:^|/)require(?:\.min)?\.js(?:\?.*)?$#i', $originalUrl)) {
                    return $updatedTag . $this->jsHelper->getRuntimeLoaderPatch();
                }

                return $updatedTag;
            },
            $html
        );
    }

    private function stripIntegrityAttributes(string $tag): string
    {
        if (stripos($tag, 'integrity=') === false && stripos($tag, 'crossorigin=') === false) {
            return $tag;
        }

        $tag = (string)preg_replace('/\s+integrity=["\'][^"\']*["\']/i', '', $tag);
        return (string)preg_replace('/\s+crossorigin=["\'][^"\']*["\']/i', '', $tag);
    }
}
