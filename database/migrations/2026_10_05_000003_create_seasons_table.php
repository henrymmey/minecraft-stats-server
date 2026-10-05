<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->boolean('active')->default(false);
            $table->timestampsTz();
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
