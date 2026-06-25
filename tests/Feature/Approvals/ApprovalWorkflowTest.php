<?php

namespace Tests\Feature\Approvals;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_revision_resets_workflow_to_lead_step(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $workspace = Workspace::query()->create([
            'workspace_name' => 'Workspace A',
            'code' => 'WSA001',
            'status' => 'Active',
        ]);
        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        $task = Task::query()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Task X',
            'priority' => 'High',
            'status' => 'In Review',
        ]);

        Sanctum::actingAs($user);
        $headers = ['X-Workspace-Id' => (string) $workspace->id];

        $create = $this->withHeaders($headers)->postJson('/api/approvals/submit', [
            'entity_type' => Task::class,
            'entity_id' => $task->id,
        ])->assertCreated();

        $workflowId = $create->json('data.id');

        $this->withHeaders($headers)->postJson("/api/approvals/{$workflowId}/approve", [
            'comments' => 'Lead approved',
        ])->assertOk()->assertJsonPath('data.current_step', 2);

        $this->withHeaders($headers)->postJson("/api/approvals/{$workflowId}/request-revision", [
            'comments' => 'Need revision',
        ])->assertOk()->assertJsonPath('data.current_step', 1);
    }
}
