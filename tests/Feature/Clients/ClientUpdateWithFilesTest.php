<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientUpdateWithFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_client_update_with_multipart_files_appends_file_assets(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Client File Updater',
            'email' => 'client-file-up-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Update Files WS',
            'code' => strtoupper(substr(uniqid('CUF', true), 0, 10)),
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
            'client_name' => 'Existing',
            'company_name' => 'Existing Co '.uniqid(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->post('/api/clients/'.$client->id, [
                'client_name' => 'Updated Name',
                'files' => [
                    UploadedFile::fake()->create('appendix.pdf', 40, 'application/pdf'),
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.client.name', 'Updated Name');

        $files = $response->json('data.files');
        $this->assertCount(1, $files);
        $this->assertSame('appendix.pdf', $files[0]['file_name']);
    }
}
