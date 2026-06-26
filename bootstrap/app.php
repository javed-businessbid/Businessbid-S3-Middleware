<?php

use App\Http\Middleware\RejectFailedFileUploads;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(
            prepend: [
                ValidatePostSize::class,
            ],
            append: [
                RejectFailedFileUploads::class,
            ],
        );
    })->create();
