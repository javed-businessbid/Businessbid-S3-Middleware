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

class ClientUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_client_sets_portal_password_and_returns_slim_payload(): void
    {
        $user = User::query()->create([
            'name' => 'Client Updater',
            'email' => 'client-updater-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Update Workspace',
            'code' => strtoupper(substr(uniqid('CUW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'Before',
            'company_name' => 'Update Co '.uniqid(),
            'email' => 'portal@example.com',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $plain = 'new-pass-99';

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->patchJson("/api/clients/{$client->id}", [
                'client_name' => 'After',
                'password' => $plain,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.client.name', 'After');
        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.portal_password');

        $freshClient = $client->fresh();
        $this->assertTrue(Hash::check($plain, (string) $freshClient?->getRawOriginal('portal_password')));

        $portalUser = User::query()->where('email', 'portal@example.com')->first();
        $this->assertNotNull($portalUser);
        $this->assertTrue(Hash::check($plain, (string) $portalUser?->getAuthPassword()));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $portalUser?->id,
            'role' => 'client',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('client_user', [
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'user_id' => $portalUser?->id,
            'access' => 'standard',
        ]);
    }
}
