<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Generates the Ed25519 key pair for portal launch tokens (exam-engine/CONTRACT.md §5.3). */
class ExamEngineKeys extends Command
{
    protected $signature = 'exam-engine:keys';

    protected $description = 'Generate the launch-token key pair: secret key for Laravel .env, public key for the engine';

    public function handle(): int
    {
        $pair = sodium_crypto_sign_keypair();

        $this->info('Add to the Laravel .env (keep secret):');
        $this->line('EXAM_ENGINE_LAUNCH_SECRET_KEY='.base64_encode(sodium_crypto_sign_secretkey($pair)));
        $this->newLine();
        $this->info('Add to the exam engine env:');
        $this->line('EXAM_ENGINE_LAUNCH_PUBLIC_KEY='.base64_encode(sodium_crypto_sign_publickey($pair)));
        $this->newLine();
        $this->info('Shared internal API token (same value on both sides):');
        $this->line('EXAM_ENGINE_INTERNAL_TOKEN='.bin2hex(random_bytes(32)));

        return self::SUCCESS;
    }
}
