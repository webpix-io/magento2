<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webpix\Optimizer\Helper\Data;

class DebugSignatureCommand extends Command
{
    private const OPTION_SOURCE = 'source';
    private const OPTION_WIDTH = 'width';
    private const OPTION_HEIGHT = 'height';
    private const OPTION_QUALITY = 'quality';
    private const OPTION_FORMAT = 'format';
    private const OPTION_RESIZE = 'resize';

    public function __construct(
        private readonly Data $dataHelper,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('webpix:optimizer:debug-signature')
            ->setDescription('Debug WebPix image signature generation without printing secret values.')
            ->addOption(self::OPTION_SOURCE, null, InputOption::VALUE_REQUIRED, 'Canonical source, for example webpix.io/media/image.webp')
            ->addOption(self::OPTION_WIDTH, null, InputOption::VALUE_OPTIONAL, 'Image width', '0')
            ->addOption(self::OPTION_HEIGHT, null, InputOption::VALUE_OPTIONAL, 'Image height', '0')
            ->addOption(self::OPTION_QUALITY, null, InputOption::VALUE_OPTIONAL, 'Image quality', '75')
            ->addOption(self::OPTION_FORMAT, null, InputOption::VALUE_OPTIONAL, 'Image format', 'webp')
            ->addOption(self::OPTION_RESIZE, null, InputOption::VALUE_OPTIONAL, 'Resize mode', 'fit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Throwable) {
        }

        $source = trim((string)$input->getOption(self::OPTION_SOURCE));
        if ($source === '') {
            $output->writeln('<error>--source is required</error>');
            return 1;
        }

        $source = $this->normalizeSource($source);
        $kid = $this->dataHelper->getCloudName();
        $width = max(0, (int)$input->getOption(self::OPTION_WIDTH));
        $height = max(0, (int)$input->getOption(self::OPTION_HEIGHT));
        $quality = max(1, min(100, (int)$input->getOption(self::OPTION_QUALITY)));
        $format = $this->normalizeFormat((string)$input->getOption(self::OPTION_FORMAT));
        $resize = $this->normalizeResize((string)$input->getOption(self::OPTION_RESIZE));

        $payload = sprintf(
            'v=1|t=img|k=%s|w=%d|h=%d|q=%d|f=%s|rs=%s|%s',
            $kid,
            $width,
            $height,
            $quality,
            $format,
            $resize,
            $source
        );
        $signature = $this->dataHelper->signPayload($payload);

        $output->writeln('Enabled: ' . ($this->dataHelper->isEnabled() ? 'yes' : 'no'));
        $output->writeln('Configured: ' . ($this->dataHelper->isConfigured() ? 'yes' : 'no'));
        $output->writeln('Cloud Name: ' . $kid);
        $output->writeln('Secret Key length: ' . strlen($this->dataHelper->getSecretKey()));
        $output->writeln('Secret Key fingerprint: ' . $this->fingerprint($this->dataHelper->getSecretKey()));
        $output->writeln('Secret Pin length: ' . strlen($this->dataHelper->getSecretPin()));
        $output->writeln('Secret Pin fingerprint: ' . $this->fingerprint($this->dataHelper->getSecretPin()));
        $output->writeln('Encrypt Key length: ' . strlen($this->dataHelper->getEncryptKey()));
        $output->writeln('Encrypt Key fingerprint: ' . $this->fingerprint($this->dataHelper->getEncryptKey()));
        $output->writeln('Payload: ' . $payload);
        $output->writeln('Signature: ' . $signature);
        $output->writeln('URL: ' . $this->dataHelper->getWebpixHost() . '/img/k_' . $kid . '/s1_' . $signature . '/' . $this->buildParams($width, $height, $quality, $format, $resize) . '/' . $source);

        return 0;
    }

    private function normalizeSource(string $source): string
    {
        if (preg_match('#^https?://#i', $source)) {
            $parts = parse_url($source);
            if ($parts !== false && !empty($parts['host']) && !empty($parts['path'])) {
                return trim((string)$parts['host']) . '/' . ltrim((string)$parts['path'], '/');
            }
        }

        return ltrim($source, '/');
    }

    private function normalizeFormat(string $format): string
    {
        return match (strtolower(trim($format))) {
            'jpg', 'jpeg', 'jpe', 'jfif', 'jif', 'jfi' => 'jpeg',
            'png' => 'png',
            'avif' => 'avif',
            'heif', 'heic' => 'heif',
            'gif' => 'gif',
            'tif', 'tiff' => 'tiff',
            default => 'webp',
        };
    }

    private function normalizeResize(string $resize): string
    {
        return match (strtolower(trim($resize))) {
            'fill' => 'fill',
            default => 'fit',
        };
    }

    private function buildParams(int $width, int $height, int $quality, string $format, string $resize): string
    {
        $params = [];
        if ($width > 0) {
            $params[] = 'w_' . $width;
        }
        if ($height > 0) {
            $params[] = 'h_' . $height;
        }
        $params[] = 'q_' . $quality;
        $params[] = 'f_' . $format;
        $params[] = 'rs_' . $resize;

        return implode(',', $params);
    }

    private function fingerprint(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        return substr(hash('sha256', $value), 0, 12);
    }
}
