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

class ClientShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_client_returns_requested_record_not_first_in_list(): void
    {
        $user = User::query()->create([
            'name' => 'Client Viewer',
            'email' => 'client-viewer-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Show Workspace',
            'code' => strtoupper(substr(uniqid('CSH', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $first = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'First Co',
            'company_name' => 'First Company',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $second = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'Second Co',
            'company_name' => 'Second Company',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson("/api/clients/{$second->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.company_name', 'Second Company')
            ->assertJsonPath('data.client.name', 'Second Co');

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson("/api/clients/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.company_name', 'First Company')
            ->assertJsonPath('data.client.name', 'First Co');
    }
}
