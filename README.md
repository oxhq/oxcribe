# Oxcribe

`v0.1.0` is the frozen OSS baseline of `oxcribe`.
From this cut forward, `oxcribe` keeps the stable Laravel package contracts and a basic local viewer, while `oxcloud` becomes the home of the advanced hosted UI, workspaces, and versioned collaboration.

`Oxcribe` is a runtime-first Laravel package that boots your app, captures the route graph Laravel actually registered, sends a strict `AnalysisRequest` to `oxinfer`, and merges runtime truth with static analysis before emitting OpenAPI.

## What It Owns

- boots Laravel and snapshots real routes, middleware, bindings and action references
- keeps runtime auth/security as the source of truth for OpenAPI `security`
- merges static controller analysis, resources, request/response overlays and authorization hints from `oxinfer`
- exports an OpenAPI document with `x-oxcribe.*` metadata for runtime and static provenance

## Requirements

- PHP `8.2+`
- Laravel `10`, `11`, `12` or `13`
- a local `oxinfer` binary available on `PATH`, placed at `bin/oxinfer` inside the Laravel app, or configured in `config/oxcribe.php`

`oxinfer` still requires `GOEXPERIMENT=jsonv2` to build and test. Build it like this:

```bash
GOEXPERIMENT=jsonv2 go build -o oxinfer ./cmd/oxinfer
GOEXPERIMENT=jsonv2 go test ./...
```

If `oxinfer` is not on `PATH`, either place it at `bin/oxinfer` in the Laravel app or point `oxcribe.oxinfer.binary` to the built binary.

## Install

```bash
composer require garaekz/oxcribe
php artisan vendor:publish --tag=oxcribe-config
```

## Minimal Config

```php
// config/oxcribe.php
return [
    'oxinfer' => [
        'binary' => env('OXINFER_BINARY', 'oxinfer'),
        'working_directory' => env('OXINFER_WORKING_DIRECTORY'),
        'timeout' => (int) env('OXINFER_TIMEOUT', 120),
    ],
];
```

## Commands

```bash
php artisan oxcribe:analyze
php artisan oxcribe:export-openapi
php artisan oxcribe:publish
```

Both commands support `--write=/absolute/path.json` and `--pretty`.
`oxcribe:publish` pushes the current OpenAPI document and `oxcribe.docs.v1` payload to `oxcloud`.

## Docs Data Endpoints

`oxcribe` exposes stable JSON endpoints. The package owns the data contract; your local app or `oxcloud` can render any viewer on top of it.

Enable docs in `config/oxcribe.php`:

```php
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

`/oxcribe/docs` is a package-owned local Vue viewer, similar in spirit to how Scramble serves its UI from the package itself.
The OpenAPI route is the canonical machine-readable document.
The payload route returns the richer `oxcribe.docs.v1` viewer payload, including generated examples, snippets, runtime metadata, and component schemas.
`oxcloud` can consume that same payload contract and host a more advanced version of the same experience.

## Publish To Oxcloud

Configure the publish target:

```env
OXCLOUD_BASE_URL=https://oxcloud.example.test
OXCLOUD_TOKEN=your-project-publish-token
OXCLOUD_TIMEOUT=30
OXCLOUD_DEFAULT_VERSION=dev
```

Then publish:

```bash
php artisan oxcribe:publish
php artisan oxcribe:publish --publish-version=2026.03.25
```

The command sends:

- `contractVersion = "oxcloud.publish.v1"`
- `version`
- `openapi`
- `docsPayload`
- `source.appName`
- `source.appUrl`
- `source.framework = "laravel"`
- `source.packageVersion = "oxcribe v0.1.0"`

## Overrides

Runtime is the primary source of truth, but `oxcribe` also supports route-level overrides through `.oxcribe.php` or `oxcribe.overrides.php`.

- docs: [docs/overrides.md](docs/overrides.md)
- minimal example: [docs/minimal.oxcribe.php](docs/minimal.oxcribe.php)

## Supported Stacks

- Laravel core request/response/resource patterns
- runtime auth and middleware-derived OpenAPI security
- Inertia transport metadata
- Spatie `laravel-data`, `laravel-query-builder`, `laravel-permission`, `laravel-medialibrary` and `laravel-translatable`

## Current Limits

- `security` is derived from runtime middleware/auth, not from static authorization hints
- static authorization hints are exposed under `x-oxcribe.authorizationStatic`
- Livewire and non-Laravel stacks are out of scope
- `oxinfer` still depends on `jsonv2`, so package integration assumes the binary was built with that experiment enabled
- the local viewer is package-owned and does not depend on publishing frontend stubs into the host app

## Smart Examples

The next product layer for `oxcribe` is deterministic smart examples built from runtime + static analysis.
The design lives in [docs/smart-examples-v1.md](docs/smart-examples-v1.md).

## Package Docs

- installation: [docs/installation.md](docs/installation.md)
- compatibility and fixtures: [docs/compatibility.md](docs/compatibility.md)
- overrides: [docs/overrides.md](docs/overrides.md)
- smart examples: [docs/smart-examples-v1.md](docs/smart-examples-v1.md)
- release checklist: [docs/release.md](docs/release.md)
