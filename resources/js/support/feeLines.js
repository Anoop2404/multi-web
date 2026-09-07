const ITEM_LINE_TYPES = new Set(['item_fee', 'item_fee_waived', 'extra_item']);

// Sports-composite fee breakdowns emit one line per registration (one per student per
// item) — see FestSportsCompositeFeeService::calculate()'s $extraLines walk, which is
// deliberately per-registration so each line's `meta` (student_id/registration_id) can
// drive per-registration payment-coverage tracking downstream (FestSchoolEventFeeService,
// FestEventFeesController). Merging that away at the source would break that tracking, so
// grouping happens here, display-only: same item + same fee type collapse into one row
// with a "N × ₹rate" label; the underlying lines/meta a school actually pays against are
// untouched.
export function groupItemFeeLines(lines) {
    const groups = new Map();
    const passthrough = [];

    for (const line of lines ?? []) {
        const type = String(line.line_type || '').toLowerCase();
        if (!ITEM_LINE_TYPES.has(type)) {
            passthrough.push(line);
            continue;
        }

        const key = `${type}:${line.meta?.item_id ?? line.label}`;
        const quantity = Number(line.quantity ?? 1);
        const amount = Number(line.amount ?? 0);

        if (groups.has(key)) {
            const g = groups.get(key);
            g.quantity += quantity;
            g.amount += amount;
        } else {
            groups.set(key, { ...line, quantity, amount, unit_amount: Number(line.unit_amount ?? amount) });
        }
    }

    const merged = Array.from(groups.values()).map(g => ({
        ...g,
        label: g.quantity > 1 ? `${g.label} (${g.quantity} × ₹${formatRate(g.unit_amount)})` : g.label,
    }));

    return [...passthrough, ...merged];
}

function formatRate(n) {
    return Number(n ?? 0).toLocaleString('en-IN');
}

// Total billable registration units across item-type lines. Cancelled/withdrawn/rejected
// registrations never reach these lines at all — FestSportsCompositeFeeService::calculate()
// and FestItemFeeResolver::billableRegistrations() both filter to submitted/approved/
// pending_approval before building them — so this count only ever reflects live registrations.
export function totalItemRegistrationCount(lines) {
    return (lines ?? [])
        .filter(line => ITEM_LINE_TYPES.has(String(line.line_type || '').toLowerCase()))
        .reduce((sum, line) => sum + Number(line.quantity ?? 1), 0);
}
