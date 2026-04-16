<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('status', 32);
            $table->timestampsTz();

            $table->unique(['event_id', 'user_id'], 'event_registrations_event_id_user_id_unique');
            $table->index('user_id', 'event_registrations_user_id_index');
            $table->index(['event_id', 'status'], 'event_registrations_event_id_status_index');
            $table->index(['user_id', 'status'], 'event_registrations_user_id_status_index');
        });

        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_status_check CHECK (status IN ('confirmed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
