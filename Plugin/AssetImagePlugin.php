<?php
declare(strict_types=1);

namespace Webpix\Optimizer\Plugin;

use Magento\Catalog\Model\View\Asset\Image as AssetImage;
use Webpix\Optimizer\Helper\Data;
use Webpix\Optimizer\Helper\Image;

class AssetImagePlugin
{
    private Data $helper;
    private Image $imageHelper;

    public function __construct(Data $helper, Image $imageHelper)
    {
        $this->helper = $helper;
        $this->imageHelper = $imageHelper;
    }

    public function afterGetUrl(AssetImage $subject, string $result): string
    {
        if (!$this->helper->isConfigured() || !$this->helper->isProductEnabled()) {
            return $result;
        }

        return $this->imageHelper->replaceAssetImageUrl($subject, $result);
    }
}
