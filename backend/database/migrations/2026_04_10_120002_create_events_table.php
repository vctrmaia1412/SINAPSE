<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('capacity');
            $table->string('status', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index('organizer_id', 'events_organizer_id_index');
            $table->index('starts_at', 'events_starts_at_index');
            $table->index('status', 'events_status_index');
            $table->index(['status', 'starts_at'], 'events_status_starts_at_index');
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('published', 'cancelled'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_ends_after_starts CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_positive CHECK (capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
