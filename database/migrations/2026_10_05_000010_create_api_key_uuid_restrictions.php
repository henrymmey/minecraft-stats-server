<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_key_uuid_restrictions', function (Blueprint $table) {
            $table->foreignUuid('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->uuid('minecraft_uuid');
            $table->primary(['api_key_id', 'minecraft_uuid']);
            $table->index('minecraft_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_uuid_restrictions');
    }
};
