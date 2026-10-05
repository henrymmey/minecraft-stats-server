<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_session_id');
            $table->timestampTz('started_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('client_version', 64);
            $table->string('minecraft_version', 64);
            $table->string('mod_version', 64);
            $table->unique(['player_id', 'client_session_id']);
            $table->index(['season_id', 'last_seen_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->uuid('client_event_id')->unique();
            $table->string('type', 64);
            $table->timestampTz('occurred_at');
            $table->jsonb('payload')->nullable();
            $table->index(['season_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('sessions');
    }
};
