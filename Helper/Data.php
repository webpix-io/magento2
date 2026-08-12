<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const DEFAULT_CDN_HOST = 'https://cdn.webpix.io';
    private const DEFAULT_CLIENT_IMAGE_MODE = 'default';
    private const SUPPORTED_IMAGE_MODES = ['default', 'auto', 'fit', 'fill'];
    private const SUPPORTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg'];
    private const DEFAULT_GOOGLE_FONTS_DISPLAY = 'swap';
    private const SUPPORTED_GOOGLE_FONTS_DISPLAY = ['swap', 'optional'];

    private UrlInterface $urlBuilder;
    private EncryptorInterface $encryptor;
    private ?bool $enabledCache = null;
    private ?bool $customCdnCache = null;
    private ?string $customHostnameCache = null;
    private ?string $webpixHostCache = null;
    private ?string $webpixHostNameCache = null;
    private ?string $cloudNameCache = null;
    private ?string $secretKeyCache = null;
    private ?string $secretPinCache = null;
    private ?string $encryptKeyCache = null;
    private ?bool $secureUrlCache = null;
    private ?int $qualityCache = null;
    private ?int $productQualityCache = null;
    private ?string $formatCache = null;
    private ?string $resizeModeCache = null;
    private ?bool $productEnabledCache = null;
    private ?bool $responsiveGalleryEnabledCache = null;
    private ?bool $listingLcpEnabledCache = null;
    private ?bool $cmsEnabledCache = null;
    private ?bool $svgEnabledCache = null;
    private ?bool $googleFontsEnabledCache = null;
    private ?string $googleFontsDisplayCache = null;
    private ?bool $googleFontsForceDisplayCache = null;
    private ?bool $configuredCache = null;
    private ?string $baseDomainCache = null;
    private array $isSupportedImageUrlCache = [];
    private array $isWebpixUrlCache = [];

    public function __construct(
        Context $context,
        UrlInterface $urlBuilder,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->urlBuilder = $urlBuilder;
        $this->encryptor = $encryptor;
    }

    public function isEnabled(): bool
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }

        $this->enabledCache = $this->scopeConfig->isSetFlag('webpix_optimizer/general/enabled', ScopeInterface::SCOPE_STORE);
        return $this->enabledCache;
    }

    public function isCustomCdn(): bool
    {
        if ($this->customCdnCache !== null) {
            return $this->customCdnCache;
        }

        $this->customCdnCache = $this->scopeConfig->isSetFlag('webpix_optimizer/general/cdn_mode', ScopeInterface::SCOPE_STORE);
        return $this->customCdnCache;
    }

    public function getCustomHostname(): string
    {
        if ($this->customHostnameCache !== null) {
            return $this->customHostnameCache;
        }

        $this->customHostnameCache = trim((string)$this->scopeConfig->getValue('webpix_optimizer/general/custom_hostname', ScopeInterface::SCOPE_STORE));
        return $this->customHostnameCache;
    }

    public function getWebpixHost(): string
    {
        if ($this->webpixHostCache !== null) {
            return $this->webpixHostCache;
        }

        $host = self::DEFAULT_CDN_HOST;
        if ($this->isCustomCdn()) {
            $customHost = $this->normalizeHost($this->getCustomHostname());
            if ($customHost !== '') {
                $host = $customHost;
            }
        }

        $this->webpixHostCache = $host;
        $this->webpixHostNameCache = (string)parse_url($host, PHP_URL_HOST);
        return $this->webpixHostCache;
    }

    public function getCloudName(): string
    {
        if ($this->cloudNameCache !== null) {
            return $this->cloudNameCache;
        }

        $this->cloudNameCache = trim((string)$this->scopeConfig->getValue('webpix_optimizer/access/cloud_name', ScopeInterface::SCOPE_STORE));
        return $this->cloudNameCache;
    }

    public function getSecretKey(): string
    {
        if ($this->secretKeyCache !== null) {
            return $this->secretKeyCache;
        }

        $value = (string)$this->scopeConfig->getValue('webpix_optimizer/access/secret_key', ScopeInterface::SCOPE_STORE);
        $this->secretKeyCache = trim($this->decryptIfNeeded($value));
        return $this->secretKeyCache;
    }

    public function getSecretPin(): string
    {
        if ($this->secretPinCache !== null) {
            return $this->secretPinCache;
        }

        $value = (string)$this->scopeConfig->getValue('webpix_optimizer/access/secret_pin', ScopeInterface::SCOPE_STORE);
        $this->secretPinCache = trim($this->decryptIfNeeded($value));
        return $this->secretPinCache;
    }

    public function getEncryptKey(): string
    {
        if ($this->encryptKeyCache !== null) {
            return $this->encryptKeyCache;
        }

        $value = (string)$this->scopeConfig->getValue('webpix_optimizer/access/encrypt_key', ScopeInterface::SCOPE_STORE);
        $this->encryptKeyCache = trim($this->decryptIfNeeded($value));
        return $this->encryptKeyCache;
    }

    private function decryptIfNeeded(string $value): string
    {
        if ($value === '' || strpos($value, '0:') !== 0) {
            return $value;
        }

        $decrypted = $this->encryptor->decrypt($value);
        return $decrypted !== '' ? $decrypted : $value;
    }

    public function isSecureUrl(): bool
    {
        if ($this->secureUrlCache !== null) {
            return $this->secureUrlCache;
        }

        $this->secureUrlCache = $this->scopeConfig->isSetFlag('webpix_optimizer/image/secure', ScopeInterface::SCOPE_STORE);
        return $this->secureUrlCache;
    }

    public function getQuality(): int
    {
        if ($this->qualityCache !== null) {
            return $this->qualityCache;
        }

        $quality = (int)$this->scopeConfig->getValue('webpix_optimizer/image/quality', ScopeInterface::SCOPE_STORE);
        $this->qualityCache = $quality > 0 ? min($quality, 100) : 75;
        return $this->qualityCache;
    }

    public function getProductQuality(): int
    {
        if ($this->productQualityCache !== null) {
            return $this->productQualityCache;
        }

        $quality = (int)$this->scopeConfig->getValue('webpix_optimizer/image/product_quality', ScopeInterface::SCOPE_STORE);
        $this->productQualityCache = $quality > 0 ? min($quality, 100) : $this->getQuality();
        return $this->productQualityCache;
    }

    public function getFormat(): string
    {
        if ($this->formatCache !== null) {
            return $this->formatCache;
        }

        $format = strtolower(trim((string)$this->scopeConfig->getValue('webpix_optimizer/image/format', ScopeInterface::SCOPE_STORE)));
        $this->formatCache = $format !== '' ? $format : 'webp';
        return $this->formatCache;
    }

    public function getResizeMode(): string
    {
        if ($this->resizeModeCache !== null) {
            return $this->resizeModeCache;
        }

        $mode = strtolower(trim((string)$this->scopeConfig->getValue('webpix_optimizer/image/resize_mode', ScopeInterface::SCOPE_STORE)));
        $this->resizeModeCache = in_array($mode, self::SUPPORTED_IMAGE_MODES, true) ? $mode : self::DEFAULT_CLIENT_IMAGE_MODE;
        return $this->resizeModeCache;
    }

    public function isProductEnabled(): bool
    {
        if ($this->productEnabledCache !== null) {
            return $this->productEnabledCache;
        }

        $this->productEnabledCache = $this->scopeConfig->isSetFlag('webpix_optimizer/image/product', ScopeInterface::SCOPE_STORE);
        return $this->productEnabledCache;
    }

    public function isResponsiveGalleryEnabled(): bool
    {
        if ($this->responsiveGalleryEnabledCache !== null) {
            return $this->responsiveGalleryEnabledCache;
        }

        $this->responsiveGalleryEnabledCache = $this->scopeConfig->isSetFlag(
            'webpix_optimizer/image/responsive_gallery',
            ScopeInterface::SCOPE_STORE
        );

        return $this->responsiveGalleryEnabledCache;
    }

    public function isListingLcpOptimizationEnabled(): bool
    {
        if ($this->listingLcpEnabledCache !== null) {
            return $this->listingLcpEnabledCache;
        }

        $this->listingLcpEnabledCache = $this->scopeConfig->isSetFlag(
            'webpix_optimizer/image/listing_lcp',
            ScopeInterface::SCOPE_STORE
        );

        return $this->listingLcpEnabledCache;
    }

    public function isCmsEnabled(): bool
    {
        if ($this->cmsEnabledCache !== null) {
            return $this->cmsEnabledCache;
        }

        $this->cmsEnabledCache = $this->scopeConfig->isSetFlag('webpix_optimizer/image/cms', ScopeInterface::SCOPE_STORE);
        return $this->cmsEnabledCache;
    }

    public function isSvgEnabled(): bool
    {
        if ($this->svgEnabledCache !== null) {
            return $this->svgEnabledCache;
        }

        $this->svgEnabledCache = $this->scopeConfig->isSetFlag('webpix_optimizer/image/svg', ScopeInterface::SCOPE_STORE);
        return $this->svgEnabledCache;
    }

    public function isGoogleFontsOptimizationEnabled(): bool
    {
        if ($this->googleFontsEnabledCache !== null) {
            return $this->googleFontsEnabledCache;
        }

        $this->googleFontsEnabledCache = $this->isEnabled()
            && $this->scopeConfig->isSetFlag('webpix_optimizer/fonts/enabled', ScopeInterface::SCOPE_STORE);

        return $this->googleFontsEnabledCache;
    }

    public function getGoogleFontsDisplayStrategy(): string
    {
        if ($this->googleFontsDisplayCache !== null) {
            return $this->googleFontsDisplayCache;
        }

        $display = strtolower(trim((string)$this->scopeConfig->getValue('webpix_optimizer/fonts/display', ScopeInterface::SCOPE_STORE)));
        $this->googleFontsDisplayCache = in_array($display, self::SUPPORTED_GOOGLE_FONTS_DISPLAY, true)
            ? $display
            : self::DEFAULT_GOOGLE_FONTS_DISPLAY;

        return $this->googleFontsDisplayCache;
    }

    public function isGoogleFontsForceDisplayEnabled(): bool
    {
        if ($this->googleFontsForceDisplayCache !== null) {
            return $this->googleFontsForceDisplayCache;
        }

        $this->googleFontsForceDisplayCache = $this->scopeConfig->isSetFlag('webpix_optimizer/fonts/force_display', ScopeInterface::SCOPE_STORE);
        return $this->googleFontsForceDisplayCache;
    }

    public function isConfigured(): bool
    {
        if ($this->configuredCache !== null) {
            return $this->configuredCache;
        }

        $this->configuredCache = $this->isEnabled()
            && $this->isValidKeyId($this->getCloudName())
            && $this->getSecretKey() !== ''
            && $this->getSecretPin() !== '';

        return $this->configuredCache;
    }

    public function getBaseDomain(): string
    {
        if ($this->baseDomainCache !== null) {
            return $this->baseDomainCache;
        }

        $host = (string)parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
        $this->baseDomainCache = $host !== '' ? $host : 'localhost';
        return $this->baseDomainCache;
    }

    public function isSupportedImageUrl(string $url): bool
    {
        if (array_key_exists($url, $this->isSupportedImageUrlCache)) {
            return $this->isSupportedImageUrlCache[$url];
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $this->isSupportedImageUrlCache[$url] = in_array($extension, self::SUPPORTED_IMAGE_EXTENSIONS, true);
        return $this->isSupportedImageUrlCache[$url];
    }

    public function isWebpixUrl(string $url): bool
    {
        if (array_key_exists($url, $this->isWebpixUrlCache)) {
            return $this->isWebpixUrlCache[$url];
        }

        $host = $this->webpixHostNameCache;
        if ($host === null) {
            $host = (string)parse_url($this->getWebpixHost(), PHP_URL_HOST);
            $this->webpixHostNameCache = $host;
        }

        if ($host === '') {
            $this->isWebpixUrlCache[$url] = false;
            return false;
        }

        $urlHost = (string)parse_url(trim($url), PHP_URL_HOST);
        $this->isWebpixUrlCache[$url] = $urlHost !== '' && strcasecmp($urlHost, $host) === 0;
        return $this->isWebpixUrlCache[$url];
    }

    public function signPayload(string $payload): string
    {
        $digest = hash_hmac('sha256', $this->getSecretPin() . '|' . $payload, $this->getSecretKey(), true);
        return $this->base64UrlEncode(substr($digest, 0, 12));
    }

    public function encryptToken(string $path, string $target, string $prefix, string $query = ''): string
    {
        $keyMaterial = $this->getEncryptKey() !== ''
            ? hash('sha256', $this->getEncryptKey(), true)
            : hash('sha256', $this->getSecretKey() . '|' . $this->getSecretPin() . '|enc', true);

        $aad = 'webpix:e1:t=' . $target . ':k=' . $this->getCloudName() . ':p=' . $prefix;
        $payloadData = ['p' => $path];
        if ($query !== '') {
            $payloadData['q'] = $query;
        }
        $payload = json_encode($payloadData, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return '';
        }
        $nonce = substr(hash_hmac('sha256', $aad . '|' . $payload, $keyMaterial, true), 0, 12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $keyMaterial,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );

        if ($ciphertext === false) {
            return '';
        }

        return $this->base64UrlEncode(chr(1) . $nonce . $ciphertext . $tag);
    }

    public function decryptTokenPayload(string $token, string $target, string $prefix): array
    {
        $raw = $this->base64UrlDecode($token);
        if (strlen($raw) < 30 || ord($raw[0]) !== 1) {
            return [];
        }

        $nonce = substr($raw, 1, 12);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 13, -16);
        if ($nonce === '' || $tag === '' || $ciphertext === '') {
            return [];
        }

        $keyMaterial = $this->getEncryptKey() !== ''
            ? hash('sha256', $this->getEncryptKey(), true)
            : hash('sha256', $this->getSecretKey() . '|' . $this->getSecretPin() . '|enc', true);

        $aad = 'webpix:e1:t=' . $target . ':k=' . $this->getCloudName() . ':p=' . $prefix;
        $payload = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $keyMaterial,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );

        if ($payload === false || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    private function isValidKeyId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{8}$/', $value) === 1;
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $host)) {
            $host = 'https://' . $host;
        }

        return rtrim($host, '/');
    }
}
