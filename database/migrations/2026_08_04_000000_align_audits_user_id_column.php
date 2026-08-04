<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings databases created by the original migration in line with it.
 *
 * That migration declared `integer('user_id')->constrained(...)`, but
 * `constrained()` only exists on `foreignId()` columns; on a plain integer it is
 * swallowed by Fluent's magic __call, so no foreign key was ever created and the
 * column stayed a signed `int` while `users.id` is `int unsigned`. It was also
 * left unindexed, even though the `modifiedByUser` scope filters on it.
 *
 * No foreign key is added here. Audits legitimately outlive the users they are
 * attributed to, so orphaned `user_id` values are expected and a constraint
 * would either reject them or require discarding the attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table_name = config('auditable.audits_table');

        if (!Schema::hasTable($table_name) || !Schema::hasColumn($table_name, 'user_id')) {
            return;
        }

        // Existing values are non-negative, so widening to unsigned is lossless.
        Schema::table($table_name, function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->change();
        });

        // Checked rather than assumed, because a column change can rebuild the
        // table (and its indexes) on some drivers.
        if (!$this->hasUserIdIndex($table_name)) {
            Schema::table($table_name, function (Blueprint $table) {
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        $table_name = config('auditable.audits_table');

        if (!Schema::hasTable($table_name) || !Schema::hasColumn($table_name, 'user_id')) {
            return;
        }

        if ($this->hasUserIdIndex($table_name)) {
            Schema::table($table_name, function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
        }

        Schema::table($table_name, function (Blueprint $table) {
            $table->integer('user_id')->nullable()->change();
        });
    }

    private function hasUserIdIndex(string $table_name): bool
    {
        foreach (Schema::getIndexes($table_name) as $index) {
            if ($index['columns'] === ['user_id']) {
                return true;
            }
        }

        return false;
    }
};
