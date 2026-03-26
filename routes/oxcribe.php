<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oxhq\Oxcribe\Http\Controllers\DocsAssetController;
use Oxhq\Oxcribe\Http\Controllers\DocsPageController;
use Oxhq\Oxcribe\Http\Controllers\DocsPayloadController;
use Oxhq\Oxcribe\Http\Controllers\OpenApiDocumentController;

Route::get('oxcribe/assets/{asset}', DocsAssetController::class)
    ->where('asset', '[A-Za-z0-9._-]+')
    ->name('oxcribe.docs.asset');

Route::get((string) config('oxcribe.docs.route', 'oxcribe/docs'), DocsPageController::class)
    ->name('oxcribe.docs');

Route::get((string) config('oxcribe.docs.openapi_route', 'oxcribe/openapi.json'), OpenApiDocumentController::class)
    ->name('oxcribe.openapi');

Route::get((string) config('oxcribe.docs.payload_route', 'oxcribe/docs/payload.json'), DocsPayloadController::class)
    ->name('oxcribe.docs.payload');
