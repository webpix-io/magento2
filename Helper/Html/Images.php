<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper\Html;

use Webpix\Optimizer\Helper\Data;
use Webpix\Optimizer\Helper\Image;

class Images
{
    private const RESPONSIVE_WIDTHS = [320, 420, 560, 700, 960, 1200, 1440];
    private const PRODUCT_GALLERY_SIZES = '(max-width: 767px) 100vw, 364px';
    private const CMS_CONTENT_SIZES = '(max-width: 767px) 100vw, 668px';
    private const MIN_RESPONSIVE_WIDTH = 500;
    private const MIN_RESPONSIVE_HEIGHT = 300;

    private array $shouldRewriteCache = [];
    private array $rewriteCache = [];

    public function __construct(
        private readonly Data $dataHelper,
        private readonly Image $imageHelper
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->dataHelper->isConfigured()
            && (
                $this->dataHelper->isProductEnabled()
                || $this->dataHelper->isCmsEnabled()
                || $this->dataHelper->isSvgEnabled()
            );
    }

    public function replace(string $html): string
    {
        $this->shouldRewriteCache = [];
        $this->rewriteCache = [];

        $updated = $html;
        if (stripos($updated, '<img') !== false && stripos($updated, 'src=') !== false) {
            $updated = $this->replaceImageUrls($updated);
        }
        if (stripos($updated, 'srcset=') !== false) {
            $updated = $this->replaceSrcsetUrls($updated);
        }
        if (
            strpos($updated, 'url(') !== false
            && (
                stripos($updated, '<style') !== false
                || stripos($updated, 'style=') !== false
                || stripos($updated, 'background:') !== false
                || stripos($updated, 'background-image:') !== false
            )
        ) {
            $updated = $this->replaceCssUrls($updated);
        }

        return $updated;
    }

    private function replaceImageUrls(string $html): string
    {
        return (string)preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $matches): string {
                $tag = $matches[0];
                $dimensions = $this->extractImageDimensions($tag);
                $originalSrc = $this->extractAttribute($tag, 'src');

                $tag = (string)preg_replace_callback(
                    '/(\s+src=["\'])([^"\']+)(["\'])/i',
                    function (array $srcMatches) use ($dimensions): string {
                        $originalUrl = $srcMatches[2];
                        if (!$this->shouldRewrite($originalUrl)) {
                            return $srcMatches[0];
                        }

                        return $srcMatches[1]
                            . $this->rewriteGenericUrlCached($originalUrl, $dimensions['width'], $dimensions['height'])
                            . $srcMatches[3];
                    },
                    $tag,
                    1
                );

                if (stripos($tag, 'srcset=') !== false) {
                    $tag = $this->replaceImgSrcsetUrls($tag, $dimensions['width'], $dimensions['height']);
                } elseif ($this->isResponsiveImageCandidate($tag, $originalSrc, $dimensions)) {
                    $tag = $this->addResponsiveSrcset($tag, $originalSrc, $dimensions);
                }

                return $tag;
            },
            $html
        );
    }

    private function replaceSrcsetUrls(string $html): string
    {
        return (string)preg_replace_callback(
            '/\s(srcset)=["\']([^"\']+)["\']/i',
            function (array $matches): string {
                $parts = explode(',', $matches[2]);
                $newParts = [];

                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }

                    $subParts = preg_split('/\s+/', $part, 2);
                    $url = $subParts[0] ?? '';
                    $descriptor = trim($subParts[1] ?? '');
                    $width = 0;
                    $height = 0;
                    if ($descriptor !== '' && preg_match('/^([0-9]+)w$/i', $descriptor, $widthMatch)) {
                        $width = (int)$widthMatch[1];
                    }
                    if ($this->shouldRewrite($url)) {
                        $url = $this->rewriteGenericUrlCached($url, $width, $height);
                    }

                    $newParts[] = trim($this->escapeSrcsetUrl($url) . ' ' . $descriptor);
                }

                return ' srcset="' . implode(', ', $newParts) . '"';
            },
            $html
        );
    }

    private function replaceImgSrcsetUrls(string $tag, int $baseWidth, int $baseHeight): string
    {
        return (string)preg_replace_callback(
            '/\s(srcset)=["\']([^"\']+)["\']/i',
            function (array $matches) use ($baseWidth, $baseHeight): string {
                $parts = explode(',', $matches[2]);
                $newParts = [];

                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }

                    $subParts = preg_split('/\s+/', $part, 2);
                    $url = $subParts[0] ?? '';
                    $descriptor = trim($subParts[1] ?? '');
                    $width = $baseWidth;
                    $height = $baseHeight;

                    if ($descriptor !== '' && preg_match('/^([0-9]+(?:\.[0-9]+)?)x$/i', $descriptor, $densityMatch)) {
                        $density = (float)$densityMatch[1];
                        $width = $baseWidth > 0 ? (int)round($baseWidth * $density) : 0;
                        $height = $baseHeight > 0 ? (int)round($baseHeight * $density) : 0;
                    } elseif ($descriptor !== '' && preg_match('/^([0-9]+)w$/i', $descriptor, $widthMatch)) {
                        $width = (int)$widthMatch[1];
                        $height = 0;
                    }

                    if ($this->shouldRewrite($url)) {
                        $url = $this->rewriteGenericUrlCached($url, $width, $height);
                    }

                    $newParts[] = trim($this->escapeSrcsetUrl($url) . ' ' . $descriptor);
                }

                return ' srcset="' . implode(', ', $newParts) . '"';
            },
            $tag
        );
    }

    private function replaceCssUrls(string $html): string
    {
        $scripts = [];
        $htmlSafe = (string)preg_replace_callback(
            '/<script\b[^>]*>.*?<\/script>/is',
            function (array $match) use (&$scripts): string {
                $placeholder = '<!--WPXS' . count($scripts) . '-->';
                $scripts[$placeholder] = $match[0];
                return $placeholder;
            },
            $html
        );

        $htmlSafe = (string)preg_replace_callback(
            '/url\([\'"]?([^\'")]+)[\'"]?\)/i',
            function (array $matches): string {
                $originalUrl = trim($matches[1]);
                if (!$this->shouldRewrite($originalUrl)) {
                    return $matches[0];
                }

                return str_replace($originalUrl, $this->rewriteGenericUrlCached($originalUrl), $matches[0]);
            },
            $htmlSafe
        );

        return $scripts !== [] ? strtr($htmlSafe, $scripts) : $htmlSafe;
    }

    private function shouldRewrite(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (array_key_exists($url, $this->shouldRewriteCache)) {
            return $this->shouldRewriteCache[$url];
        }
        if (!$this->dataHelper->isSupportedImageUrl($url) || $this->dataHelper->isWebpixUrl($url)) {
            $this->shouldRewriteCache[$url] = false;
            return false;
        }

        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        $isSvg = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
        if ($isSvg) {
            $this->shouldRewriteCache[$url] = $this->dataHelper->isSvgEnabled();
            return $this->shouldRewriteCache[$url];
        }

        if (strpos($path, '/static/') !== false || strpos($path, '/pub/static/') !== false) {
            $this->shouldRewriteCache[$url] = false;
            return $this->shouldRewriteCache[$url];
        }

        if (strpos($path, '/media/catalog/product/') !== false) {
            $this->shouldRewriteCache[$url] = $this->dataHelper->isProductEnabled();
            return $this->shouldRewriteCache[$url];
        }

        $this->shouldRewriteCache[$url] = $this->dataHelper->isCmsEnabled();
        return $this->shouldRewriteCache[$url];
    }

    private function rewriteGenericUrlCached(string $originalUrl, int $width = 0, int $height = 0): string
    {
        $cacheKey = $originalUrl . '|w=' . max(0, $width) . '|h=' . max(0, $height);
        if (!array_key_exists($cacheKey, $this->rewriteCache)) {
            $this->rewriteCache[$cacheKey] = $this->imageHelper->replaceGenericUrl($originalUrl, $width, $height);
        }

        return $this->rewriteCache[$cacheKey];
    }

    private function isResponsiveImageCandidate(string $tag, string $src, array $dimensions): bool
    {
        if (!$this->dataHelper->isResponsiveGalleryEnabled() || $src === '') {
            return false;
        }

        $sourceUrl = $this->resolveResponsiveSourceUrl($src);
        if ($sourceUrl === '') {
            return false;
        }

        $path = strtolower((string)parse_url($sourceUrl, PHP_URL_PATH));
        if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            return false;
        }

        $isProductImage = strpos($path, '/media/catalog/product/') !== false;
        if ($isProductImage && !$this->dataHelper->isProductEnabled()) {
            return false;
        }

        if (!$isProductImage && !$this->dataHelper->isCmsEnabled()) {
            return false;
        }

        $width = (int)($dimensions['width'] ?? 0);
        $height = (int)($dimensions['height'] ?? 0);
        if ($width > 0 || $height > 0) {
            return $width >= self::MIN_RESPONSIVE_WIDTH
                && $height >= self::MIN_RESPONSIVE_HEIGHT;
        }

        return !$isProductImage && $this->isLikelyCmsContentImage($tag, $path);
    }

    private function addResponsiveSrcset(string $tag, string $originalSrc, array $dimensions): string
    {
        $sourceUrl = $this->resolveResponsiveSourceUrl($originalSrc);
        if ($sourceUrl === '') {
            return $tag;
        }

        $srcset = [];
        foreach ($this->getResponsiveWidths((int)($dimensions['width'] ?? 0)) as $width) {
            $height = $this->scaleHeight($width, (int)($dimensions['width'] ?? 0), (int)($dimensions['height'] ?? 0));
            $srcset[] = $this->escapeSrcsetUrl($this->rewriteGenericUrlCached($sourceUrl, $width, $height)) . ' ' . $width . 'w';
        }

        $attributes = ' srcset="' . htmlspecialchars(implode(', ', $srcset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        if (stripos($tag, ' sizes=') === false) {
            $attributes .= ' sizes="' . htmlspecialchars($this->getResponsiveSizes($tag, (int)($dimensions['width'] ?? 0), $sourceUrl), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return (string)preg_replace('/\s*\/?>$/', $attributes . '$0', $tag, 1);
    }

    private function getResponsiveWidths(int $originalWidth): array
    {
        $widths = array_filter(
            self::RESPONSIVE_WIDTHS,
            static fn(int $width): bool => $originalWidth <= 0 || $width < $originalWidth
        );

        if ($originalWidth > 0) {
            $widths[] = $originalWidth;
        }

        return array_values(array_unique($widths));
    }

    private function scaleHeight(int $targetWidth, int $originalWidth, int $originalHeight): int
    {
        if ($targetWidth <= 0 || $originalWidth <= 0 || $originalHeight <= 0) {
            return 0;
        }

        return max(1, (int)round($originalHeight * ($targetWidth / $originalWidth)));
    }

    private function getResponsiveSizes(string $tag, int $originalWidth, string $sourceUrl = ''): string
    {
        if ($this->isLikelyProductGalleryImage($tag)) {
            return self::PRODUCT_GALLERY_SIZES;
        }

        $path = strtolower((string)parse_url($sourceUrl, PHP_URL_PATH));
        if ($this->isLikelyCmsContentImage($tag, $path)) {
            return self::CMS_CONTENT_SIZES;
        }

        if ($originalWidth <= 0) {
            return '100vw';
        }

        return '(max-width: ' . $originalWidth . 'px) 100vw, ' . $originalWidth . 'px';
    }

    private function isLikelyProductGalleryImage(string $tag): bool
    {
        $tagLower = strtolower($tag);
        return strpos($tagLower, 'gallery-ratio') !== false
            || strpos($tagLower, 'object-scale-down') !== false
            || strpos($tagLower, 'gallery-image') !== false;
    }

    private function isLikelyCmsContentImage(string $tag, string $path): bool
    {
        if (
            strpos($path, '/media/magefan_blog/') !== false
            || strpos($path, '/media/wysiwyg/') !== false
            || strpos($path, '/media/catalog/category/') !== false
        ) {
            return true;
        }

        return (bool)preg_match('/\bclass=["\'][^"\']*\bimg-fluid\b[^"\']*["\']/i', $tag);
    }

    private function resolveResponsiveSourceUrl(string $src): string
    {
        if ($src === '') {
            return '';
        }

        if (!$this->dataHelper->isWebpixUrl($src)) {
            return $src;
        }

        return $this->extractSourceUrlFromWebpixImageUrl($src);
    }

    private function extractSourceUrlFromWebpixImageUrl(string $url): string
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) < 3 || ($segments[0] ?? '') !== 'img') {
            return '';
        }

        foreach ($segments as $segment) {
            if (strpos($segment, 'e1_') !== 0) {
                continue;
            }

            $payload = $this->dataHelper->decryptTokenPayload(substr($segment, 3), 'img', '/img/');
            $path = (string)($payload['p'] ?? '');
            if ($path === '') {
                return '';
            }

            return $this->extractSourceUrlFromWebpixImagePath($path);
        }

        foreach ($segments as $index => $segment) {
            if (!preg_match('/(?:^|,)q_[0-9]+(?:,|$)/', $segment) || strpos($segment, 'f_') === false) {
                continue;
            }

            $sourceSegments = array_slice($segments, $index + 1);
            if (count($sourceSegments) < 2) {
                return '';
            }

            return 'https://' . implode('/', array_map('rawurldecode', $sourceSegments));
        }

        return '';
    }

    private function extractSourceUrlFromWebpixImagePath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) < 3 || ($segments[0] ?? '') !== 'img') {
            return '';
        }

        foreach ($segments as $index => $segment) {
            if (!preg_match('/(?:^|,)q_[0-9]+(?:,|$)/', $segment) || strpos($segment, 'f_') === false) {
                continue;
            }

            $sourceSegments = array_slice($segments, $index + 1);
            if (count($sourceSegments) < 2) {
                return '';
            }

            return 'https://' . implode('/', array_map('rawurldecode', $sourceSegments));
        }

        return '';
    }

    private function escapeSrcsetUrl(string $url): string
    {
        return str_replace(',', '%2C', $url);
    }

    private function extractImageDimensions(string $tag): array
    {
        return [
            'width' => $this->extractPositiveIntAttribute($tag, 'width'),
            'height' => $this->extractPositiveIntAttribute($tag, 'height'),
        ];
    }

    private function extractPositiveIntAttribute(string $tag, string $attribute): int
    {
        if (!preg_match('/\s' . preg_quote($attribute, '/') . '=["\']?([0-9]+)(?:px)?["\']?/i', $tag, $match)) {
            return 0;
        }

        return max(0, (int)$match[1]);
    }

    private function extractAttribute(string $tag, string $attribute): string
    {
        if (!preg_match('/\s' . preg_quote($attribute, '/') . '=["\']([^"\']+)["\']/i', $tag, $match)) {
            return '';
        }

        return trim($match[1]);
    }

}
