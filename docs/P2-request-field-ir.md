# P2: consume richer request-field IR in OpenAPI

Status: active. `oxinfer` now emits `delta.controllers[].request.fields`; `oxcribe` should prefer that overlay when building OpenAPI and keep the legacy shape tree as fallback.

## Goal

Teach `oxcribe` to consume `request.fields` as the authoritative metadata overlay on top of the existing `body`, `query`, and `files` trees.

## Scope

- Read `required`, `nullable`, `scalarType`, `format`, `isArray`, `collection`, `allowedValues`, `source`, and `via`.
- Prefer `request.fields` when present, but preserve `body/query/files` as the structural fallback.
- Apply that metadata in `src/OpenApi/OpenApiDocumentFactory.php` without regressing existing deterministic output.

## First Targets

- `laravel-data`
  - project `required` vs `optional`
  - project `nullable`
  - preserve wrappers like `Optional` and `Lazy` in `x-oxcribe` if useful
- `laravel-query-builder`
  - consume `allowedValues` from `request.fields`
  - model grouped `fields.*` params without recomputing values from the shape tree
- `laravel-medialibrary`
  - consume file collection metadata from `request.fields`

## Residual Follow-Ups

- Verify `request.fields` never diverge from the legacy shape tree for nested arrays and collection items.
- Add fixture coverage for `required` and `nullable` so `oxcribe` can safely emit stricter schemas.
- Decide whether wrapper metadata belongs in public OpenAPI extensions or stays internal.
- Keep a deterministic merge order when both overlay and fallback provide the same logical field.
- Remove shape-tree only logic only after fixture parity is proven across the Spatie matrix.

## Guardrails

- Do not remove the current shape-tree code path until fixture coverage proves parity.
- Keep OpenAPI output deterministic for the same `AnalysisResponse`.
- Prefer additive `x-oxcribe` metadata before changing public OpenAPI semantics where uncertainty remains.
