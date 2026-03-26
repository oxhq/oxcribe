<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

use Illuminate\Http\Request;

final class RequestSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return [
            'method' => $request->getMethod(),
            'path' => ltrim($request->path(), '/'),
            'query' => $request->query->all(),
            'headers' => $headers,
            'input' => $request->all(),
        ];
    }
}
