<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper\Html;

class Lcp
{
    private const PRODUCT_VIEW_IMAGE_PATTERN = '/<img\b(?=[^>]*(?:\bclass=["\'][^"\']*\bproduct-image-photo\b[^"\']*["\']|\balt=["\']main product photo["\']|\bitemprop=["\']image["\']))[^>]*>/i';
    private const LISTING_IMAGE_PATTERN = '/<img\b[^>]*\bclass=["\'][^"\']*\bproduct-image-photo\b[^"\']*["\'][^>]*>/i';

    public function optimizeProductImage(string $html, bool $optimizeListingImage = false): string
    {
        $isProductView = stripos($html, 'catalog-product-view') !== false;
        if (!$isProductView && !$optimizeListingImage) {
            return $html;
        }

        $pattern = $isProductView ? self::PRODUCT_VIEW_IMAGE_PATTERN : self::LISTING_IMAGE_PATTERN;
        if (!preg_match($pattern, $html)) {
            return $html;
        }

        return $this->preloadLikelyLcpImage($this->promoteLikelyLcpImage($html, $pattern), $pattern);
    }

    private function promoteLikelyLcpImage(string $html, string $pattern): string
    {
        $updated = (string)preg_replace_callback(
            $pattern,
            fn(array $matches): string => $this->promoteImageTag($matches[0]),
            $html,
            1
        );

        return $updated !== '' ? $updated : $html;
    }

    private function promoteImageTag(string $tag): string
    {
        if (stripos($tag, 'fetchpriority=') !== false) {
            $tag = (string)preg_replace('/\s+fetchpriority=["\'][^"\']*["\']/i', ' fetchpriority="high"', $tag, 1);
        } else {
            $tag = preg_replace('/<img\b/i', '<img fetchpriority="high"', $tag, 1) ?: $tag;
        }
        if (stripos($tag, 'decoding=') !== false) {
            $tag = (string)preg_replace('/\s+decoding=["\'][^"\']*["\']/i', ' decoding="sync"', $tag, 1);
        } else {
            $tag = preg_replace('/<img\b/i', '<img decoding="sync"', $tag, 1) ?: $tag;
        }

        return (string)preg_replace('/\s+loading=["\']lazy["\']/i', '', $tag);
    }

    private function preloadLikelyLcpImage(string $html, string $pattern): string
    {
        if (stripos($html, '</head>') === false) {
            return $html;
        }

        if (!preg_match($pattern, $html, $matches)) {
            return $html;
        }

        $tag = $matches[0];
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
            return $html;
        }

        $src = trim($srcMatch[1]);
        if ($src === '' || $this->hasImagePreload($html, $src)) {
            return $html;
        }

        $link = '<link rel="preload" as="image" href="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" fetchpriority="high"';
        if (preg_match('/\bsrcset=["\']([^"\']+)["\']/i', $tag, $srcsetMatch)) {
            $srcset = trim($srcsetMatch[1]);
            if ($srcset !== '') {
                $link .= ' imagesrcset="' . htmlspecialchars($srcset, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }
        if (preg_match('/\bsizes=["\']([^"\']+)["\']/i', $tag, $sizesMatch)) {
            $sizes = trim($sizesMatch[1]);
            if ($sizes !== '') {
                $link .= ' imagesizes="' . htmlspecialchars($sizes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }
        $link .= '>';

        return (string)preg_replace('/<\/head>/i', $link . '</head>', $html, 1);
    }

    private function hasImagePreload(string $html, string $src): bool
    {
        return (bool)preg_match(
            '/<link\b(?=[^>]*\brel=["\']preload["\'])(?=[^>]*\bas=["\']image["\'])[^>]*\bhref=["\']'
            . preg_quote($src, '/')
            . '["\'][^>]*>/i',
            $html
        );
    }

}
