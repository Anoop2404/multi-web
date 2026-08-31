/**
 * Auto-derive a breadcrumb ancestor trail from the current URL, reusing each panel's
 * nav data + active-item matching — the same logic that highlights the sidebar entry —
 * so pages get breadcrumbs without every <PageHeader> having to pass them explicitly.
 */
import { adminNavItemActive, stateAdminNav, superadminNav } from './adminNav.js';
import { adminNavItemActive as sahodayaItemActive, sahodayaAdminNav } from './sahodayaAdminNav.js';
import { schoolAdminNav, schoolNavItemActive } from './schoolAdminNav.js';

function longestMatch(groups, isActive) {
    let best = null;
    let bestLen = -1;

    for (const group of groups) {
        for (const item of group.items) {
            if (!isActive(item)) continue;
            const len = item.href.split('#')[0].split('?')[0].length;
            if (len > bestLen) {
                best = { group, item };
                bestLen = len;
            }
        }
    }

    return best;
}

function ancestorsFromMatch(dashboardHref, found) {
    if (!found || found.item.href === dashboardHref) {
        return [{ label: 'Dashboard', href: dashboardHref }];
    }

    return [
        { label: 'Dashboard', href: dashboardHref },
        { label: found.group.section },
        { label: found.item.label, href: found.item.href },
    ];
}

function sahodayaBreadcrumbs(url, props) {
    const match = (url ?? '').match(/\/sahodaya-admin\/([^/?#]+)/);
    if (!match) return [];
    const sahodayaId = match[1];
    const base = `/sahodaya-admin/${sahodayaId}`;

    const groups = sahodayaAdminNav(sahodayaId, {
        publicWebsiteEnabled: props.publicWebsiteEnabled ?? true,
        navVisibility: props.navVisibility ?? null,
        competitionPrograms: props.competitionPrograms ?? {},
        scopedEventTypes: props.scopedEventTypes ?? null,
        stateRemittancesEnabled: props.stateRemittancesEnabled !== false,
    });

    const found = longestMatch(groups, (item) => sahodayaItemActive(url, item.href, item.exact));
    return ancestorsFromMatch(base, found);
}

function schoolBreadcrumbs(url, props) {
    const match = (url ?? '').match(/\/school-admin\/([^/?#]+)/);
    if (!match) return [];
    const schoolId = match[1];
    const base = `/school-admin/${schoolId}`;

    const groups = schoolAdminNav(schoolId, {
        publicWebsiteEnabled: props.publicWebsiteEnabled ?? true,
        navVisibility: props.navVisibility ?? null,
        membershipPaid: props.membershipPaid !== false,
    });

    const found = longestMatch(groups, (item) => schoolNavItemActive(url, item.href, item.exact, item.matchQuery));
    return ancestorsFromMatch(base, found);
}

function adminBreadcrumbs(url, props) {
    const roles = props.auth?.user?.roles ?? [];
    const isSuperAdmin = roles.includes('superadmin');
    const isStateAdmin = !isSuperAdmin && roles.some((r) => ['state_admin', 'state_staff'].includes(r));

    if (!isSuperAdmin && !isStateAdmin) {
        return [];
    }

    const groups = isSuperAdmin
        ? superadminNav()
        : stateAdminNav();
    const base = isStateAdmin ? '/admin/state-dashboard' : '/admin/dashboard';

    const found = longestMatch(groups, (item) => adminNavItemActive(url, item.href, item.exact));
    return ancestorsFromMatch(base, found);
}

/** @returns {Array<{label: string, href?: string}>} */
export function autoBreadcrumbs(url, props = {}) {
    const path = (url ?? '').split('?')[0];

    if (path.startsWith('/sahodaya-admin/')) return sahodayaBreadcrumbs(url, props);
    if (path.startsWith('/school-admin/')) return schoolBreadcrumbs(url, props);
    if (path.startsWith('/admin')) return adminBreadcrumbs(url, props);

    return [];
}
