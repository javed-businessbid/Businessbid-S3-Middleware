<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\FileAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientUpdateRemoveDocIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_with_remove_doc_ids_soft_deletes_matching_files(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Doc Remover',
            'email' => 'doc-remover-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Remove Docs WS',
            'code' => strtoupper(substr(uniqid('RDW', true), 0, 10)),
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
            'client_name' => 'Doc Client',
            'company_name' => 'Doc Co '.uniqid(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $path = 'workspace/'.$workspace->id.'/clients/'.$client->id.'/abc_notes.pdf';
        Storage::disk('public')->put($path, 'pdf-bytes');

        $file = FileAsset::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'owner_id' => $user->id,
            'file_name' => 'notes.pdf',
            'storage_disk' => 'public',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'type' => 'pdf',
            'size' => 9,
            'category' => 'client',
            'access_level' => 'Internal',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->patchJson('/api/clients/'.$client->id, [
                'remove_doc_ids' => [$file->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.files', []);

        $trashed = FileAsset::withTrashed()->find($file->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_post_clients_update_alias_accepts_remove_doc_ids_json(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Doc Remover POST',
            'email' => 'doc-remover-post-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Remove Docs WS POST',
            'code' => strtoupper(substr(uniqid('RDP', true), 0, 10)),
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
            'client_name' => 'Alias Client',
            'company_name' => 'Alias Co '.uniqid(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $path = 'workspace/'.$workspace->id.'/clients/'.$client->id.'/keep.pdf';
        Storage::disk('public')->put($path, 'x');

        $file = FileAsset::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'owner_id' => $user->id,
            'file_name' => 'keep.pdf',
            'storage_disk' => 'public',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'type' => 'pdf',
            'size' => 1,
            'category' => 'client',
            'access_level' => 'Internal',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/clients/'.$client->id.'/update', [
                'remove_doc_ids' => [$file->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.files', []);

        $this->assertTrue(FileAsset::withTrashed()->find($file->id)?->trashed() ?? false);
    }

    public function test_remove_doc_ids_rejects_file_from_other_client(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Doc Remover B',
            'email' => 'doc-remover-b-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Remove Docs WS B',
            'code' => strtoupper(substr(uniqid('RDB', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $clientA = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'A',
            'company_name' => 'Co A '.uniqid(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $clientB = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'B',
            'company_name' => 'Co B '.uniqid(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $file = FileAsset::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $clientB->id,
            'owner_id' => $user->id,
            'file_name' => 'other.pdf',
            'storage_disk' => 'public',
            'storage_path' => 'workspace/'.$workspace->id.'/clients/'.$clientB->id.'/x.pdf',
            'mime_type' => 'application/pdf',
            'type' => 'pdf',
            'size' => 1,
            'category' => 'client',
            'access_level' => 'Internal',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->patchJson('/api/clients/'.$clientA->id, [
                'remove_doc_ids' => [$file->id],
            ])
            ->assertStatus(422);
    }
}
