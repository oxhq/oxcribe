# Release Checklist

## Before Tagging

- `composer validate --strict`
- `./vendor/bin/pest`
- `cargo test --locked` in `oxinfer`
- `cargo build --locked --release` in `oxinfer`
- verify `OXINFER_BINARY` install instructions still match the current `oxinfer` build
- verify the GitHub Actions matrix passes for Laravel `10`, `11`, `12` and `13`

## Package Metadata

- keep `composer.json` without a hardcoded `version`
- update `CHANGELOG.md`
- make sure docs mention current limitations and supported stacks
- verify `oxcribe:install-binary` still works against the tagged `oxinfer` release and against a local `OXINFER_SOURCE_ROOT` checkout

## Binary Contract

`oxcribe:install-binary` expects the tagged `oxinfer` release to expose:

- platform binaries named `oxinfer_<tag>_<os>_<arch>[.exe]`
- a `checksums.txt` file in the same release

If a release is missing those assets, the supported fallback is to configure `OXINFER_SOURCE_ROOT` and build from source locally.

## Real App Smoke

- install `oxhq/oxcribe` in at least one real Laravel app
- publish config
- run `php artisan oxcribe:analyze --pretty`
- run `php artisan oxcribe:export-openapi --pretty`
- inspect output for runtime auth, request/response overlays and package-specific enrichments
- if you do not have an external app yet, rerun the owned-app smoke before tagging and clearly label the release as a preview
