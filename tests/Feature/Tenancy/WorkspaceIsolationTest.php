<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_other_workspace_scope(): void
    {
        $user = User::query()->create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
        ]);
        $workspaceA = Workspace::query()->create([
            'workspace_name' => 'Workspace A',
            'code' => 'WSA001',
            'status' => 'Active',
        ]);
        $workspaceB = Workspace::query()->create([
            'workspace_name' => 'Workspace B',
            'code' => 'WSB001',
            'status' => 'Active',
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspaceA->id,
            'user_id' => $user->id,
            'role' => 'team_member',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('X-Workspace-Id', (string) $workspaceB->id)
            ->getJson('/api/teams');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'WORKSPACE_BOUNDARY_VIOLATION');
    }
}
