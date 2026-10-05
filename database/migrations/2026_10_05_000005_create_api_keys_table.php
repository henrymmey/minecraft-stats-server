<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 80);
            $table->char('hash', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestampTz('expires_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
            $table->timestampTz('revoked_at')->nullable();
            $table->index(['workspace_id', 'enabled']);
        });

        Schema::create('api_key_scopes', function (Blueprint $table) {
            $table->foreignUuid('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->string('scope', 100);
            $table->primary(['api_key_id', 'scope']);
        });

        Schema::create('api_key_player_restrictions', function (Blueprint $table) {
            $table->foreignUuid('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->primary(['api_key_id', 'player_id']);
        });

        Schema::create('api_key_server_restrictions', function (Blueprint $table) {
            $table->foreignUuid('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained('servers')->cascadeOnDelete();
            $table->primary(['api_key_id', 'server_id']);
        });

        Schema::create('api_key_season_restrictions', function (Blueprint $table) {
            $table->foreignUuid('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->primary(['api_key_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_season_restrictions');
        Schema::dropIfExists('api_key_server_restrictions');
        Schema::dropIfExists('api_key_player_restrictions');
        Schema::dropIfExists('api_key_scopes');
        Schema::dropIfExists('api_keys');
    }
};
