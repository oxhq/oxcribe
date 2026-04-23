# Troubleshooting

## Start With `oxcribe:doctor`

For the first publish path, run:

```bash
php artisan oxcribe:doctor
```

That preflight checks:

- the Laravel project root it is about to inspect
- whether `composer.json` exists there
- whether `oxcribe` can resolve and execute the `oxinfer` binary
- whether local docs are enabled
- whether `OXCLOUD_BASE_URL` and `OXCLOUD_TOKEN` are configured for publish

If you only care about local analysis and OpenAPI output, skip the cloud checks:

```bash
php artisan oxcribe:doctor --skip-cloud
```

## Common First-Publish Failures

### `Unable to find the oxinfer binary`

Fast path:

```bash
php artisan oxcribe:install-binary v0.1.4
```

If the tagged GitHub release is missing binary assets or `checksums.txt`, switch to a source-backed install:

```bash
php artisan oxcribe:install-binary v0.1.4 --source-root=/absolute/path/to/oxinfer --prefer-source
```

Manual fallback:

```bash
cargo build --locked --release
```

Then either:

- put the binary on `PATH`
- place it at `bin/oxinfer` inside the Laravel app
- or point `OXINFER_BINARY` to the executable path

If you keep `oxinfer` checked out locally, set `OXINFER_SOURCE_ROOT` so future `oxcribe:install-binary` runs can build from source automatically when a release is incomplete.

### Publish token or cloud URL is missing

Set:

```env
OXCLOUD_BASE_URL=https://your-oxcloud-host
OXCLOUD_TOKEN=your-project-publish-token
```

Then rerun:

```bash
php artisan oxcribe:doctor
php artisan oxcribe:publish --publish-version=dev
```

### Project root is wrong

If `oxcribe` is pointed at the wrong Laravel app, override it directly:

```bash
php artisan oxcribe:doctor --project-root=/absolute/path/to/app
php artisan oxcribe:publish --project-root=/absolute/path/to/app --publish-version=dev
```

### Local docs are disabled

If you want the package-owned viewer locally, enable:

```env
OXCRIBE_DOCS_ENABLED=true
```

Then use:

- `GET /oxcribe/docs`
- `GET /oxcribe/openapi.json`
- `GET /oxcribe/docs/payload.json`

## After Setup Is Green

Once `doctor`, `analyze`, and `export-openapi` are working on your app, publish a preview version and inspect the hosted docs and OpenAPI output before broad rollout:

```bash
php artisan oxcribe:publish --publish-version=preview
```
