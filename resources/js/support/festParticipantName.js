/** Display name for a fest participant (student or teacher). */
export function festParticipantName(participant) {
    if (!participant) return '—';
    return participant.student?.name ?? participant.teacher?.name ?? '—';
}

export function festParticipantRegNo(participant) {
    if (!participant) return '';
    return participant.student?.reg_no ?? participant.teacher?.reg_no ?? '';
}
