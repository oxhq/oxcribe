# Compatibility And Fixtures

## Supported Compatibility Matrix

- Laravel `10` with Testbench `8`
- Laravel `11` with Testbench `9`
- Laravel `12` with Testbench `10`
- Laravel `13` with Testbench `11`

Package CI is intended to run the same Pest suite across that matrix.

## Runtime/Static Split

- runtime auth, middleware, guards, throttles and route registration live in `oxcribe`
- static request/response/resource/package inference lives in `oxinfer`
- OpenAPI `security` comes from runtime auth, not static authorization hints
- static authorization hints are emitted as `x-oxcribe.authorizationStatic`
- smart example synthesis belongs in `oxcribe`; `oxinfer` should only provide additive semantic metadata

The current examples design note lives in [smart-examples-v1.md](smart-examples-v1.md).

## Hostile Fixture Apps

The package suite keeps real fixture apps instead of mocking the contract:

- `SpatieLaravelApp`
- `InertiaLaravelApp`
- `AuthErrorLaravelApp`
- `PolicyLaravelApp`

Each fixture is expected to pass both:

- `oxcribe:analyze`
- `oxcribe:export-openapi`

## Current Non-Goals

- Livewire
- non-Laravel frameworks
- deriving OpenAPI `security` from static authorization calls alone
