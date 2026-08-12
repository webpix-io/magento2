<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Helper;

class Svg
{
    private const DEFAULT_SVG_ENDPOINT = 'svg';

    public function __construct(
        private readonly Data $dataHelper
    ) {
    }

    public function buildUrl(string $originHost, string $originPath): string
    {
        $kid = $this->dataHelper->getCloudName();
        $source = $this->canonicalSource($originHost, $originPath);

        if ($this->dataHelper->isSecureUrl()) {
            $token = $this->dataHelper->encryptToken(
                '/' . self::DEFAULT_SVG_ENDPOINT . '/' . $source,
                'svg',
                '/' . self::DEFAULT_SVG_ENDPOINT . '/'
            );
            if ($token !== '') {
                return $this->dataHelper->getWebpixHost()
                    . '/' . self::DEFAULT_SVG_ENDPOINT
                    . '/k_' . $kid
                    . '/e1_' . $token;
            }
        }

        $payload = sprintf('v=1|t=svg|k=%s|%s', $kid, $source);
        $signature = $this->dataHelper->signPayload($payload);
        if ($signature === '') {
            return $this->dataHelper->getWebpixHost()
                . '/' . self::DEFAULT_SVG_ENDPOINT
                . '/k_' . $kid
                . '/' . $source;
        }

        return $this->dataHelper->getWebpixHost()
            . '/' . self::DEFAULT_SVG_ENDPOINT
            . '/k_' . $kid
            . '/s1_' . $signature
            . '/' . $source;
    }

    private function canonicalSource(string $host, string $path): string
    {
        return trim($host) . '/' . ltrim(trim($path), '/');
    }
}
