/**
 * Which event-scoped pages and settings tabs apply per fest type.
 * Fest event registration fees are separate from Sahodaya annual membership fees.
 */

/** True when schools pay fest registration fees for this event (not membership). */
export function eventHasFestFees(event) {
    if (!event) return false;
    if (typeof event.fee_required === 'boolean') return event.fee_required;

    const settings = event.fee_settings ?? {};
    if (settings.fee_model === 'none') return false;
    if (settings.fee_model) return true;

    if (event.fee_type && event.fee_type !== 'none') return true;

    // Matches config/fest_fees.php level_defaults when no override is saved.
    const level = event.level_round ?? 'sahodaya';
    if (level === 'sahodaya') return true;

    return false;
}

export function capabilitiesForEvent(event) {
    const type = event?.event_type ?? 'custom';
    const hasEventFees = eventHasFestFees(event);

    return {
        type,
        hasEventFees,
        isKalolsavam: type === 'kalolsavam',
        isSports: type === 'sports',
        isTeacherFest: type === 'teacher_fest',
        athleticRecords: type === 'sports',
        catering: type === 'sports',
        foodCoupons: type === 'sports',
        houses: type === 'sports',
        championship: type === 'kalolsavam' || type === 'kids_fest' || type === 'english_fest' || type === 'science_fest',
        venues: true,
        comboRules: type === 'kalolsavam' || type === 'kids_fest' || type === 'english_fest' || type === 'science_fest' || type === 'custom',
        gradeBands: type !== 'sports',
        pointRules: true,
        volunteers: true,
        recordSettings: type === 'sports',
        ageGroupFees: type === 'sports' || type === 'kids_fest',
    };
}

export function settingsTabsForEvent(event) {
    const caps = capabilitiesForEvent(event);
    return [
        { id: 'fees', label: caps.isSports ? '💳 Fees & Windows' : '💳 Fees & Registration', icon: '💳' },
        { id: 'points', label: caps.isSports ? '🏆 Scoring & Rules' : '🏆 Points & Rules', icon: '🏆' },
        { id: 'venues', label: '📍 Venues & Numbering', icon: '📍' },
        { id: 'lifecycle', label: '⚙️ General & Operations', icon: '⚙️' },
    ];
}

export function settingsDescriptionForEvent(event) {
    const caps = capabilitiesForEvent(event);
    const feeNote = caps.hasEventFees
        ? 'fest registration fees'
        : 'no fest fees for this round';

    if (caps.isSports) {
        return `Setup hub, heads, items, rank points, ${feeNote}, registration windows, and athletic records. Not membership fees.`;
    }
    if (caps.isKalolsavam) {
        return `Locks, stages, combo rules, grades, points, ${feeNote}, and lifecycle. Not annual membership.`;
    }
    return `Locks, venues, rules, grading, ${feeNote}, and lifecycle. Separate from Sahodaya membership.`;
}
