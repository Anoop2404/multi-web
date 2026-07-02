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
        championship: type === 'kalolsavam' || type === 'kids_fest',
        venues: true,
        comboRules: type === 'kalolsavam' || type === 'kids_fest' || type === 'custom',
        gradeBands: type !== 'sports',
        pointRules: true,
        volunteers: true,
        recordSettings: type === 'sports',
        ageGroupFees: type === 'sports' || type === 'kids_fest',
    };
}

export function settingsTabsForEvent(event) {
    const caps = capabilitiesForEvent(event);
    const tabs = [
        { id: 'lifecycle', label: 'Lifecycle' },
        { id: 'locks', label: 'Locks' },
    ];

    if (caps.venues) {
        tabs.push({ id: 'venues', label: caps.isSports ? 'Venues' : 'Venues & stages' });
    }

    if (caps.comboRules) {
        tabs.push({ id: 'combo', label: 'Combo rules' });
    }

    if (caps.gradeBands) {
        tabs.push({ id: 'grades', label: 'Grades' });
    }

    tabs.push({ id: 'points', label: 'Points' });
    if (caps.isSports) {
        tabs.push({ id: 'eligibility', label: 'Age cutoff' });
    }

    tabs.push({ id: 'participation', label: 'Participation' });

    if (caps.hasEventFees) {
        tabs.push({ id: 'fees', label: 'Event fees' });
    }

    tabs.push({ id: 'volunteers', label: 'Volunteers' });

    if (caps.recordSettings) {
        tabs.push({ id: 'records', label: 'Records' });
    }

    tabs.push({ id: 'clone', label: 'Clone' });

    return tabs;
}

export function settingsDescriptionForEvent(event) {
    const caps = capabilitiesForEvent(event);
    const feeNote = caps.hasEventFees
        ? 'fest registration fees'
        : 'no fest fees for this round';

    if (caps.isSports) {
        return `Locks, venues, grading, ${feeNote}, lifecycle, and athletic records. Not membership fees.`;
    }
    if (caps.isKalolsavam) {
        return `Locks, stages, combo rules, grades, points, ${feeNote}, and lifecycle. Not annual membership.`;
    }
    return `Locks, venues, rules, grading, ${feeNote}, and lifecycle. Separate from Sahodaya membership.`;
}
