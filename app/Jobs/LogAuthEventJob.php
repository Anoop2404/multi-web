<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Support\AuditLogCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAuthEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  array<string, mixed>  $context */
    public function __construct(
        public string $action,
        public string $description,
        public ?int $userId = null,
        public array $context = [],
        public ?string $email = null,
    ) {}

    public function handle(): void
    {
        AuditLog::create([
            'user_id'      => $this->userId,
            'category'     => 'auth',
            'action'       => $this->action,
            'description'  => $this->description,
            'ip_address'   => $this->context['ip'] ?? null,
            'properties'   => $this->context ?: null,
        ]);
    }

    public static function fromLogin(string $action, int $userId, ?string $email, array $context = []): self
    {
        return new self(
            action: $action,
            description: self::descriptionFor($action, $email),
            userId: $userId,
            context: array_merge(['email' => $email], $context),
            email: $email,
        );
    }

    // Portal accounts (judge/house-admin/group-admin/coordinator roles, etc.) can
    // legitimately have no email — "Email (optional — leave blank to log in by
    // username only)" is an explicit option on the Users form — so $email must
    // stay nullable through this whole chain, not just at the constructor.
    private static function descriptionFor(string $action, ?string $email): string
    {
        $label = $email ?? 'username-only account';

        return match ($action) {
            'login' => "User logged in: {$label}",
            'login.failed' => 'Failed login attempt',
            'login.portal_rejected' => "Login rejected (wrong portal): {$label}",
            'login.no_portal' => "Login rejected (no portal): {$label}",
            'logout' => "User logged out: {$label}",
            default => AuditLogCatalog::categoryForAction($action).' event',
        };
    }
}
