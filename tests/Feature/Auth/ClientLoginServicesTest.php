<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientLoginServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_login_includes_services_array(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Client Services Workspace',
            'code' => 'CSW'.substr(uniqid('', true), -5),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        $clientUser = User::query()->create([
            'name' => 'Portal Client',
            'email' => 'client-services-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $clientUser->assignRole('client');

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $clientUser->id,
            'role' => 'client',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'client_name' => 'Acme Client',
            'company_name' => 'Acme Co',
            'email' => $clientUser->email,
            'status' => 'Active',
        ]);

        DB::table('client_user')->insert([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'user_id' => $clientUser->id,
            'access' => 'standard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seo = Service::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $clientUser->id,
            'name' => 'SEO',
            'created_by' => $clientUser->id,
        ]);
        $social = Service::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $clientUser->id,
            'name' => 'Social Media',
            'created_by' => $clientUser->id,
        ]);

        DB::table('client_service')->insert([
            [
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'service_id' => $seo->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'service_id' => $social->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $clientUser->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                    'roles',
                    'permissions',
                    'auth',
                    'services' => [
                        ['id', 'name', 'parent_id'],
                    ],
                ],
            ]);

        $services = $response->json('data.services');
        $this->assertCount(2, $services);
        $this->assertEqualsCanonicalizing(
            ['SEO', 'Social Media'],
            collect($services)->pluck('name')->all()
        );
    }

    public function test_non_client_login_does_not_include_services(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-login-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('services', $response->json('data') ?? []);
    }
}
