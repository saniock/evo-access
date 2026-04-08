<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

class MigrateLegacyEvoRolesCommand extends Command
{
    protected $signature = 'evoaccess:migrate-legacy-evo-roles
                            {--mapping= : Path to JSON mapping file (evo_role_id → dd_role_name)}
                            {--dry-run : Print what would be done without making changes}';

    protected $description = 'Migrate users from legacy EVO roles to evoAccess DD roles based on a JSON mapping.';

    public function handle(): int
    {
        $mappingPath = $this->option('mapping');

        if (!$mappingPath || !is_file($mappingPath)) {
            $this->error("Mapping file not found: {$mappingPath}");
            return self::FAILURE;
        }

        $mapping = json_decode(file_get_contents($mappingPath), true);
        if (!is_array($mapping)) {
            $this->error('Mapping file must contain a JSON object: { "evo_role_id": "dd_role_name", ... }');
            return self::FAILURE;
        }

        if (!Schema::hasTable('user_attributes')) {
            $this->error("Table 'user_attributes' not found. This command requires an EVO database.");
            return self::FAILURE;
        }

        $evoUsers = DB::table('user_attributes')
            ->where('role', '>', 0)
            ->get(['internalKey', 'role', 'fullname']);

        $stats = ['migrated' => 0, 'skipped_no_mapping' => 0, 'skipped_already_assigned' => 0];

        foreach ($evoUsers as $evoUser) {
            $evoRoleId = (string) $evoUser->role;
            $ddRoleName = $mapping[$evoRoleId] ?? null;

            if ($ddRoleName === null) {
                $this->warn("Skipping user {$evoUser->internalKey} ({$evoUser->fullname}) — no mapping for EVO role {$evoRoleId}");
                $stats['skipped_no_mapping']++;
                continue;
            }

            $ddRole = Role::where('name', $ddRoleName)->first();
            if (!$ddRole) {
                $this->error("DD role '{$ddRoleName}' does not exist — create it first in /access/matrix");
                continue;
            }

            $existing = UserRole::where('user_id', $evoUser->internalKey)->first();
            if ($existing) {
                $stats['skipped_already_assigned']++;
                continue;
            }

            if (!$this->option('dry-run')) {
                UserRole::create([
                    'user_id'     => $evoUser->internalKey,
                    'role_id'     => $ddRole->id,
                    'assigned_by' => null,
                ]);
            }

            $this->info("✓ {$evoUser->fullname} (user_id={$evoUser->internalKey}) → {$ddRoleName}");
            $stats['migrated']++;
        }

        $this->newLine();
        $this->info("Migration complete: {$stats['migrated']} migrated, {$stats['skipped_no_mapping']} skipped (no mapping), {$stats['skipped_already_assigned']} skipped (already assigned)");

        return self::SUCCESS;
    }
}
