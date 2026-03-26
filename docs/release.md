# Release Checklist

## Before Tagging

- `composer validate --strict`
- `./vendor/bin/pest`
- `GOEXPERIMENT=jsonv2 go test ./...` in `go/oxinfer`
- verify `OXINFER_BINARY` install instructions still match the current `oxinfer` build
- verify the GitHub Actions matrix passes for Laravel `10`, `11`, `12` and `13`

## Package Metadata

- keep `composer.json` without a hardcoded `version`
- update `CHANGELOG.md`
- make sure docs mention current limitations and supported stacks

## Real App Smoke

- install `oxhq/oxcribe` in at least one external Laravel app
- publish config
- run `php artisan oxcribe:analyze --pretty`
- run `php artisan oxcribe:export-openapi --pretty`
- inspect output for runtime auth, request/response overlays and package-specific enrichments
