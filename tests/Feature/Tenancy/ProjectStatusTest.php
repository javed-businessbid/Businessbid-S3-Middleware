<?php

namespace Tests\Feature\Tenancy;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_status_catalog_matches_tech_flow(): void
    {
        $this->assertSame([
            Project::STATUS_BACKLOG,
            Project::STATUS_PLANNED,
            Project::STATUS_IN_PROGRESS,
            Project::STATUS_IN_REVIEW,
            Project::STATUS_BLOCKED,
            Project::STATUS_TESTING,
            Project::STATUS_DONE,
            Project::STATUS_ARCHIVED,
        ], Project::statuses());
    }

    public function test_project_store_defaults_to_planned(): void
    {
        [$user, $workspace] = $this->createScopedMember();

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/projects', [
                'name' => 'Website Revamp',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Project::STATUS_PLANNED);
    }

    public function test_project_store_accepts_normalized_status_text(): void
    {
        [$user, $workspace] = $this->createScopedMember();

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/projects', [
                'name' => 'CRM Integration',
                'status' => 'in progress',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Project::STATUS_IN_PROGRESS);
    }

    public function test_project_store_rejects_unknown_status(): void
    {
        [$user, $workspace] = $this->createScopedMember();

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/projects', [
                'name' => 'Mobile App',
                'status' => 'Paused',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ValidationException')
            ->assertJsonStructure(['error' => ['fields' => ['status']]]);
    }

    public function test_project_store_without_owner_defaults_creator_and_provisions_threads(): void
    {
        [$user, $workspace] = $this->createScopedMember();

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/projects', [
                'name' => 'Ownerless Project',
                'status' => 'Planned',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.owner_id', $user->id);

        $projectId = (int) $response->json('data.id');

        $this->assertDatabaseHas('message_threads', [
            'workspace_id' => $workspace->id,
            'project_id' => $projectId,
            'chat_scope' => 'team_pm',
        ]);
    }

    public function test_project_store_with_owner_provisions_message_threads(): void
    {
        [$user, $workspace] = $this->createScopedMember();

        $owner = User::query()->create([
            'name' => 'Project Owner',
            'email' => 'project-owner-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/projects', [
                'name' => 'Social Media Marketing - HIERYS',
                'owner_id' => $owner->id,
                'status' => 'Planned',
                'start_date' => '2026-06-02',
                'due_date' => '2026-07-09',
                'budget' => 2000,
                'timeline' => 20,
                'budget_frequency' => 'weekly',
                'notes' => 'This is a Project Testing',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Social Media Marketing - HIERYS')
            ->assertJsonPath('data.owner_id', $owner->id);

        $projectId = (int) $response->json('data.id');

        $this->assertDatabaseHas('message_threads', [
            'workspace_id' => $workspace->id,
            'project_id' => $projectId,
            'chat_scope' => 'client_pm',
        ]);

        $this->assertDatabaseHas('message_thread_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
    }

    private function createScopedMember(): array
    {
        $user = User::query()->create([
            'name' => 'Project Member',
            'email' => 'project-member-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Project Status Workspace',
            'code' => strtoupper(substr(uniqid('PWS', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'team_member',
            'status' => 'active',
        ]);

        return [$user, $workspace];
    }
}
