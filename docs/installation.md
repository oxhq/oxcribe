# Installation

## 1. Require The Package

```bash
composer require oxhq/oxcribe
php artisan vendor:publish --tag=oxcribe-config
```

## 2. Install Or Build `oxinfer`

Fast path:

```bash
php artisan oxcribe:install-binary v0.1.4
```

That downloads the matching release binary from `oxhq/oxinfer`, verifies the published checksum, and installs it into the app-local binary path.

If you have a local `oxinfer` checkout and want a source-backed install path that does not depend on release assets, point `oxcribe` at the checkout and either prefer or force source builds:

```bash
php artisan oxcribe:install-binary v0.1.4 --source-root=/absolute/path/to/oxinfer --prefer-source
```

Or set it once in the environment:

```env
OXINFER_SOURCE_ROOT=/absolute/path/to/oxinfer
```

When `OXINFER_SOURCE_ROOT` is configured, `oxcribe:install-binary` will automatically fall back to building from source if the tagged release is missing binary assets or checksums. Source builds use the checked-out local tree; the requested version remains the preferred release tag for remote downloads.

Manual fallback:

`oxcribe` shells out to a local `oxinfer` binary. `oxinfer` is the Rust analysis engine and can be built directly from the source checkout.

```bash
cargo build --locked --release
cargo test --locked
```

## 3. Point `oxcribe` At The Binary

```env
OXINFER_BINARY=/absolute/path/to/oxinfer
OXINFER_SOURCE_ROOT=/absolute/path/to/oxinfer
OXINFER_WORKING_DIRECTORY=/absolute/path/to/your/laravel/app
OXINFER_TIMEOUT=120
```

If the binary is already on `PATH`, `OXINFER_BINARY` can stay as `oxinfer`.
`oxcribe` also looks for `bin/oxinfer` inside the Laravel app working directory before failing.
`oxcribe:install-binary` writes to that app-local location by default, so you normally do not need an absolute path after running it.

## 4. Run Analysis

```bash
php artisan oxcribe:doctor
php artisan oxcribe:analyze --pretty
php artisan oxcribe:export-openapi --pretty
php artisan oxcribe:publish --publish-version=dev
```

Use `--write=/absolute/path.json` when you want stable artifacts on disk.

The clean first-run path is:

1. Create one workspace and one project in Oxcribe Cloud.
2. Issue a project publish token.
3. Set `OXCLOUD_BASE_URL` and `OXCLOUD_TOKEN` in the Laravel app.
4. Run `php artisan oxcribe:doctor`.
5. Run `php artisan oxcribe:publish --publish-version=<your-version>`.
6. Open the hosted version URL, explorer URL, and changelog URL printed by the command.

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
php artisan oxcribe:doctor
php artisan oxcribe:publish
php artisan oxcribe:publish --publish-version=2026.03.25
```

On success, `oxcribe:publish` prints the exact hosted version URL plus matching explorer and changelog URLs so you can review the published output immediately.

If the preflight fails, start with [docs/troubleshooting.md](troubleshooting.md).
