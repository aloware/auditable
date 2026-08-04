<?php

namespace Aloware\Auditable\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\User;

class AuditsTableSchemaTest extends TestCase
{
    private function column(string $name): array
    {
        foreach (Schema::getColumns('audits') as $column) {
            if ($column['name'] === $name) {
                return $column;
            }
        }

        $this->fail("The audits table has no [$name] column.");
    }

    private function indexedColumns(): array
    {
        return array_map(
            fn (array $index) => $index['columns'],
            Schema::getIndexes('audits')
        );
    }

    #[Test]
    public function it_creates_the_expected_columns(): void
    {
        $names = array_column(Schema::getColumns('audits'), 'name');

        foreach ([
            'id', 'auditable_type', 'auditable_id', 'related_type', 'related_id',
            'event_type', 'changes', 'label', 'index', 'user_id',
            'created_at', 'updated_at',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    #[Test]
    public function user_id_is_nullable(): void
    {
        // Audits are recorded for unauthenticated changes too.
        $this->assertTrue($this->column('user_id')['nullable']);
    }

    #[Test]
    public function user_id_is_indexed(): void
    {
        // Regression: the column was created without an index even though the
        // modifiedByUser scope filters on it.
        $this->assertContains(['user_id'], $this->indexedColumns());
    }

    #[Test]
    public function the_filterable_columns_are_indexed(): void
    {
        $indexed = $this->indexedColumns();

        $this->assertContains(['event_type'], $indexed);
        $this->assertContains(['label'], $indexed);
        $this->assertContains(['auditable_type', 'auditable_id'], $indexed);
        $this->assertContains(['related_type', 'related_id'], $indexed);
    }

    #[Test]
    public function it_declares_no_foreign_keys(): void
    {
        // Deliberate: audits outlive the users they are attributed to, so
        // orphaned user_id values are expected. Documenting it here so that
        // reintroducing a constraint fails loudly.
        $this->assertSame([], Schema::getForeignKeys('audits'));
    }

    #[Test]
    public function an_audit_survives_the_hard_deletion_of_its_user(): void
    {
        // This is why there is no foreign key: production holds thousands of
        // audits whose user row no longer exists.
        $user = User::create(['first_name' => 'Ada']);
        $this->actingAs($user);

        $post = $this->makePost();
        $audit_id = $post->audits()->sole()->getKey();

        $user->forceDelete();

        $audit = $post->audits()->sole();
        $this->assertSame($audit_id, $audit->getKey());
        $this->assertSame($user->getKey(), $audit->user_id);
        $this->assertNull($audit->user);
    }

    #[Test]
    public function rolling_back_drops_the_configured_table(): void
    {
        // Regression: down() hardcoded 'audits', so a custom audits_table was
        // left behind on rollback while the wrong table was targeted.
        config()->set('auditable.audits_table', 'custom_audits');

        $migration = require __DIR__
            . '/../database/migrations/2024_05_02_152738_create_audits_table.php';

        $migration->up();
        $this->assertTrue(Schema::hasTable('custom_audits'));

        $migration->down();
        $this->assertFalse(Schema::hasTable('custom_audits'));

        // The default table, which this rollback did not target, is untouched.
        $this->assertTrue(Schema::hasTable('audits'));
    }

    #[Test]
    public function it_accepts_a_user_id_beyond_the_signed_int_range(): void
    {
        // The column is unsigned, matching the users table primary key.
        $post = $this->makePost();

        $audit = $post->audits()->sole();
        $audit->user_id = 3_000_000_000;
        $audit->save();

        $this->assertSame(3_000_000_000, (int) $audit->fresh()->user_id);
    }
}
