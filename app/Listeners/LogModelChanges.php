<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LogModelChanges
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function created(Model $model): void
    {
        $request = request();
        if (! $request instanceof Request) {
            return;
        }

        $this->auditLog->log($request, 'model.created', $model, null, $model->toArray());
    }
}
