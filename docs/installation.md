# Installation

## 1. Require The Package

```bash
composer require oxhq/oxcribe
php artisan vendor:publish --tag=oxcribe-config
```

## 2. Build `oxinfer`

`oxcribe` shells out to a local `oxinfer` binary. `oxinfer` currently needs `GOEXPERIMENT=jsonv2` for both build and tests.

```bash
GOEXPERIMENT=jsonv2 go build -o oxinfer ./cmd/oxinfer
GOEXPERIMENT=jsonv2 go test ./...
```

## 3. Point `oxcribe` At The Binary

```env
OXINFER_BINARY=/absolute/path/to/oxinfer
OXINFER_WORKING_DIRECTORY=/absolute/path/to/your/laravel/app
OXINFER_TIMEOUT=120
```

If the binary is already on `PATH`, `OXINFER_BINARY` can stay as `oxinfer`.
`oxcribe` also looks for `bin/oxinfer` inside the Laravel app working directory before failing.

## 4. Run Analysis

```bash
php artisan oxcribe:analyze --pretty
php artisan oxcribe:export-openapi --pretty
php artisan oxcribe:publish --publish-version=dev
```

Use `--write=/absolute/path.json` when you want stable artifacts on disk.

## 5. Enable The Docs Endpoints

`oxcribe` exposes JSON endpoints that can be consumed by any local or hosted frontend.
Enable them in config:

```php
// config/oxcribe.php
'docs' => [
    'enabled' => true,
    'route' => 'oxcribe/docs',
    'openapi_route' => 'oxcribe/openapi.json',
    'payload_route' => 'oxcribe/docs/payload.json',
],
```

Routes provided by the package:

- `GET /oxcribe/docs`
- `GET /oxcribe/openapi.json`
- `GET /oxcribe/docs/payload.json`

The first serves the package-owned local Vue viewer.
The second returns the canonical OpenAPI document.
The third returns the normalized `oxcribe.docs.v1` payload for a richer docs frontend such as `oxcloud`.

## 6. Publish To Oxcloud

Set these environment variables in the Laravel app:

```env
OXCLOUD_BASE_URL=https://oxcloud.example.test
OXCLOUD_TOKEN=your-project-publish-token
OXCLOUD_TIMEOUT=30
OXCLOUD_DEFAULT_VERSION=dev
```

Then publish the current versioned docs payload:

```bash
php artisan oxcribe:publish
php artisan oxcribe:publish --publish-version=2026.03.25
```
