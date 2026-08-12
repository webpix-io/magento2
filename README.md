# WebPix Optimizer for Magento 2

WebPix Optimizer routes Magento 2 storefront assets through WebPix CDN for image conversion, compression, resizing, SVG optimization, CSS/JS delivery and Google Fonts display control.

## Installation

```bash
composer require webpix-io/module-optimizer
php bin/magento module:enable Webpix_Optimizer
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production mode:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

## Configuration

Open Magento admin:

```text
Stores > Configuration > WebPix > Settings
```

Set your WebPix subscription values:

```text
Cloud Name
Secret Key
Secret Pin
Encrypt Key
```

Then enable the optimization groups you need:

```text
Images
CSS
JS
SVG
Fonts
```

Use `CDN Mode = Yes` only when you have configured a custom WebPix CDN hostname.
