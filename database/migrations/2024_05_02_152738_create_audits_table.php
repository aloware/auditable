<?php

use Aloware\Auditable\Enums\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('auditable.audits_table'), function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->nullableMorphs('related');
            $table->enum('event_type', EventType::values())->index();
            $table->longText('changes');
            $table->string('label')->nullable()->index();
            $table->json('index')->nullable();

            /*
             * Matches the `int unsigned` primary key of the users table, and is
             * indexed because the modifiedByUser scope filters on it.
             *
             * Deliberately not a foreign key: an audit must outlive the user it
             * is attributed to, and createAudit() logs and swallows write
             * failures, so a constraint violation would silently discard audits
             * instead of surfacing.
             */
            $table->unsignedInteger('user_id')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Must mirror up(), which honours the configured table name.
        Schema::dropIfExists(config('auditable.audits_table'));
    }
};
