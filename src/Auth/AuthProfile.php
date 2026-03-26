<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Auth;

final readonly class AuthProfile
{
    /**
     * @param  list<AuthMiddlewareMatch>  $authenticationMatches
     * @param  list<AuthMiddlewareMatch>  $authorizationMatches
     * @param  list<AuthMiddlewareMatch>  $runtimeMatches
     * @param  list<string>  $guardCandidates
     * @param  list<string>  $schemeCandidates
     */
    public function __construct(
        public bool $requiresAuthentication,
        public bool $requiresAuthorization,
        public array $authenticationMatches,
        public array $authorizationMatches,
        public array $runtimeMatches,
        public array $guardCandidates,
        public array $schemeCandidates,
        public ?string $defaultScheme,
    ) {}

    /**
     * @param  list<string>  $middleware
     * @param  array<string, mixed>  $config
     */
    public static function fromMiddleware(array $middleware, array $config = []): self
    {
        $securityConfig = (array) ($config['openapi']['security'] ?? []);
        $authConfig = (array) ($config['auth'] ?? []);

        $defaultScheme = isset($authConfig['default_scheme'])
            ? (string) $authConfig['default_scheme']
            : (isset($securityConfig['default_scheme']) ? (string) $securityConfig['default_scheme'] : null);

        $middlewareSchemeMap = self::mergeSchemeMaps(
            (array) ($securityConfig['middleware'] ?? []),
            (array) ($authConfig['middleware_schemes'] ?? []),
        );
        $guardSchemeMap = self::normalizeSchemeMap((array) ($authConfig['guard_schemes'] ?? []));
        $guardAliases = self::normalizeAliases((array) ($authConfig['guard_aliases'] ?? []));

        $authenticationMatches = [];
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $match = self::parseAuthenticationMiddleware(
                $entry,
                $middlewareSchemeMap,
                $guardSchemeMap,
                $guardAliases,
                $defaultScheme,
            );

            if ($match !== null) {
                $authenticationMatches[] = $match;
            }
        }

        $authenticationSchemeCandidates = self::collectSchemeCandidates($authenticationMatches);

        $authorizationMatches = [];
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $match = self::parseAuthorizationMiddleware(
                $entry,
                $guardSchemeMap,
                $guardAliases,
                $authenticationSchemeCandidates,
                $defaultScheme,
            );

            if ($match !== null) {
                $authorizationMatches[] = $match;
            }
        }

        $runtimeMatches = [];
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $match = self::parseRuntimeMiddleware(
                $entry,
                $authenticationSchemeCandidates,
                $defaultScheme,
            );

            if ($match !== null) {
                $runtimeMatches[] = $match;
            }
        }

        $guardCandidates = self::uniqueStrings(array_merge(
            self::collectGuards($authenticationMatches),
            self::collectGuards($authorizationMatches),
        ));

        $schemeCandidates = self::uniqueStrings(array_merge(
            $authenticationSchemeCandidates,
            self::collectSchemeCandidates($authorizationMatches),
            self::collectSchemeCandidates($runtimeMatches),
        ));

        if ($schemeCandidates === [] && ($authenticationMatches !== [] || $authorizationMatches !== [] || self::runtimeRequiresAuthentication($runtimeMatches)) && $defaultScheme !== null) {
            $schemeCandidates = [$defaultScheme];
        }

        return new self(
            requiresAuthentication: $authenticationMatches !== [] || $authorizationMatches !== [] || self::runtimeRequiresAuthentication($runtimeMatches),
            requiresAuthorization: $authorizationMatches !== [],
            authenticationMatches: $authenticationMatches,
            authorizationMatches: $authorizationMatches,
            runtimeMatches: $runtimeMatches,
            guardCandidates: $guardCandidates,
            schemeCandidates: $schemeCandidates,
            defaultScheme: $defaultScheme,
        );
    }

    /**
     * @return list<string>
     */
    public function authenticationMiddleware(): array
    {
        return array_values(array_map(
            static fn (AuthMiddlewareMatch $match): string => $match->source,
            $this->authenticationMatches,
        ));
    }

    /**
     * @return list<string>
     */
    public function authorizationMiddleware(): array
    {
        return array_values(array_map(
            static fn (AuthMiddlewareMatch $match): string => $match->source,
            $this->authorizationMatches,
        ));
    }

    /**
     * @return list<string>
     */
    public function runtimeMiddleware(): array
    {
        return array_values(array_map(
            static fn (AuthMiddlewareMatch $match): string => $match->source,
            $this->runtimeMatches,
        ));
    }

    /**
     * @return list<array{kind: string, values: list<string>, guard: ?string, guards: list<string>, schemeCandidates: list<string>, source: string, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}>
     */
    public function authorizationConstraints(): array
    {
        return array_values(array_map(
            static function (AuthMiddlewareMatch $match): array {
                $constraint = [
                    'kind' => $match->kind,
                    'values' => array_values($match->values),
                    'guard' => $match->guards[0] ?? null,
                    'guards' => array_values($match->guards),
                    'schemeCandidates' => array_values($match->schemeCandidates),
                    'source' => $match->source,
                    'subject' => $match->subject,
                    'ability' => $match->ability,
                    'resolution' => $match->resolution,
                ];

                if ($match->metadata !== []) {
                    $constraint['metadata'] = $match->metadata;
                }

                return $constraint;
            },
            $this->authorizationMatches,
        ));
    }

    /**
     * @return list<array{kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, source: string, resolution: string, metadata?: array<string, mixed>}>
     */
    public function runtimeConstraints(): array
    {
        return array_values(array_map(
            static fn (AuthMiddlewareMatch $match): array => array_filter([
                'kind' => $match->kind,
                'values' => array_values($match->values),
                'guards' => array_values($match->guards),
                'schemeCandidates' => array_values($match->schemeCandidates),
                'source' => $match->source,
                'resolution' => $match->resolution,
                'metadata' => $match->metadata !== [] ? $match->metadata : null,
            ], static fn (mixed $value): bool => $value !== null),
            $this->runtimeMatches,
        ));
    }

    public function requiresVerifiedUser(): bool
    {
        return $this->hasRuntimeKind('verified');
    }

    public function requiresPasswordConfirmation(): bool
    {
        return $this->hasRuntimeKind('password_confirm');
    }

    public function requiresSignedUrls(): bool
    {
        return $this->hasRuntimeKind('signed');
    }

    /**
     * @return list<array{source: string, values: list<string>, metadata?: array<string, mixed>}>
     */
    public function throttleConstraints(): array
    {
        $constraints = [];
        foreach ($this->runtimeMatches as $match) {
            if ($match->kind !== 'throttle') {
                continue;
            }

            $constraints[] = array_filter([
                'source' => $match->source,
                'values' => array_values($match->values),
                'metadata' => $match->metadata !== [] ? $match->metadata : null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $constraints;
    }

    /**
     * @return list<array<string, list<string>>>
     */
    public function securityRequirements(): array
    {
        return array_values(array_map(
            static fn (string $scheme): array => [$scheme => []],
            $this->schemeCandidates,
        ));
    }

    /**
     * @return array{
     *     requiresAuthentication: bool,
     *     requiresAuthorization: bool,
     *     defaultScheme: ?string,
     *     guardCandidates: list<string>,
     *     schemeCandidates: list<string>,
     *     securityRequirements: list<array<string, list<string>>>,
     *     authenticationMiddleware: list<array{source: string, category: string, kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}>,
     *     authorizationMiddleware: list<array{source: string, category: string, kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}>,
     *     runtimeMiddleware: list<array{source: string, category: string, kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}>,
     *     authorizationConstraints: list<array{kind: string, values: list<string>, guard: ?string, guards: list<string>, schemeCandidates: list<string>, source: string, subject?: ?string, ability?: ?string, resolution: string, metadata?: array<string, mixed>}>,
     *     runtimeConstraints: list<array{kind: string, values: list<string>, guards: list<string>, schemeCandidates: list<string>, source: string, resolution: string, metadata?: array<string, mixed>}>,
     *     requiresVerifiedUser: bool,
     *     requiresPasswordConfirmation: bool,
     *     requiresSignedUrls: bool,
     *     throttleConstraints: list<array{source: string, values: list<string>, metadata?: array<string, mixed>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'requiresAuthentication' => $this->requiresAuthentication,
            'requiresAuthorization' => $this->requiresAuthorization,
            'defaultScheme' => $this->defaultScheme,
            'guardCandidates' => array_values($this->guardCandidates),
            'schemeCandidates' => array_values($this->schemeCandidates),
            'securityRequirements' => $this->securityRequirements(),
            'authenticationMiddleware' => array_map(
                static fn (AuthMiddlewareMatch $match): array => $match->toArray(),
                $this->authenticationMatches,
            ),
            'authorizationMiddleware' => array_map(
                static fn (AuthMiddlewareMatch $match): array => $match->toArray(),
                $this->authorizationMatches,
            ),
            'runtimeMiddleware' => array_map(
                static fn (AuthMiddlewareMatch $match): array => $match->toArray(),
                $this->runtimeMatches,
            ),
            'authorizationConstraints' => $this->authorizationConstraints(),
            'runtimeConstraints' => $this->runtimeConstraints(),
            'requiresVerifiedUser' => $this->requiresVerifiedUser(),
            'requiresPasswordConfirmation' => $this->requiresPasswordConfirmation(),
            'requiresSignedUrls' => $this->requiresSignedUrls(),
            'throttleConstraints' => $this->throttleConstraints(),
        ];
    }

    /**
     * @param  list<AuthMiddlewareMatch>  $matches
     * @return list<string>
     */
    private static function collectGuards(array $matches): array
    {
        $guards = [];
        foreach ($matches as $match) {
            foreach ($match->guards as $guard) {
                $guards[] = $guard;
            }
        }

        return self::uniqueStrings($guards);
    }

    /**
     * @param  list<AuthMiddlewareMatch>  $matches
     * @return list<string>
     */
    private static function collectSchemeCandidates(array $matches): array
    {
        $schemes = [];
        foreach ($matches as $match) {
            foreach ($match->schemeCandidates as $scheme) {
                $schemes[] = $scheme;
            }
        }

        return self::uniqueStrings($schemes);
    }

    /**
     * @param  array<string, list<string>>  $primary
     * @param  array<string, list<string>>  $secondary
     * @return array<string, list<string>>
     */
    private static function mergeSchemeMaps(array $primary, array $secondary): array
    {
        $merged = self::normalizeSchemeMap($primary);
        foreach (self::normalizeSchemeMap($secondary) as $key => $schemes) {
            $merged[$key] = self::uniqueStrings(array_merge($merged[$key] ?? [], $schemes));
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, list<string>>
     */
    private static function normalizeSchemeMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $key => $schemes) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $schemeList = is_array($schemes) ? $schemes : [$schemes];
            $values = [];
            foreach ($schemeList as $scheme) {
                if (is_string($scheme) && $scheme !== '') {
                    $values[] = $scheme;
                }
            }

            $normalized[$key] = self::uniqueStrings($values);
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $aliases
     * @return array<string, string>
     */
    private static function normalizeAliases(array $aliases): array
    {
        $normalized = [];
        foreach ($aliases as $alias => $target) {
            if (! is_string($alias) || ! is_string($target) || $alias === '' || $target === '') {
                continue;
            }

            $normalized[$alias] = $target;
        }

        return $normalized;
    }

    private static function isAuthenticationMiddleware(string $middleware): bool
    {
        return $middleware === 'auth'
            || str_starts_with($middleware, 'auth:')
            || $middleware === 'auth.basic'
            || $middleware === 'auth.basic.once'
            || $middleware === 'auth.session';
    }

    /**
     * @param  array<string, list<string>>  $middlewareSchemeMap
     * @param  array<string, list<string>>  $guardSchemeMap
     * @param  array<string, string>  $guardAliases
     */
    private static function parseAuthenticationMiddleware(
        string $middleware,
        array $middlewareSchemeMap,
        array $guardSchemeMap,
        array $guardAliases,
        ?string $defaultScheme,
    ): ?AuthMiddlewareMatch {
        if (! self::isAuthenticationMiddleware($middleware)) {
            return null;
        }

        $guards = [];
        $schemeCandidates = [];
        $resolution = 'direct';
        $kind = 'auth';

        if ($middleware === 'auth.basic' || $middleware === 'auth.basic.once') {
            $kind = $middleware;
            $schemeCandidates = ['basicAuth'];
        } elseif ($middleware === 'auth.session') {
            $kind = $middleware;
            $schemeCandidates = ['cookieAuth'];
        } elseif (array_key_exists($middleware, $middlewareSchemeMap)) {
            $schemeCandidates = $middlewareSchemeMap[$middleware];
        }

        $raw = null;
        if ($middleware !== 'auth' && str_starts_with($middleware, 'auth:')) {
            $raw = substr($middleware, strlen('auth:'));
        }

        if ($raw !== null) {
            $guards = self::parseGuardList($raw, $guardAliases);
        }

        if ($schemeCandidates === [] && $guards !== []) {
            $schemeCandidates = self::schemeCandidatesForGuards($guards, $guardSchemeMap);
            $resolution = $schemeCandidates === [] ? 'default' : 'guard';
        }

        if ($schemeCandidates === [] && $defaultScheme !== null) {
            $schemeCandidates = [$defaultScheme];
            $resolution = 'default';
        }

        return new AuthMiddlewareMatch(
            source: $middleware,
            category: 'authentication',
            kind: $kind,
            values: $guards,
            guards: $guards,
            schemeCandidates: self::uniqueStrings($schemeCandidates),
            resolution: $resolution,
        );
    }

    /**
     * @param  array<string, list<string>>  $guardSchemeMap
     * @param  array<string, string>  $guardAliases
     * @param  list<string>  $authenticationSchemeCandidates
     */
    private static function parseAuthorizationMiddleware(
        string $middleware,
        array $guardSchemeMap,
        array $guardAliases,
        array $authenticationSchemeCandidates,
        ?string $defaultScheme,
    ): ?AuthMiddlewareMatch {
        $kind = null;
        $raw = null;

        if (str_starts_with($middleware, 'role_or_permission:')) {
            $kind = 'role_or_permission';
            $raw = substr($middleware, strlen('role_or_permission:'));
        } elseif (str_starts_with($middleware, 'permission:')) {
            $kind = 'permission';
            $raw = substr($middleware, strlen('permission:'));
        } elseif (str_starts_with($middleware, 'role:')) {
            $kind = 'role';
            $raw = substr($middleware, strlen('role:'));
        } elseif (str_starts_with($middleware, 'role_or_permission,')) {
            $kind = 'role_or_permission';
            $raw = substr($middleware, strlen('role_or_permission,'));
        } elseif (str_starts_with($middleware, 'permission,')) {
            $kind = 'permission';
            $raw = substr($middleware, strlen('permission,'));
        } elseif (str_starts_with($middleware, 'role,')) {
            $kind = 'role';
            $raw = substr($middleware, strlen('role,'));
        } elseif (str_starts_with($middleware, 'can:')) {
            $kind = 'can';
            $raw = substr($middleware, strlen('can:'));
        } elseif (str_starts_with($middleware, 'can,')) {
            $kind = 'can';
            $raw = substr($middleware, strlen('can,'));
        } elseif (str_starts_with($middleware, 'ability:')) {
            $kind = 'ability';
            $raw = substr($middleware, strlen('ability:'));
        } elseif (str_starts_with($middleware, 'ability,')) {
            $kind = 'ability';
            $raw = substr($middleware, strlen('ability,'));
        } elseif (str_starts_with($middleware, 'abilities:')) {
            $kind = 'abilities';
            $raw = substr($middleware, strlen('abilities:'));
        } elseif (str_starts_with($middleware, 'abilities,')) {
            $kind = 'abilities';
            $raw = substr($middleware, strlen('abilities,'));
        }

        if ($kind === null || $raw === null) {
            return null;
        }

        $values = [];
        $guards = [];
        $subject = null;
        $ability = null;
        $schemeCandidates = [];
        $resolution = 'direct';

        if ($kind === 'can') {
            $segments = self::splitSegments($raw);
            $ability = $segments[0] ?? null;
            $subject = $segments[1] ?? null;
            if (is_string($ability) && $ability !== '') {
                $values[] = $ability;
            }
        } elseif ($kind === 'ability' || $kind === 'abilities') {
            $values = self::splitValues($raw);
        } else {
            $segments = self::splitSegments($raw);
            if (count($segments) > 1) {
                $guards = [self::normalizeGuardName((string) array_pop($segments), $guardAliases)];
            }

            foreach ($segments as $segment) {
                $values = array_merge($values, self::splitValues($segment));
            }
        }

        if ($guards !== []) {
            $schemeCandidates = self::schemeCandidatesForGuards($guards, $guardSchemeMap);
            $resolution = 'guard';
        } elseif ($authenticationSchemeCandidates !== []) {
            $schemeCandidates = $authenticationSchemeCandidates;
            $resolution = 'inferred';
        } elseif ($defaultScheme !== null) {
            $schemeCandidates = [$defaultScheme];
            $resolution = 'default';
        }

        return new AuthMiddlewareMatch(
            source: $middleware,
            category: 'authorization',
            kind: $kind,
            values: self::uniqueStrings($values),
            guards: $guards,
            schemeCandidates: self::uniqueStrings($schemeCandidates),
            subject: $subject,
            ability: $ability,
            resolution: $resolution,
        );
    }

    /**
     * @param  list<string>  $authenticationSchemeCandidates
     */
    private static function parseRuntimeMiddleware(
        string $middleware,
        array $authenticationSchemeCandidates,
        ?string $defaultScheme,
    ): ?AuthMiddlewareMatch {
        if ($middleware === 'verified') {
            return new AuthMiddlewareMatch(
                source: $middleware,
                category: 'runtime',
                kind: 'verified',
                values: [],
                guards: [],
                schemeCandidates: $authenticationSchemeCandidates !== [] ? $authenticationSchemeCandidates : ($defaultScheme !== null ? [$defaultScheme] : []),
                resolution: $authenticationSchemeCandidates !== [] ? 'inferred' : ($defaultScheme !== null ? 'default' : 'direct'),
            );
        }

        if ($middleware === 'password.confirm') {
            return new AuthMiddlewareMatch(
                source: $middleware,
                category: 'runtime',
                kind: 'password_confirm',
                values: [],
                guards: [],
                schemeCandidates: $authenticationSchemeCandidates !== [] ? $authenticationSchemeCandidates : ($defaultScheme !== null ? [$defaultScheme] : []),
                resolution: $authenticationSchemeCandidates !== [] ? 'inferred' : ($defaultScheme !== null ? 'default' : 'direct'),
            );
        }

        if ($middleware === 'signed' || str_starts_with($middleware, 'signed:')) {
            $values = $middleware === 'signed'
                ? []
                : self::splitSegments(substr($middleware, strlen('signed:')));

            return new AuthMiddlewareMatch(
                source: $middleware,
                category: 'runtime',
                kind: 'signed',
                values: $values,
                guards: [],
                schemeCandidates: [],
                metadata: array_filter([
                    'mode' => $values[0] ?? null,
                ], static fn (mixed $value): bool => is_string($value) && $value !== ''),
            );
        }

        if ($middleware === 'throttle' || str_starts_with($middleware, 'throttle:')) {
            $values = $middleware === 'throttle'
                ? []
                : self::splitSegments(substr($middleware, strlen('throttle:')));

            return new AuthMiddlewareMatch(
                source: $middleware,
                category: 'runtime',
                kind: 'throttle',
                values: $values,
                guards: [],
                schemeCandidates: [],
                metadata: self::throttleMetadata($values),
            );
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $guardSchemeMap
     * @return list<string>
     */
    private static function schemeCandidatesForGuards(array $guards, array $guardSchemeMap): array
    {
        $schemes = [];
        foreach ($guards as $guard) {
            foreach ($guardSchemeMap[$guard] ?? [] as $scheme) {
                $schemes[] = $scheme;
            }
        }

        return self::uniqueStrings($schemes);
    }

    /**
     * @param  array<string, string>  $guardAliases
     * @return list<string>
     */
    private static function parseGuardList(string $raw, array $guardAliases): array
    {
        $guards = [];
        foreach (self::splitSegments($raw) as $guard) {
            $guards[] = self::normalizeGuardName($guard, $guardAliases);
        }

        return self::uniqueStrings($guards);
    }

    /**
     * @param  array<string, string>  $guardAliases
     */
    private static function normalizeGuardName(string $guard, array $guardAliases): string
    {
        $guard = trim($guard);
        if ($guard === '') {
            return $guard;
        }

        return $guardAliases[$guard] ?? $guard;
    }

    /**
     * @return list<string>
     */
    private static function splitSegments(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $raw),
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return list<string>
     */
    private static function splitValues(string $raw): array
    {
        $values = [];
        foreach (self::splitSegments($raw) as $segment) {
            foreach (array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode('|', $segment),
            ), static fn (string $value): bool => $value !== '')) as $value) {
                $values[] = $value;
            }
        }

        return self::uniqueStrings($values);
    }

    /**
     * @param  list<AuthMiddlewareMatch>  $matches
     */
    private static function runtimeRequiresAuthentication(array $matches): bool
    {
        foreach ($matches as $match) {
            if (in_array($match->kind, ['verified', 'password_confirm'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasRuntimeKind(string $kind): bool
    {
        foreach ($this->runtimeMatches as $match) {
            if ($match->kind === $kind) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    private static function throttleMetadata(array $values): array
    {
        $metadata = [];
        if ($values === []) {
            return $metadata;
        }

        $first = $values[0] ?? null;
        $second = $values[1] ?? null;
        $third = $values[2] ?? null;

        if (is_string($first) && $first !== '') {
            if (ctype_digit($first)) {
                $metadata['maxAttempts'] = (int) $first;
            } else {
                $metadata['limiter'] = $first;
            }
        }

        if (is_string($second) && $second !== '') {
            if (ctype_digit($second)) {
                $metadata['decayMinutes'] = (int) $second;
            } else {
                $metadata['segment'] = $second;
            }
        }

        if (is_string($third) && $third !== '') {
            $metadata['prefix'] = $third;
        }

        return $metadata;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if ($value === '' || in_array($value, $unique, true)) {
                continue;
            }

            $unique[] = $value;
        }

        return $unique;
    }
}
