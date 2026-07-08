<?php

namespace App\Support;

use App\Models\User;

class TenantUserCatalog
{
    /** @return list<string> */
    public static function sahodayaAssignableRoles(): array
    {
        return [
            'sahodaya_staff',
            'registration_coordinator',
            'sahodaya_finance',
            'certificate_collector',
            'data_entry',
            'event_coordinator',
            'judge',
            'mark_entry_admin',
            'mark_entry_coordinator',
            'exam_controller',
            'exam_staff',
            'fest_ops',
            'school_principal',
        ];
    }

    /** @return list<string> */
    public static function schoolAssignableRoles(): array
    {
        return [
            'school_staff',
            'school_event_coordinator',
            'school_finance_coordinator',
            'school_training_coordinator',
            'school_mcq_coordinator',
            'school_kalotsavam_coordinator',
            'school_sports_coordinator',
            'group_admin',
            'house_admin',
        ];
    }

    /** Roles a school admin may create (not principal/vice-principal). */
    /** @return list<string> */
    public static function schoolAdminCreatableRoles(): array
    {
        return ['school_event_coordinator', 'school_sports_coordinator', 'school_kalotsavam_coordinator', 'school_mcq_coordinator', 'school_training_coordinator', 'school_finance_coordinator', 'school_staff', 'group_admin', 'house_admin'];
    }

    /** Roles a vice principal may create. */
    /** @return list<string> */
    public static function schoolVicePrincipalCreatableRoles(): array
    {
        return self::schoolAdminCreatableRoles();
    }

    /** Roles a school principal may create. */
    /** @return list<string> */
    public static function schoolPrincipalCreatableRoles(): array
    {
        return ['school_admin', 'school_vice_principal', 'school_event_coordinator', 'school_sports_coordinator', 'school_kalotsavam_coordinator', 'school_mcq_coordinator', 'school_training_coordinator', 'school_finance_coordinator', 'school_staff', 'group_admin', 'house_admin'];
    }

    /** @return list<string> */
    public static function assignableRolesFor(User $user): array
    {
        if ($user->hasRole('school_principal')) {
            return self::schoolPrincipalCreatableRoles();
        }
        if ($user->hasRole('school_vice_principal')) {
            return self::schoolVicePrincipalCreatableRoles();
        }
        if ($user->hasRole('school_admin')) {
            return self::schoolAdminCreatableRoles();
        }

        return [];
    }

    /** @return list<string> */
    public static function schoolManagementRoles(): array
    {
        return ['school_principal', 'school_vice_principal', 'school_admin'];
    }

    /** School roles subject to Spatie write-permission checks (non-leadership). */
    /** @return list<string> */
    public static function schoolWriteGatedRoles(): array
    {
        return ['school_staff', 'group_admin', 'house_admin'];
    }

    /** Event coordinators manage assigned fest/MCQ routes — not read-only staff. */
    /** @return list<string> */
    public static function schoolEventCoordinatorRoles(): array
    {
        return ['school_event_coordinator'];
    }

    /** @return list<string> */
    public static function schoolPanelRoles(): array
    {
        return [
            'school_principal', 'school_vice_principal', 'school_admin',
            'school_staff', 'school_event_coordinator', 'school_sports_coordinator', 'school_kalotsavam_coordinator', 'school_mcq_coordinator', 'school_training_coordinator', 'school_finance_coordinator', 'group_admin', 'house_admin',
        ];
    }

    /** @return list<string> */
    public static function festEventDuties(): array
    {
        return ['coordinator', 'stage', 'registration', 'attendance', 'food', 'appeals', 'certificates', 'marks', 'discipline', 'admit_cards'];
    }

    /** @return array<string, string> */
    public static function roleLabels(): array
    {
        return [
            'sahodaya_staff'           => 'Sahodaya staff (view + optional permissions)',
            'registration_coordinator' => 'Registration coordinator',
            'sahodaya_finance'         => 'Sahodaya finance',
            'certificate_collector'    => 'Certificate collector',
            'data_entry'               => 'Data entry',
            'event_coordinator'        => 'Event coordinator',
            'school_staff'             => 'School staff (view + optional permissions)',
            'school_principal'         => 'School principal (full access + user management)',
            'school_vice_principal'    => 'Vice principal (manage coordinators & staff)',
            'school_event_coordinator' => 'Event coordinator (assigned programs/events only)',
            'judge'                    => 'Judge',
            'mark_entry_admin'         => 'Mark entry admin',
            'mark_entry_coordinator'   => 'Mark entry coordinator',
            'exam_controller'          => 'Exam controller',
            'exam_staff'               => 'Exam hall staff',
            'fest_ops'                 => 'Event operations (assigned per event)',
            'group_admin'              => 'Class / group admin',
            'house_admin'              => 'House admin (intra-school)',
        ];
    }

    /** @return list<string> */
    public static function sportsFestEventDuties(): array
    {
        return ['coordinator', 'registration', 'attendance', 'appeals', 'marks', 'certificates', 'admit_cards', 'food'];
    }

    /** @return array<string, string> */
    public static function sportsDutyLabels(): array
    {
        return array_merge(self::dutyLabels(), [
            'marks' => 'Item head coordinator',
        ]);
    }

    /** @return array<string, string> */
    public static function dutyLabels(): array
    {
        return [
            'coordinator'  => 'Head of event (coordinator)',
            'stage'        => 'Stage manager',
            'registration' => 'Registration desk',
            'attendance'   => 'Attendance officer',
            'food'         => 'Food / catering',
            'appeals'      => 'Appeals officer',
            'certificates' => 'Certificates',
            'marks'        => 'Mark entry coordinator',
            'discipline'   => 'Discipline / item head admin',
            'admit_cards'  => 'Admit cards desk',
        ];
    }

    /** @return list<string> */
    public static function allPermissions(): array
    {
        return [
            'fest.view',
            'fest.manage',
            'fest.marks',
            'fest.registrations',
            'fest.results',
            'fest.finance',
            'fest.settings',
            'fest.catering',
            'fest.schedule',
            'fest.certificates',
            'training.view',
            'training.manage',
            'finance.view',
            'mcq.view',
            'mcq.manage',
            'mcq.attendance',
            'mcq.marks',
            'membership.view',
            'membership.manage',
            'website.view',
            'website.news',
            'website.manage',
            'users.manage',
        ];
    }

    /** @return list<string> */
    public static function sahodayaStaffDefaults(): array
    {
        return ['fest.view', 'mcq.view', 'membership.view', 'website.view'];
    }

    /** @return list<string> */
    public static function schoolStaffDefaults(): array
    {
        return ['fest.view', 'website.view'];
    }

    /** Roles that use dedicated portals instead of the Sahodaya admin panel. */
    /** @return list<string> */
    public static function sahodayaPortalOnlyRoles(): array
    {
        return ['judge', 'fest_ops', 'exam_controller', 'exam_staff', 'mark_entry_coordinator'];
    }

    /** Roles allowed into /sahodaya-admin/{tenantId} (includes primary admin). */
    /** @return list<string> */
    public static function sahodayaAdminPanelRoles(): array
    {
        return array_values(array_unique(array_merge(
            ['sahodaya_admin'],
            array_diff(self::sahodayaAssignableRoles(), self::sahodayaPortalOnlyRoles()),
        )));
    }

    /** Roles that receive Spatie permissions (not portal-only operational roles). */
    /** @return list<string> */
    public static function sahodayaPermissionRoles(): array
    {
        return [
            'sahodaya_staff',
            'registration_coordinator',
            'sahodaya_finance',
            'certificate_collector',
            'data_entry',
            'event_coordinator',
            'mark_entry_admin',
        ];
    }

    /** @return list<string> */
    public static function defaultPermissionsForRole(string $role, string $tenantType = 'sahodaya'): array
    {
        if ($tenantType === 'school' && in_array($role, ['school_staff', 'school_vice_principal'], true)) {
            return $role === 'school_vice_principal'
                ? array_merge(self::schoolStaffDefaults(), ['users.manage'])
                : self::schoolStaffDefaults();
        }

        if ($tenantType === 'school' && $role === 'school_event_coordinator') {
            return ['fest.view', 'fest.manage', 'mcq.view', 'mcq.manage'];
        }

        if ($tenantType === 'school' && $role === 'school_sports_coordinator') {
            return ['fest.view', 'fest.manage', 'fest.registrations'];
        }

        if ($tenantType === 'school' && $role === 'school_kalotsavam_coordinator') {
            return ['fest.view', 'fest.manage', 'fest.registrations'];
        }

        if ($tenantType === 'school' && $role === 'school_mcq_coordinator') {
            return ['mcq.view', 'mcq.manage'];
        }

        if ($tenantType === 'school' && $role === 'school_training_coordinator') {
            return ['training.view'];
        }

        if ($tenantType === 'school' && $role === 'school_finance_coordinator') {
            return ['finance.view', 'fest.finance'];
        }

        return match ($role) {
            'sahodaya_staff'           => self::sahodayaStaffDefaults(),
            'registration_coordinator' => ['fest.view', 'fest.registrations'],
            'sahodaya_finance'         => ['fest.view', 'fest.finance', 'finance.view', 'membership.view'],
            'certificate_collector'    => ['fest.view', 'fest.certificates'],
            'data_entry'               => ['fest.view', 'fest.manage', 'fest.marks'],
            'event_coordinator'        => ['fest.view', 'fest.manage', 'fest.schedule', 'fest.settings'],
            'mark_entry_admin'         => ['fest.view', 'fest.marks'],
            default                    => [],
        };
    }

    /** @param  list<string>  $roles */
    public static function mergedDefaultPermissions(array $roles, string $tenantType = 'sahodaya'): array
    {
        $permissions = [];

        foreach ($roles as $role) {
            $permissions = array_merge($permissions, self::defaultPermissionsForRole($role, $tenantType));
        }

        return array_values(array_unique($permissions));
    }

    public static function writePermissionForPath(string $path): ?string
    {
        if (str_contains($path, '/users')) {
            return 'users.manage';
        }

        if (str_contains($path, '/ledger') || str_contains($path, '/state-remittances')) {
            return 'fest.finance';
        }

        if (str_contains($path, '/student-change-requests') || str_contains($path, '/students/verification')) {
            return 'membership.manage';
        }

        if (str_contains($path, '/membership') || str_contains($path, '/circulars') || str_contains($path, '/academic-years')) {
            return 'membership.manage';
        }

        if (str_contains($path, '/mcq')) {
            if (str_contains($path, '/attendance')) {
                return 'mcq.attendance';
            }
            if (str_contains($path, '/marks')) {
                return 'mcq.marks';
            }

            return 'mcq.manage';
        }

        if (preg_match('#/events/\d#', $path)) {
            if (str_contains($path, '/registrations')) {
                return 'fest.registrations';
            }
            if (str_contains($path, '/marks')) {
                return 'fest.marks';
            }
            if (str_contains($path, '/results')) {
                return 'fest.results';
            }
            if (str_contains($path, '/finance') || str_contains($path, '/school-fees') || str_contains($path, '/fees')) {
                return 'fest.finance';
            }
            if (str_contains($path, '/schedule')) {
                return 'fest.schedule';
            }
            if (str_contains($path, '/certificates')) {
                return 'fest.certificates';
            }
            if (str_contains($path, '/catering') || str_contains($path, '/food-coupons')) {
                return 'fest.catering';
            }
            if (self::pathRequiresFestSettings($path)) {
                return 'fest.settings';
            }

            return 'fest.manage';
        }

        if (preg_match('#/programs/[^/]+/catalog#', $path)) {
            return 'fest.manage';
        }

        if (preg_match('#/programs/[^/]+#', $path)) {
            return 'fest.manage';
        }

        if (str_contains($path, '/certificate-templates')) {
            return 'fest.certificates';
        }

        if (str_contains($path, '/events/certificates/search')) {
            return 'fest.certificates';
        }

        if (str_contains($path, '/display-screens')) {
            return 'fest.manage';
        }

        if (preg_match('#/(events|kalotsav|sports-meet|kids-fest|fest-programs|fest/|training)#', $path)) {
            return 'fest.manage';
        }

        if (str_contains($path, 'school-admin')) {
            if (preg_match('#/(programs|fest-programs|fest/|training)#', $path)) {
                if (str_contains($path, '/catering') || str_contains($path, '/food-coupons')) {
                    return 'fest.catering';
                }

                return 'fest.manage';
            }
            if (preg_match('#/(news|gallery|site-builder|public-content|office-bearers|testimonials|contact|staff|events|downloads)#', $path)) {
                return 'website.manage';
            }
        }

        if (str_contains($path, 'sahodaya-admin') && str_contains($path, '/public-content')) {
            return 'website.manage';
        }

        if (str_contains($path, 'sahodaya-admin')
            && preg_match('#/(site-builder|office-bearers|news|gallery)#', $path)) {
            return 'website.manage';
        }

        return null;
    }

    private static function pathRequiresFestSettings(string $path): bool
    {
        foreach ([
            '/settings', '/fee-settings', '/venues', '/combo-rules', '/grade-configs',
            '/point-rules', '/volunteers', '/participation-policy', '/clone',
            '/backfill-level-registrations',
        ] as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        return str_contains($path, '/items/') && str_contains($path, '/fee');
    }

    /**
     * Nav section keys → any one of these permissions grants visibility for sahodaya staff.
     *
     * @return array<string, list<string>>
     */
    public static function sahodayaNavPermissions(): array
    {
        return [
            'website'    => ['website.view', 'website.manage', 'website.news'],
            'membership' => ['membership.view', 'membership.manage'],
            'fest'       => ['fest.view', 'fest.manage', 'fest.marks', 'fest.registrations', 'fest.results', 'fest.settings', 'fest.finance', 'fest.certificates', 'fest.catering', 'fest.schedule'],
            'mcq'        => ['mcq.view', 'mcq.manage', 'mcq.attendance', 'mcq.marks'],
            'training'   => ['training.view', 'training.manage', 'fest.view', 'fest.manage'],
            'ledger'     => ['finance.view', 'membership.view', 'membership.manage', 'fest.finance'],
            'users'      => ['users.manage'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function schoolNavPermissions(): array
    {
        return [
            'students'   => ['fest.view', 'website.view', 'website.manage'],
            'membership' => ['membership.view', 'membership.manage'],
            'fest'       => ['fest.view', 'fest.manage'],
            'mcq'        => ['mcq.view', 'mcq.manage'],
            'training'   => ['training.view', 'training.manage', 'fest.view', 'fest.manage'],
            'website'    => ['website.view', 'website.manage', 'website.news'],
            'users'      => ['users.manage'],
        ];
    }

    /** @param  list<string>  $userPermissions */
    public static function staffCanSeeNav(string $section, array $userPermissions, string $panel = 'sahodaya'): bool
    {
        $map = $panel === 'school' ? self::schoolNavPermissions() : self::sahodayaNavPermissions();
        $required = $map[$section] ?? [];

        if ($required === []) {
            return true;
        }

        return count(array_intersect($required, $userPermissions)) > 0;
    }
}
