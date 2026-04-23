# Changelog

## v0.1.4

- correct the preview release after the `v0.1.3` remote CI-only failures
- align the package default tag, docs, and install guidance with the current coordinated engine release
- harden the test harness for CI source-build environments and slow cold cargo builds

## v0.1.3

- correct the public preview release after the matching `oxinfer` CI harness fix
- point install and publish docs at the current `oxinfer` preview tag
- keep the end-user package docs trimmed to shipped behavior and supported preview limits

## v0.1.2

- switch `oxinfer` build and install docs from the old Go path to the current Rust release flow
- add source-backed `oxcribe:install-binary` fallback via `OXINFER_SOURCE_ROOT`
- improve `oxcribe:doctor` guidance and Windows binary resolution
- harden OpenAPI generation for response overlays, nested resource refs, and richer field metadata
- normalize override-source paths and improve cross-platform test helpers
- refresh public docs for preview users and remove internal validation workflow references

## v0.1.1

- add a source-backed `oxcribe:install-binary` fallback via `OXINFER_SOURCE_ROOT`
- improve `oxcribe:doctor` guidance when a local `oxinfer` checkout is configured
- document the binary release contract expected from `oxinfer`

## v0.1.0

- freeze the OSS package contracts around `oxcribe.oxinfer.v2` and `oxcribe.docs.v1`
- keep the package-owned local docs viewer at `/oxcribe/docs`
- expose stable JSON contracts at `/oxcribe/openapi.json` and `/oxcribe/docs/payload.json`
- add deterministic examples, snippets, and basic local `try it`
- add `php artisan oxcribe:publish` with `oxcloud.publish.v1`
- move advanced hosted UI, workspaces, and versioned docs evolution to `oxcloud`
