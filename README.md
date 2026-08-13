# WebPix Optimizer for Magento 2

Speed up Magento 2 storefronts with automatic image optimization, modern image conversion, CDN delivery, responsive catalog images, SVG optimization, CSS/JS delivery and Google Fonts display control.

WebPix Optimizer is a Magento 2 performance module that connects your store to the WebPix CDN. It helps reduce page weight, improve Core Web Vitals, optimize LCP images and deliver storefront assets in modern formats such as WebP and AVIF without manually changing every template.

Website: https://webpix.io

Magento integration docs: https://webpix.io/integrations/magento

## Why use WebPix Optimizer?

Magento stores often ship heavy product images, CMS banners, theme assets and third-party font files. This increases page weight, slows down category and product pages, hurts mobile performance and can reduce conversion rates.

WebPix Optimizer solves this at the delivery layer:

- Converts Magento images to modern formats such as WebP or AVIF.
- Compresses catalog, product, gallery and CMS images.
- Resizes images on delivery instead of serving oversized originals.
- Adds responsive image `srcset` for large optimized catalog images.
- Helps improve LCP by prioritizing important listing images.
- Routes SVG files through the WebPix SVG optimization endpoint.
- Can route CSS and JavaScript assets through WebPix CDN.
- Controls Google Fonts display strategy to reduce render delay and layout shifts.
- Supports the default WebPix CDN or your own custom CDN hostname.
- Supports signed and encrypted WebPix delivery URLs.

## Best for

- Magento 2 stores with large product catalogs.
- Stores that need better mobile speed and lower image weight.
- Teams working on Core Web Vitals, LCP and frontend performance.
- Magento projects that want image CDN benefits without rewriting theme templates.
- Agencies that need repeatable image optimization setup for client stores.

## Features

### Image optimization

- Product image optimization.
- CMS image optimization.
- Configurable quality.
- Separate product image quality.
- Output format selection: WebP, AVIF or JPG.
- Resize modes: default, fit, fill and auto.
- Responsive gallery `srcset`.
- Listing LCP image optimization.

### Asset optimization

- SVG optimization through the `/svg` endpoint.
- CSS delivery through the `/css` endpoint.
- JavaScript delivery through the `/js` endpoint.
- Google Fonts display strategy control.
- Custom CDN hostname support.

### Secure delivery

- WebPix Cloud Name configuration.
- Secret Key and Secret Pin for signed URLs.
- Encrypt Key for encrypted URLs.
- Magento encrypted config storage for secret values.

## Requirements

- Magento 2.
- PHP 8.1 or newer.
- WebPix account and subscription credentials.
- Publicly reachable storefront asset URLs.

## Installation

Run from your Magento root directory:

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
Stores > Configuration > WebPix Optimizer > Settings
```

Since version `1.0.2`, WebPix Optimizer uses its own configuration section and config paths under `webpix_optimizer/...`. If you upgraded from an older version, re-enter the WebPix credentials in the new `WebPix Optimizer` section.

Version `1.0.4` improves reading Magento encrypted configuration values when the store uses a non-zero encryption key version.

Enable the module:

```text
General Settings > Enabled = Yes
```

Add your WebPix access values:

```text
Access > Cloud Name
Access > Secret Key
Access > Secret Pin
Access > Encrypt Key
```

Secret values are stored by Magento using encrypted backend config fields.

## CDN mode

Use the default WebPix CDN:

```text
General Settings > CDN Mode = No
```

Use your own custom CDN hostname:

```text
General Settings > CDN Mode = Yes
General Settings > Custom CDN Hostname = media.example.com
```

Enter only the hostname. Do not include `https://`.

## Recommended image settings

Start with:

```text
Image > Secure Image Url = Yes
Image > Quality % = 75
Image > Product Image Quality % = 70
Image > Outgoing Format = Webp
Image > Resize Mode = Default
Image > Enable Product Compression = Yes
Image > Add Missing Image Width and Height = Yes
Image > Enable Responsive Image Srcset = Yes
Image > Enable Listing LCP Image Optimization = Yes
Image > Enable CMS Compression = Yes
Image > Enable SVG Optimization = Yes
```

Use AVIF when your storefront audience and testing confirm that AVIF is the right default. WebP is a safe starting point for broad compatibility.

## CSS, JS and fonts

Enable these after image optimization is confirmed:

```text
CSS > Enable CSS Optimization = Yes
CSS > Secure CSS Url = Yes

JS > Enable JS Optimization = Yes
JS > Secure JS Url = Yes

Fonts > Optimize Google Fonts = Yes
Fonts > Display Strategy = Swap
Fonts > Override Existing Display Param = Yes
```

CSS and JS can be enabled separately. This lets you test image optimization first, then expand delivery optimization to other storefront assets.

## Verify installation

Check that Magento sees the module:

```bash
php bin/magento module:status Webpix_Optimizer
```

Clear cache after changing settings:

```bash
php bin/magento cache:flush
```

Debug signature generation without printing secret values:

```bash
php bin/magento webpix:optimizer:debug-signature \
  --source=webpix.io/media/webpix/sha256.webp \
  --quality=75 \
  --format=webp \
  --resize=fit
```

Open a product, category or CMS page and inspect the HTML. Optimized image URLs should look like:

```text
https://cdn.webpix.io/img/k_your-cloud-name/...
```

With a custom CDN hostname:

```text
https://media.example.com/img/k_your-cloud-name/...
```

Depending on enabled settings, you may also see:

```text
/svg/k_your-cloud-name/...
/css/k_your-cloud-name/...
/js/k_your-cloud-name/...
```

## Disable optimization

Temporarily disable WebPix optimization without removing the module:

```bash
php bin/magento config:set webpix_optimizer/general/enabled 0
php bin/magento cache:flush
```

Enable it again:

```bash
php bin/magento config:set webpix_optimizer/general/enabled 1
php bin/magento cache:flush
```

## Troubleshooting

### WebPix URLs do not appear

- Confirm `General Settings > Enabled = Yes`.
- Flush Magento cache.
- Check that full page cache is not serving old HTML.
- Confirm the current scope: default, website or store view.

### Custom CDN hostname does not work

- Confirm DNS is configured for your WebPix custom domain.
- Enter only the hostname in Magento admin, for example `media.example.com`.
- Do not include `https://` in the custom hostname field.

### Images work but CSS or JS does not

- Enable CSS and JS optimization separately.
- Flush cache after changing settings.
- Check browser console and network responses.
- If needed, disable CSS/JS optimization while keeping image optimization enabled.

### SVG returns an error

- Confirm the original SVG URL is publicly reachable.
- Make sure the origin server allows server-to-server fetches.
- SVG output remains SVG. It is not converted to WebP.

## SEO keywords

Magento 2 image optimization, Magento image CDN, Magento WebP extension, Magento AVIF images, Magento Core Web Vitals, Magento LCP optimization, Magento performance module, Magento responsive images, Magento SVG optimization, Magento CSS optimization, Magento JS optimization, WebPix CDN for Magento.

## Support

WebPix: https://webpix.io

Integration docs: https://webpix.io/integrations/magento/
