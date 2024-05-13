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
            $table->integer('user_id')->nullable()->constrained(config('auditable.user_table'));
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
        Schema::dropIfExists('audits');
    }
};
