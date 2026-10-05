<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stat_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->string('name', 255);
            $table->string('category', 120);
            $table->string('unit', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('public')->default(true);
            $table->timestampsTz();
            $table->unique(['workspace_id', 'key']);
        });

        Schema::create('player_stats', function (Blueprint $table) {
            $table->foreignUuid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stat_definition_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('value');
            $table->timestampTz('updated_at');
            $table->primary(['season_id', 'player_id', 'stat_definition_id']);
        });

        Schema::create('stat_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stat_definition_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('value');
            $table->timestampTz('observed_at');
            $table->index(['season_id', 'player_id', 'stat_definition_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_history');
        Schema::dropIfExists('player_stats');
        Schema::dropIfExists('stat_definitions');
    }
};
