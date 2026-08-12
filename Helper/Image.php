<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper;

use Magento\Catalog\Model\View\Asset\Image as AssetImage;

class Image
{
    private const DEFAULT_IMAGE_ENDPOINT = 'img';
    private const DEFAULT_IMAGE_MODE = 'auto';
    private const DEFAULT_CLIENT_IMAGE_MODE = 'default';

    private array $originCache = [];
    private array $replacementCache = [];

    public function __construct(
        private readonly Data $dataHelper,
        private readonly Svg $svgHelper
    ) {
    }

    public function replaceAssetImageUrl(AssetImage $image, string $imageUrl): string
    {
        if (!$this->dataHelper->isConfigured() || !$this->dataHelper->isProductEnabled()) {
            return $imageUrl;
        }

        $origin = $this->extractOrigin($imageUrl);
        if ($origin === null) {
            return $imageUrl;
        }

        if ($origin['extension'] === 'svg') {
            return $this->dataHelper->isSvgEnabled()
                ? $this->svgHelper->buildUrl($origin['host'], $origin['path'])
                : $imageUrl;
        }

        $params = $image->getImageTransformationParameters();
        $width = !empty($params['width']) ? (int)$params['width'] : 0;
        $height = !empty($params['height']) ? (int)$params['height'] : 0;

        return $this->buildUrl($origin['host'], $origin['path'], $width, $height, $this->dataHelper->getProductQuality());
    }

    public function replaceGenericUrl(string $originalUrl, int $width = 0, int $height = 0): string
    {
        $width = max(0, $width);
        $height = max(0, $height);
        $cacheKey = $originalUrl . '|w=' . $width . '|h=' . $height;
        if (array_key_exists($cacheKey, $this->replacementCache)) {
            return $this->replacementCache[$cacheKey];
        }

        if (!$this->dataHelper->isConfigured()) {
            $this->replacementCache[$cacheKey] = $originalUrl;
            return $originalUrl;
        }

        $origin = $this->extractOrigin($originalUrl);
        if ($origin === null) {
            $this->replacementCache[$cacheKey] = $originalUrl;
            return $originalUrl;
        }

        if ($origin['extension'] === 'svg') {
            $this->replacementCache[$cacheKey] = $this->dataHelper->isSvgEnabled()
                ? $this->svgHelper->buildUrl($origin['host'], $origin['path'])
                : $originalUrl;
            return $this->replacementCache[$cacheKey];
        }

        $this->replacementCache[$cacheKey] = $this->buildUrl(
            $origin['host'],
            $origin['path'],
            $width,
            $height,
            strpos($origin['path'], '/media/catalog/product/') !== false ? $this->dataHelper->getProductQuality() : null
        );

        return $this->replacementCache[$cacheKey];
    }

    private function buildUrl(string $originHost, string $originPath, int $width, int $height, ?int $quality = null): string
    {
        $kid = $this->dataHelper->getCloudName();
        $quality = $quality !== null ? max(1, min($quality, 100)) : $this->dataHelper->getQuality();
        $format = $this->dataHelper->getFormat();
        $resizeMode = $this->dataHelper->getResizeMode();
        if ($resizeMode === self::DEFAULT_CLIENT_IMAGE_MODE) {
            $resizeMode = 'fit';
            $height = 0;
        }

        $source = $this->canonicalSource($originHost, $originPath);
        $sourcePath = $this->buildSourcePath($source, $width, $height, $quality, $format, $resizeMode);

        if ($this->dataHelper->isSecureUrl()) {
            $token = $this->dataHelper->encryptToken(
                '/' . self::DEFAULT_IMAGE_ENDPOINT . '/' . $sourcePath,
                'img',
                '/' . self::DEFAULT_IMAGE_ENDPOINT . '/'
            );
            if ($token !== '') {
                return $this->dataHelper->getWebpixHost()
                    . '/' . self::DEFAULT_IMAGE_ENDPOINT
                    . '/k_' . $kid
                    . '/e1_' . $token;
            }
        }

        $payload = $this->buildPayload($kid, $source, $width, $height, $quality, $format, $resizeMode);
        $signature = $this->dataHelper->signPayload($payload);
        if ($signature === '') {
            return $this->dataHelper->getWebpixHost()
                . '/' . self::DEFAULT_IMAGE_ENDPOINT
                . '/k_' . $kid
                . '/' . $sourcePath;
        }

        return $this->dataHelper->getWebpixHost()
            . '/' . self::DEFAULT_IMAGE_ENDPOINT
            . '/k_' . $kid
            . '/s1_' . $signature
            . '/' . $sourcePath;
    }

    private function extractOrigin(string $url): ?array
    {
        $normalizedUrl = trim($url);
        if (array_key_exists($normalizedUrl, $this->originCache)) {
            return $this->originCache[$normalizedUrl];
        }

        if ($normalizedUrl === '' || $this->dataHelper->isWebpixUrl($normalizedUrl) || !$this->dataHelper->isSupportedImageUrl($normalizedUrl)) {
            $this->originCache[$normalizedUrl] = null;
            return null;
        }

        $parsed = parse_url($normalizedUrl);
        if ($parsed === false) {
            $this->originCache[$normalizedUrl] = null;
            return null;
        }

        $host = isset($parsed['host']) ? trim((string)$parsed['host']) : $this->dataHelper->getBaseDomain();
        $path = isset($parsed['path']) ? trim((string)$parsed['path']) : '';
        if ($host === '' || $path === '') {
            $this->originCache[$normalizedUrl] = null;
            return null;
        }

        if (strpos($path, '/pub/') === 0) {
            $path = substr($path, 4);
        }

        if ($path === false || $path === '') {
            $this->originCache[$normalizedUrl] = null;
            return null;
        }

        $normalizedPath = '/' . ltrim($path, '/');
        $this->originCache[$normalizedUrl] = [
            'host' => $host,
            'path' => $normalizedPath,
            'extension' => strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)),
        ];

        return $this->originCache[$normalizedUrl];
    }

    private function canonicalSource(string $host, string $path): string
    {
        return trim($host) . '/' . ltrim(trim($path), '/');
    }

    private function buildPayload(
        string $kid,
        string $source,
        int $width,
        int $height,
        int $quality,
        string $format,
        string $resizeMode
    ): string {
        return sprintf(
            'v=1|t=img|k=%s|w=%d|h=%d|q=%d|f=%s|rs=%s|%s',
            $kid,
            $width,
            $height,
            $quality,
            $format,
            $resizeMode,
            $source
        );
    }

    private function buildSourcePath(
        string $source,
        int $width,
        int $height,
        int $quality,
        string $format,
        string $resizeMode
    ): string {
        $params = [];
        if ($width > 0) {
            $params[] = 'w_' . $width;
        }
        if ($height > 0) {
            $params[] = 'h_' . $height;
        }
        $params[] = 'q_' . $quality;
        $params[] = 'f_' . $format;
        if ($resizeMode !== self::DEFAULT_IMAGE_MODE) {
            $params[] = 'rs_' . $resizeMode;
        }

        return implode(',', $params) . '/' . $source;
    }
}
