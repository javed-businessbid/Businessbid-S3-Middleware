<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientStoreDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_company_name_in_workspace_returns_validation_error(): void
    {
        $user = User::query()->create([
            'name' => 'Client Creator',
            'email' => 'client-creator-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Dup Workspace',
            'code' => strtoupper(substr(uniqid('CDW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'First Client',
            'company_name' => 'Acme Corporation',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/clients', [
                'client_name' => 'Second Client',
                'company_name' => 'Acme Corporation',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ValidationException')
            ->assertJsonStructure(['error' => ['fields' => ['company_name']]]);
    }

    public function test_duplicate_company_name_conflicts_with_soft_deleted_client(): void
    {
        $user = User::query()->create([
            'name' => 'Client Creator Soft',
            'email' => 'client-soft-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Soft Workspace',
            'code' => strtoupper(substr(uniqid('CSW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'Trashed Client',
            'company_name' => 'Ghost Corp',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ])->delete();

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/clients', [
                'client_name' => 'New Client',
                'company_name' => 'Ghost Corp',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ValidationException')
            ->assertJsonStructure(['error' => ['fields' => ['company_name']]]);
    }
}
