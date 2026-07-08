<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AuthController;
use App\Models\Tenant;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;
use Inertia\Response;

class PortalWelcomeController extends Controller
{
    public static function portalRoles(): array
    {
        return [
            'student', 'teacher', 'judge', 'fest_ops', 'mark_entry_coordinator',
            'group_admin', 'house_admin', 'exam_controller', 'exam_staff',
        ];
    }

    public static function shouldShowForUser(?\App\Models\User $user): bool
    {
        if (! $user || $user->portal_welcome_seen) {
            return false;
        }

        return $user->hasAnyRole(self::portalRoles());
    }

    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless(self::shouldShowForUser($user), 404);

        $tenant = $user->tenant;
        $role = $user->getRoleNames()->first();
        $content = $this->contentForRole($role, $tenant, $user);

        return inertia('Portal/Welcome', [
            'organizationName' => $tenant?->name,
            'roleLabel'        => TenantUserCatalog::roleLabels()[$role] ?? ucwords(str_replace('_', ' ', $role ?? 'Portal')),
            'welcomeText'      => $content['text'],
            'actions'          => $content['actions'],
            'dashboardUrl'     => AuthController::homeFor($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $user->update(['portal_welcome_seen' => true]);

        return redirect(AuthController::homeFor($user) ?? '/');
    }

    /** @return array{text: string, actions: list<array{label: string, href: string}>} */
    private function contentForRole(?string $role, ?Tenant $tenant, \App\Models\User $user): array
    {
        $tid = $tenant?->id;
        $home = AuthController::homeFor($user) ?? '/';

        return match ($role) {
            'student' => [
                'text' => 'Your student portal shows fest registrations, Talent Search exams, schedule, and results.',
                'actions' => [
                    ['label' => 'View registrations', 'href' => "/portal/student/{$tid}/fest-registrations"],
                    ['label' => 'Talent Search exams', 'href' => "/portal/student/{$tid}/mcq"],
                    ['label' => 'My profile', 'href' => "/portal/student/{$tid}/profile"],
                ],
            ],
            'teacher' => [
                'text' => 'Manage Talent Search question banks, training programs, fest assignments, and download admit cards.',
                'actions' => [
                    ['label' => 'Talent Search question banks', 'href' => "/portal/teacher/{$tid}/question-banks"],
                    ['label' => 'Fest', 'href' => "/portal/teacher/{$tid}/fest"],
                    ['label' => 'Training', 'href' => "/portal/teacher/{$tid}/training"],
                ],
            ],
            'judge' => [
                'text' => 'You have been assigned as a judge. Enter marks for your assigned events and items here.',
                'actions' => [
                    ['label' => 'View assignments', 'href' => "/portal/judge/{$tid}"],
                ],
            ],
            'fest_ops' => [
                'text' => 'You are an event operations volunteer. Manage gate check, attendance, and stage assignments.',
                'actions' => [
                    ['label' => 'My assignments', 'href' => "/portal/fest-ops/{$tid}"],
                    ['label' => 'Gate check', 'href' => "/portal/fest-ops/{$tid}/gate-check"],
                ],
            ],
            'mark_entry_coordinator' => [
                'text' => 'You coordinate mark entry for assigned fest items.',
                'actions' => [
                    ['label' => 'Dashboard', 'href' => "/portal/fest-coordinator/{$tid}"],
                ],
            ],
            'group_admin' => [
                'text' => 'View student registrations, schedules, and admit cards for your group\'s schools.',
                'actions' => [
                    ['label' => 'Registrations', 'href' => "/portal/group/{$tid}/fest/registrations"],
                    ['label' => 'Students', 'href' => "/portal/group/{$tid}/students"],
                ],
            ],
            'house_admin' => [
                'text' => 'Manage house points, student assignments, and rankings.',
                'actions' => [
                    ['label' => 'Students', 'href' => "/portal/house-admin/{$tid}/students"],
                    ['label' => 'Ranking', 'href' => "/portal/house-admin/{$tid}/ranking"],
                ],
            ],
            'exam_controller', 'exam_staff' => [
                'text' => 'Manage Talent Search exam supervision, attendance, and mark recording for offline exams.',
                'actions' => [
                    ['label' => 'Dashboard', 'href' => "/portal/exam/{$tid}"],
                ],
            ],
            default => [
                'text' => 'Welcome to your portal. Use the navigation below to get started.',
                'actions' => [
                    ['label' => 'Go to dashboard', 'href' => $home],
                ],
            ],
        };
    }
}
