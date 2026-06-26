<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response;

class DatabaseExceptionPresenter
{
    /**
     * @return array{code: string, message: string, fields: array<string, array<int, string>>, status: int}
     */
    public static function present(QueryException $exception): array
    {
        return [
            'code' => 'DATABASE_ERROR',
            'message' => 'Something went wrong. Please try again.',
            'fields' => [],
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
        ];
    }
}
