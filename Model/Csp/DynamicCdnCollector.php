<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Model\Csp;

use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Webpix\Optimizer\Helper\Data;

class DynamicCdnCollector implements PolicyCollectorInterface
{
    private Data $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    public function collect(array $defaultPolicies = []): array
    {
        if (!$this->helper->isConfigured() || !$this->helper->isCustomCdn()) {
            return $defaultPolicies;
        }

        $host = $this->helper->getCustomHostname();
        if ($host === '') {
            return $defaultPolicies;
        }

        if (!preg_match('#^https?://#i', $host)) {
            $host = 'https://' . $host;
        }
        $host = (string)parse_url($host, PHP_URL_HOST);
        if ($host === '') {
            return $defaultPolicies;
        }

        foreach (['img-src', 'style-src', 'font-src', 'script-src', 'connect-src'] as $directive) {
            $defaultPolicies[] = new FetchPolicy(
                $directive,
                false,
                [$host],
                [],
                false,
                false
            );
        }

        return $defaultPolicies;
    }
}
