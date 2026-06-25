<?php

namespace Tests\Unit;

use App\Support\DatabaseExceptionPresenter;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class DatabaseExceptionPresenterTest extends TestCase
{
    public function test_maps_foreign_key_violation_to_validation_error(): void
    {
        $presented = DatabaseExceptionPresenter::present(
            $this->queryException(
                "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`db`.`projects`, CONSTRAINT `projects_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`))",
                ['23000', 1452],
            ),
        );

        $this->assertSame('ValidationException', $presented['code']);
        $this->assertSame(422, $presented['status']);
        $this->assertArrayHasKey('owner_id', $presented['fields']);
    }

    public function test_maps_unknown_column_to_schema_out_of_date(): void
    {
        $presented = DatabaseExceptionPresenter::present(
            $this->queryException(
                "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'timeline' in 'field list'",
                ['42S22', 1054],
            ),
        );

        $this->assertSame('SCHEMA_OUT_OF_DATE', $presented['code']);
        $this->assertSame(503, $presented['status']);
        $this->assertStringContainsString('timeline', $presented['message']);
    }

    /**
     * @param  array{0: string, 1: int}  $errorInfo
     */
    private function queryException(string $message, array $errorInfo): QueryException
    {
        $exception = new QueryException(
            'mysql',
            'insert into `projects` values (?)',
            [],
            new \PDOException($message, (int) $errorInfo[1]),
        );

        $property = new \ReflectionProperty($exception, 'errorInfo');
        $property->setAccessible(true);
        $property->setValue($exception, $errorInfo);

        return $exception;
    }
}
