<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_record_leaves_an_audit_entry(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        $entry = AuditLog::query()->where('auditable_id', $user->id)->firstOrFail();

        $this->assertSame('created', $entry->event);
        $this->assertSame(User::class, $entry->auditable_type);
        $this->assertSame('Ana', $entry->new_values['name']);
    }

    #[Test]
    public function updating_a_record_stores_the_previous_and_the_new_value(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        $user->update(['name' => 'Ana María']);

        $entry = AuditLog::query()->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame('Ana', $entry->old_values['name']);
        $this->assertSame('Ana María', $entry->new_values['name']);
    }

    #[Test]
    public function the_password_never_reaches_the_audit_log(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'a-brand-new-secret-1']);

        $entries = AuditLog::query()->where('auditable_id', $user->id)->get();

        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey('password', $entry->new_values ?? []);
            $this->assertArrayNotHasKey('password', $entry->old_values ?? []);
            $this->assertArrayNotHasKey('remember_token', $entry->new_values ?? []);
        }
    }

    #[Test]
    public function audit_entries_cannot_be_edited(): void
    {
        User::factory()->create();
        $entry = AuditLog::query()->firstOrFail();

        $this->expectException(RuntimeException::class);

        $entry->update(['event' => 'tampered']);
    }

    #[Test]
    public function audit_entries_cannot_be_deleted(): void
    {
        User::factory()->create();
        $entry = AuditLog::query()->firstOrFail();

        $this->expectException(RuntimeException::class);

        $entry->delete();
    }
}
