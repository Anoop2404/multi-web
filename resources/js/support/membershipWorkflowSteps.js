/**
 * School admin — annual membership registration workflow steps.
 */

function trackState(status, currentKey, key, doneStatuses = ['submitted', 'approved']) {
    if (currentKey === key) {
        return 'current';
    }
    if (status && doneStatuses.includes(status)) {
        return 'done';
    }
    if (!status || status === 'pending') {
        return 'pending';
    }
    if (status === 'rejected') {
        return 'current';
    }

    return 'pending';
}

/** @returns {Array<{key: string, label: string, href?: string, state: string, hint?: string}>} */
export function buildMembershipWorkflowSteps(schoolId, options = {}) {
    const { profile = null, registration = null, current = 'overview' } = options;
    const base = `/school-admin/${schoolId}/registration`;
    const submission = registration?.submission ?? null;
    const regStatus = registration?.registration_status ?? null;

    const steps = [
        {
            key: 'overview',
            label: 'Overview',
            href: base,
            state: current === 'overview' ? 'current' : registration ? 'done' : 'current',
        },
    ];

    if (profile?.student_data_mode === 'full_records') {
        steps.push({
            key: 'students',
            label: 'Student records',
            href: `${base}/students`,
            state: trackState(submission?.full_records_status, current, 'students'),
            hint: submission?.full_records_status === 'rejected' ? 'Needs resubmission' : undefined,
        });
    }

    if (profile?.student_data_mode === 'counts_only') {
        steps.push({
            key: 'counts',
            label: 'Student counts',
            href: `${base}/counts`,
            state: trackState(submission?.counts_status, current, 'counts'),
        });
    }

    if (profile?.teacher_registration_enabled) {
        steps.push({
            key: 'teachers',
            label: 'Teacher records',
            href: `${base}/teachers`,
            state: trackState(submission?.teacher_status, current, 'teachers'),
        });
    }

    const paymentUnlocked = !registration
        || !['data_pending', 'data_rejected'].includes(regStatus);

    const paymentDone = ['completed', 'approved'].includes(regStatus);
    const paymentActive = ['payment_pending', 'payment_submitted', 'payment_rejected'].includes(regStatus);

    steps.push({
        key: 'payment',
        label: 'Payment',
        href: paymentUnlocked ? `${base}/payment` : undefined,
        state: paymentDone
            ? 'done'
            : current === 'payment'
                ? 'current'
                : paymentActive
                    ? 'current'
                    : paymentUnlocked
                        ? 'pending'
                        : 'blocked',
        hint: regStatus === 'payment_rejected' ? 'Proof rejected — re-upload' : undefined,
    });

    steps.push({
        key: 'profile',
        label: 'Profile & account',
        href: `${base}/profile`,
        state: current === 'profile' ? 'current' : 'pending',
    });

    return steps;
}
