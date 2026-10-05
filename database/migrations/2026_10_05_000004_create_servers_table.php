<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('hostname', 253);
            $table->unsignedSmallInteger('port')->default(25565);
            $table->string('display_name', 160);
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();
            $table->unique(['workspace_id', 'hostname', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
