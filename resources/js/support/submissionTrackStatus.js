const LABELS = {
    pending: 'Not submitted',
    submitted: 'Awaiting review',
    approved: 'Approved',
    rejected: 'Rejected',
    not_applicable: 'Not required',
};

const PILL_CLASS = {
    pending: 'track-status-pill--pending',
    submitted: 'track-status-pill--submitted',
    approved: 'track-status-pill--approved',
    rejected: 'track-status-pill--rejected',
    not_applicable: 'track-status-pill--pending',
};

export function trackStatusLabel(status) {
    return LABELS[status] ?? status?.replace(/_/g, ' ') ?? '—';
}

export function trackStatusPillClass(status) {
    return PILL_CLASS[status] ?? 'track-status-pill--pending';
}
