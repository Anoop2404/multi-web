/** Labels and detail rows for fest event item listings (matches edit form fields). */

import { genderLabel, normalizeFestItemGender } from '@/support/festItemEligibility.js';

export function festItemParticipantTypeLabel(type) {
    return { individual: 'Individual', group: 'Group', team: 'Team' }[type] ?? type ?? 'Individual';
}

export function festItemGenderLabel(gender, taxonomy) {
    const normalized = normalizeFestItemGender(gender);
    if (!normalized || normalized === 'open') return 'Open';
    return taxonomy?.gender?.[normalized] ?? genderLabel(normalized) ?? normalized;
}

/** @returns {Array<{ label: string, value: string }>} */
export function festItemListingDetails(item, { taxonomy = {}, eventType = 'custom' } = {}) {
    const isSports = eventType === 'sports';
    const isKidsFest = eventType === 'kids_fest';
    const isArts = ['kalolsavam', 'kids_fest'].includes(eventType);
    const criteria = item.criteria_json ?? {};
    const fields = [];

    if (item.is_enabled === false) {
        fields.push({ label: 'Enabled', value: 'No' });
    }

    if (item.stage_type) {
        fields.push({ label: 'Stage', value: item.stage_type === 'on_stage' ? 'On stage' : 'Off stage' });
    }
    if (item.venue_type) {
        fields.push({ label: 'Venue', value: item.venue_type === 'outdoor' ? 'Outdoor' : 'Indoor' });
    }
    if (item.competition_format) {
        fields.push({
            label: 'Format',
            value: taxonomy.competition_format?.[item.competition_format] ?? item.competition_format,
        });
    }
    if (item.sport_discipline) {
        fields.push({
            label: 'Discipline',
            value: taxonomy.sport_discipline?.[item.sport_discipline] ?? item.sport_discipline,
        });
    }

    if (isSports && item.age_group) {
        fields.push({ label: 'Age group', value: taxonomy.age_group?.[item.age_group] ?? item.age_group });
    } else if (isKidsFest && item.kids_band) {
        fields.push({ label: 'Kids band', value: taxonomy.kids_band?.[item.kids_band] ?? item.kids_band });
    } else if (item.class_group) {
        fields.push({ label: 'Class', value: taxonomy.class_group?.[item.class_group] ?? item.class_group });
    }

    fields.push({ label: 'Gender', value: festItemGenderLabel(item.gender, taxonomy) });
    fields.push({ label: 'Participant', value: festItemParticipantTypeLabel(item.participant_type) });

    if (item.qualify_count != null && item.qualify_count !== '') {
        fields.push({ label: 'Qualifiers', value: String(item.qualify_count) });
    }
    if (item.max_per_school != null && item.max_per_school !== '') {
        fields.push({ label: 'Max/school', value: String(item.max_per_school) });
    }

    fields.push({
        label: 'Fee',
        value: item.fee_amount != null && item.fee_amount !== '' ? `₹${item.fee_amount}` : 'Default',
    });

    const squadParts = [];
    if (criteria.min_playing != null) squadParts.push(`min on field ${criteria.min_playing}`);
    if (criteria.max_subs != null) squadParts.push(`max subs ${criteria.max_subs}`);
    if (criteria.max_squad != null) squadParts.push(`max squad ${criteria.max_squad}`);
    if (criteria.min_squad != null) squadParts.push(`min squad ${criteria.min_squad}`);
    if (criteria.standbys != null) squadParts.push(`standbys ${criteria.standbys}`);
    if (item.min_group_size != null) squadParts.push(`min members ${item.min_group_size}`);
    if (item.max_group_size != null) squadParts.push(`max members ${item.max_group_size}`);
    if (item.squad_summary) {
        fields.push({ label: 'Squad rules', value: item.squad_summary });
    } else if (squadParts.length) {
        fields.push({ label: 'Squad rules', value: squadParts.join(', ') });
    }

    if (item.item_code) {
        fields.unshift({ label: 'Code', value: item.item_code });
    }

    return fields;
}

export function festItemTagsLine(item, options = {}) {
    return festItemListingDetails(item, options)
        .filter((f) => f.label !== 'Enabled')
        .map((f) => (['Fee', 'Qualifiers', 'Max/school'].includes(f.label) ? `${f.label}: ${f.value}` : f.value))
        .filter(Boolean)
        .join(' · ');
}

export function festItemSearchHaystack(item, options = {}) {
    return [
        item.title,
        item.item_code,
        festItemTagsLine(item, options),
        ...festItemListingDetails(item, options).map((f) => `${f.label} ${f.value}`),
        item.participant_type,
        item.is_enabled !== false ? 'on enabled' : 'off disabled',
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}
