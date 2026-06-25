<?php

namespace Tests\Feature\Files;

use App\Models\FileAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_member_streams_internal_file(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Admin Downloader',
            'email' => 'dl-admin-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        $workspace = Workspace::query()->create([
            'workspace_name' => 'DL Workspace',
            'code' => strtoupper(substr(uniqid('DLW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $relative = 'workspace/'.$workspace->id.'/test-'.uniqid().'.txt';
        Storage::disk('public')->put($relative, 'hello-download');

        $file = FileAsset::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => null,
            'owner_id' => $user->id,
            'file_name' => 'test.txt',
            'storage_disk' => 'public',
            'storage_path' => $relative,
            'mime_type' => 'text/plain',
            'type' => 'txt',
            'size' => 14,
            'category' => 'client',
            'access_level' => 'Internal',
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->get('/api/files/'.$file->id.'/download');

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', strtolower($response->headers->get('content-disposition')));
        $this->assertSame('hello-download', $response->streamedContent());
    }

    public function test_client_role_cannot_download_internal_file(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $clientUser = User::query()->create([
            'name' => 'Portal Client',
            'email' => 'dl-client-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $clientUser->assignRole('client');

        $workspace = Workspace::query()->create([
            'workspace_name' => 'DL Workspace B',
            'code' => strtoupper(substr(uniqid('DLB', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $clientUser->id,
            'role' => 'client',
            'status' => 'active',
        ]);

        $relative = 'workspace/'.$workspace->id.'/secret-'.uniqid().'.txt';
        Storage::disk('public')->put($relative, 'secret');

        $file = FileAsset::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => null,
            'owner_id' => null,
            'file_name' => 'secret.txt',
            'storage_disk' => 'public',
            'storage_path' => $relative,
            'mime_type' => 'text/plain',
            'type' => 'txt',
            'size' => 6,
            'category' => 'client',
            'access_level' => 'Internal',
        ]);

        Sanctum::actingAs($clientUser);

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->get('/api/files/'.$file->id.'/download');

        $response->assertForbidden();
    }
}
