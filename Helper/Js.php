<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class Js extends AbstractHelper
{
    private const DEFAULT_JS_ENDPOINT = 'js';
    private const DEFAULT_MODE = 'proxy';
    private const BASE_URL_PATTERNS = [
        '/(\bbaseUrl\s*:\s*[\"\'])(\/static\/[^\"\']*\/?)([\"\'])/i',
        '/(\bbaseUrl\s*:\s*[\"\'])(https?:\/\/[^\/\"\']+\/static\/[^\"\']*\/?)([\"\'])/i',
        '/(\bbaseUrl\s*=\s*[\"\'])(\/static\/[^\"\']*\/?)([\"\'])/i',
        '/(\bbaseUrl\s*=\s*[\"\'])(https?:\/\/[^\/\"\']+\/static\/[^\"\']*\/?)([\"\'])/i',
    ];

    private UrlInterface $urlBuilder;
    private Data $dataHelper;
    private ?bool $enabledCache = null;
    private ?bool $secureCache = null;
    private ?string $baseHostCache = null;
    private ?string $runtimeLoaderPatchCache = null;
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

        $this->enabledCache = $this->scopeConfig->isSetFlag('webpix/js/enabled', ScopeInterface::SCOPE_STORE);
        return $this->enabledCache;
    }

    public function isSecure(): bool
    {
        if ($this->secureCache !== null) {
            return $this->secureCache;
        }

        $this->secureCache = $this->scopeConfig->isSetFlag('webpix/js/secure', ScopeInterface::SCOPE_STORE);
        return $this->secureCache;
    }

    public function replaceJsUrl(string $url): string
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

        [$host, $path, $query] = $this->extractJsOrigin($url);
        if ($host === '' || $path === '') {
            $this->replacementCache[$url] = $url;
            return $url;
        }

        $source = $host . '/' . ltrim($path, '/');
        $kid = $this->dataHelper->getCloudName();
        $hostUrl = $this->dataHelper->getWebpixHost();

        if ($this->isSecure()) {
            $token = $this->dataHelper->encryptToken(
                '/' . self::DEFAULT_JS_ENDPOINT . '/a_' . self::DEFAULT_MODE . '/' . $source,
                'js',
                '/' . self::DEFAULT_JS_ENDPOINT . '/',
                $query
            );
            if ($token !== '') {
                $this->replacementCache[$url] = $hostUrl
                    . '/' . self::DEFAULT_JS_ENDPOINT
                    . '/k_' . $kid
                    . '/e1_' . $token;
                return $this->replacementCache[$url];
            }
        }

        $payload = sprintf(
            'v=1|t=js|k=%s|a=%s|sq=%s|%s',
            $kid,
            self::DEFAULT_MODE,
            $query !== '' ? $query : '-',
            $source
        );

        $signature = $this->dataHelper->signPayload($payload);

        $result = $hostUrl
            . '/' . self::DEFAULT_JS_ENDPOINT
            . '/k_' . $kid
            . '/s1_' . $signature
            . '/a_' . self::DEFAULT_MODE
            . '/' . $source;

        if ($query !== '') {
            $result .= '?' . $query;
        }

        $this->replacementCache[$url] = $result;
        return $this->replacementCache[$url];
    }

    public function rewriteRequireBaseUrls(string $html): string
    {
        if (!$this->isEnabled() || !$this->dataHelper->isConfigured()) {
            return $html;
        }
        if (strpos($html, 'baseUrl') === false || strpos($html, '/static/') === false) {
            return $html;
        }

        $baseHost = $this->baseHostCache;
        if ($baseHost === null) {
            $baseUrl = rtrim((string)$this->urlBuilder->getBaseUrl(), '/');
            $baseHost = (string)parse_url($baseUrl, PHP_URL_HOST);
            $this->baseHostCache = $baseHost;
        }
        if ($baseHost === '') {
            return $html;
        }

        $prefix = rtrim($this->dataHelper->getWebpixHost(), '/')
            . '/' . self::DEFAULT_JS_ENDPOINT
            . '/k_' . $this->dataHelper->getCloudName()
            . '/'
            . $baseHost
            . '/';

        foreach (self::BASE_URL_PATTERNS as $pattern) {
            $html = (string)preg_replace_callback(
                $pattern,
                static function (array $matches) use ($prefix): string {
                    $path = $matches[2];
                    if (preg_match('#^https?://#i', $path)) {
                        $parsed = parse_url($path);
                        if ($parsed === false || empty($parsed['host']) || empty($parsed['path'])) {
                            return $matches[0];
                        }

                        $path = '/' . ltrim((string)$parsed['path'], '/');
                        return $matches[1] . $prefix . ltrim($path, '/') . $matches[3];
                    }

                    return $matches[1] . $prefix . ltrim($path, '/') . $matches[3];
                },
                $html
            );
        }

        return $html;
    }

    public function getRuntimeLoaderPatch(): string
    {
        if ($this->runtimeLoaderPatchCache !== null) {
            return $this->runtimeLoaderPatchCache;
        }
        if (!$this->isEnabled() || !$this->dataHelper->isConfigured()) {
            $this->runtimeLoaderPatchCache = '';
            return $this->runtimeLoaderPatchCache;
        }

        $cdnHost = rtrim($this->dataHelper->getWebpixHost(), '/');
        $cdnParsed = parse_url($cdnHost);
        $cdnOriginHost = trim((string)($cdnParsed['host'] ?? ''));
        if ($cdnHost === '' || $cdnOriginHost === '') {
            $this->runtimeLoaderPatchCache = '';
            return $this->runtimeLoaderPatchCache;
        }

        $config = [
            'cdnHost' => $cdnHost,
            'cdnOriginHost' => $cdnOriginHost,
            'kid' => $this->dataHelper->getCloudName(),
            'endpoint' => self::DEFAULT_JS_ENDPOINT,
        ];

        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->runtimeLoaderPatchCache = '';
            return $this->runtimeLoaderPatchCache;
        }

        $nonce = $this->getCspNonceAttribute();
        $this->runtimeLoaderPatchCache = '<script' . $nonce . '>(function(){var cfg=' . $json . ';function rw(raw){try{var u=new URL(raw,window.location.origin);if(!u.pathname||!/\\.js$/i.test(u.pathname)||u.host===cfg.cdnOriginHost){return raw;}return cfg.cdnHost+\"/\"+cfg.endpoint+\"/k_\"+cfg.kid+\"/\"+u.host+\"/\"+u.pathname.replace(/^\\/+/,\"\")+(u.search||\"\");}catch(e){return raw;}}function wrap(fn){if(typeof fn!==\"function\"||fn.__webpixWrapped){return fn;}var wrapped=function(context,moduleName,url){return fn.call(this,context,moduleName,rw(url));};wrapped.__webpixWrapped=true;return wrapped;}function patch(){var req=window.requirejs||window.require;if(!req){return false;}if(typeof req.load===\"function\"){req.load=wrap(req.load);}var contexts=req.s&&req.s.contexts?req.s.contexts:null;if(contexts){Object.keys(contexts).forEach(function(key){var ctx=contexts[key];if(ctx&&typeof ctx.load===\"function\"){ctx.load=wrap(ctx.load);}});}return true;}if(!patch()){var tries=0;(function tick(){tries++;if(!patch()&&tries<60){setTimeout(tick,25);}})();}})();</script>';
        return $this->runtimeLoaderPatchCache;
    }

    private function getCspNonceAttribute(): string
    {
        $className = 'Magento\\Csp\\Helper\\CspNonceProvider';
        if (!class_exists($className)) {
            return '';
        }

        $provider = ObjectManager::getInstance()->get($className);
        if (!is_object($provider) || !method_exists($provider, 'generateNonce')) {
            return '';
        }

        $nonce = (string)$provider->generateNonce();
        if ($nonce === '') {
            return '';
        }

        return ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    private function extractJsOrigin(string $url): array
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
