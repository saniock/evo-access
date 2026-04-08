<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function logger(): AuditLogger
    {
        return $this->app->make(AuditLogger::class);
    }

    public function test_log_writes_a_row(): void
    {
        $this->logger()->log(
            actorUserId: 7,
            action: 'create_role',
            targetRoleId: 1,
            details: ['name' => 'manager'],
        );

        $this->assertSame(1, AuditLog::count());

        $row = AuditLog::first();
        $this->assertSame(7, $row->actor_user_id);
        $this->assertSame('create_role', $row->action);
        $this->assertSame(['name' => 'manager'], $row->details);
    }

    public function test_recent_returns_latest_entries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logger()->log(actorUserId: 7, action: 'test_' . $i);
        }

        $recent = $this->logger()->recent(3);
        $this->assertCount(3, $recent);
    }

    public function test_entries_for_user_filters_by_target(): void
    {
        $this->logger()->log(actorUserId: 7, action: 'assign', targetUserId: 42);
        $this->logger()->log(actorUserId: 7, action: 'assign', targetUserId: 99);

        $entries = $this->logger()->entriesForUser(42);
        $this->assertCount(1, $entries);
    }
}
