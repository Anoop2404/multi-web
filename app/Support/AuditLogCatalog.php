<?php

namespace App\Support;

class AuditLogCatalog
{
    /** @return array<string, string> */
    public static function categories(): array
    {
        return [
            'auth'       => 'Authentication',
            'users'      => 'Users & roles',
            'membership' => 'Membership & payments',
            'fest'       => 'Fest & events',
            'mcq'        => 'MCQ exams',
            'training'   => 'Training programs',
            'sports'     => 'Sports meet',
            'finance'    => 'Finance & ledger',
            'system'     => 'System',
        ];
    }

    public static function label(string $category): string
    {
        return self::categories()[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }

    public static function categoryForAction(string $action): string
    {
        if (str_starts_with($action, 'login') || $action === 'logout') {
            return 'auth';
        }

        if (str_starts_with($action, 'user.')) {
            return 'users';
        }

        if (str_starts_with($action, 'payment.') || str_starts_with($action, 'membership.')) {
            return 'membership';
        }

        if (str_starts_with($action, 'fest.')) {
            return 'fest';
        }

        if (str_starts_with($action, 'mcq.')) {
            return 'mcq';
        }

        if (str_starts_with($action, 'training.')) {
            return 'training';
        }

        if (str_starts_with($action, 'sports.')) {
            return 'sports';
        }

        if (str_starts_with($action, 'portal.')) {
            return 'users';
        }

        if (str_starts_with($action, 'ledger.') || str_starts_with($action, 'remittance.')) {
            return 'finance';
        }

        return 'system';
    }

    /** @return list<string> */
    public static function authActions(): array
    {
        return [
            'login',
            'login.failed',
            'login.portal_rejected',
            'login.no_portal',
            'logout',
        ];
    }
}
