<?php

namespace Aloware\Auditable\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exercises the upgrade path on a table shaped like an existing install, rather
 * than the fresh-install path the other schema tests cover.
 */
class AlignAuditsUserIdMigrationTest extends TestCase
{
    private const LEGACY_TABLE = 'legacy_audits';

    protected function setUp(): void
    {
        parent::setUp();

        // Point the migration at a table created the way the original one did:
        // a signed integer user_id, no index, and no foreign key.
        config()->set('auditable.audits_table', self::LEGACY_TABLE);

        Schema::create(self::LEGACY_TABLE, function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->string('event_type');
            $table->longText('changes');
            $table->json('index')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    private function migration(): Migration
    {
        return require __DIR__
            . '/../database/migrations/2026_08_04_000000_align_audits_user_id_column.php';
    }

    private function indexedColumns(): array
    {
        return array_map(
            fn (array $index) => $index['columns'],
            Schema::getIndexes(self::LEGACY_TABLE)
        );
    }

    #[Test]
    public function the_legacy_table_starts_without_a_user_id_index(): void
    {
        $this->assertNotContains(['user_id'], $this->indexedColumns());
    }

    #[Test]
    public function it_adds_the_missing_user_id_index(): void
    {
        $this->migration()->up();

        $this->assertContains(['user_id'], $this->indexedColumns());
    }

    #[Test]
    public function it_preserves_existing_rows(): void
    {
        DB::table(self::LEGACY_TABLE)->insert([
            'auditable_type' => 'App\Models\Campaign',
            'auditable_id' => 7,
            'event_type' => 'model_created',
            'changes' => '{"title":"kept"}',
            'index' => '["title"]',
            'user_id' => 4242,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $row = DB::table(self::LEGACY_TABLE)->sole();
        $this->assertSame(4242, (int) $row->user_id);
        $this->assertSame('{"title":"kept"}', $row->changes);
    }

    #[Test]
    public function it_preserves_rows_whose_user_no_longer_exists(): void
    {
        // Production holds thousands of these; the migration must not discard
        // or null out the attribution.
        DB::table(self::LEGACY_TABLE)->insert([
            'auditable_type' => 'App\Models\Campaign',
            'auditable_id' => 1,
            'event_type' => 'model_updated',
            'changes' => '{}',
            'user_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertSame(999999, (int) DB::table(self::LEGACY_TABLE)->value('user_id'));
    }

    #[Test]
    public function it_leaves_null_user_ids_alone(): void
    {
        DB::table(self::LEGACY_TABLE)->insert([
            'auditable_type' => 'App\Models\Campaign',
            'auditable_id' => 1,
            'event_type' => 'model_created',
            'changes' => '{}',
            'user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertNull(DB::table(self::LEGACY_TABLE)->value('user_id'));
    }

    #[Test]
    public function it_is_safe_to_run_twice(): void
    {
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        // A second run must not fail on a duplicate index.
        $this->assertContains(['user_id'], $this->indexedColumns());
    }

    #[Test]
    public function it_adds_no_foreign_key(): void
    {
        $this->migration()->up();

        $this->assertSame([], Schema::getForeignKeys(self::LEGACY_TABLE));
    }

    #[Test]
    public function it_can_be_rolled_back(): void
    {
        $migration = $this->migration();
        $migration->up();
        $this->assertContains(['user_id'], $this->indexedColumns());

        $migration->down();

        $this->assertNotContains(['user_id'], $this->indexedColumns());
    }

    #[Test]
    public function it_skips_a_table_without_a_user_id_column(): void
    {
        Schema::create('audits_without_user', function (Blueprint $table) {
            $table->id();
        });
        config()->set('auditable.audits_table', 'audits_without_user');

        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('audits_without_user'));
    }
}
