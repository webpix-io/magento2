<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper\Html;

use Webpix\Optimizer\Helper\Data;

class Fonts
{
    private array $urlCache = [];

    public function __construct(
        private readonly Data $dataHelper
    ) {
    }

    public function normalizeGoogleFontsUrls(string $html): string
    {
        if (!$this->dataHelper->isGoogleFontsOptimizationEnabled() || stripos($html, 'fonts.googleapis.com') === false) {
            return $html;
        }

        $updated = (string)preg_replace_callback(
            '/<link\b[^>]*\bhref=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\'][^>]*>/i',
            function (array $matches): string {
                $originalTag = $matches[0];
                $originalUrl = trim($matches[1]);
                if ($originalUrl === '') {
                    return $originalTag;
                }

                $updatedUrl = $this->rewriteGoogleFontsUrl($originalUrl);
                return $updatedUrl === $originalUrl ? $originalTag : str_replace($originalUrl, $updatedUrl, $originalTag);
            },
            $html
        );

        $updated = (string)preg_replace_callback(
            '/(@import\s+url\(\s*[\"\']?)((?:https?:)?\/\/fonts\.googleapis\.com\/[^\"\')\s]+)([\"\']?\s*\))/i',
            fn(array $matches): string => $matches[1] . $this->rewriteGoogleFontsUrl(trim($matches[2])) . $matches[3],
            $updated
        );

        return (string)preg_replace_callback(
            '/(@import\s+[\"\'])((?:https?:)?\/\/fonts\.googleapis\.com\/[^\"\')\s]+)([\"\'])/i',
            fn(array $matches): string => $matches[1] . $this->rewriteGoogleFontsUrl(trim($matches[2])) . $matches[3],
            $updated
        );
    }

    private function rewriteGoogleFontsUrl(string $url): string
    {
        if (array_key_exists($url, $this->urlCache)) {
            return $this->urlCache[$url];
        }

        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $schemeLess = strpos($decoded, '//') === 0;
        $candidate = $schemeLess ? 'https:' . $decoded : $decoded;
        if (!preg_match('#^https?://#i', $candidate)) {
            $this->urlCache[$url] = $url;
            return $this->urlCache[$url];
        }

        $parts = parse_url($candidate);
        if ($parts === false || empty($parts['host']) || strcasecmp((string)$parts['host'], 'fonts.googleapis.com') !== 0) {
            $this->urlCache[$url] = $url;
            return $this->urlCache[$url];
        }

        $query = (string)($parts['query'] ?? '');
        if (!$this->dataHelper->isGoogleFontsForceDisplayEnabled() && preg_match('/(?:^|&)display=[^&]*/i', $query)) {
            $this->urlCache[$url] = $url;
            return $this->urlCache[$url];
        }

        $query = preg_replace('/(?:^|&)display=[^&]*/i', '', $query);
        $query = trim((string)$query, '&');
        $displayParam = 'display=' . rawurlencode($this->dataHelper->getGoogleFontsDisplayStrategy());
        $parts['query'] = $query !== '' ? $query . '&' . $displayParam : $displayParam;
        $normalized = $this->buildUrl($parts);
        if ($normalized === '') {
            $this->urlCache[$url] = $url;
            return $this->urlCache[$url];
        }

        if ($schemeLess) {
            $normalized = preg_replace('#^https:#i', '', $normalized) ?: $normalized;
        }
        if (strpos($url, '&amp;') !== false) {
            $normalized = str_replace('&', '&amp;', $normalized);
        }

        $this->urlCache[$url] = $normalized;
        return $this->urlCache[$url];
    }

    private function buildUrl(array $parts): string
    {
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? ((string)$parts['scheme'] . '://') : 'https://';
        $user = (string)($parts['user'] ?? '');
        $pass = (string)($parts['pass'] ?? '');
        $auth = $user !== '' ? $user . ($pass !== '' ? ':' . $pass : '') . '@' : '';
        $host = (string)$parts['host'];
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = isset($parts['path']) ? (string)$parts['path'] : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string)$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string)$parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
