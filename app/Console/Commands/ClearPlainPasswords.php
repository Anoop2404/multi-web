<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ClearPlainPasswords extends Command
{
    protected $signature = 'users:clear-plain-passwords {--force : Run without confirmation}';

    protected $description = 'Null out legacy plain_password values on user records';

    public function handle(): int
    {
        $count = User::query()->whereNotNull('plain_password')->count();

        if ($count === 0) {
            $this->info('No plain_password values found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Clear plain_password on {$count} user(s)?")) {
            return self::SUCCESS;
        }

        User::query()->whereNotNull('plain_password')->update(['plain_password' => null]);

        $this->info("Cleared plain_password on {$count} user(s).");

        return self::SUCCESS;
    }
}
