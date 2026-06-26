<?php

use App\Http\Middleware\EnsureWorkspaceScope;
use App\Http\Middleware\RejectFailedFileUploads;
use App\Http\Middleware\RestrictApiByIp;
use App\Support\ApiResponse;
use App\Support\DatabaseExceptionPresenter;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->api(
            prepend: [
                ValidatePostSize::class,
                RestrictApiByIp::class,
            ],
            append: [
                RejectFailedFileUploads::class,
            ],
        );

        $aliases = [
            'workspace.scope' => EnsureWorkspaceScope::class,
        ];

        if (class_exists(\Spatie\Permission\Middleware\RoleMiddleware::class)) {
            $aliases['role'] = \Spatie\Permission\Middleware\RoleMiddleware::class;
            $aliases['permission'] = \Spatie\Permission\Middleware\PermissionMiddleware::class;
            $aliases['role_or_permission'] = \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class;
        }

        $middleware->alias($aliases);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $throwable, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($throwable instanceof PostTooLargeException) {
                $postMax = ini_get('post_max_size');
                $uploadMax = ini_get('upload_max_filesize');

                return ApiResponse::error(
                    code: 'PAYLOAD_TOO_LARGE',
                    message: "The request body is too large for PHP's post_max_size (currently {$postMax}). If you are uploading files, also ensure upload_max_filesize (currently {$uploadMax}) is sufficient, or reduce the payload size.",
                    fields: [],
                    status: 413
                );
            }

            $validationException = null;
            $cursor = $throwable;
            while ($cursor !== null) {
                if ($cursor instanceof ValidationException || class_basename($cursor) === 'ValidationException') {
                    $validationException = $cursor;
                    break;
                }
                $cursor = $cursor->getPrevious();
            }

            if ($validationException !== null) {
                $fields = method_exists($validationException, 'errors')
                    ? (array) $validationException->errors()
                    : [];

                $status = property_exists($validationException, 'status') && is_int($validationException->status)
                    ? $validationException->status
                    : 422;

                $message = trim((string) $validationException->getMessage()) !== ''
                    ? $validationException->getMessage()
                    : 'The given data was invalid.';

                return ApiResponse::error(
                    code: 'ValidationException',
                    message: $message,
                    fields: $fields,
                    status: $status,
                );
            }

            if ($throwable instanceof QueryException) {
                $presented = DatabaseExceptionPresenter::present($throwable);

                return ApiResponse::error(
                    code: $presented['code'],
                    message: $presented['message'],
                    fields: $presented['fields'],
                    status: $presented['status'],
                );
            }

            if ($throwable instanceof UniqueConstraintViolationException) {
                $raw = $throwable->getMessage();
                $isClientCompanyUnique = str_contains($raw, 'clients')
                    && (str_contains($raw, 'company_name') || str_contains($raw, 'company'));

                if ($isClientCompanyUnique) {
                    return ApiResponse::error(
                        code: 'ValidationException',
                        message: 'The given data was invalid.',
                        fields: [
                            'company_name' => [
                                'A client with this company name already exists in this workspace.',
                            ],
                        ],
                        status: 422
                    );
                }

                return ApiResponse::error(
                    code: 'DUPLICATE_ENTRY',
                    message: 'A record with these values already exists (database unique constraint).',
                    fields: [],
                    status: 409
                );
            }

            $status = $throwable instanceof HttpExceptionInterface
                ? $throwable->getStatusCode()
                : 500;

            $message = $status >= 500
                ? 'Something went wrong. Please try again.'
                : $throwable->getMessage();

            return ApiResponse::error(
                code: class_basename($throwable),
                message: $message,
                status: $status,
            );
        });
    })->create();
