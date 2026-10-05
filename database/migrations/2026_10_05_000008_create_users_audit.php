<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('oidc_issuer', 500);
            $table->string('oidc_subject', 500);
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->unique(['oidc_issuer', 'oidc_subject']);
        });

        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['workspace_id', 'user_id']);
        });

        Schema::create('player_aliases', function (Blueprint $table) {
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->string('username', 16);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->primary(['player_id', 'username']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 120)->nullable();
            $table->uuid('target_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('player_aliases');
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('users');
    }
};
