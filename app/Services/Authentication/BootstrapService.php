<?php

namespace App\Services\Authentication;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BootstrapService
{
    public function createToken(string $workspaceName, string $workspaceSlug, int $ttlMinutes = 60): string
    {
        if (Workspace::query()->exists()) {
            throw new RuntimeException('Bootstrapping is only available before the first workspace exists.');
        }

        $id = (string) Str::uuid();
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = "mst_bootstrap_{$id}_{$secret}";

        DB::table('bootstrap_tokens')->insert([
            'id' => $id,
            'token_hash' => hash('sha256', $secret),
            'workspace_name' => $workspaceName,
            'workspace_slug' => $workspaceSlug,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function consume(string $token, User $user): Workspace
    {
        $parts = explode('_', $token, 4);

        if (count($parts) !== 4 || $parts[0] !== 'mst' || $parts[1] !== 'bootstrap' || !Str::isUuid($parts[2])) {
            throw new RuntimeException('Invalid bootstrap token.');
        }

        $record = DB::table('bootstrap_tokens')->where('id', $parts[2])->first();

        if (!$record || $record->used_at !== null || now()->greaterThan($record->expires_at)) {
            throw new RuntimeException('Bootstrap token is invalid or expired.');
        }

        if (!hash_equals($record->token_hash, hash('sha256', $parts[3]))) {
            throw new RuntimeException('Bootstrap token is invalid.');
        }

        return DB::transaction(function () use ($record, $user): Workspace {
            if (Workspace::query()->exists()) {
                throw new RuntimeException('A workspace already exists.');
            }

            $workspace = Workspace::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $record->workspace_name,
                'slug' => $record->workspace_slug,
            ]);

            DB::table('workspace_memberships')->insert([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
            ]);

            DB::table('bootstrap_tokens')
                ->where('id', $record->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            session(['current_workspace_id' => $workspace->id]);

            return $workspace;
        });
    }
}
