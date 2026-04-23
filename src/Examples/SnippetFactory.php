<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Oxhq\Oxcribe\Examples\Data\GeneratedRequestExample;
use Oxhq\Oxcribe\Examples\Data\OperationExampleSpec;
use Oxhq\Oxcribe\Examples\Data\SnippetSet;

final class SnippetFactory
{
    public function make(OperationExampleSpec $spec, GeneratedRequestExample $request, string $baseUrl = 'https://api.example.test', ?string $bearerToken = null): SnippetSet
    {
        $url = $this->resolvedUrl($spec, $request, $baseUrl);
        $method = strtoupper($spec->endpoint->method);
        $headers = $request->headers;

        if ($bearerToken !== null && trim($bearerToken) !== '') {
            $headers['Authorization'] = 'Bearer '.$bearerToken;
        }

        $body = $request->body;
        if ($body !== null && ! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return new SnippetSet(
            curl: $this->curlSnippet($method, $url, $headers, $body),
            fetch: $this->fetchSnippet($method, $url, $headers, $body),
            axios: $this->axiosSnippet($method, $url, $headers, $body),
        );
    }

    private function resolvedUrl(OperationExampleSpec $spec, GeneratedRequestExample $request, string $baseUrl): string
    {
        $path = $spec->endpoint->path;
        foreach ($request->pathParams as $name => $value) {
            $path = str_replace('{'.$name.'}', rawurlencode((string) $value), $path);
        }

        $url = rtrim($baseUrl, '/').$path;
        if ($request->queryParams !== []) {
            $url .= '?'.http_build_query($request->queryParams);
        }

        return $url;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function curlSnippet(string $method, string $url, array $headers, mixed $body): string
    {
        $parts = ['curl', '-X', $this->shellQuote($method), $this->shellQuote($url)];

        ksort($headers);
        foreach ($headers as $name => $value) {
            $parts[] = '-H';
            $parts[] = $this->shellQuote($name.': '.$value);
        }

        if ($body !== null) {
            $parts[] = '--data';
            $parts[] = $this->shellQuote((string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function fetchSnippet(string $method, string $url, array $headers, mixed $body): string
    {
        $options = [
            'method' => $method,
        ];

        if ($headers !== []) {
            ksort($headers);
            $options['headers'] = $headers;
        }

        if ($body !== null) {
            $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return "fetch('".$url."', ".json_encode($options, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).');';
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function axiosSnippet(string $method, string $url, array $headers, mixed $body): string
    {
        $payload = [
            'method' => strtolower($method),
            'url' => $url,
        ];

        if ($headers !== []) {
            ksort($headers);
            $payload['headers'] = $headers;
        }

        if ($body !== null) {
            $payload['data'] = $body;
        }

        return 'axios('.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).');';
    }

    private function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
