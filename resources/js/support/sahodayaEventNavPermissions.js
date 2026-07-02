/**
 * Permission filtering for Sahodaya events module sidebar.
 * Each nav item may declare `permissions: string[]` — staff users need any one match.
 */

export const FEST_VIEW = ['fest.view', 'fest.manage', 'fest.marks', 'fest.registrations', 'fest.results', 'fest.finance', 'fest.settings', 'fest.schedule', 'fest.certificates', 'fest.catering'];
export const FEST_MANAGE = ['fest.manage', 'fest.settings'];
export const FEST_SETTINGS = ['fest.settings', 'fest.manage'];
export const FEST_REGISTRATIONS = ['fest.registrations', 'fest.manage'];
export const FEST_SCHEDULE = ['fest.schedule', 'fest.manage', 'fest.settings'];
export const FEST_MARKS = ['fest.marks', 'fest.manage'];
export const FEST_RESULTS = ['fest.results', 'fest.manage'];
export const FEST_FINANCE = ['fest.finance'];
export const FEST_CERTIFICATES = ['fest.certificates', 'fest.manage'];
export const FEST_CATERING = ['fest.catering', 'fest.manage'];

/**
 * @param {Array<{ section: string, items: Array<{ permissions?: string[] }> }>} groups
 * @param {(item: { permissions?: string[] }) => boolean} canSeeItem
 */
export function filterNavByPermissions(groups, canSeeItem) {
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => canSeeItem(item)),
        }))
        .filter((group) => group.items.length > 0);
}

/**
 * @param {{ permissions?: string[] }} item
 * @param {string[]} userPermissions
 */
export function staffCanSeeNavItem(item, userPermissions) {
    const required = item.permissions ?? FEST_VIEW;
    if (!required.length) {
        return true;
    }

    return required.some((p) => userPermissions.includes(p));
}
