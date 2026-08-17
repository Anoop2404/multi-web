/**
 * Sahodaya admin — phased/regional-billing event conduct setup steps (Payment batches →
 * Phases → Regions → Sync). All four live as sections/actions on the same Phases.vue page
 * rather than separate routes, so steps intentionally carry no href -- this is a progress
 * indicator, not page-to-page navigation like buildMembershipWorkflowSteps().
 */

/** @returns {Array<{key: string, label: string, state: string, hint?: string}>} */
export function buildPhaseWorkflowSteps(options = {}) {
    const { batches = [], phases = [] } = options;

    const steps = [
        {
            key: 'batches',
            label: 'Payment batches',
            state: batches.length > 0 ? 'done' : 'current',
        },
        {
            key: 'phases',
            label: 'Phases',
            state: phases.length > 0 ? 'done' : batches.length > 0 ? 'current' : 'pending',
        },
    ];

    const regionalPhases = phases.filter((phase) => phase.is_regional);
    if (regionalPhases.length > 0) {
        const allConfigured = regionalPhases.every(
            (phase) => (phase.allowed_regions ?? []).some((region) => region.enabled),
        );
        steps.push({
            key: 'regions',
            label: 'Regions',
            state: allConfigured ? 'done' : 'current',
            hint: allConfigured ? undefined : 'Every regional phase needs at least one enabled region',
        });
    }

    steps.push({
        key: 'sync',
        label: 'Sync operational events',
        state: (batches.length > 0 && phases.length > 0) ? 'current' : 'pending',
        hint: 'Re-run after adding phases, regions, or items',
    });

    return steps;
}
