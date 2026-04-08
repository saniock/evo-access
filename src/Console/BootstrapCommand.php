<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

class BootstrapCommand extends Command
{
    protected $signature = 'evoaccess:bootstrap {--dry-run}';
    protected $description = 'Ensure bootstrap superadmin user IDs from config are assigned to the system role.';

    public function handle(): int
    {
        $userIds = (array) config('evoAccess.bootstrap_superadmin_user_ids', []);

        if (empty($userIds)) {
            $this->warn('No bootstrap user IDs configured. Edit config/evoAccess.php to add some.');
            return self::SUCCESS;
        }

        $superadmin = Role::where('name', 'superadmin')->where('is_system', 1)->first();
        if (!$superadmin) {
            $this->error('Superadmin role not found. Did the migrations run?');
            return self::FAILURE;
        }

        $created = 0;
        $existing = 0;
        $wouldCreate = 0;

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $existingRow = UserRole::where('user_id', $userId)->first();

            if ($existingRow) {
                if ($existingRow->role_id !== $superadmin->id) {
                    $this->warn("User {$userId} has role {$existingRow->role_id}, NOT superadmin. Skipping (use admin UI to change).");
                }
                $existing++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would create assignment for user {$userId}");
                $wouldCreate++;
                continue;
            }

            UserRole::create([
                'user_id'     => $userId,
                'role_id'     => $superadmin->id,
                'assigned_by' => null,
            ]);
            $created++;
        }

        if ($this->option('dry-run')) {
            $this->info("Bootstrap dry-run: {$wouldCreate} would be created, {$existing} already existed.");
        } else {
            $this->info("Bootstrap complete: {$created} new, {$existing} already existed.");
        }
        return self::SUCCESS;
    }
}
