<?php

declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Tests\Fixtures\Requests\GameSearchRequest;

final class GameSearchController
{
    public function index(GameSearchRequest $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
