export function isSportsTeamItem(item) {
    return ['group', 'team', 'pair', 'trio'].includes(item?.participant_type);
}

// Mirrors sports composite billing's base rate, before quota waivers, explicit
// item overrides and optional group surcharges.
export function sportsBaseRate(item, fees) {
    const team = isSportsTeamItem(item);
    const teamFee = Number(fees?.team_registration_fee || 0);
    if (team && teamFee > 0) return { amount: teamFee, unit: 'team entry' };
    const configured = fees?.default_item_fee;
    const amount = configured !== '' && configured != null ? Number(configured) : Number(fees?.student_registration_fee || 0);
    return { amount, unit: team ? 'performer' : 'individual entry' };
}
