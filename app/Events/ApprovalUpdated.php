<?php

namespace App\Events;

use App\Models\ApprovalWorkflow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalWorkflow $workflow,
        public string $action
    ) {
    }
}
