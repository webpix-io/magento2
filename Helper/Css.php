<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class Css extends AbstractHelper
{
    private const DEFAULT_CSS_ENDPOINT = 'css';
    private const DEFAULT_MODE = 'proxy';

    private UrlInterface $urlBuilder;
    private Data $dataHelper;
    private ?bool $enabledCache = null;
    private ?bool $secureCache = null;
    private ?string $baseHostCache = null;
    private array $replacementCache = [];

    public function __construct(
        Context $context,
        UrlInterface $urlBuilder,
        Data $dataHelper
    ) {
        parent::__construct($context);
        $this->urlBuilder = $urlBuilder;
        $this->dataHelper = $dataHelper;
    }

    public function isEnabled(): bool
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }

        $this->enabledCache = $this->scopeConfig->isSetFlag('webpix/css/enabled', ScopeInterface::SCOPE_STORE);
        return $this->enabledCache;
    }

    public function isSecure(): bool
    {
        if ($this->secureCache !== null) {
            return $this->secureCache;
        }

        $this->secureCache = $this->scopeConfig->isSetFlag('webpix/css/secure', ScopeInterface::SCOPE_STORE);
        return $this->secureCache;
    }

    public function getMode(): string
    {
        return self::DEFAULT_MODE;
    }

    public function replaceCssUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }
        if (array_key_exists($url, $this->replacementCache)) {
            return $this->replacementCache[$url];
        }
        if (stripos($url, 'data:') === 0) {
            $this->replacementCache[$url] = $url;
            return $url;
        }
        if (!$this->isEnabled() || !$this->dataHelper->isConfigured() || $this->dataHelper->isWebpixUrl($url)) {
            $this->replacementCache[$url] = $url;
            return $url;
        }

        [$host, $path, $query] = $this->extractCssOrigin($url);
        if ($host === '' || $path === '') {
            $this->replacementCache[$url] = $url;
            return $url;
        }
        $source = $host . '/' . ltrim($path, '/');
        $kid = $this->dataHelper->getCloudName();
        $mode = $this->getMode();
        $hostUrl = $this->dataHelper->getWebpixHost();

        if ($this->isSecure()) {
            $token = $this->dataHelper->encryptToken(
                '/' . self::DEFAULT_CSS_ENDPOINT . '/a_' . $mode . '/' . $source,
                'css',
                '/' . self::DEFAULT_CSS_ENDPOINT . '/',
                $query
            );
            if ($token !== '') {
                $this->replacementCache[$url] = $hostUrl
                    . '/' . self::DEFAULT_CSS_ENDPOINT
                    . '/k_' . $kid
                    . '/e1_' . $token;
                return $this->replacementCache[$url];
            }
        }

        $payload = sprintf(
            'v=1|t=css|k=%s|a=%s|sq=%s|%s',
            $kid,
            $mode,
            $query !== '' ? $query : '-',
            $source
        );

        $signature = $this->dataHelper->signPayload($payload);

        $result = $hostUrl
            . '/' . self::DEFAULT_CSS_ENDPOINT
            . '/k_' . $kid
            . '/s1_' . $signature
            . '/a_' . $mode
            . '/' . $source;

        if ($query !== '') {
            $result .= '?' . $query;
        }

        $this->replacementCache[$url] = $result;
        return $this->replacementCache[$url];
    }

    private function extractCssOrigin(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['', '', ''];
        }

        if (preg_match('#^https?://#i', $url)) {
            $parsed = parse_url($url);
            if ($parsed === false || empty($parsed['host']) || empty($parsed['path'])) {
                return ['', '', ''];
            }

            return [
                trim((string)$parsed['host']),
                '/' . ltrim((string)$parsed['path'], '/'),
                trim((string)($parsed['query'] ?? '')),
            ];
        }

        $baseHost = $this->baseHostCache;
        if ($baseHost === null) {
            $baseHost = (string)parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
            $this->baseHostCache = $baseHost;
        }
        if ($baseHost === '') {
            return ['', '', ''];
        }

        $query = '';
        $path = $url;
        if (strpos($url, '?') !== false) {
            [$path, $query] = explode('?', $url, 2);
        }

        return [
            $baseHost,
            '/' . ltrim($path, '/'),
            trim($query),
        ];
    }

}
