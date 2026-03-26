<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Data\MergedOperation;

final class OperationKindResolver
{
    public function resolve(MergedOperation $operation): string
    {
        $method = strtoupper($operation->methods[0] ?? 'GET');
        $routeName = strtolower(trim((string) ($operation->name ?? '')));
        $uri = strtolower(trim($operation->uri, '/'));
        $segments = $this->staticSegments($uri);
        $resource = $this->resourceName($operation, $segments);
        $hasPathParameters = $this->hasPathParameters($operation->uri);

        if ($method === 'POST' && $this->containsAny($routeName, $uri, ['login', 'sign-in', 'signin'])) {
            return 'auth.login';
        }

        if ($method === 'POST' && $this->containsAny($routeName, $uri, ['register', 'sign-up', 'signup'])) {
            return 'auth.register';
        }

        if (in_array($method, ['POST', 'DELETE'], true) && $this->containsAny($routeName, $uri, ['logout', 'signout', 'sign-out'])) {
            return 'auth.logout';
        }

        if ($method === 'GET' && ! $hasPathParameters && $this->looksPaginated($operation)) {
            return 'index.paginated';
        }

        $routeNameAction = $this->actionFromRouteName($routeName);
        if ($routeNameAction !== null && $resource !== null) {
            return $resource.'.'.$routeNameAction;
        }

        if ($resource !== null) {
            if ($method === 'GET' && ! $hasPathParameters) {
                return $resource.'.index';
            }
            if ($method === 'GET' && $hasPathParameters) {
                return $resource.'.show';
            }
            if ($method === 'POST' && ! $hasPathParameters) {
                return $resource.'.store';
            }
            if (in_array($method, ['PUT', 'PATCH'], true) && $hasPathParameters) {
                return $resource.'.update';
            }
            if ($method === 'DELETE' && $hasPathParameters) {
                return $resource.'.destroy';
            }
        }

        return 'generic.'.strtolower($method);
    }

    /**
     * @param  list<string>  $segments
     */
    private function resourceName(MergedOperation $operation, array $segments): ?string
    {
        $routeName = trim((string) ($operation->name ?? ''));
        if ($routeName !== '') {
            $parts = array_values(array_filter(explode('.', strtolower($routeName))));
            if ($parts !== []) {
                $first = $parts[0];
                if (! in_array($first, ['login', 'logout', 'register'], true)) {
                    return $first;
                }
            }
        }

        if ($segments === []) {
            return null;
        }

        return $segments[array_key_last($segments)] ?? null;
    }

    /**
     * @return list<string>
     */
    private function staticSegments(string $uri): array
    {
        $segments = array_values(array_filter(explode('/', $uri), static fn (string $segment): bool => $segment !== ''));
        $segments = array_values(array_filter($segments, static fn (string $segment): bool => ! str_starts_with($segment, '{')));

        if ($segments !== [] && $segments[0] === 'api') {
            array_shift($segments);
        }

        return $segments;
    }

    private function hasPathParameters(string $uri): bool
    {
        return preg_match('/\{[^}]+\}/', $uri) === 1;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $routeName, string $uri, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (($routeName !== '' && str_contains($routeName, $needle)) || ($uri !== '' && str_contains($uri, $needle))) {
                return true;
            }
        }

        return false;
    }

    private function actionFromRouteName(string $routeName): ?string
    {
        if ($routeName === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('.', $routeName)));
        if (count($parts) < 2) {
            return null;
        }

        return $parts[array_key_last($parts)];
    }

    private function looksPaginated(MergedOperation $operation): bool
    {
        foreach ((array) ($operation->controller['responses'] ?? []) as $response) {
            if (! is_array($response)) {
                continue;
            }

            $status = (int) ($response['status'] ?? $operation->controller['http']['status'] ?? 200);
            if ($status < 200 || $status >= 300) {
                continue;
            }

            $schema = (array) ($response['bodySchema'] ?? []);
            $properties = (array) ($schema['properties'] ?? []);
            if (isset($properties['data'], $properties['meta'], $properties['links'])) {
                return true;
            }
        }

        return false;
    }
}
