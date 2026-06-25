<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectChatProvisioningService;
use Illuminate\Console\Command;

class ProvisionProjectChatsCommand extends Command
{
    protected $signature = 'projects:provision-chats {--workspace= : Limit to a workspace id}';

    protected $description = 'Create or refresh project group chat threads for existing projects';

    public function handle(ProjectChatProvisioningService $provisioning): int
    {
        $workspaceId = $this->option('workspace');
        $query = Project::query()->orderBy('id');

        if ($workspaceId !== null && $workspaceId !== '') {
            $query->where('workspace_id', (int) $workspaceId);
        }

        $provisioned = 0;
        $skipped = 0;

        $query->each(function (Project $project) use ($provisioning, &$provisioned, &$skipped): void {
            if ($provisioning->resolvePmId($project) === null) {
                $this->warn("Skipping project #{$project->id} ({$project->name}): no owner, team lead, or creator.");
                $skipped++;

                return;
            }

            $threads = $provisioning->ensureProjectThreads($project);
            $provisioning->syncTeamParticipantsFromAssignees((int) $project->workspace_id, (int) $project->id);
            $provisioned++;
            $this->line("Project #{$project->id}: ".count($threads).' thread(s).');
        });

        $this->info("Done. Provisioned {$provisioned} project(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
