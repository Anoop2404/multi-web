/** Shared labels for fest item gender / age eligibility display. */

const genderLabels = {
    male: 'Boys',
    female: 'Girls',
    mixed: 'Mixed',
    open: 'Open',
};

const maleGenderValues = new Set(['male', 'm', 'boy', 'boys']);
const femaleGenderValues = new Set(['female', 'f', 'girl', 'girls']);

/** Canonical fest item gender key for icons, filters, and labels. */
export function normalizeFestItemGender(gender) {
    const g = String(gender ?? '').trim().toLowerCase();
    if (!g || g === 'open') return 'open';
    if (maleGenderValues.has(g)) return 'male';
    if (femaleGenderValues.has(g)) return 'female';
    if (g === 'mixed' || g === 'common') return 'mixed';
    return g;
}

export function genderLabel(gender) {
    const normalized = normalizeFestItemGender(gender);
    if (!normalized || normalized === 'open') return null;
    return genderLabels[normalized] ?? normalized;
}

export function ageGroupLabel(key, labels = {}) {
    if (!key || key === 'open') return null;
    return labels[key] ?? String(key).toUpperCase();
}

/**
 * Build a short eligibility string for an item row (sports: U17 · Boys).
 */
export function formatItemEligibility(item, event, { ageGroupLabels = {}, classGroupLabels = {}, kidsBandLabels = {} } = {}) {
    const parts = [];

    if (event?.event_type === 'sports') {
        const age = item.age_group && item.age_group !== 'open' ? item.age_group : null;
        if (age) parts.push(ageGroupLabel(age, ageGroupLabels));
    } else if (event?.event_type === 'kalolsavam' && item.class_group && item.class_group !== 'open') {
        parts.push(classGroupLabels[item.class_group] ?? item.class_group.toUpperCase());
    } else if (event?.event_type === 'kids_fest' && item.kids_band && item.kids_band !== 'open') {
        parts.push(kidsBandLabels[item.kids_band] ?? item.kids_band);
    }

    const g = genderLabel(item.gender);
    if (g) parts.push(g);

    return parts.length ? parts.join(' · ') : null;
}

export function studentSportsHint(student) {
    const parts = [];
    if (student.sports_age_on_cutoff != null) {
        parts.push(`age ${student.sports_age_on_cutoff}`);
    }
    if (student.sports_age_group) {
        parts.push(String(student.sports_age_group).toUpperCase());
    }
    if (student.gender && !['open', ''].includes(student.gender)) {
        parts.push(genderLabel(student.gender) ?? student.gender);
    }
    return parts.join(' · ');
}
