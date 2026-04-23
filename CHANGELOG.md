# Changelog

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
