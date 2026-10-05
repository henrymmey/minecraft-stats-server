<?php

namespace App\Console\Commands;

use App\Services\Authentication\BootstrapService;
use Illuminate\Console\Command;

class CreateBootstrapToken extends Command
{
    protected $signature = 'stats:bootstrap-token
        {workspace_name : Initial workspace display name}
        {workspace_slug : Initial workspace slug}
        {--ttl=60 : Token lifetime in minutes}';

    protected $description = 'Create a one-time initial owner bootstrap token.';

    public function handle(BootstrapService $bootstrap): int
    {
        try {
            $token = $bootstrap->createToken(
                $this->argument('workspace_name'),
                $this->argument('workspace_slug'),
                (int) $this->option('ttl'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bootstrap token (show once):');
        $this->line($token);
        $this->warn('Store this token securely. It cannot be recovered after this command finishes.');

        return self::SUCCESS;
    }
}
