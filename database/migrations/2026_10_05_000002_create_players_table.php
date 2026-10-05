<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('minecraft_uuid');
            $table->string('current_username', 16);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->boolean('public')->default(true);
            $table->timestampsTz();
            $table->unique(['workspace_id', 'minecraft_uuid']);
            $table->index(['workspace_id', 'current_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
