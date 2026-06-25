<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientStorePortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_client_with_email_and_password_creates_portal_user_links(): void
    {
        $admin = User::query()->create([
            'name' => 'Client Admin',
            'email' => 'client-admin-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Portal Access Workspace',
            'code' => strtoupper(substr(uniqid('PAW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $portalEmail = 'portal-'.uniqid('', true).'@example.com';
        $portalPassword = 'portal-pass-99';

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/clients', [
                'client_name' => 'Portal Client',
                'company_name' => 'Portal Client Co '.uniqid(),
                'email' => $portalEmail,
                'password' => $portalPassword,
            ]);

        $response->assertCreated();
        $clientId = (int) $response->json('data.id');
        $response->assertJsonPath('data.admin.email', $portalEmail);
        $response->assertJsonPath('data.client.email', $portalEmail);

        $portalUser = User::query()->where('email', $portalEmail)->first();
        $this->assertNotNull($portalUser);
        $this->assertTrue(Hash::check($portalPassword, (string) $portalUser?->getAuthPassword()));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $portalUser?->id,
            'role' => 'client',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('client_user', [
            'workspace_id' => $workspace->id,
            'client_id' => $clientId,
            'user_id' => $portalUser?->id,
            'access' => 'standard',
        ]);
    }
}
