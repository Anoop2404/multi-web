/** Default empty fee/policy payload for Sports Event Head create/edit forms. */
export function emptyHeadFeeFields() {
    return {
        school_registration_fee: '',
        student_registration_fee: '',
        team_registration_fee: '',
        included_items_per_student: 0,
        included_teams: 0,
        verification_policy: 'all_students',
        approval_policy: 'auto',
        max_participants: '',
        max_teams: '',
    };
}

/** Map a FestItemHead record into the fee fields shape. */
export function headFeeFieldsFromRecord(record = {}) {
    return {
        school_registration_fee: record.school_registration_fee ?? '',
        student_registration_fee: record.student_registration_fee ?? '',
        team_registration_fee: record.team_registration_fee ?? '',
        included_items_per_student: record.included_items_per_student ?? 0,
        included_teams: record.included_teams ?? 0,
        verification_policy: record.verification_policy || 'all_students',
        approval_policy: record.approval_policy || 'auto',
        max_participants: record.max_participants ?? '',
        max_teams: record.max_teams ?? '',
    };
}
