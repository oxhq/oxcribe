<?php

declare(strict_types=1);

use Garaekz\Oxcribe\Http\Controllers\DocsPayloadController;
use Garaekz\Oxcribe\Http\Controllers\DocsPageController;
use Garaekz\Oxcribe\Http\Controllers\OpenApiDocumentController;
use Illuminate\Support\Facades\Route;

Route::get((string) config('oxcribe.docs.route', 'oxcribe/docs'), DocsPageController::class)
    ->name('oxcribe.docs');

Route::get((string) config('oxcribe.docs.openapi_route', 'oxcribe/openapi.json'), OpenApiDocumentController::class)
    ->name('oxcribe.openapi');

Route::get((string) config('oxcribe.docs.payload_route', 'oxcribe/docs/payload.json'), DocsPayloadController::class)
    ->name('oxcribe.docs.payload');
