/**
 * School admin — unified Board Result status. Merges the classic
 * BoardResult.status lifecycle (draft → submitted → verified → approved →
 * published) with the optional Principal Verification certification-package
 * pipeline (draft → … → submitted_to_sahodaya → sahodaya_verified →
 * approved → published) into one step sequence, so every screen shows the
 * same picture regardless of which status field it happens to read.
 */

const CERT_IN_FLIGHT = [
    'awaiting_leadership_review',
    'leadership_changes_requested',
    'awaiting_report_signatures',
    'individual_reports_signed',
    'awaiting_consolidated_signature',
    'school_certified',
];

const CERT_DONE = ['submitted_to_sahodaya', 'sahodaya_verified', 'approved', 'published'];

const AFTER_CERT_KEYS = ['submitted', 'verified', 'approved', 'published'];

function humanize(status) {
    return (status || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * @param {{ boardResult: object|null, certificationPackage?: object|null, certificationRequired?: boolean }} options
 * @returns {Array<{key: string, label: string, href?: string, state: string, hint?: string}>}
 */
export function buildBoardResultWorkflowSteps({ boardResult, certificationPackage = null, certificationRequired = false } = {}) {
    if (!boardResult) {
        return [];
    }

    const status = boardResult.status;
    const showCertification = certificationRequired || !!certificationPackage;
    const pkgStatus = certificationPackage?.status ?? null;
    const allDone = status === 'published';

    const steps = [
        {
            key: 'preparing',
            label: 'Preparing',
            state: allDone
                ? 'done'
                : status === 'rejected'
                    ? 'current'
                    : (showCertification ? !!pkgStatus && pkgStatus !== 'draft' : status !== 'draft')
                        ? 'done'
                        : 'current',
            hint: status === 'rejected' ? 'Sent back for correction' : undefined,
        },
    ];

    if (showCertification) {
        steps.push({
            key: 'certification',
            label: 'Leadership Review',
            state: allDone
                ? 'done'
                : !pkgStatus || pkgStatus === 'draft'
                    ? 'pending'
                    : CERT_IN_FLIGHT.includes(pkgStatus)
                        ? 'current'
                        : CERT_DONE.includes(pkgStatus)
                            ? 'done'
                            : 'current', // defensive: an unexpected/returned status still reads as "in progress" rather than silently vanishing
            hint: pkgStatus && pkgStatus !== 'draft' && !CERT_DONE.includes(pkgStatus) ? humanize(pkgStatus) : undefined,
        });
    }

    AFTER_CERT_KEYS.forEach((key) => {
        steps.push({
            key,
            label: humanize(key),
            state: allDone
                ? 'done'
                : status === key
                    ? 'current'
                    : AFTER_CERT_KEYS.indexOf(status) > AFTER_CERT_KEYS.indexOf(key)
                        ? 'done'
                        : 'pending',
        });
    });

    return steps;
}
