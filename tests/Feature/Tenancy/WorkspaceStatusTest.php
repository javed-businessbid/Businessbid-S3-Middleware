<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_defaults_to_active_status(): void
    {
        $workspace = Workspace::query()->create([
            'workspace_name' => 'Status Test Workspace',
            'code' => 'WST001',
        ]);

        $this->assertSame(Workspace::STATUS_ACTIVE, $workspace->fresh()->status);
    }

    public function test_workspace_statuses_are_active_and_inactive(): void
    {
        $this->assertSame([
            Workspace::STATUS_ACTIVE,
            Workspace::STATUS_INACTIVE,
        ], Workspace::statuses());
    }

    public function test_validation_exception_returns_field_errors(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $this->postJson('/api/workspaces', [
            'workspace_name' => 'Invalid Status Workspace',
            'status' => 'suspended',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ValidationException')
            ->assertJsonStructure(['error' => ['fields' => ['status']]]);
    }

    public function test_workspace_store_accepts_lowercase_active_status(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super2@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $this->postJson('/api/workspaces', [
            'workspace_name' => 'Lowercase Active Workspace',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.workspace.status', Workspace::STATUS_ACTIVE);
    }

    public function test_super_admin_can_show_workspace_without_workspace_scope_header(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-show@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $owner = User::query()->create([
            'name' => 'Workspace Owner',
            'email' => 'workspace-owner@example.com',
            'phone' => '+923001234567',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Shown Workspace',
            'code' => 'WSS001',
            'status' => Workspace::STATUS_ACTIVE,
            'owner_id' => $owner->id,
        ]);

        $this->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.admin_id', $owner->id)
            ->assertJsonPath('data.admin_name', 'Workspace Owner')
            ->assertJsonPath('data.admin_phone', '+923001234567');
    }

    public function test_workspace_logo_returns_metadata_attributes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-logo@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Logo Workspace',
            'code' => 'WSL001',
            'status' => Workspace::STATUS_ACTIVE,
            'logo' => 'https://api.hierys.test/storage/workspace-logos/123e4567_logo-main.png',
        ]);

        $this->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.logo.url', 'https://api.hierys.test/storage/workspace-logos/123e4567_logo-main.png')
            ->assertJsonPath('data.logo.disk', 'public')
            ->assertJsonPath('data.logo.path', 'workspace-logos/123e4567_logo-main.png')
            ->assertJsonPath('data.logo.filename', '123e4567_logo-main.png')
            ->assertJsonPath('data.logo.original_name', 'logo-main.png')
            ->assertJsonPath('data.logo.extension', 'png')
            ->assertJsonPath('data.logo.is_external', false);
    }

    public function test_super_admin_can_update_workspace_without_workspace_header(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super3@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Workspace Update Target',
            'code' => 'WSU001',
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        $this->patchJson("/api/workspaces/{$workspace->id}", [
            'workspace_name' => 'Workspace Renamed',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace_name', 'Workspace Renamed')
            ->assertJsonPath('data.status', Workspace::STATUS_INACTIVE);
    }

    public function test_super_admin_can_update_workspace_via_post_multipart_alias(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-post-update@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Post Update Target',
            'code' => 'WSP001',
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        $this->postJson("/api/workspaces/{$workspace->id}", [
            'workspace_name' => 'Renamed Via Post',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace_name', 'Renamed Via Post')
            ->assertJsonPath('data.status', Workspace::STATUS_INACTIVE);
    }

    public function test_non_super_admin_cannot_create_or_update_workspace(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin.workspace@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');
        Sanctum::actingAs($user);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Workspace Guarded',
            'code' => 'WSG001',
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/workspaces', [
            'workspace_name' => 'Should Not Create',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'WORKSPACE_ACCESS_DENIED');

        $this->patchJson("/api/workspaces/{$workspace->id}", [
            'workspace_name' => 'Should Not Update',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'WORKSPACE_ACCESS_DENIED');
    }
}
