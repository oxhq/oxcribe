# P2: richer request-field IR

- Future work should expose a field-level IR that distinguishes scalars, nested objects, collections, and wrapper types like `Optional`/`Lazy`.
- The current Spatie fixture already exercises nested `laravel-data` and query-builder shapes; this note tracks the follow-up gap, not a failing test.
- Keep the contract stable for now; only promote this once the runtime/static merge can carry richer field metadata end-to-end.
