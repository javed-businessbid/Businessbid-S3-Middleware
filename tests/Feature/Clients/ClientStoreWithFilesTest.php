<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\FileAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientStoreWithFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_client_with_files_stores_file_assets_under_client_folder(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Client With Files',
            'email' => 'client-files-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Files Workspace',
            'code' => strtoupper(substr(uniqid('CFW', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $company = 'FileCo '.uniqid();

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->post('/api/clients', [
                'client_name' => 'File Client',
                'company_name' => $company,
                'files' => [
                    UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('brief.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ]);

        $response->assertCreated();

        $clientId = (int) $response->json('data.id');
        $this->assertGreaterThan(0, $clientId);

        $expectedPrefix = Client::filesStorageDirectory($workspace->id, $clientId);

        $this->assertCount(2, FileAsset::query()->where('client_id', $clientId)->get());

        foreach (FileAsset::query()->where('client_id', $clientId)->get() as $file) {
            $this->assertStringStartsWith($expectedPrefix.'/', $file->storage_path);
            Storage::disk('public')->assertExists($file->storage_path);
        }

        $files = $response->json('data.files');
        $this->assertCount(2, $files);
        foreach ($files as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('file_name', $row);
            $this->assertArrayHasKey('size', $row);
            $this->assertArrayHasKey('type', $row);
            $this->assertArrayHasKey('url', $row);
            $this->assertIsString($row['url']);
        }
    }
}
