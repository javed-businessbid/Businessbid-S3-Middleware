<?php

namespace Tests\Feature\Messages;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_returns_groups_and_direct_chats(): void
    {
        [$user, $workspace, $peer] = $this->createWorkspacePair();

        Sanctum::actingAs($user);

        $direct = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/messages/direct/{$peer->id}")
            ->assertOk()
            ->json('data');

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/messages/{$direct['id']}/reply", ['body' => 'Hello team'])
            ->assertCreated();

        $inbox = $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/messages/inbox')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($inbox['chats']);
        $this->assertSame('Hello team', $inbox['chats'][0]['last_message_preview'] ?? $inbox['chats'][0]['last_message']['body'] ?? null);
    }

    public function test_cannot_start_direct_chat_with_client(): void
    {
        [$user, $workspace] = $this->createWorkspacePair();
        $client = User::query()->create([
            'name' => 'Client User',
            'email' => 'client-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        WorkspaceUser::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $client->id,
            'role' => 'client',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/messages/direct/{$client->id}")
            ->assertStatus(403);
    }

    /**
     * @return array{0: User, 1: Workspace, 2: User}
     */
    private function createWorkspacePair(): array
    {
        $user = User::query()->create([
            'name' => 'Chat User A',
            'email' => 'chat-a-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $peer = User::query()->create([
            'name' => 'Chat User B',
            'email' => 'chat-b-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::query()->create([
            'workspace_name' => 'Chat Workspace',
            'code' => strtoupper(substr(uniqid('CHT', true), 0, 10)),
            'status' => Workspace::STATUS_ACTIVE,
        ]);

        foreach ([$user, $peer] as $member) {
            WorkspaceUser::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'role' => 'team_member',
                'status' => 'active',
            ]);
        }

        return [$user, $workspace, $peer];
    }
}
