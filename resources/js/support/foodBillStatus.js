// Bill status is intentionally kept neutral (not the app-wide `.status-pill--open`
// emerald variant, which elsewhere means "registration window open") — an unsettled
// food bill is a normal, unremarkable state, not something to highlight green.
const BILL_LABELS = { open: 'Open', settled: 'Settled', cancelled: 'Cancelled' };
const BILL_CLASSES = {
    open: 'bg-slate-100 text-slate-700',
    settled: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};

export function billStatusLabel(status) {
    return BILL_LABELS[status] || status;
}

export function billStatusPillClass(status) {
    return BILL_CLASSES[status] || 'bg-slate-100 text-slate-700';
}

const COUPON_LABELS = { issued: 'Issued', redeemed: 'Redeemed', void: 'Void' };
const COUPON_CLASSES = {
    issued: 'bg-amber-50 text-amber-800',
    redeemed: 'bg-emerald-50 text-emerald-700',
    void: 'bg-slate-100 text-slate-500',
};

export function couponStatusLabel(status) {
    return COUPON_LABELS[status] || status;
}

export function couponStatusPillClass(status) {
    return COUPON_CLASSES[status] || 'bg-slate-100 text-slate-500';
}
