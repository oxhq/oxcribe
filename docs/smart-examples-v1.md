# Smart Examples V1

This document defines the first product-facing examples architecture for `oxcribe`.

The goal is to turn runtime Laravel facts plus static `oxinfer` metadata into:

- request examples
- response examples
- ready-to-run snippets
- a future `try-it` experience

This is intentionally separate from OpenAPI shape generation.
OpenAPI remains the source of contract truth.
The examples engine is a deterministic synthesis layer on top.

## Product Rule

Do not build "faker random".

Build scenario-aware, deterministic examples that feel coherent across:

- field names
- validation constraints
- endpoint purpose
- related request/response fields

## Ownership

### `oxinfer` provides

- request field type and shape
- semantic field hints
- normalized constraints
- response schemas and response hints
- package-aware static context

### `oxcribe` provides

- final operation classification
- example modes
- scenario context
- deterministic values
- request/response reuse
- snippets
- future `try-it`

## New Internal IR

The examples engine should introduce an internal IR that is not the same as OpenAPI.
OpenAPI can be derived from it later, but the examples engine needs richer semantics than plain schema nodes.

### `EndpointExampleContext`

```php
[
    'method' => 'POST',
    'path' => '/api/login',
    'routeName' => 'login.store',
    'actionKey' => 'App\\Http\\Controllers\\Auth\\LoginController::__invoke',
    'operationKind' => 'auth.login',
]
```

Fields:

- `method`
- `path`
- `routeName`
- `actionKey`
- `operationKind`

`operationKind` is resolved in `oxcribe`, not `oxinfer`.

### `ExampleField`

```php
[
    'name' => 'email',
    'path' => 'body.email',
    'location' => 'body',
    'baseType' => 'string',
    'semanticType' => 'email',
    'required' => true,
    'nullable' => false,
    'collection' => false,
    'itemType' => null,
    'constraints' => [
        'minLength' => null,
        'maxLength' => 255,
        'enum' => null,
        'pattern' => null,
        'exists' => null,
        'confirmedWith' => null,
    ],
    'hints' => [
        'confidence' => 0.98,
        'source' => ['field_name', 'validation_rule'],
        'via' => ['rule:email'],
    ],
]
```

### `OperationExampleSpec`

```php
[
    'endpoint' => [...],
    'pathParams' => [...],
    'queryParams' => [...],
    'requestFields' => [...],
    'responseFields' => [...],
    'responseStatuses' => [200, 401, 422],
]
```

This is the normalized operation-level input to the generator.

### `ScenarioContext`

```php
[
    'seed' => 'project:endpoint:mode',
    'mode' => 'happy_path',
    'person' => [
        'firstName' => 'Ana',
        'lastName' => 'Lopez',
        'fullName' => 'Ana Lopez',
        'email' => 'ana.lopez@acme.test',
        'phone' => '+526641234567',
        'username' => 'ana_lopez',
    ],
    'company' => [
        'name' => 'Acme Logistics',
        'email' => 'contact@acme.test',
        'website' => 'https://acme.test',
        'domain' => 'acme.test',
    ],
    'auth' => [
        'password' => 'Str0ng!Pass2026',
        'token' => 'tok_test_8f4a1c29b2',
        'apiKey' => 'oxc_live_3baf9c1d8a',
    ],
]
```

This must be shared across request, response, snippets, and future `try-it`.

### `ExampleSet`

```php
[
    'minimal_valid' => [
        'request' => [...],
        'response' => [...],
    ],
    'happy_path' => [
        'request' => [...],
        'response' => [...],
    ],
    'realistic_full' => [
        'request' => [...],
        'response' => [...],
    ],
]
```

### `SnippetSet`

```php
[
    'curl' => 'curl ...',
    'fetch' => 'fetch(...)',
    'axios' => 'axios({...})',
]
```

## Modes

Three modes should exist from day one:

- `minimal_valid`
- `happy_path`
- `realistic_full`

Rules:

- `minimal_valid`
  - only required fields
  - keep happy-path semantics
- `happy_path`
  - valid, coherent, ready to paste
- `realistic_full`
  - richer objects and optional fields when confidence is high enough

## Determinism

Examples must be stable across builds for the same project and endpoint.

Recommended seed input:

`project_fingerprint + routeId + operationKind + exampleMode`

That seed should drive:

- person fixture
- company fixture
- ids
- dates
- token-ish values
- enum selections

## Classification Pipeline

### 1. Field normalization

Take merged request/response fields and normalize them into `ExampleField`.

Source inputs:

- `request.fields`
- request body/query/file schema overlays
- response `bodySchema`
- resource schemas

### 2. Semantic classification

Resolve `semanticType` using:

1. explicit static metadata from `oxinfer`
2. field name aliases
3. constraints
4. endpoint context

Examples:

- `email` + `format=email` => `email`
- `password` + `confirmedWith=password_confirmation` => `password`
- `user_id` + `exists(users,id)` => `foreign_key_id`
- `name` under `/companies` => `company_name`

### 3. Operation classification

Resolve `operationKind` in `oxcribe`.

Examples:

- `POST /login` => `auth.login`
- `POST /register` => `auth.register`
- `POST /users` => `users.store`
- `PATCH /users/{user}` => `users.update`
- `GET /users` with `data/meta/links` => `index.paginated`

### 4. Scenario context synthesis

Use the deterministic seed and operation kind to build shared fixtures:

- person
- company
- auth
- resource ids

### 5. Example generation

Map each `ExampleField` to a value generator by `semanticType`.

Examples:

- `email` => `ctx.person.email`
- `password` => `ctx.auth.password`
- `company_name` => `ctx.company.name`
- `foreign_key_id` => deterministic integer
- `uuid` => deterministic UUID
- `datetime` => deterministic ISO datetime

### 6. Snippet generation

Build `curl`, `fetch`, and `axios` from:

- method
- URL template
- path/query params
- auth requirements
- request example body

### 7. OpenAPI attachment

Once stable, attach generated examples into OpenAPI under:

- request body `content.*.examples`
- response `content.*.examples`
- `x-oxcribe.examples`
- `x-oxcribe.snippets`

Do not make snippets part of the public machine contract with `oxinfer`.

## Proposed Code Shape

### New namespace

`src/Examples`

Suggested structure:

- `src/Examples/ExampleMode.php`
- `src/Examples/Data/FieldConstraints.php`
- `src/Examples/Data/FieldHints.php`
- `src/Examples/Data/ExampleField.php`
- `src/Examples/Data/EndpointExampleContext.php`
- `src/Examples/Data/OperationExampleSpec.php`
- `src/Examples/Data/ScenarioPerson.php`
- `src/Examples/Data/ScenarioCompany.php`
- `src/Examples/Data/ScenarioAuth.php`
- `src/Examples/Data/ScenarioContext.php`
- `src/Examples/OperationKindResolver.php`
- `src/Examples/FieldClassifier.php`
- `src/Examples/ScenarioContextFactory.php`
- `src/Examples/ValueGenerator.php`
- `src/Examples/OperationExampleGenerator.php`
- `src/Examples/SnippetFactory.php`

## First Implementation Cut

V1 should do only this:

1. classify by name and static rules
2. synthesize deterministic shared context
3. generate request/response examples for `minimal_valid`, `happy_path`, and `realistic_full`
4. emit `curl`, `fetch`, and `axios`

No viewer is required for V1.
No persisted datasets are required for V1.
No remote publish system is required for V1.

## What Not To Do Yet

Do not start with:

- random faker payloads
- heavy UI work
- multi-tenant datasets
- non-deterministic examples
- per-project editable fixtures

Those can come later.
The first sellable version is deterministic smart examples plus snippets.
